Краткие правки: сортировка опций в корзине/чекауте

Изменения
- `templates/catalog/cart/index.html.twig`: перед выводом `item.optionAssignments` добавлена сортировка по `assignment.option.sortOrder` (fallback на `assignment.sortOrder`).
- `templates/catalog/checkout/index.html.twig`: аналогичная сортировка.

Причина
- Требование: выводить опции товара строго по `Option.sortOrder` без дополнительных запросов/API.
- Данные уже подгружаются через `CartRepository` (`leftJoin` на `option` и `value`), сортировка выполняется в памяти Twig, без SQL/HTTP оверхеда.

Фрагмент
```twig
{% set sortedAssignments = item.optionAssignments
  |sort((a,b) => (a.option.sortOrder <=> b.option.sortOrder) ?: ((a.sortOrder ?? 2147483647) <=> (b.sortOrder ?? 2147483647))) %}
{% for assignment in sortedAssignments %}
  {{ assignment.option.name }}: {{ assignment.value.value }}
{% endfor %}
```

Замечание
- В `Product` страница уже использовала такую же стратегию (см. `product/show.html.twig`).

