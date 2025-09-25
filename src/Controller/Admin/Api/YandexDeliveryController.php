<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Service\Yandex\YandexDeliveryApi;
use App\Dto\PickupPointDto;
use App\Service\PickupPointProcessor;
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
        $decoded = json_decode((string) $request->getContent(), true);
        $payload = is_array($decoded) ? $decoded : [];

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

    /**
     * Получение списка точек самопривоза и ПВЗ. Пустое тело → все доступные способы доставки.
     * POST /api/admin/yandex-delivery/pickup-points/list
     * Док: https://yandex.com/support/delivery-profile/ru/api/other-day/ref/2.-Tochki-samoprivoza-i-PVZ/apib2bplatformpickup-pointslist-post
     */
    #[Route('/api/admin/yandex-delivery/pickup-points/list', name: 'admin_yandex_delivery_pickup_points_list', methods: ['POST'])]
    public function pickupPointsList(Request $request): JsonResponse
    {
        $decoded = json_decode((string) $request->getContent(), true);
        $payload = is_array($decoded) ? $decoded : [];
        try {
            $result = $this->client->listPickupPoints($payload);
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

    /**
     * Синхронизировать ПВЗ из Яндекса в таблицу PvzPoints (полная перезапись).
     * POST /api/admin/yandex-delivery/pickup-points/sync
     */
    #[Route('/api/admin/yandex-delivery/pickup-points/sync', name: 'admin_yandex_delivery_pickup_points_sync', methods: ['POST'])]
    public function pickupPointsSync(Request $request, PickupPointProcessor $processor): JsonResponse
    {
        $decoded = json_decode((string) $request->getContent(), true);
        $payload = is_array($decoded) ? $decoded : [];
        try {
            $data = $this->client->listPickupPoints($payload);
            $pointsArr = is_array($data['points'] ?? null) ? (array) $data['points'] : [];
            $dtos = array_map(static fn(array $row) => PickupPointDto::fromYandex($row), $pointsArr);
            $processor->savePickupPoints($dtos);
            return $this->json(['ok' => true, 'saved' => count($dtos)]);
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


