<template>
  <div :class="cn('rounded-lg border bg-card text-card-foreground shadow-sm', $attrs.class as string)" v-bind="{ ...$attrs, class: undefined }">
    <div v-if="$slots.header" class="flex items-center p-4 border-b">
      <slot name="header" />
    </div>
    <div class="p-4">
      <slot />
    </div>
    <div v-if="$slots.footer" class="flex items-center p-4 border-t">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { cn } from '@admin/utils/cn'
</script>


