<?php

namespace App\Listener;

use App\Event\CourseOfflineCancelRegistered;
use App\Event\CourseOfflineCompleteRegistered;
use App\Event\CourseOfflinePayRegistered;
use App\Event\GoodsPayRegistered;
use App\Event\MemberRegisterRegistered;
use App\Event\VipCardPayRegistered;
use App\Model\AsyncTask;
use App\Model\CourseOfflineOrder;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;

#[Listener]
class MemberEventListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            VipCardPayRegistered::class,
            GoodsPayRegistered::class,
            CourseOfflinePayRegistered::class,
            CourseOfflineCancelRegistered::class,
            CourseOfflineCompleteRegistered::class,
            MemberRegisterRegistered::class
        ];
    }

    public function process(object $event): void
    {
        if($event instanceof VipCardPayRegistered){
            go(function ()use($event){
                $memberId = $event->memberId;
                $orderType = $event->orderType;

                if($orderType === 1){
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>7,'member_id'=>$memberId])];
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>11,'member_id'=>$memberId])];
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>1001,'member_id'=>$memberId])];
                }else if($orderType === 2){
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>4,'member_id'=>$memberId])];
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>1002,'member_id'=>$memberId])];
                }
                if(!empty($insertAsyncTaskData)){
                    AsyncTask::query()->insert($insertAsyncTaskData);
                }
            });
        }else if($event instanceof GoodsPayRegistered){
            go(function ()use($event){
                $memberId = $event->memberId;

                $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>1,'member_id'=>$memberId])];
                $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>2,'member_id'=>$memberId])];
                if(!empty($insertAsyncTaskData)){
                    AsyncTask::query()->insert($insertAsyncTaskData);
                }
            });
        }else if($event instanceof CourseOfflinePayRegistered){
            go(function ()use($event){
                $memberId = $event->memberId;
                $isSample = $event->isSample;

                if($isSample === 1){
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>4,'member_id'=>$memberId])];
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>5,'member_id'=>$memberId])];
                }else{
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>9,'member_id'=>$memberId])];
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>11,'member_id'=>$memberId])];
                }
                $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>1004,'member_id'=>$memberId])];
                AsyncTask::query()->insert($insertAsyncTaskData);
            });
        }else if($event instanceof CourseOfflineCancelRegistered){
            go(function ()use($event){
                $memberId = $event->memberId;
                $isSample = $event->isSample;

                if($isSample === 0){
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>10,'member_id'=>$memberId])];
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>11,'member_id'=>$memberId])];
                }else{
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>4,'member_id'=>$memberId])];
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>6,'member_id'=>$memberId])];
                }
                $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>1004,'member_id'=>$memberId])];
                AsyncTask::query()->insert($insertAsyncTaskData);
            });
        }else if($event instanceof CourseOfflineCompleteRegistered){
            go(function ()use($event){
                $courseOfflinePlanId = $event->courseOfflinePlanId;

                $courseOfflineOrderList = CourseOfflineOrder::query()
                    ->select(['member_id'])
                    ->where(['course_offline_plan_id'=>$courseOfflinePlanId,'class_status'=>1])
                    ->get();
                $courseOfflineOrderList = $courseOfflineOrderList->toArray();
                foreach($courseOfflineOrderList as $value){
                    $insertAsyncTaskData[] = ['type'=>10,'data'=>json_encode(['action_type'=>13,'member_id'=>$value['member_id']])];
                }
                if(!empty($insertAsyncTaskData)){
                    AsyncTask::query()->insert($insertAsyncTaskData);
                }
            });
        }else if($event instanceof MemberRegisterRegistered){
            go(function ()use($event){
                $memberId = $event->memberId;

                $insertAsyncTaskData = ['type'=>10,'data'=>json_encode(['action_type'=>1000,'member_id'=>$memberId])];
                AsyncTask::query()->insert($insertAsyncTaskData);
            });
        }


    }
}