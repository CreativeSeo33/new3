import { computed, nextTick, ref, watch, type Ref } from 'vue'
import { debounce } from '@admin/utils/debounce'
import { OptionRepository, type Option } from '@admin/repositories/OptionRepository'
import { OptionValueRepository, type OptionValue } from '@admin/repositories/OptionValueRepository'
import type { ProductOptionValueAssignment } from '@admin/types/product'

export interface UseOptionAssignmentsOptions {
  initial: Ref<ProductOptionValueAssignment[] | null>
  prefetchedOptions?: Ref<Option[] | null | undefined>
  onUpdate: (rows: ProductOptionValueAssignment[] | null) => void
}

type NormalizedRow = Required<Omit<ProductOptionValueAssignment,
  'setPrice' | 'sortOrder' | 'quantity' | 'originalSku' | 'salePrice'>> & {
  setPrice: boolean
  sortOrder: number | null
  quantity: number | null
  originalSku: string | null
  salePrice: number | null
}

function normalizeSetPrice(value: any): boolean {
  if (value === null || value === undefined) return false
  if (typeof value === 'boolean') return value
  if (typeof value === 'number') return value === 1
  if (typeof value === 'string') return value === '1' || value.toLowerCase() === 'true'
  return Boolean(value)
}

function toNumberOrNull(v: any): number | null {
  if (v === '' || v === null || v === undefined) return null
  const n = Number(String(v))
  return Number.isFinite(n) ? n : null
}

function normalizeAssignment(r: any): NormalizedRow {
  return {
    option: r?.option ?? null,
    value: r?.value ?? null,
    height: toNumberOrNull(r?.height),
    bulbsCount: toNumberOrNull(r?.bulbsCount),
    sku: r?.sku ?? null,
    originalSku: r?.originalSku ?? null,
    price: toNumberOrNull(r?.price),
    setPrice: normalizeSetPrice(r?.setPrice),
    salePrice: toNumberOrNull(r?.salePrice),
    lightingArea: toNumberOrNull(r?.lightingArea),
    sortOrder: toNumberOrNull(r?.sortOrder),
    quantity: toNumberOrNull(r?.quantity),
    attributes: r?.attributes ?? null,
  }
}

function makeKey(r: NormalizedRow): string {
  return [r.option ?? '', r.value ?? '', r.sku ?? ''].join('|')
}

function makeSignature(rows: ProductOptionValueAssignment[] | null | undefined): string {
  try {
    const arr = Array.isArray(rows) ? rows : []
    const norm = arr.map((r) => normalizeAssignment(r))
    return JSON.stringify(norm)
  } catch {
    return String(Date.now())
  }
}

