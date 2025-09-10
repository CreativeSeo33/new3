<?php
declare(strict_types=1);

namespace App\Service\Idempotency;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Connection;

final class IdempotencyService
{
    public const TTL_HOURS = 48;
    public const STALE_PROCESSING_SECONDS = 120;

    public function __construct(private Connection $db) {}

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

        try {
            $this->db->insert('cart_idempotency', [
                'idempotency_key' => $key,
                'cart_id' => $cartId,
                'endpoint' => $endpoint,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'http_status' => null,
                'response_data' => null,
                'instance_id' => $instanceId,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
            ]);
            return BeginResult::started();
        } catch (UniqueConstraintViolationException) {
            return $this->handleUniqueConstraintViolation($key, $cartId, $endpoint, $requestHash, $nowUtc, $instanceId, $expiresAt, $maxRetries);
        } catch (DriverException $e) {
            // Handle MySQL deadlock (1213) and lock wait timeout (1205)
            if ($this->isRetryableException($e) && $maxRetries > 0) {
                usleep(random_int(10000, 50000)); // 10-50ms backoff
                return $this->begin($key, $cartId, $endpoint, $requestHash, $nowUtc, $instanceId, $maxRetries - 1);
            }
            throw $e;
        }
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
        // read existing
        $row = $this->db->fetchAssociative(
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
            try {
                $affected = $this->db->executeStatement(
                    'UPDATE cart_idempotency
                     SET status = \'processing\', request_hash = ?, cart_id = ?, endpoint = ?,
                         http_status = NULL, response_data = NULL, expires_at = ?
                     WHERE idempotency_key = ? AND expires_at < UTC_TIMESTAMP(3)',
                    [$requestHash, $cartId, $endpoint, $expiresAt->format('Y-m-d H:i:s.u'), $key]
                );
                if ($affected === 1) {
                    return BeginResult::started();
                }
            } catch (DriverException $e) {
                if ($this->isRetryableException($e) && $maxRetries > 0) {
                    usleep(random_int(10000, 50000));
                    return $this->begin($key, $cartId, $endpoint, $requestHash, $nowUtc, $instanceId, $maxRetries - 1);
                }
                throw $e;
            }
            // if not affected, fall-through to checks below
        }

        if ($row['status'] === 'processing') {
            // Check if processing record is stale (older than 120 seconds)
            $staleThreshold = $nowUtc->modify('-' . self::STALE_PROCESSING_SECONDS . ' seconds');
            if ($createdAtDb < $staleThreshold) {
                // Try to take over stale processing record
                try {
                    $affected = $this->db->executeStatement(
                        'UPDATE cart_idempotency
                         SET status = \'processing\', request_hash = ?, cart_id = ?, endpoint = ?,
                             http_status = NULL, response_data = NULL, expires_at = ?, created_at = UTC_TIMESTAMP(3)
                         WHERE idempotency_key = ? AND status = \'processing\' AND created_at < ?',
                        [
                            $requestHash,
                            $cartId,
                            $endpoint,
                            $expiresAt->format('Y-m-d H:i:s.u'),
                            $key,
                            $staleThreshold->format('Y-m-d H:i:s.u')
                        ]
                    );
                    if ($affected === 1) {
                        return BeginResult::started();
                    }
                } catch (DriverException $e) {
                    if ($this->isRetryableException($e) && $maxRetries > 0) {
                        usleep(random_int(10000, 50000));
                        return $this->begin($key, $cartId, $endpoint, $requestHash, $nowUtc, $instanceId, $maxRetries - 1);
                    }
                    throw $e;
                }
            }

            return BeginResult::inFlight(5);
        }

        // done
        if (hash_equals((string)$row['request_hash'], $requestHash)) {
            $data = $row['response_data'] ? json_decode((string)$row['response_data'], true) : null;
            return BeginResult::replay((int)$row['http_status'], $data, $expiresAtDb);
        }

        return BeginResult::conflict((string)$row['request_hash'], $requestHash, $createdAtDb);
    }

    private function isRetryableException(DriverException $e): bool
    {
        $code = $e->getCode();
        return in_array($code, [1213, 1205], true); // MySQL deadlock and lock wait timeout
    }

    public function finish(string $key, int $httpStatus, mixed $payload, int $maxRetries = 3): void
    {
        $json = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);

        try {
            $this->db->update('cart_idempotency', [
                'status' => 'done',
                'http_status' => $httpStatus,
                'response_data' => $json,
            ], ['idempotency_key' => $key]);
        } catch (DriverException $e) {
            if ($this->isRetryableException($e) && $maxRetries > 0) {
                usleep(random_int(10000, 50000)); // 10-50ms backoff
                $this->finish($key, $httpStatus, $payload, $maxRetries - 1); // Retry with reduced count
                return;
            }
            throw $e;
        }
    }
}
