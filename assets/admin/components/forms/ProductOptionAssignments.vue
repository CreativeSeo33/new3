<template>
	<div class="rounded-md border p-4 dark:border-neutral-800">
		<div class="mb-3 flex items-center justify-between">
			<div class="text-sm font-medium">Опции товара</div>
			<button
				class="text-xs px-2 py-1 rounded border bg-white hover:bg-neutral-50 dark:bg-neutral-900 dark:hover:bg-neutral-800"
				@click="openAddOptionModal"
			>
				Добавить опцию
			</button>
		</div>

		<div v-if="!assignments || assignments.length === 0" class="text-sm text-neutral-600 dark:text-neutral-300">
			Опции отсутствуют
		</div>

		<div v-else class="space-y-6">
			<div v-for="g in groupedSorted" :key="g.optIri" class="rounded-md border">
				<div class="border-b px-3 py-2 text-sm font-medium flex items-center justify-between">
					<span>{{ optionNameLabel(g.optIri) }}</span>
					<div class="flex items-center gap-2">
						<button
							@click="openAddValueModal(g.optIri)"
							class="text-xs px-2 py-1 rounded border bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
							title="Добавить значение"
						>
							Добавить значение
						</button>
						<button
							@click="emit('removeOption', g.optIri)"
							class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
							title="Удалить опцию"
						>
							Удалить
						</button>
					</div>
				</div>
				<div class="p-3 text-sm">
					<table class="w-full text-sm">
						<thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
							<tr>
								<th class="px-3 py-2 text-left w-[260px]">Значение</th>
								<th class="px-3 py-2 text-left w-28">Высота</th>
								<th class="px-3 py-2 text-left w-32">Лампочек</th>
								<th class="px-3 py-2 text-left w-40">Артикул</th>
								<th class="px-3 py-2 text-left w-40">Ориг. арт.</th>
								<th class="px-3 py-2 text-left w-28">Цена</th>
								<th class="px-3 py-2 text-left w-28">Скидка</th>
								<th class="px-3 py-2 text-left w-32">Освещение</th>
								<th class="px-3 py-2 text-left w-24">Кол-во</th>
								<th class="px-3 py-2 text-left w-24">Сорт.</th>
								<th class="px-3 py-2 text-left w-28">Базовая</th>
								<th class="px-3 py-2 text-left w-28">Действия</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="(r, idx) in g.rows" :key="idx" class="border-t">
								<td class="px-3 py-2">{{ iriLabel(r.value) || '—' }}</td>
								<td class="px-3 py-2">{{ fmtNum(r.height) }}</td>
								<td class="px-3 py-2">{{ fmtNum(r.bulbsCount) }}</td>
								<td class="px-3 py-2">{{ r.sku || '—' }}</td>
								<td class="px-3 py-2">{{ r.originalSku || '—' }}</td>
								<td class="px-3 py-2">{{ fmtNum(r.price) }}</td>
								<td class="px-3 py-2">{{ fmtNum(r.salePrice) }}</td>
								<td class="px-3 py-2">{{ fmtNum(r.lightingArea) }}</td>
								<td class="px-3 py-2">{{ fmtNum(r.quantity) }}</td>
								<td class="px-3 py-2">{{ fmtNum(r.sortOrder) }}</td>
								<td class="px-3 py-2">{{ r.setPrice ? 'Да' : 'Нет' }}</td>
								<td class="px-3 py-2">
									<button
										class="mr-2 text-xs px-2 py-1 rounded border bg-white hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:bg-neutral-800"
										@click="openEditValueModal(g.optIri, r)"
										title="Редактировать"
									>
										Редактировать
									</button>
									<button
										class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 text-xs px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
										@click="emit('removeAssignment', r)"
										title="Удалить значение"
									>
										Удалить
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Modal: Add Option -->
		<div v-if="addModalOpen" class="fixed inset-0 z-50 flex items-center justify-center">
			<div class="absolute inset-0 bg-black/40" @click="closeAddOptionModal"></div>
			<div class="relative z-10 w-[520px] max-w-[92vw] rounded-md border bg-white p-4 shadow-xl dark:border-neutral-800 dark:bg-neutral-900">
				<div class="mb-3 text-sm font-medium">Добавить опцию</div>
				<div class="space-y-3">
					<label class="block text-sm">
						<span class="mb-1 block text-neutral-600 dark:text-neutral-300">Опция</span>
						<select v-model="selectedOptionIri" class="w-full rounded border bg-white p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
							<option value="" disabled>Выберите опцию…</option>
						<option
							v-for="opt in modalOptions"
							:key="opt['@id']"
							:value="opt['@id']"
							:disabled="isOptionDisabled(String(opt['@id']))"
						>
								{{ opt.name }}
							</option>
						</select>
					</label>

					<label class="block text-sm" v-if="selectedOptionIri">
						<span class="mb-1 block text-neutral-600 dark:text-neutral-300">Значение</span>
						<select v-model="selectedValueIri" class="w-full rounded border bg-white p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
							<option value="" disabled>Выберите значение…</option>
							<option v-for="v in optionValuesFor(selectedOptionIri)" :key="v['@id']" :value="v['@id']">
								{{ v.value }}
							</option>
						</select>
					</label>
				</div>
				<div class="mt-4 flex items-center justify-end gap-2">
					<button class="px-3 py-1 text-sm rounded border hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="closeAddOptionModal">Отмена</button>
					<button class="px-3 py-1 text-sm rounded bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50" :disabled="!selectedOptionIri || !selectedValueIri || modalLoading || isOptionDisabled(selectedOptionIri)" @click="confirmAddOption">
						{{ modalLoading ? 'Добавление…' : 'Добавить' }}
					</button>
				</div>
			</div>
		</div>

		<!-- Modal: Add Option Value -->
		<div v-if="addValueModal.open" class="fixed inset-0 z-50 flex items-center justify-center">
			<div class="absolute inset-0 bg-black/40" @click="closeAddValueModal"></div>
			<div class="relative z-10 w-[720px] max-w-[96vw] rounded-md border bg-white p-4 shadow-xl dark:border-neutral-800 dark:bg-neutral-900">
				<div class="mb-3 text-sm font-medium">Добавить значение опции</div>
				<div class="space-y-3">
					<div class="grid grid-cols-2 gap-3">
						<label class="block text-sm col-span-2">
							<span class="mb-1 block text-neutral-600 dark:text-neutral-300">Опция</span>
							<input type="text" class="w-full rounded border bg-neutral-50 p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" :value="optionNameLabel(addValueModal.optionIri)" disabled>
						</label>
						<label class="block text-sm">
							<span class="mb-1 block text-neutral-600 dark:text-neutral-300">Значение</span>
							<select v-model="addValueForm.valueIri" class="w-full rounded border bg-white p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
								<option value="" disabled>Выберите значение…</option>
								<option v-for="v in optionValuesFor(addValueModal.optionIri)" :key="v['@id']" :value="v['@id']">
									{{ v.value }}
								</option>
							</select>
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Высота</span>
							<input v-model="addValueForm.height" type="number" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Лампочек</span>
							<input v-model="addValueForm.bulbsCount" type="number" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Артикул</span>
							<input v-model="addValueForm.sku" type="text" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Ориг. арт.</span>
							<input v-model="addValueForm.originalSku" type="text" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Цена</span>
							<input v-model="addValueForm.price" type="number" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Скидка</span>
							<input v-model="addValueForm.salePrice" type="number" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Освещение</span>
							<input v-model="addValueForm.lightingArea" type="number" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Кол-во</span>
							<input v-model="addValueForm.quantity" type="number" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block">Сортировка</span>
							<input v-model="addValueForm.sortOrder" type="number" class="w-full rounded border p-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
						</label>
					<label class="block text-sm">
						<span class="mb-1 block">Базовая</span>
						<input v-model="addValueForm.setPrice" type="checkbox" class="h-4 w-4" />
					</label>
					</div>
				</div>
				<div class="mt-4 flex items-center justify-end gap-2">
					<button class="px-3 py-1 text-sm rounded border hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800" @click="closeAddValueModal">Отмена</button>
					<button class="px-3 py-1 text-sm rounded bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50" :disabled="!addValueForm.valueIri || savingAddValue" @click="confirmAddValue">
						{{ savingAddValue ? 'Добавление…' : 'Добавить' }}
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, reactive, watch } from 'vue'
import { OptionRepository, type Option } from '@admin/repositories/OptionRepository'
import { OptionValueRepository, type OptionValue } from '@admin/repositories/OptionValueRepository'

