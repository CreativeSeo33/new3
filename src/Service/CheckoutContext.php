<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use Symfony\Component\HttpFoundation\RequestStack;

final class CheckoutContext
{
	private const CHECKOUT_SESSION_KEY = 'checkout';

	public function __construct(private RequestStack $rs) {}

	public function get(): array
	{
		$session = $this->rs->getSession();
		if ($session === null) return [];
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		return is_array($checkout) ? $checkout : [];
	}

	public function setCartRefFromCart(Cart $cart): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		if (!is_array($checkout)) $checkout = [];
		$checkout['cart'] = [
			'token' => $cart->getToken(),
			'id' => $cart->getId(),
		];
		$session->set(self::CHECKOUT_SESSION_KEY, $checkout);
	}

	/**
	 * @param array{
	 *   name?: string,
	 *   phone?: string,
	 *   phoneNormal?: string,
	 *   email?: string,
	 *   ip?: string,
	 *   userAgent?: string,
	 *   comment?: string
	 * } $data
	 */
	public function setCustomer(array $data): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$allowed = ['name','phone','phoneNormal','email','ip','userAgent','comment'];
		$filtered = [];
		foreach ($allowed as $k) {
			if (array_key_exists($k, $data)) $filtered[$k] = $data[$k];
		}
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		if (!is_array($checkout)) $checkout = [];
		$checkout['customer'] = array_merge(($checkout['customer'] ?? []), $filtered);
		$session->set(self::CHECKOUT_SESSION_KEY, $checkout);
	}

	public function setComment(?string $comment): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		if (!is_array($checkout)) $checkout = [];
		$comment = $comment !== null ? trim($comment) : null;
		if ($comment === null || $comment === '') {
			unset($checkout['comment']);
		} else {
			$checkout['comment'] = $comment;
		}
		$session->set(self::CHECKOUT_SESSION_KEY, $checkout);
	}

	public function setPaymentMethod(?string $method): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		if (!is_array($checkout)) $checkout = [];
		$method = $method !== null ? trim($method) : null;
		if ($method === null || $method === '') {
			unset($checkout['paymentMethod']);
		} else {
			$checkout['paymentMethod'] = $method;
		}
		$session->set(self::CHECKOUT_SESSION_KEY, $checkout);
	}

	public function clear(): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$session->remove(self::CHECKOUT_SESSION_KEY);
	}
}


