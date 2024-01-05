<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Constants\MessageConstant;
use App\Model\CourseOfflineOrder;
use App\Model\Member;
use App\Model\Message;
use App\Model\VipCardOrder;
use App\Model\VipCardOrderDynamicCourse;
use App\Model\WeixinMessageTemplate;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

class MessageService extends BaseService
{

    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 小程序订阅消息
     * @param array $params
     * @return array
     */
    public function mpSubscribeMessage(array $params): array
    {
        $requestName = $params['request_name'];
        $subscribeId = $params['subscribe_id'] ?? '';
        $templateId = $params['template_id'];
        $memberId = $this->memberId;
        $templateId = explode(',',$templateId);
        $nowDate = date('Y-m-d H:i:s');

        $weixinMessageTemplateList = WeixinMessageTemplate::query()
            ->select(['template_id','code'])
            ->whereIn('template_id',$templateId)
            ->get();
        $weixinMessageTemplateList = $weixinMessageTemplateList->toArray();
        $combineWeixinMessageTemplateKey = array_column($weixinMessageTemplateList,'template_id');
        $weixinMessageTemplateList = array_combine($combineWeixinMessageTemplateKey,$weixinMessageTemplateList);

        $memberInfo = Member::query()
            ->select(['name','mini_openid'])
            ->where(['id'=>$memberId])
            ->first();
        $memberInfo = $memberInfo->toArray();

        $insertWeixinMessageData = [];
        foreach($templateId as $value){
            $code = $weixinMessageTemplateList[$value]['code'];
            $sendAt = $nowDate;
            $data = [];
            $courseOfflineOrderInfo = CourseOfflineOrder::query()
                ->select(['physical_store_name','course_name','start_at','teacher_name','created_at'])
                ->where(['order_no'=>$subscribeId])
                ->first();
            $courseOfflineOrderInfo = $courseOfflineOrderInfo->toArray();

            switch ($code){
                case MessageConstant::MESSAGE1000:
                    $startAt = date('Y年m月d日 H:i',strtotime($courseOfflineOrderInfo['start_at']));
                    $data = [
                        'thing2'=>[
                            'value'=>$courseOfflineOrderInfo['physical_store_name']
                        ],
                        'thing3'=>[
                            'value'=>$courseOfflineOrderInfo['course_name']
                        ],
                        'time4'=>[
                            'value'=>$startAt
                        ],
                        'thing5'=>[
                            'value'=>$courseOfflineOrderInfo['teacher_name']
                        ],
                        'thing6'=>[
                            'value'=>$memberInfo['name']
                        ]
                    ];
                    break;
                case MessageConstant::MESSAGE1001:
                    $startAt = date('Y年m月d日 H:i',strtotime($courseOfflineOrderInfo['start_at']));
                    $sendAt = date('Y-m-d 18:00:00',strtotime($courseOfflineOrderInfo['start_at'].' -1 day'));
                    $data = [
                        'date1'=>[
                            'value'=>$startAt
                        ],
                        'thing2'=>[
                            'value'=>$courseOfflineOrderInfo['course_name']
                        ],
                        'name3'=>[
                            'value'=>$memberInfo['name']
                        ],
                        'thing4'=>[
                            'value'=>$courseOfflineOrderInfo['physical_store_name']
                        ],
                        'thing5'=>[
                            'value'=>'如有问题请及时联系平台！'
                        ]
                    ];
                    break;
                case MessageConstant::MESSAGE1002:
                    //会员卡信息
                    //$surplusSectionCourse = VipCardOrder::query()->where([['member_id','=',$memberId],['pay_status','=',1],['order_status','=',0],['expire_at','>=',$nowDate]])->sum(DB::connection('jkc_edu')->raw('course1+course2+course3-course1_used-course2_used-course3_used'));
                    //会员卡信息
                    $vipCardOrderList = VipCardOrder::query()
                        ->select(['id','course1','course1_used','course2','course2_used','course3','course3_used','currency_course','currency_course_used'])
                        ->where([['member_id','=',$memberId],['pay_status','=',1],['order_status','=',0],['expire_at','>',$nowDate]])
                        ->get();
                    $vipCardOrderList = $vipCardOrderList->toArray();
                    $vipCardOrderIdArray = array_column($vipCardOrderList,'id');
                    $vipCardOrderDynamicCourseList = VipCardOrderDynamicCourse::query()
                        ->select(['vip_card_order_id','course','course_used'])
                        ->whereIn('vip_card_order_id',$vipCardOrderIdArray)
                        ->get();
                    $vipCardOrderDynamicCourseList = $vipCardOrderDynamicCourseList->toArray();
                    $vipCardOrderDynamicCourseList = $this->functions->arrayGroupBy($vipCardOrderDynamicCourseList,'vip_card_order_id');
                    $totalSurplusCourse = 0;
                    foreach($vipCardOrderList as $item){
                        $surplusSectionDynamicCourse = 0;
                        $vipCardOrderDynamicCourse = $vipCardOrderDynamicCourseList[$item['id']] ?? [];
                        foreach($vipCardOrderDynamicCourse as $item1){
                            $surplusSectionDynamicCourse += $item1['course']-$item1['course_used'];
                        }
                        $surplusSectionCourse = $item['course1']-$item['course1_used']+$item['course2']-$item['course2_used']+$item['course3']-$item['course3_used']+$item['currency_course']-$item['currency_course_used'];
                        $totalSurplusCourse += $surplusSectionDynamicCourse+$surplusSectionCourse;
                    }
                    if($totalSurplusCourse>4){
                        break;
                    }
                    $courseOfflineOrderCount = CourseOfflineOrder::query()->where(['order_no'=>$subscribeId])->count();
                    $createdAt = date('Y年m月d日 H:i',strtotime($courseOfflineOrderInfo['created_at']));
                    $data = [
                        'thing1'=>[
                            'value'=>$courseOfflineOrderInfo['physical_store_name']
                        ],
                        'thing2'=>[
                            'value'=>$courseOfflineOrderInfo['course_name']
                        ],
                        'short_thing3'=>[
                            'value'=>$courseOfflineOrderCount
                        ],
                        'time4'=>[
                            'value'=>$createdAt
                        ],
                        'short_thing6'=>[
                            'value'=>$totalSurplusCourse
                        ]
                    ];
                    break;
            }
            if(empty($data)){
                continue;
            }
            $insertWeixinMessageData[] = [
                'touser'=>$memberInfo['mini_openid'],
                'code' => $code,
                'data'=>json_encode($data),
                'message_type'=>2,
                'send_at'=>$sendAt
            ];
        }

        if(!empty($insertWeixinMessageData)){
            Message::query()->insert($insertWeixinMessageData);
        }
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>null];
    }
}