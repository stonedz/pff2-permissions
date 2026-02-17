<?php

declare(strict_types=1);

namespace pff\modules;

use pff\Abs\AModule;
use pff\modules\Attributes\Pff2PermissionDescription;
use pff\modules\Attributes\Pff2Permissions;
use pff\modules\Attributes\Pff2PermissionsLogicalOperator;
use pff\Iface\IBeforeHook;
use pff\Iface\IConfigurableModule;
use pff\Exception\PffException;

/**
 * Manages Controller->action permissions
 */
class PermissionChecker extends AModule implements IConfigurableModule, IBeforeHook
{
    private $userClass;
    private $sessionUserId;
    private $getPermission;
    private $controllerNotLogged;
    private $actionNotLogged;
    private $permissionClass;

    public function __construct(string $confFile = 'pff2-permissions/module.conf.yaml')
    {
        $this->loadConfig($this->readConfig($confFile));
    }

    /**
     * {@inheritdoc}
     */
    public function loadConfig(array $parsedConfig): void
    {
        $this->userClass = $parsedConfig['moduleConf']['userClass'];
        $this->sessionUserId = $parsedConfig['moduleConf']['sessionUserId'];
        $this->getPermission = $parsedConfig['moduleConf']['getPermission'];
        $this->controllerNotLogged = $parsedConfig['moduleConf']['controllerNotLogged'];
        $this->actionNotLogged = $parsedConfig['moduleConf']['actionNotLogged'];
        $this->permissionClass = $parsedConfig['moduleConf']['permissionClass'];
    }

    /**
     * @throws PffException
     */
    public function doBefore(): void
    {
        $permissionsData = $this->readPermissionsMetadata();
        $annotations = $permissionsData['permissions'];
        $logicalOperator = $permissionsData['operator'];

        //There's no permissions, let the user in
        if ($annotations === []) {
            return;
        }

        if (isset($_SESSION['logged_data'][$this->sessionUserId])) {
            if ($this->_controller === null || $this->_controller->_em === null) {
                throw new PffException('PermissionChecker requires Doctrine ORM to be enabled', 500);
            }

            $user = $this->_controller->_em->find('\\pff\\models\\' . $this->userClass, $_SESSION['logged_data'][$this->sessionUserId]);
            $perm = call_user_func([$user, $this->getPermission]);
            if (!$perm) {
                throw new PffException('Action not permitted', 403);
            }
        } else {
            header("Location: " . $this->_app->getExternalPath() . $this->controllerNotLogged . "/" . $this->actionNotLogged);
            exit();
        }

        if ($logicalOperator === 'and') {
            foreach ($annotations as $a) {
                if (!call_user_func([$perm, 'get' . $a])) {
                    throw new PffException('Action not permitted', 403);
                }
            }
        } elseif ($logicalOperator === 'or') {
            foreach ($annotations as $a) {
                if (call_user_func([$perm, 'get' . $a])) {
                    return;
                }
            }
            throw new PffException('Action not permitted', 403);
        }
    }

    /**
     * Returns the description of the Permission's class properties
     * in the format array[property_name] = [@Pff2PermissionDescription]
     *
     * @return array
     */
    public function getPrettyPermissions()
    {
        $permissionReflect = new \ReflectionClass('\\pff\models\\' . $this->permissionClass);
        $prop = $permissionReflect->getProperties();
        $toReturnAnnotations = [];
        foreach ($prop as $a) {
            $description = $this->extractPropertyPermissionDescription($a);
            if ($description !== null) {
                $tmp_name = explode('_', $a->name);
                array_walk($tmp_name, function (&$arr, $k) {
                    $arr = ucfirst($arr);
                });
                $toReturnAnnotations[implode($tmp_name)] = $description;
            }
        }
        return $toReturnAnnotations;
    }

