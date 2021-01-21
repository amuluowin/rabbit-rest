<?php

declare(strict_types=1);

namespace Rabbit\Rest;

class RestEntry
{
    protected string $class;
    protected array $methods = ['create', 'update', 'del', 'view', 'list', 'search', 'index', 'get', 'put', 'post', 'delete'];
    protected bool $auth = true;
    protected array $events = [];

    public function __construct(array $columns)
    {
        foreach ($columns as $name => $value) {
            $this->$name = $value;
        }
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getAuth(): bool
    {
        return $this->auth;
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}
