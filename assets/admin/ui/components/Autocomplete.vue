<template>
  <div class="grid gap-1.5">
    <label v-if="label" :for="inputId" class="text-sm font-medium text-foreground/80">{{ label }}</label>

    <PopoverRoot :open="open">
      <PopoverTrigger as-child>
        <div class="relative">
          <Input
            :id="inputId"
            v-model="searchText"
            :placeholder="placeholder"
            :class="{ 'pr-8': loading }"
            @focus="onFocus"
            @keydown.esc.prevent="close"
          />
          <svg
            v-if="loading"
            class="absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-muted-foreground"
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              d="M12 4a8 8 0 1 1-7.446 5.032"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </div>
      </PopoverTrigger>

      <PopoverPortal>
        <PopoverContent
          class="w-[var(--radix-popover-trigger-width)] rounded-md border bg-white shadow-md p-0 z-[60]"
          align="start"
          side="bottom"
          :side-offset="4"
          :avoid-collisions="true"
        >
          <div class="max-h-72 overflow-auto">
            <template v-if="error">
              <div class="px-3 py-2 text-sm text-destructive">{{ error }}</div>
            </template>
            <template v-else-if="!loading && items.length === 0 && searchText.length >= minQueryLength">
              <div class="px-3 py-2 text-sm text-muted-foreground">Ничего не найдено</div>
            </template>
            <ul v-else>
              <li
                v-for="item in items"
                :key="String(item.id)"
                class="px-3 py-2 text-sm hover:bg-muted cursor-pointer flex items-center gap-2"
                @mousedown.prevent="selectItem(item)"
              >
                <img
                  v-if="item.firstImageUrl"
                  :src="item.firstImageUrl"
                  :alt="item.name || ''"
                  class="h-8 w-8 rounded object-cover border border-muted"
                  loading="lazy"
                  referrerpolicy="no-referrer"
                />
                <div class="truncate">{{ item.name || '—' }}</div>
              </li>
            </ul>
          </div>
        </PopoverContent>
      </PopoverPortal>
    </PopoverRoot>

    <p v-if="description" class="text-xs text-muted-foreground">{{ description }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'reka-ui'
import Input from './Input.vue'

// Local lightweight Hydra collection type to avoid cross-alias issues in UI layer
type HydraCollection<T = any> = {
  '@context'?: string
  '@id'?: string
  '@type'?: string
  'hydra:member'?: T[]
  member?: T[]
  'hydra:totalItems'?: number
  totalItems?: number
}

export interface AutocompleteItem {
  id: number | string
  name: string | null
  firstImageUrl?: string | null
}

const props = withDefaults(defineProps<{
  label?: string
  description?: string
  placeholder?: string
  minQueryLength?: number
  limit?: number
  search: (query: string, limit: number) => Promise<HydraCollection<AutocompleteItem>>
}>(), {
  minQueryLength: 3,
  limit: 10,
})

const emit = defineEmits<{
  (e: 'select', item: AutocompleteItem): void
}>()

const inputId = `ac-${Math.random().toString(36).slice(2, 8)}`
const open = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)
const items = ref<AutocompleteItem[]>([])
const searchText = ref('')
let debounceTimer: number | null = null

function onFocus(): void {
  if (searchText.value.length >= props.minQueryLength && (items.value.length > 0 || loading.value)) {
    open.value = true
  }
}

function close(): void {
  open.value = false
}

async function performSearch(query: string): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const res = await props.search(query, props.limit) as any
    const list = (res['hydra:member'] ?? res.member ?? []) as AutocompleteItem[]
    items.value = list.slice(0, props.limit)
    open.value = true
  } catch (e: any) {
    // 401/422/500 нормализуются HttpClient → message
    error.value = e?.message || 'Ошибка загрузки'
    items.value = []
    open.value = true
  } finally {
    loading.value = false
  }
}

watch(
  () => searchText.value,
  (q: string) => {
    if (debounceTimer) {
      window.clearTimeout(debounceTimer)
      debounceTimer = null
    }
    const trimmed = (q ?? '').trim()

    if (trimmed.length < props.minQueryLength) {
      items.value = []
      error.value = null
      loading.value = false
      open.value = false
      return
    }

    debounceTimer = window.setTimeout(() => {
      void performSearch(trimmed)
    }, 300)
  }
)

function selectItem(item: AutocompleteItem): void {
  emit('select', item)
  close()
}

onBeforeUnmount(() => {
  if (debounceTimer) {
    window.clearTimeout(debounceTimer)
  }
})
</script>

<style scoped>
</style>


