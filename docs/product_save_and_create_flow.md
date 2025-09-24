# Сохранение и добавление товара (Product): полный разбор

Дата: 2025-09-24

## Обзор

В проекте сосуществуют два API-слоя для работы с товарами:
- DTO-ресурс v2: `App\ApiResource\ProductResource` с провайдером/процессором состояния (основной в админке). Маршруты: `/api/v2/products`.
- Исторический ресурс на сущности: `App\Entity\Product` с `#[ApiResource]` (коллекция/элементы, валидации через группы `product:create|update`).

Ниже описан актуальный поток создания/сохранения через v2 (`ProductResource`), валидации и поля, а также используемое кеширование: HTTP (ETag) и приложенческий (`CacheInterface`).

---

## Ключевые артефакты

- Сущность: `App\Entity\Product`
  - Типы: `simple` и `variable`
  - Уникальные поля: `code` (ULID), `slug`
  - Встроенные объекты: `ProductPrice` (price, salePrice, currency), `ProductTimestamps` (createdAt/updatedAt)
  - Материализованное поле: `effective_price` (индекс), заполняется в жизненном цикле
  - Связи: `manufacturerRef`, `image[]`, `category[]` (через `ProductToCategory`), `optionAssignments[]`, `attributeAssignments[]`
  - Валидации (неполный список):
    - `name`: NotBlank (create), Length ≤ 255
    - `slug`: формат `/^[a-z0-9]+(?:-[a-z0-9]+)*$/i`, уникальность
    - `salePrice <= price` (если оба заданы)
    - Для `simple`: `price > 0` если задана цена; запрет вариаций
    - Для `variable`: обязательны вариации (минимум одна)

- DTO-ресурс: `App\ApiResource\ProductResource` (routePrefix: `/v2`)
  - Операции: `Get`, `GetCollection`, `Post`, `Patch (read:false)`, `Delete (output:false)`
  - Построение ответа выполняет `ProductStateProvider`
  - Запись (create/update/delete) выполняет `ProductStateProcessor`

- Жизненный цикл: `App\Doctrine\Listener\ProductEntityListener` + `App\Service\ProductLifecycleService`
  - prePersist: генерирует `code`, `createdAt`, гарантирует `slug`, материализует `effectivePrice`
  - preUpdate: обновляет `updatedAt`, гарантирует `slug`, материализует `effectivePrice`
  - Для `variable`: очищает базовые цены у товара, считает `effectivePrice` как минимум из вариаций
  - Для `simple`: `effectivePrice = salePrice ?? price`

---

## Поток создания (POST /api/v2/products)

1) Клиент отправляет JSON на `/api/v2/products`.
2) `ProductStateProcessor` создаёт/находит сущность и мапит только переданные поля (для `POST` — все доступные, для `PATCH` — только ключи из тела).
3) Особые случаи маппинга:
   - Цена: замена embeddable через `setPricingValues(price, salePrice)` для корректного change-tracking + дублирующие сеттеры.
   - `optionAssignments`: при передаче очищаются и создаются заново по IRI `option`/`value` с полями `height`, `bulbsCount`, `sku`, `originalSku`, `price`, `setPrice`, `salePrice`, `sortOrder`, `quantity`, `lightingArea`, `attributes`.
   - В конце приводится инвариант: допускается не более одного `setPrice === true` среди всех вариаций — остальные будут сброшены в `false`.
   - `manufacturerId`: переводится в `manufacturerRef`.
4) Doctrine-слушатель устанавливает `code/createdAt/updatedAt`, гарантирует `slug`, материализует `effective_price`.
5) `flush()`, затем возврат «свежего» DTO через `ProductStateProvider`.

Пример запроса (простой товар):

```json
{
  "name": "Настольная лампа Luna",
  "slug": "luna-table-lamp",
  "type": "simple",
  "price": 8900,
  "salePrice": 7900,
  "status": true,
  "quantity": 15,
  "description": "Компактная лампа для рабочего стола",
  "metaTitle": "Лампа Luna",
  "metaDescription": "Настольная лампа Luna — компактная и яркая",
  "h1": "Лампа Luna",
  "sortOrder": 10,
  "manufacturerId": 3,
  "optionsJson": {"color": ["white", "black"]}
}
```

