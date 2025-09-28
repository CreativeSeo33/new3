<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\WishlistContext;
use App\Http\WishlistCookieFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Entity\User as AppUser;

final class WishlistController extends AbstractController
{
    #[Route('/wishlist', name: 'catalog_wishlist', methods: ['GET'])]
    public function index(WishlistContext $wishlistContext, WishlistCookieFactory $cookieFactory, RequestStack $requestStack): Response
    {
        $user = $this->getUser();
        $wishlist = $wishlistContext->getOrCreate($user instanceof AppUser ? $user : null);

        $items = [];
        foreach ($wishlist->getItems() as $it) {
            $p = $it->getProduct();
            $img = null;
            try { $img = $p->getImage()->first(); } catch (\Throwable) { $img = null; }
            $imgUrl = $img ? $img->getImageUrl() : null;

            $items[] = [
                'id' => $p->getId(),
                'name' => (string)$p->getName(),
                'url' => $p->getSlug() ? $this->generateUrl('catalog_product_show', ['slug' => $p->getSlug()]) : '#',
                'price' => $p->getSalePrice() ?? $p->getPrice(),
                'image' => $imgUrl,
            ];
        }

        $response = $this->render('catalog/wishlist/index.html.twig', [
            'items' => $items,
        ]);
        if ($wishlist->getToken()) {
            $req = $requestStack->getCurrentRequest();
            if ($req) {
                $response->headers->setCookie($cookieFactory->build($req, $wishlist->ensureToken()));
            }
        }
        return $response;
    }
}


