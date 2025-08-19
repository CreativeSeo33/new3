<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class OptionCodeGenerator
{
    private AsciiSlugger $slugger;

    public function __construct(?AsciiSlugger $slugger = null)
    {
        $this->slugger = $slugger ?? new AsciiSlugger();
    }

    /**
     * Генерирует базовый код из имени (без проверки уникальности).
     */
    public function baseFromName(string $name): string
    {
        // Транслитерация и слаг: "Цвет арматуры" -> "cvet-armatury"
        $slug = (string) $this->slugger->slug($name, '-');
        $slug = strtolower($slug);

        // Разрешаем только a-z0-9 и разделители -> меняем на '_'
        $code = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? $slug;
        $code = trim($code, '_');

        // Не начинаем с цифры (редко, но на всякий случай)
        if ($code !== '' && ctype_digit($code[0])) {
            $code = 'opt_' . $code;
        }

        // Ограничение длины
        return mb_substr($code, 0, 100);
    }

    /**
     * Делает код уникальным с помощью суффиксов _2, _3, ...
     *
     * exists($code) должен вернуть true, если такой код уже занят.
     */
    public function makeUnique(string $base, callable $exists): string
    {
        $base = $base !== '' ? $base : 'option';
        $code = $base;
        $i = 2;

        // Учитываем макс. длину 100, чтобы суффикс поместился
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

    /**
     * Полная генерация: из имени + обеспечение уникальности.
     */
    public function generateUniqueFromName(string $name, callable $exists): string
    {
        $base = $this->baseFromName($name);
        return $this->makeUnique($base, $exists);
    }

    /**
     * Нормализует уже заданный вручную код (ввод пользователя).
     */
    public function normalizeInput(string $code): string
    {
        $code = strtolower($code);
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? $code;
        $code = trim($code, '_');
        if ($code !== '' && ctype_digit($code[0])) {
            $code = 'opt_' . $code;
        }
        return mb_substr($code, 0, 100);
    }
}


