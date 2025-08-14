<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\CartRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, RedirectResponse, Cookie};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart/claim')]
final class CartClaimController extends AbstractController
{
	#[Route('', name: 'cart_claim', methods: ['GET'])]
	public function claim(Request $request, CartRepository $carts): Response
	{
		$token = $request->query->get('token');
		$sig = $request->query->get('sig');
		if (!$token || !$sig) return new Response('Bad request', 400);

		$expected = hash_hmac('sha256', $token, $_ENV['APP_SECRET'] ?? '');
		if (!hash_equals($expected, $sig)) return new Response('Invalid signature', 403);

		$cart = $carts->findActiveByToken($token);
		if (!$cart) return new Response('Cart not found', 404);

		$resp = new RedirectResponse('/cart');
		$cookie = Cookie::create('cart_token')->withValue($token)->withPath('/')
			->withHttpOnly(true)->withExpires(strtotime('+30 days'));
		$resp->headers->setCookie($cookie);
		return $resp;
	}
}


