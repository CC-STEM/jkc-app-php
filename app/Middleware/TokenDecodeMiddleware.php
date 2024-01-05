<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Token\Jwt;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Utils\Context;

class TokenDecodeMiddleware implements MiddlewareInterface
{

    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @var HttpResponse
     */
    protected HttpResponse $response;

    /**
     * TokenDecodeMiddleware constructor.
     * @param ContainerInterface $container
     * @param HttpResponse $response
     * @param RequestInterface $request
     */
    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request)
    {
        $this->container = $container;
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->request->getHeaderLine('authorization');
        if(empty($token)){
            return $handler->handle($request);
        }
        $jwt = new Jwt();
        $checkTokenResult = $jwt->checkToken($token);
        $claimValue = $checkTokenResult['data'];
        $memberId = $claimValue['memberId'] ?? 0;
        Context::set('MemberId', $memberId);
        Context::set('Authorization', $token);

        return $handler->handle($request);
    }
}


