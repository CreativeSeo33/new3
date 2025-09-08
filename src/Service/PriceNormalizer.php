<?php
declare(strict_types=1);

namespace App\Service;

/**
 * PriceNormalizer - утилита для нормализации цен в рублях (целые числа)
 *
 * Гарантирует, что все цены в системе являются целыми числами рублей,
 * без копеек и дробной части.
 */
final class PriceNormalizer
{
    /**
     * Нормализует сырую цену в целые рубли
     *
     * @param int|float|string|null $raw Сырая цена
     * @return int Цена в целых рублях
     * @throws \DomainException Если цена содержит дробную часть или имеет некорректный формат
     */
    public static function toRubInt(int|float|string|null $raw): int
    {
        if ($raw === null) {
            return 0;
        }

        if (is_int($raw)) {
            return $raw;
        }

        $s = is_float($raw) ? sprintf('%.2F', $raw) : trim((string)$raw);
        $s = str_replace([' ', ','], ['', '.'], $s);

        if ($s === '') {
            return 0;
        }

        if (str_contains($s, '.')) {
            [$major, $minor] = array_pad(explode('.', $s, 2), 2, '0');
            if ((int)$minor !== 0) {
                throw new \DomainException("Fractional price is not allowed in RUB: {$raw}");
            }
            $s = $major;
        }

        if (!preg_match('/^-?\d+$/', $s)) {
            throw new \DomainException("Invalid price format: {$raw}");
        }

        return (int)$s;
    }

    /**
     * Безопасная нормализация - возвращает 0 вместо исключения при ошибке
     *
     * @param int|float|string|null $raw Сырая цена
     * @return int Цена в целых рублях или 0 при ошибке
     */
    public static function toRubIntSafe(int|float|string|null $raw): int
    {
        try {
            return self::toRubInt($raw);
        } catch (\DomainException) {
            return 0;
        }
    }

    /**
     * Проверяет, является ли цена целой (без дробной части)
     *
     * @param int|float|string|null $price Цена для проверки
     * @return bool True если цена целая
     */
    public static function isWholeRubPrice(int|float|string|null $price): bool
    {
        try {
            self::toRubInt($price);
            return true;
        } catch (\DomainException) {
            return false;
        }
    }
}
