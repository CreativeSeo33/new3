<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use Symfony\Component\HttpFoundation\RequestStack;

final class DeliveryContext
{
	public const CITY_COOKIE_NAME = 'city';
	private const CHECKOUT_SESSION_KEY = 'checkout';
	private const LEGACY_DELIVERY_SESSION_KEY = 'delivery';

	public function __construct(private RequestStack $rs, private GeoIpService $geo) {}

	/**
	 * Возвращает контекст доставки из сессии.
	 *
	 * @return array{
	 *     cityName?: string,
	 *     cityId?: int,
	 *     methodCode?: string,
	 *     pickupPointId?: int|string,
	 *     address?: string,
	 *     zip?: string,
	 *     etaDays?: int
	 * }
	 */
	public function get(): array
	{
		$session = $this->rs->getSession();
		if ($session === null) return [];

		// Новая структура: checkout.delivery.{ city: {cityName, cityId}, methodCode, pickupPointId, address, zip, etaDays }
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		$delivery = is_array($checkout) ? ($checkout['delivery'] ?? []) : [];
		$city = is_array($delivery) ? ($delivery['city'] ?? []) : [];

		$ctx = [];
		if (isset($city['cityName'])) $ctx['cityName'] = $city['cityName'];
		if (isset($city['cityId'])) $ctx['cityId'] = $city['cityId'];
		foreach (['methodCode','pickupPointId','address','zip','etaDays'] as $k) {
			if (isset($delivery[$k])) $ctx[$k] = $delivery[$k];
		}

		// Fallback для легаси-сессий: root key 'delivery'
		if ($ctx === []) {
			$legacy = $session->get(self::LEGACY_DELIVERY_SESSION_KEY, []);
			if (is_array($legacy)) {
				foreach (['cityName','cityId','methodCode','pickupPointId','address','zip','etaDays'] as $k) {
					if (isset($legacy[$k])) $ctx[$k] = $legacy[$k];
				}
			}
		}

		return $ctx;
	}

	/**
	 * Гарантирует наличие города в контексте доставки.
	 * Если города нет, пытается определить по IP, сохраняет в сессии и помечает установку cookie на RESPONSE.
	 *
	 * @return array{
	 *     cityName?: string,
	 *     cityId?: int,
	 *     methodCode?: string,
	 *     pickupPointId?: int|string,
	 *     address?: string,
	 *     zip?: string,
	 *     etaDays?: int
	 * }
	 */
	public function ensureCity(): array
	{
		$session = $this->rs->getSession();
		if ($session === null) return [];
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		$delivery = is_array($checkout) ? ($checkout['delivery'] ?? []) : [];
		$city = is_array($delivery) ? ($delivery['city'] ?? []) : [];
		if (!isset($city['cityName'])) {
			$ip = $this->rs->getCurrentRequest()?->getClientIp() ?? '127.0.0.1';
			$guess = $this->geo->guessCity($ip); // ['cityName'=>..., 'cityId'=>...]
			$city = array_merge($city, $guess);
			$delivery['city'] = $city;
			$checkout['delivery'] = $delivery;
			$session->set(self::CHECKOUT_SESSION_KEY, $checkout);
			// Пометим на текущем запросе, что cookie нужно установить на этапе RESPONSE
			$this->rs->getCurrentRequest()?->attributes->set('_delivery_city_cookie', $city['cityName']);
		}
		// Вернём плоский контекст (обратная совместимость)
		return $this->get();
	}

	public function setCity(string $name, ?int $id = null): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		$delivery = is_array($checkout) ? ($checkout['delivery'] ?? []) : [];
		$city = is_array($delivery) ? ($delivery['city'] ?? []) : [];
		$city['cityName'] = $name;
		if ($id) $city['cityId'] = $id;
		unset($delivery['methodCode'], $delivery['pickupPointId']);
		$delivery['city'] = $city;
		$checkout['delivery'] = $delivery;
		$session->set(self::CHECKOUT_SESSION_KEY, $checkout);
		// Обновить cookie на этапе RESPONSE
		$this->rs->getCurrentRequest()?->attributes->set('_delivery_city_cookie', $name);
	}

	public function setMethod(string $code, ?array $extra = null): void
	{
		$session = $this->rs->getSession();
		if ($session === null) return;
		$checkout = $session->get(self::CHECKOUT_SESSION_KEY, []);
		$delivery = is_array($checkout) ? ($checkout['delivery'] ?? []) : [];
		$delivery['methodCode'] = $code;
		if ($extra) $delivery = array_merge($delivery, $extra);
		$checkout['delivery'] = $delivery;
		$session->set(self::CHECKOUT_SESSION_KEY, $checkout);
	}

	public function syncToCart(Cart $cart): void
	{
		$ctx = $this->get();
		if (isset($ctx['cityName'])) $cart->setShipToCity($ctx['cityName']);
		if (isset($ctx['methodCode'])) $cart->setShippingMethod($ctx['methodCode']);
		$data = $cart->getShippingData() ?? [];
		foreach (['pickupPointId','address','zip','etaDays'] as $k) {
			if (isset($ctx[$k])) $data[$k] = $ctx[$k];
		}
		$cart->setShippingData($data);
	}
}


