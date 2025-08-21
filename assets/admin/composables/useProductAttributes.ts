import { ref } from 'vue'
import {
  ProductAttributeRow,
  AttributeGroup,
  AttributeData,
  AttributeAssignmentData,
  HydraResponse,
  getAttributeIri,
  getGroupIri,
  valueToTextProxy,
  textProxyToValue,
  getValueField,
  isValidValue,
  getHydraMembers
} from '@admin/utils/attributeUtils'

interface Repository<T = any> {
  findAllCached?(): Promise<HydraResponse<T> | T[]>
  findAll(params: { itemsPerPage: number; filters: Record<string, string> }): Promise<HydraResponse<T> | T[]>
  findById(id: number): Promise<T>
  partialUpdate(id: number, data: Record<string, any>): Promise<T | void>
  create(data: Record<string, any>): Promise<T>
  delete(id: number): Promise<void>
}

interface UseProductAttributesOptions {
  assignmentRepo: Repository<AttributeAssignmentData>
  attributeRepo: Repository<AttributeData>
  attributeGroupRepo: Repository<AttributeData>
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
  const error = ref<string | null>(null)

  async function loadAttributesBootstrap() {
    if (attributesPrefetched.value) return
    try {
      await Promise.all([
        attributeGroupRepo.findAllCached?.() || Promise.resolve([]),
        attributeRepo.findAllCached?.() || Promise.resolve([]),
      ])
      attributesPrefetched.value = true
      error.value = null
    } catch (err) {
      error.value = 'Ошибка при загрузке справочников атрибутов'
      emit('toast', error.value)
    }
  }

  async function loadProductAttributes() {
    if (!productId || productId === 'new') return
    
    attrLoading.value = true
    error.value = null
    
    try {
      const productIri = `/api/products/${productId}`
      
      // Загружаем справочники
      const allGroupsResponse = await (attributeGroupRepo.findAllCached?.() || Promise.resolve([]))
      const allAttrsResponse = await (attributeRepo.findAllCached?.() || Promise.resolve([]))
      
      const allGroups = getHydraMembers<AttributeData>(allGroupsResponse)
      const allAttrs = getHydraMembers<AttributeData>(allAttrsResponse)
      
      // Создаем словари для быстрого доступа
      const groupsDict = new Map<string, string>()
      allGroups.forEach(g => {
        const iri = getAttributeIri(g)
        if (iri && g.name) groupsDict.set(iri, g.name)
      })
      
      const attrsDict = new Map<string, string>()
      allAttrs.forEach(a => {
        const iri = getAttributeIri(a)
        if (iri && a.name) attrsDict.set(iri, a.name)
      })

      // Загружаем привязки атрибутов к товару
      const aaData = await assignmentRepo.findAll({ 
        itemsPerPage: 1000, 
        filters: { product: productIri } 
      })
      
      const assignments = getHydraMembers<AttributeAssignmentData>(aaData)
      
      // Фильтруем только привязки для нужного товара (клиентская фильтрация)
      const filteredAssignments = assignments.filter(a => {
        const assignmentProductIri = typeof a.product === 'string' ? a.product : a.product?.['@id']
        return assignmentProductIri === productIri
      })
      
      // Группируем по группам атрибутов
      const byGroup = new Map<string | null, AttributeAssignmentData[]>()
      filteredAssignments.forEach(a => {
        const groupIri = getGroupIri(a.attributeGroup)
        if (!byGroup.has(groupIri)) byGroup.set(groupIri, [])
        byGroup.get(groupIri)!.push(a)
      })
      
      // Формируем итоговую структуру
      const rows: AttributeGroup[] = []
      byGroup.forEach((list, groupIri) => {
        const title = groupIri ? (groupsDict.get(groupIri) ?? 'Группа') : 'Без группы'
        const items: ProductAttributeRow[] = []
        
        list.forEach(a => {
          const attrIri = getAttributeIri(a.attribute)
          const attrName = attrIri ? (attrsDict.get(attrIri) ?? `Атрибут ${String(a.attribute).split('/').pop()}`) : `Атрибут ${String(a.attribute).split('/').pop()}`
          const dataType = (a.dataType ?? 'string') as ProductAttributeRow['dataType']
          const textProxy = valueToTextProxy(dataType, a)
          
          items.push({ 
            id: Number(a.id), 
            attributeName: attrName, 
            textProxy, 
            dataType 
          })
        })
        
        rows.push({ groupIri, groupName: String(title), items })
      })
      
      productAttrGroups.value = rows
      attributesLoaded.value = true
    } catch (err) {
      error.value = 'Ошибка при загрузке атрибутов товара'
      emit('toast', error.value)
    } finally {
      attrLoading.value = false
    }
  }

  async function saveProductAttribute(item: ProductAttributeRow) {
    try {
      const value = textProxyToValue(item.textProxy, item.dataType)
      
      // Дополнительная валидация для числовых типов
      if (item.dataType === 'int' && value !== null && !Number.isFinite(value)) {
        emit('toast', 'Значение должно быть целым числом')
        return
      }
      
      if (!isValidValue(value, item.dataType)) {
        emit('toast', 'Неверный формат значения')
        return
      }
      
      const key = getValueField(item.dataType)
      await assignmentRepo.partialUpdate(item.id, { [key]: value })
      emit('toast', 'Сохранено')
    } catch (err) {
      emit('toast', err instanceof Error ? err.message : 'Ошибка при сохранении')
    }
  }

  async function addAttributeToProduct(payload: { attributeIri: string; value: string }) {
    try {
      const attributeIri = payload.attributeIri
      const attributeId = Number(attributeIri.split('/').pop())
      const attr = await attributeRepo.findById(attributeId) as AttributeData
      const groupIri = getGroupIri(attr.attributeGroup)
      const productIri = `/api/products/${productId}`
      
      if (!productIri) {
        throw new Error('Product IRI is required')
      }
      
      const requestData = {
        product: productIri,
        attribute: attributeIri,
        attributeGroup: groupIri,
        dataType: 'string',
        stringValue: payload.value,
        position: 0,
      }
      
      const result = await assignmentRepo.create(requestData)
      await loadProductAttributes()
      return result
    } catch (err) {
      emit('toast', 'Ошибка при добавлении атрибута')
      throw err
    }
  }

  return {
    attrLoading,
    attributesLoaded,
    attributesPrefetched,
    productAttrGroups,
    error,
    loadProductAttributes,
    loadAttributesBootstrap,
    saveProductAttribute,
    addAttributeToProduct
  }
}

