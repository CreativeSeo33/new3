import type { Meta, StoryObj } from '@storybook/vue3'
import Button from './Button.vue'

const meta: Meta<typeof Button> = {
  title: 'UI/Button',
  component: Button,
  args: { children: 'Button' },
  render: (args) => ({
    components: { Button },
    setup: () => ({ args }),
    template: '<Button v-bind="args">{{ args.children }}</Button>',
  }),
}

export default meta
type Story = StoryObj<typeof Button>

export const Primary: Story = { args: { variant: 'default', children: 'Primary' } }
export const Secondary: Story = { args: { variant: 'secondary', children: 'Secondary' } }
export const Outline: Story = { args: { variant: 'outline', children: 'Outline' } }
export const Destructive: Story = { args: { variant: 'destructive', children: 'Destructive' } }
export const Ghost: Story = { args: { variant: 'ghost', children: 'Ghost' } }


