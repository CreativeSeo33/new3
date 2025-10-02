# Промпт для создания системы тем в Symfony 7

## Контекст
Проект: `c:\laragon\www\new3`  
Цель: Внедрить систему независимых тем для каталога с runtime переключением, полной изоляцией templates/assets и fallback механизмом.

Критически важно: система должна поддерживать переключение тем в runtime (субдомены, пользователи) без `cache:clear`.

---

## Архитектура

### 1. Структура каталогов

```
themes/
  _shared/
    theme.yaml
    templates/
      base.html.twig
      components/
        header.html.twig
        footer.html.twig
        cart_counter.html.twig
    assets/
      shared.ts
      styles/
        variables.scss
        mixins.scss
    
  default/
    theme.yaml
    templates/
      catalog/
        product.html.twig
        category.html.twig
      layout.html.twig              # Extends @Shared/base.html.twig
    assets/
      entry.ts
      styles/
        main.scss
    services.yaml                   # (опционально; см. ниже про загрузку)

  modern/
    theme.yaml
    templates/
      catalog/
        product.html.twig           # Переопределяет default
    assets/
      entry.ts
      
config/
  packages/
    app_theme.yaml                  # Глобальная конфигурация
    twig.yaml                       # Twig paths (Shared)
  
src/
  Theme/
    ThemeManager.php                # Registry + Resolver + Context (request-scoped)
    ThemeDefinition.php             # Value Object
    Twig/
      ThemeExtension.php            # Twig функции и глобали
      ThemeLoader.php               # Custom Twig Loader (ChainLoader)
    Asset/
      ThemeAssetPackage.php         # Assets c fallback по цепочке
    EventListener/
      ThemeListener.php             # Request listener (runtime resolve)
    Exception/
      ThemeNotFoundException.php
      ThemeDisabledException.php
```

### 2. Конфигурация темы (`theme.yaml`)

```yaml
# themes/modern/theme.yaml
name: 'Modern Theme'
code: 'modern'                      # Уникальный идентификатор
enabled: true
parent: 'default'                   # optional

paths:
  templates: 'templates'
  assets: 'assets'
  public: 'themes/modern'           # Папка под public/build

metadata:
  description: 'Modern minimalist design'
  author: 'Your Company'
  version: '1.0.0'
  preview: 'preview.jpg'
  
features:
  dark_mode: true
  rtl_support: false
  
parameters:
  products_per_page: 24
  primary_color: '#007bff'
```

### 3. Глобальная конфигурация (`config/packages/app_theme.yaml`)

```yaml
parameters:
  app_theme.default: 'default'
  app_theme.cache_enabled: !'%kernel.debug%'   # true в prod, false в dev
  app_theme.cache_ttl: 3600

  # Стратегия выбора темы (приоритет сверху вниз; используем в ThemeListener)
  app_theme.resolution_strategy:
    - subdomain
    - query_parameter
    - session
    - environment

  app_theme.allowed_themes:
    - default
    - modern
```

```yaml
# config/packages/twig.yaml
twig:
  default_path: '%kernel.project_dir%/templates'
  paths:
    '%kernel.project_dir%/themes/_shared/templates': 'Shared'
```

---

## Реализация компонентов

### 1. `ThemeManager` (Registry + Context, request-scoped)

