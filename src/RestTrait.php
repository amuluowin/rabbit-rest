<?php
declare(strict_types=1);

namespace Rabbit\Rest;

use DI\DependencyException;
use DI\NotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use rabbit\db\DBHelper;
use rabbit\db\Exception;
use rabbit\db\mysql\CreateExt;
use rabbit\db\mysql\DeleteExt;
use rabbit\db\mysql\UpdateExt;
use rabbit\helper\ArrayHelper;
use rabbit\web\NotFoundHttpException;

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

    protected $crudMethods = ['create', 'update', 'delete', 'view', 'list', 'search', 'index'];
    /** @var string */
    protected $queryKey;

    /**
     * @param array $params
     * @param ServerRequestInterface|null $request
     * @param string $method
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function __invoke(array $params = [], ServerRequestInterface $request = null, string $method = '')
    {
        if (!in_array($method, $this->crudMethods)) {
            throw new NotFoundHttpException("Can not find the route:" . $request->getUri()->getPath());
        }
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
        $res = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return CreateExt::create($model, $body);
        });
        if ($res === [0]) {
            throw new Exception("Failed to create the object for unknown reason.");
        }
        return $res;
    }

    /**
     * @param array $body
     * @param ServerRequestInterface|null $request
     * @return array
     */
    protected function update(array $body, ServerRequestInterface $request = null): array
    {
        $model = new $this->modelClass();
        $res = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return UpdateExt::update($model, $body, true);
        });
        if ($res === [0]) {
            throw new Exception("Failed to update the object for unknown reason.");
        }
        return $res;
    }

    /**
     * @param array $body
     * @param ServerRequestInterface|null $request
     * @return mixed
     */
    protected function delete(array $body, ServerRequestInterface $request = null)
    {
        $model = new $this->modelClass();
        $res = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return DeleteExt::delete($model, $body, true);
        });
        if ($res === [0]) {
            throw new Exception("Failed to delete the object for unknown reason.");
        }
        return $res;
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
        $id = ArrayHelper::getValue($filter, 'id');
        $alias = $this->buildFilter($filter);
        $keys = $this->modelClass::primaryKey();
        foreach ($keys as $index => $key) {
            $keys[$index] = $alias . '.' . $key;
        }
        if (count($keys) > 1 && $id !== null) {
            $values = explode(',', $id);
            if (count($keys) === count($values)) {
                $data = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->andWhere(array_combine($keys, $values))->cache($this->getDuration($request), $this->cache)->one();
            }
        } elseif ($id !== null) {
            $data = $model = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->andWhere(array_combine($keys, [$id]))->cache($this->getDuration($request), $this->cache)->one();
        } else {
            $data = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->one();
        }
        return empty($data) ? [] : $data;
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
        $this->queryKey && $filter = ArrayHelper::remove($filter, $this->queryKey, []);
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