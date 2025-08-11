<template>
  <div class="min-h-screen bg-gray-100 p-6">
    <div class="mx-auto max-w-5xl space-y-6">
      <header class="flex items-end justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-800">Buttons</h1>
          <p class="mt-1 text-sm text-slate-500">Примеры кнопок в стиле TailAdmin (Tailwind).</p>
        </div>
      </header>

      <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Variants</h2>
        <div class="flex flex-wrap gap-3">
          <button class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            Primary
          </button>
          <button class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-4 py-2 text-white shadow-sm hover:bg-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-200">
            Dark
          </button>
          <button class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-100">
            Success
          </button>
          <button class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-white shadow-sm hover:bg-amber-600 focus:outline-none focus:ring-4 focus:ring-amber-100">
            Warning
          </button>
          <button class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-100">
            Danger
          </button>
          <button class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2 text-slate-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            Secondary
          </button>
          <button class="inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            Soft
          </button>
          <button class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-slate-700 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            Outline
          </button>
          <button class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-slate-700 hover:bg-gray-100 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            Ghost
          </button>
        </div>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Sizes</h2>
        <div class="flex flex-wrap items-end gap-3">
          <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            XS
          </button>
          <button class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            SM
          </button>
          <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            MD
          </button>
          <button class="rounded-lg bg-indigo-600 px-5 py-3 text-lg font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-100">
            LG
          </button>
        </div>
      </section>

      <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Disabled</h2>
        <div class="flex flex-wrap gap-3">
          <button disabled class="cursor-not-allowed rounded-lg bg-indigo-300 px-4 py-2 text-white">Primary (disabled)</button>
          <button disabled class="cursor-not-allowed rounded-lg border border-gray-200 bg-white px-4 py-2 text-slate-400">Secondary (disabled)</button>
        </div>
      </section>
    </div>
  </div>
  
</template>

<script setup>
</script>

<style scoped>
</style>