```php
// src/Theme/ThemeManager.php
namespace App\Theme;

use App\Theme\Exception\ThemeNotFoundException;
use App\Theme\Exception\ThemeDisabledException;
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

    public function getThemes(): array
    {
        if ($this->themes === null) {
            $this->themes = $this->loadThemes();
        }
        return $this->themes;
    }

    public function hasTheme(string $code): bool
    {
        return isset($this->getThemes()[$code]);
    }

    public function getTheme(string $code): ThemeDefinition
    {
        $themes = $this->getThemes();

        if (!isset($themes[$code])) {
            throw new ThemeNotFoundException("Theme '$code' not found");
        }
        $theme = $themes[$code];
        if (!$theme->isEnabled()) {
            throw new ThemeDisabledException("Theme '$code' is disabled");
        }
        return $theme;
    }

    // Request-scoped: текущая тема хранится в атрибуте запроса
    public function setCurrentTheme(string $code): void
    {
        $req = $this->requestStack->getMainRequest();
        if ($req) {
            $req->attributes->set('_theme', $code);
        }
    }

    public function getCurrentTheme(): ThemeDefinition
    {
        $req = $this->requestStack->getMainRequest();
        $code = $req?->attributes->get('_theme') ?? $this->defaultTheme;
        return $this->getTheme($code);
    }

    /**
     * Цепочка тем (current -> parent -> parent_of_parent -> _shared)
     */
    public function getThemeChain(?ThemeDefinition $theme = null): array
    {
        $theme ??= $this->getCurrentTheme();
        $chain = [$theme];

        $seen = [$theme->getCode() => true];
        $current = $theme;

        while ($parentCode = $current->getParent()) {
            if (isset($seen[$parentCode])) {
                throw new \RuntimeException("Theme inheritance cycle detected at '$parentCode'");
            }
            $parentTheme = $this->getTheme($parentCode);
            $chain[] = $parentTheme;
            $seen[$parentCode] = true;
            $current = $parentTheme;
        }

        if ($this->hasTheme('_shared')) {
            $chain[] = $this->getTheme('_shared');
        }

        return $chain;
    }

    private function loadThemes(): array
    {
        if (!$this->cacheEnabled) {
            return $this->scanThemes();
        }

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
            if (!is_file($configFile)) {
                continue;
            }
            $config = Yaml::parseFile($configFile) ?? [];
            $code = $config['code'] ?? basename($dir);

            if (!preg_match('/^[a-z0-9._-]+$/', $code)) {
                throw new \InvalidArgumentException("Invalid theme code: $code");
            }
            if (!empty($this->allowedThemes) && !in_array($code, $this->allowedThemes, true)) {
                // Пропускаем темы не из allowlist
                continue;
            }

            $themes[$code] = new ThemeDefinition(
                code: $code,
                name: $config['name'] ?? $code,
                path: $dir,
                enabled: $config['enabled'] ?? true,
                parent: $config['parent'] ?? null,
                metadata: $config['metadata'] ?? [],
                features: $config['features'] ?? [],
                parameters: $config['parameters'] ?? []
            );
        }

        // Валидация родителей
        foreach ($themes as $code => $theme) {
            $parent = $theme->getParent();
            if ($parent !== null && !isset($themes[$parent])) {
                throw new \InvalidArgumentException("Parent theme '$parent' not found for '$code'");
            }
        }

        return $themes;
    }

    public function clearCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->themes = null;
    }
}
```

### 2. `ThemeDefinition` (Value Object)

```php
// src/Theme/ThemeDefinition.php
namespace App\Theme;

final class ThemeDefinition
{
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $path,
        private readonly bool $enabled = true,
        private readonly ?string $parent = null,
        private readonly array $metadata = [],
        private readonly array $features = [],
        private readonly array $parameters = []
    ) {}

    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getPath(): string { return $this->path; }
    public function isEnabled(): bool { return $this->enabled; }
    public function getParent(): ?string { return $this->parent; }

    public function getTemplatePath(): string { return $this->path . '/templates'; }
    public function getAssetPath(): string { return $this->path . '/assets'; }

    // публичная часть под public/build/themes/<code>/...
    public function getPublicPath(): string { return 'themes/' . $this->code; }

    public function getMetadata(string $key = null): mixed
    {
        return $key ? ($this->metadata[$key] ?? null) : $this->metadata;
    }

    public function hasFeature(string $feature): bool
    {
        return (bool)($this->features[$feature] ?? false);
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }
}
```

### 3. `ThemeListener` (runtime resolution)

```php
// src/Theme/EventListener/ThemeListener.php
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
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $theme = $this->resolveTheme($request);
        $this->themeManager->setCurrentTheme($theme);
    }

    private function resolveTheme(Request $request): string
    {
        $candidate = null;

        // subdomain
        if (preg_match('/^([^.]+)\./', $request->getHost(), $m)) {
            $candidate = $m[1];
        }

        // preview via ?_theme
        $candidate = $request->query->get('_theme', $candidate);

        // user pref via session (без лишнего старта)
        if ($request->hasPreviousSession()) {
            $candidate = $request->getSession()->get('theme', $candidate);
        }

        // sanitize + allowlist + existence
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
```

