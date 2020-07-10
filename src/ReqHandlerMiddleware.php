<?php

namespace Rabbit\Rest;

use DI\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rabbit\Base\Core\Context;
use Rabbit\Web\AttributeEnum;
use Throwable;

/**
 * Class ReqHandlerMiddleware
 * @package Rabbit\Rest
 */
class ReqHandlerMiddleware implements MiddlewareInterface
{
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
            list($module, $model, $handler) = $route;
            $class = 'Apis\\' . ucfirst($module) . "\\Handlers\\" . ucfirst($model) . "Crud";
        } else {
            list($module, $handler) = $route;
            $class = 'Apis\\' . ucfirst($module) . "\\Handlers\\" . ucfirst($handler);
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
        $response = $class($params, $request, $handler);
        if (!$response instanceof ResponseInterface) {
            /* @var ResponseInterface $newResponse */
            $newResponse = Context::get('response');
            $response = $newResponse->withAttribute(AttributeEnum::RESPONSE_ATTRIBUTE, $response);
        }

        return $response;
    }
}
