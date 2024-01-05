<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SignOutService;

class SignOutController extends AbstractController
{
    /**
     * 微信小程序手机号登录退出
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function wxMiniProgramMobileSignOut(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $signOutService = new SignOutService();
            $result = $signOutService->wxMiniProgramMobileSignOut();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'wxMiniProgramMobileSignOut');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}


