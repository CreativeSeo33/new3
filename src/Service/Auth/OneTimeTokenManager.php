<?php
declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\User;
use App\Entity\UserOneTimeToken;
use App\Repository\UserOneTimeTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class OneTimeTokenManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserOneTimeTokenRepository $repo,
        private readonly ParameterBagInterface $params,
    ) {}

    private function generateRaw(int $len = 48): string
    {
        return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
    }

    private function hash(string $raw, string $salt): string
    {
        $pepper = (string) ($this->params->get('env(APP_PEPPER)') ?? '');
        return hash_hmac('sha256', $salt . $raw, $pepper);
    }

    public function create(User $user, string $type, int $ttl): array
    {
        $raw = $this->generateRaw(48);
        $salt = $this->generateRaw(16);
        $hash = $this->hash($raw, $salt);
        $expires = (new \DateTimeImmutable())->modify("+{$ttl} seconds");

        $entity = new UserOneTimeToken($user, $type, $hash, $salt, $expires);
        $this->em->persist($entity);
        $this->em->flush();

        return [$raw, $entity];
    }

    public function consume(User $user, string $type, string $raw): bool
    {
        $now = new \DateTimeImmutable();
        $qb = $this->repo->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.used = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('now', $now)
            ->setMaxResults(100);
        $candidates = $qb->getQuery()->getResult();

        foreach ($candidates as $candidate) {
            $r = new \ReflectionClass($candidate);
            $saltProp = $r->getProperty('salt');
            $saltProp->setAccessible(true);
            $salt = (string) $saltProp->getValue($candidate);
            if (hash_equals($candidate->tokenHash, $this->hash($raw, $salt))) {
                $usedProp = $r->getProperty('used');
                $usedProp->setAccessible(true);
                $usedProp->setValue($candidate, true);
                $this->em->flush();
                return true;
            }
        }
        return false;
    }
}


