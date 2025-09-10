<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CartIdempotency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartIdempotency>
 */
class CartIdempotencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartIdempotency::class);
    }

    /**
     * Find by idempotency key
     */
    public function findByIdempotencyKey(string $key): ?CartIdempotency
    {
        return $this->findOneBy(['idempotencyKey' => $key]);
    }

    /**
     * Try to insert new record, returns null if constraint violation
     */
    public function tryInsert(CartIdempotency $entity, int $maxRetries = 3): ?UniqueConstraintViolationException
    {
        try {
            $conn = $this->getEntityManager()->getConnection();
            $conn->insert('cart_idempotency', [
                'idempotency_key' => $entity->getIdempotencyKey(),
                'cart_id' => $entity->getCartId(),
                'endpoint' => $entity->getEndpoint(),
                'request_hash' => $entity->getRequestHash(),
                'status' => $entity->getStatus(),
                'http_status' => $entity->getHttpStatus(),
                'response_data' => $entity->getResponseData() === null ? null : json_encode($entity->getResponseData(), JSON_UNESCAPED_UNICODE),
                'instance_id' => $entity->getInstanceId(),
                'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s.u'),
                'expires_at' => $entity->getExpiresAt()->format('Y-m-d H:i:s.u'),
            ]);
            return null;
        } catch (UniqueConstraintViolationException $e) {
            return $e;
        } catch (DriverException $e) {
            // Handle MySQL deadlock (1213) and lock wait timeout (1205)
            if ($this->isRetryableException($e) && $maxRetries > 0) {
                usleep(random_int(10000, 50000)); // 10-50ms backoff
                return $this->tryInsert($entity, $maxRetries - 1);
            }
            throw $e;
        }
    }

    /**
     * Try to update expired record
     */
    public function tryReviveExpired(
        string $key,
        string $requestHash,
        string $cartId,
        string $endpoint,
        \DateTimeImmutable $expiresAt,
        int $maxRetries = 3
    ): bool {
        try {
            $qb = $this->createQueryBuilder('c');
            $affected = $qb->update()
                ->set('c.status', ':status')
                ->set('c.requestHash', ':requestHash')
                ->set('c.cartId', ':cartId')
                ->set('c.endpoint', ':endpoint')
                ->set('c.httpStatus', ':httpStatus')
                ->set('c.responseData', ':responseData')
                ->set('c.expiresAt', ':expiresAt')
                ->where('c.idempotencyKey = :key')
                ->andWhere('c.expiresAt < :now')
                ->setParameter('status', 'processing')
                ->setParameter('requestHash', $requestHash)
                ->setParameter('cartId', $cartId)
                ->setParameter('endpoint', $endpoint)
                ->setParameter('httpStatus', null)
                ->setParameter('responseData', null)
                ->setParameter('expiresAt', $expiresAt)
                ->setParameter('key', $key)
                ->setParameter('now', new \DateTimeImmutable())
                ->getQuery()
                ->execute();

            return $affected > 0;
        } catch (DriverException $e) {
            if ($this->isRetryableException($e) && $maxRetries > 0) {
                usleep(random_int(10000, 50000));
                return $this->tryReviveExpired($key, $requestHash, $cartId, $endpoint, $expiresAt, $maxRetries - 1);
            }
            throw $e;
        }
    }

    /**
     * Try to take over stale processing record
     */
    public function tryTakeOverStale(
        string $key,
        string $requestHash,
        string $cartId,
        string $endpoint,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $staleThreshold,
        int $maxRetries = 3
    ): bool {
        try {
            $qb = $this->createQueryBuilder('c');
            $affected = $qb->update()
                ->set('c.status', ':status')
                ->set('c.requestHash', ':requestHash')
                ->set('c.cartId', ':cartId')
                ->set('c.endpoint', ':endpoint')
                ->set('c.httpStatus', ':httpStatus')
                ->set('c.responseData', ':responseData')
                ->set('c.expiresAt', ':expiresAt')
                ->set('c.createdAt', ':createdAt')
                ->where('c.idempotencyKey = :key')
                ->andWhere('c.status = :processingStatus')
                ->andWhere('c.createdAt < :staleThreshold')
                ->setParameter('status', 'processing')
                ->setParameter('requestHash', $requestHash)
                ->setParameter('cartId', $cartId)
                ->setParameter('endpoint', $endpoint)
                ->setParameter('httpStatus', null)
                ->setParameter('responseData', null)
                ->setParameter('expiresAt', $expiresAt)
                ->setParameter('createdAt', new \DateTimeImmutable())
                ->setParameter('key', $key)
                ->setParameter('processingStatus', 'processing')
                ->setParameter('staleThreshold', $staleThreshold)
                ->getQuery()
                ->execute();

            return $affected > 0;
        } catch (DriverException $e) {
            if ($this->isRetryableException($e) && $maxRetries > 0) {
                usleep(random_int(10000, 50000));
                return $this->tryTakeOverStale($key, $requestHash, $cartId, $endpoint, $expiresAt, $staleThreshold, $maxRetries - 1);
            }
            throw $e;
        }
    }

    /**
     * Finish processing by updating status and response data
     */
    public function finish(string $key, int $httpStatus, mixed $payload, int $maxRetries = 3): void
    {
        try {
            $qb = $this->createQueryBuilder('c');
            $affected = $qb->update()
                ->set('c.status', ':status')
                ->set('c.httpStatus', ':httpStatus')
                ->set('c.responseData', ':responseData')
                ->where('c.idempotencyKey = :key')
                ->setParameter('status', 'done')
                ->setParameter('httpStatus', $httpStatus)
                ->setParameter('responseData', $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE))
                ->setParameter('key', $key)
                ->getQuery()
                ->execute();

            if ($affected === 0) {
                throw new \RuntimeException("Idempotency record not found for key: {$key}");
            }
        } catch (DriverException $e) {
            if ($this->isRetryableException($e) && $maxRetries > 0) {
                usleep(random_int(10000, 50000));
                $this->finish($key, $httpStatus, $payload, $maxRetries - 1);
                return;
            }
            throw $e;
        }
    }

    private function isRetryableException(DriverException $e): bool
    {
        $code = $e->getCode();
        return in_array($code, [1213, 1205], true); // MySQL deadlock and lock wait timeout
    }
}
