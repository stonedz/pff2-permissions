<?php

declare(strict_types=1);

namespace pff\modules\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Pff2Permissions
{
    /**
     * @var string[]
     */
    private array $permissions;

    /**
     * @param string[]|string $permissions
     */
    public function __construct(array|string $permissions)
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }

        $this->permissions = array_values(array_filter(array_map('trim', $permissions), static fn($permission): bool => $permission !== ''));
    }

    /**
     * @return string[]
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
