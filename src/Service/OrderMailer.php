<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class OrderMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $params,
    ) {}

    public function sendConfirmation(Order $order): void
    {
        $to = $order->getCustomer()?->getEmail();
        if (!$to) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->params->get('app.notification.from_email'))
            ->to($to)
            ->subject(sprintf('Ваш заказ №%d принят', $order->getOrderId()))
            ->htmlTemplate('email/order-confirmation.html.twig')
            ->context([
                'order' => $order,
            ]);

        $this->mailer->send($email);
    }
}


