import { ref } from 'vue'

interface ProductAttributeRow {
  id: number
  attributeName: string
  textProxy: string
  dataType: 'string' | 'text' | 'int' | 'decimal' | 'bool' | 'json' | 'date'
}

interface AttributeGroup {
  groupIri: string | null
  groupName: string
  items: ProductAttributeRow[]
}

interface UseProductAttributesOptions {
  assignmentRepo: any
  attributeRepo: any
  attributeGroupRepo: any
  productId: string
  emit: (event: 'toast', message: string) => void
}

export function useProductAttributes({
  assignmentRepo,
  attributeRepo,
  attributeGroupRepo,
  productId,
  emit
}: UseProductAttributesOptions) {
  const attrLoading = ref(false)
  const attributesLoaded = ref(false)
  const attributesPrefetched = ref(false)
  const productAttrGroups = ref<AttributeGroup[]>([])

  async function loadAttributesBootstrap() {
    if (attributesPrefetched.value) return
    await Promise.all([
      attributeGroupRepo.findAllCached(),
      attributeRepo.findAllCached(),
    ])
    attributesPrefetched.value = true
  }

  async function loadProductAttributes() {
    if (!productId || productId === 'new') return
    attrLoading.value = true
    try {
      const productIri = `/api/products/${productId}`
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
      const rows: AttributeGroup[] = []
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
        case 'int': 
          value = item.textProxy === '' ? null : Number(item.textProxy)
          if (!Number.isFinite(value)) value = null
          break
        case 'decimal': 
          value = item.textProxy === '' ? null : String(item.textProxy)
          break
        case 'bool': 
          value = item.textProxy === '' ? null : (/^(1|true|yes|on)$/i.test(item.textProxy))
          break
        case 'date': 
          value = item.textProxy || null
          break
        case 'json': 
          value = item.textProxy ? JSON.parse(item.textProxy) : null
          break
        default: 
          value = item.textProxy || null
          break
      }
    } catch {
      emit('toast', 'Неверный формат JSON')
      return
    }
    await assignmentRepo.partialUpdate(item.id, { [key]: value } as any)
    emit('toast', 'Сохранено')
  }

  async function addAttributeToProduct(payload: { attributeIri: string; value: string }) {
    const attributeIri = payload.attributeIri
    const attributeId = Number(attributeIri.split('/').pop())
    const attr = await attributeRepo.findById(attributeId) as any
    const groupRaw = attr.attributeGroup as any
    const groupIri: string | null = typeof groupRaw === 'string' ? groupRaw : groupRaw?.['@id'] ?? (groupRaw?.id ? `/api/attribute_groups/${groupRaw.id}` : null)
    const productIri = `/api/products/${productId}`
    
    // Validate that we have a product IRI
    if (!productIri) {
      throw new Error('Product IRI is required')
    }
    
    // Подготавливаем данные для отправки
    const requestData = {
      product: productIri,
      attribute: attributeIri,
      attributeGroup: groupIri,
      dataType: 'string',
      stringValue: payload.value,
      position: 0,
    }
    
    const result = await assignmentRepo.create(requestData as any)
    await loadProductAttributes()
    return result
  }

  return {
    attrLoading,
    productAttrGroups,
    loadProductAttributes,
    loadAttributesBootstrap,
    saveProductAttribute,
    addAttributeToProduct
  }
}

