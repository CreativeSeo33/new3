<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use App\State\UserPasswordProcessor;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['user:get']]),
        new GetCollection(normalizationContext: ['groups' => ['user:get']]),
        new Post(
            denormalizationContext: ['groups' => ['user:post']],
            processor: UserPasswordProcessor::class
        ),
        new Patch(
            denormalizationContext: ['groups' => ['user:patch']],
            processor: UserPasswordProcessor::class
        ),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['user:get']],
    denormalizationContext: ['groups' => ['user:post']]
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:get'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['user:get','user:post','user:patch'])]
    #[Assert\Length(min: 2)]
    private string $name = '';

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['user:get','user:post','user:patch'])]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password = '';

    // Не маппится в БД, используется только для приема пароля из API
    #[Groups(['user:post','user:patch'])]
    #[Assert\Length(min: 6, groups: ['user:post'])]
    private ?string $plainPassword = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->name;
    }


    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }
        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): self
    {
        $this->password = $hashedPassword;
        return $this;
    }

    public function eraseCredentials(): void {}

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }
}


