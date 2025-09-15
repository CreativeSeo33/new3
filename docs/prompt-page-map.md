
Ты — ИИ‑архитектор Cursor. Сформируй “page map” для указанной страницы по строгим правилам, минимизируя инлайн‑правки в шаблонах.

Параметры запуска
- pageId: обязательный короткий слаг (kebab-case).
- route: обязательный маршрут, напр. /cart.
- templatePath: обязательный путь к главному шаблону, напр. templates/catalog/cart/index.html.twig.
- extraTemplates?[]: доп. шаблоны этой страницы.
- controllers?[]: контроллеры страницы.
- apiControllers?[]: API контроллеры/эндпоинты.
- jsModules?[]: JS/Stimulus модули.
- services?[]: сервисы DI.
- httpServices?[]: классы из `src/Http/*`.
- repositories?[]: репозитории.
- entities?[]: Entity/ApiResource (указывай `uriTemplate`, если известно).
- twigFunctions?[]: Twig‑функции с путями расширений.
- twigFilters?[]: Twig‑фильтры (можно с путями расширений).
- invariants?[]: инварианты страницы.
- domAnchors?[]: ключевые якоря DOM (если заранее известны).
- options:
  - includeProvidedOnly: true|false (по умолчанию true) — включать минимум все переданные элементы; не выдумывать лишнего.
  - verifyExistence: true|false (по умолчанию true) — проверять существование путей/файлов; отсутствующие помечать TODO.
  - codeScan: true|false (по умолчанию false) — при true просканировать код (конструкторы/инъекции/use) и предложить дополнения; помечать как inferred.
  - scanDepth: число (по умолчанию 2) — глубина для codeScan.
  - anchorsMode: auto|stimulus|testid (по умолчанию auto). В auto: сначала попытка через Stimulus targets, иначе data-testid.
  - addPagePointer: true|false (по умолчанию true) — добавить комментарий‑указатель в шаблон.
  - updateIndex: true|false (по умолчанию true) — создать/обновить `/.cursor/rules/pages_index.mdc`.
  - updateAiContext: true|false (по умолчанию false) — добавить в `ai_context.mdc` ссылку на индекс и критерий, если их нет.
  - outputFormat: files|summary (по умолчанию files).

Цели
- Создать/обновить `/.cursor/rules/page_<pageId>_map.mdc` с `alwaysApply: false`.
- Вставить в шаблон одну строку‑указатель: для Twig `{# ai:page=<pageId> map=@page_<pageId>_map.mdc v=1 #}`; для HTML/Vue — аналогичный HTML‑комментарий.
- Нормализовать DOM‑якоря: предпочтительно Stimulus targets; иначе `data-testid`. Не использовать `data-ai-*`.
- Обновить `/.cursor/rules/pages_index.mdc` (если `options.updateIndex = true`).
- Опционально уточнить `ai_context.mdc` (если `options.updateAiContext = true`): добавить “Индекс карт страниц: `@pages_index.mdc`” и единый критерий про обновление page maps.

Жесткие требования
- Язык — русский; стиль — краткий.
- Не менять бизнес‑логику. Не добавлять inline `<script>`. Соблюдать Stimulus‑first.
- Не создавать pinned‑файлов. Все новые `.mdc` — только с единым фронт‑маттером:
  ---
  alwaysApply: false
  ---
- Включай в карту абсолютно все элементы из переданных списков `controllers`, `apiControllers`, `services`, `httpServices`, `repositories`, `entities`, `jsModules`, `twigFunctions`, `twigFilters` — без пропусков.
- Если `options.verifyExistence = true`:
  - Проверяй существование каждого пути. Если файл/класс не найден — оставь пункт и добавь в конце строки `# TODO: not found, needs verify`.
  - Не подменяй неймспейсы и имена; не догадывайся.
- Если `options.includeProvidedOnly = true`:
  - Ничего не добавляй сверх переданного. Исключение — когда `options.codeScan = true`: найденные зависимости выноси в секцию `inferred.*`.
- Twig классификация:
  - Функции — в `twig.functions` с путём расширения и именем в скобках.
  - Фильтры — в `twig.filters` (можно указать расширение).
  - Пример: `format_price` → функция из `src/Twig/PriceExtension.php`; `imagine_filter` → фильтр (LiipImagine).
- `meta.layout` — полный путь к базовому шаблону, напр. `templates/catalog/base.html.twig`.
- `links` — валидные `@...` ссылки; начинай с `@`.

DOM‑якоря
- Если есть `data-controller` — добавь недостающие `data-*-target` на ключевые узлы.
- Если Stimulus не используется — добавь `data-testid` (kebab‑case).
- Базовый набор имён: list, row, qtyInput, removeButton, rowTotal, deliveryRoot, methodCode, subtotal, shipping, shippingTerm, total.
- Никаких `data-ai-*`.

