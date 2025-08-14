<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\CartItemRepository;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'cart_item')]
#[ORM\UniqueConstraint(name: 'uniq_cart_product', columns: ['cart_id', 'product_id'])]
class CartItem
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private ?Cart $cart = null;

	#[ORM\ManyToOne(targetEntity: Product::class)]
	#[ORM\JoinColumn(nullable: false)]
	private ?Product $product = null;

	#[ORM\Column]
	private string $productName = '';

	#[ORM\Column(type: 'integer')]
	private int $unitPrice = 0;

	#[ORM\Column(type: 'integer')]
	private int $qty = 1;

	#[ORM\Column(type: 'integer')]
	private int $rowTotal = 0;

	#[ORM\Column(type: 'integer', options: ['default' => 1])]
	#[ORM\Version]
	private int $version = 1;

	public function getId(): ?int { return $this->id; }

	public function getCart(): ?Cart { return $this->cart; }

	public function setCart(?Cart $cart): void { $this->cart = $cart; }

	public function getProduct(): ?Product { return $this->product; }

	public function setProduct(Product $product): void { $this->product = $product; }

	public function getProductName(): string { return $this->productName; }

	public function setProductName(string $name): void { $this->productName = $name; }

	public function getUnitPrice(): int { return $this->unitPrice; }

	public function setUnitPrice(int $price): void { $this->unitPrice = $price; }

	public function getQty(): int { return $this->qty; }

	public function setQty(int $qty): void { $this->qty = $qty; }

	public function getRowTotal(): int { return $this->rowTotal; }

	public function setRowTotal(int $sum): void { $this->rowTotal = $sum; }

	public function getVersion(): int { return $this->version; }
}


