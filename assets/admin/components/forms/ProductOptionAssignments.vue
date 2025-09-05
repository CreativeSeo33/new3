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
			<div v-for="opt in optionCodes" :key="opt" class="rounded-md border">
				<div class="border-b px-3 py-2 flex items-center justify-between">
					<div class="text-sm font-medium">{{ optionTitle(opt) }}</div>
					<button type="button" class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700" @click="removeOption(opt)">Удалить</button>
				</div>
				<div class="p-3 space-y-3 text-sm">
					<div class="flex items-center justify-between">
						<div class="text-xs text-neutral-600">Записи</div>
						<Button size="sm" @click="addRow(opt)">Добавить</Button>
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
								<tr v-for="(row, idx) in rowsByOption(opt)" :key="`${opt}-${idx}`" class="border-t">
									<td class="px-3 py-2">
										<select v-model="row.value" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900">
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
import { computed, ref, watch } from 'vue'
import Button from '@admin/ui/components/Button.vue'
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { OptionRepository, type Option } from '@admin/repositories/OptionRepository'
import { OptionValueRepository, type OptionValue } from '@admin/repositories/OptionValueRepository'
import type { ProductOptionValueAssignment } from '@admin/types/product'

const props = defineProps<{ optionAssignments: ProductOptionValueAssignment[] | null }>()
const emit = defineEmits<{ 'update:optionAssignments': [val: ProductOptionValueAssignment[] | null], toast: [message: string] }>()

// Используем ref для локального состояния
const rows = ref<ProductOptionValueAssignment[]>([])

// Синхронизируем с props при изменении
watch(() => props.optionAssignments, (newAssignments) => {

    if (newAssignments) {
        const processedRows = newAssignments.map((row, index) => {
            const normalized = normalizeSetPrice(row.setPrice)
            return {
                ...row,
                setPrice: normalized
            }
        })


        rows.value = processedRows
    } else {
        rows.value = []
    }
}, { immediate: true })

// Функция для надежной нормализации setPrice
function normalizeSetPrice(value: any): boolean {
    if (value === null || value === undefined) return false
    if (typeof value === 'boolean') return value
    if (typeof value === 'number') return value === 1
    if (typeof value === 'string') return value === '1' || value.toLowerCase() === 'true'
    return Boolean(value)
}

// Эмитим изменения при обновлении rows
watch(rows, (newRows) => {
    emit('update:optionAssignments', newRows)
}, { deep: true })

const optionCodes = computed<string[]>(() => {
    const set = new Set<string>()
    for (const r of rows.value) if (r.option) set.add(r.option)
    const arr = Array.from(set.values())
    const getOrder = (iri: string): number => {
        const found = optionsSorted.value.find(o => (o as any)['@id'] === iri) as any
        const so = found?.sortOrder
        return typeof so === 'number' ? so : Number.POSITIVE_INFINITY
    }
    const getName = (iri: string): string => {
        const found = optionsSorted.value.find(o => (o as any)['@id'] === iri) as any
        return String(found?.name || optionNameByIri.get(iri) || iri.split('/').pop() || '')
    }
    arr.sort((a, b) => {
        const ao = getOrder(a), bo = getOrder(b)
        if (ao !== bo) return ao - bo
        return getName(a).localeCompare(getName(b))
    })
    return arr
})

const addOptionOpen = ref(false)
const selectedOptionIri = ref<string>('')
const optionRepo = new OptionRepository()
const optionValueRepo = new OptionValueRepository()
const optionsList = ref<Option[]>([] as any)
const optionsLoading = ref(false)
const optionsSorted = computed(() => {
    const list = (optionsList.value || ([] as any)).slice()
    list.sort((a: any, b: any) => {
        const ao = (a as any).sortOrder ?? null
        const bo = (b as any).sortOrder ?? null
        if (ao != null && bo != null) return ao - bo
        if (ao != null) return -1
        if (bo != null) return 1
        return String(a.name || '').localeCompare(String(b.name || ''))
    })
    return list
})

// Cache для заголовков по IRI
const optionNameByIri = new Map<string, string>()
const optionMetaLoading = ref<Set<string>>(new Set())
async function ensureOptionMeta(optionIri: string) {
    if (!optionIri || optionNameByIri.has(optionIri) || optionMetaLoading.value.has(optionIri)) return
    const next = new Set(optionMetaLoading.value); next.add(optionIri); optionMetaLoading.value = next
    try {
        const inList = optionsSorted.value.find(o => (o as any)['@id'] === optionIri)
        if (inList && (inList as any).name) {
            optionNameByIri.set(optionIri, String((inList as any).name))
            return
        }
        const id = Number(String(optionIri).split('/').pop())
        if (Number.isFinite(id) && id > 0) {
            const dto: any = await optionRepo.findById(id)
            const name = (dto?.name ?? null) as any
            if (name) optionNameByIri.set(optionIri, String(name))
        }
    } finally {
        const n2 = new Set(optionMetaLoading.value); n2.delete(optionIri); optionMetaLoading.value = n2
    }
}

function openAddOption() {
    addOptionOpen.value = true
    if (optionsList.value.length === 0) void loadOptions()
}
async function loadOptions() {
    optionsLoading.value = true
    try {
        const data = await optionRepo.findAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', name: 'asc' } }) as any
        const collection = (data['hydra:member'] ?? data.member ?? []) as any
        optionsList.value = collection as any
    } finally {
        optionsLoading.value = false
    }
}
function confirmAddOption() {
    addOptionOpen.value = false
    if (selectedOptionIri.value) {
        ensureOption(selectedOptionIri.value)
        emit('toast', 'Опция добавлена')
    }
    selectedOptionIri.value = ''
}

