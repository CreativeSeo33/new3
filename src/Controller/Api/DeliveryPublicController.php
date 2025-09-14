<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PvzPoints;
use App\Repository\PvzPriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Публичные эндпойнты доставки для фронта каталога.
 * Контролируем выдачу вручную (без ApiResource), с кешированием и пагинацией.
 */
#[Route('/delivery')]
final class DeliveryPublicController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PvzPriceRepository $pvzPriceRepository,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $pointsDefaultLimit,
        private readonly int $pointsMaxLimit,
        private readonly int $publicTtlSeconds
    ) {}

    /**
     * GET /delivery/points?city=...&page=1&itemsPerPage=20
     * Возвращает список ПВЗ по городу с пагинацией и кешированием (по ключу город+страница+лимит).
     */
    #[Route('/points', name: 'public_delivery_points', methods: ['GET'])]
    public function points(Request $request): JsonResponse
    {
        $city = trim((string)$request->query->get('city', ''));
        $cityId = (int)$request->query->get('cityId', 0);
        if ($cityId <= 0 && $city === '') {
            return $this->json(['error' => 'city or cityId is required'], 422);
        }

        $page = max(1, (int)$request->query->get('page', 1));
        $limitReq = (int)$request->query->get('itemsPerPage', $this->pointsDefaultLimit);
        $limit = $limitReq > 0 ? min($limitReq, $this->pointsMaxLimit) : $this->pointsDefaultLimit;
        $offset = ($page - 1) * $limit;

        $cacheKey = 'delivery_points:' . md5(($cityId > 0 ? ('#' . $cityId) : $city) . '|' . $page . '|' . $limit);
        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            return $this->json($item->get());
        }

        $repo = $this->em->getRepository(PvzPoints::class);
        $qb = $repo->createQueryBuilder('p')
            ->select('p.id AS id, p.code AS code, p.name AS name, p.address AS address, p.city AS city, p.shirota AS shirota, p.dolgota AS dolgota, p.company AS company')
            ->orderBy('p.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if ($cityId > 0) {
            $qb->andWhere('IDENTITY(p.cityFias) = :cityId')->setParameter('cityId', $cityId);
        } else {
            $qb->andWhere('LOWER(TRIM(p.city)) = :city')
               ->setParameter('city', mb_strtolower(trim($city)));
        }

        $data = $qb->getQuery()->getArrayResult();

        // total count
        $countQb = $repo->createQueryBuilder('p')
            ->select('COUNT(p.id)');
        if ($cityId > 0) {
            $countQb->andWhere('IDENTITY(p.cityFias) = :cityId')->setParameter('cityId', $cityId);
        } else {
            $countQb->andWhere('LOWER(TRIM(p.city)) = :city')
                    ->setParameter('city', mb_strtolower(trim($city)));
        }
        $total = (int)$countQb->getQuery()->getSingleScalarResult();

        $response = [
            'data' => array_map(static function (array $row): array {
                return [
                    'id' => $row['id'] ?? null,
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'] ?? null,
                    'address' => $row['address'] ?? null,
                    'city' => $row['city'] ?? null,
                    'lat' => $row['shirota'] ?? null,
                    'lng' => $row['dolgota'] ?? null,
                    'company' => $row['company'] ?? null,
                ];
            }, $data),
            'total' => $total,
            'page' => $page,
            'itemsPerPage' => $limit,
        ];

        $item->set($response)->expiresAfter($this->publicTtlSeconds);
        $this->cache->save($item);

        return $this->json($response);
    }

    /**
     * Совместимость с планом: GET /api/delivery/pvz-points?city=...
     * Проксируем к тому же обработчику, меняем только URI.
     */
    #[Route('/pvz-points', name: 'public_delivery_pvz_points', methods: ['GET'])]
    public function pvzPoints(Request $request): JsonResponse
    {
        return $this->points($request);
    }

    /**
     * GET /delivery/price?city=...
     * Возвращает расчетную стоимость и срок доставки по городу.
     */
    #[Route('/price', name: 'public_delivery_price', methods: ['GET'])]
    public function price(Request $request): JsonResponse
    {
        $city = trim((string)$request->query->get('city', ''));
        if ($city === '') {
            return $this->json(['error' => 'city is required'], 422);
        }

        $cacheKey = 'delivery_price:' . md5($city);
        $item = $this->cache->getItem($cacheKey);
        if ($item->isHit()) {
            return $this->json($item->get());
        }

        $pvz = $this->pvzPriceRepository->findOneByCityNormalized($city);
        if ($pvz === null) {
            $payload = [
                'city' => $city,
                'available' => false,
                'message' => 'Город не найден в тарифах',
            ];
            $item->set($payload)->expiresAfter($this->publicTtlSeconds);
            $this->cache->save($item);
            return $this->json($payload, 200);
        }

        $payload = [
            'city' => $pvz->getCity(),
            'available' => true,
            'cost' => $pvz->getCost(),
            'freeFrom' => $pvz->getFree(),
            'term' => $pvz->getSrok(),
        ];

        $item->set($payload)->expiresAfter($this->publicTtlSeconds);
        $this->cache->save($item);

        return $this->json($payload);
    }
}


