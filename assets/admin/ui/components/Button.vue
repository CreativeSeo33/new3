<template>
  <button
    :type="type"
    :class="cn(
      base,
      variants[variant],
      sizes[size],
      { 'opacity-50 pointer-events-none': isDisabled },
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

type Variant = 'default' | 'secondary' | 'ghost' | 'destructive' | 'outline'
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
  default: 'bg-primary text-primary-foreground hover:bg-primary/90',
  secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
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

const isDisabled = computed(() => props.disabled)
const variant = computed(() => props.variant)
const size = computed(() => props.size)
const type = computed(() => props.type)
</script>


