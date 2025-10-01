# Типы товаров: simple, variable, variable_no_prices

Документ описывает поведение типов товаров на уровне домена, API и админ‑интерфейса.

## Термины и сущности
- **Товар (Product)**: основной ресурс. Имеет поля `price`, `salePrice`, `quantity`, `type`, материализованное поле `effectivePrice` и т.п.
- **Вариация (ProductOptionValueAssignment)**: строка соответствия значения опции товару. Может содержать `price`, `salePrice`, `setPrice`, `quantity`, `sku`, доп. атрибуты.
- **Эффективная цена (effectivePrice)**: материализуется в `ProductLifecycleService` и используется для списков/сортировок.
- **optionsJson**: конфигурация доступных опций/значений (UI/конфиг), отдельно от фактических назначений вариаций.

API: для `optionAssignments` в запросах используются IRI идентификаторы (`option: "/v2/options/{id}", value: "/v2/option_values/{id}"`).

## Общие правила валидации
- **Допустимые типы**: `simple`, `variable`, `variable_no_prices`.
- **Вариативность**: для `variable` и `variable_no_prices` товар должен иметь хотя бы одну валидную вариацию; для `simple` — вариаций быть не должно.
- **Цены**: `salePrice <= price` (если оба заданы). Для простых товаров цена должна быть > 0.

## Тип simple
Товар без вариаций. Все цены — на уровне товара.

- **Вариации**: запрещены.
- **Цена товара**: обязательна; `salePrice` опциональна, но не больше `price`.
- **Количество**: на уровне товара (`quantity`).
- **Эффективная цена**: `effectivePrice = salePrice ?? price`.
- **UI/Админка**: вкладка «Опции» скрыта. Отправка `optionAssignments` не производится.

Пример создания (JSON):
```json
{
  "name": "Настольная лампа Luna",
  "slug": "luna",
  "type": "simple",
  "price": 4990,
  "salePrice": 4490,
  "status": true,
  "quantity": 10,
  "optionsJson": null
}
```

## Тип variable
Товар с вариациями и ценами на уровне вариаций.

- **Вариации**: обязательны (хотя бы одна пара `option`+`value`).
- **Цена товара**: на уровне товара обнуляется на сохранении; не используется.
- **Цена вариаций**: допускаются `price` и `salePrice` в `optionAssignments`.
- **Количество**: на уровне товара обнуляется; можно на уровне вариаций (`quantity` каждой строки).
- **setPrice**: может быть `true` максимум у одной вариации; при попытке выставить у нескольких — сохранится только первая, остальные будут сброшены.
- **Эффективная цена**: минимальное из `salePrice ?? price` среди всех вариаций.
- **UI/Админка**: вкладка «Опции» активна; ценовые поля вариаций доступны.

Пример создания (JSON):
```json
{
  "name": "Люстра Orion",
  "slug": "orion",
  "type": "variable",
  "status": true,
  "optionsJson": [
    { "option": "height", "multiple": false, "required": true, "priceMode": "absolute", "values": [], "sortOrder": 1 }
  ],
  "optionAssignments": [
    {
      "option": "/v2/options/12",
      "value": "/v2/option_values/101",
      "price": 11990,
      "salePrice": 10990,
      "setPrice": true,
      "quantity": 5,
      "sku": "ORION-101"
    },
    {
      "option": "/v2/options/12",
      "value": "/v2/option_values/102",
      "price": 12990,
      "salePrice": null,
      "setPrice": false,
      "quantity": 3,
      "sku": "ORION-102"
    }
  ]
}
```

## Тип variable_no_prices
Товар с вариациями, но без цен на уровне вариаций. Цена — только на уровне товара.

- **Вариации**: обязательны (хотя бы одна пара `option`+`value`).
- **Цена товара**: используется как основная; рекомендуется задавать `price`/`salePrice` у товара.
- **Цена вариаций**: в запросах игнорируется/обнуляется на сохранении (`price`, `salePrice`, `setPrice` у вариаций будут сброшены).
- **Количество**: на уровне товара обнуляется; допускается на уровне вариаций (`quantity` в строках назначений).
- **Эффективная цена**: `effectivePrice = salePrice ?? price` товара (цены вариаций не учитываются).
- **UI/Админка**: вкладка «Опции» активна; ценовые поля вариаций в форме отключены.

Пример создания (JSON):
```json
{
  "name": "Бра Vega",
  "slug": "vega",
  "type": "variable_no_prices",
  "price": 8990,
  "salePrice": 8490,
  "status": true,
  "optionsJson": [
    { "option": "color", "multiple": false, "required": true, "priceMode": "absolute", "values": [], "sortOrder": 1 }
  ],
  "optionAssignments": [
    { "option": "/v2/options/7", "value": "/v2/option_values/301", "quantity": 4, "sku": "VEGA-301" },
    { "option": "/v2/options/7", "value": "/v2/option_values/302", "quantity": 2, "sku": "VEGA-302" }
  ]
}
```

## Материализация effectivePrice
Материализация выполняется в `ProductLifecycleService` при сохранении:
- `simple`: `effectivePrice = salePrice ?? price` товара.
- `variable`: `effectivePrice = min(assignment.salePrice ?? assignment.price)`.
- `variable_no_prices`: `effectivePrice = salePrice ?? price` товара; цены вариаций не учитываются.

## Ошибки и типичные причины отклонений
- `Простой товар не должен иметь вариаций`: попытка добавить `optionAssignments` для `simple`.
- `Вариативный товар должен иметь хотя бы одну вариацию`: пустой/некорректный список `optionAssignments` при `variable`/`variable_no_prices`.
- `Sale price must be <= price`: нарушение соотношения цен.
- Для `variable`: при нескольких `setPrice=true` — лишние флаги будут сняты автоматически.
- Для `variable_no_prices`: любые `price/salePrice/setPrice` на уровне вариаций будут проигнорированы/сброшены.

## Поведение админки (концентрат)
- Тип отображается в списке товаров: «Простой товар», «Вариативный товар», «Вариативный без цен».
- Для `variable` и `variable_no_prices` появляется вкладка «Опции».
- Для `variable_no_prices` ценовые поля в строках вариаций отключены; на сервере они всё равно игнорируются.
- При сохранении не‑вариативных товаров `optionAssignments` не отправляются вовсе.

## Копирование и смена типа
При копировании товара можно указать `changeType` (`simple` | `variable` | `variable_no_prices`). Сервер отклонит иные значения. После смены типа соблюдайте правила валидации и материализации для нового типа.

---
Последняя проверка соответствия коду: 2025‑10‑01.
