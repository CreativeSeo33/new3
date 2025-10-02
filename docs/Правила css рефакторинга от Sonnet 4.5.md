nnet# Правила написания и организации CSS в проекте

## 1. Архитектура и структура файлов

### Организация CSS файлов

```
assets/
├── styles/
│   ├── base/
│   │   ├── _reset.css
│   │   ├── _typography.css
│   │   └── _variables.css
│   ├── components/
│   │   ├── _buttons.css
│   │   ├── _forms.css
│   │   ├── _cards.css
│   │   └── _modals.css
│   ├── layouts/
│   │   ├── _header.css
│   │   ├── _footer.css
│   │   ├── _sidebar.css
│   │   └── _grid.css
│   ├── pages/
│   │   ├── _home.css
│   │   ├── _dashboard.css
│   │   └── _profile.css
│   ├── utilities/
│   │   └── _helpers.css
│   └── app.css (главный файл, импортирующий все остальные)
```

### Правило импорта в `app.css`

```css
/* Base */
@import './base/_variables.css';
@import './base/_reset.css';
@import './base/_typography.css';

/* Layouts */
@import './layouts/_header.css';
@import './layouts/_footer.css';
@import './layouts/_sidebar.css';
@import './layouts/_grid.css';

/* Components */
@import './components/_buttons.css';
@import './components/_forms.css';
@import './components/_cards.css';
@import './components/_modals.css';

/* Pages */
@import './pages/_home.css';
@import './pages/_dashboard.css';

/* Utilities */
@import './utilities/_helpers.css';
```

## 2. Tailwind CSS - основные правила

### Приоритет использования классов

1. **ПЕРВЫЙ ПРИОРИТЕТ**: Используй утилитные классы Tailwind
2. **ВТОРОЙ ПРИОРИТЕТ**: Создавай компонентные классы через `@apply`
3. **ТРЕТИЙ ПРИОРИТЕТ**: Кастомный CSS только для уникальных случаев

### Правила написания в Twig

```twig
{# ✅ ПРАВИЛЬНО: Классы на одной строке для простых элементов #}
<button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
    Отправить
</button>

{# ✅ ПРАВИЛЬНО: Многострочная запись для сложных элементов #}
<div class="
    flex items-center justify-between
    px-6 py-4
    bg-white border border-gray-200 rounded-lg shadow-sm
    hover:shadow-md transition-shadow duration-200
">
    Контент
</div>

{# ✅ ПРАВИЛЬНО: Используй переменные для повторяющихся наборов классов #}
{% set card_classes = "bg-white border border-gray-200 rounded-lg shadow-sm p-6" %}
<div class="{{ card_classes }}">Карточка 1</div>
<div class="{{ card_classes }}">Карточка 2</div>

{# ❌ НЕПРАВИЛЬНО: Не смешивай inline стили с Tailwind #}
<div class="px-4 py-2" style="margin-top: 20px;">Плохо</div>

{# ✅ ПРАВИЛЬНО: Всё через Tailwind #}
<div class="px-4 py-2 mt-5">Хорошо</div>
```

### Группировка классов Tailwind

Соблюдай порядок групп классов:

```twig
<div class="
    {# 1. Layout (display, position) #}
    flex flex-col items-center justify-center
    
    {# 2. Box Model (width, height, padding, margin) #}
    w-full max-w-md p-6 m-4
    
    {# 3. Typography #}
    text-lg font-semibold text-gray-800
    
    {# 4. Visual (background, border, shadow) #}
    bg-white border border-gray-300 rounded-lg shadow-md
    
    {# 5. Misc (cursor, overflow) #}
    cursor-pointer overflow-hidden
    
    {# 6. Pseudo-classes и состояния #}
    hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500
    
    {# 7. Responsive модификаторы #}
    md:flex-row md:p-8 lg:max-w-2xl
">
    Контент
</div>
```

## 3. Создание компонентных классов

### Когда создавать компонентный класс

Создавай компонентный класс если:
- Набор классов повторяется **более 3 раз**
- Компонент имеет несколько вариантов (модификаторов)
- Логика стилизации сложная и требует семантического имени

### Правильное использование `@apply`

```css
/* ✅ ПРАВИЛЬНО: Компонентный класс с @apply */
.btn {
    @apply px-4 py-2 rounded font-medium transition-colors duration-200;
}

.btn-primary {
    @apply bg-blue-600 text-white hover:bg-blue-700;
}

.btn-secondary {
    @apply bg-gray-200 text-gray-800 hover:bg-gray-300;
}

.btn-lg {
    @apply px-6 py-3 text-lg;
}

/* ❌ НЕПРАВИЛЬНО: Не создавай класс для одного свойства */
.mt-custom {
    @apply mt-4;
}

/* ✅ ПРАВИЛЬНО: Используй Tailwind напрямую */
<div class="mt-4">...</div>
```

