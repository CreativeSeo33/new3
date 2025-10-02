<?php

namespace App\Theme\Asset;

use App\Theme\ThemeManager;
use Symfony\Component\Asset\Packages;

final class ThemeAssetPackage
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly Packages $packages,
        private readonly string $projectDir,
        private readonly bool $enabled = true,
    ) {}

    public function getUrl(string $path): string
    {
        if (!$this->enabled) {
            return $this->packages->getUrl($path);
        }
        foreach ($this->themeManager->getThemeChain() as $theme) {
            $assetPath = 'build/'.$theme->getPublicPath().'/'.$path; // e.g. build/themes/modern/images/logo.svg
            $fullPath = $this->projectDir.'/public/'.$assetPath;
            if (is_file($fullPath)) {
                return $this->packages->getUrl($assetPath);
            }
        }
        return $this->packages->getUrl($path);
    }

    public function exists(string $path): bool
    {
        if (!$this->enabled) return false;
        foreach ($this->themeManager->getThemeChain() as $theme) {
            $fullPath = $this->projectDir.'/public/build/'.$theme->getPublicPath().'/'.$path;
            if (is_file($fullPath)) {
                return true;
            }
        }
        return false;
    }
}



