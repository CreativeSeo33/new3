import { Controller } from '@hotwired/stimulus';
import IMask from 'imask';

export default class extends Controller {
  connect() {
    const maskOptions = {
      mask: '+{7} (000) 000-00-00',
      lazy: false,
    };

    this.mask = IMask(this.element, maskOptions);
  }

  disconnect() {
    if (this.mask) {
      this.mask.destroy();
      this.mask = null;
    }
  }
}


