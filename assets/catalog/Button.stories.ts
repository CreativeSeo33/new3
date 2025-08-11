import type { Meta, StoryObj } from '@storybook/html';

const meta: Meta = {
  title: 'Catalog/Button',
};

export default meta;

type Story = StoryObj;

export const Primary: Story = {
  render: () => {
    const button = document.createElement('button');
    button.className = 'px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400';
    button.textContent = 'Primary Button';
    return button;
  },
};


