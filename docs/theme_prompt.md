# Промпт для создания системы тем в Symfony 7

## Контекст
Проект: `c:\laragon\www\new3`  
Цель: Внедрить систему независимых тем для каталога с runtime переключением, полной изоляцией templates/assets и fallback механизмом.

**Критически важно:** Система должна поддерживать переключение тем в runtime (субдомены, пользователи) БЕЗ `cache:clear`.

---

## Архитектура

### 1. Структура каталогов

```
themes/
  _shared/                          # Префикс _ для явного приоритета
    theme.yaml
    templates/
      base.html.twig
      components/
        header.html.twig
        footer.html.twig
        cart_counter.html.twig
    assets/
      shared.ts                     # Общие модули
      styles/
        variables.scss
        mixins.scss
    
  default/
    theme.yaml                      # Единственный источник конфига темы
    templates/
      catalog/
        product.html.twig
        category.html.twig
      layout.html.twig              # Extends @Shared/base.html.twig
    assets/
      entry.ts                      # Entry point темы
      styles/
        main.scss
    services.yaml                   # Опционально: theme-specific сервисы
    
  modern/
    theme.yaml
    templates/
      catalog/
        product.html.twig           # Переопределяет default
    assets/
      entry.ts
      
config/
  themes.yaml                       # Глобальная конфигурация
  
src/
  Theme/
    ThemeManager.php                # Registry + Resolver + Context в одном
    Twig/
      ThemeExtension.php            # Twig функции
      ThemeLoader.php               # Custom Twig Loader
    Asset/
      ThemeAssetPackage.php         # Assets с fallback
    EventListener/
      ThemeListener.php             # Request listener
    Exception/
      ThemeNotFoundException.php
      ThemeDisabledException.php
```

### 2. Конфигурация темы (theme.yaml)

```yaml
# themes/modern/theme.yaml
name: 'Modern Theme'
code: 'modern'                      # Уникальный идентификатор
enabled: true                       # Можно отключить тему
parent: 'default'                   # Наследование (optional)

paths:
  templates: 'templates'
  assets: 'assets'
  public: 'public/themes/modern'    # Для статики

metadata:
  description: 'Modern minimalist design'
  author: 'Your Company'
  version: '1.0.0'
  preview: 'preview.jpg'
  
features:
  dark_mode: true
  rtl_support: false
  
# Опционально: переопределение параметров
parameters:
  products_per_page: 24
  primary_color: '#007bff'
```

### 3. Глобальная конфигурация

```yaml
# config/themes.yaml
app_theme:
  default: 'default'                # Fallback тема
  cache_enabled: true
  cache_ttl: 3600
  
  # Стратегия выбора темы (приоритет сверху вниз)
  resolution_strategy:
    - subdomain                     # theme1.example.com
    - user_preference               # Из БД пользователя
    - environment                   # .env переменная
    
  allowed_themes:
    - default
    - modern
```

```yaml
# config/packages/twig.yaml
twig:
  paths:
    '%kernel.project_dir%/themes/_shared/templates': 'Shared'
```

---

## Реализация компонентов

### 1. ThemeManager (объединяет Registry + Resolver + Context)

