<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\CheckoutContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

#[Route('/api/checkout')]
final class CheckoutDraftController extends AbstractController
{
    public function __construct(private readonly CheckoutContext $checkout) {}

    #[Route('/draft', name: 'api_checkout_draft_save', methods: ['POST'])]
    public function saveDraft(Request $request, CsrfTokenManagerInterface $csrf): JsonResponse
    {
        // CSRF
        $header = (string)$request->headers->get('X-CSRF-Token', '');
        if ($header === '' || !$csrf->isTokenValid(new CsrfToken('api', $header))) {
            return new JsonResponse(['error' => 'invalid_csrf'], 419);
        }

        $data = json_decode($request->getContent() ?: '[]', true) ?? [];

        $sanitize = static function (?string $s): string {
            $s = (string)$s;
            // remove control/invisible chars
            $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
            return trim($s);
        };

        $name = $sanitize($data['firstName'] ?? '');
        $phone = $sanitize($data['phone'] ?? '');
        $email = $sanitize($data['email'] ?? '');
        $comment = $sanitize($data['comment'] ?? '');

        // length limits
        if (mb_strlen($name) > 255 || mb_strlen($phone) > 255 || mb_strlen($email) > 255 || mb_strlen($comment) > 1000) {
            return new JsonResponse(['error' => 'payload_too_long'], 400);
        }

        // store partial draft into session
        $this->checkout->setCustomer([
            'name' => $name !== '' ? $name : null,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'comment' => $comment !== '' ? $comment : null,
        ]);
        if ($comment !== '') {
            $this->checkout->setComment($comment);
        }

        return new JsonResponse(['ok' => true]);
    }
}


