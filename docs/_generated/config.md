# Configuration Reference

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:config` to update.

_Last generated: 2025-11-08 10:19:16_

## app

**File**: `config/app.php`

| Key | Value | Type |
|-----|-------|------|
| `name` | `Laravel` | string |
| `env` | `local` | string |
| `debug` | `true` | boolean |
| `url` | `http://localhost` | string |
| `frontend_url` | `http://localhost:3000` | string |
| `asset_url` | _null_ | NULL |
| `timezone` | `UTC` | string |
| `locale` | `en` | string |
| `fallback_locale` | `en` | string |
| `faker_locale` | `en_US` | string |
| `cipher` | `AES-256-CBC` | string |
| `key` | `base64:syBmy/tOlu2gHGftU2GxBjAhjFh7JSC/e0xjj2/A...` | string |
| `maintenance.driver` | `file` | string |
| `maintenance.store` | `database` | string |
| `providers.0` | `Illuminate\Auth\AuthServiceProvider` | string |
| `providers.1` | `Illuminate\Broadcasting\BroadcastServiceProvider` | string |
| `providers.2` | `Illuminate\Bus\BusServiceProvider` | string |
| `providers.3` | `Illuminate\Cache\CacheServiceProvider` | string |
| `providers.4` | `Illuminate\Foundation\Providers\ConsoleSupportS...` | string |
| `providers.5` | `Illuminate\Concurrency\ConcurrencyServiceProvider` | string |
| `providers.6` | `Illuminate\Cookie\CookieServiceProvider` | string |
| `providers.7` | `Illuminate\Database\DatabaseServiceProvider` | string |
| `providers.8` | `Illuminate\Encryption\EncryptionServiceProvider` | string |
| `providers.9` | `Illuminate\Filesystem\FilesystemServiceProvider` | string |
| `providers.10` | `Illuminate\Foundation\Providers\FoundationServi...` | string |
| `providers.11` | `Illuminate\Hashing\HashServiceProvider` | string |
| `providers.12` | `Illuminate\Mail\MailServiceProvider` | string |
| `providers.13` | `Illuminate\Notifications\NotificationServicePro...` | string |
| `providers.14` | `Illuminate\Pagination\PaginationServiceProvider` | string |
| `providers.15` | `Illuminate\Auth\Passwords\PasswordResetServiceP...` | string |
| `providers.16` | `Illuminate\Pipeline\PipelineServiceProvider` | string |
| `providers.17` | `Illuminate\Queue\QueueServiceProvider` | string |
| `providers.18` | `Illuminate\Redis\RedisServiceProvider` | string |
| `providers.19` | `Illuminate\Session\SessionServiceProvider` | string |
| `providers.20` | `Illuminate\Translation\TranslationServiceProvider` | string |
| `providers.21` | `Illuminate\Validation\ValidationServiceProvider` | string |
| `providers.22` | `Illuminate\View\ViewServiceProvider` | string |
| `providers.23` | `App\Providers\AppServiceProvider` | string |
| `providers.24` | `App\Providers\AuthServiceProvider` | string |
| `providers.25` | `App\Providers\RouteServiceProvider` | string |
| `providers.26` | `App\Providers\EntrySlugServiceProvider` | string |
| `providers.27` | `App\Providers\PathReservationServiceProvider` | string |
| `providers.28` | `App\Providers\ReservedRoutesServiceProvider` | string |
| `providers.29` | `App\Providers\SlugServiceProvider` | string |
| `aliases.App` | `Illuminate\Support\Facades\App` | string |
| `aliases.Arr` | `Illuminate\Support\Arr` | string |
| `aliases.Artisan` | `Illuminate\Support\Facades\Artisan` | string |
| `aliases.Auth` | `Illuminate\Support\Facades\Auth` | string |
| `aliases.Benchmark` | `Illuminate\Support\Benchmark` | string |
| `aliases.Blade` | `Illuminate\Support\Facades\Blade` | string |
| `aliases.Broadcast` | `Illuminate\Support\Facades\Broadcast` | string |
| `aliases.Bus` | `Illuminate\Support\Facades\Bus` | string |
| `aliases.Cache` | `Illuminate\Support\Facades\Cache` | string |
| `aliases.Concurrency` | `Illuminate\Support\Facades\Concurrency` | string |
| `aliases.Config` | `Illuminate\Support\Facades\Config` | string |
| `aliases.Context` | `Illuminate\Support\Facades\Context` | string |
| `aliases.Cookie` | `Illuminate\Support\Facades\Cookie` | string |
| `aliases.Crypt` | `Illuminate\Support\Facades\Crypt` | string |
| `aliases.Date` | `Illuminate\Support\Facades\Date` | string |
| `aliases.DB` | `Illuminate\Support\Facades\DB` | string |
| `aliases.Eloquent` | `Illuminate\Database\Eloquent\Model` | string |
| `aliases.Event` | `Illuminate\Support\Facades\Event` | string |
| `aliases.File` | `Illuminate\Support\Facades\File` | string |
| `aliases.Gate` | `Illuminate\Support\Facades\Gate` | string |
| `aliases.Hash` | `Illuminate\Support\Facades\Hash` | string |
| `aliases.Http` | `Illuminate\Support\Facades\Http` | string |
| `aliases.Js` | `Illuminate\Support\Js` | string |
| `aliases.Lang` | `Illuminate\Support\Facades\Lang` | string |
| `aliases.Log` | `Illuminate\Support\Facades\Log` | string |
| `aliases.Mail` | `Illuminate\Support\Facades\Mail` | string |
| `aliases.Notification` | `Illuminate\Support\Facades\Notification` | string |
| `aliases.Number` | `Illuminate\Support\Number` | string |
| `aliases.Password` | `Illuminate\Support\Facades\Password` | string |
| `aliases.Process` | `Illuminate\Support\Facades\Process` | string |
| `aliases.Queue` | `Illuminate\Support\Facades\Queue` | string |
| `aliases.RateLimiter` | `Illuminate\Support\Facades\RateLimiter` | string |
| `aliases.Redirect` | `Illuminate\Support\Facades\Redirect` | string |
| `aliases.Request` | `Illuminate\Support\Facades\Request` | string |
| `aliases.Response` | `Illuminate\Support\Facades\Response` | string |
| `aliases.Route` | `Illuminate\Support\Facades\Route` | string |
| `aliases.Schedule` | `Illuminate\Support\Facades\Schedule` | string |
| `aliases.Schema` | `Illuminate\Support\Facades\Schema` | string |
| `aliases.Session` | `Illuminate\Support\Facades\Session` | string |
| `aliases.Storage` | `Illuminate\Support\Facades\Storage` | string |
| `aliases.Str` | `Illuminate\Support\Str` | string |
| `aliases.Uri` | `Illuminate\Support\Uri` | string |
| `aliases.URL` | `Illuminate\Support\Facades\URL` | string |
| `aliases.Validator` | `Illuminate\Support\Facades\Validator` | string |
| `aliases.View` | `Illuminate\Support\Facades\View` | string |
| `aliases.Vite` | `Illuminate\Support\Facades\Vite` | string |

