### Как использовать PHPStan в разработке (практика)

- Частые локальные проверки (быстро, только `src`):
```powershell
composer stan:quick
```

- Полная проверка с актуализацией контейнера (без ложных предупреждений):
```powershell
powershell -ExecutionPolicy Bypass -File tools/analyse.ps1
```

- Генерация/обновление baseline (для фиксации текущих ошибок):
```powershell
php bin/console --env=dev --no-ansi debug:container --format=xml > var/cache/dev/phpstan-container.xml
composer stan:baseline
```

- Проверка только изменённых (или индексированных) PHP-файлов перед коммитом:
```powershell
# Изменённые в индексе (staged):
$files = git diff --name-only --cached --diff-filter=ACMRT | Select-String '\.php$' | % { $_.Line }
if ($files.Count -gt 0) { php -d memory_limit=1G vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress $files }
```

- Рекомендованный workflow по задачам:
  - Во время правок — `composer stan:quick` каждые 5–10 минут.
  - Перед коммитом — прогон изменённых файлов (сниппет выше).
  - Перед пушем — `tools/analyse.ps1` (полный прогон).
  - Если задел старый код и появились новые ошибки — исправляй; baseline обновляй, только если правки невозможны быстро.

- Когда перегенерировать контейнер XML:
  - Менялись сервисы/конфиги/автowired-аргументы — запусти `tools/analyse.ps1` или:
```powershell
php bin/console --env=dev --no-ansi debug:container --format=xml > var/cache/dev/phpstan-container.xml
```

- Как держать код «зелёным» на уровне 9:
  - Проставляй точные типы, особенно:
    - Массивы: `array<string,int>`, `array<int,SomeDto>`.
    - Коллекции Doctrine:
```php
/** @var Collection<int, ProductImage> */
private Collection $images;
```
  - Уточняй nullable: `?string` vs `string` и инициализацию свойств.
  - Явные возвращаемые типы в методах, особенно в репозиториях/сервисах.
  - Избегай динамических свойств; используй объявленные типизированные свойства.
  - Для спорных мест — точечные подавления с причиной:
```php
/** @phpstan-ignore-next-line – несовместимый тип провайдера из стороннего SDK */
```
    Конфиг включает `reportUnmatchedIgnoredErrors: true`, поэтому «пустые» игноры не пройдут.

- IDE (PhpStorm):
  - Settings → PHP → Quality Tools → PHPStan:
    - Binary: `vendor/bin/phpstan`
    - Configuration: `phpstan.neon.dist`
    - Working dir: корень проекта
  - Включи инспекции на Save/Change для моментальной обратной связи.

- Стратегия снижения baseline:
  - Выделяй модуль/катalog классов → коммит «фикс типов» → перегенерируй baseline (он уменьшится).
  - Не добавляй новые ошибки: правило приёмки — новые патчи «0 новых ошибок» (baseline можно обновлять при необходимости).

- Опционально позже (когда станет зелёно):
  - Установить строгие правила и раскомментировать их в `phpstan.neon.dist`:
```powershell
composer require --dev phpstan/phpstan-strict-rules symplify/phpstan-rules
```

Кратко:
- Быстро: `composer stan:quick`.
- Полный: `tools/analyse.ps1`.
- Обновить baseline: `composer stan:baseline` (после генерации контейнера).
- Новые ошибки не допускай; baseline уменьшай постепенно.