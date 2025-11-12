# Permissions & Abilities

> ⚠️ **Auto-generated**. Do not edit manually. Run `php artisan docs:abilities` to update.

_Last generated: 2025-11-12 14:42:23_

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
| `viewAny` | _No description_ | `app/Policies/MediaPolicy.php` |
| `view` | _No description_ | `app/Policies/MediaPolicy.php` |
| `create` | _No description_ | `app/Policies/MediaPolicy.php` |
| `update` | _No description_ | `app/Policies/MediaPolicy.php` |
| `delete` | _No description_ | `app/Policies/MediaPolicy.php` |
| `restore` | _No description_ | `app/Policies/MediaPolicy.php` |
| `forceDelete` | _No description_ | `app/Policies/MediaPolicy.php` |
| `upload` | _No description_ | `app/Policies/MediaPolicy.php` |
| `reprocess` | _No description_ | `app/Policies/MediaPolicy.php` |
| `move` | _No description_ | `app/Policies/MediaPolicy.php` |

## Option

| Ability | Description | Policy File |
|---------|-------------|-------------|
| `viewAny` | _No description_ | `app/Policies/OptionPolicy.php` |
| `view` | _No description_ | `app/Policies/OptionPolicy.php` |
| `write` | _No description_ | `app/Policies/OptionPolicy.php` |
| `delete` | _No description_ | `app/Policies/OptionPolicy.php` |
| `restore` | _No description_ | `app/Policies/OptionPolicy.php` |

## Plugin

| Ability | Description | Policy File |
|---------|-------------|-------------|
| `viewAny` | _No description_ | `app/Policies/PluginPolicy.php` |
| `toggle` | _No description_ | `app/Policies/PluginPolicy.php` |
| `sync` | _No description_ | `app/Policies/PluginPolicy.php` |

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
