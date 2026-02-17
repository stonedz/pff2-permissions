<?php

declare(strict_types=1);

namespace pff\modules\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Pff2PermissionDescription
{
    public function __construct(private readonly string $description)
    {
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
