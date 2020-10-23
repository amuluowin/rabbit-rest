<?php

namespace Rabbit\Rest;

use Throwable;
use DI\NotFoundException;
use Rabbit\Base\Core\Context;
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
        if (!in_array($len, [2, 3])) {
            throw new NotFoundException("The route type error:" . $request->getUri()->getPath());
        }

        if ($len === 3 && in_array(strtolower(end($route)), $this->crudMethods)) {
            list($module, $model, $func) = $route;
            $class = 'Apis\\' . ucfirst($module) . "\\Handlers\\" . ucfirst($model) . "Crud";
        } else {
            list($module, $func) = $route;
            $class = 'Apis\\' . ucfirst($module) . "\\Handlers\\" . ucfirst($func);
        }

        // 校验路由所指定的类
        try {
            $class = getDI($class);
        } catch (\DI\NotFoundException $exception) {
            throw new NotFoundException("Can not find the route:" . $request->getUri()->getPath());
        } catch (\Error $error) {
            throw $error;
        }

        // 把GET和POST中的参数主体合并，POST的覆盖GET的
        $params = $request->getParsedBody() + $request->getQueryParams();

        /* @var ResponseInterface $response */
        $response = $class($params, $request, $func);
        if (!$response instanceof ResponseInterface) {
            /* @var ResponseInterface $newResponse */
            $newResponse = Context::get('response');
            return $this->handleAccept($request, $newResponse, $response);
        }

        return $handler->handle($request);
    }
}
