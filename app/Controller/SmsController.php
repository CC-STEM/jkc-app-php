<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Controller;

use App\Service\SmsService;

class SmsController extends AbstractController
{
    /**
     * 登录短信验证码发送
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function loginSmsSend(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $mobile = $params['mobile'];
            $smsService = new SmsService();
            $result = $smsService->loginSmsSend($mobile);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'loginSmsSend');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
