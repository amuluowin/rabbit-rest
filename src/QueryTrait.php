<?php

declare(strict_types=1);

namespace Rabbit\Rest;

use Psr\Http\Message\ServerRequestInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\DB\DBHelper;
use Rabbit\DB\Query;
use stdClass;

trait QueryTrait
{
    use RestTrait;

    protected string $db = 'db';
    protected string $key = 'default';

    protected function list(stdClass $data, ServerRequestInterface $request = null): array
    {
        $alias = $this->buildFilter($data);
        return DBHelper::Search((new Query(getDI($this->db)->get($this->key)))->alias($alias), $data->params)->cache($this->getDuration($request), $this->cache)->all();
    }

    protected function index(stdClass $data, ServerRequestInterface $request = null): array
    {
        $alias = $this->buildFilter($data);
        $page = ArrayHelper::remove($data->params, 'page', 0);
        return DBHelper::SearchList((new Query(getDI($this->db)->get($this->key)))->alias($alias), $data->params, $page, $this->getDuration($request), $this->cache);
    }

    protected function view(stdClass $data, ServerRequestInterface $request = null): ?array
    {
        $alias = $this->buildFilter($data);
        return DBHelper::search((new Query(getDI($this->db)->get($this->key)))->alias($alias), $data->params)->cache($this->getDuration($request), $this->cache)->one();
    }

    protected function search(stdClass $data, ServerRequestInterface $request = null)
    {
        $alias = $this->buildFilter($data);
        $method = ArrayHelper::remove($data->params, 'method', 'all');
        return DBHelper::search((new Query(getDI($this->db)->get($this->key)))->alias($alias), $data->params)->cache($this->getDuration($request), $this->cache)->$method();
    }
}
