<template>
  <div class="rounded-md border p-4 dark:border-neutral-800">
    <div class="mb-3 flex items-center justify-between">
      <div class="text-sm font-medium">Атрибуты</div>
      <Button size="sm" @click="attrModalOpen = true">Добавить</Button>
    </div>
    <div v-if="attrLoading" class="text-sm text-neutral-500">Загрузка…</div>
    <div v-else class="space-y-6">
      <div v-for="group in productAttrGroups" :key="group.groupIri || '__no_group__'" class="rounded-md border">
        <div class="border-b px-3 py-2 text-sm font-medium">{{ group.groupName }}</div>
        <table class="w-full text-sm">
          <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900/40 dark:text-neutral-300">
            <tr>
              <th class="px-3 py-2 text-left">Атрибут</th>
              <th class="px-3 py-2 text-left">Значение</th>
              <th class="px-3 py-2 text-left w-28">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in group.items" :key="item.id" class="border-t">
              <td class="px-3 py-2">{{ item.attributeName }}</td>
              <td class="px-3 py-2">
                <input
                  v-model="item.textProxy"
                  type="text"
                  class="h-9 w-full rounded-md border px-2 text-sm dark:border-neutral-800 dark:bg-neutral-900"
                  @blur="() => saveProductAttribute(item)"
                />
              </td>
              <td class="px-3 py-2">
                <button type="button" class="h-8 rounded-md bg-red-600 px-2 text-xs font-medium text-white hover:bg-red-700" @click="confirmDeletePA(item.id)">Удалить</button>
              </td>
            </tr>
            <tr v-if="group.items.length === 0">
              <td colspan="3" class="px-3 py-6 text-center text-neutral-500">Нет атрибутов</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <ProductAttributesAddModal v-if="attrModalOpen" v-model="attrModalOpen" @add="onAddFromModal" />
  <ConfirmDialog v-model="deletePAOpen" :title="'Удалить атрибут?'" :description="'Это действие необратимо'" confirm-text="Удалить" :danger="true" @confirm="performDeletePA" />
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import Button from '@admin/ui/components/Button.vue'
import ProductAttributesAddModal from '@admin/components/forms/ProductAttributesAddModal.vue'
import ConfirmDialog from '@admin/components/ConfirmDialog.vue'
import { ProductAttributeAssignmentRepository } from '@admin/repositories/ProductAttributeAssignmentRepository'
import { AttributeRepository } from '@admin/repositories/AttributeRepository'
import { AttributeGroupRepository } from '@admin/repositories/AttributeGroupRepository'

const props = defineProps<{ productId: string; isCreating: boolean }>()
const emit = defineEmits<{ (e: 'toast', message: string): void }>()

const assignmentRepo = new ProductAttributeAssignmentRepository()
const attributeRepo = new AttributeRepository()
const attributeGroupRepo = new AttributeGroupRepository()

const attrModalOpen = ref(false)
const attrLoading = ref(false)
const attributesLoaded = ref(false)
const attributesPrefetched = ref(false)

type ProductAttributeRow = { id: number; attributeName: string; textProxy: string; dataType: 'string' | 'text' | 'int' | 'decimal' | 'bool' | 'json' | 'date' }
const productAttrGroups = ref<Array<{ groupIri: string | null; groupName: string; items: ProductAttributeRow[] }>>([])

function publishToast(message: string) { emit('toast', message) }

async function loadAttributesBootstrap() {
  if (attributesPrefetched.value) return
  await Promise.all([
    attributeGroupRepo.findAllCached(),
    attributeRepo.findAllCached(),
  ])
  attributesPrefetched.value = true
}

