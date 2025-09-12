import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['badge','total'];
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

    // Multi-tab sync
    try {
      this.bc = new BroadcastChannel('cart');
      this.bc.onmessage = (e) => {
        const data = e?.data || {};
        if (typeof data.count === 'number') this.applyExternalCount(data.count);
        if (typeof data.subtotal === 'number') this.applyExternalTotal(data.subtotal);
      };
    } catch {}

    if (this.hasPollValue && this.pollValue > 0) {
      this.intervalId = window.setInterval(() => this.refresh(), this.pollValue);
    }
  }

  disconnect() {
    if (this.intervalId) window.clearInterval(this.intervalId);
    if (this.bc) this.bc.close();
  }

  // data-action: cart:updated@window->cart-counter#onExternalUpdate
  onExternalUpdate(event) {
    const detail = event?.detail || {};
    const next = this.guessCountFromDetail(detail);
    const total = this.guessSubtotalFromDetail(detail);
    if (typeof next === 'number') {
      this.setCount(next);
      this.persistAndBroadcast(next, total);
    }
    if (typeof total === 'number') {
      this.setTotal(total);
    } else {
      this.refresh().catch(() => {});
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
    const res = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!res.ok) return;
    const data = await res.json();
    const next = this.extractCountFromResponse(data);
    const total = this.extractSubtotalFromResponse(data);
    if (typeof next === 'number') {
      this.setCount(next);
      this.persistAndBroadcast(next, total);
    }
    if (typeof total === 'number') {
      this.setTotal(total);
    }
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
}
