<template>
  <div class="grid gap-1.5">
    <label v-if="label" :for="id" class="text-sm font-medium text-foreground/80">{{ label }}</label>
    <input
      :id="id"
      v-model="model"
      :type="type"
      :placeholder="placeholder"
      :class="cn(
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background',
        'placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        $attrs.class as string
      )"
      v-bind="{ ...$attrs, class: undefined }"
    />
    <p v-if="description" class="text-xs text-muted-foreground">{{ description }}</p>
    <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@admin/utils/cn'

const props = withDefaults(defineProps<{
  id?: string
  modelValue?: string
  type?: string
  placeholder?: string
  label?: string
  description?: string
  error?: string
}>(), {
  type: 'text',
  modelValue: '',
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const model = computed({
  get: () => props.modelValue ?? '',
  set: (v: string) => emit('update:modelValue', v),
})
</script>


