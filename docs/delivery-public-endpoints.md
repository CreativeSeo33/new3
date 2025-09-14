# Публичные эндпойнты доставки (контроллеры)

Эндпойнты реализованы в `src/Controller/Api/DeliveryPublicController.php` и отдаются без API Platform, с ручным контролем полей, кешем и пагинацией.

## Конфигурация

Параметры настраиваются через env и `config/services.yaml`:

```
# .env (пример значений)
DELIVERY_PUBLIC_TTL=600
DELIVERY_POINTS_DEFAULT_LIMIT=20
DELIVERY_POINTS_MAX_LIMIT=100
```

```
# config/services.yaml (фрагмент)
parameters:
    delivery.public.ttl_seconds: '%env(int:DELIVERY_PUBLIC_TTL)%'
    delivery.points.default_limit: '%env(int:DELIVERY_POINTS_DEFAULT_LIMIT)%'
    delivery.points.max_limit: '%env(int:DELIVERY_POINTS_MAX_LIMIT)%'
```

## Эндпойнты

### GET /delivery/points

Параметры query:
- `city` (string) или `cityId` (int, FIAS id) — один из параметров обязателен
- `page` (int, >=1) — страница; по умолчанию 1
- `itemsPerPage` (int) — лимит на страницу; по умолчанию `DELIVERY_POINTS_DEFAULT_LIMIT`, ограничивается `DELIVERY_POINTS_MAX_LIMIT`

Ответ:
```
{
  "data": [
    {"id": 1, "code": "PVZ-1", "name": "ПВЗ 1", "address": "...", "city": "...", "lat": 55.7, "lng": 37.6, "company": "..."}
  ],
  "total": 123,
  "page": 1,
  "itemsPerPage": 20
}
```

Кеш: ключ на город/fiас, страницу и лимит. TTL: `DELIVERY_PUBLIC_TTL` секунд.

Пример:
```
curl "http://127.0.0.1:8000/delivery/points?city=Москва&page=1&itemsPerPage=20"
```

### GET /delivery/price

Параметры query:
- `city` (string) — обязателен

Ответ (город найден):
```
{
  "city": "Москва",
  "available": true,
  "cost": 300,
  "freeFrom": 5000,
  "term": "1-2 дня"
}
```

Ответ (город не найден в тарифах):
```
{
  "city": "Город",
  "available": false,
  "message": "Город не найден в тарифах"
}
```

Кеш: ключ на город. TTL: `DELIVERY_PUBLIC_TTL` секунд.

## Заметки
- Источник данных: `PvzPoints` (точки) и `PvzPrice` (тарифы).
- Для точек поддерживаются фильтры по `city` (строка, нормализованное сравнение) и `cityId` (FIAS).
- Выдача полей в `/delivery/points` ограничена безопасным набором для фронта.

