# Catalog Base: рефакторинг под .cursor правила

Изменения
- Добавлен ai:page якорь в `templates/catalog/base.html.twig` первой строкой:
  `{# ai:page=catalog_base map=@page_catalog_base_map.mdc v=1 #}`
- Создана карта страницы: `.cursor/rules/page_catalog_base_map.mdc`

Контекст
- Соблюдены правила Catalog JS Architecture и page maps (.cursor).
- Без изменения бизнес‑логики и структуры блоков, только метаданные.

Проверка
- Сборка фронтенда: `npm run build:catalog`
- Визуально убедиться в отсутствии изменений UI.
