import type { Meta, StoryObj } from '@storybook/vue3'
import Card from './Card.vue'
import Button from './Button.vue'

const meta: Meta<typeof Card> = {
  title: 'UI/Card',
  component: Card,
  render: (args) => ({
    components: { Card, Button },
    setup: () => ({ args }),
    template: `
      <Card>
        <template #header>
          <div class="flex items-center justify-between w-full">
            <div class="font-medium">Заголовок</div>
            <Button size="sm" variant="outline">Действие</Button>
          </div>
        </template>
        <div class="text-sm text-muted-foreground">
          Контент карточки
        </div>
        <template #footer>
          <div class="ml-auto">
            <Button size="sm">Ок</Button>
          </div>
        </template>
      </Card>
    `,
  }),
}

export default meta
type Story = StoryObj<typeof Card>

export const Default: Story = {}


