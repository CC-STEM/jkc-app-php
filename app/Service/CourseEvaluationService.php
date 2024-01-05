<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Model\CourseOfflineOrder;
use App\Model\CourseOfflineOrderEvaluation;
use App\Snowflake\IdGenerator;
use Hyperf\Utils\Context;

class CourseEvaluationService extends BaseService
{

    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 添加线下课程评价
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addCourseOfflineOrderEvaluation(array $params): array
    {
        $memberId = $this->memberId;
        $courseOfflineOrderId = $params['course_offline_order_id'];

        $courseOfflineOrderInfo = CourseOfflineOrder::query()
            ->select(['teacher_id','physical_store_id'])
            ->where(['id'=>$courseOfflineOrderId])
            ->first();
        $courseOfflineOrderInfo = $courseOfflineOrderInfo->toArray();

        $insertCourseOfflineOrderEvaluationData['id'] = IdGenerator::generate();
        $insertCourseOfflineOrderEvaluationData['course_offline_order_id'] = $courseOfflineOrderId;
        $insertCourseOfflineOrderEvaluationData['member_id'] = $memberId;
        $insertCourseOfflineOrderEvaluationData['teacher_id'] = $courseOfflineOrderInfo['teacher_id'];
        $insertCourseOfflineOrderEvaluationData['physical_store_id'] = $courseOfflineOrderInfo['physical_store_id'];
        $insertCourseOfflineOrderEvaluationData['grade'] = $params['grade'];
        $insertCourseOfflineOrderEvaluationData['tag_text'] = $params['tag_text'];
        $insertCourseOfflineOrderEvaluationData['remark'] = $params['remark'];

        CourseOfflineOrderEvaluation::query()->insert($insertCourseOfflineOrderEvaluationData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 线下课程评价详情
     * @param int $courseOfflineOrderId
     * @return array
     */
    public function courseOfflineOrderEvaluationDetail(int $courseOfflineOrderId): array
    {
        $courseOfflineOrderEvaluationInfo = CourseOfflineOrderEvaluation::query()
            ->select(['grade','tag_text','remark'])
            ->where(['course_offline_order_id'=>$courseOfflineOrderId])
            ->first();
        $courseOfflineOrderEvaluationInfo = $courseOfflineOrderEvaluationInfo?->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineOrderEvaluationInfo];
    }
}