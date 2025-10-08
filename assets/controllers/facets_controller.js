import { Controller } from '@hotwired/stimulus'
import noUiSlider from 'nouislider'

export default class extends Controller {
  static targets = ['list', 'products', 'selected', 'limit', 'sort']
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
    this.syncSortFromUrl()
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
    const sort = url.searchParams.get('sort') || ''
    if (this.hasCategoryIdValue && this.categoryIdValue) {
      q.category = String(this.categoryIdValue)
    } else if (text) {
      q.text = text
    }
    if (limit) q.limit = limit
    if (page) q.page = page
    if (sort) q.sort = sort
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
      const sort = linkUrl.searchParams.get('sort') || ''
      const url = new URL(window.location.href)
      // копируем f[...]
      Array.from(url.searchParams.keys()).forEach(k => { if (k.startsWith('f[')) url.searchParams.delete(k) })
      Array.from(linkUrl.searchParams.keys()).forEach(k => { if (k.startsWith('f[')) url.searchParams.set(k, linkUrl.searchParams.get(k)) })
      // применяем page/limit
      url.searchParams.set('page', page)
      if (limit) url.searchParams.set('limit', limit); else url.searchParams.delete('limit')
      if (sort) url.searchParams.set('sort', sort); else url.searchParams.delete('sort')
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
    this.syncSortFromUrl()
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

  syncSortFromUrl() {
    if (!this.hasSortTarget) return
    try {
      const url = new URL(window.location.href)
      const sort = url.searchParams.get('sort')
      if (sort) this.sortTarget.value = String(sort)
    } catch (_) {}
  }

  onSortChange(event) {
    const select = event.currentTarget
    const value = String(select.value || '')
    try {
      const url = new URL(window.location.href)
      if (value) url.searchParams.set('sort', value); else url.searchParams.delete('sort')
      url.searchParams.set('page', '1')
      window.history.pushState({}, '', url.toString())
    } catch (_) {}
    this.loadProducts()
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
    // Всегда рисуем секцию цены (range) первой
    try {
      const priceEntry = entries.find(([code, facet]) => facet && facet.type === 'range' && code === 'price')
      if (priceEntry) this.renderPriceFacet(root, priceEntry[1], meta)
    } catch (_) {}
    entries.forEach(([code, facet]) => {
      if (facet.type === 'range') {
        // Диапазоны (включая цену) уже обработаны выше / пропускаем
        return
      }
      const values = Array.isArray(facet.values) ? facet.values.filter(v => v != null) : []
      if (values.length === 0) return // не рисуем секцию без значений
      // Wrapper блока фильтра (по шаблону из {# CUSTOM FILTERS #})
      const wrapper = document.createElement('div')
      // У первого элемента не показываем верхнюю границу
      wrapper.className = 'border-gray-200 py-4'
      if (root.childElementCount > 0) {
        wrapper.classList.add('border-t')
      }

      const h3 = document.createElement('h3')
      h3.className = '-mx-2 -my-3 flow-root'

      const btn = document.createElement('button')
      btn.type = 'button'
      btn.className = 'flex w-full items-center justify-between bg-white px-2 py-3 text-gray-400 hover:text-gray-500'
      btn.setAttribute('aria-expanded', 'true')
      const sectionId = `filter-section-${code}`
      btn.setAttribute('aria-controls', sectionId)

      const btnTitle = document.createElement('span')
      btnTitle.className = 'font-medium text-gray-900'
      btnTitle.textContent = (meta[code]?.title || code)

      const icons = document.createElement('span')
      icons.className = 'ml-6 flex items-center'
      icons.innerHTML = `
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 plus" hidden>
          <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"></path>
        </svg>
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 minus">
          <path d="M4 10a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 10Z" clip-rule="evenodd" fill-rule="evenodd"></path>
        </svg>`

      btn.appendChild(btnTitle)
      btn.appendChild(icons)
      h3.appendChild(btn)

      const content = document.createElement('div')
      content.id = sectionId
      content.className = 'block pt-2'

      const inner = document.createElement('div')
      inner.className = 'space-y-1'

      values.forEach(v => {
        const row = document.createElement('div')
        row.className = 'flex items-center gap-2'
        const id = `${code}__${v.code}`

        const cb = document.createElement('input')
        cb.type = 'checkbox'
        cb.id = id
        cb.className = 'checkbox checkbox-primary'
        cb.dataset.action = 'change->facets#toggle'
        cb.dataset.facetsCode = code
        cb.dataset.facetsValue = v.label
        cb.disabled = v.count === 0
        cb.checked = this.selected.get(code)?.has(String(v.label)) || false

        const label = document.createElement('label')
        label.className = 'label-text text-base'
        label.htmlFor = id
        label.textContent = `${v.label} (${v.count})`

        row.appendChild(cb)
        row.appendChild(label)
        inner.appendChild(row)
      })

      content.appendChild(inner)

      // Тогглер раскрытия/сворачивания
      btn.addEventListener('click', () => {
        const expanded = btn.getAttribute('aria-expanded') === 'true'
        btn.setAttribute('aria-expanded', String(!expanded))
        if (expanded) {
          content.classList.add('hidden')
        } else {
          content.classList.remove('hidden')
        }
        const plusIcon = icons.querySelector('.plus')
        const minusIcon = icons.querySelector('.minus')
        if (plusIcon) plusIcon.toggleAttribute('hidden', !expanded)
        if (minusIcon) minusIcon.toggleAttribute('hidden', expanded)
      })

      wrapper.appendChild(h3)
      wrapper.appendChild(content)
      root.appendChild(wrapper)

      // Ограничиваем высоту содержимого секции фильтра примерно 7 строками
      // и включаем вертикальный скролл при превышении
      try {
        const rows = inner ? Array.from(inner.children) : []
        if (rows.length > 7) {
          const contentRect = content.getBoundingClientRect()
          const seventhRow = rows[6]
          const heightForSeven = Math.ceil(seventhRow.getBoundingClientRect().bottom - contentRect.top)
          content.style.maxHeight = heightForSeven + 'px'
          content.classList.add('overflow-y-auto')
        } else {
          content.style.maxHeight = ''
          content.classList.remove('overflow-y-auto')
        }
      } catch (_) {}
    })
  }

