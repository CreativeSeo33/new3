[3 tools called]

# Инструкция по установке проекта на виртуальный хостинг CentOS 7

## Требования к серверу

- **OS:** CentOS 7
- **RAM:** минимум 2GB, рекомендуется 4GB
- **Диск:** минимум 20GB свободного места
- **CPU:** 2+ ядра
- **Root доступ:** SSH с правами sudo/root

---

## Часть 1: Подготовка сервера

### 1.1. Подключитесь к серверу по SSH

```bash
ssh root@ваш-ip-адрес
# или
ssh пользователь@ваш-ip-адрес
```

### 1.2. Обновите систему

```bash
sudo yum update -y
sudo yum install -y yum-utils device-mapper-persistent-data lvm2 curl wget git unzip
```

### 1.3. Установите Docker

```bash
# Добавьте официальный репозиторий Docker
sudo yum-config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo

# Установите Docker
sudo yum install -y docker-ce docker-ce-cli containerd.io

# Запустите Docker
sudo systemctl start docker
sudo systemctl enable docker

# Проверьте установку
docker --version
```

### 1.4. Установите Docker Compose

```bash
# Скачайте последнюю версию Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/download/v2.24.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose

# Дайте права на выполнение
sudo chmod +x /usr/local/bin/docker-compose

# Проверьте установку
docker-compose --version
```

### 1.5. Настройте Firewall

```bash
# Откройте порты HTTP (80) и HTTPS (443)
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-port=80/tcp
sudo firewall-cmd --permanent --add-port=443/tcp

# Перезагрузите firewall
sudo firewall-cmd --reload

# Проверьте статус
sudo firewall-cmd --list-all
```

### 1.6. Отключите SELinux (опционально, упрощает настройку)

```bash
# Временно
sudo setenforce 0

# Постоянно (откройте файл и измените SELINUX=enforcing на SELINUX=permissive)
sudo vi /etc/selinux/config
# Измените строку:
# SELINUX=permissive
```

---

## Часть 2: Загрузка и настройка проекта

### 2.1. Создайте директорию для проекта

```bash
# Создайте папку
sudo mkdir -p /var/www/new3
cd /var/www/new3

# Дайте права текущему пользователю
sudo chown -R $USER:$USER /var/www/new3
```

### 2.2. Загрузите проект

**Вариант А: Через Git (если есть репозиторий)**

```bash
git clone https://ваш-репозиторий.git .
```

**Вариант Б: Загрузка архива**

```bash
# На локальном компьютере создайте архив проекта
# Затем загрузите на сервер через SCP:

# На локальном компьютере (Windows PowerShell):
scp project.zip root@ваш-ip:/var/www/new3/

# На сервере распакуйте:
cd /var/www/new3
unzip project.zip
rm project.zip
```

### 2.3. Создайте .env файл для production

```bash
cd /var/www/new3

cat > .env << 'EOF'
# Окружение
APP_ENV=prod
APP_DEBUG=0

# Сгенерируйте случайный секрет (32+ символа)
APP_SECRET=замените-на-случайную-строку-32-символа-минимум

# База данных
DATABASE_URL="mysql://root:your_strong_password_here@db:3306/new3?serverVersion=8.0&charset=utf8mb4"
MYSQL_DATABASE=new3
MYSQL_ROOT_PASSWORD=your_strong_password_here

# JWT (ключи генерируются позже)
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=ваш-passphrase-для-jwt

# CORS (замените на ваш домен)
CORS_ALLOW_ORIGIN=^https?://(ваш-домен\.com)(:[0-9]+)?$

# Trusted proxies для работы за Nginx
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16

# Email
MAILER_DSN=smtp://localhost:1025
ADMIN_FROM_EMAIL=admin@ваш-домен.com

# Прочее
LOCK_DSN="flock"
APP_THEME_ENABLED=0
EOF

# Установите безопасные права доступа
chmod 600 .env
```

**⚠️ ВАЖНО:** Замените:
- `замените-на-случайную-строку-32-символа-минимум` - сгенерируйте случайную строку
- `your_strong_password_here` - надежный пароль для MySQL
- `ваш-passphrase-для-jwt` - пароль для JWT ключей
- `ваш-домен.com` - ваш реальный домен

Сгенерировать случайную строку:
```bash
openssl rand -hex 32
```

### 2.4. Измените docker-compose.yml для production