// Values cache per option
const optionIdToValues = new Map<string, OptionValue[]>()
const optionValuesLoading = ref<Set<string>>(new Set())
function isOptionValuesLoading(optionIri: string): boolean { return optionValuesLoading.value.has(optionIri) }
function optionTitle(optionIri: string): string {
    const cached = optionNameByIri.get(optionIri)
    if (cached) return cached
    const found = optionsSorted.value.find(o => (o as any)['@id'] === optionIri)
    if (found?.name) {
        const name = String(found.name)
        optionNameByIri.set(optionIri, name)
        return name
    }
    // лениво подтянем имя
    void ensureOptionMeta(optionIri)
    return `Опция ${optionIri?.split('/').pop()}`
}
function optionValuesFor(optionIri: string): OptionValue[] {
    if (!optionIdToValues.has(optionIri)) {
        void loadOptionValues(optionIri)
        return []
    }
    return optionIdToValues.get(optionIri) || []
}
async function loadOptionValues(optionIri: string) {
    if (!optionIri || optionIdToValues.has(optionIri) || optionValuesLoading.value.has(optionIri)) return
    const next = new Set(optionValuesLoading.value); next.add(optionIri); optionValuesLoading.value = next
    try {
        const all = await optionValueRepo.findAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', value: 'asc' }, filters: { optionType: optionIri } }) as any
        const list = (all['hydra:member'] ?? all.member ?? []) as any
        optionIdToValues.set(optionIri, list as any)
    } finally {
        const next2 = new Set(optionValuesLoading.value); next2.delete(optionIri); optionValuesLoading.value = next2
    }
}

function ensureOption(optionIri: string) {
    void loadOptionValues(optionIri)
    if (!rows.value.some(r => r.option === optionIri)) {
        rows.value = [...rows.value, { option: optionIri, value: null, height: null, bulbsCount: null, sku: null, originalSku: null, price: null, setPrice: false, salePrice: null, lightingArea: null, sortOrder: null }]
    }
    void ensureOptionMeta(optionIri)
}
function removeOption(optionIri: string) {
    rows.value = rows.value.filter(r => r.option !== optionIri)
}
function rowsByOption(optionIri: string) {
    return rows.value.filter(r => r.option === optionIri).map(r => withProxies(r))
}
function addRow(optionIri: string) {
    const next = rows.value.slice()
    next.push({ option: optionIri, value: null, height: null, bulbsCount: null, sku: null, originalSku: null, price: null, setPrice: false, salePrice: null, lightingArea: null, sortOrder: null })
    rows.value = next
}
function removeRow(optionIri: string, idx: number) {
    let seen = -1
    rows.value = rows.value.filter(r => {
        if (r.option !== optionIri) return true
        seen++
        return seen !== idx
    })
}


type RowProxy = ProductOptionValueAssignment & { heightStr: string; bulbsCountStr: string; priceStr: string; setPrice: boolean | null; salePriceStr: string; lightingAreaStr: string; quantityStr: string; sortOrderStr: string }
function withProxies(row: ProductOptionValueAssignment): RowProxy {
    const normalize = (n: number | null | undefined): string => (n == null ? '' : String(n))
    const proxy: any = {}
    // pass-through fields used in template with v-model
    Object.defineProperties(proxy, {
        value: {
            get() { return row.value },
            set(v: string | null) { row.value = v },
            enumerable: true,
        },
        sku: {
            get() { return row.sku as any },
            set(v: string | null) { row.sku = v },
            enumerable: true,
        },
        originalSku: {
            get() { return (row as any).originalSku as any },
            set(v: string | null) { (row as any).originalSku = v },
            enumerable: true,
        },
        option: {
            get() { return row.option },
            set(v: string) { row.option = v },
            enumerable: true,
        },
        heightStr: {
            get() { return normalize(row.height as any) },
            set(v: string) { row.height = v === '' ? null : Number(v) },
            enumerable: true,
        },
        bulbsCountStr: {
            get() { return normalize(row.bulbsCount as any) },
            set(v: string) { row.bulbsCount = v === '' ? null : Number(v) },
            enumerable: true,
        },
        priceStr: {
            get() { return normalize(row.price as any) },
            set(v: string) { row.price = v === '' ? null : Number(v) },
            enumerable: true,
        },
        setPrice: {
            get() { return normalizeSetPrice((row as any).setPrice) },
            set(v: boolean | null) {
                (row as any).setPrice = v

                // Если устанавливаем true, снимаем setPrice со всех остальных записей товара
                if (v === true) {
                    rows.value.forEach(r => {
                        if (r !== row) {
                            (r as any).setPrice = false
                        }
                    })
                }
            },
            enumerable: true,
        },
        salePriceStr: {
            get() { return normalize((row as any).salePrice as any) },
            set(v: string) { (row as any).salePrice = v === '' ? null : Number(v) },
            enumerable: true,
        },
        lightingAreaStr: {
            get() { return normalize(row.lightingArea as any) },
            set(v: string) { row.lightingArea = v === '' ? null : Number(v) },
            enumerable: true,
        },
        quantityStr: {
            get() { return normalize(row.quantity as any) },
            set(v: string) { row.quantity = v === '' ? null : Number(v) },
            enumerable: true,
        },
        sortOrderStr: {
            get() { return normalize((row as any).sortOrder as any) },
            set(v: string) { (row as any).sortOrder = v === '' ? null : Number(v) },
            enumerable: true,
        },
    })
    return proxy as RowProxy
}
</script>

<style scoped></style>


