export type CacheSerializer<T> = {
  serialize(value: T): string
  deserialize(raw: string): T
}

const defaultSerializer: CacheSerializer<any> = {
  serialize: (v: any) => JSON.stringify(v),
  deserialize: (raw: string) => JSON.parse(raw),
}

type StoredPayload = {
  v: string
  t: number
  d: string
}

export class PersistentCache {
  private namespace: string

  constructor(namespace: string) {
    this.namespace = namespace
  }

  private k(key: string): string {
    return `${this.namespace}:${key}`
  }

  get<T>(key: string, version: string, maxAgeMs: number, serializer: CacheSerializer<T> = defaultSerializer): T | null {
    try {
      const raw = localStorage.getItem(this.k(key))
      if (!raw) return null
      const parsed = JSON.parse(raw) as StoredPayload
      if (!parsed || typeof parsed !== 'object') return null
      if (parsed.v !== version) return null
      if (Date.now() - parsed.t > maxAgeMs) return null
      return serializer.deserialize(parsed.d)
    } catch {
      return null
    }
  }

  set<T>(key: string, version: string, value: T, serializer: CacheSerializer<T> = defaultSerializer): void {
    try {
      const payload: StoredPayload = { v: version, t: Date.now(), d: serializer.serialize(value) }
      localStorage.setItem(this.k(key), JSON.stringify(payload))
    } catch {
      // ignore quota / privacy mode errors silently
    }
  }

  clear(prefix?: string): void {
    try {
      const pfx = this.k(prefix ?? '')
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i)
        if (!k) continue
        if (k.startsWith(pfx)) localStorage.removeItem(k)
      }
    } catch {}
  }
}

export const adminCache = new PersistentCache('admin-cache')