```bash
# Создайте production версию
cat > docker-compose.prod.yml << 'EOF'
services:
  php:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    environment:
      DATABASE_URL: "mysql://root:${MYSQL_ROOT_PASSWORD}@db:3306/${MYSQL_DATABASE}?serverVersion=8.0&charset=utf8mb4"
      APP_ENV: prod
      APP_DEBUG: 0
      APP_SECRET: "${APP_SECRET}"
      TRUSTED_PROXIES: "127.0.0.1,REMOTE_ADDR,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16"
      JWT_SECRET_KEY: "%kernel.project_dir%/config/jwt/private.pem"
      JWT_PUBLIC_KEY: "%kernel.project_dir%/config/jwt/public.pem"
      JWT_PASSPHRASE: "${JWT_PASSPHRASE}"
      CORS_ALLOW_ORIGIN: "${CORS_ALLOW_ORIGIN}"
      LOCK_DSN: "flock"
      MAILER_DSN: "${MAILER_DSN:-smtp://mailhog:1025}"
      ADMIN_FROM_EMAIL: "${ADMIN_FROM_EMAIL}"
    volumes:
      - ./var:/app/var
      - ./public/media:/app/public/media
      - ./config/jwt:/app/config/jwt
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    restart: always

  nginx:
    build:
      context: .
      dockerfile: Dockerfile.nginx
    ports:
      - "80:80"
    volumes:
      - ./public:/app/public:ro
    depends_on:
      - php
    restart: always

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: ${MYSQL_DATABASE}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./my.cnf:/etc/mysql/conf.d/custom.cnf:ro
    command: --default-authentication-plugin=mysql_native_password --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p$$MYSQL_ROOT_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 5
    restart: always

  redis:
    image: redis:7-alpine
    command: ["redis-server", "--maxmemory", "256mb", "--maxmemory-policy", "allkeys-lru"]
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    restart: always

volumes:
  mysql_data:
  redis_data:
EOF
```

---

## Часть 3: Сборка и запуск

### 3.1. Соберите Docker образы

```bash
cd /var/www/new3

# Сборка образов (займет 5-15 минут в первый раз)
docker-compose -f docker-compose.prod.yml build --no-cache
```

### 3.2. Запустите контейнеры

```bash
# Запуск в фоновом режиме
docker-compose -f docker-compose.prod.yml up -d

# Проверьте статус контейнеров
docker-compose -f docker-compose.prod.yml ps

# Все контейнеры должны быть в статусе "Up" или "healthy"
```

### 3.3. Сгенерируйте JWT ключи

```bash
# Создайте директорию
docker-compose -f docker-compose.prod.yml exec php mkdir -p config/jwt

# Сгенерируйте ключи
docker-compose -f docker-compose.prod.yml exec php sh -c "
  openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 -pass pass:ваш-passphrase-для-jwt &&
  openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:ваш-passphrase-для-jwt &&
  chmod 640 config/jwt/private.pem config/jwt/public.pem
"

# Проверьте создание ключей
ls -la config/jwt/
```

⚠️ Замените `ваш-passphrase-для-jwt` на тот же, что в .env

### 3.4. Импортируйте базу данных

```bash
# Если у вас есть дамп базы данных (например, new3.sql)
docker-compose -f docker-compose.prod.yml exec -T db mysql -u root -pyour_strong_password_here new3 < new3.sql

# Или выполните миграции
docker-compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 3.5. Настройте права доступа

```bash
docker-compose -f docker-compose.prod.yml exec php sh -c "
  mkdir -p var/cache var/log public/media &&
  chmod -R 777 var/cache var/log public/media
"
```

### 3.6. Прогрейте кеш production

```bash
docker-compose -f docker-compose.prod.yml exec php php bin/console cache:clear --env=prod --no-debug
docker-compose -f docker-compose.prod.yml exec php php bin/console cache:warmup --env=prod --no-debug
```

---

## Часть 4: Настройка домена и SSL (опционально)

### 4.1. Настройте DNS

В панели управления вашим доменом создайте A-запись:
```
Тип: A
Имя: @ (или www)
Значение: IP-адрес-вашего-сервера
TTL: 3600
```

### 4.2. Установите Certbot для SSL (Let's Encrypt)

```bash
# Установите EPEL репозиторий
sudo yum install -y epel-release

# Установите Certbot
sudo yum install -y certbot

# Остановите Nginx контейнер временно
docker-compose -f docker-compose.prod.yml stop nginx

# Получите SSL сертификат
sudo certbot certonly --standalone -d ваш-домен.com -d www.ваш-домен.com

# Сертификаты будут в /etc/letsencrypt/live/ваш-домен.com/
```

### 4.3. Обновите Nginx конфигурацию для HTTPS

Создайте `Dockerfile.nginx.prod`:

```dockerfile
FROM nginx:1.27-alpine

RUN apk add --no-cache curl

WORKDIR /app

RUN rm -f /etc/nginx/conf.d/default.conf

COPY nginx-ssl.conf /etc/nginx/conf.d/default.conf

EXPOSE 80 443
```

Создайте `nginx-ssl.conf`:

```nginx
server {
    listen 80;
    server_name ваш-домен.com www.ваш-домен.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ваш-домен.com www.ваш-домен.com;
    
    root /app/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/ваш-домен.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ваш-домен.com/privkey.pem;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    client_max_body_size 20m;

    gzip on;
    gzip_types text/plain text/css application/javascript application/json application/xml+rss image/svg+xml;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param HTTPS on;
    }

    location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|ico|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000, immutable";
        try_files $uri =404;
    }
}
```

Обновите docker-compose.prod.yml для монтирования SSL:

```yaml
  nginx:
    # ... существующая конфигурация
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./public:/app/public:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
      - ./nginx-ssl.conf:/etc/nginx/conf.d/default.conf:ro
