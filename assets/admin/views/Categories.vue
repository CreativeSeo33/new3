<template>
	<div class="space-y-6">
		<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
			<div>
				<h1 class="text-xl font-semibold text-neutral-900 dark:text-neutral-100">Categories</h1>
				<p class="mt-1 text-sm text-neutral-500">Дерево категорий.</p>
			</div>
			<div class="flex items-center gap-2">
				<RouterLink
					:to="{ name: 'admin-category-form', params: { id: 'new' } }"
					class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100"
				>
					Add Category
				</RouterLink>
			</div>
		</div>

		<div class="rounded-md border p-2 dark:border-neutral-800">
			<!-- Drop-to-root zone -->
			<div
				class="mb-2 rounded-md border border-dashed p-2 text-center text-sm dark:border-neutral-700"
				:class="rootHover ? 'bg-blue-50 dark:bg-white/10' : ''"
				@dragover.prevent="rootHover = true"
				@dragleave="rootHover = false"
				@drop.prevent="(e) => handleMoveToRoot(e)"
			>
				Перетащите сюда, чтобы сделать корневой
			</div>
			<TreeRoot :items="treeItems" :get-key="(n) => String(n.id)" :get-children="(n) => n.children" :expanded="expandedAll">
				<ul>
					<CategoryTree :tree-items="treeItems" :current-drag-id="currentDragId || null" :descendant-map="descendantMap" @dragging="(id) => (currentDragId = id)" @dragend="() => (currentDragId = null)" @delete="handleDelete" @move="handleMove" @reorder="handleReorder" />
				</ul>
			</TreeRoot>
			<div v-if="loading" class="px-4 py-8 text-center text-neutral-500">Загрузка…</div>
			<div v-else-if="treeItems.length === 0" class="px-4 py-8 text-center text-neutral-500">Пока нет категорий</div>
		</div>

		<ToastRoot v-for="n in toastCount" :key="n" type="foreground" :duration="3000">
			<ToastDescription>{{ lastToastMessage }}</ToastDescription>
		</ToastRoot>

		<!-- Confirm dialog -->
		<ConfirmDialog v-model="confirmOpen" :title="'Подтверждение'" :description="confirmText" @confirm="performPendingAction" />
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref as vueRef } from 'vue'
import { RouterLink } from 'vue-router'
import { ToastDescription, ToastRoot, TreeRoot } from 'reka-ui'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import CategoryTree from '@admin/components/CategoryTree.vue'
import { useCrud } from '@admin/composables/useCrud'
import { CategoryRepository, type CategoryDto } from '@admin/repositories/CategoryRepository'

type CategoryNode = {
	id: number
	name: string | null
	slug?: string | null
	parentCategoryId?: number | null
	sortOrder?: number | null
	children?: CategoryNode[]
}

const repo = new CategoryRepository()
const crud = useCrud<CategoryDto>(repo)
const state = crud.state
const loading = computed(() => !!state.loading)

const treeItems = computed<CategoryNode[]>(() => buildTree((state.items ?? []) as CategoryDto[]))
const expandedAll = computed<string[]>(() => collectExpandedKeys(treeItems.value))
const descendantMap = computed(() => buildDescendantMap(treeItems.value))
const currentDragId = vueRef<number | null>(null)
const rootHover = vueRef(false)

onMounted(async () => {
	await crud.fetchAll({ itemsPerPage: 1000, sort: { sortOrder: 'asc', name: 'asc' } })
})

function buildTree(items: CategoryDto[]): CategoryNode[] {
	const byId = new Map<number, CategoryNode>()
	const roots: CategoryNode[] = []
	for (const i of items) {
		if (!i.id && i.id !== 0) continue
		byId.set(Number(i.id), { id: Number(i.id), name: i.name ?? null, slug: i.slug ?? null, parentCategoryId: (i as any).parentCategoryId ?? null, sortOrder: (i as any).sortOrder ?? null, children: [] })
	}
	for (const node of byId.values()) {
		if (node.parentCategoryId && byId.has(node.parentCategoryId)) {
			byId.get(node.parentCategoryId)!.children!.push(node)
		} else {
			roots.push(node)
		}
	}
	const sortRec = (nodes: CategoryNode[]) => {
		nodes.sort((a, b) => {
			const ao = a.sortOrder
			const bo = b.sortOrder
			if (ao != null && bo != null) return ao - bo
			if (ao != null) return -1
			if (bo != null) return 1
			return String(a.name || '').localeCompare(String(b.name || ''))
		})
		nodes.forEach((n) => n.children && sortRec(n.children))
	}
	sortRec(roots)
	return roots
}

function collectExpandedKeys(nodes: CategoryNode[]): string[] {
    const keys: string[] = []
    const walk = (ns: CategoryNode[]) => {
        for (const n of ns) {
            keys.push(String(n.id))
            if (n.children && n.children.length) walk(n.children)
        }
    }
    walk(nodes)
    return keys
}

