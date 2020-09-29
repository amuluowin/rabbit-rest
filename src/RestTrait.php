<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Throwable;
use Rabbit\DB\DBHelper;
use Rabbit\Base\Core\Context;
use Rabbit\Base\Core\Exception;
use Rabbit\ActiveRecord\ARHelper;
use Psr\SimpleCache\CacheInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Rabbit\HttpServer\Exceptions\NotFoundHttpException;

/**
 * Trait RestTrait
 * @package Rabbit\Rest
 */
trait RestTrait
{
    protected ?CacheInterface $cache = null;
    protected $cacheCallback;
    protected string $ARClass = ARHelper::class;
    public static array $joinMap = [
        'lj' => '[<]',
        'rj' => '[>]',
        'fj' => '[<>]'
    ];

    protected array $crudMethods = ['create', 'update', 'delete', 'view', 'list', 'search', 'index'];
    protected ?string $queryKey = null;
    protected ?string $modelClass = null;
    protected array $replaceAlais = [];
    protected string $sceneKey = 'scene';

    public function __get($name)
    {
        return Context::get('crud.' . $name);
    }

    public function __set($name, $value)
    {
        Context::set('crud.' . $name, $value);
    }

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
        $this->$method($params, $request);
        return $this->result;
    }

    /**
     * @param array $body
     * @param ServerRequestInterface|null $request
     * @return array
     * @throws Exception
     */
    protected function create(array $body, ServerRequestInterface $request = null): void
    {
        $model = new $this->modelClass();
        $this->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::create($model, $body);
        });
    }

    /**
     * @param array $body
     * @param ServerRequestInterface|null $request
     * @return array
     * @throws Exception
     */
    protected function update(array $body, ServerRequestInterface $request = null): void
    {
        $model = new $this->modelClass();
        $this->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::update($model, $body, true);
        });
    }

    /**
     * @param array $body
     * @param ServerRequestInterface|null $request
     * @return mixed
     * @throws Exception
     */
    protected function delete(array $body, ServerRequestInterface $request = null): void
    {
        $model = new $this->modelClass();
        $this->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::delete($model, $body, true);
        });
    }

    /**
     * @param array $filter
     * @param ServerRequestInterface|null $request
     * @return array
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    protected function list(array $filter, ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter($filter);
        $this->result = DBHelper::Search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->all();
    }

    /**
     * @param array $filter
     * @param ServerRequestInterface|null $request
     * @return array
     */
    protected function index(array $filter, ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter($filter);
        $page = ArrayHelper::remove($filter, 'page', 0);
        $this->result = DBHelper::SearchList($this->modelClass::find()->alias($alias)->asArray(), $filter, $page, $this->getDuration($request), $this->cache);
    }

    /**
     * @param array $filter
     * @param ServerRequestInterface|null $request
     * @return array
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    protected function view(array $filter, ServerRequestInterface $request = null): void
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
            $data = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->andWhere(array_combine($keys, [$id]))->cache($this->getDuration($request), $this->cache)->one();
        } else {
            $data = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->one();
        }
        $this->result = empty($data) ? [] : $data;
    }

    /**
     * @param array $filter
     * @param ServerRequestInterface|null $request
     * @return mixed
     */
    protected function search(array $filter, ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter($filter);
        $method = ArrayHelper::remove($filter, 'method', 'all');
        $this->result = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->$method();
    }

    /**
     * @param array $filter
     * @return string
     */
    protected function buildFilter(array &$filter): string
    {
        ArrayHelper::toArrayJson($filter);
        $alias = explode('\\', get_class());
        $alias = str_replace([...$this->replaceAlais, 'crud'], '', strtolower(end($alias)));
        $this->queryKey && $filter = ArrayHelper::remove($filter, $this->queryKey, []);
        $select = ArrayHelper::remove($filter, 'select', ['*']);
        foreach ($select as $index => &$field) {
            if (strpos($field, '.') === false && strpos($field, '(') === false && is_int($index)) {
                $field = $alias . '.' . $field;
            }
        }
        $filter['select'] = $select;
        return $alias;
    }

    /**
     * @param ServerRequestInterface $request
     * @return int
     */
    protected function getDuration(ServerRequestInterface $request): int
    {
        $duration = -1;
        if (is_callable($this->cacheCallback)) {
            $duration = call_user_func($this->cacheCallback, $request);
        }
        return $duration === '' ? -1 : (int)$duration;
    }
}
