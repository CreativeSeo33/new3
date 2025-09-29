<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\City;
use App\Entity\PvzPoints;
use App\Service\DeliveryContext;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InfoController extends AbstractController
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly DeliveryContext $deliveryContext,
    ) {}

    #[Route(path: '/dostavka', name: 'catalog_dostavka', methods: ['GET'])]
    public function dostavka(): Response
    {
        // Определяем текущий город пользователя из контекста доставки
        $ctx = $this->deliveryContext->ensureCity();
        $cityName = $ctx['cityName'] ?? null;

        $lat = null;
        $lon = null;
        $foundCityId = null;

        $repo = $this->registry->getRepository(City::class);
        $city = null;
        if ($cityName) {
            $city = $repo->findOneBy(['city' => $cityName]);
        }

        if ($city instanceof City) {
            $lat = $city->getGeoLat();
            $lon = $city->getGeoLon();
            $foundCityId = $city->getId();
        }

        // ПВЗ по названию города
        $pvzAddresses = [];
        $pvzPoints = [];
        if ($cityName) {
            $pvzRepo = $this->registry->getRepository(PvzPoints::class);
            $pvzList = $pvzRepo->findBy(['city' => $cityName], ['name' => 'ASC']);
            foreach ($pvzList as $p) {
                if ($p instanceof PvzPoints) {
                    $addr = $p->getAddress();
                    if ($addr !== null && $addr !== '') {
                        $pvzAddresses[] = $addr;
                    }
                    $latPvz = $p->getShirota();
                    $lonPvz = $p->getDolgota();
                    if ($latPvz !== null && $lonPvz !== null) {
                        $pvzPoints[] = [
                            'lat' => (float) $latPvz,
                            'lon' => (float) $lonPvz,
                            'title' => $addr ?? 'ПВЗ',
                        ];
                    }
                }
            }
        }

        // Ключ API из конфигурации (env YANDEX_MAPS_API_KEY)
        /** @var string $apiKey */
        $apiKey = (string) $this->getParameter('app.yandex_maps.api_key');

        return $this->render('catalog/dostavka/index.html.twig', [
            'mapCity' => $cityName,
            'mapCityId' => $foundCityId,
            'mapLat' => $lat,
            'mapLon' => $lon,
            'yandexMapsApiKey' => $apiKey,
            'pvzAddresses' => $pvzAddresses,
            'pvzPoints' => $pvzPoints,
        ]);
    }
}


