# Permissions & Abilities

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:abilities` to update.

_Last generated: 2025-11-08 10:19:15_

## Entry

| Ability | Description | Policy File |
|---------|-------------|-------------|
| `viewAny` | Determine whether the user can view any models. | `app/Policies/EntryPolicy.php` |
| `view` | Determine whether the user can view the model. | `app/Policies/EntryPolicy.php` |
| `create` | Determine whether the user can create models. | `app/Policies/EntryPolicy.php` |
| `update` | Determine whether the user can update the model. | `app/Policies/EntryPolicy.php` |
| `delete` | Determine whether the user can delete the model. | `app/Policies/EntryPolicy.php` |
| `restore` | Determine whether the user can restore the model. | `app/Policies/EntryPolicy.php` |
| `forceDelete` | Determine whether the user can permanently delete the model. | `app/Policies/EntryPolicy.php` |
| `publish` | Determine whether the user can publish/unpublish the entry. | `app/Policies/EntryPolicy.php` |
| `attachMedia` | Determine whether the user can attach media to the entry. | `app/Policies/EntryPolicy.php` |
| `manageTerms` | Determine whether the user can manage terms for the entry. | `app/Policies/EntryPolicy.php` |

## Media

| Ability | Description | Policy File |
|---------|-------------|-------------|
| `viewAny` | Determine whether the user can view any models. | `app/Policies/MediaPolicy.php` |
| `view` | Determine whether the user can view the model. | `app/Policies/MediaPolicy.php` |
| `create` | Determine whether the user can create models. | `app/Policies/MediaPolicy.php` |
| `update` | Determine whether the user can update the model. | `app/Policies/MediaPolicy.php` |
| `delete` | Determine whether the user can delete the model. | `app/Policies/MediaPolicy.php` |
| `restore` | Determine whether the user can restore the model. | `app/Policies/MediaPolicy.php` |
| `forceDelete` | Determine whether the user can permanently delete the model. | `app/Policies/MediaPolicy.php` |
| `upload` | Determine whether the user can upload media. | `app/Policies/MediaPolicy.php` |
| `reprocess` | Determine whether the user can reprocess media variants. | `app/Policies/MediaPolicy.php` |
| `move` | Determine whether the user can move media between storages/folders. | `app/Policies/MediaPolicy.php` |

## RouteReservation

| Ability | Description | Policy File |
|---------|-------------|-------------|
| `viewAny` | Determine whether the user can view any models. | `app/Policies/RouteReservationPolicy.php` |
| `view` | Determine whether the user can view the model. | `app/Policies/RouteReservationPolicy.php` |
| `create` | Determine whether the user can create models. | `app/Policies/RouteReservationPolicy.php` |
| `update` | Determine whether the user can update the model. | `app/Policies/RouteReservationPolicy.php` |
| `delete` | Determine whether the user can delete the model. | `app/Policies/RouteReservationPolicy.php` |
| `deleteAny` | Determine whether the user can delete any model (for collection operations). | `app/Policies/RouteReservationPolicy.php` |

## Term

| Ability | Description | Policy File |
|---------|-------------|-------------|
| `viewAny` | Determine whether the user can view any models. | `app/Policies/TermPolicy.php` |
| `view` | Determine whether the user can view the model. | `app/Policies/TermPolicy.php` |
| `create` | Determine whether the user can create models. | `app/Policies/TermPolicy.php` |
| `update` | Determine whether the user can update the model. | `app/Policies/TermPolicy.php` |
| `delete` | Determine whether the user can delete the model. | `app/Policies/TermPolicy.php` |
| `restore` | Determine whether the user can restore the model. | `app/Policies/TermPolicy.php` |
| `forceDelete` | Determine whether the user can permanently delete the model. | `app/Policies/TermPolicy.php` |
| `attachEntry` | Determine whether the user can attach entries to the term. | `app/Policies/TermPolicy.php` |

## Usage

```php
// In controller
$this->authorize('update', $entry);

// In blade
@can('update', $entry)
    <!-- Edit button -->
@endcan
```
