<template>
  <ul class="flex justify-center gap-1 text-gray-900">
    <li>
      <a
        href="#"
        :class="[
          'grid size-8 place-content-center rounded border border-gray-200 transition-colors hover:bg-gray-50 rtl:rotate-180',
          isFirst ? 'opacity-50 pointer-events-none' : undefined,
        ]"
        aria-label="Previous page"
        :aria-disabled="isFirst"
        :tabindex="isFirst ? -1 : 0"
        @click.prevent="goTo(currentPage - 1)"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="size-4"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
            clip-rule="evenodd"
          />
        </svg>
      </a>
    </li>

    <li v-for="item in paginationItems" :key="typeof item === 'number' ? `page-${item}` : item">
      <a
        v-if="typeof item === 'number' && item !== currentPage"
        href="#"
        class="block size-8 rounded border border-gray-200 text-center text-sm/8 font-medium transition-colors hover:bg-gray-50"
        @click.prevent="goTo(item)"
      >
        {{ item }}
      </a>

      <span
        v-else-if="typeof item === 'number'"
        class="block size-8 rounded border border-indigo-600 bg-indigo-600 text-center text-sm/8 font-medium text-white"
        aria-current="page"
      >
        {{ item }}
      </span>

      <span
        v-else
        class="grid size-8 place-content-center rounded border border-gray-200 text-center text-sm/8 font-medium text-gray-400 select-none"
        aria-hidden="true"
      >
        …
      </span>
    </li>

    <li>
      <a
        href="#"
        :class="[
          'grid size-8 place-content-center rounded border border-gray-200 transition-colors hover:bg-gray-50 rtl:rotate-180',
          isLast ? 'opacity-50 pointer-events-none' : undefined,
        ]"
        aria-label="Next page"
        :aria-disabled="isLast"
        :tabindex="isLast ? -1 : 0"
        @click.prevent="goTo(currentPage + 1)"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="size-4"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
            clip-rule="evenodd"
          />
        </svg>
      </a>
    </li>
  </ul>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  modelValue?: number
  totalPages?: number
}>(), {
  modelValue: 1,
  totalPages: 1,
})

const emit = defineEmits<{ 'update:modelValue': [value: number] }>()

const currentPage = computed<number>(() => Math.min(Math.max(1, props.modelValue ?? 1), Math.max(1, props.totalPages ?? 1)))
const totalPages = computed<number>(() => Math.max(1, props.totalPages ?? 1))

const isFirst = computed<boolean>(() => currentPage.value <= 1)
const isLast = computed<boolean>(() => currentPage.value >= totalPages.value)

// Ellipsized pagination items: numbers + two possible dots markers
type Dots = 'dots-left' | 'dots-right'
const paginationItems = computed<Array<number | Dots>>(() => {
  const tp = totalPages.value
  const cp = currentPage.value
  if (tp <= 7) {
    return Array.from({ length: tp }, (_, i) => i + 1)
  }

  const siblingCount = 1
  const boundaryCount = 1

  const range = (start: number, end: number) => Array.from({ length: end - start + 1 }, (_, i) => start + i)

  const startPages = range(1, Math.min(boundaryCount, tp))
  const endPages = range(Math.max(tp - boundaryCount + 1, boundaryCount + 1), tp)

  const siblingsStart = Math.max(
    Math.min(cp - siblingCount, tp - boundaryCount - siblingCount * 2 - 1),
    boundaryCount + 2
  )
  const siblingsEnd = Math.min(
    Math.max(cp + siblingCount, boundaryCount + siblingCount * 2 + 2),
    endPages[0] - 2
  )

  const items: Array<number | Dots> = []
  items.push(...startPages)

  if (siblingsStart > boundaryCount + 2) {
    items.push('dots-left')
  } else if (boundaryCount + 1 < tp - boundaryCount) {
    items.push(boundaryCount + 1)
  }

  items.push(...range(siblingsStart, siblingsEnd))

  if (siblingsEnd < tp - boundaryCount - 1) {
    items.push('dots-right')
  } else if (tp - boundaryCount > boundaryCount) {
    items.push(tp - boundaryCount)
  }

  items.push(...endPages)
  return items
})

function goTo(page: number) {
  const next = Math.min(Math.max(1, page), totalPages.value)
  if (next !== currentPage.value) emit('update:modelValue', next)
}
</script>