## auth

**File**: `config/auth.php`

| Key | Value | Type |
|-----|-------|------|
| `defaults.guard` | `web` | string |
| `defaults.passwords` | `users` | string |
| `guards.web.driver` | `session` | string |
| `guards.web.provider` | `users` | string |
| `guards.admin.driver` | `session` | string |
| `guards.admin.provider` | `users` | string |
| `providers.users.driver` | `eloquent` | string |
| `providers.users.model` | `App\Models\User` | string |
| `passwords.users.provider` | `users` | string |
| `passwords.users.table` | `password_reset_tokens` | string |
| `passwords.users.expire` | `60` | integer |
| `passwords.users.throttle` | `60` | integer |
| `password_timeout` | `10800` | integer |

## cache

**File**: `config/cache.php`

| Key | Value | Type |
|-----|-------|------|
| `default` | `database` | string |
| `stores.array.driver` | `array` | string |
| `stores.array.serialize` | `false` | boolean |
| `stores.session.driver` | `session` | string |
| `stores.session.key` | `_cache` | string |
| `stores.database.driver` | `database` | string |
| `stores.database.connection` | _null_ | NULL |
| `stores.database.table` | `cache` | string |
| `stores.database.lock_connection` | _null_ | NULL |
| `stores.database.lock_table` | _null_ | NULL |
| `stores.file.driver` | `file` | string |
| `stores.file.path` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `stores.file.lock_path` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `stores.memcached.driver` | `memcached` | string |
| `stores.memcached.persistent_id` | _null_ | NULL |
| `stores.memcached.sasl.0` | _null_ | NULL |
| `stores.memcached.sasl.1` | _null_ | NULL |
| `stores.memcached.servers.0.host` | `127.0.0.1` | string |
| `stores.memcached.servers.0.port` | `11211` | integer |
| `stores.memcached.servers.0.weight` | `100` | integer |
| `stores.redis.driver` | `redis` | string |
| `stores.redis.connection` | `cache` | string |
| `stores.redis.lock_connection` | `default` | string |
| `stores.dynamodb.driver` | `dynamodb` | string |
| `stores.dynamodb.key` | `` | string |
| `stores.dynamodb.secret` | `` | string |
| `stores.dynamodb.region` | `us-east-1` | string |
| `stores.dynamodb.table` | `cache` | string |
| `stores.dynamodb.endpoint` | _null_ | NULL |
| `stores.octane.driver` | `octane` | string |
| `stores.failover.driver` | `failover` | string |
| `stores.failover.stores.0` | `database` | string |
| `stores.failover.stores.1` | `array` | string |
| `prefix` | `laravel-cache-` | string |

