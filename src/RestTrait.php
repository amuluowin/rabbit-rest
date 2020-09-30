<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Rabbit\DB\DBHelper;
use Rabbit\Base\Core\CoObject;
use Rabbit\ActiveRecord\ARHelper;
use Psr\SimpleCache\CacheInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Psr\Http\Message\ServerRequestInterface;
use Rabbit\HttpServer\Exceptions\NotFoundHttpException;

/**
 * Trait RestTrait
 * @package Rabbit\Rest
 */
trait RestTrait
{
    use CoObject;
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

    /**
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @param array $params
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param string $method
     * @return void
     */
    public function __invoke(array $params = [], ServerRequestInterface $request = null, string $method = '')
    {
        if (!in_array($method, $this->crudMethods)) {
            throw new NotFoundHttpException("Can not find the route:" . $request->getUri()->getPath());
        }
        $this->params = $params;
        $this->$method($request);
        return $this->result;
    }

    /**
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return void
     */
    protected function create(ServerRequestInterface $request = null): void
    {
        $body = $this->params;
        $model = new $this->modelClass();
        $this->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::create($model, $body);
        });
    }

    /**
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return void
     */
    protected function update(ServerRequestInterface $request = null): void
    {
        $body = $this->params;
        $model = new $this->modelClass();
        $this->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::update($model, $body, true);
        });
    }

    /**
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return void
     */
    protected function delete(ServerRequestInterface $request = null): void
    {
        $body = $this->params;
        $model = new $this->modelClass();
        $this->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::delete($model, $body, true);
        });
    }

    /**
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return void
     */
    protected function list(ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter();
        $filter = $this->params;
        $this->result = DBHelper::Search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->all();
    }

    /**
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return void
     */
    protected function index(ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter();
        $filter = $this->params;
        $page = ArrayHelper::remove($filter, 'page', 0);
        $this->result = DBHelper::SearchList($this->modelClass::find()->alias($alias)->asArray(), $filter, $page, $this->getDuration($request), $this->cache);
    }

    /**
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return void
     */
    protected function view(ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter();
        $filter = $this->params;
        $id = ArrayHelper::getValue($filter, 'id');
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
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return void
     */
    protected function search(ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter();
        $filter = $this->params;
        $method = ArrayHelper::remove($filter, 'method', 'all');
        $this->result = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $filter)->cache($this->getDuration($request), $this->cache)->$method();
    }

    /**
     * @Author Albert 63851587@qq.com
     * @DateTime 2020-09-30
     * @return string
     */
    protected function buildFilter(): string
    {
        $filter = $this->params;
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
        $this->params = $filter;
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
