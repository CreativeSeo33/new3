# 📋 Шпаргалка: ImagesCacheWarmupCommand

## 🎉 Последние обновления

### ✅ Изменения после обновления:
- **Размер xl фильтра:** 1200×1200 → **1600×1600** пикселей
- **Автоматический прогрев кеша** через `ProductImageCacheListener`
- **Git игнорирует** файлы кеша `public/media/cache/`
- **Исправлена логика путей** изображений в шаблонах

### 🔄 Автоматический прогрев кеша:
```bash
# Кеш создается автоматически при добавлении новых изображений
# ProductImageCacheListener срабатывает на postPersist событии
# Обрабатывает все фильтры: sm, md, md2, xl
```

## 🚀 Быстрый старт

### Основные команды:
```bash
# Простой запуск со стандартными настройками
php bin/console app:images:cache:warmup

# Проверить что будет обработано (без выполнения)
php bin/console app:images:cache:warmup --dry-run

# Детальная справка
php bin/console app:images:cache:warmup --help
```

---

## ⚙️ Настройки по умолчанию

| Параметр | Значение | Описание |
|----------|----------|----------|
| **batch-size** | 50 | Изображений в одном батче |
| **parallel** | 4 | Параллельных процессов |
| **filters** | sm, md, md2, xl | Все фильтры |
| **path** | public/img | Папка с изображениями |

### 📏 Размеры фильтров изображений:
| Фильтр | Размер | Назначение |
|--------|--------|------------|
| **xl** | **1600×1600** | Основное изображение товара (обновлено!) |
| **md2** | 700×700 | Средний размер |
| **md** | 330×330 | Маленький средний размер |
| **sm** | 150×150 | Миниатюры и превью |

---

## 🎯 Популярные сценарии

### 🚀 После добавления новых изображений:
```bash
# Автоматический прогрев (работает сам при добавлении фото)
# ProductImageCacheListener создает кеш автоматически

# Ручной прогрев для новых изображений
php bin/console app:images:cache:warmup --path=public/img/sofia --filter=xl

# Прогрев всех фильтров для новых изображений
php bin/console app:images:cache:warmup --path=public/img/sofia --filter=sm,md,md2,xl
```

### Для разработки:
```bash
# Быстрая проверка с малым объемом
php bin/console app:images:cache:warmup --batch-size=5 --parallel=2 --dry-run

# Обработка тестовых изображений
php bin/console app:images:cache:warmup --batch-size=10 --parallel=2
```

### Для продакшена:
```bash
# Оптимизированная обработка большого объема
php bin/console app:images:cache:warmup --batch-size=200 --parallel=8

# Только критичные фильтры
php bin/console app:images:cache:warmup --filter=md --filter=xl --batch-size=100
```

### Для отладки:
```bash
# Детальный вывод всех операций
php bin/console app:images:cache:warmup --detailed --batch-size=5

# Продолжить прерванную обработку
php bin/console app:images:cache:warmup --continue
```

---

## 📊 Мониторинг

### Логи выполнения:
```bash
# Следить за логами в реальном времени
tail -f var/log/image_cache_warmup.log

# Посмотреть последние записи
tail -20 var/log/image_cache_warmup.log
```

### Прогресс выполнения:
```bash
# Проверить файл прогресса
cat var/cache/image_cache_progress.json

# Или в читаемом виде
cat var/cache/image_cache_progress.json | jq .
```

### Статистика ресурсов:
```bash
# Мониторинг во время выполнения
top -p $(pgrep -f "app:images:cache:warmup")

# Использование памяти
ps aux --sort=-%mem | head -10
```

---

## 🔧 Устранение проблем

### Быстрые решения:

| Проблема | Команда решения |
|----------|------------------|
| **Мало памяти** | `--batch-size=25 --parallel=2` |
| **Медленная обработка** | `--batch-size=100 --parallel=8` |
| **Много ошибок** | `--detailed` для диагностики |
| **Обработка прервалась** | `--continue` |
| **Не те изображения** | `--path=public/uploads` |
| **Кеш не создается** | Проверить `ProductImageCacheListener` |
| **Неправильные пути** | Проверить логику в шаблонах |

### 🗂️ Работа с Git и кешем:

```bash
# Проверить статус кеш-файлов (должны игнорироваться)
git status public/media/cache/

# Если кеш-файлы попали в Git (очистить)
git rm -r --cached public/media/
git commit -m "Remove cache files from Git tracking"

# Проверить .gitignore
cat .gitignore | grep media

# Восстановить кеш после git clone
php bin/console app:images:cache:warmup --batch-size=100 --parallel=8
```

### Очистка и сброс:
```bash
# Очистить весь кеш изображений
php bin/console liip:imagine:cache:remove

# Удалить логи прогресса
rm var/cache/image_cache_progress.json
rm var/log/image_cache_warmup.log

# Проверить состояние кеша
find public/media/cache -type f | wc -l
```

---

## ⏰ Планировщик (Cron)

### Ночные запуски:
```bash
# Каждый день в 2:00
0 2 * * * cd /path/to/project && php bin/console app:images:cache:warmup --batch-size=200 --parallel=8

# По воскресеньям в 3:00 (полная перегенерация)
0 3 * * 0 cd /path/to/project && php bin/console app:images:cache:warmup --batch-size=500 --parallel=12
```

