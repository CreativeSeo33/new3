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
use Doctrine\DBAL\Types\Types;

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

    // Имя для отображения/профиля (первое имя из формы checkout)
    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    #[Groups(['user:get','user:post','user:patch'])]
    private ?string $firstName = null;

    // Email для клиентской аутентификации (нормализуется в lower)
    #[ORM\Column(type: 'string', length: 180, unique: true, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 32, unique: true, nullable: true)]
    #[Groups(['user:get','user:post','user:patch'])]
    private ?string $phone = null;
    
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

    // Верификация email
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isVerified = false;

    // Аудит/безопасность
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lockedUntil = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    // Немедленная инвалидция access-токенов
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $tokenVersion = 0;

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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName !== null ? trim($firstName) : null;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email ? mb_strtolower(trim($email)) : null;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone !== null ? trim($phone) : null;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $verified): self
    {
        $this->isVerified = $verified;
        return $this;
    }

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $attempts): self
    {
        $this->failedLoginAttempts = max(0, $attempts);
        return $this;
    }

    public function getLockedUntil(): ?\DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeImmutable $until): self
    {
        $this->lockedUntil = $until;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $at): self
    {
        $this->lastLoginAt = $at;
        return $this;
    }

    public function getTokenVersion(): int
    {
        return $this->tokenVersion;
    }

    public function incrementTokenVersion(): self
    {
        $this->tokenVersion++;
        return $this;
    }
}


