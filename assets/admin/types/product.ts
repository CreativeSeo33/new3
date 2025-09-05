export interface ProductFormModel {
  name: string
  slug: string
  price: number | null
  salePrice: number | null
  status: boolean
  quantity: number | null
  description: string
  metaTitle: string
  metaDescription: string
  h1: string
  sortOrder: number | null
  type: string
  optionsJson: ProductOptionConfig[] | null
  optionAssignments: ProductOptionValueAssignment[] | null
}

export interface ProductTab {
  value: string
  label: string
}

export interface ProductFormErrors {
  name?: string
  slug?: string
  price?: string
  salePrice?: string
  quantity?: string
  description?: string
  sortOrder?: string
  type?: string
}

export interface ProductOptionValueSel {
  value: string
  label?: string
  price?: number | null
}

export type ProductOptionPriceMode = 'delta' | 'absolute'

export interface ProductOptionConfig {
  option: string
  multiple: boolean
  required: boolean
  priceMode: ProductOptionPriceMode
  values: ProductOptionValueSel[]
  defaultValues?: string[]
  sortOrder: number
  meta?: Record<string, any>
}

export interface ProductOptionValueAssignment {
  option: string
  value: string | null
  height: number | null
  bulbsCount: number | null
  sku: string | null
  originalSku?: string | null
  price: number | null
  setPrice?: boolean | null
  salePrice?: number | null
  lightingArea: number | null
  sortOrder?: number | null
  quantity?: number | null
  attributes?: Record<string, any> | null
}


