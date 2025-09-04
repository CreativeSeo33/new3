// Простой тест для проверки формы
const form = {
  name: '',
  slug: '',
  price: null,
  salePrice: null,
  status: true,
  quantity: 100,
  type: 'simple',
  description: '',
  metaTitle: '',
  metaDescription: '',
  h1: '',
  sortOrder: 1,
  optionsJson: [],
  optionAssignments: [],
};

console.log('Форма инициализирована:', form);
console.log('Поле type:', form.type);
console.log('Доступ к form.type:', form.type === 'simple' ? 'Работает' : 'Ошибка');
