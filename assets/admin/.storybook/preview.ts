import type { Preview } from '@storybook/vue3'
import '../styles.css'

const preview: Preview = {
  parameters: {
    backgrounds: {
      default: 'light',
      values: [
        { name: 'light', value: '#ffffff' },
        { name: 'dark', value: '#0b1220' },
      ],
    },
    layout: 'centered',
    controls: { expanded: true },
  },
}

export default preview


