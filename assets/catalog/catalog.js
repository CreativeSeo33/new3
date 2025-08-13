import './styles.css';

document.addEventListener('DOMContentLoaded', () => {
  const mountTarget = document.getElementById('catalog-app');
  if (!mountTarget) return;

  mountTarget.innerHTML = `
    <div class="p-6 bg-emerald-50 min-h-screen">
      <h1 class="text-2xl font-bold text-emerald-700">Catalog</h1>
      <p class="mt-2 text-slate-700">Каталог работает без Vue.</p>
    </div>
  `;
});

