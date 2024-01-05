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

use App\Service\DiscountTicketService;
use App\Service\HomeService;

class DiscountTicketController extends AbstractController
{
    /**
     * 减免券活动信息
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function discountTicketMarketingInfo(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $discountTicketService = new DiscountTicketService();
            $result = $discountTicketService->discountTicketMarketingInfo();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'discountTicketMarketingInfo');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
    /**
     * 减免券活动参与信息
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function discountTicketParticipateInfo(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $discountTicketService = new DiscountTicketService();
            $result = $discountTicketService->discountTicketParticipateInfo();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'discountTicketParticipateInfo');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 减免券列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function discountTicketList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $type = $this->request->query('type',1);

            $params = ['type'=>$type];
            $discountTicketService = new DiscountTicketService();
            $result = $discountTicketService->discountTicketList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'discountTicketList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
