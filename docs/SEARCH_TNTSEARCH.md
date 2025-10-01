# Поиск по товарам: TNTSearch (русская морфология)

Включение / Откат
- ENV флаг: `SEARCH_ENGINE=tnt|mysql` (по умолчанию `mysql`).
- Индексы: `var/search/indexes/products.index` (в VCS не коммитить).
- Полный реиндекс: `php bin/console app:search:reindex-products`.

Конфигурация
- `SEARCH_TNT_STORAGE=%kernel.project_dir%/var/search/indexes`
- `SEARCH_TNT_AS_YOU_TYPE=1`
- `SEARCH_TNT_FUZZY=1`
- `SEARCH_TNT_FUZZY_DISTANCE=1`

Интеграция API
- Параметр `q` на коллекции `Product` (`/api/v2/products`).
- При `SEARCH_ENGINE=tnt` используется индекс, порядок — по релевантности.
- При `SEARCH_ENGINE=mysql` — прежнее поведение (фильтры/сортировки по БД).

Инкрементальные обновления
- Doctrine subscriber `ProductSearchIndexerSubscriber`:
  - `postPersist`/`postUpdate` — upsert
  - `postRemove` — delete

Сервисы
- `TNTSearchFactory` — конфигурирование TNTSearch (filesystem driver, asYouType, fuzziness).
- `RuQueryNormalizer` — нормализация и стемминг (wamania/php-stemmer: Russian).
- `ProductIndexer` — сбор документа из полей: name×3, категории×2, атрибуты, description.
- `ProductSearch` — поиск с учётом offset/limit в PHP.

Известные ограничения
- Offset реализован на уровне PHP (TNTSearch возвращает top-N).
- Стоп-слова заданы пустым списком (можно расширить через DI).

Пост‑деплой
- Выполнить `php bin/console app:search:reindex-products` или перенести индекс как артефакт CI.

Обновления (2025-10-01)
- Страница `/search/?text=...`:
  - SSR инициализация фасетных фильтров: на первом рендере передаём `data-initial-facets` с `facets` и `meta`, чтобы избежать начального запроса к `/api/catalog/facets`.
  - Поддержка числовых фасетов в загрузке товаров: `height`, `bulbs_count`, `lighting_area` фильтруются по полям сущности `ProductOptionValueAssignment` (`height`, `bulbsCount`, `lightingArea`).
  - Пагинация поиска: учитываются `page` и `limit`, сохраняется порядок релевантности TNTSearch. Параметры передаются в грид, ссылки строятся по `catalog_search_products`.
  - Единый грид `_grid.html.twig` теперь поддерживает выбор маршрута (`routeName`, `routeParams`) для построения ссылок пагинации (категория/поиск).
  - UI: блок «Выбранные фильтры» добавлен на страницу поиска, поведение как в категории; пустые фасеты (без значений) не отображаются.