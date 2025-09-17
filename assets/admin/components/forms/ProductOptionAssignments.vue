<template>
	<div class="rounded-md border p-4 dark:border-neutral-800">
		<div class="mb-3 flex items-center justify-between">
			<div class="text-sm font-medium">Опции</div>
			<Button size="sm" @click="openAddOption()">Добавить</Button>
		</div>

		<div v-if="optionCodes.length === 0" class="text-sm text-neutral-600 dark:text-neutral-300">
			Опции не выбраны. Нажмите «Добавить», чтобы выбрать опцию.
		</div>

		<div v-else class="space-y-6">
            <div v-if="!hasBasePrice && optionCodes.length" class="px-3 py-2 text-xs text-amber-700 bg-amber-50 border rounded">
                Не выбрана базовая цена вариации — выберите одну запись «Базовая цена»
            </div>
			<div v-for="opt in optionCodes" :key="opt" class="rounded-md border">
            <div class="border-b px-3 py-2 flex items-center justify-between">
                    <button type="button" class="text-sm font-medium text-left" @click="toggleCollapsed(opt)">
                        {{ optionTitle(opt) }} ({{ rowsByOption(opt).length }})
                        <span class="ml-1 text-xs text-neutral-500">{{ collapsed.has(opt) ? '— раскрыть' : '— свернуть' }}</span>
                    </button>
					<button type="button" class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700" @click="removeOption(opt)">Удалить</button>
				</div>
                <div v-if="!collapsed.has(opt)" class="p-3 space-y-3 text-sm will-change-transform">
					<div class="flex items-center justify-between">
                        <div class="text-xs text-neutral-600">Записи</div>
                        <div class="flex items-center gap-2">
                            <Button size="sm" @click="addRow(opt)">Добавить</Button>
                            <Button size="sm" variant="secondary" :disabled="!hasDelta || savingOptions" @click="handleSaveOptions">Сохранить опции</Button>
                        </div>
					</div>
					<div>
                        <div v-if="isOptionValuesLoading(opt)" class="text-neutral-500">Загрузка…</div>
						<table v-else class="w-full text-sm">
							<thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
								<tr>
									<th class="px-3 py-2 text-left w-[260px]">Значение</th>
									<th class="px-3 py-2 text-left w-28">Высота</th>
									<th class="px-3 py-2 text-left w-32">Кол-во лампочек</th>
									<th class="px-3 py-2 text-left w-40">Артикул</th>
									<th class="px-3 py-2 text-left w-40">Ориг. артикул</th>
									<th class="px-3 py-2 text-left w-28">Цена</th>
									<th class="px-3 py-2 text-left w-32">Базовая цена</th>
									<th class="px-3 py-2 text-left w-28">Цена со скидкой</th>
									<th class="px-3 py-2 text-left w-32">Площадь освещения</th>
									<th class="px-3 py-2 text-left w-24">Кол-во</th>
									<th class="px-3 py-2 text-left w-24">Сортировка</th>
									<th class="px-3 py-2 text-left w-24">Действия</th>
								</tr>
							</thead>
							<tbody>
                                <tr v-for="(row, idx) in rowsByOptionWithProxy(opt)" :key="`${opt}-${idx}`" class="border-t">
									<td class="px-3 py-2">
							<select v-model="row.value" @focus="ensureOptionValuesLoaded(opt)" @mousedown="ensureOptionValuesLoaded(opt)" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900">
											<option :value="null">—</option>
											<option v-for="ov in optionValuesFor(opt)" :key="ov.id" :value="ov['@id']">{{ ov.value }}</option>
										</select>
									</td>
									<td class="px-3 py-2">
										<input v-model="row.heightStr" type="number" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<input v-model="row.bulbsCountStr" type="number" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<input v-model="row.sku" type="text" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<input v-model="row.originalSku" type="text" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<input v-model="row.priceStr" type="number" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<input
											v-model="row.setPrice"
											type="checkbox"
											class="h-4 w-4 rounded border dark:border-neutral-800 dark:bg-neutral-900"
											:title="row.setPrice ? 'Базовая цена (только одна может быть выбрана для всего товара)' : 'Установить как базовую цену'"
										/>
									</td>
									<td class="px-3 py-2">
										<input v-model="row.salePriceStr" type="number" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<input v-model="row.lightingAreaStr" type="number" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<input v-model="row.quantityStr" type="number" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<input v-model="row.sortOrderStr" type="number" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" />
									</td>
									<td class="px-3 py-2">
										<button type="button" class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700" @click="removeRow(opt, idx)">Удалить</button>
									</td>
								</tr>
								<tr v-if="rowsByOption(opt).length === 0">
									<td colspan="12" class="px-3 py-6 text-center text-neutral-500">Нет записей</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal: Добавить опцию -->
		<DialogRoot v-model:open="addOptionOpen">
			<DialogPortal>
				<DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
				<DialogContent
					class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900"
				>
					<div class="mb-2">
						<DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Добавить опцию</DialogTitle>
					</div>

					<div class="space-y-4">
						<div v-if="optionsLoading" class="text-sm text-neutral-500">Загрузка…</div>
						<div v-else>
							<label class="mb-1 block text-sm font-medium text-foreground/80">Опция</label>
							<select v-model="selectedOptionIri" class="h-9 w-full rounded-md border bg-background px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900">
								<option disabled value="">Выберите…</option>
								<option v-for="o in optionsSorted" :key="o.id" :value="o['@id']">{{ o.name || `Опция #${o.id}` }}</option>
							</select>
						</div>

						<div class="flex justify-end gap-2 pt-2">
							<button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="addOptionOpen = false">Отмена</button>
							<button
								type="button"
								class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-neutral-50 dark:text-neutral-900 dark:hover:bg-neutral-100"
								:disabled="!selectedOptionIri"
								@click="confirmAddOption"
							>
								Добавить
							</button>
						</div>
					</div>
				</DialogContent>
			</DialogPortal>
		</DialogRoot>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import Button from '@admin/ui/components/Button.vue'
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import type { ProductOptionValueAssignment } from '@admin/types/product'
import type { Option } from '@admin/repositories/OptionRepository'
import { useOptionAssignments } from '@admin/composables/useOptionAssignments'

