import { ref, onMounted } from 'vue'

const THEME_KEY = 'theme'
const themeRef = ref<'light' | 'dark'>('light')

export function useTheme() {
  const setTheme = (t: 'light' | 'dark') => {
    themeRef.value = t
    const root = document.documentElement
    if (t === 'dark') root.classList.add('dark')
    else root.classList.remove('dark')
    localStorage.setItem(THEME_KEY, t)
  }

  const toggleTheme = () => setTheme(themeRef.value === 'dark' ? 'light' : 'dark')

  onMounted(() => {
    const saved = localStorage.getItem(THEME_KEY) as 'light' | 'dark' | null
    if (saved) setTheme(saved)
    else setTheme(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
  })

  return { theme: themeRef, setTheme, toggleTheme }
}


