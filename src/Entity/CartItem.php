<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\CartItemRepository;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
#[ORM\Table(name: 'cart_item')]
#[ORM\UniqueConstraint(name: 'uniq_cart_product_options', columns: ['cart_id', 'product_id', 'options_hash'])]
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

	#[ORM\Column(type: 'integer', options: ['default' => 0])]
	private int $optionsPriceModifier = 0;

	#[ORM\Column(type: 'integer', options: ['default' => 0])]
	private int $effectiveUnitPrice = 0;

	#[ORM\Column(type: 'string', length: 32, options: ['default' => ''])]
	private string $optionsHash = '';

	#[ORM\Column(type: 'json', nullable: true)]
	private ?array $selectedOptionsData = null;

	#[ORM\Column(type: 'json', nullable: true)]
	private ?array $optionsSnapshot = null;

	#[ORM\Column(type: 'datetime_immutable')]
	private \DateTimeImmutable $pricedAt;

	/**
	 * @var Collection<int, ProductOptionValueAssignment>
	 */
	#[ORM\ManyToMany(targetEntity: ProductOptionValueAssignment::class, cascade: ['persist'], inversedBy: 'cartItems')]
	#[ORM\JoinTable(name: 'cart_item_option_assignment')]
	private Collection $optionAssignments;

	#[ORM\Column(type: 'integer', options: ['default' => 1])]
	#[ORM\Version]
	private int $version = 1;

	public function __construct()
	{
		$this->optionAssignments = new ArrayCollection();
	}

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

	public function getOptionsPriceModifier(): int { return $this->optionsPriceModifier; }

	public function setOptionsPriceModifier(int $modifier): void { $this->optionsPriceModifier = $modifier; }

	public function getEffectiveUnitPrice(): int { return $this->effectiveUnitPrice; }

	public function setEffectiveUnitPrice(int $price): void { $this->effectiveUnitPrice = $price; }

	public function getOptionsHash(): string { return $this->optionsHash; }

	public function setOptionsHash(string $hash): void { $this->optionsHash = $hash; }

	public function getSelectedOptionsData(): ?array { return $this->selectedOptionsData; }

	public function setSelectedOptionsData(?array $data): void { $this->selectedOptionsData = $data; }

	public function getOptionsSnapshot(): ?array { return $this->optionsSnapshot; }

	public function setOptionsSnapshot(?array $snapshot): void { $this->optionsSnapshot = $snapshot; }

	public function getPricedAt(): \DateTimeImmutable { return $this->pricedAt; }

	public function setPricedAt(\DateTimeImmutable $pricedAt): void { $this->pricedAt = $pricedAt; }

	/**
	 * @return Collection<int, ProductOptionValueAssignment>
	 */
	public function getOptionAssignments(): Collection { return $this->optionAssignments; }

	public function addOptionAssignment(ProductOptionValueAssignment $assignment): void
	{
		if (!$this->optionAssignments->contains($assignment)) {
			$this->optionAssignments->add($assignment);
		}
	}

	public function removeOptionAssignment(ProductOptionValueAssignment $assignment): void
	{
		$this->optionAssignments->removeElement($assignment);
	}

	public function getVersion(): int { return $this->version; }
}


