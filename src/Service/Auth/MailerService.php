<?php
declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment as Twig;

final class MailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Twig $twig,
    ) {}

    public function sendVerifyEmail(string $to, string $link): void
    {
        $html = $this->twig->render('email/auth_verify.html.twig', [ 'link' => $link ]);
        $this->mailer->send((new Email())
            ->to($to)
            ->subject('Подтверждение email')
            ->html($html)
        );
    }

    public function sendPasswordReset(string $to, string $link): void
    {
        $html = $this->twig->render('email/auth_password_reset.html.twig', [ 'link' => $link ]);
        $this->mailer->send((new Email())
            ->to($to)
            ->subject('Восстановление пароля')
            ->html($html)
        );
    }
}


