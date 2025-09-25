---
title: Yandex Delivery API (Admin)
---

# Yandex Delivery API (Admin)

Эндпоинт админ‑API для проксирования запросов в Яндекс Доставку.

## Переменные окружения (.env.local)

```
# Боевой хост (по умолчанию):
YANDEX_DELIVERY_BASE_URL=https://b2b-authproxy.taxi.yandex.net

# Токен Bearer из ЛК Яндекс Доставки → Интеграция → Токен
YANDEX_DELIVERY_TOKEN=<your_oauth_token>
```

Для тестирования можно использовать тестовый хост:

```
YANDEX_DELIVERY_BASE_URL=https://b2b.taxi.tst.yandex.net
```

Токены хранить только в `.env.local`, не коммитить в репозиторий.

## Доступный endpoint

```
POST /api/admin/yandex-delivery/offers/create
Content-Type: application/json

{ /* тело запроса согласно API Яндекс Доставки */ }
```

Требуется роль: `ROLE_ADMIN`.

## Документация Яндекс

- Как получить доступ к API: https://yandex.com/support/delivery-profile/ru/api/other-day/access