interface OptionRow {
	option: string | null
	value: string | null
	height: number | null
	bulbsCount: number | null
	sku: string | null
	originalSku?: string | null
	price: number | null
	setPrice?: boolean | null
	salePrice?: number | null
	lightingArea: number | null
	sortOrder?: number | null
	quantity?: number | null
}

const props = defineProps<{
	optionAssignments: OptionRow[] | null
	optionValuesMap?: Record<string, string>
	optionNamesMap?: Record<string, string>
}>()

const emit = defineEmits<{
	removeOption: [optionIri: string]
	addOption: [payload: any]
	removeAssignment: [row: OptionRow]
}>()

const options = ref<Option[]>([])
const optionsLoading = ref(false)
const optionsPrefetched = ref(false)

// Modal state (lazy load options)
const addModalOpen = ref(false)
const modalLoading = ref(false)
const selectedOptionIri = ref<string>('')
const selectedValueIri = ref<string>('')
const modalOptions = computed<Option[]>(() => {
	const list = (options.value || ([] as Option[])).slice()
	list.sort((a: any, b: any) => {
		const ao = (a as any).sortOrder ?? null
		const bo = (b as any).sortOrder ?? null
		if (ao != null && bo != null) return ao - bo
		if (ao != null) return -1
		if (bo != null) return 1
		return String(a?.name || '').localeCompare(String(b?.name || ''))
	})
	return list as Option[]
})