  renderPriceFacet(root, facet, meta) {
    // facet: { type:'range', min:number|string|null, max:number|string|null }
    const toNumber = (val) => {
      const direct = Number(val)
      if (Number.isFinite(direct)) return direct
      if (typeof val === 'string') {
        const normalized = val.replace(/\s+/g, '').replace(',', '.')
        const num = Number(normalized)
        if (Number.isFinite(num)) return num
      }
      return NaN
    }
    const parsedMin = toNumber(facet.min)
    const parsedMax = toNumber(facet.max)
    let minBound = Number.isFinite(parsedMin) ? parsedMin : 0
    let maxBound = Number.isFinite(parsedMax) ? parsedMax : 0
    // Если min == max (все товары с одинаковой ценой) — слегка расширим диапазон, чтобы noUiSlider отрисовал пипсы/ручки
    if (Number.isFinite(minBound) && Number.isFinite(maxBound) && maxBound <= minBound) {
      maxBound = minBound + 1
    }
    const startMin = (this.priceMin != null) ? this.priceMin : minBound
    const startMax = (this.priceMax != null) ? this.priceMax : maxBound

    const title = document.createElement('div')
    title.className = 'h3'
    title.textContent = (meta.price?.title || 'Цена, руб')
    const section = document.createElement('section')
    section.className = 'my-16 px-2'

    const wrapper = document.createElement('div')
    wrapper.className = 'space-y-3'

    const range = document.createElement('div')
    range.id = 'hs-price-range'

    wrapper.appendChild(range)
    section.appendChild(wrapper)
    // Вставляем заголовок перед секцией
    root.appendChild(title)
    root.appendChild(section)

    // Инициализация noUiSlider напрямую с пипсами/tooltip
    try {
      const slider = noUiSlider.create(range, {
        range: { min: minBound, max: maxBound },
        start: [startMin, startMax],
        connect: true,
        tooltips: true,
        // Пипсы для визуальных меток (5 равномерных значений)
        pips: (Number.isFinite(minBound) && Number.isFinite(maxBound) && maxBound > minBound)
          ? {
              mode: 'values',
              values: [
                minBound,
                Math.round(minBound + (maxBound - minBound) * 0.25),
                Math.round(minBound + (maxBound - minBound) * 0.5),
                Math.round(minBound + (maxBound - minBound) * 0.75),
                maxBound
              ],
              density: 20
            }
          : undefined,
        format: {
          to: (value) => Math.round(Number(value)),
          from: (value) => Number(value)
        }
      })

      // Навешиваем FlyonUI классы на внутренние элементы noUiSlider
      try {
        const addClasses = (el, classes) => {
          if (!el || !classes) return
          classes.split(/\s+/).filter(Boolean).forEach(cls => el.classList.add(cls))
        }
        // target (корневой элемент слайдера)
        addClasses(range, 'relative h-2 rounded-full bg-neutral/10')
        // connects/base/connect
        const base = range.querySelector('.noUi-base')
        addClasses(base, 'size-full relative z-1')
        const connects = range.querySelector('.noUi-connects')
        addClasses(connects, 'relative z-0 w-full h-2 rtl:rounded-e-full rtl:rounded-s-none rounded-s-full overflow-hidden')
        const connect = range.querySelector('.noUi-connect')
        addClasses(connect, 'absolute top-0 end-0 rtl:start-0 z-1 w-full h-full bg-primary origin-[0_0]')
        // origins/handles
        range.querySelectorAll('.noUi-origin').forEach(el => addClasses(el, 'absolute top-0 end-0 rtl:start-0 w-full h-full origin-[0_0] rounded-full'))
        range.querySelectorAll('.noUi-handle').forEach(el => addClasses(el, 'absolute top-1/2 end-0 rtl:start-0 size-4 bg-base-100 border-[3px] border-primary rounded-full translate-x-2/4 -translate-y-2/4 hover:cursor-grab active:cursor-grabbing hover:ring-2 ring-primary active:ring-[3px]'))
        range.querySelectorAll('.noUi-touch-area').forEach(el => addClasses(el, 'absolute -top-1 -bottom-1 -start-1 -end-1'))
        // tooltips
        range.querySelectorAll('.noUi-tooltip').forEach(el => addClasses(el, 'bg-neutral rounded-xl text-sm text-neutral-content shadow-base-300/20 py-1 px-2 mb-3 absolute bottom-full left-1/2 -translate-x-1/2 shadow-md'))
        // pips
        const pips = range.querySelector('.noUi-pips')
        if (pips) {
          addClasses(pips, 'relative w-full h-7 mt-3')
          range.querySelectorAll('.noUi-value').forEach(el => addClasses(el, 'absolute top-4 -translate-x-1/2 text-sm text-base-content/80'))
          range.querySelectorAll('.noUi-marker').forEach(el => addClasses(el, 'absolute h-4 border-s border-base-content/25'))
        }
      } catch (_) {}

      // tooltip обновляется самим noUiSlider

      slider.on('change', async (values) => {
        const [a, b] = values
        this.priceMin = Math.round(Number(a))
        this.priceMax = Math.round(Number(b))
        this.updateUrl()
        await this.loadFacets()
      })

      this.priceSlider = { range, slider, minBound, maxBound }
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
      try { root.classList.add('hidden') } catch (_) {}
      return
    }
    try { root.classList.remove('hidden') } catch (_) {}
    const wrap = document.createElement('div')
    wrap.className = 'selected-filters flex flex-wrap items-center gap-2'
    const title = document.createElement('span')
    title.className = 'text-sm text-gray-600 mr-2'
    title.textContent = 'Выбранные фильтры:'
    wrap.appendChild(title)
    entries.forEach(({ code, value }) => {
      const chip = document.createElement('span')
      chip.className = 'badge badge-soft badge-lg badge-primary removing:translate-x-5 removing:opacity-0 transition duration-300 ease-in-out'
      
      const title = (this.meta && this.meta[code] && this.meta[code].title) ? String(this.meta[code].title) : String(code)
      const label = document.createElement('span')
      label.textContent = `${title}: ${value}`
      
      const closeBtn = document.createElement('button')
      closeBtn.type = 'button'
      closeBtn.className = 'icon-[tabler--circle-x-filled] size-5 min-h-0 cursor-pointer px-0 opacity-70'
      closeBtn.dataset.action = 'click->facets#remove'
      closeBtn.dataset.facetsCode = code
      closeBtn.dataset.facetsValue = value
      closeBtn.setAttribute('aria-label', 'Close Button')
      
      chip.appendChild(label)
      chip.appendChild(closeBtn)
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


