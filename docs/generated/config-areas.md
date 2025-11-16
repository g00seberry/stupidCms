# Config Areas

## App
**ID:** `config_area:app`
**Path:** `config/app.php`

Configuration: App

### Meta
- **Keys:** `name`, `env`, `debug`, `url`, `timezone`, `locale`, `fallback_locale`, `faker_locale`, `cipher`, `key`, `previous_keys`, `maintenance`, `driver`, `store`
- **Sections:** `previous_keys`, `maintenance`

### Tags
`config`, `app`


---

## Auth
**ID:** `config_area:auth`
**Path:** `config/auth.php`

Configuration: Auth

### Meta
- **Keys:** `defaults`, `guard`, `passwords`, `guards`, `web`, `driver`, `provider`, `admin`, `api`, `providers`, `users`, `model`, `table`, `expire`, `throttle`, `password_timeout`
- **Sections:** `defaults`, `guards`, `web`, `admin`, `api`, `providers`, `users`, `passwords`

### Tags
`config`, `auth`


---

## Cache
**ID:** `config_area:cache`
**Path:** `config/cache.php`

Configuration: Cache

### Meta
- **Keys:** `default`, `stores`, `array`, `driver`, `serialize`, `database`, `connection`, `table`, `lock_connection`, `lock_table`, `file`, `path`, `lock_path`, `memcached`, `persistent_id`, `sasl`, `options`, `servers`, `host`, `port`, `weight`, `redis`, `dynamodb`, `key`, `secret`, `region`, `endpoint`, `octane`, `failover`, `prefix`
- **Sections:** `stores`, `array`, `database`, `file`, `memcached`, `sasl`, `options`, `servers`, `redis`, `dynamodb`, `octane`, `failover`

### Tags
`config`, `cache`


---

## Cors
**ID:** `config_area:cors`
**Path:** `config/cors.php`

Configuration: Cors

### Meta
- **Keys:** `paths`, `allowed_methods`, `allowed_origins`, `allowed_origins_patterns`, `allowed_headers`, `exposed_headers`, `max_age`, `supports_credentials`
- **Sections:** `paths`, `allowed_methods`, `allowed_origins_patterns`, `allowed_headers`, `exposed_headers`

### Tags
`config`, `cors`


---

## Database
**ID:** `config_area:database`
**Path:** `config/database.php`

Configuration: Database

### Meta
- **Keys:** `default`, `connections`, `sqlite`, `driver`, `url`, `database`, `prefix`, `foreign_key_constraints`, `busy_timeout`, `journal_mode`, `synchronous`, `transaction_mode`, `mysql`, `host`, `port`, `username`, `password`, `unix_socket`, `charset`, `collation`, `prefix_indexes`, `strict`, `engine`, `options`, `mariadb`, `pgsql`, `search_path`, `sslmode`, `sqlsrv`, `encrypt`, `trust_server_certificate`, `migrations`, `table`, `update_date_on_publish`, `redis`, `client`, `cluster`, `persistent`, `max_retries`, `backoff_algorithm`, `backoff_base`, `backoff_cap`, `cache`
- **Sections:** `connections`, `sqlite`, `mysql`, `mariadb`, `pgsql`, `sqlsrv`, `migrations`, `redis`, `options`, `default`, `cache`

### Tags
`config`, `database`


---

## Docs
**ID:** `config_area:docs`
**Path:** `config/docs.php`

Configuration: Docs

### Meta
- **Keys:** `types`, `model`, `path`, `namespace`, `id_prefix`, `description`, `example_id`, `domain_service`, `blade_view`, `config_area`, `concept`, `http_endpoint`, `output`, `markdown_dir`, `index_file`
- **Sections:** `types`, `model`, `domain_service`, `blade_view`, `config_area`, `concept`, `http_endpoint`, `output`

### Tags
`config`, `docs`


---

## Errors
**ID:** `config_area:errors`
**Path:** `config/errors.php`

Configuration: Errors

### Meta
- **Keys:** `kernel`, `enabled`, `types`, `uri`, `title`, `status`, `detail`, `mappings`, `builder`, `errors`, `sql`, `bindings`, `report`, `level`, `message`, `context`, `fallback`
- **Sections:** `kernel`, `types`, `mappings`, `report`, `fallback`

### Tags
`config`, `errors`


---

## Filesystems
**ID:** `config_area:filesystems`
**Path:** `config/filesystems.php`

Configuration: Filesystems

### Meta
- **Keys:** `default`, `disks`, `local`, `driver`, `root`, `serve`, `throw`, `report`, `public`, `url`, `visibility`, `media`, `s3`, `key`, `secret`, `region`, `bucket`, `endpoint`, `use_path_style_endpoint`, `links`
- **Sections:** `disks`, `local`, `public`, `media`, `s3`, `links`

### Tags
`config`, `filesystems`


---

## Jwt
**ID:** `config_area:jwt`
**Path:** `config/jwt.php`

Configuration: Jwt

