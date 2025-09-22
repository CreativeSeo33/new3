# Задача для агента: Добавить третий тип товара — configurable (опции без цен)

## Цель
Внедрить новый тип товара `configurable`, у которого есть опции (вариации), но цены на уровне вариаций отсутствуют. Цена (и при наличии распродажная цена) задаётся на самом товаре и применяется ко всем его опциям. Должны корректно работать валидации, материализация `effectivePrice`, копирование товара и формы в админке.

## Контекст (текущий код)
- Entity: `src/Entity/Product.php` (имеет типы `simple`, `variable`; методы `isSimple()`, `isVariable()`; валидации и `validateOptionAssignments()`; группы API Platform)
- Entity: `src/Entity/ProductOptionValueAssignment.php` (ценовые поля: `price`, `salePrice`, `setPrice` — nullable)
- Сервис: `src/Service/ProductLifecycleService.php` (`materializeEffectivePrice()` ветвит по `isVariable()` и обнуляет/считает цены для `variable`, для `simple` — берёт цену товара)
- Сервис: `src/Service/ProductCopyService.php` (копирование по типам `simple`/`variable`)
- Admin: `assets/admin/views/ProductForm.vue` (селект типа, вкладка «Опции», проверки блокировки сохранения)
- Admin: `assets/admin/composables/useProductSave.ts` (формирование payload, включение/исключение `optionAssignments`)
- Admin: `assets/admin/repositories/ProductRepository.ts` (интерфейс `ProductDto` с `type?: string | null`)

## Требования к изменениям

### Backend (Symfony)
1) `src/Entity/Product.php`
- Добавить константу и метод:
```php
public const TYPE_CONFIGURABLE = 'configurable';

public function isConfigurable(): bool
{
    return $this->type === self::TYPE_CONFIGURABLE;
}
```
- В аннотации `#[Assert\Choice(...)]` для `$type` добавить `self::TYPE_CONFIGURABLE` в список допустимых значений.
- Обновить `validateOptionAssignments()`:
  - Для `simple`: опционы запрещены (как сейчас), проверять через `isSimple()`.
  - Для `variable`: требуется хотя бы одна вариация (как сейчас), проверять через `isVariable()`.
  - Для `configurable`: разрешены опции, но на уровне вариаций запрещены ценовые поля.
    - Если хотя бы в одном `ProductOptionValueAssignment` задано `price != null` или `salePrice != null` или `setPrice === true`, добавить violation: «Цены вариаций не допускаются для конфигурируемого товара» на путь `optionAssignments`.
- Проверки цены товара оставляем: действующие выражения уже требуют наличие и положительность `price` для всех типов, кроме `variable`, что корректно покрывает `configurable`.

2) `src/Service/ProductLifecycleService.php`
- В `materializeEffectivePrice(Product $product)` добавить ветку для `isConfigurable()` и вызвать новый метод `handleConfigurableProduct()`.
- Реализовать `handleConfigurableProduct(Product $product)`: не обнулять базовые поля, выставить `effectivePrice = salePrice ?? price`.

3) `src/Service/ProductCopyService.php`
- Логику можно оставить как есть. Опционально: при копировании трактовать `configurable` как `simple` (копировать базовую цену; не трогать вариационные цены, которых и так нет). Проверить, что ветка `variable → simple` не ломает `configurable`.

### Frontend (Admin)
1) `assets/admin/views/ProductForm.vue`
- В селекте типа добавить опцию:
```html
<option value="configurable">Конфигурируемый товар</option>
```
- Вкладка «Опции»: показывать для `type === 'variable' || type === 'configurable'`.
- Блокировка сохранения из-за отсутствия вариаций — только для `variable`. Для `configurable` сохранение не блокировать, даже если вариаций нет.

2) `assets/admin/composables/useProductSave.ts`
- Определить `isConfigurable` аналогично `isVariable`.
- При формировании `optionAssignments`:
  - Включать поле для `variable` и `configurable` (для `simple` — не отправлять совсем).
  - Для `configurable` принудительно занулять `price` и `salePrice`, устанавливать `setPrice = false` для каждой строки; остальные поля передавать как есть.

3) Типы/DTO
- `assets/admin/repositories/ProductRepository.ts`: при необходимости уточнить допустимые значения `type` в JSDoc/типовзятии (не критично, т.к. поле уже строковое), но UI должен уметь отправлять `'configurable'`.

## Псевдо-диффы (ключевые вставки)

