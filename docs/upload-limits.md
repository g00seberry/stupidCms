# Настройка лимитов загрузки файлов

## Проблема

При загрузке файлов может возникать ошибка:
```
POST Content-Length exceeds the limit
```

Это происходит, когда размер файла превышает лимиты PHP.

## Текущие лимиты

Проверить текущие лимиты можно командой:
```bash
php -i | findstr "post_max_size upload_max_filesize"
```

Или через PHP:
```php
ini_get('post_max_size');      // Лимит POST-запроса
ini_get('upload_max_filesize'); // Лимит одного файла
```

## Решение

### 1. Apache с mod_php

Настройки уже добавлены в `public/.htaccess`:
```apache
<IfModule mod_php.c>
    php_value post_max_size 50M
    php_value upload_max_filesize 50M
    php_value max_execution_time 300
    php_value max_input_time 300
</IfModule>
```

**Важно:** Эти настройки работают только если PHP работает как модуль Apache (`mod_php`).

### 2. PHP-FPM (Nginx, Apache с PHP-FPM)

Нужно изменить настройки в `php.ini` или в конфигурации PHP-FPM:

#### Вариант A: Глобальный `php.ini`

Найти `php.ini`:
```bash
php --ini
```

Изменить значения:
```ini
post_max_size = 50M
upload_max_filesize = 50M
max_execution_time = 300
max_input_time = 300
```

#### Вариант B: Конфигурация пула PHP-FPM

Для конкретного пула (например, `/etc/php/8.3/fpm/pool.d/www.conf`):
```ini
php_admin_value[post_max_size] = 50M
php_admin_value[upload_max_filesize] = 50M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300
```

После изменений перезапустить PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
# или
sudo service php-fpm restart
```

### 3. Docker

В `Dockerfile` или `docker-compose.yml`:

```dockerfile
# В Dockerfile
RUN echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini
```

Или через переменные окружения в `docker-compose.yml`:
```yaml
services:
  app:
    environment:
      PHP_INI_SCAN_DIR: "/usr/local/etc/php/conf.d:/usr/local/etc/php/conf.d/zzz-custom.ini"
```

### 4. Laravel конфигурация

В `config/media.php` уже настроено:
```php
'max_upload_mb' => env('MEDIA_MAX_UPLOAD_MB', 25),
```

Убедитесь, что PHP лимиты >= Laravel лимита:
- `post_max_size` >= `MEDIA_MAX_UPLOAD_MB`
- `upload_max_filesize` >= `MEDIA_MAX_UPLOAD_MB`

## Проверка

После изменения настроек проверьте:
```bash
php -i | findstr "post_max_size upload_max_filesize"
```

Или через веб-интерфейс (если доступен `phpinfo()`).

## Важные замечания

1. **`post_max_size`** должен быть >= **`upload_max_filesize`**
2. **`post_max_size`** включает весь POST-запрос (файл + другие поля формы)
3. Для больших файлов также может потребоваться увеличить:
   - `memory_limit` - лимит памяти PHP
   - `max_execution_time` - максимальное время выполнения скрипта
   - `max_input_time` - максимальное время обработки входных данных

## Ошибка в ответе API

После настройки ошибка будет содержать информацию о текущих лимитах:
```json
{
    "type": "https://stupidcms.dev/problems/validation-error",
    "title": "Validation Error",
    "status": 422,
    "code": "VALIDATION_ERROR",
    "detail": "The uploaded file exceeds the maximum allowed size.",
    "meta": {
        "max_size": "50M",
        "post_max_size": "50M",
        "upload_max_filesize": "50M",
        "error_type": "post_too_large"
    }
}
```

