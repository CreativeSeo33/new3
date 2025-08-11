<template>
  <div class="space-y-6">
    <!-- Title / Breadcrumbs -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Дашборд</h1>
        <p class="text-sm text-gray-500 mt-1">Сводка показателей за сегодня</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="h-9 rounded-md border border-gray-200 px-3 text-sm hover:bg-gray-50">Экспорт</button>
        <button class="h-9 rounded-md bg-blue-600 px-3 text-sm text-white hover:bg-blue-700">Создать отчет</button>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="text-sm text-gray-500">Выручка</div>
        <div class="mt-2 flex items-baseline gap-2">
          <div class="text-2xl font-semibold">₽ 1 284 500</div>
          <div class="text-xs text-green-600">+12.3%</div>
        </div>
        <div class="mt-3 h-2 w-full rounded bg-gray-100">
          <div class="h-2 w-2/3 rounded bg-blue-500"></div>
        </div>
      </div>
      <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="text-sm text-gray-500">Заказы</div>
        <div class="mt-2 flex items-baseline gap-2">
          <div class="text-2xl font-semibold">842</div>
          <div class="text-xs text-green-600">+5.1%</div>
        </div>
        <div class="mt-3 h-2 w-full rounded bg-gray-100">
          <div class="h-2 w-3/4 rounded bg-emerald-500"></div>
        </div>
      </div>
      <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="text-sm text-gray-500">Конверсия</div>
        <div class="mt-2 flex items-baseline gap-2">
          <div class="text-2xl font-semibold">3.74%</div>
          <div class="text-xs text-red-600">-0.2%</div>
        </div>
        <div class="mt-3 h-2 w-full rounded bg-gray-100">
          <div class="h-2 w-1/2 rounded bg-amber-500"></div>
        </div>
      </div>
      <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="text-sm text-gray-500">Новые пользователи</div>
        <div class="mt-2 flex items-baseline gap-2">
          <div class="text-2xl font-semibold">1 294</div>
          <div class="text-xs text-green-600">+2.8%</div>
        </div>
        <div class="mt-3 h-2 w-full rounded bg-gray-100">
          <div class="h-2 w-4/5 rounded bg-purple-500"></div>
        </div>
      </div>
    </div>

    <!-- Charts and Table -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="lg:col-span-2 rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between">
          <h2 class="text-base font-medium">Динамика продаж</h2>
          <div class="text-xs text-gray-500">Последние 30 дней</div>
        </div>
        <!-- simple fake chart -->
        <div class="mt-4 h-48 grid grid-cols-12 items-end gap-1">
          <div v-for="n in 12" :key="n" class="bg-blue-500/70 rounded-t" :style="{ height: (30 + (n % 6) * 10) + 'px' }"></div>
        </div>
      </div>
      <div class="rounded-lg border border-gray-200 bg-white p-4">
        <h2 class="text-base font-medium">Топ источники</h2>
        <ul class="mt-3 space-y-2">
          <li class="flex items-center justify-between text-sm"><span>Organic</span><span class="font-medium">54%</span></li>
          <li class="flex items-center justify-between text-sm"><span>Ads</span><span class="font-medium">28%</span></li>
          <li class="flex items-center justify-between text-sm"><span>Referral</span><span class="font-medium">12%</span></li>
          <li class="flex items-center justify-between text-sm"><span>Social</span><span class="font-medium">6%</span></li>
        </ul>
      </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
        <h2 class="text-base font-medium">Последние заказы</h2>
        <button class="text-sm text-blue-600 hover:underline">Все</button>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600">
            <tr>
              <th class="px-4 py-2 text-left font-medium">ID</th>
              <th class="px-4 py-2 text-left font-medium">Клиент</th>
              <th class="px-4 py-2 text-left font-medium">Сумма</th>
              <th class="px-4 py-2 text-left font-medium">Статус</th>
              <th class="px-4 py-2 text-right font-medium">Дата</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="border-t border-gray-100 hover:bg-gray-50">
              <td class="px-4 py-2">#{{ row.id }}</td>
              <td class="px-4 py-2">{{ row.customer }}</td>
              <td class="px-4 py-2 font-medium">₽ {{ row.total.toLocaleString('ru-RU') }}</td>
              <td class="px-4 py-2">
                <span :class="statusClass(row.status)" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs">
                  <span class="w-1.5 h-1.5 rounded-full" :class="dotClass(row.status)"></span>
                  {{ row.status }}
                </span>
              </td>
              <td class="px-4 py-2 text-right text-gray-500">{{ row.date }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

type OrderStatus = 'Оплачен' | 'В обработке' | 'Отменен';

interface OrderRow {
  id: number;
  customer: string;
  total: number;
  status: OrderStatus;
  date: string;
}

const rows = ref<OrderRow[]>([
  { id: 10234, customer: 'ООО «Альфа»', total: 32500, status: 'Оплачен', date: '2025-08-08' },
  { id: 10233, customer: 'Петров ИП', total: 128990, status: 'В обработке', date: '2025-08-08' },
  { id: 10232, customer: 'OOO «Бета»', total: 4590, status: 'Отменен', date: '2025-08-07' },
  { id: 10231, customer: 'Сидоров ИП', total: 84990, status: 'Оплачен', date: '2025-08-07' },
  { id: 10230, customer: 'ООО «Гамма»', total: 15990, status: 'В обработке', date: '2025-08-06' }
]);

const statusClass = (status: OrderStatus) => {
  switch (status) {
    case 'Оплачен':
      return 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200';
    case 'В обработке':
      return 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200';
    case 'Отменен':
      return 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200';
  }
};

const dotClass = (status: OrderStatus) => {
  switch (status) {
    case 'Оплачен':
      return 'bg-emerald-500';
    case 'В обработке':
      return 'bg-amber-500';
    case 'Отменен':
      return 'bg-rose-500';
  }
};
</script>

<style scoped>
</style>


