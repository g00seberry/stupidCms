---
owner: "@backend-team"
system_of_record: "generated"
review_cycle_days: 14
last_reviewed: 2025-11-08
related_code:
    - "app/Http/Controllers/*.php"
    - "app/Http/Requests/*.php"
---

# API Reference

> ⚠️ **Auto-generated**. Do not edit manually. Run `composer docs:gen` or `php artisan docs:api`.

_Last generated: 2025-11-08 10:19:59 UTC_

## Admin API (`/api/v1/admin/*`)

### Entries

-   `GET` `/api/v1/admin/entries` — `admin.v1.entries.index`
-   `POST` `/api/v1/admin/entries` — `admin.v1.entries.store`
-   `GET` `/api/v1/admin/entries/{entry}/terms` — `admin.v1.entries.terms.index`
-   `POST` `/api/v1/admin/entries/{entry}/terms/attach` — `admin.v1.entries.terms.attach`
-   `POST` `/api/v1/admin/entries/{entry}/terms/detach` — `admin.v1.entries.terms.detach`
-   `PUT` `/api/v1/admin/entries/{entry}/terms/sync` — `admin.v1.entries.terms.sync`
-   `GET` `/api/v1/admin/entries/{id}` — `admin.v1.entries.show`
-   `PUT` `/api/v1/admin/entries/{id}` — `admin.v1.entries.update`
-   `DELETE` `/api/v1/admin/entries/{id}` — `admin.v1.entries.destroy`
-   `POST` `/api/v1/admin/entries/{id}/restore` — `admin.v1.entries.restore`

### Post Types

-   `GET` `/api/v1/admin/post-types/{slug}` — `admin.v1.post-types.show`
-   `PUT` `/api/v1/admin/post-types/{slug}` — `admin.v1.post-types.update`

### Reservations

-   `GET` `/api/v1/admin/reservations` — _(unnamed)_
-   `POST` `/api/v1/admin/reservations` — _(unnamed)_
-   `DELETE` `/api/v1/admin/reservations/{path}` — _(unnamed)_

### Taxonomies

-   `GET` `/api/v1/admin/taxonomies` — `admin.v1.taxonomies.index`
-   `POST` `/api/v1/admin/taxonomies` — `admin.v1.taxonomies.store`
-   `GET` `/api/v1/admin/taxonomies/{slug}` — `admin.v1.taxonomies.show`
-   `PUT` `/api/v1/admin/taxonomies/{slug}` — `admin.v1.taxonomies.update`
-   `DELETE` `/api/v1/admin/taxonomies/{slug}` — `admin.v1.taxonomies.destroy`
-   `GET` `/api/v1/admin/taxonomies/{taxonomy}/terms` — `admin.v1.taxonomies.terms.index`
-   `POST` `/api/v1/admin/taxonomies/{taxonomy}/terms` — `admin.v1.taxonomies.terms.store`

### Terms

-   `GET` `/api/v1/admin/terms/{term}` — `admin.v1.terms.show`
-   `PUT` `/api/v1/admin/terms/{term}` — `admin.v1.terms.update`
-   `DELETE` `/api/v1/admin/terms/{term}` — `admin.v1.terms.destroy`

### Utils

-   `GET` `/api/v1/admin/utils/slugify` — _(unnamed)_

## Auth (`/api/auth/*`)

### Csrf

-   `GET` `/api/v1/auth/csrf` — _(unnamed)_

### Login

-   `POST` `/api/v1/auth/login` — `api.auth.login`

### Logout

-   `POST` `/api/v1/auth/logout` — _(unnamed)_

### Refresh

-   `POST` `/api/v1/auth/refresh` — `api.auth.refresh`