Структура файла `.cursor/rules/page_<pageId>_map.mdc`
---
alwaysApply: false
---
# Page Map: <Человекочитаемое имя> (<route>)

meta:
  id: <pageId>
  route: <route>
  templates:
    - <templatePath>
    # + extraTemplates (если есть)
  layout: <templates/.../base.html.twig>

controllers:
  - src/Controller/...           # из controllers[]

api:
  - src/Controller/Api/... (/api/...)   # из apiControllers[]

entities:
  - App\Entity\... (ApiResource[: <uriTemplate>])  # из entities[]

services:
  - App\Service\...              # из services[]

httpServices?:                  # если переданы httpServices[]
  - src/Http/...

repositories:
  - App\Repository\...           # из repositories[]

jsModules:
  - assets/...                   # из jsModules[]

twig:
  functions:
    - src/Twig/... (<functionName>)  # из twigFunctions[]
  filters:
    - <filterName> [<extensionPath?>]  # из twigFilters[]

dom:
  targets?:                      # если anchorsMode = stimulus и есть data-controller
    <controllerName>:
      - name: list|row|qtyInput|removeButton|rowTotal|deliveryRoot|methodCode|subtotal|shipping|shippingTerm|total
        selector: <CSS селектор или пояснение>
  testids?:                      # если anchorsMode = testid или stimulus недоступен
    - name: cart-items|cart-item|qty-input|remove|row-total|delivery-root|delivery-method-code|subtotal|shipping|shipping-term|total
      selector: <CSS селектор>

inferred?:                       # добавлять только при options.codeScan = true
  services?: [ ... ]
  repositories?: [ ... ]
  controllers?: [ ... ]

invariants:
  - ...                          # из invariants[]

links: [@services.mdc, @doctrine_entities.mdc, @order_checkout_flow.mdc]  # по необходимости

Правки в шаблоне
- Вставь указатель (если `options.addPagePointer = true`):
  - Twig: `{# ai:page=<pageId> map=@page_<pageId>_map.mdc v=1 #}`
  - HTML/Vue: `<!-- ai:page=<pageId> map=@page_<pageId>_map.mdc v=1 -->`
- Приведи DOM‑якоря к `anchorsMode`: добавь targets или `data-testid`.
- Удали пустые/лишние inline‑скрипты, не меняя поведение.

Обновление индекса и контекста
- `/.cursor/rules/pages_index.mdc` (если `options.updateIndex = true`):
  ---
  alwaysApply: false
  ---
  # Pages Index

  - @page_<pageId>_map.mdc
  # Список отсортирован по id; каждая запись на новой строке.
- `/.cursor/rules/ai_context.mdc` (если `options.updateAiContext = true`):
  - Добавь в “Связанные правила Cursor”: `@pages_index.mdc` (если отсутствует).
  - В “Критерии приёмки”: “При изменениях маршрутов/контроллеров/сервисов/шаблонов страницы обновлён соответствующий `@page_<id>_map.mdc` и (при наличии) `@pages_index.mdc`.”

Проверки качества
- В `.mdc` один фронт‑маттер; нет повторных `---`.
- RU‑язык; корректные `@...` ссылки; `meta.layout` — полный путь.
- В шаблоне нет inline `<script>`.
- Все переданные элементы включены; отсутствующие файлы помечены `# TODO: not found, needs verify`.
- pinned‑набор не увеличен (`alwaysApply: false` для всех новых `.mdc`).

Формат вывода
- options.outputFormat = files (по умолчанию):
  - Верни полный контент каждого нового/изменённого файла в отдельных блоках:
    BEGIN FILE: <путь/имя файла>
    <полное новое содержимое файла>
    END FILE
  - Минимальный набор файлов:
    - `/.cursor/rules/page_<pageId>_map.mdc`
    - Изменённый шаблон по `templatePath` (если добавлялся указатель/якоря)
    - `/.cursor/rules/pages_index.mdc` (если обновлялся)
    - `/.cursor/rules/ai_context.mdc` (если обновлялся)
- options.outputFormat = summary:
  - Кратко перечисли, какие файлы надо создать/обновить и какие секции добавлены/изменены, без кода.

Примеры вставок
- Twig указатель:
  {# ai:page=cart map=@page_cart_map.mdc v=1 #}
- data-testid:
  <tbody id="cart-items" data-testid="cart-items"> ... </tbody>
- Stimulus targets:
  <tbody data-controller="cart-items" data-cart-items-target="list"> ... </tbody>