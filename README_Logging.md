# Логирование в приложении

## Обзор

В приложении настроено структурированное логирование с использованием Symfony Monolog Bundle. Логи разделены по каналам и уровням для удобства анализа.

## Конфигурация

Логирование настроено в `config/packages/monolog.yaml`. Основные каналы:

- **main** - основные логи приложения (`var/log/dev.log` или `var/log/prod.log`)
- **doctrine** - логи Doctrine ORM (`var/log/doctrine_dev.log`)
- **security** - логи безопасности (`var/log/security_dev.log`)
- **request** - HTTP запросы (`var/log/request_dev.log`)
- **deprecation** - предупреждения о deprecated коде (`var/log/deprecation_dev.log`)

## LoggerService

Создан сервис `App\Service\LoggerService` для удобного логирования:

```php
use App\Service\LoggerService;

class MyController
{
    public function __construct(
        private LoggerService $logger
    ) {}

    public function myAction()
    {
        // Базовые уровни логирования
        $this->logger->info('Информационное сообщение');
        $this->logger->warning('Предупреждение');
        $this->logger->error('Ошибка');
        $this->logger->debug('Отладочная информация');

        // Специализированные методы
        $this->logger->logRequest('GET', '/api/test');
        $this->logger->logUserAction('user_login', 'user123');
        $this->logger->logDatabaseQuery('SELECT * FROM users', ['id' => 123]);
        $this->logger->logError($exception, 'user_registration_failed');
    }
}
```

## Уровни логирования

- **DEBUG** - детальная отладочная информация
- **INFO** - общая информация о работе приложения
- **WARNING** - предупреждения о потенциальных проблемах
- **ERROR** - ошибки, которые не останавливают выполнение
- **CRITICAL** - критические ошибки

## Проверка логирования

### Тестовая команда
```bash
php bin/console app:test-logging
```

### Проверка логов
```bash
tail -f var/log/dev.log
tail -f var/log/deprecation_dev.log
```

### Тестовый контроллер
Откройте в браузере:
- `/api/test` - тест API с логированием
- `/test-cart` - тест страницы с логированием

## Добавление логирования в существующий код

Чтобы добавить логирование в существующий контроллер/сервис:

1. Инъектируйте LoggerService в конструктор
2. Используйте подходящие методы для логирования
3. Добавьте контекстную информацию для лучшего анализа

Пример:
```php
public function __construct(
    private LoggerService $logger
) {}

public function processOrder(Order $order)
{
    $this->logger->info('Processing order', [
        'order_id' => $order->getId(),
        'user_id' => $order->getUser()->getId(),
        'total' => $order->getTotal()
    ]);

    // ... бизнес логика ...

    $this->logger->info('Order processed successfully', [
        'order_id' => $order->getId()
    ]);
}
```

## Мониторинг

Для мониторинга логов в продакшене рекомендуется:
- Настроить ротацию логов (logrotate)
- Использовать инструменты типа ELK Stack
- Мониторить ошибки и предупреждения

## Безопасность

- Не логируйте чувствительную информацию (пароли, токены)
- Используйте соответствующие уровни логирования
- В продакшене отключайте DEBUG уровень
