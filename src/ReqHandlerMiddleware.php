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
    /** @var string */
    protected string $prefix = '';

    /**
     * ReqHandlerMiddleware constructor.
     * @param string $prefix
     */
    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }
}