```php
// src/Theme/ThemeManager.php
namespace App\Theme;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ThemeManager
{
    private const CACHE_KEY = 'app.themes.registry';
    
    private ?array $themes = null;
    private ?ThemeDefinition $currentTheme = null;
    
    public function __construct(
        private readonly string $themesPath,
        private readonly string $defaultTheme,
        private readonly CacheInterface $cache,
        private readonly bool $cacheEnabled = true,
    ) {}
    
    /**
     * Получить все доступные темы
     */
    public function getThemes(): array
    {
        if ($this->themes === null) {
            $this->themes = $this->loadThemes();
        }
        
        return $this->themes;
    }
    
    /**
     * Получить тему по коду
     */
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
    
    /**
     * Проверить существование темы
     */
    public function hasTheme(string $code): bool
    {
        return isset($this->getThemes()[$code]);
    }
    
    /**
     * Установить текущую тему
     */
    public function setCurrentTheme(string $code): void
    {
        $this->currentTheme = $this->getTheme($code);
    }
    
    /**
     * Получить текущую тему
     */
    public function getCurrentTheme(): ThemeDefinition
    {
        if ($this->currentTheme === null) {
            $this->currentTheme = $this->getTheme($this->defaultTheme);
        }
        
        return $this->currentTheme;
    }
    
    /**
     * Получить цепочку тем с наследованием (текущая -> parent -> shared)
     */
    public function getThemeChain(?ThemeDefinition $theme = null): array
    {
        $theme ??= $this->getCurrentTheme();
        $chain = [$theme];
        
        // Добавляем родителей
        $current = $theme;
        while ($parent = $current->getParent()) {
            $parentTheme = $this->getTheme($parent);
            $chain[] = $parentTheme;
            $current = $parentTheme;
        }
        
        // Всегда добавляем shared в конец
        if ($this->hasTheme('_shared')) {
            $chain[] = $this->getTheme('_shared');
        }
        
        return $chain;
    }
    
    /**
     * Загрузка тем с кешированием
     */
    private function loadThemes(): array
    {
        if (!$this->cacheEnabled) {
            return $this->scanThemes();
        }
        
        return $this->cache->get(
            self::CACHE_KEY,
            function (ItemInterface $item) {
                $item->expiresAfter(3600);
                return $this->scanThemes();
            }
        );
    }
    
    /**
     * Сканирование директории тем
     */
    private function scanThemes(): array
    {
        $themes = [];
        $dirs = glob($this->themesPath . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $code = basename($dir);
            $configFile = "$dir/theme.yaml";
            
            if (!file_exists($configFile)) {
                continue;
            }
            
            $config = Yaml::parseFile($configFile);
            $themes[$code] = new ThemeDefinition(
                code: $config['code'] ?? $code,
                name: $config['name'] ?? $code,
                path: $dir,
                enabled: $config['enabled'] ?? true,
                parent: $config['parent'] ?? null,
                metadata: $config['metadata'] ?? [],
                features: $config['features'] ?? [],
                parameters: $config['parameters'] ?? []
            );
        }
        
        return $themes;
    }
    
    /**
     * Очистить кеш тем
     */
    public function clearCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->themes = null;
    }
}
```

### 2. ThemeDefinition (Value Object)

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
    
    public function getTemplatePath(): string 
    { 
        return $this->path . '/templates'; 
    }
    
    public function getAssetPath(): string 
    { 
        return $this->path . '/assets'; 
    }
    
    public function getPublicPath(): string 
    { 
        return 'themes/' . $this->code; 
    }
    
    public function getMetadata(string $key = null): mixed
    {
        return $key ? ($this->metadata[$key] ?? null) : $this->metadata;
    }
    
    public function hasFeature(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }
    
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }
}
```

### 3. ThemeListener (Runtime Resolution)

```php
// src/Theme/EventListener/ThemeListener.php
namespace App\Theme\EventListener;

use App\Theme\ThemeManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class ThemeListener
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly string $defaultTheme,
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
        // 1. Проверяем subdomain
        $host = $request->getHost();
        if (preg_match('/^([^.]+)\./', $host, $matches)) {
            $subdomain = $matches[1];
            if ($this->themeManager->hasTheme($subdomain)) {
                return $subdomain;
            }
        }
        
        // 2. Проверяем query parameter (для превью)
        if ($theme = $request->query->get('_theme')) {
            if ($this->themeManager->hasTheme($theme)) {
                return $theme;
            }
        }
        
        // 3. Проверяем сессию (пользовательский выбор)
        if ($theme = $request->getSession()->get('theme')) {
            if ($this->themeManager->hasTheme($theme)) {
                return $theme;
            }
        }
        
        // 4. Fallback на default
        return $this->defaultTheme;
    }
}
```

### 4. Custom Twig Loader с Fallback

```php
// src/Theme/Twig/ThemeLoader.php
namespace App\Theme\Twig;

