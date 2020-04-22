<?php
declare(strict_types=1);

namespace Rabbit\Rest;

use DI\DependencyException;
use DI\NotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use rabbit\db\mysql\CreateExt;
use rabbit\db\mysql\UpdateExt;
use rabbit\helper\ArrayHelper;

/**
 * Trait RestTrait
 * @package Rabbit\Rest
 */
trait RestTrait
{
    /**
     * @param array $params
     * @param ServerRequestInterface|null $request
     * @param string $method
     * @return mixed
     */
    public function __invoke(array $params = [], ServerRequestInterface $request = null, string $method = '')
    {
        return $this->$method($params);
    }

    /**
     * @param array $body
     * @return array|array[]
     */
    protected function create(array $body): array
    {
        $model = new $this->modelClass();
        return $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return CreateExt::create($model, $body);
        });
    }

    /**
     * @param array $body
     * @return array
     */
    protected function update(array $body): array
    {
        $model = new $this->modelClass();
        return $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return UpdateExt::update($model, $body);
        });
    }

    /**
     * @param array $filter
     * @return mixed
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function list(array $filter): array
    {
        $filter = ArrayHelper::getValueByArray($filter, [0, 1], null, ['*', []]);
        return Search::run($this->modelClass::getDb(), $this->modelClass::tableName(), $filter);
    }

    /**
     * @param array $filter
     * @return array
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function view(array $filter): array
    {
        $filter = ArrayHelper::getValueByArray($filter, [0, 1], null, ['*', []]);
        return Search::run($this->modelClass::getDb(), $this->modelClass::tableName(), $filter, 'queryOne');
    }

    /**
     * @param array $filter
     * @return mixed
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function search(array $filter)
    {
        $method = ArrayHelper::remove($filter, 'method', 'queryAll');
        $filter = ArrayHelper::getValueByArray($filter['query'], [0, 1], null, ['*', []]);
        return Search::run($this->modelClass::getDb(), $this->modelClass::tableName(), $filter, $method);
    }
}