# pff2-permissions

Permissions module for `stonedz/pff2` controllers.

It reads permission metadata from controller classes/actions and blocks access when the logged user does not have the required permission flags.

## Requirements

- `stonedz/pff2` v4
- Doctrine ORM enabled in your app (the module reads the user model through the EntityManager)

## Installation

1. Require the module:

```bash
composer require stonedz/pff2-permissions
```

2. Enable it in your app modules list.

3. Add module configuration in your app config folder:

`app/config/modules/pff2-permissions/module.conf.yaml`

```yaml
moduleConf:
  userClass: AnagraficaBusiness
  sessionUserId: id_user
  getPermission: getPermesso
  controllerNotLogged: Index
  actionNotLogged: index
  permissionClass: Permesso
```

## Configuration reference

- `userClass`: user model class name under `\pff\models`.
- `sessionUserId`: key used in `$_SESSION['logged_data']` for the logged user id.
- `getPermission`: method called on the user instance to retrieve the permission object.
- `controllerNotLogged`: redirect controller when user is not logged.
- `actionNotLogged`: redirect action when user is not logged.
- `permissionClass`: permission model class name under `\pff\models`.

## Usage (native attributes)

Use attributes on controller class and/or action method.

```php
use pff\modules\Attributes\Pff2Permissions;
use pff\modules\Attributes\Pff2PermissionsLogicalOperator;

#[Pff2Permissions(["Logged", "FatturazioneWriteable"])]
class Fatturazione_Controller extends AController
{
  #[Pff2Permissions(["Admin"])]
  #[Pff2PermissionsLogicalOperator(Pff2PermissionsLogicalOperator::OR)]
    public function editAction()
    {
    }
}
```

### Supported attributes

- `#[Pff2Permissions(["PermissionA", "PermissionB"])]`
- `#[Pff2PermissionsLogicalOperator(Pff2PermissionsLogicalOperator::AND)]`
- `#[Pff2PermissionsLogicalOperator(Pff2PermissionsLogicalOperator::OR)]`

If `Pff2PermissionsLogicalOperator` is omitted, default behavior is `AND`.

## Backward compatibility (legacy docblocks)

Legacy docblock annotations are still supported, so existing controllers keep working:

```php
/**
 * @Pff2Permissions ["Logged","FatturazioneWriteable"]
 */
class Fatturazione_Controller extends AController
{
  /**
   * @Pff2Permissions ["Admin"]
   * @Pff2PermissionsLogicalOperator OR
   */
  public function editAction()
  {
  }
}
```

The legacy variant `@Pff2PermissionslogicalOperator` (lowercase `l`) is also recognized.

## Permission evaluation rules

- Class and method permissions are merged.
- Duplicate permission entries are removed.
- `AND`: all listed permissions must be true.
- `OR`: at least one listed permission must be true.
- If no permission annotations are present, the request is allowed.

## Runtime behavior

- Not logged user: redirected to `controllerNotLogged/actionNotLogged`.
- Logged user without permission: a `403` (`Action not permitted`) is thrown.
- Missing ORM setup: a `500` is thrown (`PermissionChecker requires Doctrine ORM to be enabled`).
