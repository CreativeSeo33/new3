<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Search\RuQueryNormalizer;
use PHPUnit\Framework\TestCase;

final class RuQueryNormalizerTest extends TestCase
{
    public function testMorphologyBasics(): void
    {
        $n = new RuQueryNormalizer(['и','в','на','с','а','но']);
        $a = $n->normalize('Красный телефон');
        $b = $n->normalize('красного телефоны');
        $this->assertSame($a, $b, 'Stems of forms should match');
    }

    public function testPrefixMode(): void
    {
        $n = new RuQueryNormalizer();
        $q = $n->normalize('красн', true);
        $this->assertStringEndsWith('*', $q);
    }
}


