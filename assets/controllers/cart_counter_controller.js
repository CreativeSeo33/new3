import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['badge'];
  static values = {
    count: Number,
    url: String,
    poll: Number // опционально, если решим включить периодическое обновление
  };

  connect() {
    if (this.countValue == null) {
      this.countValue = this.safeParseInt(this.badgeTarget?.textContent);
    }
    this.render();

    // Multi-tab sync
    try {
      this.bc = new BroadcastChannel('cart');
      this.bc.onmessage = (e) => this.applyExternalCount(e?.data?.count);
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
    if (typeof next === 'number') {
      this.setCount(next);
      this.persistAndBroadcast(next);
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
    if (typeof next === 'number') {
      this.setCount(next);
      this.persistAndBroadcast(next);
    }
  }

  setCount(n) {
    if (!Number.isFinite(n) || n < 0) return;
    if (n === this.countValue) return;
    this.countValue = n;
    this.render();
  }

  render() {
    if (this.badgeTarget) this.badgeTarget.textContent = String(this.countValue ?? 0);
    // небольшая подсветка изменения
    this.badgeTarget?.classList.add('ring-2','ring-red-300');
    setTimeout(() => this.badgeTarget?.classList.remove('ring-2','ring-red-300'), 150);
  }

  applyExternalCount(n) {
    if (typeof n === 'number') this.setCount(n);
  }

  persistAndBroadcast(n) {
    try { localStorage.setItem('cart:count', String(n)); } catch {}
    try { this.bc?.postMessage({ count: n }); } catch {}
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

  safeParseInt(v) {
    const n = parseInt(String(v ?? '0'), 10);
    return Number.isFinite(n) ? n : 0;
  }
}
