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

use App\Service\CourseEvaluationService;

class CourseEvaluationController extends AbstractController
{
    /**
     * 添加线下课程评价
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addCourseOfflineOrderEvaluation(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $courseEvaluationService = new CourseEvaluationService();
            $result = $courseEvaluationService->addCourseOfflineOrderEvaluation($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addCourseOfflineOrderEvaluation');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 线下课程评价详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineOrderEvaluationDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $courseOfflineOrderId = $this->request->query('course_offline_order_id');
            $courseEvaluationService = new CourseEvaluationService();
            $result = $courseEvaluationService->courseOfflineOrderEvaluationDetail((int)$courseOfflineOrderId);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOfflineOrderEvaluationDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
