# Рантайм‑артефакты

Назначение: быстрые машиночитаемые снимки проекта для ИИ и диагностики.

Содержимое:
- container.json — карта DI‑контейнера
- routes.json — маршруты
- composer-deps.json — прямые зависимости Composer
- openapi.json | openapi.yaml — контракт API Platform

Артефакты БД:
- ../db/schema.sql — SQL‑дифф схемы БД (dump only, без применения)
- ../db/entities.txt — список сущностей Doctrine

Запуск (Windows PowerShell):
```powershell
powershell -ExecutionPolicy Bypass -File docs/refresh.ps1
```

WSL/Git Bash:
```bash
bash docs/refresh.sh
```

Примечание: Messenger не используется — снимок по шинам не генерируется.


