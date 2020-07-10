<?php
declare(strict_types=1);

namespace Rabbit\Rest;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rabbit\Base\Helper\ArrayHelper;
use Rabbit\Base\Helper\JsonHelper;

/**
 * Class StartMiddleware
 * @package Rabbit\Rest
 */
class StartMiddleware implements MiddlewareInterface
{

    /**
     * process
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $result = $response->getAttribute(AttributeEnum::RESPONSE_ATTRIBUTE);
        // Headers
        $response = $response->withoutHeader('Content-Type')->withAddedHeader('Content-Type', 'application/json');
        $response = $response->withCharset($response->getCharset() ?? "UTF-8");
        // Content
        $data = [
            'code' => 0,
            'msg' => 'success',
            'result' => $result
        ];
        $data = ArrayHelper::toArray($data);
        $content = JsonHelper::encode($data, JSON_UNESCAPED_UNICODE);
        $response = $response->withContent($content);
        return $response;
    }
}
