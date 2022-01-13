<?php

declare(strict_types=1);

namespace Rabbit\Rest;

class BaseEvents
{
    public function getBefore(): array
    {
        return [];
    }
    public function getAfter(): array
    {
        return [];
    }
}