### 4. Custom Twig Loader (ChainLoader совместим)

```php
// src/Theme/Twig/ThemeLoader.php
namespace App\Theme\Twig;

use App\Theme\ThemeManager;
use Twig\Loader\LoaderInterface;
use Twig\Loader\SourceContextLoaderInterface;
use Twig\Source;

final class ThemeLoader implements LoaderInterface, SourceContextLoaderInterface
{
    public function __construct(private readonly ThemeManager $themes) {}

    /** @var array<string, ?string> key: "{$theme}::{$name}" => absolute path or null */
    private array $resolved = [];

    private function isNamespaced(string $name): bool
    {
        return str_contains($name, '@'); // пусть другой лоадер обрабатывает namespace
    }

    private function key(string $name): string
    {
        return $this->themes->getCurrentTheme()->getCode().'::'.$name;
    }

    private function resolve(string $name): ?string
    {
        if ($this->isNamespaced($name)) {
            return null;
        }
        $key = $this->key($name);
        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }
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
    {
        return (bool) $this->resolve($name);
    }

    public function getSourceContext(string $name): Source
    {
        $file = $this->resolve($name);
        if ($file && is_file($file)) {
            return new Source(file_get_contents($file), $name, $file);
        }
        // ChainLoader попробует следующий лоадер, если exists() вернёт false
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
```

Важно: не используйте `CompilerPass` и не заменяйте класс `twig.loader.native_filesystem`. Регистрируйте `ThemeLoader` как отдельный лоадер (см. services.yaml) — TwigBundle создаст `ChainLoader` и будет пробовать сначала ваш, затем стандартный `FilesystemLoader`.

### 5. `ThemeExtension` (Twig глобали и функции)

```php
// src/Theme/Twig/ThemeExtension.php
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
    {
        return $this->assetPackage->getUrl($path);
    }

    public function getThemeEntry(string $entryName): string
    {
        $theme = $this->themeManager->getCurrentTheme();
        // Используется префикс в webpack: entry "themes/<code>/main"
        return "themes/{$theme->getCode()}/{$entryName}";
    }

    public function hasFeature(string $feature): bool
    {
        return $this->themeManager->getCurrentTheme()->hasFeature($feature);
    }

    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->themeManager->getCurrentTheme()->getParameter($key, $default);
    }
}
```

### 6. `ThemeAssetPackage` (assets с корректным путём)

```php
// src/Theme/Asset/ThemeAssetPackage.php
namespace App\Theme\Asset;

use App\Theme\ThemeManager;
use Symfony\Component\Asset\Packages;

final class ThemeAssetPackage
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly Packages $packages,
        private readonly string $projectDir,
    ) {}

    /**
     * URL asset с fallback по цепочке тем, используя public/build/themes/<code>/...
     */
    public function getUrl(string $path): string
    {
        foreach ($this->themeManager->getThemeChain() as $theme) {
            $assetPath = 'build/'.$theme->getPublicPath().'/'.$path; // e.g. build/themes/modern/images/logo.svg
            $fullPath = $this->projectDir.'/public/'.$assetPath;
            if (is_file($fullPath)) {
                return $this->packages->getUrl($assetPath);
            }
        }
        // Fallback на стандартный путь
        return $this->packages->getUrl($path);
    }

    public function exists(string $path): bool
    {
        foreach ($this->themeManager->getThemeChain() as $theme) {
            $fullPath = $this->projectDir.'/public/build/'.$theme->getPublicPath().'/'.$path;
            if (is_file($fullPath)) {
                return true;
            }
        }
        return false;
    }
}
```

---

## Webpack Configuration

### `webpack.config.js`

