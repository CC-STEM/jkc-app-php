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

use App\Service\StudyPlanService;

class StudyPlanController extends AbstractController
{
    /**
     * 报名
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function studyPlanEnrollment(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $studyPlanService = new StudyPlanService();
            $result = $studyPlanService->studyPlanEnrollment($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'studyPlanEnrollment');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
