# Рефактор компонента Spinner (Twig/UX + FSD)

Изменения (минимальные и совместимые):

- В `templates/components/spinner.html.twig` добавлен ai-якорь и `data-testid`:

```twig
{# ai:component=spinner map=@component_spinner_map.mdc v=1 #}
<div id="{{ spinner_id }}" data-testid="spinner" ... data-module="spinner" ...></div>
```

- Создана карта компонента: `.cursor/rules/component_spinner_map.mdc`.
- Обновлён индекс компонентов: `.cursor/rules/components_index.mdc` — добавлена запись `@component_spinner_map.mdc`.

Совместимость:
- Атрибуты `data-visible`, `data-overlay`, `data-size`, `data-color` сохранены; структура DOM не нарушена.
- Реестр уже содержит ключ `spinner` → `@shared/ui/spinner`.

Файлы:
- Twig: `templates/components/spinner.html.twig`
- Shared UI: `assets/catalog/src/shared/ui/spinner`