```javascript
const Encore = require('@symfony/webpack-encore');
const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

const themesDir = path.resolve(__dirname, 'themes');
const filter = (process.env.THEME_FILTER || '').split(',').filter(Boolean);

// Сканируем темы
let themes = fs.readdirSync(themesDir)
  .filter(dir => {
    const themeYaml = path.join(themesDir, dir, 'theme.yaml');
    if (!fs.existsSync(themeYaml)) return false;
    const config = yaml.load(fs.readFileSync(themeYaml, 'utf8')) || {};
    return config.enabled !== false;
  })
  .map(dir => {
    const themeYaml = path.join(themesDir, dir, 'theme.yaml');
    const config = yaml.load(fs.readFileSync(themeYaml, 'utf8')) || {};
    const entryPath = path.join(themesDir, dir, 'assets/entry.ts');
    return {
      code: config.code || dir,
      name: config.name || dir,
      path: path.join(themesDir, dir),
      entryPath: fs.existsSync(entryPath) ? entryPath : null,
      parent: config.parent || null
    };
  })
  .filter(theme => theme.entryPath !== null);

if (filter.length) {
  themes = themes.filter(t => filter.includes(t.code) || filter.includes(path.basename(t.path)));
}

// Базовая конфигурация
Encore
  .setOutputPath('public/build/')
  .setPublicPath('/build')
  .enableSassLoader()
  .enableTypeScriptLoader()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .configureBabel(config => {
    config.plugins.push('@babel/plugin-proposal-class-properties');
  })
  .splitEntryChunks()
  .enableSingleRuntimeChunk()
  .configureFilenames({
    js: 'themes/[name].[contenthash].js',
    css: 'themes/[name].[contenthash].css',
  });

// Shared entry
const sharedEntry = path.join(themesDir, '_shared/assets/shared.ts');
if (fs.existsSync(sharedEntry)) {
  Encore.addEntry('shared', sharedEntry);
}

// Entries для каждой темы
themes.forEach(theme => {
  const entryName = `themes/${theme.code}/main`;
  Encore.addEntry(entryName, theme.entryPath);

  // Алиасы
  Encore.addAliases({
    [`@theme/${theme.code}`]: path.join(theme.path, 'assets'),
  });
});

// Общий alias для shared
Encore.addAliases({
  '@theme-shared': path.join(themesDir, '_shared/assets'),
});

// Копируем статические файлы тем (без хеша, предсказуемые пути)
Encore.copyFiles({
  from: './themes',
  to: 'themes/[path][name].[ext]',
  pattern: /\.(png|jpg|jpeg|gif|svg|ico|webp|woff2?|ttf|eot)$/i,
  includeSubdirectories: true
});

module.exports = Encore.getWebpackConfig();
```

### `package.json` scripts

```json
{
  "scripts": {
    "dev": "npx encore dev",
    "watch": "npx encore dev --watch",
    "build": "npx encore production --progress",

    "theme:dev": "node scripts/theme-build.js --mode=dev",
    "theme:watch": "node scripts/theme-build.js --mode=watch",
    "theme:build": "node scripts/theme-build.js --mode=production",
    "theme:list": "node scripts/theme-list.js"
  }
}
```

### `scripts/theme-build.js`

```javascript
#!/usr/bin/env node
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

const args = process.argv.slice(2);
const mode = args.find(a => a.startsWith('--mode='))?.split('=')[1] || 'dev';
const specificTheme = args.find(a => a.startsWith('--theme='))?.split('=')[1] || '';

const themesDir = path.resolve(__dirname, '../themes');
const themes = fs.readdirSync(themesDir)
  .filter(dir => {
    const configPath = path.join(themesDir, dir, 'theme.yaml');
    if (!fs.existsSync(configPath)) return false;
    const config = yaml.load(fs.readFileSync(configPath, 'utf8')) || {};
    return config.enabled !== false;
  });

console.log(`Building themes: ${specificTheme || themes.join(', ')}`);
console.log(`Mode: ${mode}\n`);

const cmd = mode === 'production'
  ? 'npx encore production --progress'
  : (mode === 'watch' ? 'npx encore dev --watch' : 'npx encore dev');

try {
  execSync(cmd, {
    stdio: 'inherit',
    env: {
      ...process.env,
      THEME_FILTER: specificTheme
    }
  });
} catch (error) {
  console.error('Build failed:', error.message);
  process.exit(1);
}
```

---

## Services Configuration

