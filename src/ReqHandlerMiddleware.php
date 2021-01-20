<?php

namespace Rabbit\Rest;

use Throwable;
use DI\NotFoundException;
use Rabbit\Web\ResponseContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rabbit\HttpServer\Middleware\AcceptTrait;

/**
 * Class ReqHandlerMiddleware
 * @package Rabbit\Rest
 */
class ReqHandlerMiddleware implements MiddlewareInterface
{
    use AcceptTrait;

    protected array $crudMethods = ['create', 'update', 'delete', 'view', 'list', 'search', 'index'];
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

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取访问接口地址
        $url = $request->getUri()->getPath();
        $url = str_replace($this->prefix, '', $url);
        // 解析路由
        $route = explode('/', ltrim($url, '/'));
        $len = count($route);
        if ($len > 3) {
            throw new NotFoundException("The route type error:" . $request->getUri()->getPath());
        }

        $args = [
            $request
        ];
        if ($len === 3 && in_array(strtolower(end($route)), $this->crudMethods)) {
            list($module, $model, $func) = $route;
            $class = 'Apis\\' . ucfirst($module) . "\\Handlers\\" . ucfirst($model) . "Crud";
        } elseif ($len === 2) {
            list($module, $func) = $route;
            $class = 'Apis\\' . ucfirst($module) . "\\Handlers\\" . ucfirst($func);
        } else {
            $db = explode('-', array_shift($route));
            if (count($db) === 1) {
                $db = array_shift($db);
                $db = empty($db) ? 'db' : $db;
                $key = 'Default';
            } else {
                [$db, $key] = $db;
            }
            $func = $request->getMethod();
            $class = BaseApi::class;
            $args = [...$args, $func, $db, $key];
        }

        // 校验路由所指定的类
        try {
            $class = getDI($class);
        } catch (\DI\NotFoundException $exception) {
            throw new NotFoundException("Can not find the route:" . $request->getUri()->getPath());
        } catch (\Error $error) {
            throw $error;
        }

        /* @var ResponseInterface $response */
        $response = $class(...$args);
        if (!$response instanceof ResponseInterface) {
            /* @var ResponseInterface $newResponse */
            $newResponse = ResponseContext::get();
            return $this->handleAccept($request, $newResponse, $response);
        }

        return $handler->handle($request);
    }
}