Пример запроса (вариативный товар):

```json
{
  "name": "Люстра Orbis",
  "slug": "orbis-chandelier",
  "type": "variable",
  "status": true,
  "description": "Серия люстр Orbis",
  "optionAssignments": [
    {
      "option": "/api/options/10",
      "value": "/api/option_values/45",
      "price": 19900,
      "salePrice": 17900,
      "sku": "ORB-5W-BLK",
      "bulbsCount": 5,
      "lightingArea": 20,
      "setPrice": true,
      "sortOrder": 1,
      "quantity": 7,
      "attributes": {"diameter": 45}
    },
    {
      "option": "/api/options/10",
      "value": "/api/option_values/46",
      "price": 23900,
      "sku": "ORB-7W-BLK",
      "bulbsCount": 7,
      "lightingArea": 28,
      "sortOrder": 2,
      "quantity": 3
    }
  ]
}
```

Ответ (сокращённо):

```json
{
  "id": 124,
  "code": "018f7b7d-3cd5-7a3b-9e9d-4a812a25fd1a",
  "name": "Люстра Orbis",
  "slug": "orbis-chandelier",
  "type": "variable",
  "effectivePrice": 17900,
  "status": true,
  "optionAssignments": [
    {
      "option": "/api/options/10",
      "value": "/api/option_values/45",
      "price": 19900,
      "salePrice": 17900,
      "setPrice": true,
      "quantity": 7
    }
  ],
  "createdAt": "2025-09-24T10:15:00+00:00"
}
```

---

## Поток обновления (PATCH /api/v2/products/{id})

- Формат: `application/merge-patch+json` (см. `config/packages/api_platform.yaml`).
- Обрабатываются только ключи, реально присутствующие в теле запроса (partial-update). Пример — изменение цены:

```http
PATCH /api/v2/products/124
Content-Type: application/merge-patch+json

{"price": 20900}
```

Если в `PATCH` передан `optionAssignments`, существующие назначения будут удалены и пересозданы из переданного массива.

---

## Удаление

`DELETE /api/v2/products/{id}` обрабатывается в `ProductStateProcessor`. Перед удалением выполняется проверка в корзинах: если товар используется, выбрасывается `409 Conflict` с сообщением вида:

```json
{"message": "Невозможно удалить товар \"Luna\" - он используется в корзине покупателей"}
```

Выдача для `DELETE` отключена (`output: false`).

---

## Валидации и инварианты

- Уникальность: `code`, `slug`.
- Формат `slug`, автогенерация из `name`, если не задан.
- `salePrice <= price` (если оба заданы).
- `simple` запрещает вариации; `variable` требует хотя бы одну.
- Ровно один `setPrice=true` в `optionAssignments` (излишние приводятся к `false`).
- Для простых товаров `price > 0` (если задана цена).

Ошибки возвращаются как `400 Bad Request` с деталями нарушений.

---

## Поля и маппинг DTO ↔ Entity (основное)

- Простые поля: `name`, `slug`, `status`, `quantity`, `sortOrder`, `type`, `description` — прямое соответствие сеттерам сущности.
- Цена: через `setPricingValues(price, salePrice)` и дублирующие сеттеры (`setPrice`, `setSalePrice`) для надёжного change-tracking Doctrine.
- SEO: `metaTitle`, `metaDescription`, `metaKeywords`, `h1` — проксируются в связанный `ProductSeo`.
- Производитель: `manufacturerId` → `manufacturerRef`.
- Вариации: `optionAssignments[]` с IRI на `Option`/`OptionValue` + числовые/строковые поля.
- `optionsJson`/`attributeJson` сохраняются как JSON (при необходимости — отдельная валидация на уровне API).

Категории и изображения в `ProductStateProcessor` не мапятся — для них предусмотрены отдельные эндпоинты (`ProductToCategory`, `ProductImage`).

---

## Кеширование

### HTTP-кеш (ETag) для админ-форм

- Эндпоинты:
  - `GET /api/admin/products/{id}/form` — ETag учитывает состояние товара, связи и версии словарей; при `If-None-Match` возвращает `304 Not Modified`.
  - `GET /api/admin/products/form` — ETag основан на версиях словарей (категории, опции) для пустой формы.