- `src/Entity/Product.php` — дополнения:
```php
// Типы товаров
public const TYPE_SIMPLE = 'simple';
public const TYPE_VARIABLE = 'variable';
public const TYPE_CONFIGURABLE = 'configurable';

public function isConfigurable(): bool
{
    return $this->type === self::TYPE_CONFIGURABLE;
}

// Choice: [self::TYPE_SIMPLE, self::TYPE_VARIABLE, self::TYPE_CONFIGURABLE]

public function validateOptionAssignments(ExecutionContextInterface $context): void
{
    if ($this->isVariable() && $this->optionAssignments->isEmpty()) {
        $context->buildViolation('Вариативный товар должен иметь хотя бы одну вариацию')
            ->atPath('type')->addViolation();
    }

    if ($this->isSimple() && !$this->optionAssignments->isEmpty()) {
        $context->buildViolation('Простой товар не должен иметь вариаций')
            ->atPath('type')->addViolation();
    }

    if ($this->isConfigurable()) {
        foreach ($this->optionAssignments as $a) {
            if ($a->getPrice() !== null || $a->getSalePrice() !== null || $a->getSetPrice() === true) {
                $context->buildViolation('Цены вариаций не допускаются для конфигурируемого товара')
                    ->atPath('optionAssignments')->addViolation();
                break;
            }
        }
    }
}
```

- `src/Service/ProductLifecycleService.php` — дополнения:
```php
protected function materializeEffectivePrice(Product $product): void
{
    if ($product->isVariable()) {
        $this->handleVariableProduct($product);
    } elseif ($product->isConfigurable()) {
        $this->handleConfigurableProduct($product);
    } else {
        $this->handleSimpleProduct($product);
    }
}

protected function handleConfigurableProduct(Product $product): void
{
    $effectivePrice = $product->getSalePrice() ?? $product->getPrice();
    $product->setEffectivePrice($effectivePrice);
}
```

- `assets/admin/views/ProductForm.vue` — ключевые моменты:
```html
<option value="configurable">Конфигурируемый товар</option>
```
```ts
// tabs
if (form?.type === 'variable' || form?.type === 'configurable') {
  baseTabs.splice(3, 0, { value: 'options', label: 'Опции' })
}

// блокировка сохранения — только для variable
const isVariableWithoutVariations = computed(() => {
  if (form?.type !== 'variable') return false
  const rows = Array.isArray(form?.optionAssignments) ? (form.optionAssignments as any[]) : []
  const valid = rows.filter(r => r && r.option && r.value)
  return valid.length === 0
})
```

- `assets/admin/composables/useProductSave.ts` — ключевые моменты:
```ts
const isVariable = (data as any)?.type === 'variable'
const isConfigurable = (data as any)?.type === 'configurable'

const filteredAssignments = Array.isArray((data as any).optionAssignments)
  ? ((data as any).optionAssignments as any[])
      .filter(r => r && typeof r.option === 'string' && r.option && typeof r.value === 'string' && r.value)
      .map(r => ({
        option: r.option,
        value: r.value,
        height: toInt(r.height),
        bulbsCount: toInt(r.bulbsCount),
        sku: r.sku ?? null,
        originalSku: r.originalSku ?? null,
        price: isConfigurable ? null : toInt(r.price),
        salePrice: isConfigurable ? null : toInt(r.salePrice),
        setPrice: isConfigurable ? false : (r.setPrice ?? false),
        lightingArea: toInt(r.lightingArea),
        sortOrder: toInt(r.sortOrder),
        quantity: toInt(r.quantity),
        attributes: r.attributes ?? null,
      }))
  : null

const payload: Partial<ProductDto> = {
  // ...
  optionAssignments: (isVariable || isConfigurable)
    ? (filteredAssignments && filteredAssignments.length ? filteredAssignments : [])
    : undefined,
}
```

## Тест‑план (acceptance)
- Simple:
  - Нельзя добавить вариации. POST/PUT с `optionAssignments` → 400/422 с сообщением «Простой товар не должен иметь вариаций».
  - `effectivePrice` = price/salePrice.
- Variable:
  - Требуется минимум одна валидная вариация (пара опция+значение). Базовые цены товара обнуляются на сохранении.
  - `effectivePrice` = минимальная цена среди вариаций (`salePrice ?? price`).
- Configurable:
  - Разрешены опции, но цены на уровне вариаций игнорируются/валидируются как запрещённые.
  - Базовая цена товара обязательна и > 0 (действующие `Assert\Expression`).
  - `effectivePrice` = price/salePrice товара.

Проверка API:
- POST `/api/v2/products` с `type: configurable`, базовой ценой и любым количеством `optionAssignments` без ценовых полей на вариациях — 201.
- PATCH `/api/v2/products/{id}` с добавлением/удалением опций — 200; любые попытки передать ценовые поля в вариациях → 400/422.

## Ограничения
- Миграции БД не требуются (тип — строка).
- Не хардкодить значения и URL (следуем конфиг‑правилам проекта).

## Формат PR
- Отдельные коммиты для backend и admin.
- Краткое описание: «feat(product): add configurable product type (options without prices)».
- В описании привести выдержку из данного документа и чек‑лист тест‑кейсов.