### `config/services.yaml`

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true

  App\:
    resource: '../src/'
    exclude:
      - '../src/DependencyInjection/'
      - '../src/Entity/'
      - '../src/Kernel.php'

  # Theme System
  App\Theme\ThemeManager:
    arguments:
      $themesPath: '%kernel.project_dir%/themes'
      $defaultTheme: '%app_theme.default%'
      $cacheEnabled: '%app_theme.cache_enabled%'
      $cacheTtl: '%app_theme.cache_ttl%'
      $allowedThemes: '%app_theme.allowed_themes%'

  App\Theme\EventListener\ThemeListener:
    arguments:
      $defaultTheme: '%app_theme.default%'
      $allowedThemes: '%app_theme.allowed_themes%'
    tags:
      - { name: kernel.event_listener, event: kernel.request, priority: 64 }

  # Регистрируем ThemeLoader как отдельный Twig loader (без decorates, без CompilerPass)
  App\Theme\Twig\ThemeLoader:
    arguments:
      $themeManager: '@App\Theme\ThemeManager'
    tags:
      - { name: 'twig.loader' }

  App\Theme\Twig\ThemeExtension:
    tags: ['twig.extension']

  App\Theme\Asset\ThemeAssetPackage:
    arguments:
      $projectDir: '%kernel.project_dir%'
    public: true
```

Удалите регистрацию `ThemeLoaderPass` и любые попытки заменить `twig.loader.native_filesystem`. В `src/Kernel.php` не требуется `addCompilerPass` для темы.

---

## Использование в коде

### В контроллерах

```php
abstract class ThemeAwareController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    public function __construct(protected \App\Theme\ThemeManager $themeManager) {}

    protected function renderTheme(string $view, array $parameters = [], \Symfony\Component\HttpFoundation\Response $response = null): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render($view, array_merge($parameters, [
            'theme' => $this->themeManager->getCurrentTheme()
        ]), $response);
    }
}

