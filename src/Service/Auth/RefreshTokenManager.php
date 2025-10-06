<?php
declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\UserRefreshToken;
use App\Repository\UserRefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

final class RefreshTokenManager
{
    private const COOKIE_NAME = '__Host-ref';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRefreshTokenRepository $repo,
        private readonly ParameterBagInterface $params,
    ) {
    }

    public function generateRawToken(int $length = 64): string
    {
        // URL-safe base64 без '='
        return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
    }

    private function hash(string $raw, string $salt): string
    {
        $pepper = (string) ($this->params->get('env(APP_PEPPER)') ?? '');
        return hash_hmac('sha256', $salt . $raw, $pepper);
    }

    public function create(User $user, Request $request): array
    {
        $raw = $this->generateRawToken(48);
        $salt = $this->generateRawToken(16);
        $hash = $this->hash($raw, $salt);

        $ttl = (int) $this->params->get('app.auth.refresh_ttl');
        $expiresAt = (new \DateTimeImmutable())->modify("+{$ttl} seconds");

        $token = new UserRefreshToken($user, $hash, $salt, $expiresAt);
        $tokenUa = substr(hash('sha256', (string) $request->headers->get('User-Agent')), 0, 64);
        $tokenIp = substr(hash('sha256', (string) $request->getClientIp()), 0, 64);

        $r = new \ReflectionClass($token);
        $uaProp = $r->getProperty('uaHash');
        $uaProp->setAccessible(true);
        $uaProp->setValue($token, $tokenUa);
        $ipProp = $r->getProperty('ipHash');
        $ipProp->setAccessible(true);
        $ipProp->setValue($token, $tokenIp);

        $this->em->persist($token);
        $this->em->flush();

        return [$raw, $token];
    }

    public function makeCookie(string $rawToken): Cookie
    {
        $ttl = (int) $this->params->get('app.auth.refresh_ttl');
        $expire = (new \DateTimeImmutable())->modify("+{$ttl} seconds");
        return Cookie::create(self::COOKIE_NAME, $rawToken, $expire, '/', null, true, true, false, Cookie::SAMESITE_STRICT);
    }

    public function expireCookie(): Cookie
    {
        return Cookie::create(self::COOKIE_NAME, '', (new \DateTimeImmutable('@0')), '/', null, true, true, false, Cookie::SAMESITE_STRICT);
    }

    public function verify(User $user, string $raw): ?UserRefreshToken
    {
        // Небольшая выборка по пользователю с неистекшими/неотозванными токенами
        $now = new \DateTimeImmutable();
        $qb = $this->repo->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.revoked = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->setMaxResults(100);
        $candidates = $qb->getQuery()->getResult();

        foreach ($candidates as $candidate) {
            $r = new \ReflectionClass($candidate);
            $saltProp = $r->getProperty('salt');
            $saltProp->setAccessible(true);
            $salt = (string) $saltProp->getValue($candidate);
            $hashProp = $r->getProperty('tokenHash');
            $hashProp->setAccessible(true);
            $storedHash = (string) $hashProp->getValue($candidate);
            if (hash_equals($storedHash, $this->hash($raw, $salt))) {
                return $candidate;
            }
        }
        return null;
    }

    public function verifyGlobal(string $raw): ?UserRefreshToken
    {
        $now = new \DateTimeImmutable();
        $qb = $this->repo->createQueryBuilder('t')
            ->andWhere('t.revoked = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('now', $now)
            ->setMaxResults(500);
        $candidates = $qb->getQuery()->getResult();
        foreach ($candidates as $candidate) {
            $r = new \ReflectionClass($candidate);
            $saltProp = $r->getProperty('salt');
            $saltProp->setAccessible(true);
            $salt = (string) $saltProp->getValue($candidate);
            $hashProp = $r->getProperty('tokenHash');
            $hashProp->setAccessible(true);
            $storedHash = (string) $hashProp->getValue($candidate);
            if (hash_equals($storedHash, $this->hash($raw, $salt))) {
                return $candidate;
            }
        }
        return null;
    }

    public function rotate(UserRefreshToken $current, User $user, Request $request): array
    {
        // Отзываем текущий и создаём новый
        $r = new \ReflectionClass($current);
        $revokedProp = $r->getProperty('revoked');
        $revokedProp->setAccessible(true);
        $revokedProp->setValue($current, true);
        $rotatedAtProp = $r->getProperty('rotatedAt');
        $rotatedAtProp->setAccessible(true);
        $rotatedAtProp->setValue($current, new \DateTimeImmutable());

        [$raw, $new] = $this->create($user, $request);
        $this->em->flush();

        return [$raw, $new];
    }

    public function revoke(UserRefreshToken $token): void
    {
        $r = new \ReflectionClass($token);
        $revokedProp = $r->getProperty('revoked');
        $revokedProp->setAccessible(true);
        $revokedProp->setValue($token, true);
        $this->em->flush();
    }

    public function revokeAll(User $user): void
    {
        $qb = $this->repo->createQueryBuilder('t')
            ->update()
            ->set('t.revoked', ':true')
            ->andWhere('t.user = :user')
            ->setParameter('true', true)
            ->setParameter('user', $user);
        $qb->getQuery()->execute();
    }
}


