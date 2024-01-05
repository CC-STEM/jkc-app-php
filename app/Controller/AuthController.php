<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

class AuthController extends AbstractController
{
    /**
     * @var ValidatorFactoryInterface
     */
    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;

    /**
     * 微信小程序会话session
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function wxMiniProgramSession(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $authService = new AuthService();
            $result = $authService->wxMiniProgramSession($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'wxMiniProgramSession');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 微信小程序手机号登录
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function wxMiniProgramMobile(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $authService = new AuthService();
            $result = $authService->wxMiniProgramMobile($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'wxMiniProgramMobile');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}


