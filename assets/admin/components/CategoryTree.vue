<template>
	<li v-for="node in treeItems" :key="node.id">
    <TreeItem v-slot="{ isExpanded }" as-child :level="level" :value="node">
            <div class="relative">
                <div
                    class="absolute left-0 right-0 -top-1 h-2"
                    :class="[hoverTop ? 'bg-blue-200/40' : '', isInvalidReorder(node) ? 'bg-red-200/60' : '']"
                    @dragover.prevent="() => (hoverTop = true)"
                    @dragleave="() => (hoverTop = false)"
                    @drop.prevent="(e) => onDropReorder(node, e, 'before')"
                />
                <div
                    class="flex items-center justify-between py-1.5"
                    :class="(hoverSelf || hoverTop || hoverBottom) ? (isInvalidMove(node) ? 'bg-red-100 dark:bg-red-900/30' : 'bg-blue-50 dark:bg-white/10') : ''"
                    draggable="true"
                    @dragstart="(e) => onDragStart(node, e)"
                    @dragover.prevent="onDragOver"
                    @dragleave="onLeave"
                    @drop.prevent="(e) => onDrop(node, e)"
                >
				<div class="flex items-center gap-2">
					<RouterLink
						:to="{ name: 'admin-category-form', params: { id: node.id } }"
						class="text-sm hover:underline"
						:class="isInvalidMove(node) ? 'text-red-600' : ''"
					>
						{{ node.name || `Без названия (#${node.id})` }}
					</RouterLink>
					<span v-if="node.slug" class="text-xs text-neutral-500">/{{ node.slug }}</span>
				</div>
				<div class="flex items-center gap-2">
					<RouterLink
						:to="{ name: 'admin-category-form', params: { id: node.id } }"
						class="inline-flex h-7 items-center rounded-md border px-2 text-xs hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
					>
						Edit
					</RouterLink>
					<a
						v-if="node.slug"
						:href="`/category/${node.slug}`"
						target="_blank"
						rel="noopener noreferrer"
						class="inline-flex h-7 items-center rounded-md border px-2 text-xs hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-white/10"
					>
						View
					</a>
                <button
						type="button"
						class="inline-flex h-7 items-center rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700"
						@click.stop="$emit('delete', node.id)"
					>
						Delete
					</button>
                </div>
                </div>
                <div
                    class="absolute left-0 right-0 -bottom-1 h-2"
                    :class="[hoverBottom ? 'bg-blue-200/40' : '', isInvalidReorder(node) ? 'bg-red-200/60' : '']"
                    @dragover.prevent="() => (hoverBottom = true)"
                    @dragleave="() => (hoverBottom = false)"
                    @drop.prevent="(e) => onDropReorder(node, e, 'after')"
                />
            </div>
            <ul v-if="isExpanded && node.children && node.children.length" class="ml-4">
                <CategoryTree
                    :tree-items="node.children"
                    :level="(level || 0) + 1"
                    :current-drag-id="props.currentDragId"
                    :descendant-map="props.descendantMap"
                    @delete="emit('delete', $event)"
                    @move="(s, t) => emit('move', s, t)"
                    @reorder="(s, t, p) => emit('reorder', s, t, p)"
                    @dragging="(id) => emit('dragging', id)"
                    @dragend="() => emit('dragend')"
                />
			</ul>
		</TreeItem>
	</li>
</template>

<script setup lang="ts">
import { ref, onUnmounted, computed } from 'vue'
import { TreeItem } from 'reka-ui'
import { RouterLink } from 'vue-router'

export interface CategoryNode {
    id: number
    name: string | null
    slug?: string | null
    parentCategoryId?: number | null
    children?: CategoryNode[]
}

const props = withDefaults(defineProps<{
  treeItems: CategoryNode[]
  level?: number
  currentDragId?: number | null
  descendantMap?: Map<number, Set<number>>
}>(), { level: 0 })

const emit = defineEmits<{
  (e: 'delete', id: number): void
  (e: 'move', sourceId: number, targetId: number): void
  (e: 'reorder', sourceId: number, targetId: number, position: 'before' | 'after'): void
  (e: 'dragging', id: number): void
  (e: 'dragend'): void
}>()

const hoverTop = ref(false)
const hoverBottom = ref(false)
const hoverSelf = ref(false)

function isInvalidMove(target: CategoryNode): boolean {
  const src = props.currentDragId
  if (src == null) return false
  const set = props.descendantMap?.get(src)
  return !!set && set.has(target.id)
}
function isInvalidReorder(target: CategoryNode): boolean {
  const src = props.currentDragId
  if (src == null) return false
  const parentId = target.parentCategoryId ?? null
  if (parentId == null) return false
  const set = props.descendantMap?.get(src)
  return !!set && set.has(parentId)
}

function onDragStart(node: CategoryNode, e: DragEvent) {
    try {
        e.dataTransfer?.setData('text/plain', String(node.id))
        if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
    } catch {}
    emit('dragging', node.id)
}
function onDragOver(e: DragEvent) {
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
    hoverSelf.value = true
}
function onDrop(target: CategoryNode, e: DragEvent) {
    try {
        const src = e.dataTransfer?.getData('text/plain') ?? ''
        const srcId = Number(src)
        if (!srcId || srcId === target.id) return
        if (isInvalidMove(target)) return
        emit('move', srcId, target.id)
    } catch {}
    hoverSelf.value = false
}

function onDropReorder(target: CategoryNode, e: DragEvent, position: 'before' | 'after') {
  hoverTop.value = false
  hoverBottom.value = false
  try {
    const src = e.dataTransfer?.getData('text/plain') ?? ''
    const srcId = Number(src)
    if (!srcId || srcId === target.id) return
    if (isInvalidReorder(target)) return
    emit('reorder', srcId, target.id, position)
  } catch {}
}

function onLeave() {
  hoverSelf.value = false
}

onUnmounted(() => emit('dragend'))
</script>

<style scoped></style>


