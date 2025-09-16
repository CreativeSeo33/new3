# Рефакторинг блока доставки (delivery-selector)

Кратко:
- Вынесен inline-скрипт из `templates/_delivery_selector.html.twig` в FSD‑модуль `assets/catalog/src/features/delivery-selector/*`.
- Соблюдены правила каталога: TypeScript, разделение API/UI, уничтожение слушателей, отсутствие raw fetch в UI.

Изменения:
- Шаблон `templates/_delivery_selector.html.twig`:
  - Добавлен `data-module="delivery-selector"` на корневой контейнер.
  - Добавлены `data-testid` на ключевые элементы (`city-input`, `set-city`, `pvz-*`, `courier-*`, `ship-cost`).
  - Удалён `<script type="module">` с логикой.

- Регистрация модуля: `assets/catalog/src/app/registry.ts` добавлен ключ `delivery-selector`.

- Новый модуль:
  - `features/delivery-selector/api/index.ts`: типизированные функции API (`getDeliveryContext`, `fetchPvzPoints`, `selectCity`, `selectMethod`, `selectPvz`).
  - `features/delivery-selector/ui/component.ts`: класс `DeliverySelector` с логикой DOM/событий, загрузкой ПВЗ, переключением методов, валидацией адреса, обновлением стоимостей.
  - `features/delivery-selector/index.ts`: экспорт `init()` и реэкспорт API.

Интеграции:
- Автокомплит города использует существующую фичу `autocomplete`; внешний `#city-input` пробрасывается через `data-input-selector`. Глобальный fetcher FIAS остаётся совместимым как `(window).__autocompleteFetcher`.

Acceptance:
- Нет inline‑скриптов в Twig.
- Все HTTP‑вызовы из API‑слоя, через `@shared/api/http`.
- Типы строго определены, обработка ошибок добавлена.