- По умолчанию глобально заданы заголовки `vary: [Content-Type, Authorization, Origin]` в `api_platform.defaults.cache_headers`.
- Для дерева категорий применяется `Cache-Control: public, max-age=300` и слабый ETag вида `W/"v{hash}"`.

Практика на клиенте (админка): кэшировать bootstrap-данные формы по ETag и выполнять условные GET-запросы.

### Приложенческий кэш (Symfony CacheInterface)

Используется для производных данных вариантов/остатков:

- `InventoryService`
  - Ключ `inventory_assignments_{md5(ids)}`, TTL 300 сек — кэширует загрузку назначений опций по их id.
  - Инвалидатор: `invalidateCache(array $optionAssignmentIds)` удаляет соответствующий ключ.

- `ProductVariantService`
  - Ключ `variant_by_sku_{md5(sku)}`, TTL 3600 сек — кэш поиска варианта по SKU.
  - Ключ `variant_assignments_{md5(ids)}`, TTL 300 сек — кэш загрузки назначений.
  - Ключ `product_combinations_{productId}`, TTL 600 сек — кэш всех доступных комбинаций для товара.
  - Инвалидаторы: `invalidateSkuCache(sku)`, `invalidateCombinationCache(ids)`, `invalidateProductCache(product)`.

Примечание: На момент написания Doctrine result cache / second-level cache не используется (поиском сигнатур не обнаружено). Инвалидаторы предоставлены сервисами и должны вызываться в местах, где меняются соответствующие данные (вариации, SKU, остатки), чтобы кэш был консистентным.

---

## Примеры

### 1) Создание простого товара

```http
POST /api/v2/products
Content-Type: application/json

{
  "name": "Лампа Desk Mini",
  "type": "simple",
  "price": 3900,
  "status": true,
  "quantity": 25
}
```

Успех: `201 Created` с телом DTO. Ошибки валидации: `400 Bad Request`.

### 2) Частичное обновление цены

```http
PATCH /api/v2/products/124
Content-Type: application/merge-patch+json

{"salePrice": 16900}
```

### 3) Обновление вариаций (пересоздание набора)

```http
PATCH /api/v2/products/124
Content-Type: application/merge-patch+json

{
  "optionAssignments": [
    {"option": "/api/options/10", "value": "/api/option_values/45", "price": 19900, "setPrice": true},
    {"option": "/api/options/10", "value": "/api/option_values/46", "price": 23900}
  ]
}
```

### 4) Условный GET формы редактирования (ETag)

```http
GET /api/admin/products/124/form
If-None-Match: "e3b0c44298fc1c149afbf4c8996fb924"
```

При совпадении ETag — `304 Not Modified` без тела.

### 5) Удаление с конфликтом использования в корзине

```http
DELETE /api/v2/products/124
```

Ответ: `409 Conflict` и сообщение о невозможности удаления.

---

## Рекомендации для клиентов (админка/интеграции)

- Для `PATCH` передавайте только изменённые поля; это ускоряет процессор и снижает риск побочных изменений.
- При работе с вариативными товарами:
  - Следите, чтобы только один `optionAssignment.setPrice` был `true`.
  - Валидируйте SKU и уникальность комбинаций на клиенте до отправки.
- Используйте ETag-кеш для форм, чтобы минимизировать трафик и ускорить UX.
- После изменений, влияющих на варианты/остатки, вызывайте соответствующие инвалидаторы кэша (если изменения идут вне стандартного процессора).

---

## Ссылки на код

- `App\ApiResource\ProductResource`
- `App\State\ProductStateProcessor`
- `App\State\ProductStateProvider`
- `App\Entity\Product`
- `App\Doctrine\Listener\ProductEntityListener`
- `App\Service\ProductLifecycleService`
- `App\Controller\Admin\Api\ProductFormController` (ETag форм)
- `App\Controller\Admin\Api\CategoriesTreeController` (ETag дерева категорий)
- `App\Service\InventoryService`, `App\Service\ProductVariantService` (кэш вариантов/остатков)
