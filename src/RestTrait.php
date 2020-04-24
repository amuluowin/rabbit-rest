<?php
declare(strict_types=1);

namespace Rabbit\Rest;

use DI\DependencyException;
use DI\NotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use rabbit\db\DBHelper;
use rabbit\db\mysql\CreateExt;
use rabbit\db\mysql\DeleteExt;
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
    /** @var callable */
    protected $cacheCallback;

    static $joinMap = [
        'lj' => '[<]',
        'rj' => '[>]',
        'fj' => '[<>]'
    ];

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
        $alias = $this->buildFilter($filter);
        return DBHelper::Search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->all();
    }

    /**
     * @param array $filter
     * @param ServerRequestInterface|null $request
     * @return array
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function index(array $filter, ServerRequestInterface $request = null): array
    {
        $alias = $this->buildFilter($filter);
        $page = ArrayHelper::remove($filter, 'page', 0);
        return DBHelper::SearchList($this->modelClass::find()->alias($alias)->asArray(), $filter, $page, $this->getDuration($request), $this->cache);
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
        $alias = $this->buildFilter($filter);
        return DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->one();
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
        $alias = $this->buildFilter($filter);
        $method = ArrayHelper::remove($filter, 'method', 'all');
        return DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->$method();
    }

    /**
     * @param array $filter
     */
    private function buildFilter(array &$filter): string
    {
        $alias = explode('\\', get_class());
        $alias = str_replace('crud', '', strtolower(end($alias)));
        $select = ArrayHelper::remove($filter, 'select', '*');
        if (is_string($select) && $select === '*') {
            $select = $alias . '.' . $select;
        } elseif (is_array($select) && $select === ['*']) {
            $select = [$alias . '.' . current($select)];
        }
        $filter['select'] = $select;
        return $alias;
    }

    /**
     * @param ServerRequestInterface $request
     * @return int
     */
    private function getDuration(ServerRequestInterface $request): int
    {
        $duration = -1;
        if (is_callable($this->cacheCallback)) {
            $duration = call_user_func($this->cacheCallback, $request);
        }
        return $duration === '' ? -1 : (int)$duration;
    }
}