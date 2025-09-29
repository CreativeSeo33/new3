<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\User as AppUser;
use App\Service\CartManager;
use App\Repository\DeliveryTypeRepository;
use App\Service\DeliveryContext;
use App\Service\Delivery\DeliveryService;
use App\Entity\PvzPoints;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CartController extends AbstractController
{
    public function __construct(
        private DeliveryService $deliveryService,
        private readonly ManagerRegistry $registry
    ) {}

    #[Route('/cart', name: 'cart_page', methods: ['GET'])]
    public function index(CartManager $cartManager, DeliveryTypeRepository $deliveryTypes, DeliveryContext $deliveryContext): Response
    {
        $user = $this->getUser();
        $userId = $user instanceof AppUser ? $user->getId() : null;
        $cart = $cartManager->getOrCreateCurrent($userId);

        // Рассчитываем стоимость доставки
        $deliveryResult = $this->deliveryService->calculateForCart($cart);

        $types = $deliveryTypes->findBy(['active' => true], ['sortOrder' => 'ASC']);
        $ctx = $deliveryContext->get();

        // Подготовим точки ПВЗ для карты (как на странице доставки), если выбран pvz и город известен
        $pvzPoints = [];
        if (($ctx['methodCode'] ?? null) === 'pvz' && !empty($ctx['cityName'])) {
            $repo = $this->registry->getRepository(PvzPoints::class);
            $rows = $repo->findBy(['city' => $ctx['cityName']], ['name' => 'ASC']);
            foreach ($rows as $row) {
                // Entity -> простая структура точек для компонента
                $lat = method_exists($row, 'getShirota') ? $row->getShirota() : null;
                $lon = method_exists($row, 'getDolgota') ? $row->getDolgota() : null;
                if ($lat !== null && $lon !== null) {
                    $pvzPoints[] = [
                        'id' => method_exists($row, 'getId') ? $row->getId() : null,
                        'lat' => (float)$lat,
                        'lon' => (float)$lon,
                        'title' => method_exists($row, 'getName') ? ($row->getName() ?? 'ПВЗ') : 'ПВЗ',
                        'address' => method_exists($row, 'getAddress') ? ($row->getAddress() ?? '') : '',
                    ];
                }
            }
        }

        return $this->render('catalog/cart/index.html.twig', [
            'cart' => $cart,
            'delivery' => $deliveryResult,
            'types' => $types,
            'deliveryContext' => $ctx,
            'pvzPoints' => $pvzPoints,
        ]);
    }
}


