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

use App\Service\CourseService;

class CourseController extends AbstractController
{
    /**
     * 门店排课日历
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function physicalStoreCourseOfflinePlanCalendar(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $physicalStoreId = $this->request->query('physical_store_id');
            $themeType = $this->request->query('theme_type');

            $params = ['physical_store_id'=>$physicalStoreId,'theme_type'=>$themeType];
            $courseService = new CourseService();
            $result = $courseService->physicalStoreCourseOfflinePlanCalendar($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'physicalStoreCourseOfflinePlanCalendar');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店线下课程排课
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function physicalStoreCourseOfflinePlan(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $physicalStoreId = $this->request->query('physical_store_id');
            $suitAge = $this->request->query('suit_age');
            $searchDate = $this->request->query('date');
            $themeType = $this->request->query('type');

            $params = ['physical_store_id'=>$physicalStoreId,'suit_age'=>$suitAge,'date'=>$searchDate,'type'=>$themeType];
            $courseService = new CourseService();
            $result = $courseService->physicalStoreCourseOfflinePlan($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'physicalStoreCourseOfflinePlan');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程批量约课详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineBatchReservationDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $courseOfflineId = $this->request->query('course_offline_id');
            $physicalStoreId = $this->request->query('physical_store_id');

            $params = ['course_offline_id'=>$courseOfflineId,'physical_store_id'=>$physicalStoreId];
            $courseService = new CourseService();
            $result = $courseService->courseOfflineBatchReservationDetail($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineBatchReservationDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }


    /**
     * 线下课程套餐(三级课程整套课程列表)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePackage(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $batchNo = $this->request->query('batch_no');

            $params = ['batch_no'=>$batchNo];
            $courseService = new CourseService();
            $result = $courseService->courseOfflinePackage($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePackage');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程排课详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflinePlanDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOfflinePlanDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflinePlanDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 添加收藏线上子课程学习作品
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addCourseOnlineChildCollectStudyOpus(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $courseService = new CourseService();
            $result = $courseService->addCourseOnlineChildCollectStudyOpus($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addCourseOnlineChildCollectStudyOpus');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线上课程列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $courseCategoryId = $this->request->query('course_category_id');
            $suitAge = $this->request->query('suit_age');

            $params = ['course_category_id'=>$courseCategoryId,'suit_age'=>$suitAge];
            $courseService = new CourseService();
            $courseService->offset = $offset;
            $courseService->limit = $pageSize;
            $result = $courseService->courseOnlineList($params);
            $data = $result['data']['list'];
            $data = [
                'list' => $data,
                'page' => ['page' => $page, 'page_size' => $pageSize, 'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线上课程详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOnlineDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineDetail');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线上子课程列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineChildList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $courseOnlineId = $this->request->query('course_online_id');

            $courseService = new CourseService();
            $result = $courseService->courseOnlineChildList((int)$courseOnlineId);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineChildList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线上子课程详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineChildDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOnlineChildDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineChildDetail');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 添加线上课程收藏
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addCourseOnlineCollect(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $courseService = new CourseService();
            $result = $courseService->addCourseOnlineCollect($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addCourseOnlineCollect');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线上课程收藏列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineCollectList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $studyStatus = $this->request->query('study_status',2);

            $params = ['study_status'=>$studyStatus];
            $courseService = new CourseService();
            $courseService->offset = $offset;
            $courseService->limit = $pageSize;
            $result = $courseService->courseOnlineCollectList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineCollectList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线上课程收藏详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineCollectDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOnlineCollectDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineChildCollectList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线上子课程收藏详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineChildCollectDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOnlineChildCollectDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineChildCollectDetail');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 删除线上课程收藏
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function deleteCourseOnlineCollect(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $id = $params['id'];
            $courseService = new CourseService();
            $result = $courseService->deleteCourseOnlineCollect((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'deleteCourseOnlineCollect');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线上课程学习成果分享
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineChildCollectStudyOpusShare(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $courseService = new CourseService();
            $result = $courseService->courseOnlineChildCollectStudyOpusShare((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineChildCollectStudyOpusShare');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 课程详情配置列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseDetailSetUpList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $courseService = new CourseService();
            $result = $courseService->courseDetailSetUpList();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseDetailSetUpList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程年龄标签数据
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineAgeTag(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $courseService = new CourseService();
            $result = $courseService->courseOfflineAgeTag();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineAgeTag');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
