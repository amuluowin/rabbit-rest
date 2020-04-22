<?php
declare(strict_types=1);

namespace Rabbit\Rest;

use DI\DependencyException;
use DI\NotFoundException;
use rabbit\db\Connection;

/**
 * Class Search
 * @package Rabbit\Rest
 */
class Search
{
    /**
     * @param Connection $connection
     * @param string $table
     * @param array $body
     * @param string $method
     * @return mixed
     * @throws DependencyException
     * @throws NotFoundException
     */
    public static function run(Connection $connection, string $table, array $body = [], string $method = 'queryAll')
    {
        return $connection->createCommandExt(['select', array_merge([$table], $body)])->$method();
    }

}
