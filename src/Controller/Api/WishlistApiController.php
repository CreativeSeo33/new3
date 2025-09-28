<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User as AppUser;
use App\Repository\ProductRepository;
use App\Service\WishlistContext;
use App\Http\WishlistCookieFactory;
use App\Service\WishlistManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/wishlist')]
final class WishlistApiController extends AbstractController
{
    public function __construct(
        private WishlistContext $ctx,
        private WishlistManager $mgr,
        private ProductRepository $products,
        private WishlistCookieFactory $cookieFactory,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $w = $this->ctx->getOrCreate($user instanceof AppUser ? $user : null);
        $items = [];
        foreach ($w->getItems() as $it) {
            $p = $it->getProduct();
            $items[] = [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'slug' => $p->getSlug(),
                'price' => $p->getSalePrice() ?? $p->getPrice(),
                'image' => (function() use ($p) {
                    try {
                        $img = $p->getImage()->first();
                        return $img ? $img->getImageUrl() : null;
                    } catch (\Throwable) { return null; }
                })(),
            ];
        }
        $response = $this->json(['count' => count($items), 'items' => $items]);
        if ($w->getToken()) {
            $response->headers->setCookie($this->cookieFactory->build($request, $w->ensureToken()));
        }
        return $response;
    }

    #[Route('/count', methods: ['GET'])]
    public function count(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $w = $this->ctx->getOrCreate($user instanceof AppUser ? $user : null);
        $response = $this->json(['count' => $w->getItems()->count()]);
        if ($w->getToken()) {
            $response->headers->setCookie($this->cookieFactory->build($request, $w->ensureToken()));
        }
        return $response;
    }

    #[Route('/items', methods: ['POST'])]
    public function add(Request $r): JsonResponse
    {
        $data = $r->toArray();
        $productId = (int)($data['productId'] ?? 0);
        if ($productId <= 0) return $this->json(['message' => 'Invalid productId'], 422);

        $p = $this->products->find($productId);
        if (!$p) return $this->json(['message' => 'Product not found'], 404);

        $user = $this->getUser();
        $w = $this->ctx->getOrCreate($user instanceof AppUser ? $user : null);
        $this->mgr->addItem($w, $p);
        $response = $this->json(['ok' => true]);
        if ($w->getToken()) {
            $response->headers->setCookie($this->cookieFactory->build($r, $w->ensureToken()));
        }
        return $response;
    }

    #[Route('/items/{productId}', methods: ['DELETE'])]
    public function remove(int $productId, Request $request): JsonResponse
    {
        $p = $this->products->find($productId);
        $response = $this->json(['ok' => true]);
        if ($p) {
            $user = $this->getUser();
            $w = $this->ctx->getOrCreate($user instanceof AppUser ? $user : null);
            $this->mgr->removeItem($w, $p);
            if ($w->getToken()) {
                $response->headers->setCookie($this->cookieFactory->build($request, $w->ensureToken()));
            }
        }
        return $response;
    }
}


