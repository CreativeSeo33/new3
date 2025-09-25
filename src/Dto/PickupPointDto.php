<?php
declare(strict_types=1);

namespace App\Dto;

final class PickupPointDto
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $address,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $cityCode = null,
        public readonly ?string $city = null,
        public readonly ?string $region = null,
        public readonly ?int $postal = null,
        public readonly ?string $phone = null
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public static function fromYandex(array $data): self
    {
        $address = is_array($data['address'] ?? null) ? (array) $data['address'] : [];
        $position = is_array($data['position'] ?? null) ? (array) $data['position'] : [];
        $contact = is_array($data['contact'] ?? null) ? (array) $data['contact'] : [];

        return new self(
            code: (string) ($data['ID'] ?? $data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            address: self::extractAddress($address),
            latitude: isset($position['latitude']) && is_numeric($position['latitude']) ? (float) $position['latitude'] : null,
            longitude: isset($position['longitude']) && is_numeric($position['longitude']) ? (float) $position['longitude'] : null,
            cityCode: isset($address['geoId']) ? (string) $address['geoId'] : null,
            city: isset($address['locality']) ? (string) $address['locality'] : null,
            region: isset($address['region']) ? (string) $address['region'] : null,
            postal: isset($address['postal_code']) && is_numeric($address['postal_code']) ? (int) $address['postal_code'] : null,
            phone: isset($contact['phone']) ? (string) $contact['phone'] : null,
        );
    }

    /**
     * @param array<string,mixed>|string|null $address
     */
    private static function extractAddress($address): string
    {
        if (is_array($address)) {
            if (isset($address['full_address']) && is_string($address['full_address'])) {
                return $address['full_address'];
            }
            return json_encode($address, JSON_UNESCAPED_UNICODE) ?: '';
        }
        return is_string($address) ? $address : '';
    }
}


