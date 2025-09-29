import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['list', 'products', 'selected']
  static values = {
    categoryId: Number,
    productsUrl: String,
    facetsUrl: String
  }

  connect() {
    this.selected = new Map() // code -> Set(values)
    this.meta = {} // code -> { title, sort }
    this.spinner = null
    // Прочитать выбранные фильтры из URL при первой загрузке
    this.readSelectedFromUrl()
    // Render initial facets if provided to avoid API call on first paint
    try {
      const initial = this.listTarget?.getAttribute('data-initial-facets')
      if (initial) {
        const data = JSON.parse(initial)
        if (data && data.facets) this.renderFacets(data.facets, data.meta || {})
        this.renderSelected()
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
    // Если ранее инициализировали, но узел спиннера был удалён при обновлении DOM — реинициализируем
    if (this.spinner) {
      const stillThere = this.productsTarget.querySelector('#category-grid-spinner')
      if (stillThere) return this.spinner
      this.spinner = null
    }
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
    this.renderFacets(data.facets, data.meta || {})
    this.renderSelected()
    await this.loadProducts()
  }

  async loadProducts() {
    const url = new URL(this.productsUrlValue, window.location.origin)
    const params = this.buildQuery()
    for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v)
    try {
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } })
      const html = await res.text()
      // Парсим только inner grid, чтобы не удалять спиннер
      const tmp = document.createElement('div')
      tmp.innerHTML = html
      const newGrid = tmp.querySelector('[data-testid="category-grid"]') || tmp.querySelector('.grid')
      const currentGrid = this.productsTarget.querySelector('[data-testid="category-grid"]') || this.productsTarget.querySelector('.grid')
      if (newGrid && currentGrid) {
        currentGrid.replaceWith(newGrid)
      } else if (newGrid) {
        // Fallback: если по какой-то причине текущего грида нет — добавим новый, не трогая спиннер
        this.productsTarget.appendChild(newGrid)
      } else {
        // В крайнем случае обновим только текст (редко), не трогая остальной контейнер
        const msg = tmp.textContent || ''
        const placeholder = document.createElement('div')
        placeholder.textContent = msg
        if (currentGrid) currentGrid.replaceWith(placeholder)
      }
    } finally {
      try { this.spinner?.hide() } catch (e) {}
    }
  }

  buildQuery() {
    const q = {}
    // Для страницы категории передаём category, для поиска — text
    const url = new URL(window.location.href)
    const text = url.searchParams.get('text') || ''
    if (this.hasCategoryIdValue && this.categoryIdValue) {
      q.category = String(this.categoryIdValue)
    } else if (text) {
      q.text = text
    }
    for (const [code, set] of this.selected.entries()) {
      if (set.size > 0) q[`f[${code}]`] = Array.from(set).join(',')
    }
    return q
  }

  updateUrl() {
    try {
      const url = new URL(window.location.href)
      // Стираем прошлые f[...] и category (text сохраняем)
      Array.from(url.searchParams.keys()).forEach(k => { if (k === 'category' || k.startsWith('f[')) url.searchParams.delete(k) })
      const q = this.buildQuery()
      for (const [k, v] of Object.entries(q)) url.searchParams.set(k, v)
      window.history.replaceState({}, '', url.toString())
    } catch (e) {}
  }

  renderFacets(facets, meta = {}) {
    const root = this.listTarget
    root.innerHTML = ''
    this.meta = meta || {}
    const entries = Object.entries(facets)
    // apply sorting by meta.sort (ascending, nulls last), then by title/code
    entries.sort((a, b) => {
      const [codeA] = a, [codeB] = b
      const ma = meta[codeA] || {}, mb = meta[codeB] || {}
      const sa = ma.sort ?? null, sb = mb.sort ?? null
      if (sa != null && sb != null) return Number(sa) - Number(sb)
      if (sa != null) return -1
      if (sb != null) return 1
      const ta = (ma.title || codeA) + ''
      const tb = (mb.title || codeB) + ''
      return ta.localeCompare(tb)
    })
    entries.forEach(([code, facet]) => {
      if (facet.type === 'range') return // пока пропускаем диапазон
      const section = document.createElement('section')
      const title = document.createElement('h3')
      title.textContent = (meta[code]?.title || code)
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

  renderSelected() {
    const root = this.hasSelectedTarget ? this.selectedTarget : null
    if (!root) return
    const entries = Array.from(this.selected.entries()).flatMap(([code, set]) =>
      Array.from(set).map(v => ({ code, value: String(v) }))
    )
    if (entries.length === 0) {
      root.innerHTML = ''
      return
    }
    const wrap = document.createElement('div')
    wrap.className = 'selected-filters flex flex-wrap items-center gap-2'
    const title = document.createElement('span')
    title.className = 'text-sm text-gray-600 mr-2'
    title.textContent = 'Выбранные фильтры:'
    wrap.appendChild(title)
    entries.forEach(({ code, value }) => {
      const chip = document.createElement('button')
      chip.type = 'button'
      chip.className = 'inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-800 hover:bg-gray-200'
      chip.dataset.action = 'click->facets#remove'
      chip.dataset.facetsCode = code
      chip.dataset.facetsValue = value
      const title = (this.meta && this.meta[code] && this.meta[code].title) ? String(this.meta[code].title) : String(code)
      chip.innerHTML = `<span>${title}: ${value}</span><svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>`
      wrap.appendChild(chip)
    })
    const clear = document.createElement('button')
    clear.type = 'button'
    clear.className = 'ml-2 text-sm text-blue-600 hover:underline'
    clear.textContent = 'Сбросить все'
    clear.dataset.action = 'click->facets#clearAll'
    wrap.appendChild(clear)
    root.innerHTML = ''
    root.appendChild(wrap)
  }

  async remove(event) {
    const btn = event.currentTarget
    const code = btn.dataset.facetsCode
    const value = btn.dataset.facetsValue
    if (!this.selected.has(code)) return
    const set = this.selected.get(code)
    set.delete(value)
    if (set.size === 0) this.selected.delete(code)
    this.updateUrl()
    await this.loadFacets()
  }

  async clearAll() {
    this.selected.clear()
    this.updateUrl()
    await this.loadFacets()
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


