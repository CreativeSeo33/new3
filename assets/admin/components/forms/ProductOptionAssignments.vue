<template>
	<div class="rounded-md border p-4 dark:border-neutral-800">
		<div class="mb-3 text-sm font-medium">Опции товара</div>

		<div v-if="!assignments || assignments.length === 0" class="text-sm text-neutral-600 dark:text-neutral-300">
			Опции отсутствуют
		</div>

		<div v-else class="space-y-6">
			<div v-for="(rows, optIri) in grouped" :key="optIri as string" class="rounded-md border">
				<div class="border-b px-3 py-2 text-sm font-medium">
					{{ optionNameLabel(optIri as string) }}
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
							</tr>
						</thead>
						<tbody>
							<tr v-for="(r, idx) in rows" :key="idx" class="border-t">
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
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
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

const options = ref<Option[]>([])
const optionsLoading = ref(false)

// Локальный кеш в памяти для быстрого поиска названий опций
const optionsNameCache = new Map<string, string>()

// Значения опций
const optionValues = ref<OptionValue[]>([])
const optionValuesLoading = ref(false)

// Локальный кеш в памяти для быстрого поиска значений опций
const optionValuesCache = new Map<string, string>()

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
	await Promise.all([loadOptions(), loadOptionValues()])
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
	} catch (error) {
		console.error('Failed to load options:', error)
	} finally {
		optionsLoading.value = false
	}
}

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

const assignments = computed(() => Array.isArray(props.optionAssignments) ? props.optionAssignments : [])

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
	// Иначе показываем читаемый вариант IRI
	const parts = String(iri).split('/')
	const lastPart = parts[parts.length - 1]
	return lastPart ? `Значение ${lastPart}` : iri
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
