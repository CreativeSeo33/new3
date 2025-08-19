<template>
  <button
    :type="type"
    :class="cn(
      base,
      variants[variant],
      sizes[size],
      isDisabled ? 'opacity-50 pointer-events-none' : undefined,
      $attrs.class as string
    )"
    v-bind="{ ...$attrs, class: undefined }"
    :disabled="isDisabled"
  >
    <slot />
  </button>
  
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@admin/utils/cn'

type Variant = 'default' | 'primary' | 'secondary' | 'ghost' | 'destructive' | 'outline'
type Size = 'sm' | 'md' | 'lg' | 'icon'

const props = withDefaults(defineProps<{
  variant?: Variant
  size?: Size
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
}>(), {
  variant: 'default',
  size: 'md',
  type: 'button',
  disabled: false,
})

const base = 'inline-flex items-center justify-center whitespace-nowrap rounded-md font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 disabled:pointer-events-none'

const variants: Record<Variant, string> = {
  default: 'shadow-theme-xs inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-sm font-medium text-gray-700 ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.03]',
  primary: 'bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition',
  secondary: 'bg-brand-500 shadow-theme-xs hover:bg-brand-600 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-medium text-white transition',
  ghost: 'bg-transparent hover:bg-accent hover:text-accent-foreground',
  destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
  outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
}

const sizes: Record<Size, string> = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-9 px-4 text-sm',
  lg: 'h-10 px-6 text-base',
  icon: 'h-9 w-9',
}

const isDisabled = computed<boolean>(() => props.disabled)
const variant = computed<Variant>(() => props.variant)
const size = computed<Size>(() => props.size)
const type = computed<'button' | 'submit' | 'reset'>(() => props.type)
</script>


