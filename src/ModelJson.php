<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Rabbit\Base\Exception\InvalidArgumentException;
use Rabbit\DB\DBHelper;
use Rabbit\ActiveRecord\ARHelper;
use Psr\SimpleCache\CacheInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Psr\Http\Message\ServerRequestInterface;
use Rabbit\ActiveRecord\BaseActiveRecord;
use Rabbit\HttpServer\Exceptions\NotFoundHttpException;
use stdClass;

class ModelJson
{
    protected ?CacheInterface $cache = null;
    protected $cacheCallback;
    protected string $ARClass = ARHelper::class;
    protected ?string $queryKey = null;
    protected array $replaceAlais = [];
    protected string $sceneKey = 'scene';
    public array $modelMap = [];

    public function __invoke(ServerRequestInterface $request, string $method, string $model)
    {
        $data = new stdClass();
        $data->params = $request->getParsedBody() + $request->getQueryParams();
        if (!isset($this->modelMap[$model])) {
            throw new InvalidArgumentException("Model not exists!");
        }
        if (!in_array($method, $this->modelMap[$model]['methods'])) {
            throw new NotFoundHttpException("The route type error:" . $request->getUri()->getPath());
        }
        $class = $this->modelMap[$model]['class'];
        return $this->$method($data, $request, new $class());
    }

    protected function create(stdClass $data, ServerRequestInterface $request, BaseActiveRecord $model): array
    {
        return $model::getDb()->transaction(function () use ($model, $data) {
            return $this->ARClass::create($model, $data->params);
        });
    }

    protected function update(stdClass $data, ServerRequestInterface $request, BaseActiveRecord $model): array
    {
        return $model::getDb()->transaction(function () use ($model, $data) {
            return $this->ARClass::update($model, $data->params, true);
        });
    }

    protected function del(stdClass $data, ServerRequestInterface $request, BaseActiveRecord $model): int
    {
        return $model::getDb()->transaction(function () use ($model, $data) {
            return $this->ARClass::delete($model, $data->params);
        });
    }

    protected function list(stdClass $data, ServerRequestInterface $request, BaseActiveRecord $model): array
    {
        $alias = $this->buildFilter($data, $model);
        return DBHelper::Search($model::find()->alias($alias)->asArray(), $data->params)->cache($this->getDuration($request), $this->cache)->all();
    }

    protected function index(stdClass $data, ServerRequestInterface $request, BaseActiveRecord $model): array
    {
        $alias = $this->buildFilter($data, $model);
        $page = ArrayHelper::remove($data->params, 'page', 0);
        return DBHelper::SearchList($model::find()->alias($alias)->asArray(), $data->params, $page, $this->getDuration($request), $this->cache);
    }

    protected function view(stdClass $data, ServerRequestInterface $request, BaseActiveRecord $model): ?array
    {
        $alias = $this->buildFilter($data, $model);
        $id = ArrayHelper::getValue($data->params, 'id');
        $keys = $model::primaryKey();
        foreach ($keys as $index => $key) {
            $keys[$index] = $alias . '.' . $key;
        }
        if (count($keys) > 1 && $id !== null) {
            $values = explode(',', $id);
            if (count($keys) === count($values)) {
                return DBHelper::search($model::find()->alias($alias)->asArray(), $data->params)->andWhere(array_combine($keys, $values))->cache($this->getDuration($request), $this->cache)->one();
            }
        } elseif ($id !== null) {
            return DBHelper::search($model::find()->alias($alias)->asArray(), $data->params)->andWhere(array_combine($keys, [$id]))->cache($this->getDuration($request), $this->cache)->one();
        } else {
            return DBHelper::search($model::find()->alias($alias)->asArray(), $data->params)->cache($this->getDuration($request), $this->cache)->one();
        }
    }

    protected function search(stdClass $data, ServerRequestInterface $request, BaseActiveRecord $model)
    {
        $alias = $this->buildFilter($data, $model);
        $method = ArrayHelper::remove($data->params, 'method', 'all');
        return DBHelper::search($model::find()->alias($alias)->asArray(), $data->params)->cache($this->getDuration($request), $this->cache)->$method();
    }

    protected function buildFilter(stdClass $data, BaseActiveRecord $model): string
    {
        ArrayHelper::toArrayJson($data->params);
        $alias = explode('\\', get_class($model));
        $alias = str_replace($this->replaceAlais, '', strtolower(end($alias)));
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
