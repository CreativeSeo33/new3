<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class AttributeCodeGenerator
{
    private AsciiSlugger $slugger;

    public function __construct(?AsciiSlugger $slugger = null)
    {
        $this->slugger = $slugger ?? new AsciiSlugger();
    }

    public function baseFromName(string $name): string
    {
        $slug = (string) $this->slugger->slug($name, '-');
        $slug = strtolower($slug);
        $code = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? $slug;
        $code = trim($code, '_');
        if ($code !== '' && ctype_digit($code[0])) {
            $code = 'attr_' . $code;
        }
        return mb_substr($code, 0, 100);
    }

    public function makeUnique(string $base, callable $exists): string
    {
        $base = $base !== '' ? $base : 'attribute';
        $code = $base;
        $i = 2;
        $maxLen = 100;
        while ($exists($code)) {
            $suffix = '_' . $i;
            $trimLen = $maxLen - strlen($suffix);
            $candidate = substr($base, 0, max(1, $trimLen)) . $suffix;
            $code = $candidate;
            $i++;
        }
        return $code;
    }

    public function generateUniqueFromName(string $name, callable $exists): string
    {
        $base = $this->baseFromName($name);
        return $this->makeUnique($base, $exists);
    }

    public function normalizeInput(string $code): string
    {
        $code = strtolower($code);
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? $code;
        $code = trim($code, '_');
        if ($code !== '' && ctype_digit($code[0])) {
            $code = 'attr_' . $code;
        }
        return mb_substr($code, 0, 100);
    }
}



