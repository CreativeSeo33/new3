<template>
  <div class="space-y-4">
    <h1 class="text-lg font-semibold">Фасетный фильтр</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="md:col-span-1 space-y-3">
        <label class="block text-sm">Категория</label>
        <select v-model="selected" class="border rounded px-2 py-1 w-full">
          <option :value="null">Глобально</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name ?? ('#' + c.id) }}</option>
        </select>

        <div class="flex gap-2">
          <button class="px-3 py-1 bg-emerald-600 text-white rounded" @click="handleSave">Сохранить</button>
          <button class="px-3 py-1 bg-sky-600 text-white rounded" @click="handleReindex">Перестроить справочники</button>
          <button class="px-3 py-1 bg-gray-200 rounded" @click="handleReset">Сбросить</button>
        </div>
      </div>

      <div class="md:col-span-2 space-y-4">
        <div>
          <h2 class="font-medium mb-2">Выбор фильтров</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h3 class="text-sm font-medium mb-2">Опции</h3>
              <div class="bg-white border rounded p-2 max-h-80 overflow-auto space-y-1">
                <div v-if="optionsAll.length === 0" class="text-xs text-gray-400">Нет данных</div>
                <div v-for="o in optionsAll" :key="'opt-' + o.id" class="flex items-start gap-2 text-sm">
                  <input class="mt-1" type="checkbox" v-model="chosenOptionCodes" :value="o.code" :disabled="!o.code" />
                  <div class="flex-1 grid grid-cols-2 gap-2">
                    <div>
                      <div class="text-xs text-gray-500">Название</div>
                      <input type="text" class="border rounded px-2 py-1 w-full" :placeholder="o.name ?? ('#' + o.id)" :value="getOptionLabel(o.code)" @input="(e:any)=>setItemLabel(String(o.code), e.target.value, 'option')" :disabled="!o.code" />
                    </div>
                    <div>
                      <div class="text-xs text-gray-500">Порядок</div>
                      <input type="number" class="border rounded px-2 py-1 w-full" :value="getOptionOrder(o.code)" @input="(e:any)=>setItemOrder(String(o.code), e.target.value, 'option')" :disabled="!o.code" />
                    </div>
                    <div class="col-span-2 text-xs text-gray-400" v-if="o.code">Код: {{ o.code }}</div>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <h3 class="text-sm font-medium mb-2">Атрибуты</h3>
              <div class="bg-white border rounded p-2 max-h-80 overflow-auto space-y-1">
                <div v-if="attributesAll.length === 0" class="text-xs text-gray-400">Нет данных</div>
                <div v-for="a in attributesAll" :key="'attr-' + a.id" class="flex items-start gap-2 text-sm">
                  <input class="mt-1" type="checkbox" v-model="chosenAttributeCodes" :value="a.code" :disabled="!a.code" />
                  <div class="flex-1 grid grid-cols-2 gap-2">
                    <div>
                      <div class="text-xs text-gray-500">Название</div>
                      <input type="text" class="border rounded px-2 py-1 w-full" :placeholder="a.name ?? ('#' + a.id)" :value="getAttributeLabel(a.code)" @input="(e:any)=>setItemLabel(String(a.code), e.target.value, 'attribute')" :disabled="!a.code" />
                    </div>
                    <div>
                      <div class="text-xs text-gray-500">Порядок</div>
                      <input type="number" class="border rounded px-2 py-1 w-full" :value="getAttributeOrder(a.code)" @input="(e:any)=>setItemOrder(String(a.code), e.target.value, 'attribute')" :disabled="!a.code" />
                    </div>
                    <div class="col-span-2 text-xs text-gray-400" v-if="a.code">Код: {{ a.code }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div>
          <h2 class="font-medium mb-2">Доступные (из словаря)</h2>
          <pre class="bg-gray-50 border rounded p-2 text-xs overflow-auto" v-if="available">{{ available }}</pre>
        </div>

        <div>
          <h2 class="font-medium mb-2">Конфигурация</h2>
          <pre class="bg-gray-50 border rounded p-2 text-xs overflow-auto" v-if="config">{{ config }}</pre>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { FacetRepository, type FacetAvailableDto, type FacetConfigDto } from '../repositories/FacetRepository'
import { httpClient } from '../services/http'
import { OptionRepository, type Option } from '../repositories/OptionRepository'
import { AttributeRepository, type Attribute as AttributeDto } from '../repositories/AttributeRepository'

const repo = new FacetRepository()
const optionRepo = new OptionRepository()
const attributeRepo = new AttributeRepository()
const selected = ref<number|null>(null)
const available = ref<FacetAvailableDto|null>(null)
const config = ref<FacetConfigDto|null>(null)
const categories = ref<Array<{ id: number; name: string|null }>>([])

const optionsAll = ref<Array<{ id: number; name: string|null; code: string|null; sortOrder?: number|null }>>([])
const attributesAll = ref<Array<{ id: number; name: string|null; code: string|null; sortOrder?: number|null }>>([])

const chosenOptionCodes = ref<string[]>([])
const chosenAttributeCodes = ref<string[]>([])

const knownOptionCodes = computed(() => optionsAll.value.map(o => o.code).filter((c): c is string => !!c))
const knownAttributeCodes = computed(() => attributesAll.value.map(a => a.code).filter((c): c is string => !!c))

// Локальные типы элементов конфигурации
type FacetWidget = 'checkbox' | 'range'
interface OptionConfigItem { id?: number; code: string; enabled: boolean; widget: FacetWidget; label?: string | null; order?: number | null; bins?: number | [number, number][] }
interface AttributeConfigItem { id?: number; code: string; enabled: boolean; widget: FacetWidget; label?: string | null; operator?: 'OR' | 'AND'; order?: number | null; bins?: number | [number, number][] }

async function loadCategories() {
  const { data } = await httpClient.getJson<{ tree: any[] }>('/admin/categories/tree')
  // Плоский список из дерева
  const flat: Array<{ id: number; name: string|null }> = []
  function walk(nodes: any[]) {
    for (const n of nodes) { flat.push({ id: n.id, name: n.name }); if (n.children) walk(n.children) }
  }
  walk((data && (data as any).tree) || [])
  categories.value = flat
}

async function loadAll() {
  try {
    available.value = await repo.getAvailable(selected.value)
  } catch {
    available.value = null
  }
  try {
    config.value = await repo.getConfig(selected.value === null ? 'global' : selected.value)
  } catch {
    config.value = makeDefaultConfig()
  }
}

async function handleSave() {
  if (!config.value) {
    config.value = makeDefaultConfig()
  }
  // перед сохранением: убедимся, что выбранные чекбоксами элементы помечены enabled=true, а остальные false
  applySelectionsToConfig()
  await repo.saveConfig({ ...config.value, categoryId: selected.value })
}

async function handleReindex() {
  const attrs = chosenAttributeCodes.value.slice()
  const opts = chosenOptionCodes.value.slice()
  await repo.reindex(selected.value ?? 'all', { attributes: attrs, options: opts })
}

async function handleReset() {
  await loadAll()
  syncSelectionsFromConfig()
}

function getMembers<T>(res: any): T[] {
  if (Array.isArray(res)) return res as T[]
  return ((res && (res['hydra:member'] ?? res.member)) || []) as T[]
}

function sortBySortOrderAndName<T extends { sortOrder?: number|null; name?: string|null }>(items: T[]): T[] {
  const list = items.slice()
  list.sort((a: any, b: any) => {
    const ao = a?.sortOrder ?? null
    const bo = b?.sortOrder ?? null
    if (ao != null && bo != null) return Number(ao) - Number(bo)
    if (ao != null) return -1
    if (bo != null) return 1
    return String(a?.name || '').localeCompare(String(b?.name || ''))
  })
  return list
}

async function loadDicts() {
  const [optsRes, attrsRes] = await Promise.all([
    optionRepo.findAll({ itemsPerPage: 1000, sort: { sortOrder: 'asc', name: 'asc' } }),
    attributeRepo.findAllCached(true),
  ])
  const opts = getMembers<Option>(optsRes)
  const attrs = getMembers<AttributeDto>(attrsRes)
  optionsAll.value = sortBySortOrderAndName(opts).map((o: any) => ({
    id: Number(o.id),
    name: (o.name ?? null) as string | null,
    code: (o.code ?? null) as string | null,
    sortOrder: (o.sortOrder ?? null) as number | null,
  }))
  attributesAll.value = sortBySortOrderAndName(attrs).map((a: any) => ({
    id: Number(a.id),
    name: (a.name ?? null) as string | null,
    code: (a.code ?? null) as string | null,
    sortOrder: (a.sortOrder ?? null) as number | null,
  }))
}

function syncSelectionsFromConfig() {
  if (!config.value) {
    chosenOptionCodes.value = []
    chosenAttributeCodes.value = []
    return
  }
  const opt = Array.isArray(config.value.options) ? config.value.options : []
  const attr = Array.isArray(config.value.attributes) ? config.value.attributes : []
  chosenOptionCodes.value = opt.filter(o => o.enabled).map(o => o.code).filter((c): c is string => !!c)
  chosenAttributeCodes.value = attr.filter(a => a.enabled).map(a => a.code).filter((c): c is string => !!c)
}

function rebuildOptionItems(existing: OptionConfigItem[], selectedSet: Set<string>, knownCodes: string[]): OptionConfigItem[] {
  const prevMap = new Map<string, OptionConfigItem>(existing.map(i => [i.code, i]))
  const codes = Array.from(new Set<string>([...knownCodes, ...existing.map(i => i.code)]))
  return codes.map((code) => {
    const prev = prevMap.get(code)
    const item: OptionConfigItem = {
      code,
      enabled: selectedSet.has(code),
      widget: prev?.widget ?? 'checkbox',
    }
    if (prev?.label != null) item.label = prev.label
    if (prev?.order != null) item.order = prev.order
    if (prev?.bins != null) item.bins = prev.bins
    return item
  })
}

function rebuildAttributeItems(existing: AttributeConfigItem[], selectedSet: Set<string>, knownCodes: string[]): AttributeConfigItem[] {
  const prevMap = new Map<string, AttributeConfigItem>(existing.map(i => [i.code, i]))
  const codes = Array.from(new Set<string>([...knownCodes, ...existing.map(i => i.code)]))
  return codes.map((code) => {
    const prev = prevMap.get(code)
    const item: AttributeConfigItem = {
      code,
      enabled: selectedSet.has(code),
      widget: prev?.widget ?? 'checkbox',
    }
    if (prev?.label != null) item.label = prev.label
    if (prev?.operator != null) item.operator = prev.operator
    if (prev?.order != null) item.order = prev.order
    if (prev?.bins != null) item.bins = prev.bins
    return item
  })
}

function applySelectionsToConfig() {
  if (!config.value) return
  const selectedOpt = new Set(chosenOptionCodes.value)
  const selectedAttr = new Set(chosenAttributeCodes.value)
  config.value = {
    ...config.value,
    options: rebuildOptionItems((config.value.options as OptionConfigItem[] | undefined) ?? [], selectedOpt, knownOptionCodes.value),
    attributes: rebuildAttributeItems((config.value.attributes as AttributeConfigItem[] | undefined) ?? [], selectedAttr, knownAttributeCodes.value),
  }
}

// Редактирование отображаемого имени и порядка для выбранных элементов
function setItemLabel(code: string, label: string, kind: 'option' | 'attribute'): void {
  if (!config.value) return
  if (kind === 'option') {
    const list: any[] = (config.value.options as any[]) ?? []
    const item = list.find(i => i.code === code)
    if (item) item.label = label
    config.value.options = list as any
  } else {
    const list: any[] = (config.value.attributes as any[]) ?? []
    const item = list.find(i => i.code === code)
    if (item) item.label = label
    config.value.attributes = list as any
  }
}

function setItemOrder(code: string, orderStr: string, kind: 'option' | 'attribute'): void {
  if (!config.value) return
  const order = orderStr === '' ? null : Number(orderStr)
  if (kind === 'option') {
    const list: any[] = (config.value.options as any[]) ?? []
    const item = list.find(i => i.code === code)
    if (item) item.order = order
    config.value.options = list as any
  } else {
    const list: any[] = (config.value.attributes as any[]) ?? []
    const item = list.find(i => i.code === code)
    if (item) item.order = order
    config.value.attributes = list as any
  }
}

function getOptionLabel(code: string | null): string {
  if (!code || !config.value) return ''
  const item = (config.value.options as any[])?.find(i => i.code === code)
  return (item?.label ?? '') as string
}

function getOptionOrder(code: string | null): string {
  if (!code || !config.value) return ''
  const item = (config.value.options as any[])?.find(i => i.code === code)
  const v = item?.order
  return v == null ? '' : String(v)
}

function getAttributeLabel(code: string | null): string {
  if (!code || !config.value) return ''
  const item = (config.value.attributes as any[])?.find(i => i.code === code)
  return (item?.label ?? '') as string
}

function getAttributeOrder(code: string | null): string {
  if (!code || !config.value) return ''
  const item = (config.value.attributes as any[])?.find(i => i.code === code)
  const v = item?.order
  return v == null ? '' : String(v)
}

function makeDefaultConfig(): FacetConfigDto {
  return {
    scope: selected.value === null ? 'GLOBAL' : 'CATEGORY',
    categoryId: selected.value,
    attributes: [],
    options: [],
    showZeros: false,
    collapsedByDefault: true,
    valuesLimit: 20,
    valuesSort: 'popularity',
  } as unknown as FacetConfigDto
}

onMounted(async () => {
  await loadCategories()
  await Promise.all([loadAll(), loadDicts()])
  syncSelectionsFromConfig()
})

watch(selected, async () => { await loadAll(); syncSelectionsFromConfig() })
watch(chosenOptionCodes, () => { applySelectionsToConfig() }, { deep: true })
watch(chosenAttributeCodes, () => { applySelectionsToConfig() }, { deep: true })
</script>

<style scoped>
</style>