function buildDescendantMap(nodes: CategoryNode[]): Map<number, Set<number>> {
    const map = new Map<number, Set<number>>()
    const dfs = (node: CategoryNode): Set<number> => {
        const set = new Set<number>()
        if (node.children) {
            for (const ch of node.children) {
                set.add(ch.id)
                const chSet = dfs(ch)
                chSet.forEach((v) => set.add(v))
            }
        }
        map.set(node.id, set)
        return set
    }
    for (const n of nodes) dfs(n)
    return map
}

async function handleDelete(id: number) {
    openConfirm(`Удалить категорию #${id}?`, async () => {
        await crud.remove(id)
        publishToast('Категория удалена')
    })
}

async function handleMove(sourceId: number, targetId: number) {
    if (sourceId === targetId) return
    if (isDescendant(treeItems.value, targetId, sourceId)) {
        publishToast('Нельзя переместить в собственную дочернюю ветку')
        return
    }
    openConfirm(`Переместить #${sourceId} внутрь #${targetId}?`, async () => {
        await repo.partialUpdate(sourceId, { parentCategoryId: targetId } as any)
        await crud.fetchAll({ itemsPerPage: 1000 })
        publishToast('Категория перемещена')
    })
}

async function handleMoveToRoot(e: DragEvent) {
    rootHover.value = false
    const src = e.dataTransfer?.getData('text/plain') ?? ''
    const srcId = Number(src)
    if (!srcId) return
    openConfirm(`Сделать категорию #${srcId} корневой?`, async () => {
        await repo.partialUpdate(srcId, { parentCategoryId: null } as any)
        await crud.fetchAll({ itemsPerPage: 1000 })
        publishToast('Категория вынесена в корень')
    })
}

function isDescendant(roots: CategoryNode[], potentialParentId: number, potentialChildId: number): boolean {
    const byId = new Map<number, CategoryNode>()
    const stack: CategoryNode[] = []
    const collect = (nodes: CategoryNode[]) => {
        for (const n of nodes) {
            byId.set(n.id, n)
            if (n.children) collect(n.children)
        }
    }
    collect(roots)
    const parent = byId.get(potentialChildId)
    if (!parent) return false
    stack.push(parent)
    while (stack.length) {
        const cur = stack.pop()!
        if (cur.id === potentialParentId) return true
        if (cur.children) stack.push(...cur.children)
    }
    return false
}

async function handleReorder(sourceId: number, targetId: number, position: 'before' | 'after') {
    if (sourceId === targetId) return
    openConfirm(`Переместить #${sourceId} ${position} #${targetId}?`, async () => {
        const items = (state.items ?? []) as CategoryDto[]
        const src = items.find(i => Number(i.id) === Number(sourceId)) as any
        const tgt = items.find(i => Number(i.id) === Number(targetId)) as any
        if (!tgt || !src) return
        const newParentId = (tgt.parentCategoryId ?? null) as number | null
        // соберём будущих соседей под newParentId
        const siblings = items
            .filter(i => ((i as any).parentCategoryId ?? null) === newParentId)
            .map(i => ({ id: Number(i.id), sortOrder: (i as any).sortOrder as number | null }))
        // убрать source из его старого места (если он в этом же списке)
        const existingIdx = siblings.findIndex(s => s.id === Number(sourceId))
        if (existingIdx !== -1) siblings.splice(existingIdx, 1)
        // найти позицию target
        const targetIdx = siblings.findIndex(s => s.id === Number(targetId))
        const insertIdx = position === 'before' ? targetIdx : targetIdx + 1
        siblings.splice(insertIdx, 0, { id: Number(sourceId), sortOrder: null })
        // перенумерация с шагом 10
        const updates: Array<Promise<any>> = []
        for (let i = 0; i < siblings.length; i++) {
            const desiredOrder = (i + 1) * 10
            const s = siblings[i]
            if (s.id === Number(sourceId)) {
                updates.push(repo.partialUpdate(s.id, { parentCategoryId: newParentId, sortOrder: desiredOrder } as any))
            } else if (s.sortOrder !== desiredOrder) {
                updates.push(repo.partialUpdate(s.id, { sortOrder: desiredOrder } as any))
            }
        }
        await Promise.all(updates)
        await crud.fetchAll({ itemsPerPage: 1000, sort: { sortOrder: 'asc', name: 'asc' } })
        publishToast('Позиция обновлена')
    })
}

// toasts
const toastCount = vueRef(0)
const lastToastMessage = vueRef('')
function publishToast(message: string) {
	lastToastMessage.value = message
	toastCount.value++
}

// AlertDialog confirm
const confirmOpen = vueRef(false)
const confirmText = vueRef('')
let pendingAction: null | (() => Promise<void> | void) = null
function openConfirm(text: string, action: () => Promise<void> | void) {
  confirmText.value = text
  pendingAction = action
  confirmOpen.value = true
}
async function performPendingAction() {
  try {
    if (pendingAction) await pendingAction()
  } finally {
    pendingAction = null
    confirmOpen.value = false
  }
}
</script>

<style scoped></style>

 
