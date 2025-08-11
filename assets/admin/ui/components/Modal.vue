<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="open"
        class="fixed inset-0 z-[1100] flex p-4"
        :class="containerClass"
        role="dialog"
        aria-modal="true"
        @keydown.esc.prevent="onEsc"
      >
        <!-- Overlay -->
        <div
          class="absolute inset-0 bg-neutral-900/50 backdrop-blur-[2px] dark:bg-black/60"
          @click="onBackdrop"
        />

        <Transition name="modal-zoom">
          <div
            class="relative w-full overflow-hidden rounded-xl bg-white text-neutral-900 shadow-xl ring-1 ring-input dark:bg-neutral-900 dark:text-neutral-100"
            :class="dialogClass"
          >
            <!-- Close button -->
            <button
              v-if="closable"
              type="button"
              class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:text-neutral-400 dark:hover:bg-white/5 dark:hover:text-neutral-200"
              aria-label="Close"
              @click="close"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd" d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
              </svg>
            </button>

            <!-- Header -->
            <div v-if="hasHeader" class="px-6 pt-6 pb-4 border-b border-border">
              <slot name="header">
                <h3 class="text-lg font-semibold">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ subtitle }}</p>
              </slot>
            </div>

            <!-- Body -->
            <div class="px-6 py-5">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="hasFooter" class="px-6 pb-6 pt-4 border-t border-border">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch, useSlots } from 'vue'
import { cn } from '@admin/utils/cn'

type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | '2xl'
type ModalAlign = 'center' | 'top'

const props = withDefaults(defineProps<{
  modelValue?: boolean
  title?: string
  subtitle?: string
  size?: ModalSize
  align?: ModalAlign
  closeOnEsc?: boolean
  closeOnBackdrop?: boolean
  preventScroll?: boolean
  zIndex?: number
  closable?: boolean
}>(), {
  modelValue: false,
  size: 'md',
  align: 'center',
  closeOnEsc: true,
  closeOnBackdrop: true,
  preventScroll: true,
  closable: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  open: []
  close: []
}>()

const open = computed<boolean>({
  get: () => !!props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const sizes: Record<ModalSize, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
}

const modalSize = computed<ModalSize>(() => props.size ?? 'md')

const dialogClass = computed<string>(() => cn(
  'mx-auto w-full',
  sizes[modalSize.value],
))

const containerClass = computed<string>(() => cn(
  props.align === 'center' ? 'items-center justify-center' : 'items-start justify-center pt-24',
  props.zIndex ? `z-[${props.zIndex}]` : undefined,
))

function onEsc() {
  if (!props.closeOnEsc) return
  if (!open.value) return
  close()
}

function onBackdrop(e: MouseEvent) {
  if (!props.closeOnBackdrop) return
  // закроем только при клике по подложке, не по диалогу
  if (e.target === e.currentTarget) {
    close()
  }
}

function close() {
  if (!open.value) return
  open.value = false
  emit('close')
}

function lockScroll(shouldLock: boolean) {
  if (!props.preventScroll) return
  const body = document.body
  if (shouldLock) {
    if (!body.classList.contains('overflow-hidden')) body.classList.add('overflow-hidden')
  } else {
    body.classList.remove('overflow-hidden')
  }
}

watch(open, (v) => {
  lockScroll(v)
  if (v) emit('open')
})

onMounted(() => {
  if (open.value) lockScroll(true)
})

onUnmounted(() => {
  lockScroll(false)
})

// header/footer presence
const slots = useSlots()
const hasHeader = computed<boolean>(() => !!(slots.header || props.title || props.subtitle))
const hasFooter = computed<boolean>(() => !!slots.footer)
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 200ms var(--ease-standard, cubic-bezier(0.2, 0, 0, 1));
}
.modal-fade-enter-from,
.modal-fade-leave-to { opacity: 0; }

.modal-zoom-enter-active,
.modal-zoom-leave-active {
  transition: transform 200ms var(--ease-decelerate, cubic-bezier(0, 0, 0.2, 1)), opacity 200ms var(--ease-decelerate, cubic-bezier(0, 0, 0.2, 1));
}
.modal-zoom-enter-from,
.modal-zoom-leave-to { transform: scale(0.96); opacity: 0; }
</style>


