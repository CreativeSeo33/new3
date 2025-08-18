<template>
	<div class="p-4">
		<div class="mb-4 flex items-center justify-between gap-2">
			<h1 class="text-xl font-semibold">Статусы заказов</h1>
			<div class="flex items-center gap-2">
				<button
					type="button"
					class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
					@click="openCreate = true"
				>
					Добавить статус
				</button>
			</div>
		</div>

		<!-- Table -->
		<div class="overflow-x-auto rounded-md border border-neutral-200 dark:border-neutral-800">
			<table class="min-w-full divide-y divide-neutral-200 text-sm dark:divide-neutral-800">
				<thead class="bg-neutral-50 dark:bg-neutral-900/40">
					<tr>
						<th class="px-3 py-2 text-left font-medium text-neutral-600 dark:text-neutral-300">Название</th>
						<th class="px-3 py-2 text-left font-medium text-neutral-600 dark:text-neutral-300">Сортировка</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in rows" :key="row.id" class="hover:bg-neutral-50 dark:hover:bg-white/5">
						<td class="px-3 py-2">{{ row.name }}</td>
						<td class="px-3 py-2">{{ row.sort }}</td>
					</tr>
				</tbody>
			</table>
		</div>



		<!-- Create modal -->
		<DialogRoot v-model:open="openCreate">
			<DialogPortal>
				<DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
				<DialogContent class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900">
					<div class="mb-2">
						<DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Новый статус</DialogTitle>
						<DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Укажите название и сортировку</DialogDescription>
					</div>
					<form class="space-y-4" @submit.prevent="submitCreate">
						<label class="block text-sm">
							<span class="mb-1 block text-neutral-600 dark:text-neutral-300">Название</span>
							<input v-model="form.name" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" required maxlength="20" />
						</label>
						<label class="block text-sm">
							<span class="mb-1 block text-neutral-600 dark:text-neutral-300">Сортировка</span>
							<input v-model.number="form.sort" type="number" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900" required />
						</label>
						<div class="flex justify-end gap-2 pt-2">
							<button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="openCreate = false">Отмена</button>
							<button type="submit" class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100">Сохранить</button>
						</div>
					</form>
				</DialogContent>
			</DialogPortal>
		</DialogRoot>

		<!-- Toasts -->
		<ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="2200">
			<ToastDescription>{{ lastToastMessage }}</ToastDescription>
		</ToastRoot>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, ToastDescription, ToastRoot } from 'reka-ui'
import { useCrud } from '@admin/composables/useCrud'
import { OrderStatusRepository, type OrderStatusDto } from '@admin/repositories/OrderStatusRepository'

const repo = new OrderStatusRepository()
const crud = useCrud<OrderStatusDto>(repo)
const state = crud.state

const rows = computed(() => (state.items ?? []) as OrderStatusDto[])
// Без пагинации

const openCreate = ref(false)
const form = reactive({ name: '', sort: 0 })

const toastCount = ref(0)
const lastToastMessage = ref('')
function toast(msg: string) {
	lastToastMessage.value = msg
	toastCount.value += 1
}

onMounted(async () => {
	await crud.fetchAll({ sort: { sort: 'asc', name: 'asc' } })
})



async function submitCreate() {
	try {
		const payload = { name: (form.name || '').trim(), sort: Number(form.sort) || 0 }
		await crud.create(payload)
		openCreate.value = false
		form.name = ''
		form.sort = 0
		toast('Статус создан')
	} catch (e: any) {
		toast(e?.message || 'Ошибка сохранения')
	}
}
</script>


