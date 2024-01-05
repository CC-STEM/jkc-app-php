<?php

declare(strict_types=1);
namespace App\Aspect;

use App\Constants\ErrorCode;
use App\Model\AsyncTask;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

#[Aspect]
class CourseSalaryCalculateAspect extends AbstractAspect
{
    public $classes = [
        'App\Service\TeacherIdentityService::addClassroomSituation',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $params = $proceedingJoinPoint->getArguments();
        $params = $params[0];
        $courseOfflinePlanId = $params['id'];
        $scanAt = date('Y-m-d H:i:s',strtotime('+1 minute'));
        $data = ['course_offline_plan_id'=>$courseOfflinePlanId];
        $insertAsyncTaskData = ['data'=>json_encode($data),'type'=>3,'scan_at'=>$scanAt,'status'=>-1];
        $asyncTaskId = AsyncTask::query()->insertGetId($insertAsyncTaskData);

        $result = $proceedingJoinPoint->process();

        if($result['code'] === ErrorCode::SUCCESS){
            //消息确认通知
            go(function ()use($asyncTaskId){
                AsyncTask::query()->where(['id'=>$asyncTaskId,'status'=>-1])->update(['status'=>0]);
            });
        }
        return $result;
    }
}