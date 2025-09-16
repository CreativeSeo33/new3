# Рефактор компонента Spinner (2025‑09‑16)

Изменения (минимальные, безопасные):

1) Удалён overlay `<div>` из `templates/components/spinner.html.twig`. Теперь подложка создаётся и управляется в TS (`assets/catalog/src/shared/ui/spinner/ui/spinner.ts`).
2) Обновлена карта `.cursor/rules/component_spinner_map.mdc`: добавлено примечание, что overlay не размечается в Twig.

Совместимость:
- Параметры `data-visible`, `data-overlay`, `data-size`, `data-color` сохранены.
- Инициализация через `data-module="spinner"` без изменений.
- Внешние include/использования (например, в `CartCounter` и `CityModal`) не требуют правок.

Причина:
- Единая точка управления overlay и стилями в TS, отсутствие дублирующей разметки.


