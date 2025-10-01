import { Controller } from '@hotwired/stimulus'
import noUiSlider from 'nouislider'

export default class extends Controller {
  static targets = ['list', 'products', 'selected', 'limit']
  static values = {
    categoryId: Number,
    productsUrl: String,
    facetsUrl: String
  }

  connect() {
    this.selected = new Map() // code -> Set(values)
    this.meta = {} // code -> { title, sort }
    this.spinner = null
    // Диапазон цены (храним отдельно от selected)
    this.priceMin = null
    this.priceMax = null
    this.priceSlider = null
    // Прочитать выбранные фильтры из URL при первой загрузке
    this.readSelectedFromUrl()
    this.readPriceFromUrl()
    // Привязываем popstate и синхронизируем селект лимита
    this.onPopStateBound = this.onPopState.bind(this)
    window.addEventListener('popstate', this.onPopStateBound)
    this.syncLimitFromUrl()
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

  disconnect() {
    try { window.removeEventListener('popstate', this.onPopStateBound) } catch (_) {}
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

  readPriceFromUrl() {
    try {
      const url = new URL(window.location.href)
      const mn = url.searchParams.get('price_min')
      const mx = url.searchParams.get('price_max')
      this.priceMin = (mn != null && mn !== '' && !Number.isNaN(Number(mn))) ? Number(mn) : null
      this.priceMax = (mx != null && mx !== '' && !Number.isNaN(Number(mx))) ? Number(mx) : null
    } catch (_) {
      this.priceMin = null
      this.priceMax = null
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
    try { (await this.getOrInitSpinner())?.show() } catch (e) {}
    const url = new URL(this.productsUrlValue, window.location.origin)
    const params = this.buildQuery()
    for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v)
    try {
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' } })
      const html = await res.text()
      // Обновляем весь контент продуктов (грид + пагинация), сохраняя спиннер
      const wrapper = document.createElement('div')
      wrapper.innerHTML = html
      const spinnerRoot = this.productsTarget.querySelector('#category-grid-spinner')
      const children = Array.from(this.productsTarget.children)
      for (const ch of children) {
        if (spinnerRoot && ch === spinnerRoot) continue
        ch.remove()
      }
      const frag = document.createDocumentFragment()
      while (wrapper.firstChild) frag.appendChild(wrapper.firstChild)
      this.productsTarget.appendChild(frag)
    } finally {
      try { this.spinner?.hide() } catch (e) {}
    }
  }

  buildQuery() {
    const q = {}
    // Для страницы категории передаём category, для поиска — text
    const url = new URL(window.location.href)
    const text = url.searchParams.get('text') || ''
    const limit = url.searchParams.get('limit') || ''
    const page = url.searchParams.get('page') || ''
    if (this.hasCategoryIdValue && this.categoryIdValue) {
      q.category = String(this.categoryIdValue)
    } else if (text) {
      q.text = text
    }
    if (limit) q.limit = limit
    if (page) q.page = page
    if (this.priceMin != null) q['price_min'] = String(this.priceMin)
    if (this.priceMax != null) q['price_max'] = String(this.priceMax)
    for (const [code, set] of this.selected.entries()) {
      if (set.size > 0) q[`f[${code}]`] = Array.from(set).join(',')
    }
    return q
  }

  updateUrl() {
    try {
      const url = new URL(window.location.href)
      // Стираем прошлые f[...] и category (text/limit сохраняем); сбрасываем page=1
      Array.from(url.searchParams.keys()).forEach(k => { if (k === 'category' || k.startsWith('f[')) url.searchParams.delete(k) })
      const q = this.buildQuery()
      for (const [k, v] of Object.entries(q)) url.searchParams.set(k, v)
      url.searchParams.set('page', '1')
      window.history.replaceState({}, '', url.toString())
    } catch (e) {}
  }

  // === Новое поведение: лимит/страницы ===
  onLimitChange(event) {
    const select = event.currentTarget
    const value = String(select.value || '')
    try {
      const url = new URL(window.location.href)
      if (value) url.searchParams.set('limit', value); else url.searchParams.delete('limit')
      url.searchParams.set('page', '1')
      window.history.pushState({}, '', url.toString())
    } catch (_) {}
    // Перезагружаем только товары
    this.loadProducts()
  }

  onPaginationClick(event) {
    // Делегирование: реагируем только на клики по ссылкам внутри навигации пагинации
    const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null
    if (!anchor) return
    const nav = anchor.closest && anchor.closest('nav[aria-label="Pagination"]')
    if (!nav) return
    event.preventDefault()
    try {
      const linkUrl = new URL(anchor.href)
      const page = linkUrl.searchParams.get('page') || '1'
      const limit = linkUrl.searchParams.get('limit') || ''
      const url = new URL(window.location.href)
      // копируем f[...]
      Array.from(url.searchParams.keys()).forEach(k => { if (k.startsWith('f[')) url.searchParams.delete(k) })
      Array.from(linkUrl.searchParams.keys()).forEach(k => { if (k.startsWith('f[')) url.searchParams.set(k, linkUrl.searchParams.get(k)) })
      // применяем page/limit
      url.searchParams.set('page', page)
      if (limit) url.searchParams.set('limit', limit); else url.searchParams.delete('limit')
      window.history.pushState({}, '', url.toString())
    } catch (_) {}
    this.loadProducts()
  }

  onPopState() {
    // Считываем новое состояние из URL
    const prevSelected = this.selected
    this.readSelectedFromUrl()
    this.readPriceFromUrl()
    this.syncLimitFromUrl()
    const serialize = (m) => JSON.stringify(Array.from(m.entries()).map(([k, set]) => [k, Array.from(set).sort()]).sort())
    const changedFilters = serialize(prevSelected) !== serialize(this.selected)
    if (changedFilters) {
      this.loadFacets()
      return
    }
    this.loadProducts()
  }

  syncLimitFromUrl() {
    if (!this.hasLimitTarget) return
    try {
      const url = new URL(window.location.href)
      const limit = url.searchParams.get('limit')
      if (limit) this.limitTarget.value = String(limit)
    } catch (_) {}
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
      if (facet.type === 'range') {
        // Поддерживаем только ценовой диапазон
        if (code === 'price') this.renderPriceFacet(root, facet, meta)
        return
      }
      const values = Array.isArray(facet.values) ? facet.values.filter(v => v != null) : []
      if (values.length === 0) return // не рисуем секцию без значений
      const section = document.createElement('section')
      const title = document.createElement('h3')
      title.textContent = (meta[code]?.title || code)
      section.appendChild(title)
      const list = document.createElement('ul')
      values.forEach(v => {
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

  renderPriceFacet(root, facet, meta) {
    // facet: { type:'range', min:number|null, max:number|null }
    const minBound = Number.isFinite(Number(facet.min)) ? Number(facet.min) : 0
    const maxBound = Number.isFinite(Number(facet.max)) ? Number(facet.max) : 0
    const startMin = (this.priceMin != null) ? this.priceMin : minBound
    const startMax = (this.priceMax != null) ? this.priceMax : maxBound

    const section = document.createElement('section')
    const title = document.createElement('h3')
    title.textContent = (meta.price?.title || 'Цена')
    section.appendChild(title)

    const wrapper = document.createElement('div')
    wrapper.className = 'space-y-3'

    const range = document.createElement('div')
    range.id = 'hs-price-range'
    range.className = 'hs-range-slider'

    const valuesRow = document.createElement('div')
    valuesRow.className = 'flex items-center gap-2 text-sm'
    const minOut = document.createElement('span')
    const sep = document.createElement('span')
    sep.textContent = '—'
    const maxOut = document.createElement('span')
    minOut.textContent = String(startMin)
    maxOut.textContent = String(startMax)
    valuesRow.appendChild(minOut)
    valuesRow.appendChild(sep)
    valuesRow.appendChild(maxOut)

    wrapper.appendChild(range)
    wrapper.appendChild(valuesRow)
    section.appendChild(wrapper)
    root.appendChild(section)

    // Инициализация noUiSlider напрямую
    try {
      const slider = noUiSlider.create(range, {
        range: { min: minBound, max: maxBound },
        start: [startMin, startMax],
        connect: true,
        tooltips: true,
        format: {
          to: (value) => Math.round(Number(value)),
          from: (value) => Number(value)
        }
      })

      slider.on('update', (values) => {
        const [a, b] = values
        minOut.textContent = String(Math.round(Number(a)))
        maxOut.textContent = String(Math.round(Number(b)))
      })

      slider.on('change', async (values) => {
        const [a, b] = values
        this.priceMin = Math.round(Number(a))
        this.priceMax = Math.round(Number(b))
        this.updateUrl()
        await this.loadFacets()
      })

      this.priceSlider = { range, slider, minBound, maxBound, minOut, maxOut }
    } catch (err) {
      console.error('Failed to init price range slider', err)
      this.priceSlider = null
    }
  }

  renderSelected() {
    const root = this.hasSelectedTarget ? this.selectedTarget : null
    if (!root) return
    const entries = Array.from(this.selected.entries()).flatMap(([code, set]) =>
      Array.from(set).map(v => ({ code, value: String(v) }))
    )
    if (this.priceMin != null || this.priceMax != null) {
      const label = `${this.priceMin ?? ''}—${this.priceMax ?? ''}`
      entries.unshift({ code: 'price', value: label })
    }
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
    if (code === 'price') {
      this.priceMin = null
      this.priceMax = null
      // Сбросим слайдер, если есть
      try {
        if (this.priceSlider?.range?.noUiSlider) {
          const { minBound, maxBound } = this.priceSlider
          this.priceSlider.range.noUiSlider.set([minBound, maxBound])
        }
      } catch (_) {}
      this.updateUrl()
      await this.loadFacets()
      return
    }
    if (!this.selected.has(code)) return
    const set = this.selected.get(code)
    set.delete(value)
    if (set.size === 0) this.selected.delete(code)
    this.updateUrl()
    await this.loadFacets()
  }

  async clearAll() {
    this.selected.clear()
    this.priceMin = null
    this.priceMax = null
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


