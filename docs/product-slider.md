## Product Slider (Twig + FSD widget)

### Назначение
Переиспользуемый Twig-компонент витрины для вывода карусели товаров, работающий поверх JS-виджета `product-slider` (FSD `widgets/product-slider`).

### Файлы
- Twig: `templates/components/product-slider.html.twig`
- JS-виджет: `assets/catalog/src/widgets/product-slider`
- Реестр модулей: `assets/catalog/src/app/registry.ts` (ключ `'product-slider'`)

### Параметры компонента
- `products` — обязательная коллекция товаров для грида.
- `gridTitle` — опциональный заголовок блока (строка).
- `isInSlider` — опциональный флаг для `_grid.html.twig`, по умолчанию `true`.

### Структура разметки
- Корневой элемент: `div[data-module="product-slider"]`.
- Скелетон: блок с `data-product-slider-skeleton` (5 карточек-скелетонов).
- Контент: блок с `data-product-slider-content`, внутри:
  - include `catalog/category/_grid.html.twig` с `{ products, gridTitle, isInSlider }`
  - кнопки навигации `.swiper-button-prev` и `.swiper-button-next`.

### Использование в Twig
На главной странице каталога (`templates/catalog/index.html.twig`):

```twig
{% set gridTitle = 'Хиты продаж' %}
{% include 'components/product-slider.html.twig' with { products: products, gridTitle: gridTitle } %}
```

### Интеграция с JS-модулем
- Инициализация происходит через FSD-реестр по `data-module="product-slider"`.
- JS-виджет ответственен за:
  - переключение видимости блоков `data-product-slider-skeleton` и `data-product-slider-content`;
  - настройку и управление слайдером (Swiper) и навигационными кнопками.

### Секция «Похожие товары» на карточке
- Контроллер `ProductCatalogController::show` подтягивает `Product[]` через `RelatedProductRepository::findRelatedProducts($productId, $limit)` и передаёт их в Twig как `relatedProducts`.
- В шаблоне `templates/catalog/product/show.html.twig` блок слайдера выводится только при наличии связанных товаров:

```twig
{% if relatedProducts|length > 0 %}
  {% include 'components/product-slider.html.twig' with {
    products: relatedProducts,
    gridTitle: 'Похожие товары'
  } %}
{% endif %}
```

- Лимит и сортировка определяются репозиторием, поэтому компоненту достаточно корректной коллекции `Product`.
- Ту же схему можно переиспользовать для любых связанных подборок (например, акций) — главное, чтобы контроллер подготовил массив товаров и обернул include условием.


