<?php
declare(strict_types=1);

namespace App\Service\Yandex;

final class YandexDeliveryApi
{
	public function __construct(
		private string $baseUrl,
		private string $token,
		private int $timeoutSeconds = 10,
		private ?string $offersCreatePath = '/api/b2b/platform/offers/create',
		private ?string $pickupPointsListPath = '/api/b2b/platform/pickup-points/list',
	) {}

	/**
	 * Создание оффера (offers/create).
	 * @see https://yandex.com/support/delivery-profile/ru/api/other-day/access
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public function createOffer(array $payload): array
	{
		$path = $this->offersCreatePath ?? '/api/b2b/platform/offers/create';
		return $this->request('POST', $path, $payload);
	}

	/**
	 * Получение списка точек самопривоза и ПВЗ/способов доставки.
	 * Пустое тело запроса вернёт все доступные способы доставки.
	 * @see https://yandex.com/support/delivery-profile/ru/api/other-day/ref/2.-Tochki-samoprivoza-i-PVZ/apib2bplatformpickup-pointslist-post
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	public function listPickupPoints(array $payload = []): array
	{
		$path = $this->pickupPointsListPath ?? '/api/b2b/platform/pickup-points/list';
		return $this->request('POST', $path, $payload);
	}

	/**
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
    private function request(string $method, string $path, array $payload): array
	{
		if ($this->token === '') {
			throw new \RuntimeException('YANDEX_DELIVERY_TOKEN is not configured');
		}

		$url = rtrim($this->baseUrl, '/') . $path;
        // Яндекс ожидает JSON-объект на входе; пустой массив [] приведёт к ошибке парсинга.
        $bodyData = ($payload === []) ? (object)[] : $payload;
        $body = json_encode($bodyData, JSON_UNESCAPED_UNICODE);
		if ($body === false) { $body = '{}'; }

		$respBody = null; $status = 0;

		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			if ($ch === false) {
				throw new \RuntimeException('Failed to initialize cURL');
			}
			$headers = [
				'Accept: application/json',
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->token,
			];
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			if (in_array(strtoupper($method), ['POST','PUT','PATCH'], true)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			}
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeoutSeconds);
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			$respBody = curl_exec($ch);
			$infoCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$status = is_int($infoCode) ? $infoCode : (is_numeric($infoCode) ? (int) $infoCode : 0);
			if ($respBody === false) {
				$err = curl_error($ch);
				curl_close($ch);
				throw new \RuntimeException('Yandex Delivery API request failed: ' . $err);
			}
			curl_close($ch);
		} else {
			$opts = [
				'http' => [
					'method' => strtoupper($method),
					'header' => implode("\r\n", [
						'Accept: application/json',
						'Content-Type: application/json',
						'Authorization: Bearer ' . $this->token,
					]),
					'content' => $body,
					'timeout' => $this->timeoutSeconds,
					'ignore_errors' => true,
				],
			];
			$ctx = stream_context_create($opts);
			$respBody = @file_get_contents($url, false, $ctx);
			if (is_array($http_response_header ?? null)) {
				foreach ($http_response_header as $h) {
					if (preg_match('~HTTP/\S+\s(\d{3})~', $h, $m)) {
						$status = (int) $m[1];
						break;
					}
				}
			}
			if ($respBody === false) {
				throw new \RuntimeException('Yandex Delivery API request failed: stream error');
			}
		}

		$decoded = json_decode((string) $respBody, true);
		if ($status < 200 || $status >= 300) {
			$message = is_array($decoded) && isset($decoded['message'])
				? (string) $decoded['message']
				: 'HTTP ' . $status;
			throw new \RuntimeException('Yandex Delivery API error: ' . $message, $status);
		}

		return is_array($decoded) ? $decoded : [
			'raw' => (string) $respBody,
			'status' => $status,
		];
	}
}


