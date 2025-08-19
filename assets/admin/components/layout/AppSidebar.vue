<template>
  <div
    class="fixed inset-y-0 left-0 z-40 w-64 transform bg-white border-r shadow-sm transition-transform duration-200 md:translate-x-0"
    :class="{ '-translate-x-full': !open }"
  >
    <div class="h-14 flex items-center border-b px-4">
      <span class="text-base font-semibold">Admin</span>
    </div>
    <nav class="p-3 space-y-1">
      <template v-for="item in items" :key="getItemKey(item)">
        <router-link
          v-if="!isGroup(item)"
          :to="item.to"
          class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100 transition-colors"
          active-class="bg-gray-100 text-slate-900"
        >
    
          {{ item.label }}
        </router-link>

        <div v-else class="space-y-1">
          <button
            @click="toggleGroup(item.label)"
            class="w-full flex items-center justify-between gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100 transition-colors"
            :class="{ 'bg-gray-50': expandedGroups.has(item.label) }"
          >
            <span class="flex items-center gap-3">
              
              {{ item.label }}
            </span>
            <svg
              class="w-4 h-4 transition-transform duration-200"
              :class="{ 'rotate-90': expandedGroups.has(item.label) }"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>

          <div v-show="expandedGroups.has(item.label)" class="ml-4 space-y-1 border-l border-gray-200 pl-4">
            <router-link
              v-for="child in item.children"
              :key="typeof child.to === 'string' ? child.to : child.to.name"
              :to="child.to"
              class="flex items-center gap-3 rounded-md px-3 py-2 text-sm hover:bg-gray-100 transition-colors"
              active-class="bg-gray-100 text-slate-900"
            >
              
              {{ child.label }}
            </router-link>
          </div>
        </div>
      </template>
    </nav>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import type { AdminSidebarItem } from '@admin/router/modules/admin';
import { adminSidebarItems, isGroup } from '@admin/router/modules/admin';

defineProps<{ open: boolean }>();

const route = useRoute();
const items: AdminSidebarItem[] = adminSidebarItems;
const expandedGroups = ref<Set<string>>(new Set());

function getItemKey(item: AdminSidebarItem): string {
  if (isGroup(item)) return `group-${item.label}`;
  return typeof item.to === 'string' ? item.to : item.to.name;
}

function toggleGroup(groupLabel: string) {
  const next = new Set(expandedGroups.value);
  if (next.has(groupLabel)) next.delete(groupLabel); else next.add(groupLabel);
  expandedGroups.value = next;
}

function expandActiveGroup() {
  const currentRouteName = route.name as string | undefined;
  if (!currentRouteName) return;
  const next = new Set(expandedGroups.value);
  for (const item of items) {
    if (isGroup(item)) {
      const hasActiveChild = item.children.some((child) => {
        const name = typeof child.to === 'string' ? child.to : child.to.name;
        return name === currentRouteName;
      });
      if (hasActiveChild) next.add(item.label);
    }
  }
  expandedGroups.value = next;
}

onMounted(() => {
  expandActiveGroup();
});

watch(() => route.name, () => {
  expandActiveGroup();
});
</script>

<style scoped>
</style>


