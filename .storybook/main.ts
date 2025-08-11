import type { StorybookConfig } from '@storybook/vue3-vite'
import path from 'node:path'

const config: StorybookConfig = {
  stories: ['../assets/admin/**/*.stories.@(ts|tsx)'],
  addons: [
    '@storybook/addon-essentials',
    '@storybook/addon-a11y',
  ],
  framework: {
    name: '@storybook/vue3-vite',
    options: {},
  },
  viteFinal: async (config) => {
    config.resolve = config.resolve || {}
    config.resolve.alias = {
      ...(config.resolve?.alias as Record<string, string>),
      '@admin': path.resolve(__dirname, '../assets/admin'),
    }
    return config
  },
}

export default config