```

Пересоберите и перезапустите:

```bash
docker-compose -f docker-compose.prod.yml up -d --build nginx
```

### 4.4. Автообновление SSL сертификата

```bash
# Создайте скрипт для обновления
sudo cat > /usr/local/bin/renew-ssl.sh << 'EOF'
#!/bin/bash
cd /var/www/new3
docker-compose -f docker-compose.prod.yml stop nginx
certbot renew --quiet
docker-compose -f docker-compose.prod.yml start nginx
EOF

sudo chmod +x /usr/local/bin/renew-ssl.sh

# Добавьте в cron (запуск каждую неделю)
sudo crontab -e
# Добавьте строку:
# 0 3 * * 0 /usr/local/bin/renew-ssl.sh >> /var/log/certbot-renew.log 2>&1
```

---

## Часть 5: Автозапуск при перезагрузке сервера

```bash
# Создайте systemd сервис
sudo cat > /etc/systemd/system/new3-app.service << 'EOF'
[Unit]
Description=New3 Application
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/var/www/new3
ExecStart=/usr/local/bin/docker-compose -f docker-compose.prod.yml up -d
ExecStop=/usr/local/bin/docker-compose -f docker-compose.prod.yml down
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
EOF

# Активируйте сервис
sudo systemctl daemon-reload
sudo systemctl enable new3-app.service
sudo systemctl start new3-app.service

# Проверьте статус
sudo systemctl status new3-app.service
```

---

## Часть 6: Мониторинг и обслуживание

### Просмотр логов

```bash
# Логи всех контейнеров
docker-compose -f docker-compose.prod.yml logs -f

# Логи конкретного контейнера
docker-compose -f docker-compose.prod.yml logs -f php
docker-compose -f docker-compose.prod.yml logs -f nginx
docker-compose -f docker-compose.prod.yml logs -f db

# Логи Symfony
docker-compose -f docker-compose.prod.yml exec php tail -f var/log/prod.log
```

### Бэкап базы данных

```bash
# Создайте директорию для бэкапов
mkdir -p /var/backups/new3

# Бэкап БД
docker-compose -f docker-compose.prod.yml exec -T db mysqldump -u root -pyour_strong_password_here new3 | gzip > /var/backups/new3/db-backup-$(date +%Y%m%d-%H%M%S).sql.gz

# Автоматический ежедневный бэкап через cron
sudo crontab -e
# Добавьте:
# 0 2 * * * cd /var/www/new3 && docker-compose -f docker-compose.prod.yml exec -T db mysqldump -u root -pPASSWORD new3 | gzip > /var/backups/new3/db-backup-$(date +\%Y\%m\%d).sql.gz
```

### Обновление проекта

```bash
cd /var/www/new3

# Скачайте обновления
git pull origin main
# или загрузите новые файлы

# Пересоберите образы
docker-compose -f docker-compose.prod.yml build --no-cache

# Остановите старые контейнеры и запустите новые
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml up -d

# Выполните миграции
docker-compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction

# Очистите кеш
docker-compose -f docker-compose.prod.yml exec php php bin/console cache:clear --env=prod
```

---

## Troubleshooting (решение проблем)

### Проблема: Контейнер не запускается

```bash
# Проверьте логи
docker-compose -f docker-compose.prod.yml logs php

# Проверьте ресурсы
docker stats

# Проверьте права доступа
ls -la var/
```

### Проблема: Сайт не открывается

```bash
# Проверьте, работает ли Nginx
docker-compose -f docker-compose.prod.yml ps

# Проверьте firewall
sudo firewall-cmd --list-all

# Проверьте порты
sudo netstat -tulpn | grep :80
```

### Проблема: Ошибки базы данных

```bash
# Войдите в контейнер БД
docker-compose -f docker-compose.prod.yml exec db mysql -u root -p

# Проверьте подключение
docker-compose -f docker-compose.prod.yml exec php php bin/console doctrine:schema:validate
```

### Полная переустановка

```bash
# Остановите и удалите всё
cd /var/www/new3
docker-compose -f docker-compose.prod.yml down -v

# Удалите образы
docker system prune -a

# Начните с Части 3 заново
```

---

## Чек-лист после установки

- [ ] Docker и Docker Compose установлены
- [ ] Firewall настроен (порты 80, 443 открыты)
- [ ] Проект загружен в `/var/www/new3`
- [ ] `.env` файл создан с правильными паролями
- [ ] Контейнеры запущены и работают (статус "healthy")
- [ ] JWT ключи сгенерированы
- [ ] База данных импортирована
- [ ] Кеш прогрет
- [ ] Сайт открывается по IP или домену
- [ ] SSL сертификат установлен (опционально)
- [ ] Автозапуск настроен (systemd)
- [ ] Бэкапы настроены

**Готово!** Ваш проект должен работать на `http://ваш-ip` или `https://ваш-домен.com`