export interface ProductAttributeRow {
  id: number
  attributeName: string
  textProxy: string
  dataType: 'string' | 'text' | 'int' | 'decimal' | 'bool' | 'json' | 'date'
}

export interface AttributeGroup {
  groupIri: string | null
  groupName: string
  items: ProductAttributeRow[]
}

export interface AttributeData {
  '@id'?: string
  id?: number | string
  name?: string | null
  attributeGroup?: string | AttributeData | null
}

export interface AttributeAssignmentData {
  id?: number | string | undefined
  product: string | AttributeData
  attribute: string | AttributeData
  attributeGroup?: string | AttributeData | null
  dataType?: string
  stringValue?: string | null
  textValue?: string | null
  intValue?: number | null
  decimalValue?: string | number | null | undefined
  boolValue?: boolean | null
  dateValue?: string | null
  jsonValue?: any | null
}

export interface HydraResponse<T> {
  'hydra:member'?: T[]
  member?: T[]
}

/**
 * Извлекает IRI из объекта атрибута
 */
export function getAttributeIri(attribute: string | AttributeData): string | null {
  if (typeof attribute === 'string') return attribute
  const id = attribute.id
  const iriObj = attribute['@id']
  if (typeof iriObj === 'string') return iriObj
  if (id != null) return `/api/attributes/${id}`
  return null
}

/**
 * Извлекает IRI из группы атрибутов
 */
export function getGroupIri(attributeGroup: string | AttributeData | null | undefined): string | null {
  if (!attributeGroup) return null
  if (typeof attributeGroup === 'string') return attributeGroup
  const id = attributeGroup.id
  const iriObj = attributeGroup['@id']
  if (typeof iriObj === 'string') return iriObj
  if (id != null) return `/api/attribute_groups/${id}`
  return null
}

/**
 * Преобразует значение атрибута в текстовое представление
 */
export function valueToTextProxy(dataType: ProductAttributeRow['dataType'], assignment: AttributeAssignmentData): string {
  switch (dataType) {
    case 'int': return assignment.intValue != null ? String(assignment.intValue) : ''
    case 'decimal': return assignment.decimalValue != null ? String(assignment.decimalValue) : ''
    case 'bool': return assignment.boolValue != null ? (assignment.boolValue ? 'true' : 'false') : ''
    case 'date': return assignment.dateValue ?? ''
    case 'text': return assignment.textValue ?? ''
    case 'json': return assignment.jsonValue != null ? JSON.stringify(assignment.jsonValue) : ''
    default: return assignment.stringValue ?? ''
  }
}

/**
 * Преобразует текстовое представление в значение соответствующего типа
 */
export function textProxyToValue(textProxy: string, dataType: ProductAttributeRow['dataType']): any {
  try {
    switch (dataType) {
      case 'int': 
        return textProxy === '' ? null : Number(textProxy)
      case 'decimal': 
        return textProxy === '' ? null : String(textProxy)
      case 'bool': 
        return textProxy === '' ? null : (/^(1|true|yes|on)$/i.test(textProxy))
      case 'date': 
        return textProxy || null
      case 'json': 
        return textProxy ? JSON.parse(textProxy) : null
      default: 
        return textProxy || null
    }
  } catch {
    throw new Error('Неверный формат JSON')
  }
}

/**
 * Проверяет, является ли значение валидным для указанного типа данных
 */
export function isValidValue(value: any, dataType: ProductAttributeRow['dataType']): boolean {
  if (value === null || value === undefined) return true
  
  switch (dataType) {
    case 'int': 
      return Number.isFinite(value)
    case 'decimal': 
      return !isNaN(Number(value))
    case 'bool': 
      return typeof value === 'boolean'
    case 'date': 
      return typeof value === 'string'
    case 'json': 
      return typeof value === 'object' || typeof value === 'string'
    default: 
      return typeof value === 'string'
  }
}

/**
 * Получает имя поля для сохранения значения в зависимости от типа данных
 */
export function getValueField(dataType: ProductAttributeRow['dataType']): string {
  const map: Record<ProductAttributeRow['dataType'], string> = {
    string: 'stringValue',
    text: 'textValue',
    int: 'intValue',
    decimal: 'decimalValue',
    bool: 'boolValue',
    date: 'dateValue',
    json: 'jsonValue',
  }
  return map[dataType]
}

/**
 * Извлекает данные из Hydra-ответа API Platform
 */
export function getHydraMembers<T>(response: HydraResponse<T> | T[] | any): T[] {
  if (Array.isArray(response)) return response
  return response['hydra:member'] || response.member || []
}