## cors

**File**: `config/cors.php`

| Key | Value | Type |
|-----|-------|------|
| `paths.0` | `api/*` | string |
| `allowed_methods.0` | `*` | string |
| `allowed_origins.0` | `https://app.example.com` | string |
| `allowed_headers.0` | `*` | string |
| `max_age` | `600` | integer |
| `supports_credentials` | `true` | boolean |

## database

**File**: `config/database.php`

| Key | Value | Type |
|-----|-------|------|
| `default` | `mysql` | string |
| `connections.sqlite.driver` | `sqlite` | string |
| `connections.sqlite.url` | _null_ | NULL |
| `connections.sqlite.database` | `cakes3` | string |
| `connections.sqlite.prefix` | `` | string |
| `connections.sqlite.foreign_key_constraints` | `true` | boolean |
| `connections.sqlite.busy_timeout` | _null_ | NULL |
| `connections.sqlite.journal_mode` | _null_ | NULL |
| `connections.sqlite.synchronous` | _null_ | NULL |
| `connections.sqlite.transaction_mode` | `DEFERRED` | string |
| `connections.mysql.driver` | `mysql` | string |
| `connections.mysql.url` | _null_ | NULL |
| `connections.mysql.host` | `127.0.0.1` | string |
| `connections.mysql.port` | `3306` | string |
| `connections.mysql.database` | `cakes3` | string |
| `connections.mysql.username` | `root` | string |
| `connections.mysql.password` | `` | string |
| `connections.mysql.unix_socket` | `` | string |
| `connections.mysql.charset` | `utf8mb4` | string |
| `connections.mysql.collation` | `utf8mb4_unicode_ci` | string |
| `connections.mysql.prefix` | `` | string |
| `connections.mysql.prefix_indexes` | `true` | boolean |
| `connections.mysql.strict` | `true` | boolean |
| `connections.mysql.engine` | _null_ | NULL |
| `connections.mariadb.driver` | `mariadb` | string |
| `connections.mariadb.url` | _null_ | NULL |
| `connections.mariadb.host` | `127.0.0.1` | string |
| `connections.mariadb.port` | `3306` | string |
| `connections.mariadb.database` | `cakes3` | string |
| `connections.mariadb.username` | `root` | string |
| `connections.mariadb.password` | `` | string |
| `connections.mariadb.unix_socket` | `` | string |
| `connections.mariadb.charset` | `utf8mb4` | string |
| `connections.mariadb.collation` | `utf8mb4_unicode_ci` | string |
| `connections.mariadb.prefix` | `` | string |
| `connections.mariadb.prefix_indexes` | `true` | boolean |
| `connections.mariadb.strict` | `true` | boolean |
| `connections.mariadb.engine` | _null_ | NULL |
| `connections.pgsql.driver` | `pgsql` | string |
| `connections.pgsql.url` | _null_ | NULL |
| `connections.pgsql.host` | `127.0.0.1` | string |
| `connections.pgsql.port` | `3306` | string |
| `connections.pgsql.database` | `cakes3` | string |
| `connections.pgsql.username` | `root` | string |
| `connections.pgsql.password` | `` | string |
| `connections.pgsql.charset` | `utf8` | string |
| `connections.pgsql.prefix` | `` | string |
| `connections.pgsql.prefix_indexes` | `true` | boolean |
| `connections.pgsql.search_path` | `public` | string |
| `connections.pgsql.sslmode` | `prefer` | string |
| `connections.sqlsrv.driver` | `sqlsrv` | string |
| `connections.sqlsrv.url` | _null_ | NULL |
| `connections.sqlsrv.host` | `127.0.0.1` | string |
| `connections.sqlsrv.port` | `3306` | string |
| `connections.sqlsrv.database` | `cakes3` | string |
| `connections.sqlsrv.username` | `root` | string |
| `connections.sqlsrv.password` | `` | string |
| `connections.sqlsrv.charset` | `utf8` | string |
| `connections.sqlsrv.prefix` | `` | string |
| `connections.sqlsrv.prefix_indexes` | `true` | boolean |
| `migrations.table` | `migrations` | string |
| `migrations.update_date_on_publish` | `true` | boolean |
| `redis.client` | `phpredis` | string |
| `redis.options.cluster` | `redis` | string |
| `redis.options.prefix` | `laravel-database-` | string |
| `redis.options.persistent` | `false` | boolean |
| `redis.default.url` | _null_ | NULL |
| `redis.default.host` | `127.0.0.1` | string |
| `redis.default.username` | _null_ | NULL |
| `redis.default.password` | _null_ | NULL |
| `redis.default.port` | `6379` | string |
| `redis.default.database` | `0` | string |
| `redis.default.max_retries` | `3` | integer |
| `redis.default.backoff_algorithm` | `decorrelated_jitter` | string |
| `redis.default.backoff_base` | `100` | integer |
| `redis.default.backoff_cap` | `1000` | integer |
| `redis.cache.url` | _null_ | NULL |
| `redis.cache.host` | `127.0.0.1` | string |
| `redis.cache.username` | _null_ | NULL |
| `redis.cache.password` | _null_ | NULL |
| `redis.cache.port` | `6379` | string |
| `redis.cache.database` | `1` | string |
| `redis.cache.max_retries` | `3` | integer |
| `redis.cache.backoff_algorithm` | `decorrelated_jitter` | string |
| `redis.cache.backoff_base` | `100` | integer |
| `redis.cache.backoff_cap` | `1000` | integer |

