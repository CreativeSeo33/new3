<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\User as AppUser;
use App\Service\CartManager;
use App\Service\Delivery\DeliveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CartController extends AbstractController
{
    public function __construct(
        private DeliveryService $deliveryService
    ) {}

    #[Route('/cart', name: 'cart_page', methods: ['GET'])]
    public function index(CartManager $cartManager): Response
    {
        $user = $this->getUser();
        $userId = $user instanceof AppUser ? $user->getId() : null;
        $cart = $cartManager->getOrCreateCurrent($userId);

        // Рассчитываем стоимость доставки
        $deliveryResult = $this->deliveryService->calculateForCart($cart);

        return $this->render('catalog/cart/index.html.twig', [
            'cart' => $cart,
            'delivery' => $deliveryResult,
        ]);
    }
}


