
Задача: Унифицировать Twig‑компонент без page map. Добавить якорный комментарий (ai:component/ai:module), расставить data-testid, проверить lazy‑загрузку Stimulus‑контроллеров, убрать inline <script>. По необходимости — создать необязательную карту компонента (alwaysApply: false).

Ограничения
- Не создавать page map для компонентов (page maps — только для страниц).
- Не менять бизнес‑логику и внешний вид.
- Не использовать inline <script>.
- Не расширять pinned‑набор. Новые карты компонентов — not pinned (`alwaysApply: false`).
- Не трогать `vendor/**`, `node_modules/**`, `public/**`, `public/build/**`, `var/**`.

Область применения
- Обрабатывай только:
  - конкретный(е) компонент(ы), указанный(е) в задаче, или
  - файлы, затронутые в текущем PR/диффе.
- Шаблоны Twig‑компонентов обычно лежат в `templates/components/**.html.twig`. Класс компонента — в `src/**/Twig/Components/**.php`.

Что сделать для каждого целевого компонента
1) Добавить якорный комментарий в начало Twig‑шаблона
- Предпочтительно со ссылкой на карту компонента:
  {# ai:component=<kebab-name> map=@component_<kebab-name>_map.mdc v=1 #}
- Если карту не создаём — достаточно:
  {# ai:module=<kebab-name> root="<селектор_корневого_контейнера>" #}
- Правило именования: `<kebab-name>` — из имени файла/компонента в kebab-case (например, `ExampleBox` → `example-box`).

2) Расставить стабильные data-testid
- Минимально:
  - На корневой контейнер: `data-testid="<kebab-name>-root"`
- Для интерактива/динамики — по смыслу (если узлы присутствуют):
  - Триггер/кнопка: `data-testid="<kebab-name>-trigger"`
  - Панель/дропдаун/модалка: `data-testid="<kebab-name>-panel"`
  - Список/элемент: `data-testid="<kebab-name>-list"`, `data-testid="<kebab-name>-item"`
  - Значимые показатели: `...-badge`, `...-count`, `...-total`, `...-status`
- Не переименовывать существующие `data-testid`, только добавлять недостающее.
- Использовать kebab-case.

3) Убрать inline <script>
- Любые скрипты в шаблоне перенести в JS:
  - По умолчанию — Stimulus‑контроллер(ы) с `"fetch": "lazy"`.
  - Для сложного интерактива каталога — в FSD‑модуль (см. `@catalog_js_architecture.mdc`).
- В шаблоне оставить только `data-controller`/`data-action`/`...-target`/`...-value`.

4) Обеспечить lazy для Stimulus‑контроллеров
- Найти контроллеры в шаблоне (`data-controller="..."`). Для каждого “своего” контроллера добавить/актуализировать запись в `assets/controllers.json`:
  {
    "controllers": {
      "app/<controller-name>": { "enabled": true, "fetch": "lazy" }
    }
  }
- Преобразование имени:
  - Если в шаблоне `data-controller="app--foo-bar"` → ключ `app/foo-bar`.
  - Если `data-controller="foo-bar"` → считать как `app/foo-bar`.
  - Вендорные (`symfony--ux-*`) не менять.
- Если `assets/controllers.json` отсутствует — не создавать слепо. Вернуть TODO в summary с фактическими путями контроллеров и предложением добавить файл.

5) Необязательная “карта компонента” (not pinned)
- Создавай `.cursor/rules/component_<kebab-name>_map.mdc` с `alwaysApply: false`, если компонент:
  - имеет `data-controller` или
  - содержит модалку/дропдаун/список/состояния или
  - шаблон > 100 строк.
- Для простых статичных компонентов карту можно не создавать.
- Содержимое карты см. ниже (шаблон), адаптируй под фактические targets/события/файлы.

6) A11y (минимально и по необходимости)
- Если это диалог/модалка — добавь `role="dialog"`, `aria-modal="true"`, `aria-labelledby`.
- Для раскрывающихся панелей — `aria-expanded` на триггере и `aria-controls` с `id` панели.

Шаблон карты компонента
BEGIN FILE: .cursor/rules/component_<kebab-name>_map.mdc
---
alwaysApply: false
---

# Component: <ComponentName> (<kebab-name>)

Назначение: кратко опиши цель компонента (1–2 строки).

Файлы
- Twig: `templates/components/<ComponentName>.html.twig`
- PHP (Twig Component): `App\Twig\Components\<ComponentName>` (если есть)
- Контроллеры Stimulus: перечисли задействованные контроллеры

Корневой селектор
- Укажи селектор из ai:component/ai:module (например, `.component.<kebab-name>`)

Targets (если есть)
- перечисли targets для каждого контроллера (например: `badge`, `list`, `panel`, `total`)

События (если есть)
- перечисли внешние/глобальные события и их хендлеры (пример: `cart:updated@window -> cart-counter#onExternalUpdate`)

Стабильные якоря (рекомендация)
- `data-testid="<kebab-name>-root"`
- Добавь специфичные для компонента (пример: `<kebab-name>-panel`, `<kebab-name>-list`)

API/значения
- перечисли `data-*-value` и ожидаемые маршруты/URL

Примечания
- Без inline `<script>`; логика — через Stimulus или FSD.
- Добавляй `data-testid` на ключевые узлы.
END FILE

Формат вывода
- Верни изменённые/новые файлы блоками:
  - BEGIN FILE: templates/components/<ComponentName>.html.twig
    ...полный файл с добавленными якорями и data-testid...
    END FILE
  - BEGIN FILE: assets/controllers.json
    ...полный файл (только если он уже существует и были изменения)...
    END FILE
  - BEGIN FILE: .cursor/rules/component_<kebab-name>_map.mdc
    ...если карта создаётся...
    END FILE
- Если `assets/controllers.json` отсутствует или есть неясности — добавь в конце краткий summary с TODO.

Acceptance
- В каждом обработанном компоненте первая строка — ai‑якорь (`ai:component` с `map=...` или `ai:module` с `root=...`).
- Добавлены `data-testid` на корневой контейнер и ключевые узлы.
- Нет inline `<script>`; интерактив — через Stimulus (lazy) или FSD в каталоге.
- Для найденных контроллеров обновлён `assets/controllers.json` c `"fetch": "lazy"`; вендорные контроллеры не трогаются.
- Если компонент сложный — создана `.cursor/rules/component_<kebab-name>_map.mdc` (alwaysApply: false).
- Ничего лишнего не изменено; визуальное поведение сохранено.

В конце выведи summary
- Перечень обработанных компонентов и действий (якорь, testid, controllers.json обновлён/пропущен, карта создана/пропущена).
- TODO, если:
  - нет `assets/controllers.json`;
  - не удалось определить надёжный root‑селектор;
  - компонент содержит сложный интерактив, требующий FSD‑модуля, но он отсутствует.