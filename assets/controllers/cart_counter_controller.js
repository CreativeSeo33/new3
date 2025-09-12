import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['badge','total','dropdown','list','dropdownTotal','shipping'];
  static values = {
    count: Number,
    total: Number,
    currency: String,
    url: String,
    poll: Number // опционально, если решим включить периодическое обновление
  };

  connect() {
    if (this.countValue == null) {
      this.countValue = this.safeParseInt(this.badgeTarget?.textContent);
    }
    if (this.totalValue == null) {
      this.totalValue = this.safeParseInt(this.totalTarget?.textContent);
    }
    this.render();

    // Lazy load items on first hover
    this.hoverHandler = () => {
      if (!this.itemsLoaded) this.loadItems().catch(() => {});
    };
    try {
      this.element.addEventListener('mouseenter', this.hoverHandler, { passive: true });
    } catch {}

    // Multi-tab sync
    try {
      this.bc = new BroadcastChannel('cart');
      this.bc.onmessage = (e) => {
        const data = e?.data || {};
        if (typeof data.count === 'number') this.applyExternalCount(data.count);
        if (typeof data.subtotal === 'number') this.applyExternalTotal(data.subtotal);
        // любые внешние изменения делают список неактуальным
        this.itemsLoaded = false;
      };
    } catch {}

    if (this.hasPollValue && this.pollValue > 0) {
      this.intervalId = window.setInterval(() => this.refresh(), this.pollValue);
    }
  }

  disconnect() {
    if (this.intervalId) window.clearInterval(this.intervalId);
    if (this.bc) this.bc.close();
    if (this.hoverHandler) this.element.removeEventListener('mouseenter', this.hoverHandler);
  }

  // data-action: cart:updated@window->cart-counter#onExternalUpdate
  onExternalUpdate(event) {
    const detail = event?.detail || {};
    // Если город изменился — принудительно обновляем корзину (доставка/итого)
    if (detail.cityChanged) {
      this.itemsLoaded = false;
      this.refresh().catch(() => {});
      return;
    }
    const next = this.guessCountFromDetail(detail);
    const total = this.guessSubtotalFromDetail(detail);
    if (typeof next === 'number') {
      this.setCount(next);
      this.persistAndBroadcast(next, total);
    }
    if (typeof total === 'number') {
      this.setTotal(total);
    }
    // обновляем доставку/итого, если данные есть в detail; иначе подтянем через refresh
    if (detail && (detail.shipping || typeof detail.subtotal === 'number')) {
      this.updateShippingAndGrandTotal(detail);
    } else {
      this.refresh().catch(() => {});
    }
    // если пришёл полный список товаров — обновляем немедленно
    if (Array.isArray(detail.items)) {
      this.renderItems(detail.items, detail.currency || 'RUB');
      this.itemsLoaded = true;
      if (typeof total === 'number' && this.hasDropdownTotalTarget) {
        this.dropdownTotalTarget.textContent = this.formatRub(total);
      }
    } else {
      // помечаем данные как устаревшие, чтобы при следующем наведении перезагрузить
      this.itemsLoaded = false;
    }
  }

  // data-action: storage@window->cart-counter#onStorageEvent
  onStorageEvent(e) {
    if (e.key === 'cart:count' && e.newValue) {
      const next = this.safeParseInt(e.newValue);
      if (Number.isFinite(next)) this.setCount(next);
    }
  }

  async refresh() {
    if (!this.hasUrlValue) return;
    this.showSpinner();
    let data;
    try {
      const res = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) { this.hideSpinner(); return; }
      data = await res.json();
    } catch (e) {
      this.hideSpinner();
      return;
    }
    try { this.cartEtag = res.headers.get('ETag') || null; } catch { this.cartEtag = null; }
    if (typeof data?.version === 'number') this.cartVersion = data.version;
    const next = this.extractCountFromResponse(data);
    const total = this.extractSubtotalFromResponse(data);
    if (typeof next === 'number') {
      this.setCount(next);
      this.persistAndBroadcast(next, total);
    }
    if (typeof total === 'number') {
      this.setTotal(total);
      if (this.hasDropdownTotalTarget) this.dropdownTotalTarget.textContent = this.formatRub(total);
    }
    if (Array.isArray(data?.items)) {
      this.renderItems(data.items, data?.currency || 'RUB');
      this.itemsLoaded = true;
    } else {
      this.itemsLoaded = false;
    }
    // update shipping and grand total
    this.updateShippingAndGrandTotal(data);
    // ensure spinner hidden after refresh completes
    this.hideSpinner();
  }

  async loadItems() {
    if (!this.hasUrlValue) return;
    this.showSpinner();
    let data;
    try {
      const res = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) { this.hideSpinner(); return; }
      data = await res.json();
    } catch (e) {
      this.hideSpinner();
      return;
    }
    try { this.cartEtag = res.headers.get('ETag') || null; } catch { this.cartEtag = null; }
    if (typeof data?.version === 'number') this.cartVersion = data.version;
    const items = Array.isArray(data?.items) ? data.items : [];
    this.renderItems(items, data?.currency || 'RUB');
    this.itemsLoaded = true;
    const subtotal = this.extractSubtotalFromResponse(data);
    if (typeof subtotal === 'number') {
      this.setTotal(subtotal);
      if (this.hasDropdownTotalTarget) this.dropdownTotalTarget.textContent = this.formatRub(subtotal);
    }
    // update shipping and grand total
    this.updateShippingAndGrandTotal(data);
    this.hideSpinner();
  }

  renderItems(items, currency) {
    if (!this.hasListTarget) return;
    if (!items.length) {
      this.listTarget.innerHTML = '<div class="text-sm text-gray-500">Корзина пуста</div>';
      this.hideSpinner();
      return;
    }
    const html = items.map((i) => {
      const qty = Number(i.qty || 0);
      const price = Number((i.effectiveUnitPrice ?? i.unitPrice) || 0);
      const rowTotal = Number(i.rowTotal || (qty * price));
      const raw = i.firstImageSmUrl || i.firstImageUrl;
      const img = raw ? this.normalizeImageUrl(raw) : '';
      const href = this.productHref(i);
      const optionsHtml = this.renderOptionsHtml(i);
      return `
        <div class="flex items-center gap-3 py-2">
          <a href="${href}" class="w-12 h-12 bg-gray-100 rounded overflow-hidden border flex items-center justify-center shrink-0">
            ${img ? `<img src="${img}" alt="" class="w-full h-full object-cover"/>` : '<span class="text-gray-400 text-xs">нет фото</span>'}
          </a>
          <div class="flex-1 min-w-0">
            <a href="${href}" class="text-sm text-gray-900 truncate hover:text-blue-700">${this.escapeHtml(i.name || '')}</a>
            ${optionsHtml}
            <div class="text-xs text-gray-500">${qty} × ${this.formatRub(price)}</div>
          </div>
          <div class="text-sm text-gray-900 whitespace-nowrap">${this.formatRub(rowTotal)}</div>
        </div>
      `;
    }).join('');
    this.listTarget.innerHTML = html;
    this.hideSpinner();
  }

  spinnerElement() {
    try { return this.element.querySelector('#cart-counter-spinner'); } catch { return null; }
  }

  showSpinner() {
    const el = this.spinnerElement();
    if (!el) return;
    // Инициируем spinner.ts, если он есть
    try {
      if (typeof window !== 'undefined' && 'Spinner' in window) {
        // no-op, если не зарегистрирован глобально
      }
    } catch {}
    // Встроенный механизм: data-visible
    el.setAttribute('data-visible', 'true');
    el.style.display = 'flex';
    el.style.pointerEvents = 'auto';
  }

  hideSpinner() {
    const el = this.spinnerElement();
    if (!el) return;
    el.setAttribute('data-visible', 'false');
    el.style.display = 'none';
    el.style.pointerEvents = 'none';
  }

  updateShippingAndGrandTotal(data) {
    try {
      const subtotal = Number(data?.subtotal || 0);
      const shippingCost = (data?.shipping && typeof data.shipping.cost === 'number') ? data.shipping.cost : null;
      if (this.hasShippingTarget) {
        this.shippingTarget.textContent = (shippingCost === null)
          ? 'Расчет менеджером'
          : this.formatRub(shippingCost);
      }
      const grandTotal = subtotal + (shippingCost || 0);
      if (this.hasDropdownTotalTarget) {
        this.dropdownTotalTarget.textContent = this.formatRub(grandTotal);
      }
    } catch {}
  }

  productHref(item) {
    try {
      if (item && typeof item.url === 'string' && item.url) return item.url;
      const slug = (item && (item.slug || (item.product && item.product.slug))) ? (item.slug || item.product.slug) : null;
      if (slug) return `/product/${encodeURIComponent(String(slug))}`;
      return '#';
    } catch { return '#'; }
  }

  renderOptionsHtml(item) {
    const lines = this.getOptionsList(item);
    if (!lines.length) return '';
    return `<div class="mt-0.5">${lines.map(l => `<div class=\"text-xs text-gray-500 truncate\">${l}</div>`).join('')}</div>`;
  }

  getOptionsList(item) {
    try {
      const list = Array.isArray(item?.selectedOptions) ? item.selectedOptions : [];
      if (!list.length) return [];
      return list.map((o) => {
        const optName = o.option_name || o.optionName || o.option_code || (o.option && (o.option.name || o.option.code));
        const valName = o.value_name || o.valueName || o.value_code || (o.value && (o.value.value || o.value.code));
        const left = optName ? this.escapeHtml(String(optName)) : '';
        const right = valName ? this.escapeHtml(String(valName)) : '';
        const pair = [left, right].filter(Boolean).join(': ');
        return pair;
      }).filter(Boolean);
    } catch { return []; }
  }

  setCount(n) {
    if (!Number.isFinite(n) || n < 0) return;
    if (n === this.countValue) return;
    this.countValue = n;
    this.render();
  }

  setTotal(n) {
    if (!Number.isFinite(n) || n < 0) return;
    if (n === this.totalValue) return;
    this.totalValue = n;
    this.render();
  }

  render() {
    if (this.badgeTarget) this.badgeTarget.textContent = String(this.countValue ?? 0);
    // небольшая подсветка изменения
    this.badgeTarget?.classList.add('ring-2','ring-red-300');
    setTimeout(() => this.badgeTarget?.classList.remove('ring-2','ring-red-300'), 150);
    if (this.hasTotalTarget) {
      this.totalTarget.textContent = this.formatRub(this.totalValue ?? 0);
    }
  }

  applyExternalCount(n) {
    if (typeof n === 'number') this.setCount(n);
  }

  applyExternalTotal(n) {
    if (typeof n === 'number') this.setTotal(n);
  }

  persistAndBroadcast(n, total) {
    try { localStorage.setItem('cart:count', String(n)); } catch {}
    try { this.bc?.postMessage({ count: n, total }); } catch {}
  }

  guessCountFromDetail(detail) {
    if (typeof detail.count === 'number') return detail.count;
    return undefined;
  }

  extractCountFromResponse(data) {
    if (typeof data?.count === 'number') return data.count;
    if (typeof data?.totalItemQuantity === 'number') return data.totalItemQuantity;
    return undefined;
  }

  guessSubtotalFromDetail(detail) {
    if (typeof detail.subtotal === 'number') return detail.subtotal;
    if (typeof detail?.totals?.subtotal === 'number') return detail.totals.subtotal;
    return undefined;
  }

  extractSubtotalFromResponse(data) {
    if (typeof data?.subtotal === 'number') return data.subtotal;
    if (typeof data?.totals?.subtotal === 'number') return data.totals.subtotal;
    return undefined;
  }

  safeParseInt(v) {
    const n = parseInt(String(v ?? '0'), 10);
    return Number.isFinite(n) ? n : 0;
  }

  formatRub(amount) {
    try {
      // amount уже в копейках? нет — теперь используем subtotal как рубли
      const value = Number(amount || 0);
      return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value) + ' руб.';
    } catch {
      return String(amount || 0) + ' руб.';
    }
  }

  normalizeImageUrl(url) {
    try {
      if (!url) return '';
      if (url.startsWith('http')) return url;
      // В проекте изображения лежат в /img/ или уже /media/cache/.., оставляем как есть
      return url;
    } catch { return ''; }
  }

  escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}
