# Промпт: Система независимых тем для каталога (Symfony 7, проект new3)

## Контекст
- Проект: `c:\laragon\www\new3`
- Задача: внедрить систему независимых тем только для витрины каталога. Admin SPA не трогаем.
- Требования: runtime‑переключение темы без `cache:clear`, изоляция `templates/assets`, цепочка fallback: текущая тема → родитель → `_shared` → базовые `templates/`.
- Обратная совместимость: сохранить текущий entry `catalog` и плавно мигрировать на тему `default` через фиче‑флаг.

---

## Архитектура

### Структура директорий тем
```
themes/
  _shared/
    theme.yaml
    templates/
      base.html.twig               # базовый layout/блоки по умолчанию
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
      layout.html.twig             # {% extends '@Shared/base.html.twig' %}
      catalog/
        product.html.twig
        category.html.twig
    assets/
      entry.ts                     # импортирует shared + стили + bootstrap каталога

  modern/
    theme.yaml
    templates/
      catalog/
        product.html.twig          # переопределяет default
    assets/
      entry.ts
```

### Компоненты
- `ThemeManager` — реестр тем + request‑scoped контекст текущей темы.
- `ThemeDefinition` — value object темы.
- `ThemeListener` — выбирает тему в runtime (subdomain → query → session → default).
- `ThemeLoader` — дополнительный Twig loader (ChainLoader) с fallback цепочкой.
- `ThemeExtension` — Twig‑глобали/функции: `current_theme`, `theme_entry()`, `theme_asset()` и др.
- `ThemeAssetPackage` — формирует URL ассетов тем с проверкой наличия и fallback по цепочке.

Особенности адаптации к проекту new3:
- Не изменяем Admin SPA: entry `admin` и алиасы `@admin/**` остаются неизменными.
- Сохраняем текущий entry `catalog` как fallback до полной миграции на темы.
- Тема `default` импортирует существующий bootstrap каталога из `assets/catalog/catalog.ts`, чтобы не дублировать логику.

---

## Конфигурация

### Глобальные параметры
```yaml
# config/packages/app_theme.yaml
parameters:
  app_theme.enabled: false                 # мягкий фиче‑флаг
  app_theme.default: 'default'
  app_theme.cache_enabled: !'%kernel.debug%'
  app_theme.cache_ttl: 3600
  app_theme.allowed_themes:
    - default
    - modern
```

### Twig
Добавляем shared‑путь и глобаль флага.
```yaml
# config/packages/twig.yaml (дополнить существующий)
twig:
  paths:
    '%kernel.project_dir%/themes/_shared/templates': 'Shared'
  globals:
    theme_enabled: '%app_theme.enabled%'
```

---

## Сервисы (Symfony)
```yaml
# config/services.yaml (добавить в конец файла)
services:
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

  # Дополнительный Twig Loader, не заменяем FilesystemLoader
  App\Theme\Twig\ThemeLoader:
    arguments:
      $themeManager: '@App\Theme\ThemeManager'
    tags: ['twig.loader']

  App\Theme\Twig\ThemeExtension:
    tags: ['twig.extension']

  App\Theme\Asset\ThemeAssetPackage:
    arguments:
      $projectDir: '%kernel.project_dir%'
    public: true
```

---

## Реализация компонентов (кратко)

