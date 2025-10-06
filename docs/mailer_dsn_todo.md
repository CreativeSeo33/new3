## TODO: Включение доставки писем через MAILER_DSN (dev и prod)

### Общее (Symfony Mailer)
- [ ] Убедиться, что в проекте есть `config/packages/mailer.yaml` c `framework.mailer.dsn: '%env(MAILER_DSN)%'` (есть)
- [ ] Установить валидный `ADMIN_FROM_EMAIL` (используется как `app.notification.from_email`) — без него письма не отправятся (From обязателен)
  - [ ] В dev: `ADMIN_FROM_EMAIL=no-reply@localhost`
  - [ ] В prod: адрес на домене проекта: `no-reply@your-domain.tld`

### DEV (локально)
- [ ] Выбрать транспорт (рекомендовано Mailpit/MailHog)
  - [ ] Вариант A (Mailpit):
    - [ ] Запустить Mailpit (Windows) — скачать бинарь или `docker run -p 8025:8025 -p 1025:1025 axllent/mailpit`
    - [ ] В `.env.local`: `MAILER_DSN=smtp://127.0.0.1:1025`
    - [ ] Открыть UI Mailpit: http://127.0.0.1:8025
  - [ ] Вариант B (только лог):
    - [ ] `MAILER_DSN=logger://`
    - [ ] Проверять письма в `var/log/dev.log`
  - [ ] Вариант C (фактическая отправка через внешний SMTP, например, Gmail/Sendgrid):
    - [ ] `MAILER_DSN=smtp://USER:PASSWORD@SMTP_HOST:587?encryption=tls&auth_mode=login`
    - [ ] Для Gmail нужен app‑password и включённый SMTP
- [ ] Проверка
  - [ ] Убедиться, что `ADMIN_FROM_EMAIL` задан (например, `no-reply@localhost`)
  - [ ] Выполнить действие, которое шлёт письмо (регистрация, сброс пароля) и проверить доставку в Mailpit/логах

### PROD (CentOS 7, виртуальный хостинг)
- [ ] Определить способ отправки (на виртуальном/shared хостинге чаще всего внешний SMTP провайдера)
  - [ ] Получить параметры SMTP у хостинга/провайдера почты (host, порт 587/465, TLS/SSL, логин/пароль)
  - [ ] Пример DSN (TLS/587):
    - [ ] `MAILER_DSN=smtp://USER:PASSWORD@SMTP_HOST:587?encryption=tls&auth_mode=login`
  - [ ] Пример DSN (SSL/465):
    - [ ] `MAILER_DSN=smtp://USER:PASSWORD@SMTP_HOST:465?encryption=ssl&auth_mode=login`
- [ ] Настроить переменные окружения на сервере
  - [ ] В `.env.local` или через системное окружение (unit/Apache/Nginx PHP‑FPM):
    - [ ] `APP_ENV=prod`
    - [ ] `MAILER_DSN=...` (из пункта выше)
    - [ ] `ADMIN_FROM_EMAIL=no-reply@your-domain.tld`
- [ ] DNS и доверие (повышает доставляемость)
  - [ ] Добавить SPF запись для домена (TXT): `v=spf1 include:_spf.your-smtp-provider ~all`
  - [ ] Включить DKIM у почтового провайдера и добавить DKIM TXT запись
  - [ ] Добавить DMARC (опционально): `v=DMARC1; p=quarantine; rua=mailto:dmarc@your-domain.tld`
- [ ] Сетевые требования
  - [ ] Проверить, что исходящие соединения на порт 587/465 разрешены хостингом/фаерволом
  - [ ] Для self‑signed/TLS проблем временно можно добавить `?verify_peer=0&verify_peer_name=0` (не рекомендуется в проде)
- [ ] Релизные шаги
  - [ ] `php bin/console cache:clear --env=prod`
  - [ ] Проверить `var/log/prod.log` на ошибки Mailer
  - [ ] Протестировать реальную отправку (регистрация/сброс пароля)

### Точки конфигурации в коде проекта (актуально уже внедрено)
- [ ] `config/packages/mailer.yaml` — читает `MAILER_DSN`
- [ ] `config/services.yaml` — `app.notification.from_email` ← `ADMIN_FROM_EMAIL`
- [ ] `App\Service\Auth\MailerService` — использует `$from` из параметров; без `ADMIN_FROM_EMAIL` письма не отправятся (ошибка «An email must have a From»)

### Быстрый чек‑лист проверки
- [ ] MAILER_DSN установлен и доступен (SMTP хост/порт достижимы)
- [ ] ADMIN_FROM_EMAIL указывает валидный адрес на домене
- [ ] Для prod настроены SPF/DKIM/DMARC
- [ ] В логах нет исключений Mailer (`var/log/*`)
- [ ] Письмо реально доходит (проверено на ящик/в Mailpit)

### Примеры DSN
- Sendgrid: `MAILER_DSN=smtp://apikey:SG.xxxxxxxx@smtp.sendgrid.net:587?encryption=tls`
- Mailgun: `MAILER_DSN=smtp://postmaster@mg.your-domain.tld:PASSWORD@smtp.mailgun.org:587?encryption=tls`
- Yandex 360: `MAILER_DSN=smtp://USER:PASSWORD@smtp.yandex.ru:465?encryption=ssl`


