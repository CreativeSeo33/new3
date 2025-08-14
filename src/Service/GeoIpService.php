<?php
declare(strict_types=1);

namespace App\Service;

final class GeoIpService
{
	public function guessCity(string $ip): array
	{
		return ['cityName' => 'Москва', 'cityId' => 77];
	}
}


