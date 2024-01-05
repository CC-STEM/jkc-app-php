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

use App\Service\MessageService;

class MessageController extends AbstractController
{
    /**
     * 小程序订阅消息
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function mpSubscribeMessage(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $messageService = new MessageService();
            $result = $messageService->mpSubscribeMessage($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'mpSubscribeMessage');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
