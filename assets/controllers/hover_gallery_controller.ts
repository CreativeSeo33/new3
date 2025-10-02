import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  private loaded = false;

  loadThumbs() {
    if (this.loaded) return;
    this.loaded = true;

    const thumbs = this.element.querySelectorAll<HTMLElement>('.card-product__thumb');
    thumbs.forEach((thumb) => {
      const img = thumb.querySelector('img') as HTMLImageElement | null;
      const dataSrc = img?.getAttribute('data-src');
      if (img && dataSrc) {
        img.src = dataSrc;
        img.removeAttribute('data-src');
      }
      const bg = thumb.getAttribute('data-bg');
      if (bg) {
        thumb.style.backgroundImage = `url('${bg}')`;
        thumb.removeAttribute('data-bg');
      }
    });
  }
}




