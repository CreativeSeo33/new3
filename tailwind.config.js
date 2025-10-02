/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './templates/**/*.html.twig',
    './assets/admin/**/*.{js,vue,ts}',
    './assets/catalog/**/*.{js,vue,ts}',
    // './assets/shared/**/*.{js,vue,ts}', // shared более не используется
  ],
  theme: {
    container: {
      center: true,
      padding: {
        DEFAULT: '1rem',
        lg: '2rem',
      },
      screens: {
        sm: '640px',
        md: '768px',
        lg: '1024px',
        xl: '1280px',
        '2xl': '1536px',
      },
    },
    extend: {
      colors: {
        // Семантические токены — сохраняем для текущих классов
        border: 'hsl(var(--border) / <alpha-value>)',
        input: 'hsl(var(--input) / <alpha-value>)',
        ring: 'hsl(var(--ring) / <alpha-value>)',
        background: 'hsl(var(--background) / <alpha-value>)',
        foreground: 'hsl(var(--foreground) / <alpha-value>)',
        primary: {
          DEFAULT: 'hsl(var(--primary) / <alpha-value>)',
          foreground: 'hsl(var(--primary-foreground) / <alpha-value>)',
          // Новая шкала бренда 50..900
          50:  'rgb(235 239 255 / <alpha-value>)',
          100: 'rgb(221 227 255 / <alpha-value>)',
          200: 'rgb(195 204 252 / <alpha-value>)',
          300: 'rgb(161 173 248 / <alpha-value>)',
          400: 'rgb(120 135 241 / <alpha-value>)',
          500: 'rgb(60 80 224 / <alpha-value>)',
          600: 'rgb(49 66 190 / <alpha-value>)',
          700: 'rgb(38 53 156 / <alpha-value>)',
          800: 'rgb(30 43 127 / <alpha-value>)',
          900: 'rgb(20 31 95 / <alpha-value>)',
        },
        secondary: {
          DEFAULT: 'hsl(var(--secondary) / <alpha-value>)',
          foreground: 'hsl(var(--secondary-foreground) / <alpha-value>)',
        },
        muted: {
          DEFAULT: 'hsl(var(--muted) / <alpha-value>)',
          foreground: 'hsl(var(--muted-foreground) / <alpha-value>)',
        },
        accent: {
          DEFAULT: 'hsl(var(--accent) / <alpha-value>)',
          foreground: 'hsl(var(--accent-foreground) / <alpha-value>)',
        },
        destructive: {
          DEFAULT: 'hsl(var(--destructive) / <alpha-value>)',
          foreground: 'hsl(var(--destructive-foreground) / <alpha-value>)',
        },
        card: {
          DEFAULT: 'hsl(var(--card) / <alpha-value>)',
          foreground: 'hsl(var(--card-foreground) / <alpha-value>)',
        },

        // Нейтральные
        neutral: {
          50:  'rgb(248 250 252 / <alpha-value>)',
          100: 'rgb(241 245 249 / <alpha-value>)',
          200: 'rgb(226 232 240 / <alpha-value>)',
          300: 'rgb(203 213 225 / <alpha-value>)',
          400: 'rgb(148 163 184 / <alpha-value>)',
          500: 'rgb(100 116 139 / <alpha-value>)',
          600: 'rgb(71 85 105 / <alpha-value>)',
          700: 'rgb(51 65 85 / <alpha-value>)',
          800: 'rgb(30 41 59 / <alpha-value>)',
          900: 'rgb(15 23 42 / <alpha-value>)',
        },
        // Статусы
        success: {
          50:  'rgb(240 253 244 / <alpha-value>)',
          100: 'rgb(220 252 231 / <alpha-value>)',
          200: 'rgb(187 247 208 / <alpha-value>)',
          300: 'rgb(134 239 172 / <alpha-value>)',
          400: 'rgb(74 222 128 / <alpha-value>)',
          500: 'rgb(34 197 94 / <alpha-value>)',
          600: 'rgb(22 163 74 / <alpha-value>)',
          700: 'rgb(21 128 61 / <alpha-value>)',
          800: 'rgb(22 101 52 / <alpha-value>)',
          900: 'rgb(20 83 45 / <alpha-value>)',
        },
        warning: {
          50:  'rgb(255 251 235 / <alpha-value>)',
          100: 'rgb(254 243 199 / <alpha-value>)',
          200: 'rgb(253 230 138 / <alpha-value>)',
          300: 'rgb(252 211 77 / <alpha-value>)',
          400: 'rgb(251 191 36 / <alpha-value>)',
          500: 'rgb(245 158 11 / <alpha-value>)',
          600: 'rgb(217 119 6 / <alpha-value>)',
          700: 'rgb(180 83 9 / <alpha-value>)',
          800: 'rgb(146 64 14 / <alpha-value>)',
          900: 'rgb(120 53 15 / <alpha-value>)',
        },
        danger: {
          50:  'rgb(254 242 242 / <alpha-value>)',
          100: 'rgb(254 226 226 / <alpha-value>)',
          200: 'rgb(254 202 202 / <alpha-value>)',
          300: 'rgb(252 165 165 / <alpha-value>)',
          400: 'rgb(248 113 113 / <alpha-value>)',
          500: 'rgb(239 68 68 / <alpha-value>)',
          600: 'rgb(220 38 38 / <alpha-value>)',
          700: 'rgb(185 28 28 / <alpha-value>)',
          800: 'rgb(153 27 27 / <alpha-value>)',
          900: 'rgb(127 29 29 / <alpha-value>)',
        },
        info: {
          50:  'rgb(240 249 255 / <alpha-value>)',
          100: 'rgb(224 242 254 / <alpha-value>)',
          200: 'rgb(186 230 253 / <alpha-value>)',
          300: 'rgb(125 211 252 / <alpha-value>)',
          400: 'rgb(56 189 248 / <alpha-value>)',
          500: 'rgb(14 165 233 / <alpha-value>)',
          600: 'rgb(2 132 199 / <alpha-value>)',
          700: 'rgb(3 105 161 / <alpha-value>)',
          800: 'rgb(7 89 133 / <alpha-value>)',
          900: 'rgb(12 74 110 / <alpha-value>)',
        },

        // Алиасы
        brand: {
          50:  'rgb(235 239 255 / <alpha-value>)',
          100: 'rgb(221 227 255 / <alpha-value>)',
          200: 'rgb(195 204 252 / <alpha-value>)',
          300: 'rgb(161 173 248 / <alpha-value>)',
          400: 'rgb(120 135 241 / <alpha-value>)',
          500: 'rgb(60 80 224 / <alpha-value>)',
          600: 'rgb(49 66 190 / <alpha-value>)',
          700: 'rgb(38 53 156 / <alpha-value>)',
          800: 'rgb(30 43 127 / <alpha-value>)',
          900: 'rgb(20 31 95 / <alpha-value>)',
        },
        white: '#ffffff',
        black: '#000000',
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
        xl: '12px',
        '2xl': '16px',
        full: '9999px',
      },
      keyframes: {
        'accordion-down': {
          from: { height: '0' },
          to: { height: 'var(--radix-accordion-content-height)' },
        },
        'accordion-up': {
          from: { height: 'var(--radix-accordion-content-height)' },
          to: { height: '0' },
        },
      },
      animation: {
        'accordion-down': 'accordion-down 0.2s ease-out',
        'accordion-up': 'accordion-up 0.2s ease-out',
      },
      // Шрифты
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'],
        serif: ['ui-serif', 'Georgia', 'Cambria', '"Times New Roman"', 'Times', 'serif'],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', '"Liberation Mono"', '"Courier New"', 'monospace'],
      },
      // Размеры шрифтов
      fontSize: {
        xs:  ['0.75rem', { lineHeight: '1.25rem' }],
        sm:  ['0.875rem', { lineHeight: '1.375rem' }],
        base:['1rem', { lineHeight: '1.5rem' }],
        lg:  ['1.125rem', { lineHeight: '1.75rem' }],
        xl:  ['1.25rem', { lineHeight: '1.75rem' }],
        '2xl': ['1.5rem', { lineHeight: '2rem' }],
        '3xl': ['1.875rem', { lineHeight: '2.25rem' }],
        '4xl': ['2.25rem', { lineHeight: '2.5rem' }],
        '5xl': ['3rem', { lineHeight: '1' }],
      },
      // Отступы
      spacing: {
        0: '0px',
        0.5: '2px',
        1: '4px',
        1.5: '6px',
        2: '8px',
        3: '12px',
        4: '16px',
        6: '24px',
        8: '32px',
        10: '40px',
        12: '48px',
        16: '64px',
        20: '80px',
        24: '96px',
        32: '128px',
        40: '160px',
        48: '192px',
        64: '256px',
      },
      // Тени
      boxShadow: {
        sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        md: '0 4px 6px -1px rgb(0 0 0 / 0.10), 0 2px 4px -2px rgb(0 0 0 / 0.10)',
        lg: '0 10px 15px -3px rgb(0 0 0 / 0.10), 0 4px 6px -4px rgb(0 0 0 / 0.10)',
        xl: '0 20px 25px -5px rgb(0 0 0 / 0.10), 0 8px 10px -6px rgb(0 0 0 / 0.10)',
      },
      // Переходы
      transitionDuration: {
        75: '75ms',
        100: '100ms',
        150: '150ms',
        200: '200ms',
        300: '300ms',
        500: '500ms',
      },
      transitionTimingFunction: {
        standard: 'cubic-bezier(0.2, 0, 0, 1)',
        accelerate: 'cubic-bezier(0.4, 0, 1, 1)',
        decelerate: 'cubic-bezier(0, 0, 0.2, 1)',
      },
      // Слои
      zIndex: {
        0: 0,
        1: 1,
        10: 10,
        50: 50,
        100: 100,
        modal: 1000,
        overlay: 1100,
      },
      // Брейкпоинты
      screens: {
        sm: '640px',
        md: '768px',
        lg: '1024px',
        xl: '1280px',
        '2xl': '1536px',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms')({ strategy: 'class' }),
    require('@tailwindcss/typography'),
    require('@tailwindcss/line-clamp'),
    require('flyonui').default,
  ],
  safelist: [
    { pattern: /(bg|text|border)-(primary|success|warning|danger|info)-(50|100|200|300|400|500|600|700|800|900)/ },
    { pattern: /(bg|text|border)-neutral-(50|100|200|300|400|500|600|700|800|900)/ },
    { pattern: /col-span-(1|2|3|4|5|6|7|8|9|10|11|12)/ },
  ],
}

