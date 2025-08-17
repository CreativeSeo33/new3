<template>
	<div class="rounded-md border p-4 dark:border-neutral-800">
		<div class="mb-3 flex items-center justify-between">
			<div class="text-sm font-medium">Опции</div>
			<Button size="sm" @click="openAddOption()">Добавить</Button>
		</div>

		<div v-if="!options || options.length === 0" class="text-sm text-neutral-600 dark:text-neutral-300">
			Опции не выбраны. Нажмите «Добавить», чтобы выбрать опцию.
		</div>

		<div v-else class="space-y-6">
			<div v-for="cfg in options" :key="cfg.option" class="rounded-md border">
				<div class="border-b px-3 py-2 flex items-center justify-between">
					<div class="text-sm font-medium">{{ optionTitle(cfg.option) }}</div>
					<button type="button" class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700" @click="removeOptionConfig(cfg.option)">Удалить</button>
				</div>
				<div class="p-3 space-y-3 text-sm">
					<div class="flex flex-wrap items-center gap-4">
						<label class="inline-flex items-center gap-2">
							<input type="checkbox" v-model="cfg.multiple" />
							<span>Множественный выбор</span>
						</label>
						<label class="inline-flex items-center gap-2">
							<input type="checkbox" v-model="cfg.required" />
							<span>Обязательная</span>
						</label>
						<label class="inline-flex items-center gap-2">
							<span class="text-neutral-600">Режим цены:</span>
							<select v-model="cfg.priceMode" class="h-8 rounded-md border px-2 dark:border-neutral-800 dark:bg-neutral-900">
								<option value="delta">Надбавка к цене</option>
								<option value="absolute">Фиксированная цена</option>
							</select>
						</label>
					</div>
					<div>
						<div class="mb-2 text-sm text-neutral-700 dark:text-neutral-300">Значения</div>
						<div v-if="isOptionValuesLoading(cfg.option)" class="text-neutral-500">Загрузка…</div>
						<table v-else class="w-full text-sm">
							<thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
								<tr>
									<th class="px-3 py-2 text-left w-8"></th>
									<th class="px-3 py-2 text-left">Значение</th>
									<th class="px-3 py-2 text-left w-40">Цена</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="ov in optionValuesFor(cfg.option)" :key="ov.id" class="border-t">
									<td class="px-3 py-2 align-top">
										<input type="checkbox" :checked="isSelectedValue(cfg, ov)" @change="onToggleValue(cfg, ov, $event)" />
									</td>
									<td class="px-3 py-2">{{ ov.value }}</td>
									<td class="px-3 py-2">
										<input type="number" :value="priceForValue(cfg, ov)" class="h-9 w-40 rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" placeholder="0" @input="onPriceInput(cfg, ov, ($event.target as HTMLInputElement).value)" />
									</td>
								</tr>
								<tr v-if="optionValuesFor(cfg.option).length === 0">
									<td colspan="3" class="px-3 py-6 text-center text-neutral-500">Нет значений</td>
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
import { OptionRepository, type Option } from '@admin/repositories/OptionRepository'
import { OptionValueRepository, type OptionValue } from '@admin/repositories/OptionValueRepository'
import type { ProductOptionConfig } from '@admin/types/product'

const props = defineProps<{ optionsJson: ProductOptionConfig[] | null }>()
const emit = defineEmits<{ 'update:optionsJson': [val: ProductOptionConfig[] | null], toast: [message: string] }>()

const options = computed<ProductOptionConfig[] | null>({
	get: () => props.optionsJson ?? [],
	set: (val) => emit('update:optionsJson', val),
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
		addOptionConfig(selectedOptionIri.value)
		emit('toast', 'Опция добавлена')
	}
	selectedOptionIri.value = ''
}

// Values cache per option
const optionIdToValues = new Map<string, OptionValue[]>()
const optionValuesLoading = ref<Set<string>>(new Set())
function isOptionValuesLoading(optionIri: string): boolean { return optionValuesLoading.value.has(optionIri) }
function optionTitle(optionIri: string): string {
	const found = optionsSorted.value.find(o => (o as any)['@id'] === optionIri)
	return found?.name ? String(found.name) : `Опция ${optionIri?.split('/').pop()}`
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

function addOptionConfig(optionIri: string) {
	const current = (options.value ?? []).slice()
	if (current.some(c => c.option === optionIri)) return
	current.push({ option: optionIri, multiple: false, required: false, priceMode: 'delta', values: [], defaultValues: [], sortOrder: 0 })
	options.value = current
	void loadOptionValues(optionIri)
}
function removeOptionConfig(optionIri: string) {
	const next = (options.value ?? []).filter(c => c.option !== optionIri)
	options.value = next
}
function isSelectedValue(cfg: ProductOptionConfig, ov: OptionValue): boolean {
	return Array.isArray(cfg.values) && cfg.values.some(v => v.value === (ov as any)['@id'])
}
function priceForValue(cfg: ProductOptionConfig, ov: OptionValue): string {
	const entry = Array.isArray(cfg.values) ? cfg.values.find(v => v.value === (ov as any)['@id']) : null
	return entry && entry.price != null ? String(entry.price) : ''
}
function onToggleValue(cfg: ProductOptionConfig, ov: OptionValue, e: Event) {
	const checked = (e.target as HTMLInputElement).checked
	const iri = (ov as any)['@id']
	const vals = Array.isArray(cfg.values) ? cfg.values.slice() : []
	const idx = vals.findIndex(v => v.value === iri)
	if (checked && idx === -1) vals.push({ value: iri, label: (ov as any).value ?? undefined, price: null })
	if (!checked && idx !== -1) vals.splice(idx, 1)
	cfg.values = vals
	options.value = (options.value ?? []).slice()
}
function onPriceInput(cfg: ProductOptionConfig, ov: OptionValue, raw: string) {
	const iri = (ov as any)['@id']
	const vals = Array.isArray(cfg.values) ? cfg.values.slice() : []
	let entry = vals.find(v => v.value === iri)
	if (!entry) { entry = { value: iri, label: (ov as any).value ?? undefined, price: null }; vals.push(entry) }
	const n = Number(String(raw).trim().replace(',', '.'))
	entry.price = Number.isFinite(n) ? n : null
	cfg.values = vals
	options.value = (options.value ?? []).slice()
}
</script>

<style scoped></style>


