import type { Meta, StoryObj } from '@storybook/vue3'
import { ref } from 'vue'
import Modal from './Modal.vue'
import Button from './Button.vue'

const meta: Meta<typeof Modal> = {
  title: 'UI/Modal',
  component: Modal,
  args: {
    modelValue: false,
    title: 'Modal Title',
    subtitle: 'Optional subtitle',
    size: 'md',
    align: 'center',
    closable: true,
  },
  render: (args) => ({
    components: { Modal, Button },
    setup: () => {
      const open = ref(false)
      return { args, open }
    },
    template: `
      <div class="p-6">
        <Button @click="open = true">Open modal</Button>
        <Modal v-model="open" v-bind="args">
          <template #header>
            <h3 class="text-lg font-semibold">{{ args.title }}</h3>
            <p v-if="args.subtitle" class="mt-1 text-sm text-neutral-500">{{ args.subtitle }}</p>
          </template>
          <div class="space-y-4 text-sm text-neutral-700 dark:text-neutral-300">
            <p>
              This is a TailAdmin-like modal content. Use it to show forms, confirmations, etc.
            </p>
            <p>
              It supports header, default slot, and footer.
            </p>
          </div>
          <template #footer>
            <div class="flex justify-end gap-3">
              <Button variant="outline" @click="open = false">Cancel</Button>
              <Button variant="secondary" @click="open = false">Confirm</Button>
            </div>
          </template>
        </Modal>
      </div>
    `,
  }),
}

export default meta
type Story = StoryObj<typeof Modal>

export const Default: Story = {}
export const TopAligned: Story = { args: { align: 'top' } }
export const Large: Story = { args: { size: 'lg' } }


