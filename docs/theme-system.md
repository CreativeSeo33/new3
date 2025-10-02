# Система тем каталога — включение/отключение и полное удаление

Обновлено: 2025-10-02

Документ описывает, как включать/отключать систему тем на витрине каталога без удаления кода, а также содержит полный перечень связанных файлов и чек‑лист для полного удаления системы из проекта.

---

## Быстрое включение/отключение

- Включить темы:
  1) Установите переменную окружения:
     
     ```bash
     APP_THEME_ENABLED=1
     ```
  2) Очистите кеш Symfony:
     
     ```bash
     php bin/console cache:clear
     ```
  3) Соберите ассеты тем (если менялись ассеты темы):
     
     ```bash
     npm run theme:dev   # dev-сборка
     # или
     npm run theme:build # prod-сборка
     ```

- Отключить темы:
  1) Установите переменную окружения:
     
     ```bash
     APP_THEME_ENABLED=0
     ```
  2) Очистите кеш:
     
     ```bash
     php bin/console cache:clear
     ```

Поведение при выключенных темах:
- Twig глобаль `theme_enabled` = false, в `templates/catalog/base.html.twig` подключаются стандартные entry `catalog`.
- `ThemeLoader`, `ThemeListener`, `ThemeAssetPackage` выполняют ранний выход и не влияют на резолв шаблонов/ассетов.

Фрагмент переключения ассетов (уже присутствует в проекте):

```twig
{# templates/catalog/base.html.twig #}
{% if theme_enabled %}
  {{ encore_entry_link_tags(theme_entry('main')) }}
{% else %}
  {{ encore_entry_link_tags('catalog') }}
{% endif %}

{% if theme_enabled %}
  {{ encore_entry_script_tags(theme_entry('main')) }}
{% else %}
  {{ encore_entry_script_tags('catalog') }}
{% endif %}
```

---

## Как это работает под капотом

- Конфиг `app_theme.enabled` берётся из env: `%env(bool:APP_THEME_ENABLED)%`.
- При `APP_THEME_ENABLED=1`:
  - `ThemeListener` выбирает активную тему на запросе (субдомен → `?_theme` → сессия → default/allowlist).
  - `ThemeLoader` ищет шаблоны по цепочке тем и подменяет обычные пути Twig.
  - `ThemeAssetPackage` ищет ассеты темы в `public/build/themes/<code>/**` с fallback.
- При `APP_THEME_ENABLED=0` указанные сервисы ничего не делают (гарды на `$enabled=false`).

---

## Точки интеграции и конфигурации

- `config/packages/app_theme.yaml`
  
  ```yaml
  parameters:
      app_theme.enabled: '%env(bool:APP_THEME_ENABLED)%'
      app_theme.default: 'default'
      app_theme.cache_enabled: true
      app_theme.cache_ttl: 3600
      app_theme.allowed_themes:
          - _shared
          - default
          - modern

  when@dev:
      parameters:
          app_theme.cache_enabled: false
  ```

- `config/packages/twig.yaml`
  
  ```yaml
  twig:
      globals:
          theme_enabled: '%app_theme.enabled%'
      paths:
          '%kernel.project_dir%/templates': 'App'
  ```

- `config/services.yaml` (фрагменты)
  
  ```yaml
  parameters:
      env(APP_THEME_ENABLED): '0'  # default
  
  services:
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
              $enabled: '%app_theme.enabled%'
          tags:
              - { name: kernel.event_listener, event: kernel.request, priority: 64 }

      App\Theme\Twig\ThemeLoader:
          arguments:
              $themes: '@App\Theme\ThemeManager'
              $enabled: '%app_theme.enabled%'
          tags: ['twig.loader']

      App\Theme\Twig\ThemeExtension:
          tags: ['twig.extension']

      App\Theme\Asset\ThemeAssetPackage:
          arguments:
              $projectDir: '%kernel.project_dir%'
              $enabled: '%app_theme.enabled%'
          public: true
  ```

- `webpack.config.js` (тематические entries, алиасы и копирование статиков)
  
  Ключевые элементы:
  - Создание entries `themes/${code}/main` по `themes/*/theme.yaml`
  - Алиасы `@theme/<code>` и `@theme-shared`
  - Копирование статических файлов тем в `public/build/themes/**`

