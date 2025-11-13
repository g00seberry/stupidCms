# Models

## Audit
**ID:** `model:App\Models\Audit`
**Path:** `app/Models/Audit.php`

Audit model

### Meta
- **Table:** `audits`
- **Casts:** `diff_json` => `array`, `meta` => `array`
- **Relations:**
  - `user`: belongsTo → `App\Models\User`

### Tags
`audit`


---

## Entry
**ID:** `model:App\Models\Entry`
**Path:** `app/Models/Entry.php`

Entry model

### Meta
- **Table:** `entries`
- **Casts:** `data_json` => `array`, `seo_json` => `array`, `published_at` => `datetime`
- **Relations:**
  - `postType`: belongsTo → `App\Models\PostType`
  - `author`: belongsTo → `App\Models\User`
  - `slugs`: hasMany → `App\Models\EntrySlug`
  - `terms`: belongsToMany → `App\Models\Term`
  - `media`: belongsToMany → `App\Models\Media`
- **Factory:** `Database\Factories\EntryFactory`

### Tags
`entry`


---

## EntryMedia
**ID:** `model:App\Models\EntryMedia`
**Path:** `app/Models/EntryMedia.php`

EntryMedia model

### Meta
- **Table:** `entry_media`
- **Casts:** `order` => `integer`

### Tags
`entrymedia`


---

## EntrySlug
**ID:** `model:App\Models\EntrySlug`
**Path:** `app/Models/EntrySlug.php`

EntrySlug model

### Meta
- **Table:** `entry_slugs`
- **Casts:** `is_current` => `boolean`, `created_at` => `datetime`
- **Relations:**
  - `entry`: belongsTo → `App\Models\Entry`

### Tags
`entryslug`


---

## Media
**ID:** `model:App\Models\Media`
**Path:** `app/Models/Media.php`

Media model

### Meta
- **Table:** `media`
- **Casts:** `exif_json` => `array`, `deleted_at` => `datetime`
- **Relations:**
  - `variants`: hasMany → `App\Models\MediaVariant`
  - `entries`: belongsToMany → `App\Models\Entry`

### Tags
`media`


---

## MediaVariant
**ID:** `model:App\Models\MediaVariant`
**Path:** `app/Models/MediaVariant.php`

MediaVariant model

### Meta
- **Table:** `media_variants`
- **Relations:**
  - `media`: belongsTo → `App\Models\Media`

### Tags
`mediavariant`


---

## Option
**ID:** `model:App\Models\Option`
**Path:** `app/Models/Option.php`

Option model

### Meta
- **Table:** `options`
- **Fillable:** `namespace`, `key`, `value_json`, `description`
- **Guarded:** `*`
- **Casts:** `value_json` => `App\Casts\AsJsonValue`

### Tags
`option`


---

## Outbox
**ID:** `model:App\Models\Outbox`
**Path:** `app/Models/Outbox.php`

Outbox model

### Meta
- **Table:** `outboxes`
- **Casts:** `payload_json` => `array`, `attempts` => `integer`, `available_at` => `datetime`

### Tags
`outbox`


---

## Plugin
**ID:** `model:App\Models\Plugin`
**Path:** `app/Models/Plugin.php`

Plugin model

### Meta
- **Table:** `plugins`
- **Casts:** `enabled` => `boolean`, `meta_json` => `array`, `last_synced_at` => `immutable_datetime`

### Tags
`plugin`


---

## PostType
**ID:** `model:App\Models\PostType`
**Path:** `app/Models/PostType.php`

PostType model

### Meta
- **Table:** `post_types`
- **Fillable:** `slug`, `name`, `options_json`
- **Guarded:** `*`
- **Casts:** `options_json` => `array`
- **Relations:**
  - `entries`: hasMany → `App\Models\Entry`
- **Factory:** `Database\Factories\PostTypeFactory`

### Tags
`posttype`


---

## Redirect
**ID:** `model:App\Models\Redirect`
**Path:** `app/Models/Redirect.php`

Redirect model

### Meta
- **Table:** `redirects`

### Tags
`redirect`


---

## RefreshToken
**ID:** `model:App\Models\RefreshToken`
**Path:** `app/Models/RefreshToken.php`

RefreshToken model for tracking JWT refresh tokens.

### Meta
- **Table:** `refresh_tokens`
- **Fillable:** `user_id`, `jti`, `expires_at`, `used_at`, `revoked_at`, `parent_jti`
- **Guarded:** `*`
- **Casts:** `expires_at` => `datetime`, `used_at` => `datetime`, `revoked_at` => `datetime`
- **Relations:**
  - `user`: belongsTo → `App\Models\User`

### Tags
`refreshtoken`


---

## ReservedRoute
**ID:** `model:App\Models\ReservedRoute`
**Path:** `app/Models/ReservedRoute.php`

ReservedRoute model

### Meta
- **Table:** `reserved_routes`
- **Fillable:** `path`, `kind`, `source`
- **Guarded:** `*`
- **Casts:** `created_at` => `datetime`, `updated_at` => `datetime`

### Tags
`reservedroute`


---

## RouteReservation
**ID:** `model:App\Models\RouteReservation`
**Path:** `app/Models/RouteReservation.php`

Эта модель оставлена для обратной совместимости и указывает на таблицу reserved_routes.

### Meta
- **Table:** `reserved_routes`
- **Fillable:** `path`, `kind`, `source`
- **Guarded:** `*`
- **Casts:** `created_at` => `datetime`, `updated_at` => `datetime`

### Tags
`routereservation`


---

## Taxonomy
**ID:** `model:App\Models\Taxonomy`
**Path:** `app/Models/Taxonomy.php`

Taxonomy model

### Meta
- **Table:** `taxonomies`
- **Casts:** `options_json` => `array`, `hierarchical` => `boolean`
- **Relations:**
  - `terms`: hasMany → `App\Models\Term`
- **Factory:** `Database\Factories\TaxonomyFactory`

### Tags
`taxonomy`


---

## Term
**ID:** `model:App\Models\Term`
**Path:** `app/Models/Term.php`

Term model

### Meta
- **Table:** `terms`
- **Casts:** `meta_json` => `array`
- **Relations:**
  - `taxonomy`: belongsTo → `App\Models\Taxonomy`
  - `entries`: belongsToMany → `App\Models\Entry`
  - `ancestors`: belongsToMany → `App\Models\Term`
  - `descendants`: belongsToMany → `App\Models\Term`
  - `parent`: belongsToMany → `App\Models\Term`
  - `children`: belongsToMany → `App\Models\Term`
- **Factory:** `Database\Factories\TermFactory`

### Tags
`term`


---

## TermTree
**ID:** `model:App\Models\TermTree`
**Path:** `app/Models/TermTree.php`

TermTree model

### Meta
- **Table:** `term_tree`

### Tags
`termtree`


---

## User
**ID:** `model:App\Models\User`
**Path:** `app/Models/User.php`

User model

### Meta
- **Table:** `users`
- **Fillable:** `name`, `email`, `password`, `email_verified_at`
- **Guarded:** `is_admin`
- **Relations:**
  - `notifications`: morphMany → `App\Models\DatabaseNotification`

### Tags
`user`


---
