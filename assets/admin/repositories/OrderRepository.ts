import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface OrderDto extends ApiResource {
  id: number
  orderId: number
  dateAdded: string
  comment?: string | null
  status?: number | null
  total?: number | null
  customer?: Record<string, any> | null
  delivery?: Record<string, any> | null
}

export class OrderRepository extends BaseRepository<OrderDto> {
  constructor() {
    // Api Platform по умолчанию отдаёт коллекцию по /orders
    super('/orders')
  }
}




