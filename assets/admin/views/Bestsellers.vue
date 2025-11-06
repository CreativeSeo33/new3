<template>
  <div class="space-y-4">
    <h1 class="text-xl font-semibold">Хиты продаж</h1>

    <Autocomplete
      label="Хиты продаж"
      placeholder="Начните вводить название товара…"
      :search="searchProducts"
      :min-query-length="3"
      :limit="10"
    />
  </div>
</template>

<script setup lang="ts">
import Autocomplete from '../ui/components/Autocomplete.vue'
import { ProductRepository, type ProductDto } from '../repositories/ProductRepository'

type HydraCollection<T = any> = {
  '@context'?: string
  '@id'?: string
  '@type'?: string
  'hydra:member'?: T[]
  member?: T[]
  'hydra:totalItems'?: number
  totalItems?: number
}

const repo = new ProductRepository()

async function searchProducts(
  query: string,
  limit: number
): Promise<HydraCollection<{ id: number | string; name: string | null; firstImageUrl?: string | null }>> {
  const res = await repo.searchProducts(query, limit) as any
  const original = (res['hydra:member'] ?? res.member ?? []) as Array<any>
  const mapped = original
    .map((p) => {
      const id = p?.id
      if (id === null || id === undefined) return null
      return {
        id: id as number,
        name: (p?.name ?? null) as string | null,
        firstImageUrl: (p?.firstImageUrl ?? null) as string | null,
      }
    })
    .filter(Boolean) as Array<{ id: number; name: string | null; firstImageUrl?: string | null }>

  return {
    ...(res as any),
    'hydra:member': mapped,
    member: mapped,
  }
}
</script>

<style scoped>
</style>