### ThemeManager
```php
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

    public function getThemes(): array
    {
        if ($this->themes === null) {
            $this->themes = $this->loadThemes();
        }
        return $this->themes;
    }

    public function hasTheme(string $code): bool { return isset($this->getThemes()[$code]); }
    public function getTheme(string $code): ThemeDefinition { $t=$this->getThemes(); if(!isset($t[$code])) throw new \RuntimeException("Theme '$code' not found"); $def=$t[$code]; if(!$def->isEnabled()) throw new \RuntimeException("Theme '$code' is disabled"); return $def; }

    public function setCurrentTheme(string $code): void { $req=$this->requestStack->getMainRequest(); if($req){ $req->attributes->set('_theme',$code);} }
    public function getCurrentTheme(): ThemeDefinition { $req=$this->requestStack->getMainRequest(); $code=$req?->attributes->get('_theme') ?? $this->defaultTheme; return $this->getTheme($code); }

    /** @return ThemeDefinition[] */
    public function getThemeChain(?ThemeDefinition $theme = null): array
    {
        $theme ??= $this->getCurrentTheme();
        $chain = [$theme];
        $seen = [$theme->getCode() => true];
        $current = $theme;
        while ($parent = $current->getParent()) {
            if (isset($seen[$parent])) throw new \RuntimeException('Theme inheritance cycle detected');
            $current = $this->getTheme($parent);
            $chain[] = $current;
            $seen[$parent] = true;
        }
        if ($this->hasTheme('_shared')) { $chain[] = $this->getTheme('_shared'); }
        return $chain;
    }

    private function loadThemes(): array
    {
        if (!$this->cacheEnabled) { return $this->scanThemes(); }
        return $this->cache->get(self::CACHE_KEY, fn(ItemInterface $i) => ($i->expiresAfter($this->cacheTtl)) || true ? $this->scanThemes() : []);
    }

    private function scanThemes(): array
    {
        $themes = [];
        foreach (glob($this->themesPath.'/*', GLOB_ONLYDIR) as $dir) {
            $configFile = $dir.'/theme.yaml';
            if (!is_file($configFile)) continue;
            $cfg = Yaml::parseFile($configFile) ?? [];
            $code = $cfg['code'] ?? basename($dir);
            if (!preg_match('/^[a-z0-9._-]+$/', $code)) throw new \InvalidArgumentException("Invalid theme code: $code");
            if (!empty($this->allowedThemes) && !in_array($code, $this->allowedThemes, true)) continue;
            $themes[$code] = new ThemeDefinition(
                code: $code,
                name: $cfg['name'] ?? $code,
                path: $dir,
                enabled: $cfg['enabled'] ?? true,
                parent: $cfg['parent'] ?? null,
                metadata: $cfg['metadata'] ?? [],
                features: $cfg['features'] ?? [],
                parameters: $cfg['parameters'] ?? [],
            );
        }
        foreach ($themes as $code => $t) { $p=$t->getParent(); if ($p!==null && !isset($themes[$p])) throw new \InvalidArgumentException("Parent theme '$p' not found for '$code'"); }
        return $themes;
    }

    public function clearCache(): void { $this->cache->delete(self::CACHE_KEY); $this->themes = null; }
}
```

### ThemeDefinition
```php
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
        private readonly array $parameters = [],
    ) {}
    public function getCode(): string { return $this->code; }
    public function getName(): string { return $this->name; }
    public function getPath(): string { return $this->path; }
    public function isEnabled(): bool { return $this->enabled; }
    public function getParent(): ?string { return $this->parent; }
    public function getTemplatePath(): string { return $this->path.'/templates'; }
    public function getAssetPath(): string { return $this->path.'/assets'; }
    public function getPublicPath(): string { return 'themes/'.$this->code; }
    public function getMetadata(string $key = null): mixed { return $key ? ($this->metadata[$key] ?? null) : $this->metadata; }
    public function hasFeature(string $f): bool { return (bool)($this->features[$f] ?? false); }
    public function getParameter(string $k, mixed $d = null): mixed { return $this->parameters[$k] ?? $d; }
}
```

### ThemeListener
```php
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
        if (!$event->isMainRequest()) return;
        $request = $event->getRequest();
        $theme = $this->resolveTheme($request);
        $this->themeManager->setCurrentTheme($theme);
    }

    private function resolveTheme(Request $r): string
    {
        $candidate = null;
        if (preg_match('/^([^.]+)\./', $r->getHost(), $m)) $candidate = $m[1];
        $candidate = $r->query->get('_theme', $candidate);
        if ($r->hasPreviousSession()) $candidate = $r->getSession()->get('theme', $candidate);
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

### ThemeLoader
```php
namespace App\Theme\Twig;

use App\Theme\ThemeManager;
use Twig\Loader\LoaderInterface;
use Twig\Loader\SourceContextLoaderInterface;
use Twig\Source;

