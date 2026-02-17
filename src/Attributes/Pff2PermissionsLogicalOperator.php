<?php

declare(strict_types=1);

namespace pff\modules\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Pff2PermissionsLogicalOperator
{
    public const AND = 'AND';
    public const OR = 'OR';

    public function __construct(private readonly string $operator)
    {
    }

    public function getOperator(): string
    {
        return $this->operator;
    }
}
