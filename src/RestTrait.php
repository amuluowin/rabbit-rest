<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Rabbit\ActiveRecord\ARHelper;
use Rabbit\Base\Exception\InvalidArgumentException;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\DB\DBHelper;
use Rabbit\DB\Query;
use stdClass;

trait RestTrait
{
    protected ?string $queryKey = null;
    protected ?CacheInterface $cache = null;
    protected $cacheCallback;

    protected array $modelMap = [];
    protected string $ARClass = ARHelper::class;

    public function __invoke(ServerRequestInterface $request, string $method, string $db = 'db', string $key = 'default')
    {
        $method = strtolower($method);
        $data = new stdClass();
        $data->params = $request->getParsedBody() + $request->getQueryParams();
        return $this->$method($data, $request, $db, $key);
    }

    protected function get(stdClass $data, ServerRequestInterface $request, string $db, string $key): array
    {
        $result = [];
        ArrayHelper::toArrayJson($data->params);
        foreach ($data->params as $method => $value) {
            foreach ($value as $table => $filter) {
                $tableArr = explode(':', $table);
                $table = array_shift($tableArr);
                $alias = array_shift($tableArr);
                if ($alias) {
                    $filter['alias'] = $alias;
                }
                ArrayHelper::remove($filter, 'from');
                $page = ArrayHelper::remove($filter, 'page');
                if ($page !== null) {
                    $result[$table] = DBHelper::SearchList((new Query(getDI($db)->get($key)))->from((array)$table), $filter, $page, $this->getDuration($request), $this->cache);
                } else {
                    $result[$table] = DBHelper::Search((new Query(getDI($db)->get($key)))->from((array)$table), $filter)->cache($this->getDuration($request), $this->cache)->$method();
                }
            }
        }
        return $result;
    }

    protected function post(stdClass $data, ServerRequestInterface $request): array
    {
        $result = [];
        foreach ($data->params as $model => $value) {
            if (!isset($this->tableMap[$model])) {
                throw new InvalidArgumentException("Model not exists!");
            }
            $model = new $this->tableMap[$model]();
            $result[$model] = $model::getDb()->transaction(function () use ($model, $value) {
                return $this->ARClass::create($model, $value);
            });
        }
        return $result;
    }

    protected function put(stdClass $data, ServerRequestInterface $request): ?array
    {
        $result = [];
        foreach ($data->params as $model => $value) {
            if (!isset($this->tableMap[$model])) {
                throw new InvalidArgumentException("Model not exists!");
            }
            $model = new $this->tableMap[$model]();
            $result[$model] = $model::getDb()->transaction(function () use ($model, $value) {
                return $this->ARClass::update($model, $value, true);
            });
        }
        return $result;
    }

    protected function delete(stdClass $data, ServerRequestInterface $request)
    {
        $result = [];
        foreach ($data->params as $model => $value) {
            if (!isset($this->tableMap[$model])) {
                throw new InvalidArgumentException("Model not exists!");
            }
            $model = new $this->tableMap[$model]();
            $result[$model] = $model::getDb()->transaction(function () use ($model, $value) {
                return $this->ARClass::delete($model, $value);
            });
        }
        return $result;
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
