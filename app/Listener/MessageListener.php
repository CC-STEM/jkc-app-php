<?php

namespace App\Listener;

use App\Constants\MessageConstant;
use App\Event\GoodsPayRegistered;
use App\Event\InviteBindRegistered;
use App\Event\VipCardPayRegistered;
use App\Model\Member;
use App\Model\OrderInfo;
use App\Model\PhysicalStore;
use App\Model\PhysicalStoreAdmins;
use App\Model\VipCardOrder;
use App\Model\Message;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;

#[Listener]
class MessageListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            InviteBindRegistered::class
        ];
    }

    public function process(object $event): void
    {
        if($event instanceof VipCardPayRegistered){
            go(function ()use($event){
                $orderNo = $event->orderNo;

                $vipCardOrderInfo = VipCardOrder::query()
                    ->select(['recommend_physical_store_id','order_title','price','created_at'])
                    ->where(['order_no'=>$orderNo])
                    ->first();
                $vipCardOrderInfo = $vipCardOrderInfo?->toArray();
                if($vipCardOrderInfo === null || $vipCardOrderInfo['recommend_physical_store_id'] == 0){
                    return;
                }
                $recommendPhysicalStoreId = $vipCardOrderInfo['recommend_physical_store_id'];
                $createdAt = date('Y年m月d日 H:i',strtotime($vipCardOrderInfo['created_at']));

                $physicalStoreInfo = PhysicalStore::query()
                    ->select(['name'])
                    ->where(['id'=>$recommendPhysicalStoreId])
                    ->first();
                $physicalStoreInfo = $physicalStoreInfo?->toArray();
                if($physicalStoreInfo === null){
                    return;
                }
                $physicalStoreName = $physicalStoreInfo['name'];

                $physicalStoreAdminsInfo = PhysicalStoreAdmins::query()
                    ->select(['physical_store_admins.mobile'])
                    ->leftJoin('physical_store_admins_physical_store','physical_store_admins.id','=','physical_store_admins_physical_store.physical_store_admins_id')
                    ->where(['physical_store_admins_physical_store.physical_store_id'=>$recommendPhysicalStoreId,'physical_store_admins.is_store_manager'=>1])
                    ->first();
                $physicalStoreAdminsInfo = $physicalStoreAdminsInfo?->toArray();
                if($physicalStoreAdminsInfo === null){
                    return;
                }

                $memberInfo = Member::query()
                    ->select(['mini_openid'])
                    ->where(['mobile'=>$physicalStoreAdminsInfo['mobile']])
                    ->first();
                $memberInfo = $memberInfo?->toArray();
                if($memberInfo === null){
                    return;
                }

                $data = [
                    'thing3'=>[
                        'value'=>$vipCardOrderInfo['order_title']
                    ],
                    'amount5'=>[
                        'value'=>$vipCardOrderInfo['price']
                    ],
                    'time4'=>[
                        'value'=>$createdAt
                    ],
                    'thing8'=>[
                        'value'=>$physicalStoreName
                    ]
                ];
                $insertWeixinMessageData = [
                    'touser'=>$memberInfo['mini_openid'],
                    'code' => MessageConstant::MESSAGE2000,
                    'data'=>json_encode($data),
                    'message_type'=>1,
                    'send_at'=>date('Y-m-d H:i:s')
                ];
                Message::query()->insert($insertWeixinMessageData);
            });
        }else if($event instanceof GoodsPayRegistered){
            $orderNo = $event->orderNo;

            $orderInfoInfo = OrderInfo::query()
                ->select(['recommend_physical_store_id','order_title','amount','created_at'])
                ->where(['order_no'=>$orderNo])
                ->first();
            $orderInfoInfo = $orderInfoInfo?->toArray();
            if($orderInfoInfo === null || $orderInfoInfo['recommend_physical_store_id'] == 0){
                return;
            }
            $recommendPhysicalStoreId = $orderInfoInfo['recommend_physical_store_id'];
            $createdAt = date('Y年m月d日 H:i',strtotime($orderInfoInfo['created_at']));

            $physicalStoreInfo = PhysicalStore::query()
                ->select(['name'])
                ->where(['id'=>$recommendPhysicalStoreId])
                ->first();
            $physicalStoreInfo = $physicalStoreInfo?->toArray();
            if($physicalStoreInfo === null){
                return;
            }
            $physicalStoreName = $physicalStoreInfo['name'];

            $physicalStoreAdminsInfo = PhysicalStoreAdmins::query()
                ->select(['physical_store_admins.mobile'])
                ->leftJoin('physical_store_admins_physical_store','physical_store_admins.id','=','physical_store_admins_physical_store.physical_store_admins_id')
                ->where(['physical_store_admins_physical_store.physical_store_id'=>$recommendPhysicalStoreId,'physical_store_admins.is_store_manager'=>1])
                ->first();
            $physicalStoreAdminsInfo = $physicalStoreAdminsInfo?->toArray();
            if($physicalStoreAdminsInfo === null){
                return;
            }

            $memberInfo = Member::query()
                ->select(['mini_openid'])
                ->where(['mobile'=>$physicalStoreAdminsInfo['mobile']])
                ->first();
            $memberInfo = $memberInfo?->toArray();
            if($memberInfo === null){
                return;
            }

            $data = [
                'thing3'=>[
                    'value'=>$orderInfoInfo['order_title']
                ],
                'amount5'=>[
                    'value'=>$orderInfoInfo['amount']
                ],
                'time4'=>[
                    'value'=>$createdAt
                ],
                'thing8'=>[
                    'value'=>$physicalStoreName
                ]
            ];
            $insertWeixinMessageData = [
                'touser'=>$memberInfo['mini_openid'],
                'code' => MessageConstant::MESSAGE2000,
                'data'=>json_encode($data),
                'message_type'=>1,
                'send_at'=>date('Y-m-d H:i:s')
            ];
            Message::query()->insert($insertWeixinMessageData);
        }else if($event instanceof InviteBindRegistered){
            $memberId = $event->memberId;
            $parentId = $event->parentId;

            $memberInfo = Member::query()
                ->select(['name','created_at'])
                ->where(['id'=>$memberId])
                ->first();
            $memberInfo = $memberInfo?->toArray();
            if($memberInfo === null){
                return;
            }
            $createdAt = date('Y年m月d日',strtotime($memberInfo['created_at']));

            $parentMemberInfo = Member::query()
                ->select(['mini_openid'])
                ->where(['id'=>$parentId])
                ->first();
            $parentMemberInfo = $parentMemberInfo?->toArray();
            if($parentMemberInfo === null){
                return;
            }

            $data = [
                'name1'=>[
                    'value'=>'课程抵扣券'
                ],
                'date4'=>[
                    'value'=>$createdAt
                ],
                'thing9'=>[
                    'value'=>$memberInfo['name']
                ]
            ];
            $insertWeixinMessageData = [
                'touser'=>$parentMemberInfo['mini_openid'],
                'code' => MessageConstant::MESSAGE1003,
                'data'=>json_encode($data),
                'message_type'=>2,
                'send_at'=>date('Y-m-d H:i:s')
            ];
            Message::query()->insert($insertWeixinMessageData);
        }
    }
}