final class CatalogController extends ThemeAwareController
{
    #[\Symfony\Component\Routing\Annotation\Route('/product/{id}')]
    public function product(int $id): \Symfony\Component\HttpFoundation\Response
    {
        // ThemeLoader автоматически ищет:
        // 1. themes/<current>/templates/catalog/product.html.twig
        // 2. themes/<parent>/templates/catalog/product.html.twig
        // 3. themes/_shared/templates/catalog/product.html.twig
        // 4. templates/catalog/product.html.twig (fallback)
        return $this->renderTheme('catalog/product.html.twig', [
            'product' => $this->productRepository->find($id)
        ]);
    }
}
```

### В шаблонах

```twig
{# themes/default/templates/layout.html.twig #}
{% extends '@Shared/base.html.twig' %}

{% block stylesheets %}
  {{ encore_entry_link_tags(theme_entry('main')) }}
  <link rel="stylesheet" href="{{ theme_asset('styles/custom.css') }}">
{% endblock %}

{% block javascripts %}
  {{ encore_entry_script_tags(theme_entry('main')) }}
{% endblock %}

{% block header %}
  {% include 'components/header.html.twig' %}            {# fallback через ThemeLoader #}
  {# или явный include из shared #}
  {# {% include '@Shared/components/header.html.twig' %} #}
{% endblock %}

{% block body %}
  {% if theme_has_feature('dark_mode') %}
    <button id="theme-toggle">Toggle Dark Mode</button>
  {% endif %}

  <div class="container" style="--primary-color: {{ theme_parameter('primary_color', '#000') }}">
    {% block content %}{% endblock %}
  </div>
{% endblock %}
```

```twig
{# themes/modern/templates/catalog/product.html.twig #}
{% extends 'layout.html.twig' %}

{% block content %}
  <h1>{{ product.name }}</h1>
  {% if app.debug %}
    <small>Theme: {{ current_theme }}</small>
  {% endif %}
{% endblock %}
```

### Theme-specific сервисы
Поддержка `themes/<code>/services.yaml` не включена по умолчанию. Если нужна — добавьте отдельный `CompilerPass`, который на этапе компиляции контейнера найдёт и прогрузит эти файлы через `YamlFileLoader`. Иначе — держите тему-специфичный код внутри основного `src/` с условной активацией по `current_theme`.

---

## Migration Plan

- Phase 1: Инфраструктура
  - Создать структуру `themes/`
  - Реализовать `ThemeManager` (request-scoped), `ThemeListener`, `ThemeLoader`, `ThemeExtension`, `ThemeAssetPackage`
  - Настроить Webpack для тем
  - Написать unit-тесты для `ThemeManager`
  - Добавить feature flag `ENABLE_THEME_SYSTEM=false`
- Phase 2: Shared компоненты
  - Перенести общие компоненты в `themes/_shared/templates/components/`
  - Создать базовый layout в `themes/_shared/templates/base.html.twig`
  - Перенести общие assets в `themes/_shared/assets/`
  - Убедиться, что сайт работает через shared
  - Включить `ENABLE_THEME_SYSTEM=true` в dev
- Phase 3: Default тема
  - Создать `themes/default/theme.yaml`
  - Перенести шаблоны каталога
  - Создать `themes/default/assets/entry.ts`
  - Smoke тесты для страниц
- Phase 4: Тестирование и оптимизация
  - Integration тесты
  - Профилирование
  - Кеширование
  - Документация
- Phase 5: Production
  - Включить флаг
  - Мониторинг
  - Удаление старого fallback при успехе
- Phase 6: Новая тема (опционально)
  - Создать `themes/modern/`
  - Переопределить шаблоны
  - Кастомные стили, A/B

---

## Тестирование

### Unit Tests

```php
// tests/Theme/ThemeManagerTest.php
namespace App\Tests\Theme;

use App\Theme\ThemeManager;
use App\Theme\Exception\ThemeNotFoundException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ThemeManagerTest extends KernelTestCase
{
    private ThemeManager $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->manager = self::getContainer()->get(ThemeManager::class);
    }

    public function testGetDefaultTheme(): void
    {
        $theme = $this->manager->getTheme('default');
        self::assertSame('default', $theme->getCode());
        self::assertTrue($theme->isEnabled());
    }

    public function testThemeChainWithParent(): void
    {
        $this->manager->setCurrentTheme('modern');
        $chain = $this->manager->getThemeChain();
        self::assertCount(3, $chain); // modern -> default -> _shared
        self::assertSame('modern', $chain[0]->getCode());
        self::assertSame('default', $chain[1]->getCode());
        self::assertSame('_shared', $chain[2]->getCode());
    }

    public function testInvalidThemeThrows(): void
    {
        $this->expectException(ThemeNotFoundException::class);
        $this->manager->getTheme('nonexistent');
    }

    public function testCacheClear(): void
    {
        $themes1 = $this->manager->getThemes();
        $this->manager->clearCache();
        $themes2 = $this->manager->getThemes();
        self::assertEquals($themes1, $themes2);
    }
}
```

### Integration Tests

```php
// tests/Theme/ThemeRenderingTest.php
namespace App\Tests\Theme;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ThemeRenderingTest extends WebTestCase
{
    public function testProductPageRendersWithDefaultTheme(): void
    {
        $client = static::createClient();
        $client->request('GET', '/product/1');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('html', 'Product Name');
    }

    public function testThemeSwitchingViaQueryParameter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/product/1?_theme=modern');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('link[href*="themes/modern/main"]');
    }

    public function testSharedComponentFallback(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('header.shared-header');
    }
}
```

### Smoke Tests

```bash
#!/bin/bash
echo "Running theme system smoke tests..."

urls=("/" "/catalog" "/product/1" "/category/electronics")
for url in "${urls[@]}"; do
  echo "Testing $url..."
  code=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost$url")
  if [ "$code" -ne 200 ]; then
    echo "❌ FAILED: $url returned $code"
    exit 1
  fi
  echo "✅ OK: $url"
done

echo "Testing theme switching..."
resp=$(curl -s "http://localhost/?_theme=modern" | grep -c "themes/modern")
if [ "$resp" -eq 0 ]; then
  echo "❌ FAILED: Theme switching not working"
  exit 1
fi
echo "✅ OK: Theme switching"

echo "All smoke tests passed!"
```

---

## Документация

### `README.md` в `themes/`

```markdown
# Система тем каталога

## Структура

- `_shared/` — Общие компоненты и ассеты для всех тем
- `default/` — Базовая тема (fallback)
- `modern/` — Современная тема

## Создание новой темы

1. Создайте директорию `themes/my-theme/`
2. Добавьте `theme.yaml`:
```yaml
name: 'My Theme'
code: 'my-theme'
enabled: true
parent: 'default'
```

3. Структура:
```
my-theme/
  theme.yaml
  templates/
    layout.html.twig
    catalog/
      product.html.twig
  assets/
    entry.ts
    styles/
      main.scss
```

4. Entry point (`assets/entry.ts`):
```typescript
import './styles/main.scss';
import '@theme-shared/components/cart-counter';
```

5. Сборка:
```bash
npm run theme:build -- --theme=my-theme
```

6. Активация (fallback):
```bash
# .env или config
CATALOG_THEME=my-theme
```

## Переопределение компонентов

Чтобы переопределить shared-компонент:
1. Скопируйте `_shared/templates/components/header.html.twig`
2. Положите в `my-theme/templates/components/header.html.twig`
3. `ThemeLoader` автоматически использует вашу версию

## API темы

В контроллерах:
```php
$theme = $this->themeManager->getCurrentTheme();
$color = $theme->getParameter('primary_color');
```

В Twig:
```twig
{{ current_theme }}
{{ theme.name }}
{{ theme_has_feature('dark_mode') }}
{{ theme_parameter('primary_color', '#000') }}
{{ theme_asset('images/logo.svg') }}   {# /build/themes/my-theme/images/logo.svg #}
```

## Отладка

```bash
php bin/console debug:container App\\Theme\\ThemeManager
php bin/console debug:twig
http://localhost/?_theme=modern
php bin/console cache:pool:clear app.cache.theme
```
```

---

## Checklist

Обязательные:
- Реализовать классы: `ThemeManager`, `ThemeDefinition`, `ThemeListener`, `ThemeLoader`, `ThemeExtension`, `ThemeAssetPackage`
- Настроить Webpack и npm-скрипты
- Создать структуру `themes/` с `_shared`, `default`
- Перенести компоненты в `_shared`
- Написать unit/integration тесты
- Документация (README + docs/)
- Feature flag для постепенного роллаута
- Smoke тесты

Критерии приёмки:
- `php bin/console debug:twig` показывает правильные пути и работает ChainLoader
- Переключение темы через `?_theme=X` работает без `cache:clear`
- `npm run theme:build` собирает темы; пути ассетов соответствуют `/build/themes/<code>/...`
- Страницы каталога работают без ошибок
- Fallback: тема → parent → shared → base
- Нет N+1 к файловой системе (кеш резолва в лоадере)

Опционально:
- Admin-превью тем
- Обновление тем через ZIP
- Marketplace тем
- Theme sandboxing

---

## Важные замечания

1. Не используйте `CompilerPass` для замены `twig.loader.native_filesystem`. Регистрируйте `ThemeLoader` через тег `twig.loader` — TwigBundle создаст `ChainLoader`.
2. `ThemeLoader` должен реализовывать `LoaderInterface` и `SourceContextLoaderInterface`, определять `exists`, `getSourceContext`, `getCacheKey`, `isFresh`, кэшировать резолв на запрос.
3. `ThemeManager` — stateless относительно текущей темы, состояние хранится в `RequestStack` (`_theme` атрибут).
4. Интегрируйте конфигурацию через `config/packages/app_theme.yaml` (default, allowed, cache, ttl).
5. Кеш включён в prod и выключен в dev: используйте `!%kernel.debug%`.
6. Валидация: уникальные коды, допустимые символы, отсутствие циклов наследования, существование родителей, allowlist.
7. Assets: статические копии без хеша (`copyFiles`), а для JS/CSS используйте `encore_entry_*` с хешами. Пути — `public/build/themes/<code>/...`.
8. Приоритет листенера — 64, чтобы тема была выбрана до рендера.
9. Безопасность: санитайз кода темы (`^[a-z0-9._-]+$`), проверка `enabled`, `allowed`.
10. Документируйте всё; добавьте feature flag для постепенного включения.