## filesystems

**File**: `config/filesystems.php`

| Key | Value | Type |
|-----|-------|------|
| `default` | `local` | string |
| `disks.local.driver` | `local` | string |
| `disks.local.root` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `disks.local.serve` | `true` | boolean |
| `disks.local.throw` | `false` | boolean |
| `disks.local.report` | `false` | boolean |
| `disks.public.driver` | `local` | string |
| `disks.public.root` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `disks.public.url` | `http://localhost/storage` | string |
| `disks.public.visibility` | `public` | string |
| `disks.public.throw` | `false` | boolean |
| `disks.public.report` | `false` | boolean |
| `disks.s3.driver` | `s3` | string |
| `disks.s3.key` | `` | string |
| `disks.s3.secret` | `` | string |
| `disks.s3.region` | `us-east-1` | string |
| `disks.s3.bucket` | `` | string |
| `disks.s3.url` | _null_ | NULL |
| `disks.s3.endpoint` | _null_ | NULL |
| `disks.s3.use_path_style_endpoint` | `false` | boolean |
| `disks.s3.throw` | `false` | boolean |
| `disks.s3.report` | `false` | boolean |
| `links.C:\Users\dattebayo\Desktop\proj\stupidCms\public\storage` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |

## jwt

**File**: `config/jwt.php`

| Key | Value | Type |
|-----|-------|------|
| `algo` | `HS256` | string |
| `access_ttl` | `900` | integer |
| `refresh_ttl` | `2592000` | integer |
| `leeway` | `5` | integer |
| `secret` | `Sg9Q0lCyrSwmOQA1H/LcY4bBVuTnXSQV7Jhlge/WpFI=` | string |
| `issuer` | `https://stupidcms.local` | string |
| `audience` | `stupidcms-api` | string |
| `cookies.access` | `cms_at` | string |
| `cookies.refresh` | `cms_rt` | string |
| `cookies.domain` | _null_ | NULL |
| `cookies.secure` | `false` | boolean |
| `cookies.samesite` | `Strict` | string |
| `cookies.path` | `/` | string |

## logging

**File**: `config/logging.php`

