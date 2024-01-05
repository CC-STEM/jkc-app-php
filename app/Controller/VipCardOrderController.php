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

use App\Service\VipCardOrderService;

class VipCardOrderController extends AbstractController
{
    /**
     * 会员卡订单结算
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function vipCardOrderConfirm(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $vipCardOrderService = new VipCardOrderService();
            $result = $vipCardOrderService->vipCardOrderConfirm($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'vipCardOrderConfirm');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 创建会员卡订单
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function vipCardOrderCreate(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $vipCardOrderService = new VipCardOrderService();
            $result = $vipCardOrderService->vipCardOrderCreate($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'vipCardOrderCreate');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 会员卡订单列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function vipCardOrderList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $vipCardOrderService = new VipCardOrderService();
            $vipCardOrderService->offset = $offset;
            $vipCardOrderService->limit = $pageSize;
            $result = $vipCardOrderService->vipCardOrderList();
            $data = [
                'list' => $result['data'],
                'page' => ['page' => $page, 'page_size' => $pageSize],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'vipCardOrderList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
