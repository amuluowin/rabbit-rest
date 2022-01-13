<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Rabbit\Base\Exception\InvalidArgumentException;
use Rabbit\DB\DBHelper;
use Rabbit\ActiveRecord\ARHelper;
use Psr\SimpleCache\CacheInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Psr\Http\Message\ServerRequestInterface;
use Rabbit\HttpServer\Exceptions\NotFoundHttpException;
use stdClass;

abstract class ModelJson
{
    protected ?CacheInterface $cache = null;
    protected $cacheCallback;
    protected string $ARClass = ARHelper::class;
    protected ?string $queryKey = null;
    protected string $beforeKey = 'before';
    protected string $afterKey = 'after';
    public array $modelMap = [];
    protected string $listKey = 'data';
    protected string $totalKey = 'total';

    public function __invoke(ServerRequestInterface $request, string $method, string $model)
    {
        $data = new stdClass();
        $data->params = $request->getParsedBody() + $request->getQueryParams();

        if (!($this->modelMap[$model] ?? false)) {
            throw new InvalidArgumentException("Model not exists!");
        }
        if (!in_array($method, $this->modelMap[$model]->getMethods())) {
            throw new NotFoundHttpException("The route type error:" . $request->getUri()->getPath());
        }
        $func = function () use ($model, $method, $data, $request) {
            ArrayHelper::toArrayJson($data->params);

            $before = $this->modelMap[$model]->getEvents()?->getBefore();
            $beforeScene = ArrayHelper::remove($data->params, $this->beforeKey);
            if ($before && isset($before[$method]) && is_callable($before[$method])) {
                $before[$method]($data, $request);
            }
            if ($before && isset($before[$beforeScene]) && is_callable($before[$beforeScene])) {
                $before[$beforeScene]($data, $request);
            }
            
            if (in_array($method, ['list', 'index', 'view', 'search'])) {
                $this->buildFilter($data, $model);
            }

            $after = $this->modelMap[$model]->getEvents()?->getAfter();
            $afterScene = ArrayHelper::remove($data->params, $this->afterKey);
            $data->result = $this->$method($data, $request, $this->modelMap[$model], $model);
            if ($after && isset($after[$method]) && is_callable($after[$method])) {
                $after[$method]($data, $request);
            }
            if ($after && isset($after[$afterScene]) && is_callable($after[$afterScene])) {
                $after[$afterScene]($data, $request);
            }
            return $data;
        };
        if (in_array($method, RestEntry::SHARE_FUNC)) {
            $key = $method . ':' . md5(\igbinary_serialize($data->params));
            $data = share($key, $func, $this->modelMap[$model]->getShareTimeout() ?? 3)->result;
        } else {
            $data = $func();
        }
        return $data->result;
    }

    protected function create(stdClass $data, ServerRequestInterface $request, RestEntry $entry): array
    {
        $class = $entry->getClass();
        $model = new $class();
        return $this->ARClass::create($model, $data->params);
    }

    protected function save(stdClass $data, ServerRequestInterface $request, RestEntry $entry): array
    {
        $class = $entry->getClass();
        $model = new $class();
        return $this->ARClass::update($model, $data->params);
    }

    protected function update(stdClass $data, ServerRequestInterface $request, RestEntry $entry): array
    {
        $class = $entry->getClass();
        $model = new $class();
        return $this->ARClass::update($model, $data->params, true);
    }

    protected function del(stdClass $data, ServerRequestInterface $request, RestEntry $entry): int
    {
        $class = $entry->getClass();
        $model = new $class();
        return $this->ARClass::delete($model, $data->params);
    }

    protected function list(stdClass $data, ServerRequestInterface $request, RestEntry $entry, string $alias): array
    {
        return $entry->getRuleQuery(DBHelper::Search(create($entry->getClass())->find()->alias($alias), $data->params))->cache($this->getDuration($request), $this->cache)->all();
    }

    protected function index(stdClass $data, ServerRequestInterface $request, RestEntry $entry, string $alias): array
    {
        $page = ArrayHelper::remove($data->params, 'page', 0);
        $limit = ArrayHelper::remove($data->params, 'limit', 20);
        $offset = ArrayHelper::remove($data->params, 'offset', ($page ? ($page - 1) : 0) * (int)$limit);
        $count = ArrayHelper::remove($data->params, 'count', '1');
        $query = $entry->getRuleQuery(DBHelper::Search(create($entry->getClass())->find()->alias($alias), $data->params));
        $duration = $this->getDuration($request);
        $rows = $query->cache($duration, $this->cache)->limit($limit)->offset($offset)->all();
        if ($limit) {
            $query->limit = null;
            $query->offset = null;
            $total = $query->cache($duration, $this->cache)->count($count);
        } else {
            $total = count($rows);
        }
        return [$this->totalKey => $total, $this->listKey => $rows];
    }

    protected function view(stdClass $data, ServerRequestInterface $request, RestEntry $entry, string $alias): ?array
    {
        $id = ArrayHelper::getValue($data->params, 'id');
        $keys = $entry->getClass()::primaryKey();
        foreach ($keys as $index => $key) {
            $keys[$index] = $alias . '.' . $key;
        }
        if (count($keys) > 1 && $id !== null) {
            $values = explode(',', $id);
            if (count($keys) === count($values)) {
                return $entry->getRuleQuery(DBHelper::search(create($entry->getClass())->find()->alias($alias), $data->params)->andWhere(array_combine($keys, $values)))->cache($this->getDuration($request), $this->cache)->one();
            }
        } elseif ($id !== null) {
            return $entry->getRuleQuery(DBHelper::search(create($entry->getClass())->find()->alias($alias), $data->params)->andWhere(array_combine($keys, [$id])))->cache($this->getDuration($request), $this->cache)->one();
        } else {
            return $entry->getRuleQuery(DBHelper::search(create($entry->getClass())->find()->alias($alias), $data->params))->cache($this->getDuration($request), $this->cache)->one();
        }
    }

    protected function search(stdClass $data, ServerRequestInterface $request, RestEntry $entry, string $alias)
    {
        $method = ArrayHelper::remove($data->params, 'method', 'all');
        return $entry->getRuleQuery(DBHelper::search(create($entry->getClass())->find()->alias($alias), $data->params))->cache($this->getDuration($request), $this->cache)->$method();
    }

    protected function buildFilter(stdClass $data, string $alias): void
    {
        $this->queryKey && $data->params = ArrayHelper::remove($data->params, $this->queryKey, []);
        $select = ArrayHelper::remove($data->params, 'select', ['*']);
        foreach ($select as $index => &$field) {
            if (is_string($field) && strpos($field, '.') === false && is_int($index)) {
                $field = $alias . '.' . $field;
            }
        }
        $data->params['select'] = $select;
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
