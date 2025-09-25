<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Service\Yandex\YandexDeliveryApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin API controller for Yandex Delivery integration.
 * Документация: https://yandex.com/support/delivery-profile/ru/api/other-day/access
 */
#[IsGranted('ROLE_ADMIN')]
final class YandexDeliveryController extends AbstractController
{
    public function __construct(private YandexDeliveryApi $client) {}

    /**
     * Проксирование запроса создания оффера в Яндекс Доставку.
     * POST /api/admin/yandex-delivery/offers/create
     */
    #[Route('/api/admin/yandex-delivery/offers/create', name: 'admin_yandex_delivery_offers_create', methods: ['POST'])]
    public function offersCreate(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true) ?: [];
        if (!is_array($payload)) { $payload = []; }

        try {
            $result = $this->client->createOffer($payload);
            return $this->json($result, 200);
        } catch (\Throwable $e) {
            $code = $e->getCode();
            $status = is_int($code) && $code >= 400 && $code < 600 ? $code : 502;
            return $this->json([
                'error' => 'yandex_delivery_error',
                'message' => $e->getMessage(),
            ], $status);
        }
    }
}


