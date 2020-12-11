<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Rabbit\DB\DBHelper;
use Rabbit\ActiveRecord\ARHelper;
use Psr\SimpleCache\CacheInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Psr\Http\Message\ServerRequestInterface;
use Rabbit\HttpServer\Exceptions\NotFoundHttpException;
use stdClass;

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

    public function __invoke(array $params = [], ServerRequestInterface $request = null, string $method = '')
    {
        if (!in_array($method, $this->crudMethods)) {
            throw new NotFoundHttpException("Can not find the route:" . $request->getUri()->getPath());
        }
        $data = new stdClass();
        $data->params = $params;
        $this->$method($data, $request);
        return $data->result;
    }

    protected function create(stdClass $data, ServerRequestInterface $request = null): void
    {
        $body = $this->params;
        $model = new $this->modelClass();
        $data->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::create($model, $body);
        });
    }

    protected function update(stdClass $data, ServerRequestInterface $request = null): void
    {
        $body = $this->params;
        $model = new $this->modelClass();
        $data->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::update($model, $body, true);
        });
    }

    protected function delete(stdClass $data, ServerRequestInterface $request = null): void
    {
        $body = $this->params;
        $model = new $this->modelClass();
        $data->result = $this->modelClass::getDb()->transaction(function () use ($model, &$body) {
            return $this->ARClass::delete($model, $body, true);
        });
    }

    protected function list(stdClass $data, ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter($data);
        $data->result = DBHelper::Search($this->modelClass::find()->alias($alias)->asArray(), $data->params)->cache($this->getDuration($request), $this->cache)->all();
    }

    protected function index(stdClass $data, ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter($data);
        $page = ArrayHelper::remove($data->params, 'page', 0);
        $data->result = DBHelper::SearchList($this->modelClass::find()->alias($alias)->asArray(), $data->params, $page, $this->getDuration($request), $this->cache);
    }

    protected function view(stdClass $data, ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter($data);
        $id = ArrayHelper::getValue($data->params, 'id');
        $keys = $this->modelClass::primaryKey();
        foreach ($keys as $index => $key) {
            $keys[$index] = $alias . '.' . $key;
        }
        if (count($keys) > 1 && $id !== null) {
            $values = explode(',', $id);
            if (count($keys) === count($values)) {
                $data = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $data->params)->andWhere(array_combine($keys, $values))->cache($this->getDuration($request), $this->cache)->one();
            }
        } elseif ($id !== null) {
            $data = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $data->params)->andWhere(array_combine($keys, [$id]))->cache($this->getDuration($request), $this->cache)->one();
        } else {
            $data = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $data->params)->cache($this->getDuration($request), $this->cache)->one();
        }
        $data->result = empty($data) ? [] : $data;
    }

    protected function search(stdClass $data, ServerRequestInterface $request = null): void
    {
        $alias = $this->buildFilter($data);
        $method = ArrayHelper::remove($data->params, 'method', 'all');
        $data->result = DBHelper::search($this->modelClass::find()->alias($alias)->asArray(), $data->params)->cache($this->getDuration($request), $this->cache)->$method();
    }

    protected function buildFilter(stdClass $data): string
    {
        ArrayHelper::toArrayJson($data->params);
        $alias = explode('\\', get_class());
        $alias = str_replace([...$this->replaceAlais, 'crud'], '', strtolower(end($alias)));
        $this->queryKey && $data->params = ArrayHelper::remove($data->params, $this->queryKey, []);
        $select = ArrayHelper::remove($data->params, 'select', ['*']);
        foreach ($select as $index => &$field) {
            if (strpos($field, '.') === false && strpos($field, '(') === false && is_int($index)) {
                $field = $alias . '.' . $field;
            }
        }
        $data->params['select'] = $select;
        return $alias;
    }

    protected function getDuration(ServerRequestInterface $request): int
    {
        $duration = -1;
        if (is_callable($this->cacheCallback)) {
            $duration = call_user_func($this->cacheCallback, $request);
        }
        return $duration === '' ? -1 : (int)$duration;
    }
}
