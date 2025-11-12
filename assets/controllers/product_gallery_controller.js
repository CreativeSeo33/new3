import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['image', 'indicator'];
  static values = { images: Array };

  connect() {
    // Выделяем первый индикатор как активный по умолчанию
    this.setActiveIndicator(0);
  }

  showImage(event) {
    try {
      const target = event.currentTarget;
      if (!target) return;
      const indexAttr = target.dataset.productGalleryImageIndexValue;
      const index = parseInt(indexAttr || '', 10);
      const urls = this.imagesValue || [];
      if (!Number.isInteger(index) || index < 0 || index >= urls.length) return;

      const imgEl = this.imageTarget;
      if (imgEl && typeof urls[index] === 'string' && urls[index]) {
        if (imgEl.src !== urls[index]) {
          imgEl.src = urls[index];
        }
      }

      this.setActiveIndicator(index);
    } catch (_) {
      // noop: не ломаем UX на краевых случаях
    }
  }

  setActiveIndicator(index) {
    try {
      const items = this.indicatorTargets || [];
      if (!Array.isArray(items) || items.length === 0) return;
      items.forEach((el, i) => {
        el.classList.remove('border-yellow-500');
        el.classList.add('border-gray-300');
      });
      const active = items[index];
      if (active) {
        active.classList.remove('border-gray-300');
        active.classList.add('border-yellow-500');
      }
    } catch (_) {
      // noop
    }
  }
}