| Key | Value | Type |
|-----|-------|------|
| `default` | `stack` | string |
| `deprecations.channel` | _null_ | NULL |
| `deprecations.trace` | `false` | boolean |
| `channels.stack.driver` | `stack` | string |
| `channels.stack.channels.0` | `single` | string |
| `channels.stack.ignore_exceptions` | `false` | boolean |
| `channels.single.driver` | `single` | string |
| `channels.single.path` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `channels.single.level` | `debug` | string |
| `channels.single.replace_placeholders` | `true` | boolean |
| `channels.daily.driver` | `daily` | string |
| `channels.daily.path` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `channels.daily.level` | `debug` | string |
| `channels.daily.days` | `14` | integer |
| `channels.daily.replace_placeholders` | `true` | boolean |
| `channels.slack.driver` | `slack` | string |
| `channels.slack.url` | _null_ | NULL |
| `channels.slack.username` | `Laravel Log` | string |
| `channels.slack.emoji` | `:boom:` | string |
| `channels.slack.level` | `debug` | string |
| `channels.slack.replace_placeholders` | `true` | boolean |
| `channels.papertrail.driver` | `monolog` | string |
| `channels.papertrail.level` | `debug` | string |
| `channels.papertrail.handler` | `Monolog\Handler\SyslogUdpHandler` | string |
| `channels.papertrail.handler_with.host` | _null_ | NULL |
| `channels.papertrail.handler_with.port` | _null_ | NULL |
| `channels.papertrail.handler_with.connectionString` | `tls://:` | string |
| `channels.papertrail.processors.0` | `Monolog\Processor\PsrLogMessageProcessor` | string |
| `channels.stderr.driver` | `monolog` | string |
| `channels.stderr.level` | `debug` | string |
| `channels.stderr.handler` | `Monolog\Handler\StreamHandler` | string |
| `channels.stderr.handler_with.stream` | `php://stderr` | string |
| `channels.stderr.formatter` | _null_ | NULL |
| `channels.stderr.processors.0` | `Monolog\Processor\PsrLogMessageProcessor` | string |
| `channels.syslog.driver` | `syslog` | string |
| `channels.syslog.level` | `debug` | string |
| `channels.syslog.facility` | `8` | integer |
| `channels.syslog.replace_placeholders` | `true` | boolean |
| `channels.errorlog.driver` | `errorlog` | string |
| `channels.errorlog.level` | `debug` | string |
| `channels.errorlog.replace_placeholders` | `true` | boolean |
| `channels.null.driver` | `monolog` | string |
| `channels.null.handler` | `Monolog\Handler\NullHandler` | string |
| `channels.emergency.path` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |

## mail

**File**: `config/mail.php`

| Key | Value | Type |
|-----|-------|------|
| `default` | `log` | string |
| `mailers.smtp.transport` | `smtp` | string |
| `mailers.smtp.scheme` | _null_ | NULL |
| `mailers.smtp.url` | _null_ | NULL |
| `mailers.smtp.host` | `127.0.0.1` | string |
| `mailers.smtp.port` | `2525` | string |
| `mailers.smtp.username` | _null_ | NULL |
| `mailers.smtp.password` | _null_ | NULL |
| `mailers.smtp.timeout` | _null_ | NULL |
| `mailers.smtp.local_domain` | `localhost` | string |
| `mailers.ses.transport` | `ses` | string |
| `mailers.postmark.transport` | `postmark` | string |
| `mailers.resend.transport` | `resend` | string |
| `mailers.sendmail.transport` | `sendmail` | string |
| `mailers.sendmail.path` | `/usr/sbin/sendmail -bs -i` | string |
| `mailers.log.transport` | `log` | string |
| `mailers.log.channel` | _null_ | NULL |
| `mailers.array.transport` | `array` | string |
| `mailers.failover.transport` | `failover` | string |
| `mailers.failover.mailers.0` | `smtp` | string |
| `mailers.failover.mailers.1` | `log` | string |
| `mailers.failover.retry_after` | `60` | integer |
| `mailers.roundrobin.transport` | `roundrobin` | string |
| `mailers.roundrobin.mailers.0` | `ses` | string |
| `mailers.roundrobin.mailers.1` | `postmark` | string |
| `mailers.roundrobin.retry_after` | `60` | integer |
| `from.address` | `hello@example.com` | string |
| `from.name` | `Laravel` | string |
| `markdown.theme` | `default` | string |
| `markdown.paths.0` | `C:\Users\dattebayo\Desktop\proj\stupidCms\resou...` | string |