// Локальный кеш (reactive) для быстрого поиска названий опций
const optionsNameCache = reactive(new Map<string, string>())
const optionNameLoading = ref<Set<string>>(new Set())

// Значения опций
const optionValues = ref<OptionValue[]>([])
const optionValuesLoading = ref(false)

// Локальный кеш в памяти для быстрого поиска значений опций
const optionValuesCache = new Map<string, string>()
const optionValueNameLoading = ref<Set<string>>(new Set())
// Модалка добавления/редактирования значения
const addValueModal = ref<{ open: boolean; optionIri: string; editRow: OptionRow | null }>({ open: false, optionIri: '', editRow: null })
const addValueForm = ref<{
	valueIri: string
	height: number | null
	bulbsCount: number | null
	sku: string | null
	originalSku: string | null
	price: number | null
	salePrice: number | null
	lightingArea: number | null
	quantity: number | null
	sortOrder: number | null
	setPrice: boolean
}>({
	valueIri: '',
	height: null,
	bulbsCount: null,
	sku: null,
	originalSku: null,
	price: null,
	salePrice: null,
	lightingArea: null,
	quantity: null,
	sortOrder: null,
	setPrice: false,
})
const savingAddValue = ref(false)

function openAddValueModal(optionIri: string) {
	addValueModal.value = { open: true, optionIri, editRow: null }
	addValueForm.value = {
        valueIri: '', height: null, bulbsCount: null, sku: null, originalSku: null,
        price: null, salePrice: null, lightingArea: null, quantity: null, sortOrder: null,
        setPrice: false,
	}
	// Ленивая подгрузка значений для конкретной опции, если они не загружены
	ensureOptionValuesLoaded(optionIri)
}

function closeAddValueModal() { addValueModal.value = { open: false, optionIri: '', editRow: null } }

