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

use App\Service\OrderService;

class OrderController extends AbstractController
{
    /**
     * 教具商品订单结算
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsGoodsOrderConfirm(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $orderService = new OrderService();
            $result = $orderService->teachingAidsGoodsOrderConfirm($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingAidsGoodsOrderConfirm');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 创建教具订单
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsGoodsCreateOrder(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $orderService = new OrderService();
            $result = $orderService->teachingAidsGoodsCreateOrder($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingAidsGoodsCreateOrder');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 教具订单列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsOrderList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $type = $this->request->query('type',0);

            $params = ['type'=>$type];
            $orderService = new OrderService();
            $orderService->offset = $offset;
            $orderService->limit = $pageSize;
            $result = $orderService->teachingAidsOrderList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingAidsOrderList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 教具订单详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsOrderDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');
            $orderService = new OrderService();
            $result = $orderService->teachingAidsOrderDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingAidsOrderDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 教具订单退款申请
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsOrderRefundApply(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $orderService = new OrderService();
            $result = $orderService->teachingAidsOrderRefundApply($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingAidsOrderRefundApply');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 添加教具订单退款寄件信息
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addTeachingAidsOrderRefundPackage(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $orderService = new OrderService();
            $result = $orderService->addTeachingAidsOrderRefundPackage($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addTeachingAidsOrderRefundPackage');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 取消教具订单退款申请
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function cancelTeachingAidsOrderRefundApply(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $orderService = new OrderService();
            $result = $orderService->cancelTeachingAidsOrderRefundApply($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'cancelTeachingAidsOrderRefundApply');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 退款原因
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function refundReasonList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $orderService = new OrderService();
            $result = $orderService->refundReasonList();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'refundReasonList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
