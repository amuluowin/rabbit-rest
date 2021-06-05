<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Psr\Http\Message\ServerRequestInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\DB\DBHelper;
use stdClass;

abstract class RestJson extends ModelJson
{
    public function __invoke(ServerRequestInterface $request, string $method, string $db = null)
    {
        if ($db !== null) {
            return parent::__invoke($request, $method, $db);
        }
        $method = strtolower($method);
        $query = $request->getParsedBody();
        $body = $request->getQueryParams();
        $data = new stdClass();
        $data->share = ArrayHelper::remove($query, 'share');
        $data->params =  $query + $body;
        return $this->$method($data, $request);
    }

    protected function get(stdClass $data, ServerRequestInterface $request): array
    {
        $result = [];
        ArrayHelper::toArrayJson($data->params);
        wgeach($data->params, function (string $config, array $value) use ($request, &$result, $data) {
            $arr = explode(':', $config);
            if (count($arr) === 2) {
                [$key, $method] = $arr;
            } else {
                [$method] = $arr;
                $key = 'default';
            }
            wgeach($value, function (string $table, array $filter) use (&$result, $config, $key, $method, $request, $data) {
                $tableArr = explode(':', $table);
                $table = array_shift($tableArr);
                $alias = array_shift($tableArr);
                if ($alias) {
                    $filter['alias'] = $alias;
                }
                ArrayHelper::remove($filter, 'from');
                if (is_numeric($data->share) && (int)$data->share > 0) {
                    $name = "$config:$table:" . md5(\igbinary_serialize($filter));
                    $result["$config:$table"] = share($name, function () use ($key, $table, $filter, $request, $method) {
                        $page = ArrayHelper::remove($filter, 'page');
                        if ($page !== null) {
                            return DBHelper::SearchList(getDI('db')->get($key)->buildQuery()->from((array)$table), $filter, $page, $this->getDuration($request), $this->cache);
                        } else {
                            return DBHelper::Search(getDI('db')->get($key)->buildQuery()->from((array)$table), $filter)->cache($this->getDuration($request), $this->cache)->$method();
                        }
                    }, (int)$data->share)->result;
                } else {
                    $page = ArrayHelper::remove($filter, 'page');
                    if ($page !== null) {
                        $result["$config:$table"] = DBHelper::SearchList(getDI('db')->get($key)->buildQuery()->from((array)$table), $filter, $page, $this->getDuration($request), $this->cache);
                    } else {
                        $result["$config:$table"] = DBHelper::Search(getDI('db')->get($key)->buildQuery()->from((array)$table), $filter)->cache($this->getDuration($request), $this->cache)->$method();
                    }
                }
            });
        });
        return $result;
    }

    protected function post(stdClass $data, ServerRequestInterface $request): array
    {
        $result = [];
        foreach ($data->params as $model => $value) {
            $arr = explode(':', $model);
            if (count($arr) === 2) {
                [$key, $model] = $arr;
            } else {
                [$model] = $arr;
                $key = 'default';
            }
            $model = $this->ARClass::getModel($model, $key);
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
            $arr = explode(':', $model);
            if (count($arr) === 2) {
                [$key, $model] = $arr;
            } else {
                [$model] = $arr;
                $key = 'default';
            }
            $model = $this->ARClass::getModel($model, $key);
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
            $arr = explode(':', $model);
            if (count($arr) === 2) {
                [$key, $model] = $arr;
            } else {
                [$model] = $arr;
                $key = 'default';
            }
            $model = $this->ARClass::getModel($model, $key);
            $result[$model] = $model::getDb()->transaction(function () use ($model, $value) {
                return $this->ARClass::delete($model, $value);
            });
        }
        return $result;
    }
}
