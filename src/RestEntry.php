<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Rabbit\DB\Query;

class RestEntry
{
    protected string $class;
    protected array $methods = ['create', 'save', 'update', 'del', 'view', 'list', 'search', 'index', 'get', 'put', 'post', 'delete'];
    protected bool $auth = true;
    protected array $events = [];
    protected ?RulesInterface $rules = null;

    public function __construct(array $columns, RulesInterface $rules = null)
    {
        $this->rules = $rules ?? getDI('rules', false);
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

    public function getRuleQuery(Query $query): Query
    {
        foreach ($this->rules->getRules($this->class::tableName()) as $condition) {
            $query = $query->andWhere($condition);
        }
        return $query;
    }
}
