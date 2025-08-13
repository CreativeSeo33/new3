<template>
	<AlertDialogRoot v-model:open="model">
		<AlertDialogPortal>
			<AlertDialogOverlay class="fixed inset-0 bg-black/50" />
			<AlertDialogContent class="fixed left-1/2 top-1/2 w-[92vw] max-w-sm -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg dark:border-neutral-800 dark:bg-neutral-900">
				<AlertDialogTitle class="text-base font-semibold">{{ title }}</AlertDialogTitle>
				<AlertDialogDescription v-if="description" class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
					{{ description }}
				</AlertDialogDescription>
				<div class="mt-4 flex justify-end gap-2">
					<AlertDialogCancel class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10">{{ cancelText }}</AlertDialogCancel>
					<AlertDialogAction :class="confirmClass" @click="onConfirm">{{ confirmText }}</AlertDialogAction>
				</div>
			</AlertDialogContent>
		</AlertDialogPortal>
	</AlertDialogRoot>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogOverlay, AlertDialogPortal, AlertDialogRoot, AlertDialogTitle } from 'reka-ui'

const props = withDefaults(defineProps<{
	modelValue: boolean
	title: string
	description?: string
	confirmText?: string
	cancelText?: string
	danger?: boolean
}>(), {
	confirmText: 'ОК',
	cancelText: 'Отмена',
	danger: false,
})

const emit = defineEmits<{
	(e: 'update:modelValue', v: boolean): void
	(e: 'confirm'): void
}>()

const model = computed({
	get: () => props.modelValue,
	set: (v: boolean) => emit('update:modelValue', v),
})

const confirmClass = computed(() =>
	props.danger
		? 'h-9 rounded-md bg-red-600 px-3 text-sm font-medium text-white hover:bg-red-700'
		: 'h-9 rounded-md bg-neutral-900 px-3 text-sm font-medium text-white hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100',
)

function onConfirm() {
	emit('confirm')
	model.value = false
}
</script>

<style scoped></style>


