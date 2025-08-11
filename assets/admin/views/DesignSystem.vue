<template>
  <div class="space-y-8">
    <header class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Design System</h1>
        <p class="text-sm text-muted-foreground mt-1">Токены, темы и базовые компоненты</p>
      </div>
      <div class="flex items-center gap-2">
        <Button @click="toggleTheme">Переключить тему</Button>
      </div>
    </header>

    <section class="rounded-xl border bg-card text-card-foreground p-6 shadow-sm">
      <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Токены цвета</h2>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <div v-for="token in colorTokens" :key="token.key" class="rounded-lg border overflow-hidden">
          <div class="h-12" :style="{ backgroundColor: token.css }"></div>
          <div class="p-2 text-xs">
            <div class="font-medium">{{ token.key }}</div>
            <div class="text-muted-foreground">{{ token.css }}</div>
          </div>
        </div>
      </div>
    </section>

    <section class="rounded-xl border bg-card text-card-foreground p-6 shadow-sm">
      <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Buttons</h2>
      <div class="flex flex-wrap gap-3">
        <Button>Primary</Button>
        <Button variant="secondary">Secondary</Button>
        <Button variant="outline">Outline</Button>
        <Button variant="destructive">Destructive</Button>
        <Button variant="ghost">Ghost</Button>
        <Button size="icon" aria-label="star">★</Button>
      </div>
    </section>

    <section class="rounded-xl border bg-card text-card-foreground p-6 shadow-sm">
      <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Form</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <Input v-model="email" label="Email" placeholder="you@example.com" />
        <Input v-model="name" label="Имя" />
      </div>
      <div class="mt-4 flex justify-end">
        <Button>Сохранить</Button>
      </div>
    </section>

    <section class="rounded-xl border bg-card text-card-foreground p-6 shadow-sm">
      <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Card</h2>
      <Card>
        <template #header>
          <div class="flex items-center justify-between w-full">
            <div class="font-medium">Заголовок</div>
            <Button size="sm" variant="outline">Действие</Button>
          </div>
        </template>
        <div class="text-sm text-muted-foreground">
          Карточка использует токены `card`/`card-foreground` и общий бордер.
        </div>
        <template #footer>
          <div class="ml-auto">
            <Button size="sm">Ок</Button>
          </div>
        </template>
      </Card>
    </section>

    <section class="rounded-xl border bg-card text-card-foreground p-6 shadow-sm">
      <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Modal</h2>
      <div class="flex gap-3">
        <Button @click="isModalOpen = true">Открыть модалку</Button>
      </div>

      <Modal v-model="isModalOpen" title="Подтвердите действие" subtitle="Это демо модального окна">
        <div class="space-y-3 text-sm text-muted-foreground">
          <p>Содержимое модалки. Используйте для подтверждений, форм и т.п.</p>
          <p class="text-neutral-700 dark:text-neutral-300">Поддерживаются слоты: header, default, footer.</p>
        </div>
        <template #footer>
          <div class="flex justify-end gap-3">
            <Button variant="outline" @click="isModalOpen = false">Отмена</Button>
            <Button variant="secondary" @click="isModalOpen = false">Подтвердить</Button>
          </div>
        </template>
      </Modal>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Button from '@admin/ui/components/Button.vue'
import Input from '@admin/ui/components/Input.vue'
import Card from '@admin/ui/components/Card.vue'
import Modal from '@admin/ui/components/Modal.vue'
import { useTheme } from '@admin/composables/useTheme'

const { toggleTheme } = useTheme()

const email = ref('')
const name = ref('')
const isModalOpen = ref(false)

const colorTokens = computed(() => [
  { key: 'background', css: 'hsl(var(--background))' },
  { key: 'foreground', css: 'hsl(var(--foreground))' },
  { key: 'primary', css: 'hsl(var(--primary))' },
  { key: 'secondary', css: 'hsl(var(--secondary))' },
  { key: 'accent', css: 'hsl(var(--accent))' },
  { key: 'muted', css: 'hsl(var(--muted))' },
  { key: 'destructive', css: 'hsl(var(--destructive))' },
  { key: 'card', css: 'hsl(var(--card))' },
])
</script>


