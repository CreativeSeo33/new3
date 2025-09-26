import type { ApiResource } from '@admin/types/api'
import { BaseRepository } from './BaseRepository'

export interface OrderStatusDto extends ApiResource {
	id: number
	name: string
	sort: number
}

export class OrderStatusRepository extends BaseRepository<OrderStatusDto> {
	constructor() {
		super('/order_statuses')
	}
}



