<?php
declare(strict_types=1);

namespace App\Service\Idempotency;

use App\Entity\CartIdempotency;
use App\Repository\CartIdempotencyRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class IdempotencyService
{
    public const TTL_HOURS = 48;
    public const STALE_PROCESSING_SECONDS = 120;

    public function __construct(
        private EntityManagerInterface $em,
        private CartIdempotencyRepository $repository
    ) {}

    public function begin(
        string $key,
        string $cartId,
        string $endpoint,
        string $requestHash,
        \DateTimeImmutable $nowUtc,
        ?string $instanceId = null,
        int $maxRetries = 3
    ): BeginResult {
        $expiresAt = $nowUtc->modify('+' . self::TTL_HOURS . ' hours');

        // Create new entity
        $entity = new CartIdempotency();
        $entity->setIdempotencyKey($key)
               ->setCartId($cartId)
               ->setEndpoint($endpoint)
               ->setRequestHash($requestHash)
               ->setStatus('processing')
               ->setHttpStatus(null)
               ->setResponseData(null)
               ->setInstanceId($instanceId)
               ->setCreatedAt($nowUtc)
               ->setExpiresAt($expiresAt);

        // Try to insert
        $exception = $this->repository->tryInsert($entity, $maxRetries);
        if ($exception === null) {
            return BeginResult::started();
        }

        return $this->handleUniqueConstraintViolation($key, $cartId, $endpoint, $requestHash, $nowUtc, $instanceId, $expiresAt, $maxRetries);
    }

    private function handleUniqueConstraintViolation(
        string $key,
        string $cartId,
        string $endpoint,
        string $requestHash,
        \DateTimeImmutable $nowUtc,
        ?string $instanceId,
        \DateTimeImmutable $expiresAt,
        int $maxRetries = 3
    ): BeginResult {
        // read existing using direct SQL to avoid EntityManager issues
        $conn = $this->em->getConnection();
        $row = $conn->fetchAssociative(
            'SELECT * FROM cart_idempotency WHERE idempotency_key = ? LIMIT 1',
            [$key]
        );
        if (!$row) {
            // rare race; small backoff and retry once
            if ($maxRetries > 0) {
                usleep(random_int(1000, 3000));
                return $this->begin($key, $cartId, $endpoint, $requestHash, $nowUtc, $instanceId, $maxRetries - 1);
            }
            throw new \RuntimeException('Failed to handle unique constraint violation after retries');
        }

        $expiresAtDb = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $row['expires_at'], new \DateTimeZone('UTC'))
            ?: new \DateTimeImmutable($row['expires_at'], new \DateTimeZone('UTC'));
        $createdAtDb = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $row['created_at'], new \DateTimeZone('UTC'))
            ?: new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC'));

        if ($expiresAtDb < $nowUtc) {
            // try revive expired row
            if ($this->repository->tryReviveExpired($key, $requestHash, $cartId, $endpoint, $expiresAt, $maxRetries)) {
                return BeginResult::started();
            }
            // if not affected, fall-through to checks below
        }

        if ($row['status'] === 'processing') {
            // Check if processing record is stale (older than 120 seconds)
            $staleThreshold = $nowUtc->modify('-' . self::STALE_PROCESSING_SECONDS . ' seconds');
            if ($createdAtDb < $staleThreshold) {
                // Try to take over stale processing record
                if ($this->repository->tryTakeOverStale($key, $requestHash, $cartId, $endpoint, $expiresAt, $staleThreshold, $maxRetries)) {
                    return BeginResult::started();
                }
            }

            return BeginResult::inFlight(5);
        }

        // done
        if (hash_equals($row['request_hash'], $requestHash)) {
            $data = $row['response_data'] ? json_decode($row['response_data'], true) : null;
            return BeginResult::replay((int)$row['http_status'], $data, $expiresAtDb);
        }

        return BeginResult::conflict($row['request_hash'], $requestHash, $createdAtDb);
    }

    public function finish(string $key, int $httpStatus, mixed $payload, int $maxRetries = 3): void
    {
        $this->repository->finish($key, $httpStatus, $payload, $maxRetries);
    }
}
