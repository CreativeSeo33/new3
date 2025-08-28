#!/bin/bash

# 🚀 Настройка и запуск ImagesCacheWarmupCommand
# Этот скрипт поможет быстро настроить и запустить прогрев кеша изображений

set -e  # Остановить скрипт при первой ошибке

echo "🖼️  Настройка ImagesCacheWarmupCommand"
echo "======================================"

# Проверка наличия PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP не найден. Установите PHP 8.1+"
    exit 1
fi

# Проверка Symfony консоли
if [ ! -f "bin/console" ]; then
    echo "❌ Файл bin/console не найден. Вы в корне Symfony проекта?"
    exit 1
fi

# Проверка команды
if ! php bin/console list | grep -q "app:images:cache:warmup"; then
    echo "❌ Команда app:images:cache:warmup не найдена"
    echo "💡 Убедитесь что команда зарегистрирована в src/Command/ImagesCacheWarmupCommand.php"
    exit 1
fi

echo "✅ PHP найден: $(php --version | head -n 1)"
echo "✅ Symfony проект найден"
echo "✅ Команда ImagesCacheWarmupCommand доступна"

# Создание необходимых директорий
echo ""
echo "📁 Создание необходимых директорий..."
mkdir -p var/log
mkdir -p var/cache
mkdir -p public/media/cache

echo "✅ Директории созданы"

# Проверка прав на запись
echo ""
echo "🔐 Проверка прав доступа..."

if [ ! -w "var/log" ]; then
    echo "⚠️  Нет прав на запись в var/log"
    echo "💡 Выполните: chmod -R 755 var/"
fi

if [ ! -w "public/media/cache" ]; then
    echo "⚠️  Нет прав на запись в public/media/cache"
    echo "💡 Выполните: chmod -R 755 public/media/cache/"
fi

echo "✅ Проверка прав завершена"

# Подсчет изображений
echo ""
echo "📊 Анализ изображений..."

if [ -d "public/img" ]; then
    IMAGE_COUNT=$(find public/img -type f \( -iname "*.jpg" -o -iname "*.jpeg" -o -iname "*.png" -o -iname "*.gif" -o -iname "*.webp" \) 2>/dev/null | wc -l)
    echo "📸 Найдено изображений: $IMAGE_COUNT"

    if [ "$IMAGE_COUNT" -eq 0 ]; then
        echo "⚠️  Изображения не найдены в public/img/"
        echo "💡 Проверьте содержимое папки: ls -la public/img/"
    fi
else
    echo "⚠️  Папка public/img/ не существует"
    echo "💡 Создайте папку или укажите другой путь через --path"
fi

# Рекомендации по настройкам
echo ""
echo "🎯 Рекомендации по настройкам:"

if [ "$IMAGE_COUNT" -lt 100 ]; then
    echo "💡 Для небольшого количества изображений:"
    echo "   php bin/console app:images:cache:warmup --batch-size=10 --parallel=2"
elif [ "$IMAGE_COUNT" -lt 1000 ]; then
    echo "💡 Для среднего количества изображений:"
    echo "   php bin/console app:images:cache:warmup --batch-size=50 --parallel=4"
else
    echo "💡 Для большого количества изображений:"
    echo "   php bin/console app:images:cache:warmup --batch-size=200 --parallel=8"
fi

# Предлагаем запустить dry-run
echo ""
echo "🔍 Рекомендуется сначала запустить dry-run:"
echo "php bin/console app:images:cache:warmup --dry-run"

# Спрашиваем о запуске
echo ""
read -p "🚀 Запустить dry-run сейчас? (y/n): " -n 1 -r
echo

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🔍 Запуск dry-run..."
    php bin/console app:images:cache:warmup --dry-run
fi

echo ""
echo "📚 Полезные команды:"
echo "• php bin/console app:images:cache:warmup --help          # Справка"
echo "• php bin/console app:images:cache:warmup --dry-run      # Тестирование"
echo "• php bin/console app:images:cache:warmup --continue     # Продолжить"
echo "• tail -f var/log/image_cache_warmup.log               # Мониторинг логов"

echo ""
echo "✅ Настройка завершена! Можете запускать команду."
echo ""
echo "📖 Читайте документацию:"
echo "• README_ImageCacheWarmup.md      # Полная документация"
echo "• CHEATSHEET_ImageCacheWarmup.md  # Шпаргалка"

echo ""
echo "🎉 Приятной работы с ImagesCacheWarmupCommand!"
