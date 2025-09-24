<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div class="text-sm text-neutral-600 dark:text-neutral-300">Добавляйте изображения из каталога /public/img</div>
      <DialogRoot v-model:open="photosModalOpen">
        <DialogTrigger as-child>
          <Button variant="secondary">Добавить изображение</Button>
        </DialogTrigger>
        <DialogOverlay class="fixed inset-0 bg-black/40 z-[9998]" />
        <DialogContent class="fixed left-1/2 top-1/2 w-[90vw] max-w-5xl min-h-[70vh] -translate-x-1/2 -translate-y-1/2 rounded-md bg-white p-0 shadow-lg focus:outline-none z-[9999]">
          <div class="flex items-center justify-between border-b px-4 py-3">
            <DialogTitle class="text-base font-medium">Библиотека изображений</DialogTitle>
            <DialogClose as-child>
              <button class="text-neutral-500 hover:text-neutral-700">✕</button>
            </DialogClose>
          </div>
          <div class="grid grid-cols-12 gap-0">
            <div class="col-span-4 border-r max-h-[70vh] overflow-y-auto">
              <div class="px-3 py-2 cursor-pointer hover:bg-neutral-50" :class="{ 'bg-neutral-100': selectedFolderPath === '' }" @click="selectFolder('')">
                /img
              </div>
              <div v-for="f in flattenedFolders" :key="f.path" class="py-1 cursor-pointer hover:bg-neutral-50" :style="{ paddingLeft: (12 + f.depth * 16) + 'px' }" :class="{ 'bg-neutral-100': selectedFolderPath === f.path }" @click="selectFolder(f.path)">
                <span class="px-3">{{ f.name }}</span>
              </div>
            </div>
            <div class="col-span-8 max-h-[70vh] overflow-y-auto p-4">
              <div class="flex items-center justify-between mb-3">
                <div></div>
                <div v-if="selectedImages.size > 0">
                  <Button variant="secondary" @click="attachSelected" :disabled="attachLoading">
                    Выбрано: {{ selectedImages.size }}
                  </Button>
                </div>
              </div>
              <div v-if="imagesLoading" class="relative h-32">
                <Spinner />
              </div>
              <div v-else-if="imagesError" class="text-sm text-red-600">{{ imagesError }}</div>
              <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <label v-for="img in folderImages" :key="img.relative" class="group cursor-pointer block">
                  <div class="relative">
                    <input type="checkbox" class="absolute left-2 top-2 z-10 h-4 w-4" :checked="selectedImages.has(img.relative)" @change="toggleImage(img.relative, ($event.target as HTMLInputElement).checked)" />
                    <div class="aspect-square w-full overflow-hidden rounded border bg-neutral-50">
                      <img :src="img.url" :alt="img.name" class="h-full w-full object-cover" loading="lazy" />
                    </div>
                  </div>
                  <div class="mt-1 truncate text-xs text-neutral-600">{{ img.name }}</div>
                </label>
              </div>
            </div>
          </div>
        </DialogContent>
      </DialogRoot>
    </div>

    <div class="mb-2 flex items-center justify-between">
      <div class="text-sm font-medium">Текущие фото товара</div>
      <div v-if="!isCreating && productImages.length">
        <AlertDialogRoot v-model:open="bulkDeleteDialogOpen">
          <AlertDialogTrigger as-child>
            <button
              class="px-3 py-1.5 text-xs rounded border border-red-300 text-red-600 hover:bg-red-50 disabled:opacity-50"
              :disabled="productImagesLoading || bulkDeleting || !productImages.length"
            >Удалить все фото</button>
          </AlertDialogTrigger>
          <AlertDialogOverlay class="fixed inset-0 bg-black/40 z-[9998]" />
          <AlertDialogContent class="fixed left-1/2 top-1/2 w-[90vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md bg-white p-0 shadow-lg focus:outline-none z-[9999]">
            <div class="px-4 py-3 border-b">
              <AlertDialogTitle class="text-base font-medium">Удалить все фото?</AlertDialogTitle>
            </div>
            <div class="p-4 text-sm text-neutral-700">
              Будут удалены {{ productImages.length }} фото. Действие необратимо.
            </div>
            <div class="flex items-center justify-end gap-2 px-4 py-3 border-t">
              <AlertDialogCancel as-child>
                <button class="px-3 py-1.5 text-sm rounded border">Отмена</button>
              </AlertDialogCancel>
              <AlertDialogAction as-child>
                <button
                  class="px-3 py-1.5 text-sm rounded bg-red-600 text-white hover:bg-red-700 disabled:opacity-50"
                  :disabled="bulkDeleting"
                  @click="performBulkDelete"
                >Удалить</button>
              </AlertDialogAction>
            </div>
          </AlertDialogContent>
        </AlertDialogRoot>
      </div>
    </div>
    <div v-if="isCreating">
      <div class="text-sm text-neutral-600">
        Вы можете выбрать изображения сейчас — они будут автоматически добавлены после первого сохранения товара.
      </div>
      <div v-if="pendingRelatives.length" class="mt-3">
        <div class="mb-2 text-sm font-medium">Ожидают добавления ({{ pendingRelatives.length }})</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
          <div v-for="rel in pendingRelatives" :key="rel" class="rounded border overflow-hidden">
            <div class="aspect-square bg-neutral-50 select-none">
              <img :src="liipResolve(rel)" class="h-full w-full object-cover pointer-events-none" loading="lazy" />
            </div>
            <div class="px-2 py-1 text-xs text-neutral-600 truncate">{{ rel }}</div>
          </div>
        </div>
      </div>
    </div>
    <div v-else>
      <div v-if="productImagesLoading" class="relative h-32">
        <Spinner />
      </div>
      <div v-else-if="productImagesError" class="text-sm text-red-600">{{ productImagesError }}</div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
        <div
          v-for="(pi, idx) in productImages"
          :key="pi.id"
          class="rounded border overflow-hidden relative group"
          :class="{ 'ring-2 ring-blue-500': dragOverIndex === idx }"
          @dragover.prevent="onDragOver(idx)"
          @dragleave="onDragLeave(idx)"
          @drop.prevent="onDrop(idx)"
        >
          <button
            class="absolute left-2 top-2 z-10 rounded bg-white/90 px-2 py-1 text-xs text-neutral-700 shadow hover:bg-white cursor-grab"
            title="Перетащить для сортировки"
            draggable="true"
            @dragstart="onDragStart(idx, $event)"
          >⠿</button>
          <button
            class="absolute right-2 top-2 z-10 rounded bg-white/90 px-2 py-1 text-xs text-red-600 shadow hover:bg-white"
            @click="deleteProductImage(pi.id)"
            title="Удалить"
          >Удалить</button>
          <div class="aspect-square bg-neutral-50 select-none">
            <img :src="pi.imageUrl" class="h-full w-full object-cover pointer-events-none" loading="lazy" />
          </div>
          <div class="px-2 py-1 text-xs text-neutral-600">#{{ pi.id }} · {{ pi.sortOrder }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import Button from '@admin/ui/components/Button.vue'