async function loadProductAttributes() {
  if (!props.productId) return
  attrLoading.value = true
  try {
    const productIri = `/api/products/${props.productId}`
    // Словари групп и атрибутов
    const allGroups = await attributeGroupRepo.findAllCached() as any
    const groupsDict = new Map<string, string>()
    for (const g of (allGroups['hydra:member'] ?? allGroups.member ?? [])) {
      groupsDict.set(g['@id'], g.name ?? `Группа ${g.id}`)
    }
    const allAttrs = await attributeRepo.findAllCached() as any
    const attrsDict = new Map<string, string>()
    for (const a of (allAttrs['hydra:member'] ?? allAttrs.member ?? [])) {
      const iri = a['@id'] ?? (a.id ? `/api/attributes/${a.id}` : null)
      if (iri) attrsDict.set(iri, a.name ?? `Атрибут ${a.id}`)
    }

    const aaData = await assignmentRepo.findAll({ itemsPerPage: 1000, filters: { product: productIri } }) as any
    const members = (aaData['hydra:member'] ?? aaData.member ?? []) as any[]
    const byGroup = new Map<string | null, any[]>()
    for (const a of members) {
      const k: string | null = typeof a.attributeGroup === 'string' ? a.attributeGroup : (a.attributeGroup?.['@id'] ?? null)
      if (!byGroup.has(k)) byGroup.set(k, [])
      byGroup.get(k)!.push(a)
    }
    const rows: Array<{ groupIri: string | null; groupName: string; items: ProductAttributeRow[] }> = []
    for (const [groupIri, list] of byGroup.entries()) {
      const title = groupIri ? (groupsDict.get(groupIri) ?? 'Группа') : 'Без группы'
      const items: ProductAttributeRow[] = []
      for (const a of list) {
        const attrIri = ((): string | null => {
          if (typeof a.attribute === 'string') return a.attribute
          const id = a.attribute?.id
          const iriObj = a.attribute?.['@id']
          if (typeof iriObj === 'string') return iriObj
          if (id != null) return `/api/attributes/${id}`
          return null
        })()
        const attrName = (attrIri ? attrsDict.get(attrIri) : undefined) ?? `Атрибут ${String(a.attribute || '').split('/').pop()}`
        const dt = (a.dataType ?? 'string') as ProductAttributeRow['dataType']
        const textProxy = ((): string => {
          switch (dt) {
            case 'int': return a.intValue != null ? String(a.intValue) : ''
            case 'decimal': return a.decimalValue != null ? String(a.decimalValue) : ''
            case 'bool': return a.boolValue != null ? (a.boolValue ? 'true' : 'false') : ''
            case 'date': return a.dateValue ?? ''
            case 'text': return a.textValue ?? ''
            case 'json': return a.jsonValue != null ? JSON.stringify(a.jsonValue) : ''
            default: return a.stringValue ?? ''
          }
        })()
        items.push({ id: Number(a.id), attributeName: attrName, textProxy, dataType: dt })
      }
      rows.push({ groupIri, groupName: String(title), items })
    }
    productAttrGroups.value = rows
  } finally {
    attrLoading.value = false
    attributesLoaded.value = true
  }
}

async function onAddFromModal(payload: { attributeIri: string; value: string }) {
  if (props.isCreating) {
    publishToast('Сначала сохраните товар')
    return
  }
  const attributeIri = payload.attributeIri
  const attributeId = Number(attributeIri.split('/').pop())
  const attr = await attributeRepo.findById(attributeId) as any
  const groupRaw = attr.attributeGroup as any
  const groupIri: string | null = typeof groupRaw === 'string' ? groupRaw : groupRaw?.['@id'] ?? (groupRaw?.id ? `/api/attribute_groups/${groupRaw.id}` : null)
  const productIri = `/api/products/${props.productId}`
  await assignmentRepo.create({
    product: productIri,
    attribute: attributeIri,
    attributeGroup: groupIri,
    dataType: 'string',
    stringValue: payload.value,
    position: 0,
  } as any)
  publishToast('Атрибут добавлен к товару')
  await loadProductAttributes()
}

async function saveProductAttribute(item: ProductAttributeRow) {
  const map: Record<ProductAttributeRow['dataType'], string> = {
    string: 'stringValue',
    text: 'textValue',
    int: 'intValue',
    decimal: 'decimalValue',
    bool: 'boolValue',
    date: 'dateValue',
    json: 'jsonValue',
  }
  const key = map[item.dataType]
  let value: any = item.textProxy
  try {
    switch (item.dataType) {
      case 'int': value = item.textProxy === '' ? null : Number(item.textProxy); if (!Number.isFinite(value)) value = null; break
      case 'decimal': value = item.textProxy === '' ? null : String(item.textProxy); break
      case 'bool': value = item.textProxy === '' ? null : (/^(1|true|yes|on)$/i.test(item.textProxy)); break
      case 'date': value = item.textProxy || null; break
      case 'json': value = item.textProxy ? JSON.parse(item.textProxy) : null; break
      default: value = item.textProxy || null; break
    }
  } catch {
    publishToast('Неверный формат JSON')
    return
  }
  await assignmentRepo.partialUpdate(item.id, { [key]: value } as any)
  publishToast('Сохранено')
}

const deletePAOpen = ref(false)
const pendingPAId = ref<number | null>(null)
function confirmDeletePA(idNum: number) { pendingPAId.value = idNum; deletePAOpen.value = true }
async function performDeletePA() {
  if (pendingPAId.value == null) return
  await assignmentRepo.delete(pendingPAId.value)
  for (const g of productAttrGroups.value) {
    g.items = g.items.filter(i => i.id !== pendingPAId.value!)
  }
  publishToast('Удалено')
  pendingPAId.value = null
}

onMounted(async () => {
  await loadAttributesBootstrap()
  await loadProductAttributes()
})

watch(() => props.productId, async (val, oldVal) => {
  if (val !== oldVal) {
    attributesLoaded.value = false
    productAttrGroups.value = []
    await loadProductAttributes()
  }
})
</script>

<style scoped></style>