## options

**File**: `config/options.php`

| Key | Value | Type |
|-----|-------|------|
| `allowed.site.0` | `home_entry_id` | string |

## purifier

**File**: `config/purifier.php`

| Key | Value | Type |
|-----|-------|------|
| `encoding` | `UTF-8` | string |
| `finalize` | `true` | boolean |
| `ignoreNonStrings` | `false` | boolean |
| `cachePath` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `cacheFileMode` | `493` | integer |
| `settings.cms_default.HTML.Doctype` | `HTML 4.01 Transitional` | string |
| `settings.cms_default.Cache.SerializerPath` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `settings.cms_default.HTML.AllowedElements.0` | `a` | string |
| `settings.cms_default.HTML.AllowedElements.1` | `abbr` | string |
| `settings.cms_default.HTML.AllowedElements.2` | `b` | string |
| `settings.cms_default.HTML.AllowedElements.3` | `blockquote` | string |
| `settings.cms_default.HTML.AllowedElements.4` | `br` | string |
| `settings.cms_default.HTML.AllowedElements.5` | `code` | string |
| `settings.cms_default.HTML.AllowedElements.6` | `em` | string |
| `settings.cms_default.HTML.AllowedElements.7` | `i` | string |
| `settings.cms_default.HTML.AllowedElements.8` | `hr` | string |
| `settings.cms_default.HTML.AllowedElements.9` | `img` | string |
| `settings.cms_default.HTML.AllowedElements.10` | `li` | string |
| `settings.cms_default.HTML.AllowedElements.11` | `ol` | string |
| `settings.cms_default.HTML.AllowedElements.12` | `p` | string |
| `settings.cms_default.HTML.AllowedElements.13` | `pre` | string |
| `settings.cms_default.HTML.AllowedElements.14` | `s` | string |
| `settings.cms_default.HTML.AllowedElements.15` | `small` | string |
| `settings.cms_default.HTML.AllowedElements.16` | `strong` | string |
| `settings.cms_default.HTML.AllowedElements.17` | `sub` | string |
| `settings.cms_default.HTML.AllowedElements.18` | `sup` | string |
| `settings.cms_default.HTML.AllowedElements.19` | `u` | string |
| `settings.cms_default.HTML.AllowedElements.20` | `ul` | string |
| `settings.cms_default.HTML.AllowedElements.21` | `h1` | string |
| `settings.cms_default.HTML.AllowedElements.22` | `h2` | string |
| `settings.cms_default.HTML.AllowedElements.23` | `h3` | string |
| `settings.cms_default.HTML.AllowedElements.24` | `h4` | string |
| `settings.cms_default.HTML.AllowedElements.25` | `h5` | string |
| `settings.cms_default.HTML.AllowedElements.26` | `h6` | string |
| `settings.cms_default.HTML.AllowedElements.27` | `div` | string |
| `settings.cms_default.HTML.AllowedElements.28` | `span` | string |
| `settings.cms_default.HTML.AllowedElements.29` | `figure` | string |
| `settings.cms_default.HTML.AllowedElements.30` | `figcaption` | string |
| `settings.cms_default.HTML.AllowedAttributes.0` | `a.href` | string |
| `settings.cms_default.HTML.AllowedAttributes.1` | `a.title` | string |
| `settings.cms_default.HTML.AllowedAttributes.2` | `a.target` | string |
| `settings.cms_default.HTML.AllowedAttributes.3` | `a.rel` | string |
| `settings.cms_default.HTML.AllowedAttributes.4` | `img.src` | string |
| `settings.cms_default.HTML.AllowedAttributes.5` | `img.alt` | string |
| `settings.cms_default.HTML.AllowedAttributes.6` | `img.title` | string |
| `settings.cms_default.HTML.AllowedAttributes.7` | `img.width` | string |
| `settings.cms_default.HTML.AllowedAttributes.8` | `img.height` | string |
| `settings.cms_default.URI.AllowedSchemes.http` | `true` | boolean |
| `settings.cms_default.URI.AllowedSchemes.https` | `true` | boolean |
| `settings.cms_default.URI.AllowedSchemes.mailto` | `true` | boolean |
| `settings.cms_default.HTML.SafeEmbed` | `false` | boolean |
| `settings.cms_default.HTML.SafeObject` | `false` | boolean |
| `settings.cms_default.Attr.EnableID` | `false` | boolean |
| `settings.cms_default.AutoFormat.RemoveEmpty` | `true` | boolean |
| `settings.cms_default.AutoFormat.Linkify` | `false` | boolean |
| `settings.cms_default.AutoFormat.AutoParagraph` | `false` | boolean |
| `settings.cms_default.URI.DisableExternalResources` | `false` | boolean |
| `settings.custom_definition.id` | `cms_default_html5` | string |
| `settings.custom_definition.rev` | `1` | integer |
| `settings.custom_definition.debug` | `false` | boolean |
| `settings.custom_definition.elements.0.0` | `figure` | string |
| `settings.custom_definition.elements.0.1` | `Block` | string |
| `settings.custom_definition.elements.0.2` | `Optional: (figcaption, Flow) | (Flow, figcaptio...` | string |
| `settings.custom_definition.elements.0.3` | `Common` | string |
| `settings.custom_definition.elements.1.0` | `figcaption` | string |
| `settings.custom_definition.elements.1.1` | `Inline` | string |
| `settings.custom_definition.elements.1.2` | `Flow` | string |
| `settings.custom_definition.elements.1.3` | `Common` | string |

