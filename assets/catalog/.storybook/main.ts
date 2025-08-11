import type { StorybookConfig } from '@storybook/html-vite';

const config: StorybookConfig = {
  framework: {
    name: '@storybook/html-vite',
    options: {},
  },
  stories: [
    '../**/*.stories.@(js|ts|mdx)'
  ],
  addons: [
    '@storybook/addon-essentials',
    '@storybook/addon-a11y',
  ],
  core: {
    disableTelemetry: true,
  },
  docs: {
    autodocs: false,
  },
};

export default config;


