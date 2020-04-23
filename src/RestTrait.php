<?php
declare(strict_types=1);

namespace Rabbit\Rest;

use DI\DependencyException;
use DI\NotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use rabbit\db\mysql\CreateExt;
use rabbit\db\mysql\DeleteExt;
use rabbit\db\mysql\Orm;
use rabbit\db\mysql\UpdateExt;
use rabbit\helper\ArrayHelper;

/**
 * Trait RestTrait
 * @package Rabbit\Rest
 */
trait RestTrait
{
    /** @var CacheInterface */
    protected $cache;
    /** @var string */
    protected $cacheHeader = 'Cache';
    /** @var callable */
    protected $cacheCallback;

    /**
     * @param array $params
     * @param ServerRequestInterface|null $request
     * @param string $method
     * @return mixed
     */
    public function __invoke(array $params = [], ServerRequestInterface $request = null, string $method = '')
    {
        return $this->$method($params, $request);
    }

    /**
     * @param array $body
     * @param ServerRequestInterface|null $request
     * @return array
     */
    protected function create(array $body, ServerRequestInterface $request = null): array
    {
        $model = new $this->modelClass();
        return $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return CreateExt::create($model, $body);
        });
    }

    /**
     * @param array $body
     * @param ServerRequestInterface|null $request
     * @return array
     */
    protected function update(array $body, ServerRequestInterface $request = null): array
    {
        $model = new $this->modelClass();
        return $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return UpdateExt::update($model, $body, true);
        });
    }

    /**
     * @param array $body
     * @param ServerRequestInterface|null $request
     * @return mixed
     */
    protected function delete(array $body, ServerRequestInterface $request = null)
    {
        $model = new $this->modelClass();
        return $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return DeleteExt::delete($model, $body, true);
        });
    }

    /**
     * @param array $filter
     * @param ServerRequestInterface|null $request
     * @return array
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function list(array $filter, ServerRequestInterface $request = null): array
    {
        $filter = ArrayHelper::getValueByArray($filter, [0, 1], null, ['*', []]);
        $duration = -1;
        if (is_callable($this->cacheCallback)) {
            $duration = call_user_func($this->cacheCallback, $request);
        }
        return Orm::search($this->modelClass::getDb(), $this->modelClass::tableName(), $filter, 'queryAll', $duration === '' ? -1 : (int)$duration, $this->cache);
    }

    /**
     * @param array $filter
     * @param ServerRequestInterface|null $request
     * @return array
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function view(array $filter, ServerRequestInterface $request = null): array
    {
        $filter = ArrayHelper::getValueByArray($filter, [0, 1], null, ['*', []]);
        $duration = -1;
        if (is_callable($this->cacheCallback)) {
            $duration = call_user_func($this->cacheCallback, $request);
        }
        return Orm::search($this->modelClass::getDb(), $this->modelClass::tableName(), $filter, 'queryOne', $duration === '' ? -1 : (int)$duration, $this->cache);
    }

    /**
     * @param array $filter
     * @param ServerRequestInterface|null $request
     * @return mixed
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function search(array $filter, ServerRequestInterface $request = null)
    {
        $method = ArrayHelper::remove($filter, 'method', 'queryAll');
        $filter = ArrayHelper::getValueByArray($filter['query'], [0, 1], null, ['*', []]);
        $duration = -1;
        if (is_callable($this->cacheCallback)) {
            $duration = call_user_func($this->cacheCallback, $request);
        }
        return Orm::search($this->modelClass::getDb(), $this->modelClass::tableName(), $filter, $method, $duration === '' ? -1 : (int)$duration, $this->cache);
    }
}