use App\Theme\ThemeManager;
use Twig\Loader\FilesystemLoader;
use Twig\Source;
use Twig\Error\LoaderError;

class ThemeLoader extends FilesystemLoader
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        string|iterable $paths = [],
        ?string $rootPath = null
    ) {
        parent::__construct($paths, $rootPath);
    }
    
    public function getSourceContext(string $name): Source
    {
        // Если используется namespace (@Shared/..., @ActiveTheme/...) - стандартная обработка
        if (str_contains($name, '@')) {
            return parent::getSourceContext($name);
        }
        
        // Иначе ищем в цепочке тем
        $chain = $this->themeManager->getThemeChain();
        
        foreach ($chain as $theme) {
            try {
                $path = $theme->getTemplatePath() . '/' . $name;
                if (file_exists($path)) {
                    return new Source(
                        file_get_contents($path),
                        $name,
                        $path
                    );
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback на стандартный loader
        return parent::getSourceContext($name);
    }
    
    public function exists(string $name): bool
    {
        if (str_contains($name, '@')) {
            return parent::exists($name);
        }
        
        $chain = $this->themeManager->getThemeChain();
        
        foreach ($chain as $theme) {
            $path = $theme->getTemplatePath() . '/' . $name;
            if (file_exists($path)) {
                return true;
            }
        }
        
        return parent::exists($name);
    }
}
```

### 5. Compiler Pass для регистрации Loader

```php
// src/Theme/DependencyInjection/ThemeLoaderPass.php
namespace App\Theme\DependencyInjection;

use App\Theme\Twig\ThemeLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ThemeLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Заменяем стандартный FilesystemLoader на наш ThemeLoader
        $container
            ->getDefinition('twig.loader.native_filesystem')
            ->setClass(ThemeLoader::class)
            ->setArgument(0, new Reference(ThemeManager::class));
        
        // Регистрируем namespace для shared
        $loader = $container->getDefinition('twig.loader.native_filesystem');
        $loader->addMethodCall('addPath', [
            '%kernel.project_dir%/themes/_shared/templates',
            'Shared'
        ]);
    }
}
```

### 6. Twig Extension

```php
// src/Theme/Twig/ThemeExtension.php
namespace App\Theme\Twig;

use App\Theme\ThemeManager;
use App\Theme\Asset\ThemeAssetPackage;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class ThemeExtension extends AbstractExtension implements GlobalsInterface
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
    
    /**
     * Получить путь к asset темы с fallback
     */
    public function getThemeAsset(string $path): string
    {
        return $this->assetPackage->getUrl($path);
    }
    
    /**
     * Получить Encore entry для темы
     */
    public function getThemeEntry(string $entryName): string
    {
        $theme = $this->themeManager->getCurrentTheme();
        return "themes/{$theme->getCode()}/$entryName";
    }
    
    /**
     * Проверить наличие feature
     */
    public function hasFeature(string $feature): bool
    {
        return $this->themeManager->getCurrentTheme()->hasFeature($feature);
    }
    
    /**
     * Получить параметр темы
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->themeManager->getCurrentTheme()->getParameter($key, $default);
    }
}
```

### 7. Theme Asset Package

```php
// src/Theme/Asset/ThemeAssetPackage.php
namespace App\Theme\Asset;

use App\Theme\ThemeManager;
use Symfony\Component\Asset\Packages;

class ThemeAssetPackage
{
    public function __construct(
        private readonly ThemeManager $themeManager,
        private readonly Packages $packages,
    ) {}
    
    /**
     * Получить URL asset с fallback по цепочке тем
     */
    public function getUrl(string $path): string
    {
        $chain = $this->themeManager->getThemeChain();
        
        foreach ($chain as $theme) {
            $assetPath = $theme->getPublicPath() . '/' . $path;
            $fullPath = 'public/' . $assetPath;
            
            if (file_exists($fullPath)) {
                return $this->packages->getUrl($assetPath);
            }
        }
        
        // Fallback на стандартный путь
        return $this->packages->getUrl($path);
    }
    
    /**
     * Проверить существование asset
     */
    public function exists(string $path): bool
    {
        $chain = $this->themeManager->getThemeChain();
        
        foreach ($chain as $theme) {
            $fullPath = 'public/' . $theme->getPublicPath() . '/' . $path;
            if (file_exists($fullPath)) {
                return true;
            }
        }
        
        return false;
    }
}
```

---

## Webpack Configuration

### webpack.config.js

```javascript
const Encore = require('@symfony/webpack-encore');
const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

