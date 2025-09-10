<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: "App\Repository\CartIdempotencyRepository")]
#[ORM\Table(name: "cart_idempotency", indexes: [
    new ORM\Index(name: "idx_expires_at", columns: ["expires_at"]),
    new ORM\Index(name: "idx_cart_id", columns: ["cart_id"]),
    new ORM\Index(name: "idx_endpoint", columns: ["endpoint"])
])]
#[ORM\UniqueConstraint(name: "uk_idem_key", columns: ["idempotency_key"])]
class CartIdempotency
{
    /**
     * @ORM\Id
     * @ORM\Column(type="bigint", options={"unsigned": true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    #[ORM\Id]
    #[ORM\Column(type: "bigint", options: ["unsigned" => true])]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    #[ORM\Column(type: "string", length: 255, unique: true)]
    private string $idempotencyKey;

    /**
     * @ORM\Column(type="string", length=26)
     */
    #[ORM\Column(type: "string", length: 26)]
    private string $cartId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    #[ORM\Column(type: "string", length: 255)]
    private string $endpoint;

    /**
     * @ORM\Column(type="string", length=64)
     */
    #[ORM\Column(type: "string", length: 64)]
    private string $requestHash;

    /**
     * @ORM\Column(type="string", length=16)
     */
    #[ORM\Column(type: "string", length: 16)]
    private string $status;

    /**
     * @ORM\Column(type="smallint", nullable=true, options={"unsigned": true})
     */
    #[ORM\Column(type: "smallint", nullable: true, options: ["unsigned" => true])]
    private ?int $httpStatus = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    #[ORM\Column(type: "json", nullable: true)]
    private mixed $responseData = null;

    /**
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    #[ORM\Column(type: "string", length: 64, nullable: true)]
    private ?string $instanceId = null;

    /**
     * @ORM\Column(type="datetime_immutable", precision=3)
     */
    #[ORM\Column(type: "datetime_immutable", precision: 3)]
    private \DateTimeImmutable $createdAt;

    /**
     * @ORM\Column(type="datetime_immutable", precision=3)
     */
    #[ORM\Column(type: "datetime_immutable", precision: 3)]
    private \DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(string $idempotencyKey): self
    {
        $this->idempotencyKey = $idempotencyKey;
        return $this;
    }

    public function getCartId(): string
    {
        return $this->cartId;
    }

    public function setCartId(string $cartId): self
    {
        $this->cartId = $cartId;
        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function setRequestHash(string $requestHash): self
    {
        $this->requestHash = $requestHash;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(?int $httpStatus): self
    {
        $this->httpStatus = $httpStatus;
        return $this;
    }

    public function getResponseData(): mixed
    {
        return $this->responseData;
    }

    public function setResponseData(mixed $responseData): self
    {
        $this->responseData = $responseData;
        return $this;
    }

    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }

    public function setInstanceId(?string $instanceId): self
    {
        $this->instanceId = $instanceId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }
}
