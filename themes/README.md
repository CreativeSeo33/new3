# Система тем каталога (Symfony + Webpack Encore)

Документ описывает, как устроены темы витрины каталога, как они подключаются на рантайме без cache:clear, как работает fallback, сборка ассетов и правила переопределения шаблонов.

---

## Обзор

- Темы живут в директории `themes/<code>` и полностью изолируют шаблоны и ассеты витрины.
- Переключение темы выполняется в рантайме (по субдомену/параметру запроса/сессии) без очистки кеша.
- При включённой теме Twig использует дополнительный загрузчик (ThemeLoader), который ищет шаблоны в теме, затем в её родителе (если есть), и только после этого — в обычных `templates/`.
- JS/CSS ассеты темы собираются в отдельные entry (`themes/<code>/main`) и подключаются в Twig через `theme_entry('main')`. При активной теме стандартный entry `catalog` НЕ подключается, чтобы избежать двойной инициализации.

---

## Файловая структура

```
themes/
  default/
    theme.yaml                   # описание темы (код, имя, флаги)
    templates/                   # Twig шаблоны темы (переопределения)
      layout.html.twig           # базовый layout темы (extends @App/base.html.twig)
      catalog/
        base.html.twig
        layouts/
          header.html.twig
          navbar.html.twig
          breadcrumbs.html.twig
          footer.html.twig
        category/
          index.html.twig
          _grid.html.twig
          show.html.twig
        product/
          show.html.twig
        search/
          index.html.twig
    assets/
      entry.ts                   # входная точка темы (импортирует стили и bootstrap каталога)
      styles/
        main.scss                # стили темы (Tailwind/кастом)
```

Примечания:
- Пространство `@App/...` в Twig указывает на файлы из стандартной директории `templates/`. Это удобно для явных include и исключает цикл ThemeLoader.
- Дополнительная тема подключается аналогично: `themes/modern/` и т.п.
- Общая `_shared` тема опциональна. В текущей конфигурации тема `default` не наследует `_shared` (parent=null).

---

## Компоненты системы

- ThemeManager — сканирует `themes/*`, валидирует конфиг `theme.yaml`, хранит контекст текущей темы (на уровне RequestStack), предоставляет цепочку тем для fallback.
- ThemeListener — выбирает тему на каждом запросе (приоритет 64) по стратегии: субдомен → `?_theme` → сессия → default.
- ThemeLoader — дополнительный Twig-загрузчик, который перед стандартным FilesystemLoader ищет шаблон в цепочке тем. Для неймспейсных путей (`@App/...`) не работает — их обрабатывает стандартный загрузчик.
- ThemeExtension — Twig глобали/функции: `current_theme`, `theme`, `theme_entry(name)`, `theme_asset(path)`, `theme_has_feature`, `theme_parameter(key)`.
- ThemeAssetPackage — возвращает URL ассетов темы с fallback по цепочке тем в `public/build/themes/<code>/**`.

---

## Конфигурация

Файл `config/packages/app_theme.yaml`:

```yaml
parameters:
    app_theme.enabled: true         # включение тем
    app_theme.default: 'default'    # код темы по умолчанию
    app_theme.cache_enabled: true   # кеш реестра тем
    app_theme.cache_ttl: 3600       # TTL кеша реестра тем
    app_theme.allowed_themes:
        - default                   # разрешённые темы
        # - modern
```

В dev-окружении кеш реестра можно отключить:

```yaml
when@dev:
    parameters:
        app_theme.cache_enabled: false
```

Twig (`config/packages/twig.yaml`):

```yaml
twig:
    # ...
    globals:
        theme_enabled: '%app_theme.enabled%'
    paths:
        '%kernel.project_dir%/templates': 'App'
```

Сервисы (`config/services.yaml`) уже зарегистрированы:
- `App\Theme\ThemeManager`
- `App\Theme\EventListener\ThemeListener`
- `App\Theme\Twig\ThemeLoader` (tag: `twig.loader`)
- `App\Theme\Twig\ThemeExtension` (tag: `twig.extension`)
- `App\Theme\Asset\ThemeAssetPackage`

---

## Как выбирается тема

1) Subdomain: если хост начинается с `code.example.com`, будет взят `code` как кандидат темы.
2) Query: `?_theme=<code>` переопределяет кандидат.
3) Session: если в сессии сохранён `theme`, он используется.
4) Allowlist + существование: кандидат проверяется на соответствие `allowed_themes` и наличию в реестре.
5) Иначе — `app_theme.default`.

Текущий код темы хранится в атрибуте запроса `_theme`. Получить из PHP: `ThemeManager->getCurrentTheme()`.

---

## Правила разрешения Twig-шаблонов

- Вызов `render('catalog/page.html.twig')` проходит через ThemeLoader и ищет по цепочке:
  1. `themes/<current>/templates/catalog/page.html.twig`
  2. `themes/<parent>/templates/...` (если указан parent)
  3. `templates/catalog/page.html.twig`
- Неймспейс `@App/...` всегда ссылается на обычные `templates/` и не обрабатывается ThemeLoader (удобно для явных include/extends без риска рекурсии).
- Избегайте рекурсивных include/extends:
  - В теме не делайте `{% include 'catalog/...' %}`, который указывает на тот же путь темы. Либо используйте копию шаблона, либо `@App/...`.
  - Базовый layout темы должен расширять `@App/base.html.twig`, а не простой `base.html.twig` (иначе ThemeLoader может зациклиться).

