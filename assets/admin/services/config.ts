import { httpClient } from '@admin/services/http'
import { adminCache } from '@admin/utils/persistentCache'

export type PaginationConfig = {
  itemsPerPageOptions: number[]
  defaultItemsPerPage: number
}

const CACHE_VERSION = 'v1'
const ONE_DAY_MS = 24 * 60 * 60 * 1000

function endpointFor(scope?: string): string {
  if (!scope || scope === 'default') return '/config/pagination'
  return `/config/pagination/${scope}`
}

export async function getPaginationConfig(scope: string = 'default'): Promise<PaginationConfig> {
  // 1) Try from injected global config (fastest, no network)
  const win = window as any
  const fromWindow = win?.APP_CONFIG?.pagination?.[scope]
  if (fromWindow && Array.isArray(fromWindow.itemsPerPageOptions)) {
    return {
      itemsPerPageOptions: fromWindow.itemsPerPageOptions.map((n: any) => Number(n)).filter((n: number) => Number.isFinite(n) && n > 0),
      defaultItemsPerPage: Number(fromWindow.defaultItemsPerPage) || 10,
    }
  }

  // 2) Fallback to local persistent cache
  const cacheKey = `config:pagination:${scope}`
  const cached = adminCache.get<PaginationConfig>(cacheKey, CACHE_VERSION, ONE_DAY_MS)
  if (cached) return cached

  // 3) Last resort – fetch from API (should be rare now)
  const res = await httpClient.get(endpointFor(scope))
  const data = res.data as PaginationConfig
  const items = Array.isArray((data as any)?.itemsPerPageOptions)
    ? (data as any).itemsPerPageOptions.map((n: any) => Number(n)).filter((n: number) => Number.isFinite(n) && n > 0)
    : []
  const normalized: PaginationConfig = {
    itemsPerPageOptions: items,
    defaultItemsPerPage: Number.isFinite(Number((data as any)?.defaultItemsPerPage))
      ? Number((data as any).defaultItemsPerPage)
      : (items[0] ?? 10),
  }
  adminCache.set<PaginationConfig>(cacheKey, CACHE_VERSION, normalized)
  return normalized
}


