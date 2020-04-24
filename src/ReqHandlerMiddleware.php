<?php

namespace Rabbit\Rest;

use common\Exception\NotFoundException;
use common\RequestHandlers\SingletonInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use rabbit\core\Context;
use rabbit\server\AttributeEnum;

/**
 * Class ReqHandlerMiddleware
 * @package Rabbit\Rest
 */
class ReqHandlerMiddleware extends \rabbit\auth\middleware\ReqHandlerMiddleware
{
    protected $crudMethods = ['create', 'update', 'delete', 'view', 'list', 'search', 'index'];

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws NotFoundException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 获取访问接口地址
        $url = $request->getUri()->getPath();
        // 解析路由
        $route = explode('/', ltrim($url, '/'));
        $len = count($route);
        if (!in_array($len, [3, 4]) || $route[0] !== 'api') {
            throw new NotFoundException("The route type error:" . $request->getUri()->getPath());
        }

        if ($len === 4 && in_array(strtolower(end($route)), $this->crudMethods)) {
            list(, $module, $model, $handler) = $route;
            $class = 'Apis\\' . ucfirst($module) . "\\Handlers\\" . ucfirst($model) . "Crud";
        } else {
            list(, $module, $handler) = $route;
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

        if (!$class instanceof SingletonInterface) {
            $invoker = clone $class;
        } else {
            $invoker = $class;
        }

        // 把GET和POST中的参数主体合并，POST的覆盖GET的
        $params = $request->getParsedBody() + $request->getQueryParams();

        /* @var ResponseInterface $response */
        $response = $invoker($params, $request, $handler);
        if (!$response instanceof ResponseInterface) {
            /* @var ResponseInterface $newResponse */
            $newResponse = Context::get('response');
            $response = $newResponse->withAttribute(AttributeEnum::RESPONSE_ATTRIBUTE, $response);
        }

        return $response;
    }
}
