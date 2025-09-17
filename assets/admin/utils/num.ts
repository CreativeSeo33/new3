export function toInt(value: unknown): number | null {
  if (value === null || value === undefined || value === '') return null
  const normalized = String(value).trim().replace(',', '.')
  const num = Number(normalized)
  return Number.isFinite(num) ? Math.trunc(num) : null
}

export function toNum(value: unknown): number | null {
  if (value === null || value === undefined || value === '') return null
  const normalized = String(value).trim().replace(',', '.')
  const num = Number(normalized)
  return Number.isFinite(num) ? num : null
}


