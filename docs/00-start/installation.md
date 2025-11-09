---
owner: "@devops-team"
system_of_record: "narrative"
review_cycle_days: 60
last_reviewed: 2025-11-08
related_code:
    - ".env.example"
    - "composer.json"
    - "database/seeders/*.php"
---

# Установка stupidCms

Руководство по локальной установке stupidCms за 5 минут.

## Требования

-   **PHP**: 8.2 или выше
-   **Composer**: 2.x
-   **База данных**: MySQL 8.0+ или PostgreSQL 15+ или SQLite 3.35+
-   **Node.js**: 18+ (для admin UI)
-   **Elasticsearch**: 8.x (опционально, для поиска)

### Расширения PHP

```bash
# Обязательные
php -m | grep -E "pdo|mbstring|openssl|tokenizer|xml|ctype|json|bcmath"

# Рекомендуемые
php -m | grep -E "gd|imagick|redis|opcache"
```

## Быстрый старт

### 1. Клонирование репозитория

```bash
git clone <repository-url> stupidcms
cd stupidcms
```

### 2. Установка зависимостей

```bash
# PHP зависимости
composer install

# Node.js зависимости (для admin UI)
cd cms/admin
npm install
cd ../..
```

### 3. Настройка окружения

```bash
# Создать .env
cp .env.example .env

# Сгенерировать ключ приложения
php artisan key:generate

# Сгенерировать JWT secret
php artisan jwt:secret
```

### 4. Конфигурация БД

Отредактируйте `.env`:

```env
# Для MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=stupidcms
DB_USERNAME=root
DB_PASSWORD=

# Или для SQLite (быстрый старт)
DB_CONNECTION=sqlite
# DB_DATABASE будет database/database.sqlite
```

### 5. Миграции и сиды

```bash
# Создать таблицы
php artisan migrate

# Заполнить тестовыми данными
php artisan db:seed

# Или всё сразу (пересоздаст БД!)
php artisan migrate:fresh --seed
```

Будет создан админ-пользователь:

-   **Email**: admin@example.com
-   **Password**: password

### 6. Запуск сервера

```bash
# Laravel сервер
php artisan serve

# В другом терминале: Admin UI dev server
cd cms/admin
npm run dev
```

Откройте в браузере:

-   **API**: http://localhost:8000
-   **Admin UI**: http://localhost:5173

## Продвинутая установка

### Настройка Elasticsearch (опционально)

stupidCms может использовать Elasticsearch для полнотекстового поиска.

#### Docker Compose

```yaml
# docker-compose.yml
services:
    elasticsearch:
        image: docker.elastic.co/elasticsearch/elasticsearch:8.11.0
        environment:
            - discovery.type=single-node
            - xpack.security.enabled=false
            - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
        ports:
            - "9200:9200"
```

```bash
docker-compose up -d elasticsearch
```

#### Конфигурация

```env
# .env
ELASTICSEARCH_ENABLED=true
ELASTICSEARCH_HOSTS=localhost:9200
```

#### Индексация

```bash
# Создать индексы
php artisan search:setup

# Индексировать существующие entries
php artisan search:reindex
```

### Настройка Redis (кэш и очереди)

```env
# .env
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

```bash
# Запустить queue worker
php artisan queue:work
```

### Настройка хранилища (S3/MinIO)

Для production используйте S3-совместимое хранилище для медиа.

```env
# .env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=stupidcms-media
AWS_ENDPOINT=https://s3.amazonaws.com  # или MinIO endpoint
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### Настройка CORS

Для SPA фронтенда настройте CORS:

```env
# .env
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://yourapp.com
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Requested-With
CORS_EXPOSED_HEADERS=
CORS_MAX_AGE=3600
CORS_SUPPORTS_CREDENTIALS=true
```

Подробнее: [CORS & Cookies](../20-how-to/cors.md)

## Проверка установки

### 1. Health Check

```bash
curl http://localhost:8000/api/health
```

Ожидаемый ответ:

```json
{
    "status": "ok",
    "database": "connected",
    "cache": "working"
}
```

### 2. Тесты

```bash
# Запустить все тесты
php artisan test

# С coverage
php artisan test --coverage-html coverage/
```

Все тесты должны пройти ✅

### 3. Вход в админку

1. Откройте http://localhost:5173
2. Войдите как:
    - Email: `admin@example.com`
    - Password: `password`

## Типичные проблемы

### Ошибка: "No application encryption key"

```bash
php artisan key:generate
```

### Ошибка: "SQLSTATE[HY000] [1045] Access denied"

Проверьте настройки БД в `.env`:

-   `DB_HOST`, `DB_PORT`
-   `DB_USERNAME`, `DB_PASSWORD`
-   Убедитесь, что БД запущена

### Ошибка: "Class 'Redis' not found"

Установите расширение Redis:

```bash
# Ubuntu/Debian
sudo apt-get install php-redis

# macOS (Homebrew)
pecl install redis
```

### Ошибка: "Permission denied" при загрузке медиа

```bash
# Linux/macOS
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Или для локальной разработки
chmod -R 777 storage bootstrap/cache
```

### Admin UI не запускается

```bash
cd cms/admin
rm -rf node_modules package-lock.json
npm install
npm run dev
```

## Следующие шаги

✅ Установка завершена! Теперь:

1. **Изучите концепции**: [Domain Model](../10-concepts/domain-model.md)
2. **Создайте первый Post Type**: [How-to Guide](../20-how-to/add-post-type.md)
3. **Изучите API**: Scribe (`../_generated/api-docs/index.html`)
4. **Настройте development**: [Local Development](#) _(TODO)_

## Полезные команды

```bash
# Очистить кэш
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Сгенерировать документацию
composer docs:gen

# Собрать frontend
cd cms/admin && npm run build

# Запустить всё в production режиме
php artisan optimize
php artisan config:cache
php artisan route:cache
```

## Docker (альтернатива)

Для быстрого старта с Docker:

```bash
# TODO: Добавить Dockerfile и docker-compose.yml
docker-compose up -d
docker-compose exec app php artisan migrate --seed
```

---

**Возникли проблемы?** Создайте [issue](#) или проверьте [FAQ](#).