import Spinner from '@admin/ui/components/Spinner.vue'
import { DialogClose, DialogContent, DialogOverlay, DialogRoot, DialogTitle, DialogTrigger } from 'reka-ui'
import { AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogOverlay, AlertDialogRoot, AlertDialogTitle, AlertDialogTrigger } from 'reka-ui'
import { MediaRepository } from '@admin/repositories/MediaRepository'
import { httpClient } from '@admin/services/http'
const mediaRepo = new MediaRepository()

const props = defineProps<{ productId: string; isCreating: boolean; initialPhotos?: Array<{ id: number; imageUrl: string; sortOrder: number }> }>()
const emit = defineEmits<{ (e: 'toast', message: string): void }>()
const publishToast = (m: string) => emit('toast', m)

// Modal + folder tree
const photosModalOpen = ref(false)
type FolderNode = { name: string; path: string; children?: FolderNode[] }
type FlatFolder = { name: string; path: string; depth: number }
const folderTree = ref<FolderNode[]>([])
const flattenedFolders = computed<FlatFolder[]>(() => {
  const acc: FlatFolder[] = []
  const walk = (nodes: FolderNode[], depth: number) => {
    for (const n of nodes) {
      acc.push({ name: n.name, path: n.path, depth })
      if (n.children && n.children.length) walk(n.children, depth + 1)
    }
  }
  walk(folderTree.value, 0)
  return acc
})
const selectedFolderPath = ref<string>('')
const folderImages = ref<Array<{ name: string; relative: string; url: string }>>([])
const imagesLoading = ref(false)
const imagesError = ref<string>('')
const selectedImages = ref<Set<string>>(new Set())
const attachLoading = ref(false)