function openEditValueModal(optionIri: string, row: OptionRow) {
	addValueModal.value = { open: true, optionIri, editRow: row }
	addValueForm.value = {
		valueIri: String(row.value || ''),
		height: row.height ?? null,
		bulbsCount: row.bulbsCount ?? null,
		sku: row.sku ?? null,
		originalSku: row.originalSku ?? null,
		price: row.price ?? null,
		salePrice: row.salePrice ?? null,
		lightingArea: row.lightingArea ?? null,
		quantity: row.quantity ?? null,
        sortOrder: row.sortOrder ?? null,
        setPrice: Boolean(row.setPrice ?? false),
	}
	ensureOptionValuesLoaded(optionIri)
}

function confirmAddValue() {
	if (!addValueForm.value.valueIri) return
	// Сигнал наверх: добавление или редактирование
	const payload = {
		option: addValueModal.value.optionIri,
		value: addValueForm.value.valueIri,
		height: addValueForm.value.height,
		bulbsCount: addValueForm.value.bulbsCount,
		sku: addValueForm.value.sku,
		originalSku: addValueForm.value.originalSku,
		price: addValueForm.value.price,
		salePrice: addValueForm.value.salePrice,
		lightingArea: addValueForm.value.lightingArea,
		quantity: addValueForm.value.quantity,
		sortOrder: addValueForm.value.sortOrder,
        setPrice: addValueForm.value.setPrice,
	}
	if (addValueModal.value.editRow) {
		(payload as any).__editOf = addValueModal.value.editRow
	}
	// Используем событие addOption для единой обработки в родителе
	emit('addOption', payload as any)
	closeAddValueModal()
}

const optionsMap = computed(() => {
	const map = new Map<string, string>()
	for (const option of options.value) {
		if (option['@id'] && option.name) {
			map.set(option['@id'], option.name)
		}
	}
	return map
})

onMounted(async () => {
    // Предзагрузка справочника опций (24h cache) и значений по видимым опциям
    await loadOptions()
    await prewarmOptionValuesFromAssignments()
})

async function loadOptions() {
	if (optionsLoading.value) return
	optionsLoading.value = true
	try {
		const repo = new OptionRepository()
		const data = await repo.findAllCached({ itemsPerPage: 500 }) as any
		const collection = (data['hydra:member'] ?? data.member ?? []) as any
		options.value = collection as Option[]

		// Заполняем локальный кеш для быстрого поиска
		optionsNameCache.clear()
        for (const option of options.value) {
			if (option['@id'] && option.name) {
				optionsNameCache.set(option['@id'], option.name)
			}
		}
        optionsPrefetched.value = true
	} catch (error) {
		console.error('Failed to load options:', error)
	} finally {
		optionsLoading.value = false
	}
}

function openAddOptionModal() {
	addModalOpen.value = true
	selectedOptionIri.value = ''
	selectedValueIri.value = ''
	if (!options.value.length) void ensureModalOptions()
}

function closeAddOptionModal() {
	addModalOpen.value = false
}

async function ensureModalOptions() {
	if (optionsLoading.value) return
	modalLoading.value = true
	try {
		await loadOptions()
	} finally {
		modalLoading.value = false
	}
}

function confirmAddOption() {
	if (!selectedOptionIri.value || !selectedValueIri.value) return
	// Отправляем сразу пару option/value, чтобы PATCH прошёл
	emit('addOption', {
		option: selectedOptionIri.value,
		value: selectedValueIri.value,
		height: null,
		bulbsCount: null,
		sku: null,
		originalSku: null,
		price: null,
		salePrice: null,
		lightingArea: null,
		quantity: null,
		sortOrder: null,
		attributes: null,
	})
	closeAddOptionModal()
}

// При выборе опции в модалке — лениво подгружаем её значения
watch(selectedOptionIri, (iri) => {
	selectedValueIri.value = ''
	if (iri) void ensureOptionValuesLoaded(iri)
})

