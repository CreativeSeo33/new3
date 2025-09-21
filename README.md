# 🖼️ ImagesCacheWarmupCommand

Symfony команда для **прогрева кеша изображений** с оптимизацией для больших объемов (10k+ изображений).

## 📚 Документация

### 🚀 Быстрый старт
```bash
# Основная команда
php bin/console app:images:cache:warmup

# Тестирование (без выполнения)
php bin/console app:images:cache:warmup --dry-run

# Справка по опциям
php bin/console app:images:cache:warmup --help
```

### 📖 Полная документация

| Документ | Описание |
|----------|----------|
| [**README_ImageCacheWarmup.md**](README_ImageCacheWarmup.md) | Полная документация с примерами, архитектурой и troubleshooting |
| [**CHEATSHEET_ImageCacheWarmup.md**](CHEATSHEET_ImageCacheWarmup.md) | Шпаргалка с быстрыми командами и решениями проблем |
| [**setup_cache_warmup.sh**](setup_cache_warmup.sh) | Скрипт автоматической настройки и диагностики |

## ⚡ Возможности

### 🚀 Производительность
- ✅ **Параллельная обработка** до 16+ процессов
- ✅ **Батчинг** с настраиваемым размером
- ✅ **Оптимизация памяти** с автоматической очисткой
- ✅ **Graceful shutdown** (Ctrl+C безопасно)

### 📊 Мониторинг
- ✅ **Детальный прогресс-бар** с временем и памятью
- ✅ **Логирование** всех операций
- ✅ **Сохранение прогресса** для возобновления
- ✅ **Статистика выполнения**

### 🛠️ Гибкость
- ✅ **Настраиваемые фильтры** (sm, md, md2, xl)
- ✅ **Любые пути** к изображениям
- ✅ **Dry-run режим** для тестирования
- ✅ **Возобновление** прерванной обработки

## 🎯 Основные сценарии

### Для разработки:
```bash
php bin/console app:images:cache:warmup --batch-size=10 --parallel=2 --dry-run
```

### Для продакшена:
```bash
php bin/console app:images:cache:warmup --batch-size=200 --parallel=8
```

### Для больших объемов:
```bash
php bin/console app:images:cache:warmup --batch-size=500 --parallel=12 --filter=md --filter=xl
```

## 📈 Производительность

| Количество изображений | Время обработки | Рекомендуемые настройки |
|------------------------|-----------------|-------------------------|
| 100 изображений | ~30 секунд | `--batch-size=50 --parallel=4` |
| 1,000 изображений | ~5-10 минут | `--batch-size=100 --parallel=8` |
| 10,000 изображений | ~1-2 часа | `--batch-size=200 --parallel=8` |

## 🔧 Установка и настройка

### 1. Автоматическая настройка
```bash
# Запустите скрипт настройки (Linux/Mac)
./setup_cache_warmup.sh

# Или вручную на Windows
php bin/console app:images:cache:warmup --dry-run
```

### 2. Проверка работоспособности
```bash
# Должен показать доступные опции
php bin/console app:images:cache:warmup --help

# Должен показать найденные изображения
php bin/console app:images:cache:warmup --dry-run
```

## 🆘 Проблемы и решения

### Быстрые решения распространенных проблем:

| Проблема | Решение |
|----------|---------|
| **Команда не найдена** | Проверьте `src/Command/ImagesCacheWarmupCommand.php` |
| **Мало памяти** | `--batch-size=25 --parallel=2` |
| **Медленная обработка** | `--batch-size=100 --parallel=8` |
| **Обработка прервалась** | `--continue` |
| **Не те изображения** | `--path=public/uploads` |

### Мониторинг и отладка:
```bash
# Логи в реальном времени
tail -f var/log/image_cache_warmup.log

# Проверка прогресса
cat var/cache/image_cache_progress.json

# Мониторинг ресурсов
top -p $(pgrep -f "app:images:cache:warmup")
```

## 📋 Полезные команды

```bash
# Очистка кеша
php bin/console liip:imagine:cache:remove

# Проверка статуса
php bin/console liip:imagine:cache:resolve --dry-run

# Только определенные фильтры
php bin/console app:images:cache:warmup --filter=md --filter=xl

# Детальный вывод
php bin/console app:images:cache:warmup --detailed --batch-size=5
```

## ⏰ Автоматизация (Cron)

```bash
# Ночной прогрев (каждый день в 2:00)
0 2 * * * cd /path/to/project && php bin/console app:images:cache:warmup --batch-size=200 --parallel=8

# После загрузки новых изображений (каждые 4 часа)
0 */4 * * * cd /path/to/project && php bin/console app:images:cache:warmup --path=public/uploads/new --batch-size=50
```

## 🎓 Для начинающих разработчиков

1. **Начните с dry-run:** `php bin/console app:images:cache:warmup --dry-run`
2. **Тестируйте на малом объеме:** `--batch-size=10 --parallel=2`
3. **Следите за логами:** `tail -f var/log/image_cache_warmup.log`
4. **Используйте `--continue`** если обработка прервалась
5. **Читаите CHEATSHEET** для быстрых решений

## 📞 Поддержка

- 📖 [**Полная документация**](README_ImageCacheWarmup.md)
- 📋 [**Шпаргалка**](CHEATSHEET_ImageCacheWarmup.md)
- 🔧 [**Скрипт настройки**](setup_cache_warmup.sh)

---

## 🎉 Быстрый тест

Хотите проверить работу команды прямо сейчас?

```bash
# 1. Посмотреть что будет обработано
php bin/console app:images:cache:warmup --dry-run

# 2. Запустить с минимальными настройками
php bin/console app:images:cache:warmup --batch-size=3 --parallel=2

# 3. Проверить результат
find public/media/cache -name "*.jpg" | wc -l
```

---

*Создано для оптимизации работы с изображениями в Symfony проектах*
