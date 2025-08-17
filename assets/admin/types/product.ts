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
  optionsJson: ProductOptionConfig[] | null
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