---

## Полный список файлов системы тем

PHP (ядро тем):
- `src/Theme/ThemeDefinition.php`
- `src/Theme/ThemeManager.php`
- `src/Theme/EventListener/ThemeListener.php`
- `src/Theme/Twig/ThemeLoader.php`
- `src/Theme/Twig/ThemeExtension.php`
- `src/Theme/Asset/ThemeAssetPackage.php`

Конфигурация:
- `config/packages/app_theme.yaml`
- `config/packages/twig.yaml` (глобаль `theme_enabled`)
- `config/services.yaml` (секция “Theme System” и `env(APP_THEME_ENABLED)`)

Webpack и ассеты:
- `webpack.config.js` (блок тематической сборки)
- `themes/assets_placeholder.ts`
- `themes/_shared/theme.yaml`
- `themes/_shared/assets/shared.ts` (если используется)
- `themes/default/theme.yaml`
- `themes/default/assets/entry.ts`
- `themes/default/assets/styles/main.scss`

Шаблоны тем:
- `themes/default/templates/layout.html.twig`
- `themes/default/templates/catalog/base.html.twig`
- `themes/default/templates/catalog/layouts/header.html.twig`
- `themes/default/templates/catalog/layouts/navbar.html.twig`
- `themes/default/templates/catalog/layouts/breadcrumbs.html.twig`
- `themes/default/templates/catalog/layouts/footer.html.twig`
- `themes/default/templates/catalog/category/index.html.twig`
- `themes/default/templates/catalog/category/_grid.html.twig`
- `themes/default/templates/catalog/category/show.html.twig`
- `themes/default/templates/catalog/product/show.html.twig`
- `themes/default/templates/catalog/search/index.html.twig`
- (аналогичные файлы в других темах, если добавите новые)

Документация:
- `themes/README.md`

Точки использования в Twig:
- `templates/catalog/base.html.twig` — переключение ассетов/entry по `theme_enabled` и `theme_entry('main')`

---

## Полное удаление системы тем — чек‑лист

1) Удалить PHP‑файлы:
- удалить директорию `src/Theme/**`

2) Обновить конфиги:
- в `config/services.yaml` удалить секцию “Theme System” и при желании строку `env(APP_THEME_ENABLED): '0'`
- в `config/packages/app_theme.yaml` — удалить файл
- в `config/packages/twig.yaml` — удалить глобаль `theme_enabled` (и, при необходимости, алиас путей `Shared`)

3) Обновить Twig:
- в `templates/catalog/base.html.twig` заменить ветвление на прямые подключения каталога:
  
  ```twig
  {{ encore_entry_link_tags('catalog') }}
  {{ encore_entry_script_tags('catalog') }}
  ```

- убедиться, что не используется `theme_entry`, `theme_asset`, `theme_has_feature`, `theme_parameter` в других шаблонах

4) Webpack:
- в `webpack.config.js` удалить блок тематической сборки, алиасы `@theme/*` и копирование статиков тем

5) Каталог тем:
- удалить директорию `themes/**` целиком (`_shared`, `default`, `assets_placeholder.ts`, дополнительные темы)

6) Очистка и сборка:
- `php bin/console cache:clear`
- пересобрать ассеты: `npm run dev`
- очистить `public/build/themes/**` при необходимости

Поиск для финальной зачистки (перед удалением убедиться, что ничего не осталось):

```bash
rg "theme_entry\(|theme_asset\(|theme_has_feature\(|theme_parameter\(" templates themes src
```

---

## Примечания

- При выключенных темах (`APP_THEME_ENABLED=0`) система тем полностью безвредна: рендер идёт через обычные шаблоны `templates/**`, а ассеты — через entry `catalog`.
- Включение/отключение выполняется без `cache:clear` на уровне Twig‑резолвинга шаблонов только частично; для консистентности рекомендуется всегда очищать кеш после изменения флага окружения.
- При разработке темы избегайте рекурсий в Twig и используйте `@App/...` для явных include/extends внутри темы (см. `themes/README.md`).
