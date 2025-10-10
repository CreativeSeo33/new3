# Docker запуск и деплой

## Быстрый старт (Windows PowerShell)

```powershell
copy env.docker.example .env.docker
make build
make up
make jwt-keys
make migrate
```

URL: http://localhost
API: http://localhost/api
Health: http://localhost/health

## Команды

- build — сборка образов
- up — запуск сервисов
- down — остановка
- logs — логи
- migrate — миграции Doctrine
- fixtures — фикстуры (если есть)
- cache-clear — очистка кеша
- warmup — прогрев кеша
- restart — рестарт php/nginx
- jwt-keys — генерация RSA ключей в config/jwt

## Примечания

- Статика public/build собирается на Node стадии внутри образа
- Медиа в public/media (volume)
- Для dev можно добавить docker-compose.dev.yml с монтированием проекта


