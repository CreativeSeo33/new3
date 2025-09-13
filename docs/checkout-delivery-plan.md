### План внедрения выбора и сохранения доставки на странице оформления заказа

Цели:
- Предоставить на `templates/catalog/checkout/index.html.twig` выбор способа доставки (pvz | courier).
- Для `pvz`: подгружать список пунктов выдачи по городу (по полю `city` из `PvzPoints`).
- Для `courier`: показать поле «Адрес доставки» (обязательное) и сохранить адрес в `OrderDelivery.address` при оформлении.
- Не переносить бизнес-логику на фронт: все расчёты/валидации — через API (DeliveryContext/DeliveryService).

---

1) Бэкенд: API, контекст и расчёт

- Используем существующие эндпоинты `DeliveryApiController`:
  - `GET /api/delivery/context` — текущее состояние сессии (city, methodCode, pickupPointId, address, и т.п.).
  - `POST /api/delivery/select-city { cityName, cityId? }` — сохранить город, пересчитать корзину (синхронно).
  - `POST /api/delivery/select-method { methodCode, pickupPointId?, address?, zip? }` — выбрать способ доставки и дополнительные поля.
  - `POST /api/delivery/select-pvz { pvzCode, address? }` — выбрать пункт выдачи (устанавливает метод `pvz`).

- Добавить публичный эндпоинт для получения ПВЗ по городу:
  - `GET /api/delivery/pvz-points?city={name}` → массив точек: `{ code, name, address, city, ... }` (либо минимальный набор: `{ code, address }`).
  - Источник: `PvzPoints` (поиск по полю `city`). Результаты пагинировать при необходимости.

- (Рекомендуется) Нормализовать поиск цены по городу (см. `docs/delivery-impl-changes.md` п.1–2): `PvzPriceRepository::findOneByCityNormalized()` и использование в `DeliveryService`.

- (Готово в документах) Привязка доставки к заказу (см. `docs/delivery-impl-changes.md` п.3):
  - В `CheckoutController::submit` создать `OrderDelivery` из `DeliveryContext` и `DeliveryService::calculateForCart`.
  - Для `pvz` валидировать соответствие `pvzCode` и `city`.
  - Для `courier` перенести `address` (обязателен на фронте).

---

2) Фронтенд: UI и сценарии на `checkout/index.html.twig`

- Блок «Способ доставки» (радиокнопки):
  - Значения: `pvz`, `courier`.
  - При выборе метода вызывать `POST /api/delivery/select-method` c `{ methodCode }`.
  - После ответа — обновить локальное состояние (например, записать в стейт на странице и/или перечитать `GET /api/delivery/context`).

- Блок «Выбор пункта выдачи» (виден, когда `pvz`):
  - При загрузке страницы запросить `GET /api/delivery/context`.
  - Если `context.cityName` определён, запросить `GET /api/delivery/pvz-points?city={cityName}`.
  - Если список ПВЗ пуст — вывести сообщение («В вашем городе ПВЗ отсутствуют. Выберите курьерскую доставку.»).
  - При выборе ПВЗ → `POST /api/delivery/select-pvz` c `{ pvzCode }`.

- Блок «Адрес доставки» (виден, когда `courier`):
  - Поле `address` (обязательно).
  - При изменении/blur — `POST /api/delivery/select-method` c `{ methodCode: 'courier', address }`.
  - Валидация на фронте: не пусто, разумная длина (до 255), без управляющих символов. Окончательная валидация — на бэке.

- Загрузка города (если не выбран):
  - Если `context.cityName` отсутствует — показать селектор города или автокомплит.
  - При выборе города → `POST /api/delivery/select-city` с обновлением корзины/стоимости доставки (ответ возвращается Cart summary).

- Состояния/UX:
  - На все запросы — индикатор загрузки, дизабл инпутов.
  - Обработка ошибок: всплывающее сообщение и сохранение текущего ввода.
  - Кеширование выбранных значений в `localStorage` (как сделано для контактных данных) — опционально.

---

3) Изменения в шаблоне `checkout/index.html.twig` (минимальные)

- Добавить секцию «Способ доставки» над контактными данными:

