Задача: нормализовать отображение стоимости доставки в `cart/summary` при нечисловом значении `delivery.cost` и корректно считать итог.

Изменён файл: `templates/components/cart/summary.html.twig`

Ключевые правки:
- Нормализация `shippingCost`: если `delivery.cost` не определён/`null`/нечисловой — считаем 0; строки с запятой конвертируем.
- Единый рендер блока: всегда показываем срок доставки, если он есть.
- Итог: `cart.subtotal + shippingCost`.

Фрагмент:

```twig
{# Безопасная нормализация стоимости доставки: строки c запятой → точка; иначе 0 #}
{% set shippingCost = 0 %}
{% if delivery.cost is defined and delivery.cost is not null %}
  {% set costStr = (delivery.cost ~ '')|replace({',': '.'})|trim %}
  {% if costStr matches '/^-?\\d+(?:\\.\\d+)?$/' %}
    {% set shippingCost = costStr + 0 %}
  {% endif %}
{% endif %}

<div>Стоимость товаров: <span id="cart-subtotal" data-cart-subtotal data-testid="subtotal">{{ cart.subtotal|number_format(0, '.', ' ') ~ ' руб.' }}</span></div>
{% if delivery.cost is not defined or delivery.cost is null %}
  <div>Доставка: <span id="cart-shipping" data-cart-shipping data-testid="shipping">Расчет менеджером</span></div>
{% else %}
  <div>Доставка: <span id="cart-shipping" data-cart-shipping data-testid="shipping">{{ shippingCost|number_format(0, '.', ' ') ~ ' руб.' }}</span></div>
{% endif %}
{% if delivery.term is defined and delivery.term %}
  <div class="text-sm text-gray-600" id="cart-shipping-term" data-cart-shipping-term data-testid="shipping-term">{{ delivery.term }}</div>
{% endif %}
<div class="border-t pt-2 font-semibold">
  Итого: <span id="cart-total" data-cart-total data-testid="total">{{ (cart.subtotal + shippingCost)|number_format(0, '.', ' ') ~ ' руб.' }}</span>
</div>
```

Проверочный кейс:
- Вход: `cart.subtotal=13000`, `delivery.cost='не число'`, `delivery.term='от 2 до 4 дней'`.
- Ожидаемо: `Стоимость товаров: 13 000 руб.`, `Доставка: 0 руб.`, `Срок: от 2 до 4 дней`, `Итого: 13 000 руб.`


