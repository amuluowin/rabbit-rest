<?php

namespace Rabbit\Rest;

use Psr\Http\Server\MiddlewareInterface;
use Rabbit\HttpServer\Middleware\AcceptTrait;

/**
 * Class ReqHandlerMiddleware
 * @package Rabbit\Rest
 */
abstract class ReqHandlerMiddleware implements MiddlewareInterface
{
    use AcceptTrait;

    public function __construct(protected string $prefix = '')
    {
    }
}