```html
<section class="p-4 border rounded" id="delivery-methods">
  <h2 class="text-lg font-medium mb-3">Способ доставки</h2>
  <div class="space-y-2">
    <label class="flex items-center gap-2"><input type="radio" name="deliveryMethod" value="pvz"> Пункт выдачи</label>
    <label class="flex items-center gap-2"><input type="radio" name="deliveryMethod" value="courier"> Курьер</label>
  </div>

  <div id="pvz-block" class="mt-3 hidden">
    <div class="text-sm mb-2">Выберите пункт выдачи</div>
    <select id="pvz-select" class="w-full border rounded px-3 py-2"></select>
    <div id="pvz-empty" class="text-sm text-gray-500 mt-2 hidden">В вашем городе пункты выдачи отсутствуют</div>
  </div>

  <div id="courier-block" class="mt-3 hidden">
    <label class="block text-sm mb-1">Адрес доставки</label>
    <input id="courier-address" class="w-full border rounded px-3 py-2" placeholder="Город, улица, дом, квартира" required />
    <div id="addr-error" class="text-xs text-red-600 mt-1 hidden">Адрес обязателен</div>
  </div>
</section>
```

- В скрипте страницы:

```js
// 1. Получаем контекст
const ctx = await fetch('/api/delivery/context').then(r=>r.json()).catch(()=>({}));
const methodInput = document.querySelectorAll('input[name="deliveryMethod"]');
const pvzBlock = document.getElementById('pvz-block');
const pvzSelect = document.getElementById('pvz-select');
const pvzEmpty = document.getElementById('pvz-empty');
const courierBlock = document.getElementById('courier-block');
const addrInput = document.getElementById('courier-address');

// 2. Инициализируем выбранный метод
const initMethod = (ctx.methodCode || 'pvz');
[...methodInput].forEach(r => { r.checked = (r.value === initMethod); });
toggleBlocks(initMethod);

// 3. При смене метода
methodInput.forEach(radio => radio.addEventListener('change', async (e) => {
  const value = e.target.value;
  await fetch('/api/delivery/select-method', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ methodCode: value }) });
  toggleBlocks(value);
  if (value === 'pvz') { await loadPvz(); }
}));

// 4. Загрузка ПВЗ
async function loadPvz() {
  pvzSelect.innerHTML = '';
  pvzEmpty.classList.add('hidden');
  const city = (ctx && ctx.cityName) ? ctx.cityName : null;
  if (!city) { pvzEmpty.textContent = 'Город не выбран'; pvzEmpty.classList.remove('hidden'); return; }
  const list = await fetch('/api/delivery/pvz-points?city=' + encodeURIComponent(city)).then(r=>r.json()).catch(()=>[]);
  if (!Array.isArray(list) || list.length === 0) { pvzEmpty.classList.remove('hidden'); return; }
  for (const p of list) {
    const opt = document.createElement('option');
    opt.value = p.code; opt.textContent = p.address || p.name || p.code;
    pvzSelect.appendChild(opt);
  }
}

// 5. Выбор ПВЗ
pvzSelect?.addEventListener('change', async () => {
  const pvzCode = pvzSelect.value;
  if (!pvzCode) return;
  await fetch('/api/delivery/select-pvz', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ pvzCode }) });
});

// 6. Адрес курьера (обязателен)
addrInput?.addEventListener('blur', async () => {
  const address = (addrInput.value || '').trim();
  document.getElementById('addr-error').classList.toggle('hidden', !!address);
  if (!address) return;
  await fetch('/api/delivery/select-method', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ methodCode: 'courier', address }) });
});

function toggleBlocks(method) {
  const isPvz = method === 'pvz';
  pvzBlock.classList.toggle('hidden', !isPvz);
  courierBlock.classList.toggle('hidden', isPvz);
}
```

Примечание: адрес сохраняется в сессии через `/api/delivery/select-method`, финально переносится в `OrderDelivery.address` при оформлении (см. п.1).

---

4) Тест-кейсы

- Без выбранного города: при попытке загрузить ПВЗ — сообщение «Город не выбран». После `select-city` ПВЗ подгружаются.
- Город с ПВЗ: список загружается, выбор ПВЗ сохраняется; переключение метода на courier и обратно не теряет выбор.
- Город без ПВЗ: сообщение «ПВЗ отсутствуют», метод `courier` остаётся доступным.
- Курьер: пустой адрес → подсветка ошибки; заполнение адреса → уходит `select-method` с `address`.
- После оформления: в заказе присутствует `OrderDelivery` c корректными полями (`type/city/cost/isFree/isCustomCalculate/pvz?` или `address?`).

---

5) Критерии готовности

- На странице checkout доступны: выбор способа, загрузка ПВЗ по городу, ввод адреса для courier.
- Данные сохраняются в `DeliveryContext` через API и учитываются при расчёте доставки.
- `CheckoutController::submit` переносит доставку в `OrderDelivery`.
- UI корректно обрабатывает отсутствие города/ПВЗ и ошибки сети.


