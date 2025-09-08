<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PriceNormalizer;
use PHPUnit\Framework\TestCase;

final class PriceNormalizerTest extends TestCase
{
    public function testToRubIntWithNull(): void
    {
        $this->assertEquals(0, PriceNormalizer::toRubInt(null));
    }

    public function testToRubIntWithInt(): void
    {
        $this->assertEquals(1999, PriceNormalizer::toRubInt(1999));
        $this->assertEquals(0, PriceNormalizer::toRubInt(0));
        $this->assertEquals(-100, PriceNormalizer::toRubInt(-100));
    }

    public function testToRubIntWithValidStrings(): void
    {
        $this->assertEquals(1999, PriceNormalizer::toRubInt('1999'));
        $this->assertEquals(1999, PriceNormalizer::toRubInt('1 999'));
        $this->assertEquals(1999, PriceNormalizer::toRubInt(' 1999 '));
        $this->assertEquals(1999, PriceNormalizer::toRubInt('1999.00'));
        $this->assertEquals(0, PriceNormalizer::toRubInt('0'));
        $this->assertEquals(0, PriceNormalizer::toRubInt(''));
    }

    public function testToRubIntWithFloat(): void
    {
        $this->assertEquals(1999, PriceNormalizer::toRubInt(1999.0));
        // Целые числа с плавающей точкой должны работать
        $this->assertEquals(2000, PriceNormalizer::toRubInt(2000.0));
    }

    public function testToRubIntWithFractionalFloatThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Fractional price is not allowed in RUB');
        PriceNormalizer::toRubInt(1999.50);
    }

    public function testToRubIntWithFractionalStringThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Fractional price is not allowed in RUB');
        PriceNormalizer::toRubInt('1999.50');
    }

    public function testToRubIntWithInvalidStringThrowsException(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid price format');
        PriceNormalizer::toRubInt('abc');
    }

    public function testToRubIntWithCommaDecimal(): void
    {
        $this->assertEquals(1999, PriceNormalizer::toRubInt('1999,00'));
    }

    public function testToRubIntSafeWithValidInput(): void
    {
        $this->assertEquals(1999, PriceNormalizer::toRubIntSafe(1999));
        $this->assertEquals(1999, PriceNormalizer::toRubIntSafe('1999'));
        $this->assertEquals(1999, PriceNormalizer::toRubIntSafe(1999.0));
    }

    public function testToRubIntSafeWithInvalidInput(): void
    {
        $this->assertEquals(0, PriceNormalizer::toRubIntSafe('invalid'));
        $this->assertEquals(0, PriceNormalizer::toRubIntSafe(1999.50));
    }

    public function testIsWholeRubPrice(): void
    {
        $this->assertTrue(PriceNormalizer::isWholeRubPrice(1999));
        $this->assertTrue(PriceNormalizer::isWholeRubPrice('1999'));
        $this->assertTrue(PriceNormalizer::isWholeRubPrice('1999.00'));
        $this->assertTrue(PriceNormalizer::isWholeRubPrice(1999.0));

        $this->assertFalse(PriceNormalizer::isWholeRubPrice(1999.50));
        $this->assertFalse(PriceNormalizer::isWholeRubPrice('1999.50'));
        $this->assertFalse(PriceNormalizer::isWholeRubPrice('invalid'));
    }

    public function testLargeNumbers(): void
    {
        $this->assertEquals(1000000, PriceNormalizer::toRubInt(1000000));
        $this->assertEquals(1000000, PriceNormalizer::toRubInt('1000000'));
    }

    public function testNegativeNumbers(): void
    {
        $this->assertEquals(-1999, PriceNormalizer::toRubInt(-1999));
        $this->assertEquals(-1999, PriceNormalizer::toRubInt('-1999'));
    }
}
