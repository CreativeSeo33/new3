# Рефактор: City Modal → Twig Component + FSD

Кратко что сделано:

- Вынес разметку модалки города в Twig-компонент `CityModal`.
- Удалил inline `<script>` из `templates/catalog/layouts/header.html.twig`.
- Реализовал FSD‑модуль `features/city-modal` (api/ui) и зарегистрировал в реестре.
- Реализовал FSD‑модуль `features/mobile-menu` и зарегистрировал.

Затронутые файлы (минимальные правки):

```12:22:assets/catalog/src/app/registry.ts
  'autocomplete': () => import('../features/autocomplete').then(m => m.init),
  'city-modal': () => import('../features/city-modal').then(m => m.init),
  'mobile-menu': () => import('../features/mobile-menu').then(m => m.init),
```

```211:214:templates/catalog/layouts/header.html.twig
  {{ component('CityModal') }}
  <div data-module="mobile-menu" aria-hidden="true" class="hidden"></div>
```

Добавлено:

- `src/Twig/Components/CityModal.php`
- `templates/components/CityModal.html.twig` — содержит контейнер модалки с `data-module="city-modal"` и `data-testid`.
- `assets/catalog/src/features/city-modal/{api/index.ts, ui/component.ts, index.ts}` — загрузка списка городов и выбор города через бекенд `/api/delivery/select-city`.
- `assets/catalog/src/features/mobile-menu/{ui/component.ts, index.ts}` — логика открытия/закрытия мобильного меню.

Политики соблюдены:

- Нет inline `<script>` в Twig; интерактив через FSD.
- HTTP вызовы — только через `@shared/api/http`.
- Разделение API/UI слоев.
- Инициализация через реестр `data-module`.
- Селекторы для автотестов: `data-testid` у корня модалки, `data-list` для контейнера.

Примечание:

- Компонент `CityModal` сам не открывается; триггер остаётся `[data-city-modal-trigger]` в хедере, обработку клика делает модуль `city-modal` при вызове публичного метода `show()` извне (сейчас используется `ensureLoaded()` при видимости). Ленивая загрузка списка при первом показе.


