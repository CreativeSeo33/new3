import path from 'path'
import vue from '@vitejs/plugin-vue'
import type { StorybookConfig } from '@storybook/vue3-vite'

const config: StorybookConfig = {
  stories: [
    '../ui/components/**/*.stories.@(ts|tsx)',
    '../views/**/*.stories.@(ts|tsx)'
  ],
  addons: [
    '@storybook/addon-essentials',
    '@storybook/addon-a11y'
  ],
  framework: {
    name: '@storybook/vue3-vite',
    options: {}
  },
  viteFinal: async (config) => {
    config.resolve = config.resolve || {}
    config.resolve.alias = Object.assign({}, config.resolve.alias || {}, {
      '@admin': path.resolve(__dirname, '..'),
    })
    // Ensure Vue SFCs are handled in Storybook's Vite build
    config.plugins = [...(config.plugins || []), vue()]
    return config
  },
}

export default config


