

@index.html.twig Ты — ИИ‑архитектор Cursor. Реализуй “page map” для указанной страницы, минимизируя инлайн‑правки в шаблоне.

Параметры запуска
- cart: короткий слаг страницы, .
- route: основной маршрут (пример: /cart).
- templatePath: путь к шаблону (Twig/Vue), пример: templates/catalog/cart/index.html.twig.
- extraTemplates?[]: дополнительные шаблоны этой страницы (если есть).
- controllers?[]: контроллеры страницы (если можно — autodetect из routes).
- apiControllers?[]: API контроллеры/эндпоинты.
- jsModules?[]: JS/Stimulus модули.
- services?[]: используемые сервисы.
- repositories?[]: репозитории.
- entities?[]: связанные Entity/ApiResource.
- invariants?[]: важные инварианты страницы (кратко).
- domAnchors?[]: ключевые якоря DOM, если уже известны (см. формат ниже).

Цель
- Вынести подробную “карту страницы” в `.cursor/rules/page_<pageId>_map.mdc` (alwaysApply: false).
- В шаблоне оставить один короткий Twig/HTML‑комментарий‑указатель на карту.
- Для DOM‑якорей опираться на Stimulus targets либо `data-testid`. Не использовать `data-ai-*`.
- Обновить индекс страниц `.cursor/rules/pages_index.mdc`.
- Уточнить критерии приёмки в `.cursor/rules/ai_context.mdc`: “при изменениях страниц/контроллеров/сервисов обновлять @page_*_map.mdc”.

Требования и ограничения
- Язык — русский, стиль — краткий.
- Не создавай pinned‑файлов; все новые .mdc — `alwaysApply: false`.
- Не меняй бизнес‑логику. Не добавляй inline `<script>`; соответствуй Stimulus‑first.
- Строгий формат фронт‑маттера: единый блок наверху файла:
  ---
  alwaysApply: false
  ---
- Сохрани существующие Stimulus атрибуты; если нужно добавить якоря — сначала попробуй targets, затем `data-testid`.
- Если нет Stimulus‑контроллера — не генерируй новый JS‑код; просто добавь `data-testid`.

Шаги выполнения
1) Определи pageId, route, шаблон(ы)
- Если pageId не задан — derives из имени файла шаблона: `cart/index.html.twig` → cart.
- Найди основной `{% block %}` для контента (Twig) или корневой тег (Vue/HTML).

2) Создай/обнови .cursor/rules/page_<pageId>_map.mdc
- Структура:
  ---
  alwaysApply: false
  ---
  # Page Map: <Page Title> (<route>)

  meta:
    id: <pageId>
    route: <route>
    templates:
      - <templatePath>
      # + extraTemplates если есть
    layout?: <extends/base>
  controllers:
    - src/Controller/...
  api:
    - src/Controller/Api/... (/api/..)
  entities:
    - App\Entity\... (ApiResource?: /api/...)
  services:
    - App\Service\...
  repositories:
    - App\Repository\...
  jsModules:
    - assets/... (Stimulus/Vue/ES)
  twigExtensions|filters|functions:
    - src/Twig/... (имя)
  dom:
    # Предпочтительно через Stimulus targets; если их нет — data-testid
    targets?:    # если есть data-controller и targets
      <controllerName>:
        - name: <targetName>
          selector: <css селектор или описание узла>
    testids?:    # если targets нет
      - name: <logicalName>
        selector: <css селектор>
  invariants:
    - ...
  links: [@services.mdc, doctrine_entities.mdc, @order_checkout_flow.mdc] # при необходимости

3) Вставь короткий указатель в шаблон
- Вставить сразу внутри основного блока шаблона одной строкой:
  - Twig: {# ai:page=<pageId> map=@page_<pageId>_map.mdc v=1 #}
  - Vue/HTML: <!-- ai:page=<pageId> map=@page_<pageId>_map.mdc v=1 -->
- Если указатель уже есть — не дублируй; обнови до актуального имени карты.

4) Нормализуй DOM‑якоря
- Если в шаблоне есть `data-controller` → добавь недостающие `data-*-target` для ключевых узлов (список, строка, qty‑input, remove‑button, totals: subtotal/shipping/total, выбор доставки).
- Если Stimulus не используется — добавь `data-testid` с короткими именами:
  - Примеры: cart-items, cart-item, qty-input, remove, row-total, delivery-root, delivery-method-code, subtotal, shipping, shipping-term, total.
- Не добавляй `data-ai-*`. Не ломай существующий CSS/JS.

5) Обнови индекс страниц `.cursor/rules/pages_index.mdc`
- Создай/обнови файл:
  ---
  alwaysApply: false
  ---
  # Pages Index

  - @page_<pageId>_map.mdc
  # Сохрани/дополни существующие записи, сортируй по id.

6) Уточни критерии приёмки в `.cursor/rules/ai_context.mdc`
- Убедись, что есть пункт: “Page maps обновлены при правках страниц/контроллеров/сервисов: @page_*_map.mdc”.
- Если такого пункта нет — добавь его в конец списка “Критерии приёмки изменений (для задач ИИ)”.
- Не изменяй другие пункты.

7) Проверки качества
- В .mdc один фронт‑маттер, корректные ссылки `@...`.
- В шаблоне нет сломанных блоков и inline `<script>`.
- DOM‑якоря присутствуют либо через Stimulus targets, либо через `data-testid`.
- Порог pinned не увеличен.

Выходной формат
- Сначала краткий reasoning (до 5 строк): что создано/обновлено.
- Затем единый unified git diff с изменениями:
  - Новый/изменённый `.cursor/rules/page_<pageId>_map.mdc`
  - Обновлённый шаблон(ы) с комментариями/якорями
  - Обновлённый `.cursor/rules/pages_index.mdc`
  - Изменённый `.cursor/rules/ai_context.mdc` (только добавление пункта про page maps, если его не было)
- Не включай изменения вне указанных файлов.

Подсказки по именованию anchors
- targets: ориентируйся на семантику контроллера: `<controller>-target="list|row|qtyInput|removeButton|rowTotal|deliveryRoot|methodCode|subtotal|shipping|shippingTerm|total"`.
- testids: kebab‑case, коротко и стабильно: cart-items, cart-item, qty-input, remove, row-total, delivery-root, delivery-method-code, subtotal, shipping, shipping-term, total.

Примеры вставок
- Twig указатель:
  {# ai:page=cart map=@page_cart_map.mdc v=1 #}
- data-testid:
  <tbody id="cart-items" data-testid="cart-items"> ... </tbody>
- Stimulus targets:
  <tbody data-controller="cart-items" data-cart-items-target="list"> ... </tbody>

Если данных недостаточно
- Автодополнить список controllers/api/services по очевидным именам/пути от шаблона и route.
- Пометь сомнительные места комментариями в карте: “TODO: уточнить …”.

Выполни задание и верни один git diff.