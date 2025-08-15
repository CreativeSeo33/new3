<template>
	<ul class="space-y-1">
    <li v-for="node in nodes" :key="node.id" class="py-1.5">
        <div class="flex items-center gap-2">
            <input type="checkbox" :checked="isChecked(node.id)" @change="onToggle(node, ($event.target as HTMLInputElement).checked)" />
            <span class="text-sm">{{ node.label }}</span>
        </div>
			<ul v-if="node.children && node.children.length" class="ml-5 border-l border-neutral-200 pl-3 dark:border-neutral-800">
				<ProductCategoryTree
					:nodes="node.children"
					:checked-ids="props.checkedIds"
					@toggle="(id, checked) => $emit('toggle', id, checked)"
				/>
			</ul>
    </li>
	</ul>
</template>

<script setup lang="ts">
export interface TreeOption {
  id: number
  label: string
  children?: TreeOption[]
}

const props = defineProps<{ nodes: TreeOption[]; checkedIds: Set<number> }>()
const emit = defineEmits<{ (e: 'toggle', id: number, checked: boolean): void }>()

function isChecked(id: number) {
  return props.checkedIds?.has(id)
}

function onToggle(node: TreeOption, checked: boolean) {
  emit('toggle', node.id, checked)
}
</script>

<style scoped></style>


