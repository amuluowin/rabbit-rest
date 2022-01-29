<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Rabbit\DB\Query;

class RestEntry
{
    const SHARE_FUNC = ['view', 'list', 'search', 'index'];
    protected string $class;
    protected array $methods = ['create', 'save', 'update', 'del', 'view', 'list', 'search', 'index', 'get', 'put', 'post', 'delete'];
    protected array $exclude = [];
    protected bool $auth = true;
    protected ?BaseEvents $events = null;
    protected ?RulesInterface $rules = null;
    protected ?int $shareTimeout = null;
    protected ?int $index = null;
    protected ?string $name = null;

    public function __construct(array $columns, RulesInterface $rules = null)
    {
        $this->rules = $rules ?? service('rules', false);
        foreach ($columns as $name => $value) {
            $this->$name = $value;
        }
        foreach ($this->exclude as $name) {
            unset($this->methods[array_search($name, $this->methods)]);
        }
    }



    public function getShareTimeout(): ?int
    {
        return $this->shareTimeout;
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

    public function getRuleQuery(Query $query): Query
    {
        if ($this->rules) {
            foreach ($this->rules->getRules($this->class::tableName()) as $condition) {
                $query = $query->andWhere($condition);
            }
        }
        return $query;
    }

    public function getIndex(): ?int
    {
        return $this->index;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEvents(): ?BaseEvents
    {
        return $this->events;
    }
}