// Загружаем конфигурацию тем
const themesConfig = yaml.load(
    fs.readFileSync('./config/themes.yaml', 'utf8')
);

// Сканируем темы
const themesDir = path.resolve(__dirname, 'themes');
const themes = fs.readdirSync(themesDir)
    .filter(dir => {
        const themeYaml = path.join(themesDir, dir, 'theme.yaml');
        if (!fs.existsSync(themeYaml)) return false;
        
        const config = yaml.load(fs.readFileSync(themeYaml, 'utf8'));
        return config.enabled !== false;
    })
    .map(dir => {
        const themeYaml = path.join(themesDir, dir, 'theme.yaml');
        const config = yaml.load(fs.readFileSync(themeYaml, 'utf8'));
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

// Базовая конфигурация
Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .enableSassLoader()
    .enableTypeScriptLoader()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .configureBabel((config) => {
        config.plugins.push('@babel/plugin-proposal-class-properties');
    })
    .enableSingleRuntimeChunk();

// Добавляем shared entry
const sharedEntry = path.join(themesDir, '_shared/assets/shared.ts');
if (fs.existsSync(sharedEntry)) {
    Encore.addEntry('shared', sharedEntry);
}

// Добавляем entries для каждой темы
themes.forEach(theme => {
    const entryName = `themes/${theme.code}/main`;
    Encore.addEntry(entryName, theme.entryPath);
    
    // Алиасы для удобного импорта
    Encore.addAliases({
        [`@theme/${theme.code}`]: path.join(theme.path, 'assets'),
        '@theme-shared': path.join(themesDir, '_shared/assets'),
    });
});

// Копируем статические файлы тем
Encore.copyFiles({
    from: './themes',
    to: 'themes/[path][name].[hash:8].[ext]',
    pattern: /\.(png|jpg|jpeg|gif|svg|ico|webp|woff2?)$/,
    includeSubdirectories: true
});

module.exports = Encore.getWebpackConfig();
```

### package.json scripts

```json
{
  "scripts": {
    "dev": "encore dev",
    "watch": "encore dev --watch",
    "build": "encore production --progress",
    
    "theme:dev": "node scripts/theme-build.js --mode=dev",
    "theme:watch": "node scripts/theme-build.js --mode=watch",
    "theme:build": "node scripts/theme-build.js --mode=production",
    "theme:list": "node scripts/theme-list.js"
  }
}
```

### scripts/theme-build.js

```javascript
#!/usr/bin/env node
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

const args = process.argv.slice(2);
const mode = args.find(a => a.startsWith('--mode='))?.split('=')[1] || 'dev';
const specificTheme = args.find(a => a.startsWith('--theme='))?.split('=')[1];

// Получаем список тем
const themesDir = path.resolve(__dirname, '../themes');
const themes = fs.readdirSync(themesDir)
    .filter(dir => {
        const configPath = path.join(themesDir, dir, 'theme.yaml');
        if (!fs.existsSync(configPath)) return false;
        
        if (specificTheme && dir !== specificTheme) return false;
        
        const config = yaml.load(fs.readFileSync(configPath, 'utf8'));
        return config.enabled !== false;
    });

console.log(`Building themes: ${themes.join(', ')}`);
console.log(`Mode: ${mode}\n`);

// Определяем команду Encore
let encoreCmd = 'encore dev';
if (mode === 'watch') {
    encoreCmd = 'encore dev --watch';
} else if (mode === 'production') {
    encoreCmd = 'encore production --progress';
}

try {
    execSync(`npm run ${encoreCmd.split(' ')[1]}`, {
        stdio: 'inherit',
        env: {
            ...process.env,
            THEME_FILTER: specificTheme || themes.join(',')
        }
    });
} catch (error) {
    console.error('Build failed:', error.message);
    process.exit(1);
}
```

---

## Services Configuration

### config/services.yaml

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
            $defaultTheme: '%env(string:CATALOG_THEME)%'
            $cacheEnabled: '%kernel.debug%' # false в dev, true в prod
            
    App\Theme\EventListener\ThemeListener:
        arguments:
            $defaultTheme: '%env(string:CATALOG_THEME)%'
            
    App\Theme\Twig\ThemeLoader:
        decorates: 'twig.loader.native_filesystem'
        arguments:
            $themeManager: '@App\Theme\ThemeManager'
            
    App\Theme\Twig\ThemeExtension:
        tags: ['twig.extension']
        
    App\Theme\Asset\ThemeAssetPackage:
        public: true
```

### src/Kernel.php

```php
protected function build(ContainerBuilder $container): void
{
    $container->addCompilerPass(new ThemeLoaderPass());
}
```

### .env

```bash
CATALOG_THEME=default
```

---

## Использование в коде

### В контроллерах

```php
// Базовый контроллер с поддержкой тем
abstract class ThemeAwareController extends AbstractController
{
    public function __construct(
        protected ThemeManager $themeManager
    ) {}
    
    /**
     * Render с автоматическим fallback
     */
    protected function renderTheme(
        string $view,
        array $parameters = [],
        Response $response = null
    ): Response {
        // Просто используем стандартный render - ThemeLoader сам найдёт шаблон
        return $this->render($view, array_merge(
            $parameters,
            ['theme' => $this->themeManager->getCurrentTheme()]
        ), $response);
    }
}

// Использование
class CatalogController extends ThemeAwareController
{
    #[Route('/product/{id}')]
    public function product(int $id): Response
    {
        // ThemeLoader автоматически ищет:
        // 1. themes/modern/templates/catalog/product.html.twig
        // 2. themes/default/templates/catalog/product.html.twig
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
    
    {# Или прямая ссылка на asset темы #}
    <link rel="stylesheet" href="{{ theme_asset('styles/custom.css') }}">
{% endblock %}

{% block javascripts %}
    {{ encore_entry_script_tags(theme_entry('main')) }}
{% endblock %}

{% block header %}
    {# Стандартный include с fallback #}
    {% include 'components/header.html.twig' %}
    
    {# Или явно из shared #}
    {% include '@Shared/components/header.html.twig' %}
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
    
    {# Информация о текущей теме для отладки #}
    {% if app.debug %}
        <small>Theme: {{ current_theme }}</small>
    {% endif %}
{% endblock %}
```

### Theme-specific сервисы

```yaml
# themes/modern/services.yaml
services:
    App\Theme\Modern\:
        resource: 'src/'
        
    # Пример: кастомный Twig extension для темы
    App\Theme\Modern\Twig\ModernExtension:
        tags: ['twig.extension']
```

```php
// themes/modern/src/Twig/ModernExtension.php
namespace App\Theme\Modern\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ModernExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('modern_format', [$this, 'modernFormat']),
        ];
    }
    
    public function modernFormat(string $text): string
    {
        // Theme-specific логика
        return strtoupper($text);
    }
}
```

---

## Migration Plan

### Phase 1: Инфраструктура (неделя 1)
- [ ] Создать структуру `themes/`
- [ ] Реализовать `ThemeManager`
- [ ] Создать `ThemeListener`, `ThemeLoader`, `ThemeExtension`
- [ ] Настроить Webpack для тем
- [ ] Написать unit-тесты для `ThemeManager`
- [ ] Добавить feature flag `ENABLE_THEME_SYSTEM=false`

### Phase 2: Shared компоненты (неделя 2)
- [ ] Перенести общие компоненты в `themes/_shared/templates/components/`
- [ ] Создать базовый layout в `themes/_shared/templates/base.html.twig`
- [ ] Перенести общие assets в `themes/_shared/assets/`
- [ ] Убедиться, что сайт работает через shared
- [ ] Включить `ENABLE_THEME_SYSTEM=true` в dev

### Phase 3: Default тема (неделя 3)
- [ ] Создать `themes/default/theme.yaml`
- [ ] Постепенно переносить шаблоны каталога (по 10% в день)
- [ ] Создать `themes/default/assets/entry.ts`
- [ ] Тестировать каждую страницу после переноса
- [ ] Smoke тесты для всех основных страниц

### Phase 4: Тестирование и оптимизация (неделя 4)
- [ ] Написать integration тесты
- [ ] Профилирование производительности
- [ ] Добавить кеширование где необходимо
- [ ] Code review
- [ ] Документация

### Phase 5: Production (неделя 5)
- [ ] Включить `ENABLE_THEME_SYSTEM=true` в production
- [ ] Мониторинг ошибок 48 часов
- [ ] Hotfix при необходимости
- [ ] Удаление старых `templates/catalog` (если всё OK)

### Phase 6: Новая тема (опционально)
- [ ] Создать `themes/modern/`
- [ ] Переопределить ключевые шаблоны
- [ ] Кастомные стили
- [ ] A/B тестирование

---

## Тестирование

### Unit Tests

```php
// tests/Theme/ThemeManagerTest.php
namespace App\Tests\Theme;

use App\Theme\ThemeManager;
use App\Theme\Exception\ThemeNotFoundException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ThemeManagerTest extends KernelTestCase
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
        
        $this->assertSame('default', $theme->getCode());
        $this->assertTrue($theme->isEnabled());
    }
    
    public function testThemeChainWithParent(): void
    {
        $this->manager->setCurrentTheme('modern');
        $chain = $this->manager->getThemeChain();
        
        // modern -> default -> _shared
        $this->assertCount(3, $chain);
        $this->assertSame('modern', $chain[0]->getCode());
        $this->assertSame('default', $chain[1]->getCode());
        $this->assertSame('_shared', $chain[2]->getCode());
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
        
        $this->assertEquals($themes1, $themes2);
    }
}
```

### Integration Tests

```php
// tests/Theme/ThemeRenderingTest.php
namespace App\Tests\Theme;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ThemeRenderingTest extends WebTestCase
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
        // Проверяем, что загружен правильный CSS
        $this->assertSelectorExists('link[href*="themes/modern/main"]');
    }
    
    public function testSharedComponentFallback(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        
        // Проверяем, что header из shared загрузился
        $this->assertSelectorExists('header.shared-header');
    }
}
```

### Smoke Tests

```bash
#!/bin/bash
# tests/smoke/theme-smoke.sh

echo "Running theme system smoke tests..."

# Проверяем доступность основных страниц
urls=(
    "/"
    "/catalog"
    "/product/1"
    "/category/electronics"
)

for url in "${urls[@]}"; do
    echo "Testing $url..."
    response=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost$url")
    
    if [ $response -ne 200 ]; then
        echo "❌ FAILED: $url returned $response"
        exit 1
    fi
    echo "✅ OK: $url"
done

# Проверяем переключение тем
echo "Testing theme switching..."
response=$(curl -s "http://localhost/?_theme=modern" | grep -c "themes/modern")
if [ $response -eq 0 ]; then
    echo "❌ FAILED: Theme switching not working"
    exit 1
fi
echo "✅ OK: Theme switching"

echo "All smoke tests passed!"
```

---

## Документация

### README.md в themes/

```markdown
# Система тем каталога

## Структура

- `_shared/` - Общие компоненты для всех тем
- `default/` - Базовая тема (fallback)
- `modern/` - Современная минималистичная тема

## Создание новой темы

1. Создайте директорию `themes/my-theme/`
2. Добавьте `theme.yaml`:
```yaml
name: 'My Theme'
code: 'my-theme'
enabled: true
parent: 'default'  # Наследуем от default
```

3. Создайте структуру:
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
// Импортируем shared модули
import '@theme-shared/components/cart-counter';
```

5. Постройте assets:
```bash
npm run theme:build -- --theme=my-theme
```

6. Активируйте:
```bash
# .env
CATALOG_THEME=my-theme
```

## Переопределение компонентов

Чтобы переопределить компонент из shared:
1. Скопируйте `_shared/templates/components/header.html.twig`
2. В `my-theme/templates/components/header.html.twig`
3. Система автоматически использует вашу версию

## Доступ к API темы

### В контроллерах
```php
$theme = $this->themeManager->getCurrentTheme();
$color = $theme->getParameter('primary_color');
```

### В Twig
```twig
{{ current_theme }}                           {# 'modern' #}
{{ theme.name }}                              {# 'Modern Theme' #}
{{ theme_has_feature('dark_mode') }}          {# true/false #}
{{ theme_parameter('primary_color', '#000') }} {# #007bff #}
{{ theme_asset('images/logo.svg') }}          {# /build/themes/modern/images/logo.svg #}
```

## Отладка

```bash
# Список тем
php bin/console debug:container ThemeManager

# Проверка путей Twig
php bin/console debug:twig

# Превью темы
http://localhost/?_theme=modern

# Очистка кеша тем
php bin/console cache:pool:clear app.cache.theme
```
```

### docs/THEME_SYSTEM.md

Полная документация с:
- Архитектурой решения
- Диаграммами последовательности
- API reference
- Best practices
- Troubleshooting

---

## Checklist для выполнения

### Обязательные задачи
- [ ] Реализовать все классы из раздела "Реализация компонентов"
- [ ] Настроить Webpack согласно конфигурации
- [ ] Создать структуру `themes/` с `_shared`, `default`
- [ ] Перенести компоненты в `_shared`
- [ ] Написать минимум 10 unit/integration тестов
- [ ] Добавить документацию (README + docs/)
- [ ] Feature flag для постепенного роллаута
- [ ] Smoke тесты для production

### Критерии приёмки
- [ ] `php bin/console debug:twig` показывает правильные пути без дублей
- [ ] Переключение темы через `?_theme=X` работает БЕЗ cache:clear
- [ ] `npm run theme:build` успешно собирает все темы
- [ ] Все тесты проходят (100% coverage для ThemeManager)
- [ ] Страницы каталога работают без ошибок
- [ ] Assets загружаются с правильными версиями
- [ ] Fallback работает (тема → parent → shared → base)
- [ ] Нет N+1 запросов к файловой системе (кеширование)

### Опционально
- [ ] Admin панель для превью тем
- [ ] Механизм обновления тем через ZIP
- [ ] Marketplace тем
- [ ] Theme sandboxing (изоляция PHP кода)

---

## Важные замечания

1. **НЕ используйте CompilerPass для динамических путей** - только для регистрации loader'а
2. **Всегда проверяйте безопасность** - валидация кодов тем, проверка enabled
3. **Кешируйте агрессивно** - парсинг YAML на каждый запрос убьёт performance
4. **Тестируйте fallback** - это критическая часть системы
5. **Feature flag обязателен** - для постепенного роллаута
6. **Документируйте все** - будущие разработчики скажут спасибо