// Построение URL LiipImagine resolver для предпросмотра (md2)
function liipResolve(relative: string, filter: string = 'md2'): string {
  const clean = String(relative || '')
    .replace(/\\+/g, '/')              // windows path → unix
    .replace(/\.{2,}(?:[\\/]|$)/g, '') // убрать только '..' сегменты
    .replace(/^\/+|\/+$/g, '')          // обрезать ведущие/хвостовые '/'
  return `/media/cache/resolve/${filter}/img/${clean}`
}

// Pending images storage for a new product
const PENDING_KEY = 'new-product-pending-images'
const pendingRelatives = ref<string[]>([])
function readPendingFromStorage() {
  try {
    const raw = localStorage.getItem(PENDING_KEY)
    const arr = raw ? JSON.parse(raw) : []
    pendingRelatives.value = Array.isArray(arr) ? arr.filter((v: any) => typeof v === 'string') : []
  } catch {
    pendingRelatives.value = []
  }
}
readPendingFromStorage()

async function fetchFolderTree() {
  const data = await mediaRepo.fetchFolderTree()
  folderTree.value = Array.isArray((data as any).tree) ? (data as any).tree : []
}

async function fetchImages(dir: string) {
  imagesLoading.value = true
  imagesError.value = ''
  try {
    const data = await mediaRepo.fetchImages(dir)
    folderImages.value = Array.isArray((data as any).items) ? (data as any).items : []
  } catch (e: any) {
    imagesError.value = e?.message || 'Ошибка'
  } finally {
    imagesLoading.value = false
  }
}

function selectFolder(path: string) {
  selectedFolderPath.value = path
  fetchImages(path)
}

function toggleImage(relative: string, checked: boolean) {
  const set = new Set(selectedImages.value)
  if (checked) set.add(relative)
  else set.delete(relative)
  selectedImages.value = set
}

watch(photosModalOpen, async (open) => {
  if (open) {
    try {
      if (folderTree.value.length === 0) await fetchFolderTree()
      await fetchImages(selectedFolderPath.value)
    } catch {
      /* handled in UI */
    }
  }
})

// Product images list
type ProductImageDto = { id: number; imageUrl: string; sortOrder: number }
const productImages = ref<ProductImageDto[]>([])
const productImagesLoading = ref(false)
const productImagesError = ref<string>('')
const deletingImageIds = ref<Set<number>>(new Set())
const dragFromIndex = ref<number | null>(null)
const dragOverIndex = ref<number | null>(null)
const savingOrder = ref(false)
const bulkDeleteDialogOpen = ref(false)
const bulkDeleting = ref(false)
const hasDuplicateSortOrders = computed(() => {
  const seen = new Set<number>()
  for (const it of productImages.value) {
    const so = Number(it.sortOrder)
    if (!Number.isFinite(so)) continue
    if (seen.has(so)) return true
    seen.add(so)
  }
  return false
})

