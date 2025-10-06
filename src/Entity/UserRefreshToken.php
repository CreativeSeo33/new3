<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRefreshTokenRepository;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: UserRefreshTokenRepository::class)]
#[ORM\Table(name: 'user_refresh_token')]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['expires_at'])]
class UserRefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    // Хеш refresh токена (HMAC-SHA256), raw не храним
    #[ORM\Column(type: Types::STRING, length: 128)]
    private string $tokenHash;

    // Пер-токенный salt
    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $salt;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $revoked = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $rotatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // Отпечатки окружения (не raw)
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $uaHash = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $ipHash = null;

    public function __construct(User $user, string $tokenHash, string $salt, \DateTimeImmutable $expiresAt)
    {
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->salt = $salt;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
}