### Meta
- **Keys:** `algo`, `access_ttl`, `refresh_ttl`, `leeway`, `secret`, `issuer`, `audience`, `cookies`, `access`, `refresh`, `domain`, `secure`, `samesite`, `path`
- **Sections:** `cookies`

### Tags
`config`, `jwt`


---

## Logging
**ID:** `config_area:logging`
**Path:** `config/logging.php`

Configuration: Logging

### Meta
- **Keys:** `default`, `deprecations`, `channel`, `trace`, `channels`, `stack`, `driver`, `ignore_exceptions`, `single`, `path`, `level`, `replace_placeholders`, `daily`, `days`, `slack`, `url`, `username`, `emoji`, `papertrail`, `handler`, `handler_with`, `host`, `port`, `connectionString`, `processors`, `stderr`, `stream`, `formatter`, `syslog`, `facility`, `errorlog`, `null`, `emergency`
- **Sections:** `deprecations`, `channels`, `stack`, `single`, `daily`, `slack`, `papertrail`, `handler_with`, `processors`, `stderr`, `syslog`, `errorlog`, `null`, `emergency`

### Tags
`config`, `logging`


---

## Mail
**ID:** `config_area:mail`
**Path:** `config/mail.php`

Configuration: Mail

### Meta
- **Keys:** `default`, `mailers`, `smtp`, `transport`, `scheme`, `url`, `host`, `port`, `username`, `password`, `timeout`, `local_domain`, `ses`, `postmark`, `message_stream_id`, `client`, `resend`, `sendmail`, `path`, `log`, `channel`, `array`, `failover`, `retry_after`, `roundrobin`, `from`, `address`, `name`
- **Sections:** `mailers`, `smtp`, `ses`, `postmark`, `client`, `resend`, `sendmail`, `log`, `array`, `failover`, `roundrobin`, `from`

### Tags
`config`, `mail`


---

## Media
**ID:** `config_area:media`
**Path:** `config/media.php`

Configuration: Media

### Meta
- **Keys:** `disk`, `max_upload_mb`, `allowed_mimes`, `variants`, `thumbnail`, `max`, `medium`, `signed_ttl`, `path_strategy`, `image`, `driver`, `quality`, `glide_driver`, `metadata`, `ffprobe`, `enabled`, `binary`
- **Sections:** `allowed_mimes`, `variants`, `thumbnail`, `medium`, `image`, `metadata`, `ffprobe`

### Tags
`config`, `media`


---

## Options
**ID:** `config_area:options`
**Path:** `config/options.php`

Configuration: Options

### Meta
- **Keys:** `allowed`, `site`
- **Sections:** `allowed`, `site`

### Tags
`config`, `options`


---

## Plugins
**ID:** `config_area:plugins`
**Path:** `config/plugins.php`

Configuration: Plugins

### Meta
- **Keys:** `path`, `manifest`, `auto_route_cache`
- **Sections:** `manifest`

### Tags
`config`, `plugins`


---

## Purifier
**ID:** `config_area:purifier`
**Path:** `config/purifier.php`

Configuration: Purifier

### Meta
- **Keys:** `settings`, `cms_default`, `HTML.Doctype`, `Cache.SerializerPath`, `HTML.AllowedElements`, `HTML.AllowedAttributes`, `URI.AllowedSchemes`, `http`, `https`, `mailto`, `HTML.SafeScripting`, `HTML.SafeEmbed`, `HTML.SafeObject`, `Attr.EnableID`, `AutoFormat.RemoveEmpty`, `AutoFormat.Linkify`, `AutoFormat.AutoParagraph`, `URI.DisableExternalResources`, `CSS.AllowedProperties`, `custom_definition`, `id`, `rev`, `debug`, `elements`
- **Sections:** `settings`, `cms_default`, `HTML.AllowedElements`, `HTML.AllowedAttributes`, `URI.AllowedSchemes`, `HTML.SafeScripting`, `CSS.AllowedProperties`, `custom_definition`, `elements`

### Tags
`config`, `purifier`


---

## Queue
**ID:** `config_area:queue`
**Path:** `config/queue.php`

Configuration: Queue

### Meta
- **Keys:** `default`, `connections`, `sync`, `driver`, `database`, `connection`, `table`, `queue`, `retry_after`, `after_commit`, `beanstalkd`, `host`, `block_for`, `sqs`, `key`, `secret`, `prefix`, `suffix`, `region`, `redis`, `deferred`, `background`, `failover`, `batching`, `failed`
- **Sections:** `connections`, `sync`, `database`, `beanstalkd`, `sqs`, `redis`, `deferred`, `background`, `failover`, `batching`, `failed`

### Tags
`config`, `queue`


---

## Scribe
**ID:** `config_area:scribe`
**Path:** `config/scribe.php`

Configuration: Scribe

