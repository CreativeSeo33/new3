<?php

namespace App\Theme;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ThemeManager
{
    private const CACHE_KEY = 'app.themes.registry';

    /** @var array<string, ThemeDefinition>|null */
    private ?array $themes = null;

    public function __construct(
        private readonly string $themesPath,
        private readonly string $defaultTheme,
        private readonly CacheInterface $cache,
        private readonly RequestStack $requestStack,
        private readonly bool $cacheEnabled = true,
        private readonly int $cacheTtl = 3600,
        private readonly array $allowedThemes = [],
    ) {}

    /** @return array<string, ThemeDefinition> */
    public function getThemes(): array
    {
        if ($this->themes === null) {
            $this->themes = $this->loadThemes();
        }
        return $this->themes;
    }

    public function hasTheme(string $code): bool
    { return isset($this->getThemes()[$code]); }

    public function getTheme(string $code): ThemeDefinition
    {
        $themes = $this->getThemes();
        if (!isset($themes[$code])) {
            throw new \RuntimeException("Theme '$code' not found");
        }
        $theme = $themes[$code];
        if (!$theme->isEnabled()) {
            throw new \RuntimeException("Theme '$code' is disabled");
        }
        return $theme;
    }

    public function setCurrentTheme(string $code): void
    { $req = $this->requestStack->getMainRequest(); if ($req) { $req->attributes->set('_theme', $code); } }

    public function getCurrentTheme(): ThemeDefinition
    { $req = $this->requestStack->getMainRequest(); $code = $req?->attributes->get('_theme') ?? $this->defaultTheme; return $this->getTheme($code); }

    /** @return ThemeDefinition[] */
    public function getThemeChain(?ThemeDefinition $theme = null): array
    {
        $theme ??= $this->getCurrentTheme();
        $chain = [$theme];
        $seen = [$theme->getCode() => true];
        $current = $theme;
        while ($parentCode = $current->getParent()) {
            if (isset($seen[$parentCode])) throw new \RuntimeException("Theme inheritance cycle detected at '$parentCode'");
            $parentTheme = $this->getTheme($parentCode);
            $chain[] = $parentTheme;
            $seen[$parentCode] = true;
            $current = $parentTheme;
        }
        if ($this->hasTheme('_shared')) { $chain[] = $this->getTheme('_shared'); }
        return $chain;
    }

    private function loadThemes(): array
    {
        if (!$this->cacheEnabled) { return $this->scanThemes(); }
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter($this->cacheTtl);
            return $this->scanThemes();
        });
    }

    private function scanThemes(): array
    {
        $themes = [];
        foreach (glob($this->themesPath . '/*', GLOB_ONLYDIR) as $dir) {
            $configFile = $dir . '/theme.yaml';
            if (!is_file($configFile)) continue;
            $config = Yaml::parseFile($configFile) ?? [];
            $code = $config['code'] ?? basename($dir);
            if (!preg_match('/^[a-z0-9._-]+$/', $code)) throw new \InvalidArgumentException("Invalid theme code: $code");
            if (!empty($this->allowedThemes) && !in_array($code, $this->allowedThemes, true)) continue;
            $themes[$code] = new ThemeDefinition(
                code: $code,
                name: $config['name'] ?? $code,
                path: $dir,
                enabled: $config['enabled'] ?? true,
                parent: $config['parent'] ?? null,
                metadata: $config['metadata'] ?? [],
                features: $config['features'] ?? [],
                parameters: $config['parameters'] ?? [],
            );
        }
        foreach ($themes as $code => $theme) {
            $parent = $theme->getParent();
            if ($parent !== null && !isset($themes[$parent])) {
                throw new \InvalidArgumentException("Parent theme '$parent' not found for '$code'");
            }
        }
        return $themes;
    }

    public function clearCache(): void
    { $this->cache->delete(self::CACHE_KEY); $this->themes = null; }
}



