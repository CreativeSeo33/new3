import { Controller } from '@hotwired/stimulus';

/*
 * Click Counter Controller
 *
 * Пример контроллера для подсчета кликов
 */
export default class extends Controller {
    static targets = ["count"];

    connect() {
        console.log('Click Counter controller connected!');
        this.count = 0;
        this.updateDisplay();
    }

    increment() {
        this.count++;
        this.updateDisplay();
    }

    decrement() {
        if (this.count > 0) {
            this.count--;
            this.updateDisplay();
        }
    }

    reset() {
        this.count = 0;
        this.updateDisplay();
    }

    updateDisplay() {
        if (this.hasCountTarget) {
            this.countTarget.textContent = this.count;
        }
    }

    disconnect() {
        console.log('Click Counter controller disconnected!');
    }
}
