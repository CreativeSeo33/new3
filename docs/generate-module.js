#!/usr/bin/env node

/**
 * Скрипт для генерации нового модуля на основе шаблонов
 * Использование: node docs/generate-module.js <type> <name>
 *
 * Примеры:
 * node docs/generate-module.js feature my-new-feature
 * node docs/generate-module.js widget my-new-widget
 */

const fs = require('fs');
const path = require('path');

const [,, moduleType, moduleName] = process.argv;

if (!moduleType || !moduleName) {
  console.log('Использование: node docs/generate-module.js <type> <name>');
  console.log('Примеры:');
  console.log('  node docs/generate-module.js feature my-new-feature');
  console.log('  node docs/generate-module.js widget my-new-widget');
  process.exit(1);
}

const templates = {
  feature: [
    'feature-api-template.ts',
    'feature-ui-template.ts',
    'feature-index-template.ts'
  ],
  widget: [
    'widget-template.ts'
  ]
};

if (!templates[moduleType]) {
  console.log(`Неизвестный тип модуля: ${moduleType}`);
  console.log('Доступные типы:', Object.keys(templates).join(', '));
  process.exit(1);
}

// Создание структуры папок
const basePath = path.join(__dirname, '..', 'assets', 'catalog', 'src');

let targetPath;
if (moduleType === 'feature') {
  targetPath = path.join(basePath, 'features', moduleName);
  fs.mkdirSync(path.join(targetPath, 'api'), { recursive: true });
  fs.mkdirSync(path.join(targetPath, 'ui'), { recursive: true });
} else if (moduleType === 'widget') {
  targetPath = path.join(basePath, 'widgets', moduleName);
  fs.mkdirSync(targetPath, { recursive: true });
}

// Копирование и обработка шаблонов
templates[moduleType].forEach(templateFile => {
  const templatePath = path.join(__dirname, 'templates', templateFile);
  const templateContent = fs.readFileSync(templatePath, 'utf8');

  // Замена плейсхолдеров
  const processedContent = templateContent
    .replace(/FeatureName/g, capitalizeFirst(moduleName))
    .replace(/feature-name/g, moduleName.toLowerCase().replace(/_/g, '-'))
    .replace(/feature_name/g, moduleName.toLowerCase().replace(/-/g, '_'))
    .replace(/WidgetName/g, capitalizeFirst(moduleName))
    .replace(/widget-name/g, moduleName.toLowerCase().replace(/_/g, '-'))
    .replace(/widget_name/g, moduleName.toLowerCase().replace(/-/g, '_'));

  // Определение целевого файла
  let targetFile;
  if (templateFile === 'feature-api-template.ts') {
    targetFile = path.join(targetPath, 'api', 'index.ts');
  } else if (templateFile === 'feature-ui-template.ts') {
    targetFile = path.join(targetPath, 'ui', 'component.ts');
  } else if (templateFile === 'feature-index-template.ts') {
    targetFile = path.join(targetPath, 'index.ts');
  } else if (templateFile === 'widget-template.ts') {
    targetFile = path.join(targetPath, 'index.ts');
  }

  // Запись файла
  fs.writeFileSync(targetFile, processedContent);
  console.log(`Создан файл: ${targetFile}`);
});

// Создание типа в shared/types
if (moduleType === 'feature') {
  const typesTemplate = path.join(__dirname, 'templates', 'api-types-template.ts');
  const typesContent = fs.readFileSync(typesTemplate, 'utf8');

  const processedTypes = typesContent
    .replace(/FeatureName/g, capitalizeFirst(moduleName))
    .replace(/feature-name/g, moduleName.toLowerCase().replace(/_/g, '-'))
    .replace(/feature_name/g, moduleName.toLowerCase().replace(/-/g, '_'));

  const typesFile = path.join(basePath, 'shared', 'types', `${moduleName}.ts`);
  fs.writeFileSync(typesFile, processedTypes);
  console.log(`Создан файл типов: ${typesFile}`);
}

console.log('\n✅ Модуль успешно создан!');
console.log(`\n📝 Следующие шаги:`);
console.log(`1. Отредактируйте созданные файлы под ваши нужды`);
console.log(`2. Зарегистрируйте модуль в app/registry.ts`);
console.log(`3. Добавьте data-module атрибут в HTML`);
console.log(`4. Протестируйте работу модуля`);

function capitalizeFirst(str) {
  return str.charAt(0).toUpperCase() + str.slice(1).replace(/[-_](.)/g, (_, char) => char.toUpperCase());
}