async function fetchProductImages() {
  if (props.isCreating) return
  productImagesLoading.value = true
  productImagesError.value = ''
  try {
    // Предпочитаем initialPhotos, но если их нет, можно добавить лёгкий эндпоинт только для фото.
    const { data } = await httpClient.getJson<any>(`/admin/products/${props.productId}/form`)
    const imgs = Array.isArray((data as any)?.photos) ? (data as any).photos : []
    const normalized: ProductImageDto[] = imgs
      .map((it: any): ProductImageDto => ({ id: Number(it.id), imageUrl: String(it.imageUrl), sortOrder: Number(it.sortOrder ?? 0) }))
      .sort((a: ProductImageDto, b: ProductImageDto) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0) || a.id - b.id)
    // авто-нормализация дублей: если есть повторы, пересчитываем и отправляем на сервер
    const seen: Set<number> = new Set<number>()
    let hasDup = false
    for (const it of normalized) {
      const so = Number(it.sortOrder)
      if (Number.isFinite(so) && seen.has(so)) { hasDup = true; break }
      seen.add(so)
    }
    if (hasDup) {
      normalized.forEach((it: ProductImageDto, i: number) => (it.sortOrder = i + 1))
      productImages.value = normalized
      // fire-and-forget, но дождёмся чтобы порядок в UI совпал
      await saveImagesOrder(normalized.map((it: ProductImageDto) => it.id))
    } else {
      productImages.value = normalized
    }
  } catch (e: any) {
    productImagesError.value = e?.message || 'Ошибка'
  } finally {
    productImagesLoading.value = false
  }
}

async function attachPendingIfAny() {
  readPendingFromStorage()
  if (!pendingRelatives.value.length) return
  try {
    await mediaRepo.attachProductImages(props.productId, pendingRelatives.value)
    try { localStorage.removeItem(PENDING_KEY) } catch {}
    pendingRelatives.value = []
    // сразу обновим список фото товара
    await fetchProductImages()
    publishToast('Изображения добавлены')
  } catch {}
}

onMounted(async () => {
  if (!props.isCreating) {
    // Инициализируемся данными из родителя, если есть
    if (Array.isArray(props.initialPhotos) && props.initialPhotos.length) {
      productImages.value = props.initialPhotos.slice().sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0) || a.id - b.id)
    } else {
      await fetchProductImages()
    }
    await attachPendingIfAny()
  }
})
// Реакция на приход initialPhotos после монтирования
watch(() => props.initialPhotos, (list) => {
  if (!props.isCreating) {
    const arr = Array.isArray(list) ? list : []
    if (arr.length) {
      productImages.value = arr.slice().sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0) || a.id - b.id)
    }
  }
})
watch(() => props.productId, async () => {
  if (!props.isCreating) {
    if (Array.isArray(props.initialPhotos) && props.initialPhotos.length) {
      productImages.value = props.initialPhotos.slice().sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0) || a.id - b.id)
    } else {
      await fetchProductImages()
    }
    await attachPendingIfAny()
  }
})

// При открытии компонента для нового товара — убедимся, что отложенный список чист
watch(() => props.isCreating, (creating) => {
  if (creating) {
    try { localStorage.removeItem(PENDING_KEY) } catch {}
    pendingRelatives.value = []
  }
}, { immediate: true })

async function attachSelected() {
  if (selectedImages.value.size === 0) return
  if (props.isCreating) {
    // Accumulate selections until the product is saved first time
    const existing = new Set<string>(pendingRelatives.value)
    for (const r of selectedImages.value) existing.add(r)
    const next = Array.from(existing)
    try { localStorage.setItem(PENDING_KEY, JSON.stringify(next)) } catch {}
    pendingRelatives.value = next
    publishToast('Изображения будут добавлены после сохранения товара')
    selectedImages.value = new Set()
    photosModalOpen.value = false
    return
  }
  attachLoading.value = true
  try {
    await mediaRepo.attachProductImages(props.productId, Array.from(selectedImages.value))
    await fetchProductImages()
    publishToast('Изображения добавлены')
    selectedImages.value = new Set()
    photosModalOpen.value = false
  } catch (e: any) {
    publishToast(e?.message || 'Ошибка')
  } finally {
    attachLoading.value = false
  }
}