final class ThemeLoader implements LoaderInterface, SourceContextLoaderInterface
{
    public function __construct(private readonly ThemeManager $themes) {}
    /** @var array<string, ?string> */
    private array $resolved = [];
    private function isNamespaced(string $name): bool { return str_contains($name, '@'); }
    private function key(string $name): string { return $this->themes->getCurrentTheme()->getCode().'::'.$name; }
    private function resolve(string $name): ?string
    {
        if ($this->isNamespaced($name)) return null;
        $key = $this->key($name);
        if (array_key_exists($key, $this->resolved)) return $this->resolved[$key];
        foreach ($this->themes->getThemeChain() as $theme) {
            $path = $theme->getTemplatePath().'/'.$name;
            if (is_file($path)) { $real = realpath($path) ?: $path; return $this->resolved[$key] = $real; }
        }
        return $this->resolved[$key] = null;
    }
    public function exists(string $name): bool { return (bool) $this->resolve($name); }
    public function getSourceContext(string $name): Source
    { $file=$this->resolve($name); if ($file && is_file($file)) return new Source(file_get_contents($file), $name, $file); throw new \Twig\Error\LoaderError(sprintf('Template "%s" not found in theme chain.', $name)); }
    public function getCacheKey(string $name): string
    { $file=$this->resolve($name); return $file ? 'theme:'.$this->themes->getCurrentTheme()->getCode().':'.$file : 'theme:miss:'.$name.':'.$this->themes->getCurrentTheme()->getCode(); }
    public function isFresh(string $name, int $time): bool
    { $file=$this->resolve($name); return $file ? (filemtime($file) <= $time) : false; }
}
```

### ThemeExtension
```php
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
        return [ 'current_theme' => $theme->getCode(), 'theme' => $theme ];
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

    public function getThemeAsset(string $path): string { return $this->assetPackage->getUrl($path); }
    public function getThemeEntry(string $entryName): string { $code=$this->themeManager->getCurrentTheme()->getCode(); return "themes/{$code}/{$entryName}"; }
    public function hasFeature(string $f): bool { return $this->themeManager->getCurrentTheme()->hasFeature($f); }
    public function getParameter(string $k, mixed $d = null): mixed { return $this->themeManager->getCurrentTheme()->getParameter($k, $d); }
}
```

### ThemeAssetPackage
```php
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

    public function getUrl(string $path): string
    {
        foreach ($this->themeManager->getThemeChain() as $theme) {
            $assetPath = 'build/'.$theme->getPublicPath().'/'.$path;
            $fullPath = $this->projectDir.'/public/'.$assetPath;
            if (is_file($fullPath)) return $this->packages->getUrl($assetPath);
        }
        return $this->packages->getUrl($path);
    }

    public function exists(string $path): bool
    {
        foreach ($this->themeManager->getThemeChain() as $theme) {
            $fullPath = $this->projectDir.'/public/build/'.$theme->getPublicPath().'/'.$path;
            if (is_file($fullPath)) return true;
        }
        return false;
    }
}
```

---

## Webpack/Encore (расширение без ломки текущей сборки)

Текущие entries `admin` и `catalog` сохраняем. Добавляем темы как дополнительные entries.

1) Установить зависимость:
```bash
npm i -D js-yaml
```

2) Расширить `webpack.config.js`:
```js
// в начале файла
const fs = require('fs');
const yaml = require('js-yaml');
const themesDir = path.resolve(__dirname, 'themes');
const themeFilter = (process.env.THEME_FILTER || '').split(',').filter(Boolean);

let themes = [];
if (fs.existsSync(themesDir)) {
  themes = fs.readdirSync(themesDir)
    .filter(dir => fs.existsSync(path.join(themesDir, dir, 'theme.yaml')))
    .map(dir => {
      const cfg = yaml.load(fs.readFileSync(path.join(themesDir, dir, 'theme.yaml'), 'utf8')) || {};
      const entryPath = path.join(themesDir, dir, 'assets/entry.ts');
      return {
        code: cfg.code || dir,
        enabled: cfg.enabled !== false,
        path: path.join(themesDir, dir),
        entryPath: fs.existsSync(entryPath) ? entryPath : null,
      };
    })
    .filter(t => t.enabled && t.entryPath);
  if (themeFilter.length) {
    themes = themes.filter(t => themeFilter.includes(t.code) || themeFilter.includes(path.basename(t.path)));
  }
}

