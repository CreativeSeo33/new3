<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class JwtTokenVersionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly EntityManagerInterface $em,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_CREATED => 'onJwtCreated',
            Events::JWT_DECODED => 'onJwtDecoded',
        ];
    }

    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $payload = $event->getData();
            $payload['tv'] = $user->getTokenVersion();
            $event->setData($payload);
        }
    }

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        if (!isset($payload['tv'], $payload['username'])) {
            return;
        }

        // username claim по умолчанию — идентификатор пользователя (у нас name), поэтому делаем быстрый запрос
        $repo = $this->em->getRepository(User::class);
        $user = $repo->findOneBy(['name' => $payload['username']]);
        if (!$user) {
            $event->markAsInvalid();
            return;
        }

        $cached = $this->cache->get('user_token_version_' . $user->getId(), function () use ($user) {
            return $user->getTokenVersion();
        });

        if ((int) $payload['tv'] !== (int) $cached) {
            $event->markAsInvalid();
        }
    }
}


