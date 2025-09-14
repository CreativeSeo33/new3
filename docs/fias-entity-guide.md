# Руководство по работе с Entity Fias (ФИАС)

## Описание

Entity `Fias` представляет собой модель для работы с данными Федеральной Информационной Адресной Системы (ФИАС).

## Структура таблицы

```sql
CREATE TABLE fias (
  fias_id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  postalcode VARCHAR(6) NULL,
  offname VARCHAR(120) NULL,
  shortname VARCHAR(10) NULL,
  level SMALLINT NOT NULL,
  INDEX postalcode_idx (postalcode),
  INDEX offname_idx (offname),
  INDEX level_idx (level),
  INDEX parent_id_idx (parent_id),
  INDEX osl_idx (offname, shortname, level)
);
```

## Поля Entity

| Поле | Тип | Описание |
|------|-----|----------|
| `id` | `int` | Первичный ключ |
| `parentId` | `int` | ID родительского элемента |
| `postalcode` | `string|null` | Почтовый индекс (6 символов) |
| `offname` | `string|null` | Название объекта (120 символов) |
| `shortname` | `string|null` | Короткое название типа (10 символов) |
| `level` | `int` | Уровень иерархии |

## Уровни адресов

| Уровень | Название | Пример |
|---------|----------|---------|
| 0 | Страна | Россия |
| 1 | Регион | Московская область |
| 2 | Район | Одинцовский район |
| 3 | Город | Москва |
| 4 | Населенный пункт | пос. Внуково |
| 5 | Улица | ул. Ленина |
| 6 | Здание | д. 15 |

## Использование Repository

### Основные методы

```php
use App\Repository\FiasRepository;

class SomeController
{
    public function __construct(
        private FiasRepository $fiasRepository
    ) {}

    // Найти по почтовому индексу
    public function findByPostalcode(string $postalcode): array
    {
        return $this->fiasRepository->findByPostalcode($postalcode);
    }

    // Найти по названию
    public function findByName(string $name, ?int $level = null): array
    {
        return $this->fiasRepository->findByName($name, $level);
    }

    // Получить регионы
    public function getRegions(): array
    {
        return $this->fiasRepository->findRegions();
    }

    // Получить города региона
    public function getCities(int $regionId): array
    {
        return $this->fiasRepository->findCities($regionId);
    }

    // Получить дочерние записи
    public function getChildren(int $parentId, ?int $level = null): array
    {
        return $this->fiasRepository->findChildren($parentId, $level);
    }

    // Получить полный путь адреса
    public function getFullPath(Fias $fias): array
    {
        return $this->fiasRepository->getFullPath($fias);
    }
}
```

## Использование Service

### FiasService методы

```php
use App\Service\FiasService;

class AddressController
{
    public function __construct(
        private FiasService $fiasService
    ) {}

    // Поиск адресов с фильтрами
    public function searchAddresses(Request $request): JsonResponse
    {
        $name = $request->query->get('name');
        $postalcode = $request->query->get('postalcode');
        $level = $request->query->getInt('level');
        $parentId = $request->query->getInt('parentId');

        $results = $this->fiasService->searchAddresses(
            name: $name,
            postalcode: $postalcode,
            level: $level,
            parentId: $parentId,
            limit: 50,
            offset: 0
        );

        return $this->json($results);
    }

    // Получить полный адрес
    public function getFullAddress(int $id): JsonResponse
    {
        $fias = $this->fiasService->findById($id);

        if (!$fias) {
            throw $this->createNotFoundException('Адрес не найден');
        }

        return $this->json([
            'id' => $fias->getId(),
            'fullAddress' => $fias->getFullAddress(),
            'fullPath' => $this->fiasService->getFullAddressPath($fias),
            'level' => $fias->getLevel(),
            'levelName' => $fias->getLevelName(),
        ]);
    }
}
```

## Контроллер для API

### Пример контроллера

```php
<?php

namespace App\Controller\Api;

use App\Service\FiasService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/fias')]
class FiasController
{
    public function __construct(
        private FiasService $fiasService
    ) {}

    #[Route('/regions', methods: ['GET'])]
    public function getRegions(): JsonResponse
    {
        $regions = $this->fiasService->getRegions();

        return $this->json(array_map(function ($region) {
            return [
                'id' => $region->getId(),
                'name' => $region->getOffname(),
                'shortName' => $region->getShortname(),
                'postalcode' => $region->getPostalcode(),
            ];
        }, $regions));
    }

    #[Route('/cities/{regionId}', methods: ['GET'])]
    public function getCities(int $regionId): JsonResponse
    {
        $cities = $this->fiasService->getCities($regionId);

        return $this->json(array_map(function ($city) {
            return [
                'id' => $city->getId(),
                'name' => $city->getOffname(),
                'shortName' => $city->getShortname(),
                'postalcode' => $city->getPostalcode(),
            ];
        }, $cities));
    }

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $name = $request->query->get('q');
        $level = $request->query->getInt('level');
        $limit = $request->query->getInt('limit', 20);

        if (empty($name)) {
            return $this->json([]);
        }

        $results = $this->fiasService->findByName($name, $level);

        return $this->json(array_map(function ($item) {
            return [
                'id' => $item->getId(),
                'name' => $item->getOffname(),
                'shortName' => $item->getShortname(),
                'level' => $item->getLevel(),
                'levelName' => $item->getLevelName(),
                'postalcode' => $item->getPostalcode(),
                'fullAddress' => $item->getFullAddress(),
            ];
        }, array_slice($results, 0, $limit)));
    }
}
```

## Миграция

Для создания таблицы в базе данных выполните:

```bash
php bin/console doctrine:migrations:migrate
```

Или только эту миграцию:

```bash
php bin/console doctrine:migrations:execute --up "DoctrineMigrations\\Version20250914095900"
```

## Импорт данных

Для импорта данных из SQL файла используйте:

```bash
mysql -u username -p database_name < h:/fias.sql
```

Или в phpMyAdmin загрузите файл `h:/fias.sql`.

## Производительность

### Индексы

Таблица имеет следующие индексы для оптимизации запросов:

- `postalcode_idx` - для поиска по почтовому индексу
- `offname_idx` - для поиска по названию
- `level_idx` - для фильтрации по уровню
- `parent_id_idx` - для поиска дочерних записей
- `osl_idx` - составной индекс для сложных запросов

### Оптимизация запросов

- Используйте LIMIT для ограничения результатов
- Для больших объемов данных рассмотрите пагинацию
- Используйте конкретные уровни адресов при поиске

## Примеры использования

### Получение полного адреса

```php
$fias = $fiasService->findById(123);
$fullPath = $fiasService->getFullAddressPath($fias);
// Результат: "Россия, Московская область, г. Москва"
```

### Поиск города по названию

```php
$cities = $fiasService->findByName('Москва', 3); // уровень 3 = город
```

### Получение всех регионов

```php
$regions = $fiasService->getRegions();
foreach ($regions as $region) {
    echo $region->getOffname() . ' ' . $region->getShortname() . PHP_EOL;
}
```
