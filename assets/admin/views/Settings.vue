<template>
	<div class="space-y-6">
		<div class="flex items-center justify-between">
			<h1 class="text-xl font-semibold">Настройки</h1>
			<button
				type="button"
				class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
				@click="openCreate = true"
			>
				Добавить
			</button>
		</div>

		<div v-if="state.error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
			{{ state.error }}
		</div>

		<div class="rounded-md border">
			<table class="w-full text-sm">
				<thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
					<tr>
						<th class="px-4 py-2 text-left w-64">Название</th>
						<th class="px-4 py-2 text-left">Значение</th>
						
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in rows" :key="row.id" class="border-t">
						<td class="px-4 py-2"><Input v-model="row.nameProxy" placeholder="Ключ" @blur="() => saveRow(row)" /></td>
						<td class="px-4 py-2"><Input v-model="row.valueProxy" placeholder="Значение" @blur="() => saveRow(row)" /></td>
					</tr>
					<tr v-if="!loading && rows.length === 0">
						<td colspan="3" class="px-4 py-6 text-center text-neutral-500">Нет записей</td>
					</tr>
				</tbody>
			</table>
		</div>

		<DialogRoot v-model:open="openCreate">
			<DialogPortal>
				<DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
				<DialogContent class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900">
					<div class="mb-2">
						<DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Новая настройка</DialogTitle>
						<DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Заполните поля ниже</DialogDescription>
					</div>
					<form class="space-y-4" @submit.prevent="createSubmit">
						<Input v-model="createForm.name" label="Название" placeholder="site.title" />
						<Input v-model="createForm.value" label="Значение" placeholder="Магазин" />
						<div class="flex justify-end gap-2 pt-2">
							<button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
							<button type="submit" :disabled="submitting || !createForm.name.trim()" class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100">
								{{ submitting ? 'Сохранение…' : 'Сохранить' }}
							</button>
						</div>
					</form>
					<DialogClose as-child>
						<button aria-label="Закрыть" class="sr-only">Закрыть</button>
					</DialogClose>
				</DialogContent>
			</DialogPortal>
		</DialogRoot>

		<ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="2200">
			<ToastDescription>{{ lastToastMessage }}</ToastDescription>
		</ToastRoot>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { DialogClose, DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, ToastDescription, ToastRoot } from 'reka-ui'
import Input from '@admin/ui/components/Input.vue'
import { useCrud } from '@admin/composables/useCrud'
import { SettingsRepository, type Setting } from '@admin/repositories/SettingsRepository'

type EditableRow = { id: number; nameProxy: string; valueProxy: string }

const repo = new SettingsRepository()
const crud = useCrud<Setting>(repo)
const state = crud.state
const loading = computed(() => !!state.loading)

const rows = ref<EditableRow[]>([])
watch(
	() => state.items,
	() => syncRows(),
	{ deep: true }
)

function syncRows() {
	const items = ((state.items ?? []) as Setting[]).slice()
	rows.value = items.map((s) => ({ id: Number(s.id), nameProxy: String((s as any).name ?? ''), valueProxy: String((s as any).value ?? '') }))
}

async function saveRow(row: EditableRow) {
	const payload: Partial<Setting> = { name: row.nameProxy.trim() || null, value: row.valueProxy.trim() || null }
	await crud.update(row.id, payload)
	publishToast('Сохранено')
}

// Create form
const openCreate = ref(false)
const submitting = ref(false)
const createForm = reactive({ name: '', value: '' })

async function createSubmit() {
	if (!createForm.name.trim()) return
	try {
		submitting.value = true
		await crud.create({ name: createForm.name.trim(), value: createForm.value.trim() || null })
		createForm.name = ''
		createForm.value = ''
		openCreate.value = false
		await crud.fetchAll({ itemsPerPage: 1000 })
		publishToast('Создано')
	} finally {
		submitting.value = false
	}
}

const toastCount = ref(0)
const lastToastMessage = ref('')
function publishToast(message: string) {
	lastToastMessage.value = message
	toastCount.value++
}

onMounted(async () => {
	await crud.fetchAll({ itemsPerPage: 1000 })
	syncRows()
})
</script>

<style scoped>
</style>


