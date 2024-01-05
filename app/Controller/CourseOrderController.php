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

use App\Service\CourseOrderService;

class CourseOrderController extends AbstractController
{
    /**
     * 线下课程订单信息确认
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineConfirmOrder(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();

            $courseOrderService = new CourseOrderService();
            $result = $courseOrderService->courseOfflineConfirmOrder($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineConfirmOrder');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 创建线下课程订单
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineCreateOrder(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();

            $courseOrderService = new CourseOrderService();
            $result = $courseOrderService->courseOfflineCreateOrder($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineCreateOrder');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程订单列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineOrderList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $longitude = $this->request->query('longitude');
            $latitude = $this->request->query('latitude');
            $searchStatus = $this->request->query('status');

            $params = ['longitude'=>$longitude,'latitude'=>$latitude,'status'=>$searchStatus];
            $courseOrderService = new CourseOrderService();
            $result = $courseOrderService->courseOfflineOrderList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineOrderList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程课后反馈列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineFeedbackList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $courseOrderService = new CourseOrderService();
            $result = $courseOrderService->courseOfflineFeedbackList();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineFeedbackList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程订单调课
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineOrderReadjust(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $courseOrderService = new CourseOrderService();
            $result = $courseOrderService->courseOfflineOrderReadjust($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineOrderReadjust');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程订单取消
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineOrderCancel(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $courseOrderService = new CourseOrderService();
            $result = $courseOrderService->courseOfflineOrderCancel($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineOrderCancel');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