    /**
     * @return array{permissions: string[], operator: string}
     */
    private function readPermissionsMetadata(): array
    {
        if ($this->_controller === null) {
            return ['permissions' => [], 'operator' => 'and'];
        }

        $controllerReflection = new \ReflectionClass($this->_controller);
        $actionName = $this->_controller->getAction();

        $classPermissions = $this->extractPermissionsFromReflector($controllerReflection);
        $methodPermissions = [];
        $logicalOperator = 'and';

        if ($controllerReflection->hasMethod($actionName)) {
            $methodReflection = $controllerReflection->getMethod($actionName);
            $methodPermissions = $this->extractPermissionsFromReflector($methodReflection);
            $logicalOperator = $this->extractLogicalOperator($methodReflection);
        }

        $permissions = array_unique(array_merge($classPermissions, $methodPermissions));

        return [
            'permissions' => array_values($permissions),
            'operator' => $logicalOperator,
        ];
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionMethod $reflector
     * @return string[]
     */
    private function extractPermissionsFromReflector(object $reflector): array
    {
        $permissions = [];

        foreach ($reflector->getAttributes(Pff2Permissions::class) as $attribute) {
            /** @var Pff2Permissions $instance */
            $instance = $attribute->newInstance();
            $permissions = array_merge($permissions, $instance->getPermissions());
        }

        $permissions = array_merge($permissions, $this->extractPermissionsFromDocComment((string) $reflector->getDocComment()));

        return array_values(array_unique($permissions));
    }

    private function extractLogicalOperator(\ReflectionMethod $methodReflection): string
    {
        foreach ($methodReflection->getAttributes(Pff2PermissionsLogicalOperator::class) as $attribute) {
            /** @var Pff2PermissionsLogicalOperator $instance */
            $instance = $attribute->newInstance();
            return $this->normalizeLogicalOperator($instance->getOperator());
        }

        return $this->extractLogicalOperatorFromDocComment((string) $methodReflection->getDocComment());
    }

    private function normalizeLogicalOperator(string $operator): string
    {
        return strtolower($operator) === 'or' ? 'or' : 'and';
    }

    /**
     * @return string[]
     */
    private function extractPermissionsFromDocComment(string $docComment): array
    {
        if ($docComment === '') {
            return [];
        }

        if (!preg_match_all('/@Pff2Permissions\\s*\\[(.*?)\\]/i', $docComment, $matches)) {
            return [];
        }

        $permissions = [];
        foreach ($matches[1] as $rawPermissions) {
            if (preg_match_all('/"([^"]+)"|\'([^\']+)\'|([A-Za-z0-9_]+)/', $rawPermissions, $tokenMatches, PREG_SET_ORDER)) {
                foreach ($tokenMatches as $tokenMatch) {
                    $permission = $tokenMatch[1] ?: ($tokenMatch[2] ?: $tokenMatch[3]);
                    $permission = trim($permission);
                    if ($permission !== '') {
                        $permissions[] = $permission;
                    }
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    private function extractLogicalOperatorFromDocComment(string $docComment): string
    {
        if ($docComment === '') {
            return 'and';
        }

        if (preg_match('/@Pff2PermissionsLogicalOperator\\s+(AND|OR)/i', $docComment, $match)) {
            return $this->normalizeLogicalOperator($match[1]);
        }

        if (preg_match('/@Pff2PermissionslogicalOperator\\s+(AND|OR)/i', $docComment, $match)) {
            return $this->normalizeLogicalOperator($match[1]);
        }

        return 'and';
    }

    private function extractPropertyPermissionDescription(\ReflectionProperty $property): ?string
    {
        foreach ($property->getAttributes(Pff2PermissionDescription::class) as $attribute) {
            /** @var Pff2PermissionDescription $instance */
            $instance = $attribute->newInstance();
            return $instance->getDescription();
        }

        $docComment = (string) $property->getDocComment();
        if ($docComment === '') {
            return null;
        }

        if (preg_match('/@Pff2PermissionDescription\\s+([^\\r\\n*]+)/i', $docComment, $match)) {
            $description = trim($match[1]);
            if ($description !== '') {
                return trim($description, " \t\n\r\0\x0B\"'");
            }
        }

        return null;
    }
}
