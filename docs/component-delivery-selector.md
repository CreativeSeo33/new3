Задача: унификация Twig‑компонента `_delivery_selector.html.twig` по гайду promt‑twig‑component‑map.

Сделанные изменения
- Добавлен ai‑якорь в начало шаблона для FSD‑модуля.
- Проверены `data-testid` — покрывают корень и ключевые узлы; без переименований.
- Stimulus‑контроллеры не используются → `assets/controllers.json` не менялся.
- Создана карта компонента: `.cursor/rules/component_delivery-selector_map.mdc` (alwaysApply: false).

Правка в шаблоне
```12:16:templates/catalog/_delivery_selector.html.twig
{# ai:module=delivery-selector root="#delivery" #}
<div id="delivery" data-module="delivery-selector" data-testid="delivery-selector" class="p-4 border rounded mb-6">
```

Файлы
- Шаблон: `templates/catalog/_delivery_selector.html.twig`
- Карта: `.cursor/rules/component_delivery-selector_map.mdc`
- FSD UI: `assets/catalog/src/features/delivery-selector/ui/component.ts`
- FSD API: `assets/catalog/src/features/delivery-selector/api/index.ts`

Примечание
- Визуальное поведение не менялось; inline `<script>` отсутствует.

