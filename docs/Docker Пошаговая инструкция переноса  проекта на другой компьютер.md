Прочитаю конфигурацию Docker, чтобы дать точную инструкцию.

[5 tools called]

# Пошаговая инструкция переноса Docker проекта на другой компьютер

## Вариант 1: Перенос исходного кода (рекомендуется для новичков) 🎯

### На исходном компьютере:

1. **Архивируйте проект**
   ```powershell
   # Перейдите в папку проекта
   cd j:\laragon\www\new3
   
   # Создайте архив (можно через WinRAR/7-Zip или PowerShell)
   Compress-Archive -Path * -DestinationPath project.zip
   ```

2. **Экспортируйте базу данных**
   ```powershell
   # Если Docker запущен
   docker compose exec db mysqldump -u root -ppassword new3 > database-backup.sql
   
   # Или используйте уже существующий файл new3.sql
   ```

3. **Скопируйте эти файлы на флешку/облако:**
   - `project.zip`
   - `database-backup.sql` (или `new3.sql`)

### На новом компьютере:

1. **Установите необходимое ПО:**
   - Docker Desktop для Windows: https://www.docker.com/products/docker-desktop/
   - Git (опционально): https://git-scm.com/download/win

2. **Распакуйте проект**
   ```powershell
   # Создайте папку и распакуйте туда проект
   mkdir C:\projects\new3
   cd C:\projects\new3
   # Распакуйте project.zip в эту папку
   ```

3. **Создайте файл `.env`**
   ```powershell
   # В папке проекта создайте файл .env с минимальным содержимым:
   @"
   APP_ENV=dev
   APP_SECRET=dev-secret-change-me
   DATABASE_URL="mysql://root:password@db:3306/new3?serverVersion=8.0&charset=utf8mb4"
   MYSQL_DATABASE=new3
   MYSQL_ROOT_PASSWORD=password
   "@ | Out-File -FilePath .env -Encoding utf8
   ```

4. **Соберите и запустите проект**
   ```powershell
   # Сборка образов (первый раз займет 5-10 минут)
   docker compose build
   
   # Запуск всех сервисов
   docker compose up -d
   
   # Проверьте статус
   docker compose ps
   ```

5. **Импортируйте базу данных**
   ```powershell
   # Подождите 30 секунд, пока БД запустится, затем:
   docker compose exec -T db mysql -u root -ppassword new3 < database-backup.sql
   ```

6. **Сгенерируйте JWT ключи**
   ```powershell
   docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
   ```

7. **Выполните миграции (опционально)**
   ```powershell
   docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
   ```

8. **Очистите кеш**
   ```powershell
   docker compose exec php php bin/console cache:clear
   ```

9. **Готово!** Откройте браузер:
   - Сайт: http://localhost
   - API: http://localhost/api
   - Adminer (БД): http://localhost:8080
   - MailHog (почта): http://localhost:8025

---

## Вариант 2: Экспорт/импорт готовых образов Docker (для продвинутых)

### На исходном компьютере:

1. **Сохраните образы**
   ```powershell
   # Экспортируйте все образы проекта
   docker save new3-php new3-nginx -o docker-images.tar
   
   # Это создаст файл docker-images.tar (может быть несколько ГБ)
   ```

2. **Экспортируйте volumes (данные БД)**
   ```powershell
   # Экспорт volume с данными MySQL
   docker run --rm -v new3_mysql_data:/data -v ${PWD}:/backup alpine tar czf /backup/mysql-data.tar.gz -C /data .
   
   # Экспорт volume с данными Redis
   docker run --rm -v new3_redis_data:/data -v ${PWD}:/backup alpine tar czf /backup/redis-data.tar.gz -C /data .
   ```

3. **Скопируйте на флешку:**
   - `docker-images.tar`
   - `mysql-data.tar.gz`
   - `redis-data.tar.gz`
   - `docker-compose.yml`
   - `.env` (если есть)

### На новом компьютере:

1. **Установите Docker Desktop**

2. **Импортируйте образы**
   ```powershell
   docker load -i docker-images.tar
   ```

3. **Создайте volumes и импортируйте данные**
   ```powershell
   # Создайте volumes
   docker volume create new3_mysql_data
   docker volume create new3_redis_data
   
   # Импортируйте данные MySQL
   docker run --rm -v new3_mysql_data:/data -v ${PWD}:/backup alpine tar xzf /backup/mysql-data.tar.gz -C /data
   
   # Импортируйте данные Redis
   docker run --rm -v new3_redis_data:/data -v ${PWD}:/backup alpine tar xzf /backup/redis-data.tar.gz -C /data
   ```

4. **Запустите**
   ```powershell
   docker compose up -d
   ```

---

## Troubleshooting (решение проблем)

**Порт 80 занят:**
```yaml
# В docker-compose.yml измените порт nginx:
ports:
  - "8000:80"  # Теперь сайт будет на http://localhost:8000
```

**Недостаточно памяти Docker:**
- Откройте Docker Desktop → Settings → Resources
- Увеличьте Memory до минимум 4GB

**Образы не собираются:**
```powershell
# Очистите кеш Docker
docker system prune -a
docker compose build --no-cache
```

**База данных не подключается:**
```powershell
# Проверьте логи
docker compose logs db

# Убедитесь, что контейнер БД здоров
docker compose ps
```

---

## Полезные команды

```powershell
# Просмотр логов
docker compose logs -f

# Остановка проекта
docker compose down

# Полное удаление (включая volumes)
docker compose down -v

# Перезапуск сервисов
docker compose restart

# Войти в контейнер PHP
docker compose exec php sh

# Войти в контейнер БД
docker compose exec db mysql -u root -ppassword new3
```

**Рекомендация:** Для новичка лучше использовать **Вариант 1** - он проще, надежнее и занимает меньше места.