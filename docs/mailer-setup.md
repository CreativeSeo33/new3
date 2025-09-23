# Настройка Symfony Mailer для писем подтверждения заказа

Этот документ описывает подключение и проверку отправки писем через Symfony Mailer для подтверждения заказа, отправляемого синхронно в `CheckoutController::submit`.

## 1) Зависимости и файлы
- Установлен пакет: `symfony/mailer` (уже добавлен)
- Сервис отправки: `src/Service/OrderMailer.php`
- Шаблон письма: `templates/email/order-confirmation.html.twig`
- Вызов сервиса: `src/Controller/Catalog/CheckoutController.php::submit()` — после фиксации транзакции; ошибки логируются и не прерывают оформление

## 2) Переменные окружения
Добавьте (предпочтительно в `.env.local`, не коммитьте секреты):

```env
# Адрес отправителя по умолчанию
ADMIN_FROM_EMAIL=no-reply@example.com

# Транспорт для Mailer (выберите подходящий)
MAILER_DSN=smtp://localhost:1025
```

Частые варианты `MAILER_DSN`:
- Локально с MailHog/Mailpit: `smtp://localhost:1025`
- Gmail (App Password): `smtps://USERNAME:APP_PASSWORD@smtp.gmail.com:465`
- Sendmail: `sendmail://default`
- Mailgun (требуется bridge): `mailgun+api://KEY@default?domain=YOUR_DOMAIN`

## 3) Конфигурация проекта
- `config/packages/mailer.yaml`:
```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
```
- `config/services.yaml` — параметр отправителя (уже добавлен):
```yaml
parameters:
    app.notification.from_email: '%env(ADMIN_FROM_EMAIL)%'
```

## 4) Локальная проверка
1. Запустите локальный SMTP-клиент (MailHog/Mailpit) на порту 1025.
2. Установите в `.env.local`:
   - `MAILER_DSN=smtp://localhost:1025`
   - `ADMIN_FROM_EMAIL=no-reply@example.com`
3. Оформите заказ через форму checkout.
4. Письмо должно появиться в UI MailHog/Mailpit.

Если письма нет:
- Посмотрите логи: `var/log/dev.log` (Windows PowerShell: `Get-Content var/log/dev.log -Tail 50`)
- Проверьте, что email покупателя заполнен в форме и попадает в `Order->getCustomer()->getEmail()`
- Убедитесь, что SMTP слушает указанный порт; фаервол не блокирует

## 5) Продакшн-настройки
- Не хардкодить значения. Все через ENV/систему конфигурации.
- Задайте `ADMIN_FROM_EMAIL` и `MAILER_DSN` через переменные окружения среды.
- Для провайдеров (Gmail/Mailgun/др.) используйте их рекомендованные DSN и секреты из Secret Manager.
- Включите мониторинг ошибок отправки (канал логов mailer, APM).

## 6) Транспортные примеры
- SMTP 587 (STARTTLS): `smtp://user:pass@smtp.example.com:587?encryption=tls`
- SMTPS 465: `smtps://user:pass@smtp.example.com:465`
- С указанием имени отправителя: задавайте только email в конфиге, имя формируйте в коде письма при необходимости.

## 7) Где отправляется письмо
- `src/Controller/Catalog/CheckoutController.php` — метод `submit(...)` после `wrapInTransaction(...)` и `flush()`:
  - очищается checkout-контекст;
  - выполняется `OrderMailer->sendConfirmation($createdOrder)` в блоке try/catch;
  - `success`-экшен не отправляет письма, чтобы избежать повторов.

## 8) Быстрый троблшутинг
- 535/5.7.x — ошибка аутентификации: проверьте логин/пароль/токен провайдера
- SSL/TLS ошибки — проверьте порт/шифрование (`smtp://` + TLS для 587, `smtps://` для 465)
- Connection refused — SMTP не запущен/недоступен; проверьте хост/порт/фаервол
- Invalid address — проверьте валидность email получателя

## 9) Полезные команды
- Очистка кэша: `php bin/console cache:clear`
- Проверка контейнера DI: `php bin/console debug:container mailer`
