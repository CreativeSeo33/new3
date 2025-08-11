import type { Meta, StoryObj } from '@storybook/vue3'
import Input from './Input.vue'

const meta: Meta<typeof Input> = {
  title: 'UI/Input',
  component: Input,
  render: (args) => ({
    components: { Input },
    setup: () => ({ args }),
    template: '<Input v-bind="args" />',
  }),
}

export default meta
type Story = StoryObj<typeof Input>

export const Text: Story = { args: { label: 'Email', placeholder: 'you@example.com' } }