## queue

**File**: `config/queue.php`

| Key | Value | Type |
|-----|-------|------|
| `default` | `database` | string |
| `connections.sync.driver` | `sync` | string |
| `connections.database.driver` | `database` | string |
| `connections.database.connection` | _null_ | NULL |
| `connections.database.table` | `jobs` | string |
| `connections.database.queue` | `default` | string |
| `connections.database.retry_after` | `90` | integer |
| `connections.database.after_commit` | `false` | boolean |
| `connections.beanstalkd.driver` | `beanstalkd` | string |
| `connections.beanstalkd.host` | `localhost` | string |
| `connections.beanstalkd.queue` | `default` | string |
| `connections.beanstalkd.retry_after` | `90` | integer |
| `connections.beanstalkd.block_for` | `0` | integer |
| `connections.beanstalkd.after_commit` | `false` | boolean |
| `connections.sqs.driver` | `sqs` | string |
| `connections.sqs.key` | `` | string |
| `connections.sqs.secret` | `` | string |
| `connections.sqs.prefix` | `https://sqs.us-east-1.amazonaws.com/your-accoun...` | string |
| `connections.sqs.queue` | `default` | string |
| `connections.sqs.suffix` | _null_ | NULL |
| `connections.sqs.region` | `us-east-1` | string |
| `connections.sqs.after_commit` | `false` | boolean |
| `connections.redis.driver` | `redis` | string |
| `connections.redis.connection` | `default` | string |
| `connections.redis.queue` | `default` | string |
| `connections.redis.retry_after` | `90` | integer |
| `connections.redis.block_for` | _null_ | NULL |
| `connections.redis.after_commit` | `false` | boolean |
| `connections.deferred.driver` | `deferred` | string |
| `connections.failover.driver` | `failover` | string |
| `connections.failover.connections.0` | `database` | string |
| `connections.failover.connections.1` | `deferred` | string |
| `connections.background.driver` | `background` | string |
| `batching.database` | `mysql` | string |
| `batching.table` | `job_batches` | string |
| `failed.driver` | `database-uuids` | string |
| `failed.database` | `mysql` | string |
| `failed.table` | `failed_jobs` | string |

## security

**File**: `config/security.php`

| Key | Value | Type |
|-----|-------|------|
| `csrf.cookie_name` | `cms_csrf` | string |
| `csrf.ttl_hours` | `12` | integer |
| `csrf.samesite` | `Strict` | string |
| `csrf.secure` | `false` | boolean |
| `csrf.domain` | _null_ | NULL |
| `csrf.path` | `/` | string |

## services

**File**: `config/services.php`

| Key | Value | Type |
|-----|-------|------|
| `postmark.key` | _null_ | NULL |
| `resend.key` | _null_ | NULL |
| `ses.key` | `` | string |
| `ses.secret` | `` | string |
| `ses.region` | `us-east-1` | string |
| `slack.notifications.bot_user_oauth_token` | _null_ | NULL |
| `slack.notifications.channel` | _null_ | NULL |

## session

**File**: `config/session.php`

