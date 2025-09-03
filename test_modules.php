<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест модулей товара</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .option-group { margin: 20px 0; }
        .option-item { margin: 10px 0; }
        .price-display { background: #f0f0f0; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .logs { background: #f8f8f8; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Тест модулей товара</h1>

    <!-- Модуль цены -->
    <div class="price-display" data-module="product-price-calculator">
        <h2>Цена товара</h2>
        <p>Базовая цена: <span id="product-price" data-base-price="5000">₽5,000</span></p>
        <p>Старая цена: <span id="old-price" data-old-price="6000">₽6,000</span></p>
        <p>Скидка: <span id="discount-badge">-17%</span></p>
    </div>

    <!-- Модуль опций -->
    <form data-module="product-options">
        <div class="option-group">
            <h3>Диаметр</h3>
            <div class="option-item">
                <input type="radio" id="option-1" name="option-1" value="1"
                       data-option-name="Диаметр" data-option-price="1000" data-option-value="30 см" checked>
                <label for="option-1">30 см (+₽1,000)</label>
            </div>
            <div class="option-item">
                <input type="radio" id="option-2" name="option-1" value="2"
                       data-option-name="Диаметр" data-option-price="2000" data-option-value="40 см">
                <label for="option-2">40 см (+₽2,000)</label>
            </div>
        </div>

        <div class="option-group">
            <h3>Цвет</h3>
            <div class="option-item">
                <input type="radio" id="option-3" name="option-2" value="3"
                       data-option-name="Цвет" data-option-price="500" data-option-value="Белый" checked>
                <label for="option-3">Белый (+₽500)</label>
            </div>
            <div class="option-item">
                <input type="radio" id="option-4" name="option-2" value="4"
                       data-option-name="Цвет" data-option-price="800" data-option-value="Черный">
                <label for="option-4">Черный (+₽800)</label>
            </div>
        </div>
    </form>

    <!-- Модуль корзины -->
    <div style="margin: 20px 0;">
        <button type="button" data-product-id="1" data-module="add-to-cart">
            Добавить в корзину
        </button>
    </div>

    <!-- Логи -->
    <div class="logs" id="logs"></div>

    <!-- Загрузка скриптов -->
    <script src="/build/runtime.00b25e1c.js"></script>
    <script src="/build/850.fe56ffd7.js"></script>
    <script src="/build/catalog.1c6eee94.js"></script>

    <script>
        // Логирование для отладки
        const logsEl = document.getElementById('logs');

        function log(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.style.color = type === 'error' ? 'red' : type === 'success' ? 'green' : 'black';
            logEntry.textContent = `[${timestamp}] ${message}`;
            logsEl.appendChild(logEntry);
            logsEl.scrollTop = logsEl.scrollHeight;
        }

        // Слушаем события модулей
        window.addEventListener('product:options-changed', (e) => {
            const { selectedOptions, totalPriceModifier } = e.detail;
            log(`Опции изменены. Выбрано: ${selectedOptions.length}, модификатор цены: +₽${totalPriceModifier}`, 'success');

            selectedOptions.forEach(option => {
                log(`  - ${option.name}: ${option.value} (+₽${option.price})`);
            });
        });

        window.addEventListener('cart:updated', (e) => {
            log(`Корзина обновлена: ${JSON.stringify(e.detail)}`, 'success');
        });

        log('Тестовая страница загружена');
    </script>
</body>
</html>
