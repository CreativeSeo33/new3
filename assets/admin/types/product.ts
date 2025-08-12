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


