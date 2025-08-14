<template>
  <DialogRoot v-model:open="open">
    <DialogPortal>
      <DialogOverlay class="fixed inset-0 bg-black/50 backdrop-blur-[1px]" />
      <DialogContent class="fixed left-1/2 top-1/2 w-[92vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border bg-white p-4 shadow-lg focus:outline-none dark:border-neutral-800 dark:bg-neutral-900">
        <div class="mb-2">
          <DialogTitle class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Добавить атрибут</DialogTitle>
          <DialogDescription class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Выберите атрибут из списка</DialogDescription>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
          <label class="block text-sm">
            <span class="mb-1 block text-neutral-600 dark:text-neutral-300">Атрибут</span>
            <select v-model="selected" class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900">
              <option disabled value="">Выберите…</option>
              <optgroup v-for="group in groupedOptions" :key="group.key" :label="group.title">
                <option v-for="opt in group.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </optgroup>
            </select>
          </label>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="h-9 rounded-md px-3 text-sm hover:bg-neutral-100 dark:hover:bg-white/10" @click="open = false">Отмена</button>
            <button type="submit" class="inline-flex h-9 items-center rounded-md bg-neutral-900 px-3 text-sm font-medium text-white shadow hover:bg-neutral-800 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-100" :disabled="!selected">Добавить</button>
          </div>
        </form>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { DialogContent, DialogDescription, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui'
import { AttributeRepository, type Attribute } from '@admin/repositories/AttributeRepository'
import { AttributeGroupRepository, type AttributeGroupDto } from '@admin/repositories/AttributeGroupRepository'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [boolean]; add: [attributeIri: string] }>()

const open = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })
const selected = ref<string>('')

const attrRepo = new AttributeRepository()
const groupRepo = new AttributeGroupRepository()

const attributes = ref<Attribute[]>([])
const groups = ref<AttributeGroupDto[]>([])

onMounted(async () => {
  const [attrs, grps] = await Promise.all([
    attrRepo.findAllCached() as any,
    groupRepo.findAllCached() as any,
  ])
  attributes.value = (attrs['hydra:member'] ?? attrs.member ?? []) as Attribute[]
  groups.value = (grps['hydra:member'] ?? grps.member ?? []) as AttributeGroupDto[]
})

const groupedOptions = computed(() => {
  const groupMap = new Map<string, { title: string; options: Array<{ value: string; label: string }> }>()
  const titleFor = (gIri: string | null): string => {
    if (!gIri) return 'Без группы'
    const g = groups.value.find(x => x['@id'] === gIri || `/api/attribute_groups/${x.id}` === gIri)
    return g?.name || `Группа ${g?.id ?? ''}`
  }
  for (const a of attributes.value) {
    const raw = (a as any).attributeGroup as any
    const iri = typeof raw === 'string' ? raw : raw?.['@id'] ?? (raw?.id ? `/api/attribute_groups/${raw.id}` : null)
    const key = iri ?? '__no_group__'
    const title = titleFor(iri)
    if (!groupMap.has(key)) groupMap.set(key, { title, options: [] })
    groupMap.get(key)!.options.push({ value: `/api/attributes/${a.id}`, label: a.name ?? `Атрибут #${a.id}` })
  }
  // порядок групп как в groups
  const orderedKeys = ['__no_group__', ...groups.value.map(g => g['@id'] as string)]
  return orderedKeys.filter(k => groupMap.has(k)).map(k => ({ key: k, title: groupMap.get(k)!.title, options: groupMap.get(k)!.options }))
})

function submit() {
  if (!selected.value) return
  emit('add', selected.value)
  selected.value = ''
  open.value = false
}
</script>

<style scoped></style>


