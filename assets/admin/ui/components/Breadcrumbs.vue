<template>
  <nav class="text-sm text-slate-500" aria-label="Breadcrumb">
    <ol class="flex items-center gap-2 flex-wrap">
      <li v-for="(crumb, index) in breadcrumbs" :key="index" class="flex items-center gap-2">
        <span v-if="index !== 0" class="text-slate-400">/</span>
        <RouterLink
          v-if="crumb.to && !crumb.current"
          :to="crumb.to"
          class="hover:text-slate-700 hover:underline underline-offset-2"
        >
          {{ crumb.label }}
        </RouterLink>
        <span v-else class="text-slate-700 font-medium">{{ crumb.label }}</span>
      </li>
    </ol>
  </nav>
  
  <div v-if="breadcrumbs.length" class="h-3" />
  
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, type RouteLocationRaw } from 'vue-router'

type BreadcrumbItem = {
  label: string
  to: RouteLocationRaw | null
  current: boolean
}

const route = useRoute()

const LABELS: Record<string, string> = {
  'admin-dashboard': 'Главная',
  'admin-orders': 'Заказы',
  'admin-products': 'Товары',
  'admin-product-form': 'Товар',
  'admin-categories': 'Категории',
  'admin-category-form': 'Категория',
  'admin-attributes': 'Атрибуты',
  'admin-attribute-groups': 'Группы атрибутов',
  'admin-yandex-delivery': 'Яндекс Доставка',
  'admin-design-system': 'Дизайн-система',
  'AdminNotFound': 'Не найдено'
}

const PARENT_FOR: Record<string, string> = {
  'admin-product-form': 'admin-products',
  'admin-category-form': 'admin-categories'
}

function resolveToByName(name: string): RouteLocationRaw | null {
  return { name }
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
  const list: BreadcrumbItem[] = []

  // Home always first
  const homeTo = resolveToByName('admin-dashboard')
  list.push({ label: LABELS['admin-dashboard'], to: homeTo, current: false })

  const currentName = String(route.name ?? '')

  if (!currentName) {
    // Unknown route, just mark home as current
    list[0].to = null
    list[0].current = true
    return list
  }

  // Спец-случай: страница заказа — показываем «Заказы» + номер заказа
  if (currentName === 'admin-order') {
    const parentTo = resolveToByName('admin-orders')
    list.push({ label: LABELS['admin-orders'] ?? 'Заказы', to: parentTo, current: false })
    const rawId = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id
    const idLabel = String(rawId ?? '').trim() || 'Заказ'
    list.push({ label: idLabel, to: null, current: true })
    return list
  }

  // If current has a declared parent (edit/detail pages), show only parent as current
  const parentName = PARENT_FOR[currentName]
  if (parentName) {
    const parentTo = resolveToByName(parentName)
    list.push({ label: LABELS[parentName] ?? parentName, to: parentTo, current: false })
    return list
  }

  // For regular list-like pages, show them as current
  if (LABELS[currentName]) {
    list.push({ label: LABELS[currentName], to: null, current: true })
    // If dashboard itself, collapse to single crumb
    if (currentName === 'admin-dashboard') {
      return [{ label: LABELS['admin-dashboard'], to: null, current: true }]
    }
    return list
  }

  // Fallback: derive label from path
  const last = route.path.split('/').filter(Boolean).pop() ?? ''
  const label = last.replace(/[-_]/g, ' ').replace(/\b\w/g, m => m.toUpperCase()) || 'Страница'
  list.push({ label, to: null, current: true })
  return list
})
</script>

<style scoped>
</style>