### В Twig используй так:

```twig
{# ✅ ПРАВИЛЬНО #}
<button class="btn btn-primary">Сохранить</button>
<button class="btn btn-secondary btn-lg">Отмена</button>

{# Или комбинируй с утилитами Tailwind #}
<button class="btn btn-primary mt-4 w-full">Войти</button>
```

## 4. Именование классов (BEM для кастомных компонентов)

```css
/* ✅ ПРАВИЛЬНО: BEM методология */
.product-card { }
.product-card__image { }
.product-card__title { }
.product-card__price { }
.product-card--featured { }
.product-card--sale { }

/* ❌ НЕПРАВИЛЬНО: Вложенность и неясные имена */
.card .img { }
.card .title-text { }
```

### В Twig:

```twig
<div class="product-card product-card--featured">
    <img src="..." class="product-card__image" alt="">
    <h3 class="product-card__title">Название</h3>
    <p class="product-card__price">1000 ₽</p>
</div>
```

## 5. Responsive дизайн

### Правила breakpoints

```css
/* Tailwind breakpoints (не меняй их без крайней необходимости) */
/* sm: 640px */
/* md: 768px */
/* lg: 1024px */
/* xl: 1280px */
/* 2xl: 1536px */
```

### Mobile-first подход

```twig
{# ✅ ПРАВИЛЬНО: Mobile-first #}
<div class="
    text-sm        {# базовый стиль для mobile #}
    md:text-base   {# >= 768px #}
    lg:text-lg     {# >= 1024px #}
">
    Адаптивный текст
</div>

{# ✅ ПРАВИЛЬНО: Адаптивная сетка #}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <div>Item 1</div>
    <div>Item 2</div>
    <div>Item 3</div>
</div>
```

## 6. Кастомизация Tailwind

### `tailwind.config.js` - правила расширения

```javascript
module.exports = {
    content: [
        "./assets/**/*.js",
        "./templates/**/*.html.twig",
    ],
    theme: {
        extend: {
            // ✅ Расширяй, не перезаписывай базовые значения
            colors: {
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    // ...
                    900: '#1e3a8a',
                },
                brand: '#FF6B35',
            },
            spacing: {
                '128': '32rem',
                '144': '36rem',
            },
            fontSize: {
                '2xs': '0.625rem',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
}
```

## 7. Правила рефакторинга CSS

### Шаг 1: Анализ

```bash
# Найди все кастомные CSS файлы
find assets/styles -name "*.css" -type f

# Найди все Twig файлы с inline стилями
grep -r "style=" templates/
```

### Шаг 2: Приоритеты рефакторинга

1. **Удали дублирующийся код**
   ```css
   /* ❌ Было */
   .btn-save { padding: 8px 16px; background: blue; }
   .btn-cancel { padding: 8px 16px; background: gray; }
   
   /* ✅ Стало */
   .btn { @apply px-4 py-2; }
   .btn-save { @apply btn bg-blue-600; }
   .btn-cancel { @apply btn bg-gray-600; }
   ```

2. **Замени магические числа на Tailwind значения**
   ```css
   /* ❌ Было */
   .container { margin-top: 23px; }
   
   /* ✅ Стало - используй ближайшее Tailwind значение */
   <div class="mt-6">{# 24px #}</div>
   ```

3. **Преобразуй inline стили в классы**
   ```twig
   {# ❌ Было #}
   <div style="display: flex; align-items: center; gap: 8px;">
   
   {# ✅ Стало #}
   <div class="flex items-center gap-2">
   ```

### Шаг 3: Рефакторинг сложных компонентов

```twig
{# ❌ Было #}
<div class="custom-modal">
    <div class="modal-header">
        <h2 class="modal-title">Заголовок</h2>
    </div>
    <div class="modal-body">
        Контент
    </div>
</div>

<style>
.custom-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.modal-header {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
}
.modal-title {
    font-size: 1.5rem;
    font-weight: 600;
}
.modal-body {
    font-size: 0.875rem;
    color: #6b7280;
}
</style>

{# ✅ Стало #}
<div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white p-6 rounded-lg shadow-xl">
    <div class="mb-4 pb-3 border-b border-gray-200">
        <h2 class="text-2xl font-semibold">Заголовок</h2>
    </div>
    <div class="text-sm text-gray-600">
        Контент
    </div>
</div>
```

## 8. Правила для работы с Twig компонентами

### Создание переиспользуемых компонентов