| Key | Value | Type |
|-----|-------|------|
| `driver` | `database` | string |
| `lifetime` | `120` | integer |
| `expire_on_close` | `false` | boolean |
| `encrypt` | `false` | boolean |
| `files` | `C:\Users\dattebayo\Desktop\proj\stupidCms\stora...` | string |
| `connection` | _null_ | NULL |
| `table` | `sessions` | string |
| `store` | _null_ | NULL |
| `lottery.0` | `2` | integer |
| `lottery.1` | `100` | integer |
| `cookie` | `laravel-session` | string |
| `path` | `/` | string |
| `domain` | _null_ | NULL |
| `secure` | _null_ | NULL |
| `http_only` | `true` | boolean |
| `same_site` | `lax` | string |
| `partitioned` | `false` | boolean |

## stupidcms

**File**: `config/stupidcms.php`

| Key | Value | Type |
|-----|-------|------|
| `reserved_routes.paths.0` | `admin` | string |
| `reserved_routes.prefixes.0` | `admin` | string |
| `reserved_routes.prefixes.1` | `api` | string |
| `slug.default.delimiter` | `-` | string |
| `slug.default.toLower` | `true` | boolean |
| `slug.default.asciiOnly` | `true` | boolean |
| `slug.default.maxLength` | `120` | integer |
| `slug.default.scheme` | `ru_basic` | string |
| `slug.default.stopWords.0` | `и` | string |
| `slug.default.stopWords.1` | `в` | string |
| `slug.default.stopWords.2` | `на` | string |
| `slug.schemes.ru_basic.map.а` | `a` | string |
| `slug.schemes.ru_basic.map.б` | `b` | string |
| `slug.schemes.ru_basic.map.в` | `v` | string |
| `slug.schemes.ru_basic.map.г` | `g` | string |
| `slug.schemes.ru_basic.map.д` | `d` | string |
| `slug.schemes.ru_basic.map.е` | `e` | string |
| `slug.schemes.ru_basic.map.ё` | `e` | string |
| `slug.schemes.ru_basic.map.ж` | `zh` | string |
| `slug.schemes.ru_basic.map.з` | `z` | string |
| `slug.schemes.ru_basic.map.и` | `i` | string |
| `slug.schemes.ru_basic.map.й` | `i` | string |
| `slug.schemes.ru_basic.map.к` | `k` | string |
| `slug.schemes.ru_basic.map.л` | `l` | string |
| `slug.schemes.ru_basic.map.м` | `m` | string |
| `slug.schemes.ru_basic.map.н` | `n` | string |
| `slug.schemes.ru_basic.map.о` | `o` | string |
| `slug.schemes.ru_basic.map.п` | `p` | string |
| `slug.schemes.ru_basic.map.р` | `r` | string |
| `slug.schemes.ru_basic.map.с` | `s` | string |
| `slug.schemes.ru_basic.map.т` | `t` | string |
| `slug.schemes.ru_basic.map.у` | `u` | string |
| `slug.schemes.ru_basic.map.ф` | `f` | string |
| `slug.schemes.ru_basic.map.х` | `kh` | string |
| `slug.schemes.ru_basic.map.ц` | `c` | string |
| `slug.schemes.ru_basic.map.ч` | `ch` | string |
| `slug.schemes.ru_basic.map.ш` | `sh` | string |
| `slug.schemes.ru_basic.map.щ` | `shch` | string |
| `slug.schemes.ru_basic.map.ъ` | `` | string |
| `slug.schemes.ru_basic.map.ы` | `y` | string |
| `slug.schemes.ru_basic.map.ь` | `` | string |
| `slug.schemes.ru_basic.map.э` | `e` | string |
| `slug.schemes.ru_basic.map.ю` | `yu` | string |
| `slug.schemes.ru_basic.map.я` | `ya` | string |

## view_templates

**File**: `config/view_templates.php`

| Key | Value | Type |
|-----|-------|------|
| `default` | `pages.show` | string |
| `override_prefix` | `pages.overrides.` | string |
| `type_prefix` | `pages.types.` | string |

## Environment Variables

See `.env.example` for available environment variables.

Key config variables:

```env
APP_NAME="stupidCms"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.stupidcms.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=stupidcms

JWT_SECRET=<secret>
JWT_ALGO=HS256

ELASTICSEARCH_ENABLED=true
ELASTICSEARCH_HOSTS=localhost:9200
```
