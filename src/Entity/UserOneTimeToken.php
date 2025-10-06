<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Repository\UserOneTimeTokenRepository;

#[ORM\Entity(repositoryClass: UserOneTimeTokenRepository::class)]
#[ORM\Table(name: 'user_one_time_token')]
#[ORM\Index(columns: ['type'])]
#[ORM\Index(columns: ['expires_at'])]
class UserOneTimeToken
{
    public const TYPE_VERIFY_EMAIL = 'verify_email';
    public const TYPE_RESET_PASSWORD = 'reset_password';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 128)]
    public string $tokenHash;

    #[ORM\Column(type: Types::STRING, length: 64)]
    public string $salt;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    public bool $used = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $type, string $tokenHash, string $salt, \DateTimeImmutable $expiresAt)
    {
        $this->user = $user;
        $this->type = $type;
        $this->tokenHash = $tokenHash;
        $this->salt = $salt;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getType(): string { return $this->type; }
}