const props = defineProps<{ optionAssignments: ProductOptionValueAssignment[] | null; prefetchedOptions?: Option[] | null; productId?: string }>()
const emit = defineEmits<{ 'update:optionAssignments': [val: ProductOptionValueAssignment[] | null], toast: [message: string] }>()

// Используем ref для локального состояния
const addOptionOpen = ref(false)
const selectedOptionIri = ref<string>('')

const {
  rows,
  optionCodes,
  optionsSorted,
  optionsLoading,
  collapsed,
  hasBasePrice,
  hasDelta,
  optionTitle,
  optionValuesFor,
  isOptionValuesLoading,
  ensureOptionValuesLoaded,
  ensureOption,
  addRow,
  removeRow,
  rowsByOption,
  toggleCollapsed,
  setBasePriceForRow,
  emitRowsUpdate,
  loadOptions,
  saveDelta,
} = useOptionAssignments({
  initial: computed(() => props.optionAssignments),
  prefetchedOptions: computed(() => props.prefetchedOptions || null),
  onUpdate: (val) => emit('update:optionAssignments', val),
})

function openAddOption() { addOptionOpen.value = true; void loadOptions() }

// Инициализация из пропсов, если список передан
// инициализация списка опций по требованию
function confirmAddOption() {
    addOptionOpen.value = false
    if (selectedOptionIri.value) { ensureOption(selectedOptionIri.value); emit('toast', 'Опция добавлена') }
    selectedOptionIri.value = ''
}
function removeOption(optionIri: string) {
  rows.value = rows.value.filter(r => r.option !== optionIri)
  emitRowsUpdate()
}
function rowsByOptionWithProxy(optionIri: string) {
    return rowsByOption(optionIri).map(r => withProxies(r))
}
// используем removeRow/addRow из composable напрямую в шаблоне

