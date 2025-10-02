# CSS/Tailwind Browser Compatibility Rules for Cursor AI

## Project Stack
- Tailwind CSS 3
- Webpack + Symfony 7
- Target: Russian e-commerce market (< 1000 users/day)

## Browser Support Policy

### Minimum Supported Versions
- Chrome/Edge: 100+ (2022+)
- Firefox: 100+ (2022+)
- Safari: 15+ (iOS 15+, macOS Big Sur+)
- Yandex Browser: 22+ (2022+)
- Samsung Internet: 18+
- Coverage: 95-97% of Russian audience

### Explicitly NOT Supported
- Internet Explorer 11 (0.3% users in Russia)
- Opera Mini extreme mode
- Safari < 13
- UC Browser

---

## CSS Writing Rules

### 1. Modern CSS Properties - Usage Guidelines

#### ✅ SAFE TO USE (98%+ support)

```css
/* Flexbox - full support */
.container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
}

/* CSS Grid - full support */
.grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
}

/* CSS Custom Properties */
:root {
  --primary-color: #3490dc;
  --spacing-unit: 1rem;
}

/* Transform & Transition */
.element {
  transform: translateY(-10px) scale(1.05);
  transition: all 0.3s ease;
}

/* Modern selectors */
.parent > .child { }
.element:not(.excluded) { }
.element:nth-child(odd) { }
⚠️ USE WITH CAUTION (requires fallback or checking)

/* aspect-ratio - Safari 15+, Chrome 88+ */
/* ⚠️ ALWAYS provide fallback */
.video-container {
  /* Fallback for older Safari */
  position: relative;
  padding-bottom: 56.25%; /* 16:9 */
}

.video-container iframe {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

/* Modern approach with @supports */
@supports (aspect-ratio: 16/9) {
  .video-container {
    aspect-ratio: 16/9;
    padding-bottom: 0;
  }
  
  .video-container iframe {
    position: static;
  }
}

/* gap in Flexbox - Safari 14.1+, Chrome 84+ */
/* ⚠️ Fallback: use margin on children */
.flex-container {
  display: flex;
}

/* Fallback for older browsers */
.flex-container > * + * {
  margin-left: 1rem;
}

/* Modern approach */
@supports (gap: 1rem) {
  .flex-container {
    gap: 1rem;
  }
  
  .flex-container > * + * {
    margin-left: 0;
  }
}

/* backdrop-filter - Chrome 76+, Safari 9+ (with prefix) */
/* ⚠️ Provide fallback background */
.glass-effect {
  background: rgba(255, 255, 255, 0.8); /* Fallback */
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px); /* Safari prefix */
}

/* :has() selector - Safari 15.4+, Chrome 105+ */
/* ⚠️ Use @supports or avoid in critical styles */
@supports selector(:has(> *)) {
  .parent:has(.special-child) {
    border: 2px solid blue;
  }
}

/* scroll-behavior - Chrome 61+, Safari 15.4+ */
/* ⚠️ Progressive enhancement, not critical */
html {
  scroll-behavior: smooth; /* Won't break if not supported */
}
❌ DO NOT USE (poor support or too new)

/* Container queries - too new */
/* ❌ AVOID */
@container (min-width: 400px) {
  .card { font-size: 1.5rem; }
}

/* CSS Cascade Layers - Chrome 99+, Safari 15.4+ */
/* ❌ AVOID - too new */
@layer base {
  h1 { font-size: 2rem; }
}

/* Subgrid - Chrome 117+, Safari 16+ */
/* ❌ AVOID - too new */
.grid {
  display: grid;
  grid-template-columns: subgrid;
}

/* :is() and :where() with complex selectors */
/* ⚠️ Simple usage OK, complex patterns avoid */
/* ❌ AVOID complex patterns */
:is(.parent1, .parent2) :is(.child1, .child2) { }
2. Tailwind CSS - Browser Compatibility
Automatic Prefixing (Autoprefixer handles)
Tailwind classes automatically get vendor prefixes:


<!-- You write -->
<div class="flex items-center">

<!-- Autoprefixer outputs -->
<style>
.flex {
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
}
.items-center {
  -webkit-box-align: center;
  -ms-flex-align: center;
  align-items: center;
}
</style>
Tailwind Classes with Browser Caveats

<!-- ✅ SAFE - Full support -->
<div class="flex flex-col gap-4">  <!-- gap works in Flex since Chrome 84+, Safari 14.1+ -->

<!-- ⚠️ CAUTION - aspect-ratio -->
<!-- Provide fallback for Safari < 15 -->
<div class="aspect-video">  <!-- aspect-ratio: 16/9 -->
  <!-- For older Safari, use padding-bottom hack -->
</div>

<!-- Alternative with manual fallback -->
<div class="relative pb-[56.25%] supports-[aspect-ratio]:pb-0 supports-[aspect-ratio]:aspect-video">
  <iframe class="absolute inset-0 w-full h-full supports-[aspect-ratio]:static"></iframe>
</div>

<!-- ✅ SAFE - backdrop-blur with Tailwind -->
<div class="bg-white/80 backdrop-blur-lg">
  <!-- Autoprefixer adds -webkit-backdrop-filter automatically -->
</div>

<!-- ❌ AVOID - arbitrary values with new features -->
<div class="grid-cols-[subgrid]">  <!-- subgrid not supported -->

<!-- ✅ SAFE - Use standard values -->
<div class="grid grid-cols-3">
3. Feature Detection Rules
Always use @supports for new CSS features

/* ✅ CORRECT - Feature detection */
.element {
  /* Fallback styles */
  position: relative;
  padding-bottom: 75%;
}

@supports (aspect-ratio: 4/3) {
  .element {
    aspect-ratio: 4/3;
    padding-bottom: 0;
  }
}

/* ✅ CORRECT - Progressive enhancement */
.card {
  background: rgba(255, 255, 255, 0.9); /* Fallback */
}

@supports (backdrop-filter: blur(10px)) {
  .card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
  }
}

/* ❌ WRONG - No fallback */
.card {
  aspect-ratio: 16/9; /* Breaks in Safari < 15 */
}
JavaScript Feature Detection

// ✅ CORRECT - Check before using
if (CSS.supports('aspect-ratio', '16/9')) {
  document.documentElement.classList.add('supports-aspect-ratio');
} else {
  document.documentElement.classList.add('no-aspect-ratio');
}

// Use in Tailwind
// <div class="supports-aspect-ratio:aspect-video no-aspect-ratio:pb-[56.25%]">

// ✅ CORRECT - IntersectionObserver check
if ('IntersectionObserver' in window) {
  // Use modern lazy loading
  const observer = new IntersectionObserver(callback);
} else {
  // Fallback: load all images immediately
  images.forEach(img => img.src = img.dataset.src);
}
4. Vendor Prefixes Rules
Let Autoprefixer Handle It

/* ✅ CORRECT - Write standard CSS */
.element {
  display: flex;
  user-select: none;
  backdrop-filter: blur(10px);
}

/* Autoprefixer will output: */
.element {
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  -webkit-user-select: none;
  -moz-user-select: none;
  -ms-user-select: none;
  user-select: none;
  -webkit-backdrop-filter: blur(10px);
  backdrop-filter: blur(10px);
}

/* ❌ WRONG - Don't write prefixes manually */
.element {
  -webkit-box-flex: 1;
  -ms-flex: 1;
  flex: 1;
  /* Autoprefixer does this better */
}
Exceptions - Manual Prefixes (rare cases)

/* ⚠️ Only if Autoprefixer doesn't handle */
.sticky-element {
  position: -webkit-sticky; /* Safari < 13 */
  position: sticky;
}

/* Gradient prefixes (Autoprefixer handles, but FYI) */
.gradient {
  background: linear-gradient(to right, #000, #fff);
  /* Autoprefixer adds -webkit- automatically */
}
5. Tailwind Configuration for Browser Support

// tailwind.config.js

module.exports = {
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
  ],
  
  theme: {
    extend: {
      // ✅ SAFE - Standard extensions
      colors: {
        primary: '#3490dc',
      },
      spacing: {
        '128': '32rem',
      },
    },
  },
  
  // ⚠️ Be careful with experimental features
  future: {
    // Don't enable experimental features for production
    hoverOnlyWhenSupported: false, // Can cause issues
  },
  
  plugins: [
    require('@tailwindcss/forms'), // ✅ SAFE
    require('@tailwindcss/typography'), // ✅ SAFE
    // ❌ Avoid experimental plugins
  ],
  
  // Safelist for dynamic classes
  safelist: [
    // Classes generated dynamically in PHP/Twig
    'bg-red-500',
    'bg-green-500',
    {
      pattern: /bg-(red|green|blue)-(400|500|600)/,
    },
  ],
}
6. PostCSS Configuration

// postcss.config.js

module.exports = {
  plugins: [
    require('tailwindcss'),
    
    // ✅ REQUIRED - Autoprefixer based on browserslist
    require('autoprefixer')({
      flexbox: 'no-2009', // Modern flexbox (drop IE 10)
      grid: 'autoplace',  // Modern grid with autoplace
      overrideBrowserslist: undefined, // Use .browserslistrc
    }),
    
    // Production optimizations
    ...(process.env.NODE_ENV === 'production' ? [
      require('cssnano')({
        preset: ['default', {
          discardComments: { removeAll: true },
          normalizeWhitespace: true,
          // Don't remove fallback code
          reduceIdents: false,
        }]
      })
    ] : [])
  ],
};
7. Browserslist Configuration

# .browserslistrc

# ✅ CORRECT - Russian market optimization
> 0.5% in RU
last 2 versions
not dead
not IE 11
iOS >= 15

# This targets:
# - Chrome 119-120
# - Firefox 120-121
# - Safari 17.1-17.2
# - Yandex Browser 23.9-23.11
# - Edge 119-120
# Coverage: ~97% in Russia
Check Your Config

# Verify what browsers are targeted
npx browserslist

# Check coverage
npx browserslist --coverage=RU

# Should output something like:
# Chrome: 63.5%
# Safari: 15.2%
# Firefox: 3.8%
# Edge: 3.2%
# Yandex: 12.1%
# Total: ~97.8%
8. Common Patterns with Fallbacks
Pattern 1: Aspect Ratio

<!-- Twig Template -->
<div class="aspect-ratio-container">
  <iframe 
    src="..." 
    class="aspect-ratio-content"
  ></iframe>
</div>

/* CSS with fallback */
.aspect-ratio-container {
  /* Fallback: padding-bottom hack */
  position: relative;
  padding-bottom: 56.25%; /* 16:9 */
  height: 0;
}

.aspect-ratio-content {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

/* Modern browsers */
@supports (aspect-ratio: 16/9) {
  .aspect-ratio-container {
    aspect-ratio: 16/9;
    padding-bottom: 0;
    height: auto;
  }
  
  .aspect-ratio-content {
    position: static;
  }
}
Pattern 2: Gap in Flexbox

<!-- Tailwind approach -->
<div class="flex flex-wrap gap-4">
  <div>Item 1</div>
  <div>Item 2</div>
  <div>Item 3</div>
</div>

<!-- ⚠️ For Safari < 14.1, gap doesn't work in flex -->
<!-- Alternative with negative margin -->
<div class="flex flex-wrap -m-2">
  <div class="p-2">Item 1</div>
  <div class="p-2">Item 2</div>
  <div class="p-2">Item 3</div>
</div>
Pattern 3: Backdrop Filter

<!-- Tailwind with automatic prefix -->
<div class="bg-white/80 backdrop-blur-lg rounded-lg">
  Glass morphism effect
</div>

<!-- CSS output (Autoprefixer adds -webkit-) -->
<style>
.backdrop-blur-lg {
  -webkit-backdrop-filter: blur(16px);
  backdrop-filter: blur(16px);
}
</style>

<!-- Fallback if blur not supported -->
<div class="bg-white/90 supports-[backdrop-filter]:bg-white/80 supports-[backdrop-filter]:backdrop-blur-lg">
  Graceful degradation
</div>
Pattern 4: Sticky Position

/* ✅ CORRECT - With prefix for old Safari */
.sticky-header {
  position: -webkit-sticky; /* Safari < 13 */
  position: sticky;
  top: 0;
  z-index: 10;
}

/* Tailwind equivalent */
/* Autoprefixer handles this automatically */

<header class="sticky top-0 z-10">
  <!-- Autoprefixer adds -webkit-sticky -->
</header>
9. Testing Requirements
Before Committing CSS Changes

# 1. Build production bundle
npm run build

# 2. Check bundle size
ls -lh public/build/*.css

# 3. Verify Autoprefixer worked
# Look for vendor prefixes in output CSS
cat public/build/app.css | grep -E "(webkit|moz|ms)-"

# 4. Check browserslist coverage
npx browserslist --coverage=RU
Manual Browser Testing (Minimum)
Chrome (latest) - 60% of users
Yandex Browser (latest) - 15-20% of users
Safari iOS (latest) - 25-30% of mobile users
Firefox (latest) - 3-5% of users
Testing Checklist
 Layout intact (flex, grid)
 Spacing correct (margin, padding, gap)
 Animations smooth (transitions, transforms)
 Colors correct (including transparency)
 Responsive design works (all breakpoints)
 No console errors
 Hover/focus states work
10. AI Agent Instructions for CSS Writing
When writing CSS, ALWAYS:

Check browser support before using new CSS features

Visit caniuse.com mentally or use @supports
If support < 95%, provide fallback
Write fallback-first, then enhance


/* ✅ This order */
.element {
  /* Fallback for old browsers */
  padding-bottom: 56.25%;
}

@supports (aspect-ratio: 16/9) {
  .element {
    /* Enhancement for modern browsers */
    aspect-ratio: 16/9;
    padding-bottom: 0;
  }
}

/* ❌ Not this order */
.element {
  aspect-ratio: 16/9; /* Breaks old Safari */
}
Let Autoprefixer work - Don't write vendor prefixes manually


/* ✅ Write this */
.element {
  display: flex;
  user-select: none;
}

/* ❌ Not this */
.element {
  display: -webkit-box;
  display: flex;
  -webkit-user-select: none;
  user-select: none;
}
Use Tailwind first, custom CSS second


<!-- ✅ Prefer Tailwind -->
<div class="flex items-center gap-4 px-6 py-4">

<!-- ⚠️ Custom CSS only if repeated 3+ times -->
<div class="card">
Test responsive at all breakpoints


<!-- ✅ Always consider mobile-first -->
<div class="text-sm md:text-base lg:text-lg">
Comment browser-specific code


/* Safari < 15 fallback for aspect-ratio */
.video {
  position: relative;
  padding-bottom: 56.25%;
}
Avoid experimental features in production

No container queries
No cascade layers
No subgrid (yet)
11. Quick Reference: Safe vs Unsafe CSS
✅ ALWAYS SAFE (use freely)
display: flex, grid
position: relative, absolute, fixed, sticky (with prefix)
margin, padding, all box model
color, background, border
font-*, text-*, line-height
transform, transition, animation
box-shadow, border-radius
CSS variables (--custom-property)
Media queries
Pseudo-classes (:hover, :focus, :active)
Pseudo-elements (::before, ::after)
⚠️ USE WITH FALLBACK
aspect-ratio → padding-bottom fallback
gap in flexbox → margin fallback
backdrop-filter → solid background fallback
:has() → avoid or use @supports
scroll-behavior → JS fallback
object-fit → fallback layout
❌ AVOID (not ready)
Container queries (@container)
Cascade layers (@layer)
Subgrid (grid-template-columns: subgrid)
:is() with complex selectors
View transitions
Anchor positioning
12. Error Messages for Common Mistakes
When AI agent uses unsupported CSS:


⚠️ BROWSER COMPATIBILITY WARNING

You used: aspect-ratio: 16/9
Support: Safari 15+ (released 2021)
Coverage: ~95% (5% users affected)

Action required:
1. Add fallback using padding-bottom hack
2. Use @supports for progressive enhancement

Example:
.element {
  position: relative;
  padding-bottom: 56.25%; /* fallback */
}

@supports (aspect-ratio: 16/9) {
  .element {
    aspect-ratio: 16/9;
    padding-bottom: 0;
  }
}
13. Pre-commit Checklist
Before committing CSS changes, verify:

 No vendor prefixes written manually (let Autoprefixer handle)
 New CSS features have fallbacks
 No experimental CSS features
 Tested in Chrome + Safari iOS + Yandex Browser
 npm run build completes without errors
 CSS bundle size reasonable (< 250 KB)
 No console warnings about unsupported properties
 Responsive design works on mobile/tablet/desktop
Summary for AI Agent
When writing CSS in this project:

Target: Russian market, 95-97% browser coverage
Exclude: IE11, Opera Mini, Safari < 13
Approach: Progressive enhancement (fallback → enhancement)
Tools: Tailwind first, Autoprefixer handles prefixes
New features: Always use @supports + fallback
Testing: Minimum Chrome + Safari iOS + Yandex Browser
Golden Rule: If a CSS feature is not in the "ALWAYS SAFE" list, provide a fallback or use @supports.



---

## Дополнительный файл: `.cursor/browser-compat.json`

Для более глубокой интеграции создай JSON файл с метаданными:

```json
{
  "browserSupport": {
    "target": "Russian e-commerce market",
    "minVersions": {
      "chrome": 100,
      "firefox": 100,
      "safari": 15,
      "edge": 100,
      "yandex": 22,
      "ios": 15,
      "samsung": 18
    },
    "excluded": ["ie", "opera_mini", "uc"],
    "coverage": "95-97%"
  },
  
  "cssFeatures": {
    "safe": [
      "flexbox",
      "grid",
      "custom-properties",
      "transform",
      "transition",
      "media-queries",
      "pseudo-classes",
      "pseudo-elements"
    ],
    
    "needsFallback": [
      {
        "feature": "aspect-ratio",
        "minVersion": { "chrome": 88, "safari": 15, "firefox": 89 },
        "fallback": "padding-bottom percentage hack",
        "example": "aspect-ratio-pattern"
      },
      {
        "feature": "gap in flexbox",
        "minVersion": { "chrome": 84, "safari": 14.1, "firefox": 63 },
        "fallback": "margin on children or negative margin on parent",
        "example": "flexbox-gap-pattern"
      },
      {
        "feature": "backdrop-filter",
        "minVersion": { "chrome": 76, "safari": 9, "firefox": 103 },
        "fallback": "solid background color with opacity",
        "example": "backdrop-filter-pattern"
      },
      {
        "feature": ":has() selector",
        "minVersion": { "chrome": 105, "safari": 15.4, "firefox": 103 },
        "fallback": "avoid or use @supports",
        "example": "has-selector-pattern"
      }
    ],
    
    "forbidden": [
      "container-queries",
      "cascade-layers",
      "subgrid",
      "view-transitions",
      "anchor-positioning"
    ]
  },
  
  "patterns": {
    "aspect-ratio-pattern": {
      "fallback": ".element { position: relative; padding-bottom: 56.25%; height: 0; } .element > * { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }",
      "modern": "@supports (aspect-ratio: 16/9) { .element { aspect-ratio: 16/9; padding-bottom: 0; height: auto; } .element > * { position: static; } }"
    },
    "flexbox-gap-pattern": {
      "fallback": ".flex-container { display: flex; margin: -0.5rem; } .flex-container > * { margin: 0.5rem; }",
      "modern": "@supports (gap: 1rem) { .flex-container { gap: 1rem; margin: 0; } .flex-container > * { margin: 0; } }"
    },
    "backdrop-filter-pattern": {
      "fallback": ".glass { background: rgba(255, 255, 255, 0.9); }",
      "modern": "@supports (backdrop-filter: blur(10px)) { .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); } }"
    }
  },
  
  "testing": {
    "required": [
      "Chrome latest",
      "Safari iOS latest",
      "Yandex Browser latest"
    ],
    "optional": [
      "Firefox latest",
      "Edge latest",
      "Samsung Internet"
    ],
    "commands": {
      "build": "npm run build",
      "checkSize": "ls -lh public/build/*.css",
      "checkPrefixes": "cat public/build/app.css | grep -E '(webkit|moz|ms)-'",
      "checkCoverage": "npx browserslist --coverage=RU"
    }
  }
}