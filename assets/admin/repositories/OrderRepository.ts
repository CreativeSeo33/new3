import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface OrderProductDto {
  id: number
  product_id?: number | null
  product_name: string | null
  quantity: number
  price?: number | null
  salePrice?: number | null
  options?: unknown[] | null
}

export interface OrderCustomerDto {
  name?: string | null
  surname?: string | null
  phone?: string | null
}

export interface OrderDeliveryDto {
  city?: string | null
}

export interface OrderDto extends ApiResource {
  id: number
  orderId: number
  dateAdded: string
  comment?: string | null
  status?: number | null
  total?: number | null
  customer?: OrderCustomerDto | null
  delivery?: OrderDeliveryDto | null
  products?: OrderProductDto[] | null
}

export class OrderRepository extends BaseRepository<OrderDto> {
  constructor() {
    // Api Platform по умолчанию отдаёт коллекцию по /orders
    super('/orders')
  }
}