---

## Подключение ассетов темы

Включённая тема заменяет стандартные ассеты `catalog` на `theme_entry('main')`:

```twig
{# templates/catalog/base.html.twig #}
{% block styles %}
  {{ parent() }}
  {% if theme_enabled %}
    {{ encore_entry_link_tags(theme_entry('main')) }}
  {% else %}
    {{ encore_entry_link_tags('catalog') }}
  {% endif %}
{% endblock %}

{% block javascripts %}
  {{ parent() }}
  {% if theme_enabled %}
    {{ encore_entry_script_tags(theme_entry('main')) }}
  {% else %}
    {{ encore_entry_script_tags('catalog') }}
  {% endif %}
{% endblock %}
```

`themes/default/assets/entry.ts` — единая точка темы:

```ts
import './styles/main.scss';
// Реиспользуем bootstrap каталога, чтобы не дублировать логику
import '../../assets_placeholder'; // импортирует assets/catalog/catalog.ts
```

Важно: при активной теме стандартный `catalog` entry больше не подключается из Twig, чтобы исключить двойную инициализацию JS.

---

## Сборка Webpack Encore

- Конфиг сканирует `themes/*/theme.yaml` и для каждой включенной темы добавляет entry `themes/<code>/main`.
- Алиасы:
  - `@theme/<code>` → `themes/<code>/assets`
  - `@theme-shared` (необязателен)
- Статические файлы темы копируются в `public/build/themes/<code>/**`.

Скрипты:

```bash
npm run theme:list     # список тем (по theme.yaml)
npm run theme:dev      # сборка (dev)
npm run theme:watch    # watch
npm run theme:build    # production
```

Фильтрация по теме:

```bash
THEME_FILTER=default npm run theme:dev
```

---

## TailwindCSS

- Рекомендуется добавить шаблоны темы в `content` Tailwind, чтобы JIT видел классы внутри `themes/default/templates/**`.
- Если не хотите изменять глобальный `tailwind.config.js`, держите атомарные классы в уже учитываемых директориях или используйте `safelist`.

Пример content (рекомендация):

```js
content: [
  './templates/**/*.html.twig',
  './assets/**/*.ts',
  './themes/**/*.html.twig',
]
```

---

## Создание новой темы

1) Создайте директорию `themes/<code>/`.
2) Добавьте `theme.yaml`:

```yaml
name: 'My Theme'
code: 'my-theme'
enabled: true
parent: null
```

3) Добавьте `assets/entry.ts` и `assets/styles/main.scss`.
4) Скопируйте нужные шаблоны в `templates/` темы, сохраняя относительные пути.
5) Соберите ассеты: `npm run theme:dev`.
6) Включите тему на странице: `?_theme=my-theme`.

Чек‑лист темы:
- [ ] `theme.yaml` корректен и включён в allowlist
- [ ] Есть `assets/entry.ts` + `styles/main.scss`
- [ ] Нет рекурсивных include/extends (используйте `@App/...`)
- [ ] Страница открывается без `cache:clear`

---

## Миграция каталога в тему (использованный подход)

- Включили флаг `app_theme.enabled=true` и сохранили fallback на `catalog` при выключенной теме.
- Перенесли шаблоны каталога в `themes/default/templates/catalog/**`.
- В теме `layout.html.twig` расширили `@App/base.html.twig`.
- Включили ассеты темы через `theme_entry('main')`; стандартный `catalog` entry отключён при активной теме.
- `entry.ts` темы импортирует `assets/catalog/catalog.ts` через локальный `assets_placeholder.ts`.
- Устранили рекурсии: заменили include на `@App/...` там, где был риск самовключения.

---

## Трюблшутинг

- «Страница виснет / память исчерпана (ProfilerExtension)»: почти всегда рекурсия шаблонов. Проверьте, что тема не `{% include '...' %}` сама себя. Используйте `@App/...` или локальные копии шаблонов.
- «Двойная инициализация JS»: убедитесь, что при активной теме Twig не подключает `catalog` entry, а только `theme_entry('main')`.
- «Ассеты темы не грузятся»: проверьте, что есть entry `themes/<code>/main` в `public/build/entrypoints.json`, и что `theme_entry('main')` совпадает с ним.
- «Шаблон не найден»: проверьте путь внутри темы и порядок fallback. Для явных include используйте `@App/...`.
- «Tailwind классы не применяются»: добавьте `themes/**/*.html.twig` в `tailwind.config.js` → `content` или используйте `safelist`.

---

## FAQ

- Можно ли наследовать темы? — Да, через `parent` в `theme.yaml`. В текущем проекте `default` работает без родителя.
- Можно ли держать админку в темах? — Нет, темы относятся только к витрине каталога. Admin SPA не затрагивается.
- Как переключить тему для предпросмотра? — Добавьте к URL `?_theme=<code>`; разрешение происходит без cache:clear.

---

## Полезные команды

```bash
php bin/console cache:clear        # на случай обновлений конфигурации
npm run theme:list                 # список тем
npm run theme:dev                  # сборка тем в dev
npm run theme:build                # сборка тем для prod
```

---

## Контакты и поддержка

- За реализацию отвечают: ThemeManager / ThemeLoader / ThemeExtension в `src/Theme/**`.
- При проблемах с шаблонами: проверьте include/extends, используйте `@App/...` для исключения циклов.
- При проблемах со сборкой: смотрите `webpack.config.js` и `public/build/entrypoints.json`.