```twig
{# templates/components/button.html.twig #}
{% set base_classes = "px-4 py-2 rounded font-medium transition-colors duration-200" %}

{% set variant_classes = {
    'primary': 'bg-blue-600 text-white hover:bg-blue-700',
    'secondary': 'bg-gray-200 text-gray-800 hover:bg-gray-300',
    'danger': 'bg-red-600 text-white hover:bg-red-700',
} %}

{% set size_classes = {
    'sm': 'px-3 py-1 text-sm',
    'md': 'px-4 py-2',
    'lg': 'px-6 py-3 text-lg',
} %}

<button 
    class="{{ base_classes }} {{ variant_classes[variant|default('primary')] }} {{ size_classes[size|default('md')] }} {{ class|default('') }}"
    {{ type ? 'type="' ~ type ~ '"' : '' }}
>
    {{ label|default('Button') }}
</button>
```

### Использование:

```twig
{{ include('components/button.html.twig', {
    label: 'Сохранить',
    variant: 'primary',
    size: 'lg',
    type: 'submit',
    class: 'w-full mt-4'
}) }}
```

## 9. Performance и оптимизация

### Правила для продакшена

```javascript
// webpack.config.js
Encore
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: [
                require('tailwindcss'),
                require('autoprefixer'),
                // ✅ Для production - purge неиспользуемых классов
                ...(Encore.isProduction() ? [
                    require('cssnano')({
                        preset: ['default', {
                            discardComments: { removeAll: true },
                        }]
                    })
                ] : [])
            ],
        };
    });
```

### Настройка purge в Tailwind

```javascript
// tailwind.config.js
module.exports = {
    content: [
        "./assets/**/*.js",
        "./templates/**/*.html.twig",
        "./src/**/*.php", // если используешь классы в PHP
    ],
    safelist: [
        // ✅ Защити динамические классы от удаления
        'bg-red-500',
        'bg-green-500',
        /^text-(red|green|blue)-/,
    ],
}
```

## 10. Чек-лист для ИИ агента при рефакторинге

### Перед началом работы:

- [ ] Проанализируй все файлы в `assets/styles/`
- [ ] Найди все Twig файлы с inline стилями
- [ ] Составь список повторяющихся паттернов CSS
- [ ] Определи компоненты, которые нужно создать

### Во время рефакторинга:

- [ ] Заменяй inline стили на Tailwind классы
- [ ] Используй `@apply` только для часто повторяющихся комбинаций (3+ раза)
- [ ] Группируй классы по категориям (layout → box model → typography → visual → states → responsive)
- [ ] Создавай Twig компоненты для сложных UI элементов
- [ ] Удаляй неиспользуемые CSS файлы и классы
- [ ] Проверяй responsive поведение на всех breakpoints

### После рефакторинга:

- [ ] Запусти `npm run build` и проверь размер итогового CSS
- [ ] Протестируй все страницы в браузере
- [ ] Проверь консоль на предупреждения Tailwind
- [ ] Убедись, что все динамические классы в `safelist`
- [ ] Обнови документацию проекта

## 11. Частые ошибки и как их избежать

```twig
{# ❌ ОШИБКА 1: Использование !important #}
<div class="!mt-10">Плохо</div>

{# ✅ ПРАВИЛЬНО: Увеличь специфичность #}
<div class="mt-10">Хорошо</div>

{# ❌ ОШИБКА 2: Создание утилитарного класса для одного свойства #}
.custom-margin { @apply mt-4; }

{# ✅ ПРАВИЛЬНО: Используй Tailwind напрямую #}
<div class="mt-4">Хорошо</div>

{# ❌ ОШИБКА 3: Произвольные значения везде #}
<div class="mt-[13px] px-[27px]">Плохо</div>

{# ✅ ПРАВИЛЬНО: Используй стандартные значения Tailwind #}
<div class="mt-3 px-6">Хорошо</div>

{# ❌ ОШИБКА 4: Дублирование длинных наборов классов #}
<div class="flex items-center justify-between px-6 py-4 bg-white border">Card 1</div>
<div class="flex items-center justify-between px-6 py-4 bg-white border">Card 2</div>
<div class="flex items-center justify-between px-6 py-4 bg-white border">Card 3</div>

{# ✅ ПРАВИЛЬНО: Создай компонент #}
{% set card_layout = "flex items-center justify-between px-6 py-4 bg-white border" %}
<div class="{{ card_layout }}">Card 1</div>
<div class="{{ card_layout }}">Card 2</div>
<div class="{{ card_layout }}">Card 3</div>
```

---

## Итоговые принципы

1. **Tailwind first** - используй утилитные классы по максимуму
2. **Компоненты для повторений** - создавай компонентные классы только при необходимости
3. **Mobile-first** - всегда начинай с мобильных стилей
4. **Группировка классов** - соблюдай логический порядок
5. **BEM для кастома** - используй методологию для сложных компонентов
6. **Twig компоненты** - выноси переиспользуемые UI элементы
7. **Никаких inline стилей** - всё через классы
8. **Семантичность** - код должен быть понятным и читаемым