async function deleteProductImage(imageId: number) {
  if (!imageId) return
  const set = new Set(deletingImageIds.value); set.add(imageId); deletingImageIds.value = set
  try {
    await mediaRepo.deleteProductImage(imageId)
    // Локально обновим список без лишнего GET
    const next = productImages.value.filter(it => it.id !== imageId)
    next.forEach((it, i) => (it.sortOrder = i + 1))
    productImages.value = next
    publishToast('Изображение удалено')
  } catch (e: any) {
    publishToast(e?.message || 'Ошибка удаления')
  } finally {
    const set2 = new Set(deletingImageIds.value); set2.delete(imageId); deletingImageIds.value = set2
  }
}

function onDragStart(index: number, e: DragEvent) {
  dragFromIndex.value = index
  dragOverIndex.value = index
  try {
    e.dataTransfer?.setData('text/plain', String(index))
    e.dataTransfer?.setDragImage?.(new Image(), 0, 0)
  } catch {}
}

function onDragOver(index: number) {
  dragOverIndex.value = index
}

function onDragLeave(index: number) {
  if (dragOverIndex.value === index) dragOverIndex.value = null
}

function reorderArray<T>(arr: T[], from: number, to: number): T[] {
  const copy = arr.slice()
  const [moved] = copy.splice(from, 1)
  copy.splice(to, 0, moved)
  return copy
}

async function onDrop(index: number) {
  const from = dragFromIndex.value
  dragFromIndex.value = null
  dragOverIndex.value = null
  if (from === null || from === index) return
  const next = reorderArray(productImages.value, from, index)
  // локально проставим новые sortOrder с 1
  next.forEach((it, i) => (it.sortOrder = i + 1))
  productImages.value = next
  // отправим порядок на сервер
  await saveImagesOrder(next.map(it => it.id))
}

async function saveImagesOrder(orderIds: number[]) {
  if (props.isCreating || !orderIds.length) return
  if (savingOrder.value) return
  savingOrder.value = true
  try {
    const data = await mediaRepo.reorderProductImages(props.productId, orderIds)
    if (Array.isArray((data as any).items)) {
      // синхронизируем sortOrder из ответа, если пришло
      const byId = new Map<number, number>()
      for (const it of (data as any).items) {
        const id = Number(it.id)
        const so = Number(it.sortOrder)
        if (!Number.isNaN(id) && !Number.isNaN(so)) byId.set(id, so)
      }
      productImages.value = productImages.value.map(it => ({ ...it, sortOrder: byId.get(it.id) ?? it.sortOrder }))
    }
  } catch (e: any) {
    publishToast(e?.message || 'Ошибка сохранения порядка')
    // откат к серверному
    await fetchProductImages()
  } finally {
    savingOrder.value = false
  }
}

async function normalizeOrder() {
  // нормализуем локально и отправим на сервер
  const sortedByCurrent = productImages.value.slice().sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0) || a.id - b.id)
  sortedByCurrent.forEach((it, i) => (it.sortOrder = i + 1))
  productImages.value = sortedByCurrent
  await saveImagesOrder(sortedByCurrent.map(it => it.id))
}

async function deleteAllProductImages() {
  if (props.isCreating) return
  const ids = productImages.value.map(it => it.id)
  if (!ids.length) return
  bulkDeleting.value = true
  const set = new Set(deletingImageIds.value)
  for (const id of ids) set.add(id)
  deletingImageIds.value = set
  try {
    const results = await Promise.allSettled(ids.map(async (id) => mediaRepo.deleteProductImage(id)))
    const failed = results.filter(r => r.status === 'rejected').length
    await fetchProductImages()
    if (failed === 0) {
      publishToast(`Удалены все изображения (${ids.length})`)
    } else if (failed === ids.length) {
      publishToast('Не удалось удалить изображения')
    } else {
      publishToast(`Удалены не все изображения: ошибок ${failed}`)
    }
  } catch (e: any) {
    publishToast(e?.message || 'Ошибка пакетного удаления')
    await fetchProductImages()
  } finally {
    deletingImageIds.value = new Set()
    bulkDeleting.value = false
  }
}

async function performBulkDelete() {
  bulkDeleteDialogOpen.value = false
  await deleteAllProductImages()
}
</script>

<style scoped></style>


