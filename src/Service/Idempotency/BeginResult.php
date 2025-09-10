<?php
declare(strict_types=1);

namespace App\Service\Idempotency;

final class BeginResult
{
    public function __construct(
        public string $type, // 'started' | 'replay' | 'conflict' | 'in_flight'
        public ?int $httpStatus = null,
        public mixed $responseData = null,
        public ?\DateTimeImmutable $expiresAt = null,
        public ?string $storedHash = null,
        public ?string $providedHash = null,
        public ?\DateTimeImmutable $keyReusedAt = null,
        public ?int $retryAfter = null
    ) {}

    public static function started(): self
    {
        return new self('started');
    }

    public static function replay(int $status, mixed $data, \DateTimeImmutable $exp): self
    {
        return new self('replay', $status, $data, $exp);
    }

    public static function conflict(string $stored, string $provided, \DateTimeImmutable $reusedAt): self
    {
        return new self('conflict', null, null, null, $stored, $provided, $reusedAt);
    }

    public static function inFlight(int $retryAfter = 5): self
    {
        return new self('in_flight', null, null, null, null, null, null, $retryAfter);
    }
}