async function loadOptionValues() {
	if (optionValuesLoading.value) return
	optionValuesLoading.value = true
	try {
		const repo = new OptionValueRepository()
		const data = await repo.findAllCached({ itemsPerPage: 1000 }) as any
		const collection = (data['hydra:member'] ?? data.member ?? []) as any
		optionValues.value = collection as OptionValue[]

		// Заполняем локальный кеш для быстрого поиска
		optionValuesCache.clear()
		for (const optionValue of optionValues.value) {
			if (optionValue['@id'] && optionValue.value) {
				optionValuesCache.set(optionValue['@id'], optionValue.value)
			}
		}
	} catch (error) {
		console.error('Failed to load option values:', error)
	} finally {
		optionValuesLoading.value = false
	}
}

// Пер-опционная ленивая подгрузка значений, чтобы не грузить все сразу
const optionValuesByOption = ref<Record<string, OptionValue[]>>({})
const optionValuesLoadingSet = ref<Set<string>>(new Set())

function optionValuesFor(optionIri: string): OptionValue[] {
	return optionValuesByOption.value[optionIri] || []
}

function isOptionValuesLoading(optionIri: string): boolean {
	return optionValuesLoadingSet.value.has(optionIri)
}

async function ensureOptionValuesLoaded(optionIri: string) {
	if (!optionIri) return
	if (optionValuesByOption.value[optionIri]?.length) return
	if (optionValuesLoadingSet.value.has(optionIri)) return
	const next = new Set(optionValuesLoadingSet.value); next.add(optionIri); optionValuesLoadingSet.value = next
	try {
		const repo = new OptionValueRepository()
		// Используем кешированную выборку по опции (24h)
		const data = await repo.findByOptionCached(optionIri, { itemsPerPage: 1000 }) as any
		const list = (data['hydra:member'] ?? data.member ?? []) as OptionValue[]
		// записываем реактивно
		optionValuesByOption.value = { ...optionValuesByOption.value, [optionIri]: list }
		// Включаем значения в кэш для меток, чтобы таблица тоже показывала названия
		for (const ov of list) {
			const iri = (ov as any)['@id'] as string | undefined
			if (iri && (ov as any).value) optionValuesCache.set(iri, (ov as any).value)
		}
	} finally {
		const n2 = new Set(optionValuesLoadingSet.value); n2.delete(optionIri); optionValuesLoadingSet.value = n2
	}
}

async function prewarmOptionValuesFromAssignments() {
    try {
        const unique = new Set<string>()
        for (const r of assignments.value) {
            if (r?.option) unique.add(String(r.option))
        }
        await Promise.all(Array.from(unique).map((iri) => ensureOptionValuesLoaded(iri)))
    } catch {}
}

const assignments = computed(() => Array.isArray(props.optionAssignments) ? props.optionAssignments : [])

// Множество IRI опций, которые уже есть у товара
const assignedOptionSet = computed<Set<string>>(() => {
	const s = new Set<string>()
	for (const r of assignments.value) {
		if (r?.option) s.add(String(r.option))
	}
	return s
})

function isOptionDisabled(optionIri: string): boolean {
	return assignedOptionSet.value.has(String(optionIri))
}

const grouped = computed<Record<string, OptionRow[]>>(() => {
	const map: Record<string, OptionRow[]> = {}
	for (const r of assignments.value) {
		const key = r.option || '__unknown__'
		if (!map[key]) map[key] = []
		map[key].push(r)
	}
	for (const k of Object.keys(map)) {
		map[k].sort((a, b) => (toNum(a.sortOrder) - toNum(b.sortOrder)) || iriLabel(a.value).localeCompare(iriLabel(b.value)))
	}
	return map
})

// Сортировка групп опций по Option.sortOrder (падение к имени)
const groupedSorted = computed<Array<{ optIri: string; rows: OptionRow[] }>>(() => {
	const entries = Object.entries(grouped.value).map(([optIri, rows]) => ({ optIri, rows }))
	entries.sort((a, b) => {
		const ao = getOptionSortOrder(a.optIri)
		const bo = getOptionSortOrder(b.optIri)
		if (Number.isFinite(ao) && Number.isFinite(bo)) return ao - bo
		if (Number.isFinite(ao)) return -1
		if (Number.isFinite(bo)) return 1
		return optionNameLabel(a.optIri).localeCompare(optionNameLabel(b.optIri))
	})
	return entries
})