### После загрузки новых изображений:
```bash
# Обработка папки с новыми изображениями
0 */4 * * * cd /path/to/project && php bin/console app:images:cache:warmup --path=public/uploads/new --batch-size=50
```

---

## 📈 Производительность

### Рекомендации по серверам:

| RAM | CPU | Рекомендуемые настройки |
|-----|-----|-------------------------|
| 2GB | 2 cores | `--batch-size=25 --parallel=2` |
| 4GB | 4 cores | `--batch-size=50 --parallel=4` |
| 8GB | 8 cores | `--batch-size=100 --parallel=8` |
| 16GB+ | 16+ cores | `--batch-size=200 --parallel=12` |

### Ожидаемая скорость:
- **100 изображений:** ~30 секунд
- **1000 изображений:** ~5-10 минут
- **10000 изображений:** ~1-2 часа

---

## 🎮 Интерактивные команды

### Для тестирования и разработки:

```bash
# Интерактивный режим с вопросами
read -p "Сколько изображений в батче? " batch_size
read -p "Сколько параллельных процессов? " parallel

php bin/console app:images:cache:warmup --batch-size=$batch_size --parallel=$parallel
```

### Проверка перед запуском:
```bash
# Посчитать изображения
find public/img -type f \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" \) | wc -l

# Оценить размер
du -sh public/img

# Проверить права
ls -la public/media/cache/
```

---

## 🚨 Важные напоминания

### ✅ Делать перед запуском:
- [ ] Проверить доступное место на диске
- [ ] Сделать бэкап важных данных
- [ ] Проверить права на папки cache
- [ ] Запустить в dry-run режиме
- [ ] **Убедиться что .gitignore настроен** (public/media/cache/)

### ✅ Новые возможности:
- [ ] **Автоматический прогрев кеша** работает через ProductImageCacheListener
- [ ] **Размер xl фильтра обновлен** до 1600×1600 пикселей
- [ ] **Кеш-файлы игнорируются Git** - репозиторий чище
- [ ] **Исправлена логика путей** изображений в шаблонах

### ✅ Мониторить во время работы:
- [ ] Использование CPU (`top`)
- [ ] Использование памяти (`free -h`)
- [ ] Количество открытых файлов (`lsof | wc -l`)
- [ ] Логи выполнения

### ✅ После завершения:
- [ ] Проверить логи на ошибки
- [ ] Подсчитать созданные файлы
- [ ] Проверить производительность сайта
- [ ] **Убедиться что новые изображения отображаются**

---

## 🆘 Экстренная помощь

### Если команда зависла:
```bash
# Найти и убить процесс
ps aux | grep "app:images:cache:warmup"
kill -9 <PID>

# Продолжить с места остановки
php bin/console app:images:cache:warmup --continue
```

### Если памяти не хватает:
```bash
# Увеличить лимит PHP
php -d memory_limit=1G bin/console app:images:cache:warmup --batch-size=10

# Или уменьшить нагрузку
php bin/console app:images:cache:warmup --batch-size=5 --parallel=1
```

### Если диск заполнен:
```bash
# Проверить место
df -h

# Очистить старые логи
find var/log -name "*.log" -mtime +30 -delete

# Очистить старый кеш
find public/media/cache -mtime +7 -delete
```

---

## 📞 Поддержка

### 🎯 Для новых изображений (рекомендуется):
```bash
# 1. Проверить что автоматический кеш работает
tail -f var/log/dev.log | grep -i "ProductImageCacheListener"

# 2. Если нужно вручную прогреть
php bin/console app:images:cache:warmup --path=public/img/новая_папка --filter=xl

# 3. Проверить результат
ls -la public/media/cache/xl/img/новая_папка/
```

### Для быстрой помощи:
1. **Сначала:** `php bin/console app:images:cache:warmup --dry-run`
2. **Если проблема:** `tail -50 var/log/image_cache_warmup.log`
3. **Для детальной диагностики:** `php bin/console app:images:cache:warmup --detailed --batch-size=1`
4. **Проверить ProductImageCacheListener:** `tail -f var/log/dev.log | grep -i cache`

### Полезные команды для отладки:
```bash
# Проверить PHP конфигурацию
php -i | grep -E "(memory_limit|max_execution_time)"

# Проверить доступ к imagemagick/gd
php -m | grep -E "(gd|imagick)"

# Проверить права на файлы
ls -la public/img/
ls -la public/media/cache/

# Проверить логи автоматического кеша
grep -i "cache" var/log/dev.log | tail -10

# Проверить размер нового xl фильтра
php bin/console liip:imagine:cache:resolve img/sofia/sofiya-3-baden-1.jpg --filter=xl --force && ls -lh public/media/cache/xl/img/sofia/
```

**🎉 После обновления:**
- Размер изображений xl теперь **1600×1600** пикселей
- **Автоматический прогрев кеша** при добавлении новых фото
- **Git игнорирует кеш-файлы** - чище репозиторий
- **Исправлены пути** к изображениям в шаблонах

**Помните:** Лучший способ научиться - начать с маленького тестового запуска! 🎯