export function useOptionAssignments(opts: UseOptionAssignmentsOptions) {
  const rows = ref<ProductOptionValueAssignment[]>([])
  const suppressRowsEmit = ref(false)
  const lastEmittedSignature = ref('')
  const baseSnapshot = ref<NormalizedRow[]>([])
  const baseSignature = ref('')

  const optionRepo = new OptionRepository()
  const optionValueRepo = new OptionValueRepository()
  const optionsList = ref<Option[]>([])
  const optionsLoading = ref(false)
  const optionNameByIri = new Map<string, string>()
  const optionMetaLoading = ref<Set<string>>(new Set())
  const optionIdToValues = new Map<string, OptionValue[]>()
  const optionValuesLoading = ref<Set<string>>(new Set())

  const optionsSorted = computed(() => {
    const list = (optionsList.value || ([] as any)).slice() as any[]
    list.sort((a: any, b: any) => {
      const ao = (a as any).sortOrder ?? null
      const bo = (b as any).sortOrder ?? null
      if (ao != null && bo != null) return ao - bo
      if (ao != null) return -1
      if (bo != null) return 1
      return String(a.name || '').localeCompare(String(b.name || ''))
    })
    return list as Option[]
  })

  async function loadOptions() {
    if (optionsLoading.value) return
    optionsLoading.value = true
    try {
      if (opts.prefetchedOptions?.value && opts.prefetchedOptions.value.length) {
        optionsList.value = opts.prefetchedOptions.value
      } else {
        const data = await optionRepo.findAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', name: 'asc' } }) as any
        const collection = (data['hydra:member'] ?? data.member ?? []) as any
        optionsList.value = collection as Option[]
      }
    } finally {
      optionsLoading.value = false
    }
  }

  if (opts.prefetchedOptions?.value?.length) {
    optionsList.value = opts.prefetchedOptions.value
  }
  if (opts.prefetchedOptions) {
    watch(opts.prefetchedOptions, (next) => {
      if (next && next.length > 0) optionsList.value = next
    })
  }

  function emitRowsUpdate(): void {
    scheduleEmit()
  }
  const scheduleEmit = debounce(() => {
    if (suppressRowsEmit.value) return
    const sig = makeSignature(rows.value as any)
    if (sig === lastEmittedSignature.value) return
    lastEmittedSignature.value = sig
    opts.onUpdate(rows.value)
  }, 120)

  function setBasePriceForRow(target: ProductOptionValueAssignment, v: boolean | null) {
    const val = normalizeSetPrice(v)
    if (!val) {
      ;(target as any).setPrice = false
      emitRowsUpdate()
      return
    }
    for (const r of rows.value) if (r !== target) (r as any).setPrice = false
    ;(target as any).setPrice = true
    emitRowsUpdate()
  }

  const optionCodes = computed<string[]>(() => {
    const set = new Set<string>()
    for (const r of rows.value) if (r.option) set.add(r.option)
    const arr = Array.from(set.values())
    const getOrder = (iri: string): number => {
      const found = (optionsSorted.value as any).find((o: any) => (o as any)['@id'] === iri) as any
      const so = found?.sortOrder
      return typeof so === 'number' ? so : Number.POSITIVE_INFINITY
    }
    const getName = (iri: string): string => {
      const found = (optionsSorted.value as any).find((o: any) => (o as any)['@id'] === iri) as any
      return String(found?.name || optionNameByIri.get(iri) || iri.split('/').pop() || '')
    }
    arr.sort((a, b) => {
      const ao = getOrder(a), bo = getOrder(b)
      if (ao !== bo) return ao - bo
      return getName(a).localeCompare(getName(b))
    })
    return arr
  })

  // Группировка строк по опциям для быстрых выборок и подсчётов
  const rowsByOptionMap = computed<Map<string, ProductOptionValueAssignment[]>>(() => {
    const map = new Map<string, ProductOptionValueAssignment[]>()
    for (const r of rows.value) {
      const key = r.option || ''
      if (!map.has(key)) map.set(key, [])
      map.get(key)!.push(r)
    }
    return map
  })

  function optionTitle(optionIri: string): string {
    const cached = optionNameByIri.get(optionIri)
    if (cached) return cached
    const found = (optionsSorted.value as any).find((o: any) => (o as any)['@id'] === optionIri)
    if ((found as any)?.name) {
      const name = String((found as any).name)
      optionNameByIri.set(optionIri, name)
      return name
    }
    void ensureOptionMeta(optionIri)
    return `Опция ${optionIri?.split('/').pop()}`
  }

  async function ensureOptionMeta(optionIri: string) {
    if (!optionIri || optionNameByIri.has(optionIri) || optionMetaLoading.value.has(optionIri)) return
    const next = new Set(optionMetaLoading.value); next.add(optionIri); optionMetaLoading.value = next
    try {
      const inList = (optionsSorted.value as any).find((o: any) => (o as any)['@id'] === optionIri)
      if (inList && (inList as any).name) {
        optionNameByIri.set(optionIri, String((inList as any).name))
        return
      }
    } finally {
      const n2 = new Set(optionMetaLoading.value); n2.delete(optionIri); optionMetaLoading.value = n2
    }
  }

  function isOptionValuesLoading(optionIri: string): boolean { return optionValuesLoading.value.has(optionIri) }
  function optionValuesFor(optionIri: string): OptionValue[] { return optionIdToValues.get(optionIri) || [] }
  function ensureOptionValuesLoaded(optionIri: string) { void loadOptionValues(optionIri) }
  async function loadOptionValues(optionIri: string) {
    if (!optionIri || optionIdToValues.has(optionIri) || optionValuesLoading.value.has(optionIri)) return
    const next = new Set(optionValuesLoading.value); next.add(optionIri); optionValuesLoading.value = next
    try {
      const all = await optionValueRepo.findAll({ itemsPerPage: 500, sort: { sortOrder: 'asc', value: 'asc' }, filters: { optionType: optionIri } }) as any
      const list = (all['hydra:member'] ?? all.member ?? []) as any
      optionIdToValues.set(optionIri, list as any)
    } finally {
      const next2 = new Set(optionValuesLoading.value); next2.delete(optionIri); optionValuesLoading.value = next2
    }
  }

  function prefetchUsedOptionValues() {
    const MAX_PREFETCH = 5
    const set = new Set<string>()
    for (const r of rows.value) if (r.option) set.add(r.option)
    let scheduled = 0
    for (const iri of set.values()) {
      if (scheduled >= MAX_PREFETCH) break
      if (!optionIdToValues.has(iri)) { scheduled++; void loadOptionValues(iri) }
    }
  }

  function ensureOption(optionIri: string) {
    if (!rows.value.some(r => r.option === optionIri)) {
      suppressRowsEmit.value = false
      rows.value = [...rows.value, { option: optionIri, value: null, height: null, bulbsCount: null, sku: null, originalSku: null, price: null, setPrice: false, salePrice: null, lightingArea: null, sortOrder: null }]
      emitRowsUpdate()
    }
    void ensureOptionMeta(optionIri)
  }

  function addRow(optionIri: string) {
    const next = rows.value.slice()
    next.push({ option: optionIri, value: null, height: null, bulbsCount: null, sku: null, originalSku: null, price: null, setPrice: false, salePrice: null, lightingArea: null, sortOrder: null })
    rows.value = next
    emitRowsUpdate()
  }

  function removeRow(optionIri: string, idx: number) {
    let seen = -1
    rows.value = rows.value.filter(r => {
      if (r.option !== optionIri) return true
      seen++
      return seen !== idx
    })
    emitRowsUpdate()
  }

  function rowsByOption(optionIri: string): ProductOptionValueAssignment[] {
    return rowsByOptionMap.value.get(optionIri) || []
  }

  const collapsed = ref<Set<string>>(new Set())
  const collapsedInitialized = ref(false)
  // По умолчанию — сворачиваем все группы при первом наборе опций (улучшает производительность при входе)
  watch(optionCodes, (codes) => {
    if (collapsedInitialized.value) return
    const next = new Set<string>()
    for (const c of codes) next.add(c)
    collapsed.value = next
    collapsedInitialized.value = true
  }, { immediate: true })
  function toggleCollapsed(optIri: string) {
    const next = new Set(collapsed.value)
    if (next.has(optIri)) next.delete(optIri); else next.add(optIri)
    collapsed.value = next
    if (!next.has(optIri)) ensureOptionValuesLoaded(optIri)
  }

  const hasBasePrice = computed(() => rows.value.some(r => normalizeSetPrice((r as any).setPrice)))

  // Delta save support
  function getDelta() {
    const baseMap = new Map<string, NormalizedRow>()
    for (const r of baseSnapshot.value) baseMap.set(makeKey(r), r)
    const currNorm = rows.value.map(normalizeAssignment)
    const currMap = new Map<string, NormalizedRow>()
    for (const r of currNorm) currMap.set(makeKey(r), r)

    const removed = Array.from(baseMap.keys()).filter(k => !currMap.has(k)).map(k => baseMap.get(k)!)
    const upsert: NormalizedRow[] = []
    for (const [k, r] of currMap.entries()) {
      const b = baseMap.get(k)
      if (!b || JSON.stringify(b) !== JSON.stringify(r)) upsert.push(r)
    }
    return { removed, upsert }
  }

  const hasDelta = computed(() => makeSignature(rows.value) !== baseSignature.value)

  async function saveDelta(productId: string): Promise<{ success: boolean; message?: string }> {
    if (!productId || productId === 'new') return { success: false, message: 'Сначала сохраните товар' }
    const { removed, upsert } = getDelta()
    try {
      const res = await fetch(`/api/v2/products/${productId}/options`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          upsert,
          remove: removed.map(r => ({ option: r.option, value: r.value, sku: r.sku })),
        }),
      })
      if (!res.ok) throw new Error('Эндпоинт сохранения опций недоступен')
      // Обновим базу
      baseSnapshot.value = rows.value.map(normalizeAssignment)
      baseSignature.value = makeSignature(rows.value)
      return { success: true }
    } catch (e: any) {
      return { success: false, message: e?.message || 'Ошибка сохранения опций' }
    }
  }

  // Sync from props
  watch(opts.initial, (newAssignments) => {
    if (newAssignments) {
      const processed = newAssignments.map((row) => ({ ...row, setPrice: normalizeSetPrice((row as any)?.setPrice) }))
      const incomingSig = makeSignature(processed)
      const currentSig = makeSignature(rows.value)
      if (incomingSig !== currentSig) {
        suppressRowsEmit.value = true
        rows.value = processed
        void nextTick(() => {
          suppressRowsEmit.value = false
          lastEmittedSignature.value = incomingSig
          // если базовый снимок ещё пуст — зафиксируем
          if (!baseSignature.value) {
            baseSnapshot.value = rows.value.map(normalizeAssignment)
            baseSignature.value = incomingSig
          }
          prefetchUsedOptionValues()
        })
      } else {
        void nextTick(() => prefetchUsedOptionValues())
      }
    } else {
      rows.value = []
    }
  }, { immediate: true })

  return {
    // state
    rows,
    optionCodes,
    optionsSorted,
    optionsLoading,
    collapsed,
    hasBasePrice,
    hasDelta,
    // titles and lookups
    optionTitle,
    optionValuesFor,
    isOptionValuesLoading,
    // actions
    ensureOptionValuesLoaded,
    ensureOption,
    addRow,
    removeRow,
    rowsByOption,
    toggleCollapsed,
    setBasePriceForRow,
    emitRowsUpdate,
    loadOptions,
    // delta
    saveDelta,
  }
}


