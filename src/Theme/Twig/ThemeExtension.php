<?php

namespace App\Theme\Twig;

use App\Theme\ThemeManager;
use App\Theme\Asset\ThemeAssetPackage;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class ThemeExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly ThemeAssetPackage $assetPackage,
    ) {}

    public function getGlobals(): array
    {
        $theme = $this->themeManager->getCurrentTheme();
        return [
            'current_theme' => $theme->getCode(),
            'theme' => $theme,
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_asset', [$this, 'getThemeAsset']),
            new TwigFunction('theme_entry', [$this, 'getThemeEntry']),
            new TwigFunction('theme_has_feature', [$this, 'hasFeature']),
            new TwigFunction('theme_parameter', [$this, 'getParameter']),
        ];
    }

    public function getThemeAsset(string $path): string
    { return $this->assetPackage->getUrl($path); }

    public function getThemeEntry(string $entryName): string
    { $theme = $this->themeManager->getCurrentTheme(); return "themes/{$theme->getCode()}/{$entryName}"; }

    public function hasFeature(string $feature): bool
    { return $this->themeManager->getCurrentTheme()->hasFeature($feature); }

    public function getParameter(string $key, mixed $default = null): mixed
    { return $this->themeManager->getCurrentTheme()->getParameter($key, $default); }
}



