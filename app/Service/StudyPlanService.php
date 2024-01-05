<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Model\StudyPlanEnrollment;
use App\Snowflake\IdGenerator;
use Hyperf\Utils\Context;

class StudyPlanService extends BaseService
{

    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 学习计划报名
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function studyPlanEnrollment(array $params): array
    {
        $type = $params['type'];
        $memberId = $this->memberId;

        $insertStudyPlanEnrollmentData['id'] = IdGenerator::generate();
        $insertStudyPlanEnrollmentData['member_id'] = $memberId;
        $insertStudyPlanEnrollmentData['type'] = $type;
        StudyPlanEnrollment::query()->insert($insertStudyPlanEnrollmentData);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

}