import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['list', 'products']
  static values = {
    categoryId: Number,
    productsUrl: String,
    facetsUrl: String
  }

  connect() {
    this.selected = new Map() // code -> Set(values)
    this.spinner = null
    // Прочитать выбранные фильтры из URL при первой загрузке
    this.readSelectedFromUrl()
    // Render initial facets if provided to avoid API call on first paint
    try {
      const initial = this.listTarget?.getAttribute('data-initial-facets')
      if (initial) {
        const data = JSON.parse(initial)
        if (data && data.facets) this.renderFacets(data.facets)
      } else {
        this.loadFacets()
      }
    } catch (e) {
      this.loadFacets()
    }
  }

  readSelectedFromUrl() {
    try {
      const url = new URL(window.location.href)
      const next = new Map()
      for (const key of Array.from(url.searchParams.keys())) {
        const m = key.match(/^f\[(.+)\]$/)
        if (!m) continue
        const code = decodeURIComponent(m[1])
        const raw = url.searchParams.get(key) || ''
        const values = raw.split(',').map(s => s.trim()).filter(Boolean)
        if (values.length) next.set(code, new Set(values))
      }
      this.selected = next
    } catch (_) {
      // ignore
    }
  }

  async getOrInitSpinner() {
    if (this.spinner) return this.spinner
    const spinnerRoot = this.productsTarget.querySelector('#category-grid-spinner')
    if (!spinnerRoot) return null
    try {
      const mod = await import('@shared/ui/spinner')
      this.spinner = new mod.Spinner(spinnerRoot, { visible: false, overlay: true })
      return this.spinner
    } catch (e) {
      return null
    }
  }

  async loadFacets() {
    // show spinner at the start of facets request
    try { (await this.getOrInitSpinner())?.show() } catch (e) {}
    const url = new URL(this.facetsUrlValue, window.location.origin)
    const params = this.buildQuery()
    for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v)
    const res = await fetch(url.toString(), { headers: { Accept: 'application/json' } })
    const data = await res.json()
    this.renderFacets(data.facets)
    await this.loadProducts()
  }

  async loadProducts() {
    const url = new URL(this.productsUrlValue, window.location.origin)
    const params = this.buildQuery()
    for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v)
    try {
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } })
      const html = await res.text()
      // Парсим только inner grid, если пришел полный HTML по ошибке
      const tmp = document.createElement('div')
      tmp.innerHTML = html
      const grid = tmp.querySelector('.grid')
      this.productsTarget.innerHTML = grid ? grid.outerHTML : html
    } finally {
      try { this.spinner?.hide() } catch (e) {}
    }
  }

  buildQuery() {
    const q = {}
    q.category = String(this.categoryIdValue || '')
    for (const [code, set] of this.selected.entries()) {
      if (set.size > 0) q[`f[${code}]`] = Array.from(set).join(',')
    }
    return q
  }

  updateUrl() {
    try {
      const url = new URL(window.location.href)
      // Стираем прошлые f[...] и category
      Array.from(url.searchParams.keys()).forEach(k => { if (k === 'category' || k.startsWith('f[')) url.searchParams.delete(k) })
      const q = this.buildQuery()
      for (const [k, v] of Object.entries(q)) url.searchParams.set(k, v)
      window.history.replaceState({}, '', url.toString())
    } catch (e) {}
  }

  renderFacets(facets) {
    const root = this.listTarget
    root.innerHTML = ''
    Object.entries(facets).forEach(([code, facet]) => {
      if (facet.type === 'range') return // пока пропускаем диапазон
      const section = document.createElement('section')
      const title = document.createElement('h3')
      title.textContent = code
      section.appendChild(title)
      const list = document.createElement('ul')
      ;(facet.values || []).forEach(v => {
        const li = document.createElement('li')
        const id = `${code}__${v.code}`
        const cb = document.createElement('input')
        cb.type = 'checkbox'
        cb.id = id
        cb.dataset.action = 'change->facets#toggle'
        cb.dataset.facetsCode = code
        cb.dataset.facetsValue = v.label
        cb.disabled = v.count === 0
        cb.checked = this.selected.get(code)?.has(String(v.label)) || false
        const label = document.createElement('label')
        label.htmlFor = id
        label.textContent = `${v.label} (${v.count})`
        li.appendChild(cb)
        li.appendChild(label)
        list.appendChild(li)
      })
      section.appendChild(list)
      root.appendChild(section)
    })
  }

  async toggle(event) {
    const cb = event.currentTarget
    const code = cb.dataset.facetsCode
    const value = cb.dataset.facetsValue
    if (!this.selected.has(code)) this.selected.set(code, new Set())
    const set = this.selected.get(code)
    if (cb.checked) set.add(value); else set.delete(value)
    this.updateUrl()
    await this.loadFacets() // перерисуем агрегации и товары
  }
}


