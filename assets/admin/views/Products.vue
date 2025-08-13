<template>
  <div class="space-y-6">
    <!-- Header / Actions -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">Products List</h1>
        <p class="mt-1 text-sm text-neutral-500">Track your store's progress to boost your sales.</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-md border px-3 text-sm shadow-sm hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
        >
          Export
        </button>
        <RouterLink
          :to="{ name: 'admin-product-form', params: { id: 'new' } }"
          class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
        >
          Add Product
        </RouterLink>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-md border bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900/40">
      <div class="flex flex-wrap items-end gap-3">
        <div class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Filter</div>
        <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
          <label class="block text-sm">
            <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Category</span>
            <select v-model="filters.category" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900">
              <option value="">All</option>
              <option value="electronics">Electronics</option>
              <option value="fashion">Fashion</option>
            </select>
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Company</span>
            <select v-model="filters.company" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900">
              <option value="">All</option>
              <option value="apple">Apple</option>
              <option value="samsung">Samsung</option>
            </select>
          </label>
          <div class="flex items-end gap-2">
            <button
              type="button"
              class="h-9 rounded-md border px-3 text-sm hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
              @click="resetFilters"
            >
              Reset
            </button>
            <button
              type="button"
              class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
              @click="applyFilters"
            >
              Apply
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-md border dark:border-neutral-800">
      <table class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
          <tr>
            <th class="px-4 py-2 text-left w-16">#</th>
            <th class="px-4 py-2 text-left">Product</th>
            <th class="px-4 py-2 text-left">Brand</th>
            <th class="px-4 py-2 text-left">Price</th>
            <th class="px-4 py-2 text-left">Stock</th>
            <th class="px-4 py-2 text-left">Status</th>
            <th class="px-4 py-2 text-left w-40">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.id" class="border-t dark:border-neutral-800">
            <td class="px-4 py-2 text-neutral-500">{{ p.id }}</td>
            <td class="px-4 py-2">
              <RouterLink
                :to="{ name: 'admin-product-form', params: { id: p.id } }"
                class="text-neutral-900 underline-offset-2 hover:underline dark:text-neutral-100"
              >
                {{ p.name || 'Без названия' }}
              </RouterLink>
              <div v-if="p.slug" class="text-xs text-neutral-500">/{{ p.slug }}</div>
            </td>
            <td class="px-4 py-2">{{ p.manufacturerName || '—' }}</td>
            <td class="px-4 py-2">
              <div class="flex items-center gap-1">
                <span class="font-medium">{{ p.salePrice ?? p.price ?? 0 }}</span>
                <span v-if="p.salePrice && p.price" class="text-xs text-neutral-500 line-through">{{ p.price }}</span>
              </div>
            </td>
            <td class="px-4 py-2">{{ p.quantity ?? 0 }}</td>
            <td class="px-4 py-2">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs',
                  p.status ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                ]"
              >
                {{ p.status ? 'Active' : 'Disabled' }}
              </span>
            </td>
            <td class="px-4 py-2">
              <div class="flex items-center gap-2">
                <RouterLink
                  :to="{ name: 'admin-product-form', params: { id: p.id } }"
                  class="inline-flex h-8 items-center rounded-md border px-2 text-xs hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
                >
                  View More
                </RouterLink>
                <button
                  type="button"
                  class="inline-flex h-8 items-center rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700"
                  @click="confirmDelete(p)"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && products.length === 0">
            <td colspan="7" class="px-4 py-8 text-center text-neutral-500">Пока нет товаров</td>
          </tr>
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-neutral-500">Загрузка…</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer / Pagination (simple) -->
    <div class="flex flex-col items-center justify-between gap-2 sm:flex-row">
      <div class="text-sm text-neutral-500">
        Showing <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ products.length }}</span>
        of <span class="font-medium text-neutral-700 dark:text-neutral-300">{{ totalItems }}</span>
      </div>
      <div class="flex items-center gap-2">
        <button class="h-8 rounded border px-2 text-xs disabled:opacity-50" :disabled="!hasPrevPage" @click="prevPage">Prev</button>
        <div class="text-xs">Page {{ page }} / {{ totalPages }}</div>
        <button class="h-8 rounded border px-2 text-xs disabled:opacity-50" :disabled="!hasNextPage" @click="nextPage">Next</button>
      </div>
    </div>

    <!-- Toasts -->
    <ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="3000">
      <ToastDescription>{{ lastToastMessage }}</ToastDescription>
    </ToastRoot>

    <!-- Delete confirmation -->
    <ConfirmDialog v-model="deleteDialogOpen" title="Удалить товар?" description="Это действие необратимо. Товар будет удалён навсегда." confirm-text="Удалить" :danger="true" @confirm="performDelete" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive } from 'vue'
import { RouterLink } from 'vue-router'
import { ToastDescription, ToastRoot } from 'reka-ui'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import { useCrud } from '@admin/composables/useCrud'
import { ProductRepository, type ProductDto } from '@admin/repositories/ProductRepository'

// Data fetching via repository + useCrud
const productRepository = new ProductRepository()
const crud = useCrud<ProductDto>(productRepository)
const crudState = crud.state

const products = computed<ProductDto[]>(() => (crudState.items ?? []) as ProductDto[])
const loading = computed(() => !!crudState.loading)
const totalItems = computed(() => crudState.totalItems ?? products.value.length)
const page = computed(() => crudState.pagination.page)
const totalPages = computed(() => crudState.pagination.totalPages)
const hasNextPage = computed(() => page.value < totalPages.value)
const hasPrevPage = computed(() => page.value > 1)

onMounted(async () => {
  await crud.fetchAll({ itemsPerPage: 20 })
})

function nextPage() {
  crud.nextPage()
}
function prevPage() {
  crud.prevPage()
}

// Filters (stub)
const filters = reactive<{ category: string; company: string }>({ category: '', company: '' })
function resetFilters() {
  filters.category = ''
  filters.company = ''
}
async function applyFilters() {
  // TODO: when backend supports filters, map to query
  await crud.fetchAll({ itemsPerPage: 20 })
}

// Delete with confirmation + toast
import { ref as vueRef } from 'vue'
import { AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogOverlay, AlertDialogPortal, AlertDialogRoot, AlertDialogTitle, AlertDialogTrigger } from 'reka-ui'
const deleteDialogOpen = vueRef(false)
const pendingDeleteId = vueRef<number | null>(null)

async function confirmDelete(p: ProductDto) {
  if (!p.id) return
  pendingDeleteId.value = Number(p.id)
  deleteDialogOpen.value = true
}
async function performDelete() {
  if (pendingDeleteId.value == null) return
  await crud.remove(pendingDeleteId.value)
  pendingDeleteId.value = null
  publishToast('Товар удалён')
}

// Imperative toast publisher (per Reka UI)
const toastCount = vueRef(0)
const lastToastMessage = vueRef('')
function publishToast(message: string) {
  lastToastMessage.value = message
  toastCount.value++
}
</script>

<style scoped></style>