const savingOptions = ref(false)
async function handleSaveOptions() {
  if (!props.productId || props.productId === 'new') {
    emit('toast', 'Сначала сохраните товар')
    return
  }
  if (!hasDelta) return
  savingOptions.value = true
  const res = await saveDelta(props.productId)
  savingOptions.value = false
  emit('toast', res.success ? 'Опции сохранены' : (res.message || 'Ошибка сохранения опций'))
}


type RowProxy = ProductOptionValueAssignment & { heightStr: string; bulbsCountStr: string; priceStr: string; setPrice: boolean | null; salePriceStr: string; lightingAreaStr: string; quantityStr: string; sortOrderStr: string }
const proxyCache = new WeakMap<ProductOptionValueAssignment, RowProxy>()
function normalizeSetPrice(value: any): boolean {
    if (value === null || value === undefined) return false
    if (typeof value === 'boolean') return value
    if (typeof value === 'number') return value === 1
    if (typeof value === 'string') return value === '1' || value.toLowerCase() === 'true'
    return Boolean(value)
}
function withProxies(row: ProductOptionValueAssignment): RowProxy {
    const cached = proxyCache.get(row)
    if (cached) return cached
    const normalize = (n: number | null | undefined): string => (n == null ? '' : String(n))
    const proxy: any = {}
    // pass-through fields used in template with v-model
    Object.defineProperties(proxy, {
        value: {
            get() { return row.value },
            set(v: string | null) { row.value = v; emitRowsUpdate() },
            enumerable: true,
        },
        sku: {
            get() { return row.sku as any },
            set(v: string | null) { row.sku = v; emitRowsUpdate() },
            enumerable: true,
        },
        originalSku: {
            get() { return (row as any).originalSku as any },
            set(v: string | null) { (row as any).originalSku = v; emitRowsUpdate() },
            enumerable: true,
        },
        option: {
            get() { return row.option },
            set(v: string) { row.option = v; emitRowsUpdate() },
            enumerable: true,
        },
        heightStr: {
            get() { return normalize(row.height as any) },
            set(v: string) { row.height = v === '' ? null : Number(v); emitRowsUpdate() },
            enumerable: true,
        },
        bulbsCountStr: {
            get() { return normalize(row.bulbsCount as any) },
            set(v: string) { row.bulbsCount = v === '' ? null : Number(v); emitRowsUpdate() },
            enumerable: true,
        },
        priceStr: {
            get() { return normalize(row.price as any) },
            set(v: string) { row.price = v === '' ? null : Number(v); emitRowsUpdate() },
            enumerable: true,
        },
        setPrice: {
            get() { return normalizeSetPrice((row as any).setPrice) },
            set(v: boolean | null) { setBasePriceForRow(row, v) },
            enumerable: true,
        },
        salePriceStr: {
            get() { return normalize((row as any).salePrice as any) },
            set(v: string) { (row as any).salePrice = v === '' ? null : Number(v); emitRowsUpdate() },
            enumerable: true,
        },
        lightingAreaStr: {
            get() { return normalize(row.lightingArea as any) },
            set(v: string) { row.lightingArea = v === '' ? null : Number(v); emitRowsUpdate() },
            enumerable: true,
        },
        quantityStr: {
            get() { return normalize(row.quantity as any) },
            set(v: string) { row.quantity = v === '' ? null : Number(v); emitRowsUpdate() },
            enumerable: true,
        },
        sortOrderStr: {
            get() { return normalize((row as any).sortOrder as any) },
            set(v: string) { (row as any).sortOrder = v === '' ? null : Number(v); emitRowsUpdate() },
            enumerable: true,
        },
    })
    const proxied = proxy as RowProxy
    proxyCache.set(row, proxied)
    return proxied
}
</script>

<style scoped></style>


