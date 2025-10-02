<?php

namespace App\Theme\Twig;

use App\Theme\ThemeManager;
use Twig\Loader\LoaderInterface;
use Twig\Source;

final class ThemeLoader implements LoaderInterface
{
    public function __construct(private readonly ThemeManager $themes, private readonly bool $enabled = true) {}

    /** @var array<string, ?string> key: "{$theme}::{$name}" => absolute path or null */
    private array $resolved = [];

    private function isNamespaced(string $name): bool
    { return str_contains($name, '@'); }

    private function key(string $name): string
    { return $this->themes->getCurrentTheme()->getCode().'::'.$name; }

    private function resolve(string $name): ?string
    {
        if (!$this->enabled) return null;
        if ($this->isNamespaced($name)) return null;
        $key = $this->key($name);
        if (array_key_exists($key, $this->resolved)) return $this->resolved[$key];
        foreach ($this->themes->getThemeChain() as $theme) {
            $path = $theme->getTemplatePath().'/'.$name;
            if (is_file($path)) {
                $real = realpath($path) ?: $path;
                return $this->resolved[$key] = $real;
            }
        }
        return $this->resolved[$key] = null;
    }

    public function exists(string $name): bool
    { return $this->enabled && (bool) $this->resolve($name); }

    public function getSourceContext(string $name): Source
    {
        $file = $this->resolve($name);
        if ($file && is_file($file)) {
            return new Source(file_get_contents($file), $name, $file);
        }
        throw new \Twig\Error\LoaderError(sprintf('Template "%s" not found in theme chain.', $name));
    }

    public function getCacheKey(string $name): string
    {
        $file = $this->resolve($name);
        return $file
            ? 'theme:'.$this->themes->getCurrentTheme()->getCode().':'.$file
            : 'theme:miss:'.$name.':'.$this->themes->getCurrentTheme()->getCode();
    }

    public function isFresh(string $name, int $time): bool
    {
        $file = $this->resolve($name);
        return $file ? (filemtime($file) <= $time) : false;
    }
}


