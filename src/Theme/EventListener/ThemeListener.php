<?php

namespace App\Theme\EventListener;

use App\Theme\ThemeManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final class ThemeListener
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly string $defaultTheme,
        private readonly array $allowedThemes = [],
        private readonly bool $enabled = true,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;
        if (!$this->enabled) return;
        $request = $event->getRequest();
        $theme = $this->resolveTheme($request);
        $this->themeManager->setCurrentTheme($theme);
    }

    private function resolveTheme(Request $request): string
    {
        $candidate = null;
        if (preg_match('/^([^.]+)\./', $request->getHost(), $m)) {
            $candidate = $m[1];
        }
        $candidate = $request->query->get('_theme', $candidate);
        if ($request->hasPreviousSession()) {
            $candidate = $request->getSession()->get('theme', $candidate);
        }
        if (is_string($candidate)) {
            $candidate = strtolower($candidate);
            if (preg_match('/^[a-z0-9._-]+$/', $candidate)
                && (empty($this->allowedThemes) || in_array($candidate, $this->allowedThemes, true))
                && $this->themeManager->hasTheme($candidate)) {
                return $candidate;
            }
        }
        return $this->defaultTheme;
    }
}