### Meta
- **Keys:** `title`, `description`, `intro_text`, `base_url`, `routes`, `match`, `prefixes`, `domains`, `include`, `exclude`, `type`, `theme`, `static`, `output_path`, `laravel`, `add_routes`, `docs_url`, `assets_directory`, `middleware`, `external`, `html_attributes`, `try_it_out`, `enabled`, `use_csrf`, `csrf_url`, `auth`, `default`, `in`, `name`, `use_value`, `placeholder`, `extra_info`, `example_languages`, `postman`, `overrides`, `info.version`, `openapi`, `generators`, `groups`, `order`, `logo`, `last_updated`, `examples`, `faker_seed`, `models_source`, `strategies`, `metadata`, `headers`, `Content-Type`, `Accept`, `urlParameters`, `queryParameters`, `bodyParameters`, `responses`, `responseFields`, `database_connections_to_transact`, `fractal`, `serializer`
- **Sections:** `routes`, `match`, `prefixes`, `domains`, `include`, `exclude`, `static`, `laravel`, `middleware`, `external`, `html_attributes`, `try_it_out`, `auth`, `example_languages`, `postman`, `overrides`, `openapi`, `generators`, `groups`, `order`, `examples`, `models_source`, `strategies`, `metadata`, `headers`, `urlParameters`, `queryParameters`, `bodyParameters`, `responseFields`, `database_connections_to_transact`, `fractal`

### Tags
`config`, `scribe`


---

## Search
**ID:** `config_area:search`
**Path:** `config/search.php`

Configuration: Search

### Meta
- **Keys:** `enabled`, `client`, `hosts`, `username`, `password`, `verify_ssl`, `timeout`, `indexes`, `entries`, `read_alias`, `write_alias`, `name_prefix`, `settings`, `number_of_shards`, `number_of_replicas`, `analysis`, `filter`, `ru_stop`, `type`, `stopwords`, `ru_stemmer`, `language`, `en_stemmer`, `analyzer`, `ru_en`, `tokenizer`, `mappings`, `dynamic`, `properties`, `id`, `post_type`, `slug`, `title`, `excerpt`, `body_plain`, `terms`, `taxonomy`, `published_at`, `boost`, `batch`, `size`, `pagination`, `per_page`, `max_per_page`
- **Sections:** `client`, `indexes`, `entries`, `settings`, `analysis`, `filter`, `ru_stop`, `ru_stemmer`, `en_stemmer`, `analyzer`, `ru_en`, `mappings`, `properties`, `id`, `post_type`, `slug`, `title`, `excerpt`, `body_plain`, `terms`, `taxonomy`, `published_at`, `boost`, `batch`, `pagination`

### Tags
`config`, `search`


---

## Security
**ID:** `config_area:security`
**Path:** `config/security.php`

Configuration: Security

### Meta
- **Keys:** `csrf`, `cookie_name`, `ttl_hours`, `samesite`, `secure`, `domain`, `path`
- **Sections:** `csrf`

### Tags
`config`, `security`


---

## Services
**ID:** `config_area:services`
**Path:** `config/services.php`

Configuration: Services

### Meta
- **Keys:** `postmark`, `key`, `resend`, `ses`, `secret`, `region`, `slack`, `notifications`, `bot_user_oauth_token`, `channel`
- **Sections:** `postmark`, `resend`, `ses`, `slack`, `notifications`

### Tags
`config`, `services`


---

## Session
**ID:** `config_area:session`
**Path:** `config/session.php`

Configuration: Session

### Meta
- **Keys:** `driver`, `lifetime`, `expire_on_close`, `encrypt`, `files`, `connection`, `table`, `store`, `lottery`, `cookie`, `path`, `domain`, `secure`, `http_only`, `same_site`, `partitioned`
- **Sections:** `lottery`

### Tags
`config`, `session`


---

## Stupidcms
**ID:** `config_area:stupidcms`
**Path:** `config/stupidcms.php`

Configuration: Stupidcms

### Meta
- **Keys:** `reserved_routes`, `paths`, `prefixes`, `slug`, `default`, `delimiter`, `toLower`, `asciiOnly`, `maxLength`, `scheme`, `stopWords`, `reserved`, `schemes`, `ru_basic`, `map`, `а`, `б`, `в`, `г`, `д`, `е`, `ё`, `ж`, `з`, `и`, `й`, `к`, `л`, `м`, `н`, `о`, `п`, `р`, `с`, `т`, `у`, `ф`, `х`, `ц`, `ч`, `ш`, `щ`, `ъ`, `ы`, `ь`, `э`, `ю`, `я`, `exceptions`, `йога`, `Санкт-Петербург`
- **Sections:** `reserved_routes`, `paths`, `prefixes`, `slug`, `default`, `stopWords`, `reserved`, `schemes`, `ru_basic`, `map`, `exceptions`

### Tags
`config`, `stupidcms`


---

## View_templates
**ID:** `config_area:view_templates`
**Path:** `config/view_templates.php`

Configuration: View_templates

### Meta
- **Keys:** `default`

### Tags
`config`, `view_templates`


---
