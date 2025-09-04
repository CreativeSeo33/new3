// Тест для проверки логики optionAssignments
const testOptionAssignmentsLogic = () => {
  console.log('=== Тест логики optionAssignments ===\n');

  const testCases = [
    {
      description: 'Вариативный товар без опций (пустой массив)',
      form: { type: 'variable', optionAssignments: [] },
      expected: { isVariableWithoutVariations: true, canSave: false }
    },
    {
      description: 'Вариативный товар без опций (null)',
      form: { type: 'variable', optionAssignments: null },
      expected: { isVariableWithoutVariations: true, canSave: false }
    },
    {
      description: 'Вариативный товар без опций (undefined)',
      form: { type: 'variable', optionAssignments: undefined },
      expected: { isVariableWithoutVariations: true, canSave: false }
    },
    {
      description: 'Вариативный товар с опциями',
      form: { type: 'variable', optionAssignments: [{ id: 1 }] },
      expected: { isVariableWithoutVariations: false, canSave: true }
    },
    {
      description: 'Простой товар без опций',
      form: { type: 'simple', optionAssignments: [] },
      expected: { isVariableWithoutVariations: false, canSave: true }
    },
    {
      description: 'Простой товар с опциями (что не должно быть)',
      form: { type: 'simple', optionAssignments: [{ id: 1 }] },
      expected: { isVariableWithoutVariations: false, canSave: true }
    }
  ];

  testCases.forEach((testCase, index) => {
    const isVariableWithoutVariations = testCase.form.type === 'variable' &&
      (!testCase.form.optionAssignments || testCase.form.optionAssignments.length === 0);

    const canSave = !isVariableWithoutVariations;

    console.log(`Тест ${index + 1}: ${testCase.description}`);
    console.log(`  Данные:`, testCase.form);
    console.log(`  Вариативный без вариаций: ${isVariableWithoutVariations}`);
    console.log(`  Можно сохранить: ${canSave}`);

    const passed = isVariableWithoutVariations === testCase.expected.isVariableWithoutVariations &&
                   canSave === testCase.expected.canSave;

    console.log(`  Результат: ${passed ? '✅ Пройден' : '❌ Провален'}`);
    console.log('');
  });

  console.log('Все тесты завершены!');
};

// Запуск теста
testOptionAssignmentsLogic();
