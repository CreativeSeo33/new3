<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\CarouselRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\Validator\Constraints as Assert;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;

#[ApiResource(
    operations: [
        new Get(),
        new Patch(
            denormalizationContext: ['groups' => ['carousel:patch']]
        ),
        new Delete(),
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['carousel:get']],
)]
#[ApiFilter(SearchFilter::class,
    properties: ['place' => 'exact']
)]
/** не хватает attributes={"order"={"sort": "ASC"}} */
/**collectionOperations={"get",
 *     "post"={
 *             "denormalization_context"={"groups"={"carousel:post"}}
 *         },
 *     },
*/
#[ORM\Entity(repositoryClass: CarouselRepository::class)]
#[ORM\Index(columns: ["place"], name: "carousel_place_idx")]
#[ORM\Index(columns: ["sort"], name: "carousel_sort_idx")]
class Carousel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product:get', 'carousel:get'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['product:get', 'carousel:get', 'carousel:patch', 'carousel:post'])]
    #[Assert\NotBlank(groups: ['carousel:post'])]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['product:get', 'carousel:get', 'carousel:patch', 'carousel:post'])]
    private ?array $productsId = [];

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'carousels', fetch: 'EXTRA_LAZY')]
    #[Groups(['carousel:patch', 'carousel:get', 'carousel:post'])]
    private ?Product $product = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['product:get', 'carousel:get', 'carousel:patch', 'carousel:post'])]
    private ?int $sort = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['carousel:get', 'carousel:patch', 'carousel:post'])]
    #[Assert\Length(max: 255)]
    private ?string $place = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getProductsId(): ?array
    {
        return $this->productsId ?? [];
    }

    public function setProductsId(?array $productsId): self
    {
        $this->productsId = $productsId;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getSort(): ?int
    {
        return $this->sort;
    }

    public function setSort(?int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function getPlace(): ?string
    {
        return $this->place;
    }

    public function setPlace(?string $place): self
    {
        $this->place = $place;

        return $this;
    }
}
