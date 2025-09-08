<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: \App\Repository\CartRepository::class)]
#[ORM\Table(name: 'cart')]
#[ORM\Index(columns: ['token'])]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['expires_at'])]
class Cart
{
	#[ORM\Id]
	#[ORM\Column(type: 'ulid', unique: true)]
	private ?Ulid $id = null;

	#[ORM\Column(name: 'user_id', nullable: true)]
	private ?int $userId = null;

	#[ORM\Column(length: 36, unique: true, nullable: true)]
	private ?string $token = null;

	#[ORM\Column(length: 3)]
	private string $currency = 'RUB';

	#[ORM\Column(length: 16, options: ['default' => 'SNAPSHOT'])]
	private string $pricingPolicy = 'SNAPSHOT';

	#[ORM\Column(type: 'integer', options: ['default' => 0])]
	private int $subtotal = 0;

	#[ORM\Column(type: 'integer', options: ['default' => 0])]
	private int $discountTotal = 0;

	#[ORM\Column(type: 'integer', options: ['default' => 0])]
	private int $total = 0;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $createdAt;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $updatedAt;

	#[ORM\Column(type: 'datetime_immutable', nullable: true)]
	private ?\DateTimeImmutable $expiresAt = null;

	/** @var Collection<int, CartItem> */
	#[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
	private Collection $items;

	#[ORM\Column(type: 'integer', options: ['default' => 1])]
	#[ORM\Version]
	private int $version = 1;

	#[ORM\Column(length: 64, nullable: true)]
	private ?string $shippingMethod = null;

	#[ORM\Column(type: 'integer', options: ['default' => 0])]
	private int $shippingCost = 0;

	#[ORM\Column(length: 128, nullable: true)]
	private ?string $shipToCity = null;

	#[ORM\Column(type: 'json', nullable: true)]
	private ?array $shippingData = null;

	public function __construct()
	{
		$this->items = new ArrayCollection();
		$now = new \DateTimeImmutable();
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public static function newGuest(): self
	{
		$cart = new self();
		$cart->id = Ulid::fromString(Ulid::generate());
		$cart->token = Uuid::v4()->toRfc4122();
		$cart->expiresAt = (new \DateTimeImmutable())->modify('+30 days');
		return $cart;
	}

	public static function createNew(?int $userId, \DateTimeImmutable $ttlUntil): self
	{
		$cart = new self();
		$cart->id = Ulid::fromString(Ulid::generate());
		$cart->token = Uuid::v4()->toRfc4122(); // Генерируем токен для безопасности
		$cart->userId = $userId;
		$cart->createdAt = new \DateTimeImmutable(); // Явная инициализация
		$cart->updatedAt = new \DateTimeImmutable();
		$cart->expiresAt = $ttlUntil;
		return $cart;
	}

	public function prolong(\DateTimeImmutable $ttlUntil): void
	{
		$this->expiresAt = $ttlUntil;
		$this->updatedAt = new \DateTimeImmutable();
	}

	public function getId(): ?Ulid { return $this->id; }

	public function getIdString(): string { return $this->id?->toBase32() ?? ''; }

	public function getUserId(): ?int { return $this->userId; }

	public function setUserId(?int $userId): void { $this->userId = $userId; }

	public function getToken(): ?string { return $this->token; }

	public function setToken(?string $token): void { $this->token = $token; }

	public function getCurrency(): string { return $this->currency; }

	public function setCurrency(string $currency): void { $this->currency = $currency; }

	public function getPricingPolicy(): string { return $this->pricingPolicy; }

	public function setPricingPolicy(string $policy): void {
		if (!in_array($policy, ['SNAPSHOT', 'LIVE'], true)) {
			throw new \InvalidArgumentException('Invalid pricing policy. Allowed: SNAPSHOT, LIVE');
		}
		$this->pricingPolicy = $policy;
	}

	public function getSubtotal(): int { return $this->subtotal; }

	public function setSubtotal(int $subtotal): void { $this->subtotal = $subtotal; }

	public function getDiscountTotal(): int { return $this->discountTotal; }

	public function setDiscountTotal(int $discountTotal): void { $this->discountTotal = $discountTotal; }

	public function getTotal(): int { return $this->total; }

	public function setTotal(int $total): void { $this->total = $total; }

	public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

	public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

	public function setUpdatedAt(\DateTimeImmutable $time): void { $this->updatedAt = $time; }

	public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }

	public function setExpiresAt(?\DateTimeImmutable $time): void { $this->expiresAt = $time; }

	/** @return Collection<int, CartItem> */
	public function getItems(): Collection { return $this->items; }

	public function addItem(CartItem $item): void
	{
		if (!$this->items->contains($item)) {
			$this->items->add($item);
			$item->setCart($this);
		}
	}

	public function removeItem(CartItem $item): void
	{
		if ($this->items->removeElement($item)) {
			if ($item->getCart() === $this) {
				$item->setCart($this); // keep relation consistent for Doctrine orphanRemoval
			}
		}
	}

	public function getVersion(): int { return $this->version; }

	public function getShippingMethod(): ?string { return $this->shippingMethod; }

	public function setShippingMethod(?string $shippingMethod): void { $this->shippingMethod = $shippingMethod; }

	public function getShippingCost(): int { return $this->shippingCost; }

	public function setShippingCost(int $shippingCost): void { $this->shippingCost = $shippingCost; }

	public function getShipToCity(): ?string { return $this->shipToCity; }

	public function setShipToCity(?string $shipToCity): void { $this->shipToCity = $shipToCity; }

	public function getShippingData(): ?array { return $this->shippingData; }

	public function setShippingData(?array $shippingData): void { $this->shippingData = $shippingData; }

	/**
	 * Гарантирует наличие токена для гостевой корзины.
	 * Генерирует новый UUID токен, если его нет.
	 */
	public function ensureToken(): string
	{
		if (!$this->token) {
			$this->token = Uuid::v4()->toRfc4122();
		}
		return $this->token;
	}

    /**
     * Рассчитывает общее количество товарных единиц в корзине.
     * Например, 2 стула + 3 стола = 5.
     */
    public function getTotalItemQuantity(): int
    {
        $totalQuantity = 0;
        foreach ($this->items as $item) {
            $totalQuantity += $item->getQty();
        }
        return $totalQuantity;
    }
}


