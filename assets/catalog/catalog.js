import './styles.css';
import { createApp } from 'vue';

const CatalogApp = {
  template: `<div class="p-6 bg-emerald-50 min-h-screen">
    <h1 class="text-2xl font-bold text-emerald-700">Catalog</h1>
    <p class="mt-2 text-slate-700">Отдельная сборка catalog работает.</p>
  </div>`
};

const mountTarget = document.getElementById('catalog-app');
if (mountTarget) {
  createApp(CatalogApp).mount(mountTarget);
}


