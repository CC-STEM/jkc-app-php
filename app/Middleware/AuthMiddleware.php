<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Cache\MemberCache;
use App\Constants\ErrorCode;
use Hyperf\Di\Annotation\Inject;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\HttpServer\Router\Dispatched;

class AuthMiddleware implements MiddlewareInterface
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

    #[Inject]
    protected MemberCache $memberCache;

    /**
     * AuthMiddleware constructor.
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
        //此行代码用于联调
        //Context::set('MemberId', 417650201952550913);
        //return $handler->handle($request);

        if(empty($this->request->getAttribute(Dispatched::class)->handler)){
            $response = ['code' => ErrorCode::NOT_FOUND, 'data' => null, 'msg' => ErrorCode::getMessage(ErrorCode::NOT_FOUND)];
            return $this->response->withStatus(ErrorCode::NOT_FOUND)->json($response);
        }
        $response = ['code' => ErrorCode::UNAUTHORIZED, 'data' => null, 'msg' => ErrorCode::getMessage(ErrorCode::UNAUTHORIZED)];
        $token = $this->request->getHeaderLine('authorization');
        //客户端:1 小程序，2 APP，3 PC
        //$clientType = $this->request->getHeaderLine('client-type') ?? 1;

        if(empty($token)){
            return $this->response->withStatus(ErrorCode::SUCCESS)->json($response);
        }
        $md5Token = md5($token);
        $checkTokenResult = $this->memberCache->existsAuthTokenWxMini($md5Token);
        if($checkTokenResult === 0){
            return $this->response->withStatus(ErrorCode::SUCCESS)->json($response);
        }
        return $handler->handle($request);
    }
}


