<?php

declare(strict_types=1);

namespace Rabbit\Rest;

interface RulesInterface
{
    public function getRules(string $tableName, string $id = null): array;
}
