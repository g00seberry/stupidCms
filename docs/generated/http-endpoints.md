# HTTP Endpoints

## admin.v1.auth.current
**ID:** `http_endpoint:GET:/api/v1/admin/auth/current`
**Path:** `app/Http/Controllers/Auth/CurrentUserController.php`

GET /api/v1/admin/auth/current (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/auth/current`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `auth`, `current`


---

## admin.v1.blueprints.components.attach
**ID:** `http_endpoint:POST:/api/v1/admin/blueprints/{blueprint}/components`
**Path:** `app/Http/Controllers/Admin/BlueprintComponentController.php`

POST /api/v1/admin/blueprints/{blueprint}/components (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/blueprints/{blueprint}/components`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`, `components`


---

## admin.v1.blueprints.components.detach
**ID:** `http_endpoint:DELETE:/api/v1/admin/blueprints/{blueprint}/components/{component}`
**Path:** `app/Http/Controllers/Admin/BlueprintComponentController.php`

DELETE /api/v1/admin/blueprints/{blueprint}/components/{component} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/blueprints/{blueprint}/components/{component}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)
  - `component` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`, `components`


---

## admin.v1.blueprints.components.index
**ID:** `http_endpoint:GET:/api/v1/admin/blueprints/{blueprint}/components`
**Path:** `app/Http/Controllers/Admin/BlueprintComponentController.php`

GET /api/v1/admin/blueprints/{blueprint}/components (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/blueprints/{blueprint}/components`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`, `components`


---

## admin.v1.blueprints.destroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/blueprints/{blueprint}`
**Path:** `app/Http/Controllers/Admin/BlueprintController.php`

DELETE /api/v1/admin/blueprints/{blueprint} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/blueprints/{blueprint}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`


---

## admin.v1.blueprints.index
**ID:** `http_endpoint:GET:/api/v1/admin/blueprints`
**Path:** `app/Http/Controllers/Admin/BlueprintController.php`

GET /api/v1/admin/blueprints (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/blueprints`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `blueprints`


---

## admin.v1.blueprints.paths.destroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/blueprints/{blueprint}/paths/{path}`
**Path:** `app/Http/Controllers/Admin/PathController.php`

DELETE /api/v1/admin/blueprints/{blueprint}/paths/{path} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/blueprints/{blueprint}/paths/{path}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)
  - `path` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`, `paths`


---

## admin.v1.blueprints.paths.index
**ID:** `http_endpoint:GET:/api/v1/admin/blueprints/{blueprint}/paths`
**Path:** `app/Http/Controllers/Admin/PathController.php`

GET /api/v1/admin/blueprints/{blueprint}/paths (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/blueprints/{blueprint}/paths`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`, `paths`


---

## admin.v1.blueprints.paths.show
**ID:** `http_endpoint:GET:/api/v1/admin/blueprints/{blueprint}/paths/{path}`
**Path:** `app/Http/Controllers/Admin/PathController.php`

GET /api/v1/admin/blueprints/{blueprint}/paths/{path} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/blueprints/{blueprint}/paths/{path}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)
  - `path` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`, `paths`


---

## admin.v1.blueprints.paths.store
**ID:** `http_endpoint:POST:/api/v1/admin/blueprints/{blueprint}/paths`
**Path:** `app/Http/Controllers/Admin/PathController.php`

POST /api/v1/admin/blueprints/{blueprint}/paths (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/blueprints/{blueprint}/paths`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`, `paths`


---

## admin.v1.blueprints.paths.update
**ID:** `http_endpoint:PUT:/api/v1/admin/blueprints/{blueprint}/paths/{path}`
**Path:** `app/Http/Controllers/Admin/PathController.php`

PUT /api/v1/admin/blueprints/{blueprint}/paths/{path} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/blueprints/{blueprint}/paths/{path}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)
  - `path` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`, `paths`


---

## admin.v1.blueprints.show
**ID:** `http_endpoint:GET:/api/v1/admin/blueprints/{blueprint}`
**Path:** `app/Http/Controllers/Admin/BlueprintController.php`

GET /api/v1/admin/blueprints/{blueprint} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/blueprints/{blueprint}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`


---

## admin.v1.blueprints.store
**ID:** `http_endpoint:POST:/api/v1/admin/blueprints`
**Path:** `app/Http/Controllers/Admin/BlueprintController.php`

POST /api/v1/admin/blueprints (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/blueprints`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `blueprints`


---

## admin.v1.blueprints.update
**ID:** `http_endpoint:PUT:/api/v1/admin/blueprints/{blueprint}`
**Path:** `app/Http/Controllers/Admin/BlueprintController.php`

PUT /api/v1/admin/blueprints/{blueprint} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/blueprints/{blueprint}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `blueprint` (string, required)

### Tags
`api`, `admin`, `v1`, `blueprints`


---

## admin.v1.entries.destroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/entries/{id}`
**Path:** `app/Http/Controllers/Admin/V1/EntryController.php`

DELETE /api/v1/admin/entries/{id} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/entries/{id}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `id` (string, required)

### Tags
`api`, `admin`, `v1`, `entries`


---

## admin.v1.entries.index
**ID:** `http_endpoint:GET:/api/v1/admin/entries`
**Path:** `app/Http/Controllers/Admin/V1/EntryController.php`

GET /api/v1/admin/entries (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/entries`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `entries`


---

## admin.v1.entries.restore
**ID:** `http_endpoint:POST:/api/v1/admin/entries/{id}/restore`
**Path:** `app/Http/Controllers/Admin/V1/EntryController.php`

POST /api/v1/admin/entries/{id}/restore (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/entries/{id}/restore`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `id` (string, required)

### Tags
`api`, `admin`, `v1`, `entries`, `restore`


---

## admin.v1.entries.show
**ID:** `http_endpoint:GET:/api/v1/admin/entries/{id}`
**Path:** `app/Http/Controllers/Admin/V1/EntryController.php`

GET /api/v1/admin/entries/{id} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/entries/{id}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `id` (string, required)

### Tags
`api`, `admin`, `v1`, `entries`


---

## admin.v1.entries.statuses
**ID:** `http_endpoint:GET:/api/v1/admin/entries/statuses`
**Path:** `app/Http/Controllers/Admin/V1/EntryController.php`

GET /api/v1/admin/entries/statuses (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/entries/statuses`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `entries`, `statuses`


---

## admin.v1.entries.store
**ID:** `http_endpoint:POST:/api/v1/admin/entries`
**Path:** `app/Http/Controllers/Admin/V1/EntryController.php`

POST /api/v1/admin/entries (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/entries`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `entries`


---

## admin.v1.entries.terms.index
**ID:** `http_endpoint:GET:/api/v1/admin/entries/{entry}/terms`
**Path:** `app/Http/Controllers/Admin/V1/EntryTermsController.php`

GET /api/v1/admin/entries/{entry}/terms (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/entries/{entry}/terms`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `entry` (string, required)

### Tags
`api`, `admin`, `v1`, `entries`, `terms`


---

## admin.v1.entries.terms.sync
**ID:** `http_endpoint:PUT:/api/v1/admin/entries/{entry}/terms/sync`
**Path:** `app/Http/Controllers/Admin/V1/EntryTermsController.php`

PUT /api/v1/admin/entries/{entry}/terms/sync (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/entries/{entry}/terms/sync`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `entry` (string, required)

### Tags
`api`, `admin`, `v1`, `entries`, `terms`, `sync`


---

## admin.v1.entries.update
**ID:** `http_endpoint:PUT:/api/v1/admin/entries/{id}`
**Path:** `app/Http/Controllers/Admin/V1/EntryController.php`

PUT /api/v1/admin/entries/{id} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/entries/{id}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `id` (string, required)

### Tags
`api`, `admin`, `v1`, `entries`


---

## admin.v1.media.bulkDestroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/media/bulk`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

DELETE /api/v1/admin/media/bulk (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/media/bulk`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `media`, `bulk`


---

## admin.v1.media.bulkForceDestroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/media/bulk/force`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

DELETE /api/v1/admin/media/bulk/force (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/media/bulk/force`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `media`, `bulk`, `force`


---

## admin.v1.media.bulkRestore
**ID:** `http_endpoint:POST:/api/v1/admin/media/bulk/restore`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

POST /api/v1/admin/media/bulk/restore (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/media/bulk/restore`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `media`, `bulk`, `restore`


---

## admin.v1.media.bulkStore
**ID:** `http_endpoint:POST:/api/v1/admin/media/bulk`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

POST /api/v1/admin/media/bulk (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/media/bulk`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `media`, `bulk`


---

## admin.v1.media.config
**ID:** `http_endpoint:GET:/api/v1/admin/media/config`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

GET /api/v1/admin/media/config (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/media/config`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `media`, `config`


---

## admin.v1.media.index
**ID:** `http_endpoint:GET:/api/v1/admin/media`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

GET /api/v1/admin/media (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/media`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `media`


---

## admin.v1.media.show
**ID:** `http_endpoint:GET:/api/v1/admin/media/{media}`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

GET /api/v1/admin/media/{media} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/media/{media}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `media` (string, required)

### Tags
`api`, `admin`, `v1`, `media`


---

## admin.v1.media.store
**ID:** `http_endpoint:POST:/api/v1/admin/media`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

POST /api/v1/admin/media (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/media`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `media`


---

## admin.v1.media.update
**ID:** `http_endpoint:PUT:/api/v1/admin/media/{media}`
**Path:** `app/Http/Controllers/Admin/V1/MediaController.php`

PUT /api/v1/admin/media/{media} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/media/{media}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `media` (string, required)

### Tags
`api`, `admin`, `v1`, `media`


---

## admin.v1.options.destroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/options/{namespace}/{key}`
**Path:** `app/Http/Controllers/Admin/V1/OptionsController.php`

DELETE /api/v1/admin/options/{namespace}/{key} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/options/{namespace}/{key}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `namespace` (string, required)
  - `key` (string, required)

### Tags
`api`, `admin`, `v1`, `options`


---

## admin.v1.options.index
**ID:** `http_endpoint:GET:/api/v1/admin/options/{namespace}`
**Path:** `app/Http/Controllers/Admin/V1/OptionsController.php`

GET /api/v1/admin/options/{namespace} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/options/{namespace}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `namespace` (string, required)

### Tags
`api`, `admin`, `v1`, `options`


---

## admin.v1.options.restore
**ID:** `http_endpoint:POST:/api/v1/admin/options/{namespace}/{key}/restore`
**Path:** `app/Http/Controllers/Admin/V1/OptionsController.php`

POST /api/v1/admin/options/{namespace}/{key}/restore (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/options/{namespace}/{key}/restore`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `namespace` (string, required)
  - `key` (string, required)

### Tags
`api`, `admin`, `v1`, `options`, `restore`


---

## admin.v1.options.show
**ID:** `http_endpoint:GET:/api/v1/admin/options/{namespace}/{key}`
**Path:** `app/Http/Controllers/Admin/V1/OptionsController.php`

GET /api/v1/admin/options/{namespace}/{key} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/options/{namespace}/{key}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `namespace` (string, required)
  - `key` (string, required)

### Tags
`api`, `admin`, `v1`, `options`


---

## admin.v1.options.upsert
**ID:** `http_endpoint:PUT:/api/v1/admin/options/{namespace}/{key}`
**Path:** `app/Http/Controllers/Admin/V1/OptionsController.php`

PUT /api/v1/admin/options/{namespace}/{key} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/options/{namespace}/{key}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `namespace` (string, required)
  - `key` (string, required)

### Tags
`api`, `admin`, `v1`, `options`


---

## admin.v1.plugins.disable
**ID:** `http_endpoint:POST:/api/v1/admin/plugins/{slug}/disable`
**Path:** `app/Http/Controllers/Admin/V1/PluginsController.php`

POST /api/v1/admin/plugins/{slug}/disable (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/plugins/{slug}/disable`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `slug` (string, required)

### Tags
`api`, `admin`, `v1`, `plugins`, `disable`


---

## admin.v1.plugins.enable
**ID:** `http_endpoint:POST:/api/v1/admin/plugins/{slug}/enable`
**Path:** `app/Http/Controllers/Admin/V1/PluginsController.php`

POST /api/v1/admin/plugins/{slug}/enable (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/plugins/{slug}/enable`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `slug` (string, required)

### Tags
`api`, `admin`, `v1`, `plugins`, `enable`


---

## admin.v1.plugins.index
**ID:** `http_endpoint:GET:/api/v1/admin/plugins`
**Path:** `app/Http/Controllers/Admin/V1/PluginsController.php`

GET /api/v1/admin/plugins (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/plugins`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `plugins`


---

## admin.v1.plugins.sync
**ID:** `http_endpoint:POST:/api/v1/admin/plugins/sync`
**Path:** `app/Http/Controllers/Admin/V1/PluginsController.php`

POST /api/v1/admin/plugins/sync (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/plugins/sync`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `plugins`, `sync`


---

## admin.v1.post-types.destroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/post-types/{slug}`
**Path:** `app/Http/Controllers/Admin/V1/PostTypeController.php`

DELETE /api/v1/admin/post-types/{slug} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/post-types/{slug}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `slug` (string, required)

### Tags
`api`, `admin`, `v1`, `post-types`


---

## admin.v1.post-types.index
**ID:** `http_endpoint:GET:/api/v1/admin/post-types`
**Path:** `app/Http/Controllers/Admin/V1/PostTypeController.php`

GET /api/v1/admin/post-types (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/post-types`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `post-types`


---

## admin.v1.post-types.show
**ID:** `http_endpoint:GET:/api/v1/admin/post-types/{slug}`
**Path:** `app/Http/Controllers/Admin/V1/PostTypeController.php`

GET /api/v1/admin/post-types/{slug} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/post-types/{slug}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `slug` (string, required)

### Tags
`api`, `admin`, `v1`, `post-types`


---

## admin.v1.post-types.store
**ID:** `http_endpoint:POST:/api/v1/admin/post-types`
**Path:** `app/Http/Controllers/Admin/V1/PostTypeController.php`

POST /api/v1/admin/post-types (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/post-types`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `post-types`


---

## admin.v1.post-types.update
**ID:** `http_endpoint:PUT:/api/v1/admin/post-types/{slug}`
**Path:** `app/Http/Controllers/Admin/V1/PostTypeController.php`

PUT /api/v1/admin/post-types/{slug} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/post-types/{slug}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `slug` (string, required)

### Tags
`api`, `admin`, `v1`, `post-types`


---

## admin.v1.search.reindex
**ID:** `http_endpoint:POST:/api/v1/admin/search/reindex`
**Path:** `app/Http/Controllers/Admin/V1/SearchAdminController.php`

POST /api/v1/admin/search/reindex (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/search/reindex`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `search`, `reindex`


---

## admin.v1.taxonomies.destroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/taxonomies/{id}`
**Path:** `app/Http/Controllers/Admin/V1/TaxonomyController.php`

DELETE /api/v1/admin/taxonomies/{id} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/taxonomies/{id}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `id` (string, required)

### Tags
`api`, `admin`, `v1`, `taxonomies`


---

## admin.v1.taxonomies.index
**ID:** `http_endpoint:GET:/api/v1/admin/taxonomies`
**Path:** `app/Http/Controllers/Admin/V1/TaxonomyController.php`

GET /api/v1/admin/taxonomies (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/taxonomies`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `taxonomies`


---

## admin.v1.taxonomies.show
**ID:** `http_endpoint:GET:/api/v1/admin/taxonomies/{id}`
**Path:** `app/Http/Controllers/Admin/V1/TaxonomyController.php`

GET /api/v1/admin/taxonomies/{id} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/taxonomies/{id}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `id` (string, required)

### Tags
`api`, `admin`, `v1`, `taxonomies`


---

## admin.v1.taxonomies.store
**ID:** `http_endpoint:POST:/api/v1/admin/taxonomies`
**Path:** `app/Http/Controllers/Admin/V1/TaxonomyController.php`

POST /api/v1/admin/taxonomies (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/taxonomies`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `taxonomies`


---

## admin.v1.taxonomies.terms.index
**ID:** `http_endpoint:GET:/api/v1/admin/taxonomies/{taxonomy}/terms`
**Path:** `app/Http/Controllers/Admin/V1/TermController.php`

GET /api/v1/admin/taxonomies/{taxonomy}/terms (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/taxonomies/{taxonomy}/terms`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `taxonomy` (string, required)

### Tags
`api`, `admin`, `v1`, `taxonomies`, `terms`


---

## admin.v1.taxonomies.terms.store
**ID:** `http_endpoint:POST:/api/v1/admin/taxonomies/{taxonomy}/terms`
**Path:** `app/Http/Controllers/Admin/V1/TermController.php`

POST /api/v1/admin/taxonomies/{taxonomy}/terms (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/taxonomies/{taxonomy}/terms`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `taxonomy` (string, required)

### Tags
`api`, `admin`, `v1`, `taxonomies`, `terms`


---

## admin.v1.taxonomies.terms.tree
**ID:** `http_endpoint:GET:/api/v1/admin/taxonomies/{taxonomy}/terms/tree`
**Path:** `app/Http/Controllers/Admin/V1/TermController.php`

GET /api/v1/admin/taxonomies/{taxonomy}/terms/tree (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/taxonomies/{taxonomy}/terms/tree`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `taxonomy` (string, required)

### Tags
`api`, `admin`, `v1`, `taxonomies`, `terms`, `tree`


---

## admin.v1.taxonomies.update
**ID:** `http_endpoint:PUT:/api/v1/admin/taxonomies/{id}`
**Path:** `app/Http/Controllers/Admin/V1/TaxonomyController.php`

PUT /api/v1/admin/taxonomies/{id} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/taxonomies/{id}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `id` (string, required)

### Tags
`api`, `admin`, `v1`, `taxonomies`


---

## admin.v1.templates.index
**ID:** `http_endpoint:GET:/api/v1/admin/templates`
**Path:** `app/Http/Controllers/Admin/V1/TemplateController.php`

GET /api/v1/admin/templates (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/templates`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `templates`


---

## admin.v1.templates.show
**ID:** `http_endpoint:GET:/api/v1/admin/templates/{name}`
**Path:** `app/Http/Controllers/Admin/V1/TemplateController.php`

GET /api/v1/admin/templates/{name} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/templates/{name}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `name` (string, required)

### Tags
`api`, `admin`, `v1`, `templates`


---

## admin.v1.templates.store
**ID:** `http_endpoint:POST:/api/v1/admin/templates`
**Path:** `app/Http/Controllers/Admin/V1/TemplateController.php`

POST /api/v1/admin/templates (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/templates`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `templates`


---

## admin.v1.templates.update
**ID:** `http_endpoint:PUT:/api/v1/admin/templates/{name}`
**Path:** `app/Http/Controllers/Admin/V1/TemplateController.php`

PUT /api/v1/admin/templates/{name} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/templates/{name}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `name` (string, required)

### Tags
`api`, `admin`, `v1`, `templates`


---

## admin.v1.terms.destroy
**ID:** `http_endpoint:DELETE:/api/v1/admin/terms/{term}`
**Path:** `app/Http/Controllers/Admin/V1/TermController.php`

DELETE /api/v1/admin/terms/{term} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/terms/{term}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `term` (string, required)

### Tags
`api`, `admin`, `v1`, `terms`


---

## admin.v1.terms.show
**ID:** `http_endpoint:GET:/api/v1/admin/terms/{term}`
**Path:** `app/Http/Controllers/Admin/V1/TermController.php`

GET /api/v1/admin/terms/{term} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/terms/{term}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `term` (string, required)

### Tags
`api`, `admin`, `v1`, `terms`


---

## admin.v1.terms.update
**ID:** `http_endpoint:PUT:/api/v1/admin/terms/{term}`
**Path:** `app/Http/Controllers/Admin/V1/TermController.php`

PUT /api/v1/admin/terms/{term} (api)

### Meta
- **Method:** `PUT`
- **URI:** `/api/v1/admin/terms/{term}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `term` (string, required)

### Tags
`api`, `admin`, `v1`, `terms`


---

## api.auth.login
**ID:** `http_endpoint:POST:/api/v1/auth/login`
**Path:** `app/Http/Controllers/Auth/LoginController.php`

POST /api/v1/auth/login (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/auth/login`
- **Group:** `api`

### Tags
`api`, `v1`, `auth`, `login`


---

## api.auth.logout
**ID:** `http_endpoint:POST:/api/v1/auth/logout`
**Path:** `app/Http/Controllers/Auth/LogoutController.php`

POST /api/v1/auth/logout (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/auth/logout`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `v1`, `auth`, `logout`


---

## api.auth.refresh
**ID:** `http_endpoint:POST:/api/v1/auth/refresh`
**Path:** `app/Http/Controllers/Auth/RefreshController.php`

POST /api/v1/auth/refresh (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/auth/refresh`
- **Group:** `api`

### Tags
`api`, `v1`, `auth`, `refresh`


---

## api.v1.media.show
**ID:** `http_endpoint:GET:/api/v1/media/{id}`
**Path:** `app/Http/Controllers/MediaPreviewController.php`

GET /api/v1/media/{id} (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/media/{id}`
- **Group:** `api`
- **Parameters:**
  - `id` (string, required)

### Tags
`api`, `v1`, `media`


---

## api.v1.search
**ID:** `http_endpoint:GET:/api/v1/search`
**Path:** `app/Http/Controllers/SearchController.php`

GET /api/v1/search (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/search`
- **Group:** `api`

### Tags
`api`, `v1`, `search`


---

## delete.path
**ID:** `http_endpoint:DELETE:/api/v1/admin/reservations/{path}`
**Path:** `app/Http/Controllers/Admin/V1/PathReservationController.php`

DELETE /api/v1/admin/reservations/{path} (api)

### Meta
- **Method:** `DELETE`
- **URI:** `/api/v1/admin/reservations/{path}`
- **Group:** `api`
- **Auth:** `jwt`
- **Parameters:**
  - `path` (string, required)

### Tags
`api`, `admin`, `v1`, `reservations`


---

## get.reservations
**ID:** `http_endpoint:GET:/api/v1/admin/reservations`
**Path:** `app/Http/Controllers/Admin/V1/PathReservationController.php`

GET /api/v1/admin/reservations (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/reservations`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `reservations`


---

## get.slugify
**ID:** `http_endpoint:GET:/api/v1/admin/utils/slugify`
**Path:** `app/Http/Controllers/Admin/V1/UtilsController.php`

GET /api/v1/admin/utils/slugify (api)

### Meta
- **Method:** `GET`
- **URI:** `/api/v1/admin/utils/slugify`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `utils`, `slugify`


---

## home
**ID:** `http_endpoint:GET://`
**Path:** `routes`

GET // (web)

### Meta
- **Method:** `GET`
- **URI:** `//`
- **Group:** `web`

### Tags
`web`


---

## page.show
**ID:** `http_endpoint:GET:/{slug}`
**Path:** `app/Http/Controllers/PageController.php`

GET /{slug} (web)

### Meta
- **Method:** `GET`
- **URI:** `/{slug}`
- **Group:** `web`
- **Parameters:**
  - `slug` (string, required)

### Tags
`web`


---

## post.reservations
**ID:** `http_endpoint:POST:/api/v1/admin/reservations`
**Path:** `app/Http/Controllers/Admin/V1/PathReservationController.php`

POST /api/v1/admin/reservations (api)

### Meta
- **Method:** `POST`
- **URI:** `/api/v1/admin/reservations`
- **Group:** `api`
- **Auth:** `jwt`

### Tags
`api`, `admin`, `v1`, `reservations`


---

## storage.local
**ID:** `http_endpoint:GET:/storage/{path}`
**Path:** `routes`

GET /storage/{path}

### Meta
- **Method:** `GET`
- **URI:** `/storage/{path}`
- **Parameters:**
  - `path` (string, required)

### Tags
`storage`


---