function getOptionSortOrder(optionIri: string): number {
	const found = (options.value || ([] as Option[])).find(o => (o as any)['@id'] === optionIri) as any
	const so = found?.sortOrder
	return typeof so === 'number' ? so : Number.POSITIVE_INFINITY
}

function optionNameLabel(iri?: string | null): string {
	if (!iri) return ''
	// Быстрый поиск в локальном кеше
	const cached = optionsNameCache.get(iri)
	if (cached) {
		return cached
	}
	// Запасной вариант через computed map
	const name = optionsMap.value.get(iri)
	if (name) {
		return name
	}
	// Пытаемся лениво подтянуть имя опции по IRI
	void ensureOptionNameLoaded(iri)
	// Если опции еще не загружены или опция не найдена, показываем читаемый вариант IRI
	const parts = String(iri).split('/')
	const lastPart = parts[parts.length - 1]
	return lastPart ? `Опция ${lastPart}` : iri
}

function iriLabel(iri?: string | null): string {
	if (!iri) return ''
	// Быстрый поиск в локальном кеше значений опций
	const cached = optionValuesCache.get(iri)
	if (cached) {
		return cached
	}
	// Если есть маппинг значений из пропсов, используем его
	if (props.optionValuesMap?.[iri]) {
		return props.optionValuesMap[iri]
	}
	// Пытаемся лениво подтянуть значение по IRI
	void ensureOptionValueNameLoaded(iri)
	// Иначе показываем читаемый вариант IRI
	const parts = String(iri).split('/')
	const lastPart = parts[parts.length - 1]
	return lastPart ? `Значение ${lastPart}` : iri
}

async function ensureOptionNameLoaded(optionIri: string) {
	if (!optionIri) return
	if (optionsNameCache.has(optionIri)) return
	if (optionNameLoading.value.has(optionIri)) return
	const next = new Set(optionNameLoading.value); next.add(optionIri); optionNameLoading.value = next
	try {
		const idStr = optionIri.split('/').pop() || ''
		const id = Number(idStr)
		if (!Number.isFinite(id) || id <= 0) return
		const repo = new OptionRepository()
		const data = await repo.findById(id) as any
		const iri = data?.['@id'] as string | undefined
		const name = data?.name as string | undefined
		if (iri && name) {
			optionsNameCache.set(iri, name)
		}
	} finally {
		const n2 = new Set(optionNameLoading.value); n2.delete(optionIri); optionNameLoading.value = n2
	}
}

async function ensureOptionValueNameLoaded(valueIri: string) {
	if (!valueIri) return
	if (optionValuesCache.has(valueIri)) return
	if (optionValueNameLoading.value.has(valueIri)) return
	const next = new Set(optionValueNameLoading.value); next.add(valueIri); optionValueNameLoading.value = next
	try {
		const idStr = valueIri.split('/').pop() || ''
		const id = Number(idStr)
		if (!Number.isFinite(id) || id <= 0) return
		const repo = new OptionValueRepository()
		const data = await repo.findById(id) as any
		const iri = data?.['@id'] as string | undefined
		const label = data?.value as string | undefined
		if (iri && label) {
			optionValuesCache.set(iri, label)
		}
	} finally {
		const n2 = new Set(optionValueNameLoading.value); n2.delete(valueIri); optionValueNameLoading.value = n2
	}
}

function toNum(n: any): number {
	const v = Number(n)
	return Number.isFinite(v) ? v : 0
}

function fmtNum(n: any): string {
	if (n === null || n === undefined || n === '') return '—'
	const v = Number(n)
	return Number.isFinite(v) ? String(v) : '—'
}
</script>

<style scoped></style>
