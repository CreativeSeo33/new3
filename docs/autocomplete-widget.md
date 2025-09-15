# Виджет автокомплита (FSD)

Код: `assets/catalog/src/features/autocomplete/*`

Использование в HTML:

```html
<!-- Вариант с существующим input -->
<div data-module="autocomplete" data-placeholder="Ваш город" data-input-selector="#city-input"></div>
<input id="city-input" class="w-full border rounded px-3 py-2" placeholder="Ваш город">

<!-- Вариант без input: будет создан внутренний input -->
<div data-module="autocomplete" data-placeholder="Поиск..."></div>
```

По умолчанию подключается fetcher FIAS городов (`level=3`, `shortname=г.`). Для переопределения используйте JS-инициализацию модуля и передайте `fetcher`.

API компонента:
- Событие `autocomplete:selected` с detail `{ id?, label, value, raw }`
- dataset: `data-min-chars`, `data-debounce`, `data-max-items`, `data-placeholder`, `data-input-selector`

Интеграция в `_delivery_selector.html.twig` выполнена; inline-логика автокомплита удалена.


