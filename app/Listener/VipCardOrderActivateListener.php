<?php

namespace App\Listener;

use App\Event\CourseOfflinePayRegistered;
use App\Model\AsyncTask;
use App\Model\CourseOfflineOrder;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;

#[Listener]
class VipCardOrderActivateListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            CourseOfflinePayRegistered::class
        ];
    }

    public function process(object $event): void
    {
        if($event instanceof CourseOfflinePayRegistered){
            go(function ()use($event){
                $orderNo = $event->orderNo;

                $courseOfflineOrderList = CourseOfflineOrder::query()
                    ->select(['id','vip_card_order_id','start_at'])
                    ->where(['order_no'=>$orderNo])
                    ->get();
                $courseOfflineOrderList = $courseOfflineOrderList->toArray();

                foreach($courseOfflineOrderList as $value){
                    $data = ['course_offline_order_id'=>$value['id'],'vip_card_order_id'=>$value['vip_card_order_id']];
                    $insertAsyncTaskData[] = ['data'=>json_encode($data),'type'=>2,'scan_at'=>$value['start_at'],'status'=>-1];
                }
                if(!empty($insertAsyncTaskData)){
                    AsyncTask::query()->insert($insertAsyncTaskData);
                }
            });
        }
    }
}