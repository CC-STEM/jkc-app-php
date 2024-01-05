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

use App\Service\TeacherIdentityService;

class TeacherIdentityController extends AbstractController
{
    /**
     * 老师薪资数据统计
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherSalaryStatistics(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $teacherIdentityService = new TeacherIdentityService();
            $result = $teacherIdentityService->teacherSalaryStatistics();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teacherSalaryStatistics');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 老师薪资数据详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherSalaryDetailed(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $month = $this->request->query('month');

            $params = ['month'=>$month];
            $teacherIdentityService = new TeacherIdentityService();
            $result = $teacherIdentityService->teacherSalaryDetailed($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teacherSalaryDetailed');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 老师薪资详情列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherSalaryDetailedList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $id = $this->request->query('id');
            $type = $this->request->query('type');

            $params = [
                'id'=>$id,
                'type'=>$type
            ];
            $teacherIdentityService = new TeacherIdentityService();
            $teacherIdentityService->offset = $offset;
            $teacherIdentityService->limit = $pageSize;
            $result = $teacherIdentityService->teacherSalaryDetailedList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count' => $result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'salaryBillDetailedList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 老师最近上课数据统计
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherCourseStatistics(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $teacherIdentityService = new TeacherIdentityService();
            $result = $teacherIdentityService->teacherCourseStatistics();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teacherCourseStatistics');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 老师课程列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherCourseList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $type = $this->request->query('type',0);

            $params = ['type'=>$type];
            $teacherIdentityService = new TeacherIdentityService();
            $teacherIdentityService->offset = $offset;
            $teacherIdentityService->limit = $pageSize;
            $result = $teacherIdentityService->teacherCourseList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teacherCourseList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 老师课程详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherCourseDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $teacherIdentityService = new TeacherIdentityService();
            $result = $teacherIdentityService->teacherCourseDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teacherCourseDetail');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 老师点名
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherRollCall(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $teacherIdentityService = new TeacherIdentityService();
            $result = $teacherIdentityService->teacherRollCall($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teacherRollCall');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 添加课堂情景图片
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addClassroomSituation(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $teacherIdentityService = new TeacherIdentityService();
            $result = $teacherIdentityService->addClassroomSituation($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addClassroomSituation');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 老师身份信息
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teacherIdentityInfo(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $teacherIdentityService = new TeacherIdentityService();
            $result = $teacherIdentityService->teacherIdentityInfo();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teacherIdentityInfo');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
