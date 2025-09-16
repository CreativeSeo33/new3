# Рефактор компонента CartCounter

Изменения (минимальные, безопасные):

1) Вставлен якорь и тест‑идентификаторы в `templates/components/CartCounter.html.twig`:

```twig
{# ai:component=cart-counter map=@component_cart-counter_map.mdc v=1 #}
<div class="relative inline-block group"
     data-testid="cart-counter"
     ...>
  ...
  <div id="cart-dropdown-panel" ...
       data-testid="cart-counter-dropdown"
       data-cart-dropdown-target="dropdown" role="dialog" aria-hidden="true">
```

2) Создана карта компонента `.cursor/rules/component_cart-counter_map.mdc` с перечислением файлов, targets, событий и параметров.

3) Обновлён индекс компонентов `.cursor/rules/components_index.mdc` — добавлена запись про `cart-counter`.

Безопасность: Бизнес‑логика не тронута, только метаданные и атрибуты для тестирования/ИИ. Совместимость Stimulus контроллеров сохранена.

