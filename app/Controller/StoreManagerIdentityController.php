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

use App\Service\StoreManagerIdentityService;

class StoreManagerIdentityController extends AbstractController
{
    /**
     * 管理门店列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function managePhysicalStoreList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $longitude = $this->request->query('longitude');
            $latitude = $this->request->query('latitude');

            $params = ['longitude'=>$longitude,'latitude'=>$latitude];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->managePhysicalStoreList($params);
            $data = [
                'list' => $result['data'],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'managePhysicalStoreList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 指定门店
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectedPhysicalStore(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->selectedPhysicalStore($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'selectedPhysicalStore');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店老师列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function physicalStoreTeacherList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->physicalStoreTeacherList();
            $data = [
                'list' => $result['data'],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'physicalStoreTeacherList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店营收统计数据
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeRevenueStatistics(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $month = $this->request->query('month');

            $params = ['month'=>$month];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeRevenueStatistics($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeRevenueStatistics');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店今日统计数据
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeTodayStatistics(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeTodayStatistics();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeTodayStatistics');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店体验课
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeSampleCourseOfflineOrder(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $date = $this->request->query('date');

            $params = ['date'=>$date];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeSampleCourseOfflineOrder($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeSampleCourseOfflineOrder');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店每日统计数据
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeDailyStatistics(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $month = $this->request->query('month');

            $params = ['month'=>$month];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeDailyStatistics($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeDailyStatistics');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店每日明细数据
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeDailyDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $date = $this->request->query('date');
            $type = $this->request->query('type');
            $keywords = $this->request->query('keywords');
            $teacherId = $this->request->query('teacher_id');

            $params = [
                'date'=>$date,
                'type'=>$type,
                'keywords'=>$keywords,
                'teacher_id'=>$teacherId
            ];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeDailyDetail($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeDailyDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 排课详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePlanDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->courseOfflinePlanDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePlanDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店课表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeCurriculum(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $date = $this->request->query('date');

            $params = ['date'=>$date];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeCurriculum($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeCurriculum');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店经营分析
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeBusinessAnalysis(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $month = $this->request->query('month');

            $params = ['month'=>$month];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeBusinessAnalysis($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeBusinessAnalysis');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店老师管理
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeTeacherManage(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $month = $this->request->query('month');

            $params = ['month'=>$month];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeTeacherManage($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeTeacherManage');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店会员管理
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeMemberManage(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeMemberManage();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeMemberManage');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店会员列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeMemberList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $keywords = $this->request->query('keywords');
            $memberStatusType = $this->request->query('member_status_type');
            $teacherId = $this->request->query('teacher_id');

            $params = ['keywords'=>$keywords,'member_status_type'=>$memberStatusType,'teacher_id'=>$teacherId];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeMemberList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeMemberList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店会员详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeMemberDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeMemberDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeMemberDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店会员课程订单列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeMemberCourseOfflineOrderList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $memberId = $this->request->query('id');
            $status = $this->request->query('status');

            $params = ['id'=>$memberId,'status'=>$status];
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->storeMemberCourseOfflineOrderList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'storeMemberCourseOfflineOrderList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 设置老师营收目标
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function setTeacherRevenueTargetAmount(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->setTeacherRevenueTargetAmount($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'setTeacherRevenueTargetAmount');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 会员卡订单延期
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function vipCardOrderExtension(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $storeManagerIdentityService = new StoreManagerIdentityService();
            $result = $storeManagerIdentityService->vipCardOrderExtension($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'vipCardOrderExtension');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
