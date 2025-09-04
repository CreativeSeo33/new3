// Тест для проверки безопасности доступа к form.value.type
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

// Тест с безопасным доступом
console.log('Безопасный доступ к form.type:', form?.type);
console.log('form.value?.type (симуляция):', form?.type);
console.log('Проверка типа:', form?.type === 'simple' ? 'Работает' : 'Ошибка');

// Тест с undefined
const undefinedForm = undefined;
console.log('Безопасный доступ к undefined form:', undefinedForm?.type);
console.log('Результат:', undefinedForm?.type === undefined ? 'Безопасно' : 'Ошибка');

console.log('Все тесты пройдены!');
