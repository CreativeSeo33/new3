// Тест для проверки логики валидации вариативных товаров
const testVariableValidation = () => {
  console.log('=== Тест логики вариативных товаров ===\n');

  // Симуляция состояний формы
  const testCases = [
    {
      type: 'simple',
      optionAssignments: [],
      expected: {
        isVariableWithoutVariations: false,
        canSave: true,
        warning: false
      }
    },
    {
      type: 'variable',
      optionAssignments: [],
      expected: {
        isVariableWithoutVariations: true,
        canSave: false,
        warning: true
      }
    },
    {
      type: 'variable',
      optionAssignments: [{ id: 1, price: 100 }],
      expected: {
        isVariableWithoutVariations: false,
        canSave: true,
        warning: false
      }
    }
  ];

  testCases.forEach((testCase, index) => {
    const isVariableWithoutVariations = testCase.type === 'variable' &&
      (!testCase.optionAssignments || testCase.optionAssignments.length === 0);

    const canSave = !isVariableWithoutVariations;

    const warning = isVariableWithoutVariations;

    console.log(`Тест ${index + 1}:`);
    console.log(`  Тип: ${testCase.type}`);
    console.log(`  Вариации: ${testCase.optionAssignments.length}`);
    console.log(`  Вариативный без вариаций: ${isVariableWithoutVariations}`);
    console.log(`  Можно сохранить: ${canSave}`);
    console.log(`  Показывать предупреждение: ${warning}`);

    const passed = isVariableWithoutVariations === testCase.expected.isVariableWithoutVariations &&
                   canSave === testCase.expected.canSave &&
                   warning === testCase.expected.warning;

    console.log(`  Результат: ${passed ? '✅ Пройден' : '❌ Провален'}`);
    console.log('');
  });

  console.log('Все тесты завершены!');
};

// Запуск теста
testVariableValidation();