// после существующих alias/entries
if (themes.length) {
  // общий alias для shared
  Encore.addAliases({ '@theme-shared': path.resolve(__dirname, 'themes/_shared/assets') });
  // темы
  themes.forEach(t => {
    Encore.addEntry(`themes/${t.code}/main`, t.entryPath);
    const aliasKey = `@theme/${t.code}`;
    const aliasPath = path.join(t.path, 'assets');
    if (fs.existsSync(aliasPath)) {
      const extra = {}; extra[aliasKey] = aliasPath; Encore.addAliases(extra);
    }
  });
  // копирование статиков тем в public/build/themes/**
  Encore.copyFiles({
    from: './themes',
    to: 'themes/[path][name].[ext]',
    pattern: /\.(png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot)$/i,
    includeSubdirectories: true,
  });
}
```

3) Скрипты (дополнительно к вашим):
```json
{
  "scripts": {
    "theme:dev": "node scripts/theme-build.js --mode=dev",
    "theme:watch": "node scripts/theme-build.js --mode=watch",
    "theme:build": "node scripts/theme-build.js --mode=production",
    "theme:list": "node scripts/theme-list.js"
  }
}
```

`scripts/theme-build.js` (пример):
```js
#!/usr/bin/env node
const { execSync } = require('child_process');
const args = process.argv.slice(2);
const mode = args.find(a => a.startsWith('--mode='))?.split('=')[1] || 'dev';
const specific = args.find(a => a.startsWith('--theme='))?.split('=')[1] || '';
const cmd = mode === 'production' ? 'encore production' : (mode === 'watch' ? 'encore dev --watch' : 'encore dev');
execSync(cmd, { stdio: 'inherit', env: { ...process.env, THEME_FILTER: specific } });
```

---

## Интеграция в Twig (мягкий флаг)
В `templates/catalog/base.html.twig` подключаем ассеты условно (fallback на текущий `catalog`).
```twig
{% block styles %}
  {{ parent() }}
  {% if theme_enabled and function('theme_entry') is defined %}
    {{ encore_entry_link_tags(theme_entry('main')) }}
  {% else %}
    {{ encore_entry_link_tags('catalog') }}
  {% endif %}
{% endblock %}

{% block javascripts %}
  {{ parent() }}
  {% if theme_enabled and function('theme_entry') is defined %}
    {{ encore_entry_script_tags(theme_entry('main')) }}
  {% else %}
    {{ encore_entry_script_tags('catalog') }}
  {% endif %}
{% endblock %}
```

Shared компоненты можно подключать без namespace (ThemeLoader найдёт), либо явно через `@Shared`.

---

## Использование в коде

### Контроллер (опциональная базовая прослойка)
```php
abstract class ThemeAwareController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    public function __construct(protected \App\Theme\ThemeManager $themeManager) {}
    protected function renderTheme(string $view, array $parameters = [], ?\Symfony\Component\HttpFoundation\Response $response = null): \Symfony\Component\HttpFoundation\Response
    { return $this->render($view, array_merge($parameters, ['theme' => $this->themeManager->getCurrentTheme()]), $response); }
}
```

---

## Темы и bootstrap каталога

Чтобы не дублировать код, `themes/default/assets/entry.ts` импортирует текущий bootstrap витрины:
```ts
// themes/default/assets/entry.ts
import '@theme-shared/shared';
import './styles/main.scss';
import '@/catalog'; // реиспользуем assets/catalog/catalog.ts
```

`themes/_shared/assets/shared.ts` может импортировать общие стили:
```ts
import '@/styles.css';
// дополнительные shared‑ресурсы
```

---

## Migration Plan
- Фаза 1 (инфраструктура): директория `themes/` (+ `_shared`, `default`), сервисы/лоадер/extension, расширение webpack, `app_theme.enabled=false`.
- Фаза 2 (Twig‑интеграция): правка `templates/catalog/base.html.twig` с условным подключением ассетов; smoke‑тесты.
- Фаза 3 (перенос): вынести общие компоненты в `_shared/templates`, собрать `default`; включить `app_theme.enabled=true` в dev.
- Фаза 4 (опционально): добавить вторую тему `modern`, покрыть интеграционными тестами; включить в prod.

---

## Тестирование (кратко)
Unit: проверка реестра, цепочки и кеша `ThemeManager`.
Integration: рендер страницы каталога с `?_theme=default`/`modern`, наличие ссылок на `themes/<code>/main`.
Smoke: GET `/`, `/product/1`, переключение темы по `?_theme`.

---

## Acceptance
- Переключение темы работает без `cache:clear`.
- В prod включён кеш реестра и резолва шаблонов.
- Admin SPA не затронут (entries/алиасы неизменны).
- Fallback: тема → parent → `_shared` → обычные `templates/`.
- Fallback на текущий `catalog` entry при выключенном фиче‑флаге.

---

## Важные замечания
1) Не заменяем `twig.loader.native_filesystem`: регистрируем `ThemeLoader` отдельным тегом `twig.loader` (ChainLoader).
2) Храним состояние текущей темы в `RequestStack` (атрибут `_theme`).
3) Безопасность: санитайз кода темы `^[a-z0-9._-]+$`, проверка `enabled` и allowlist.
4) Пути ассетов: `public/build/themes/<code>/...` для статиков; JS/CSS — через `encore_entry_*`.
5) Обновление сборки тем не должно ломать текущие `admin`/`catalog` сборки.



