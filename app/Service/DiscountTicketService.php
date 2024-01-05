<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\DiscountTicket;
use App\Model\DiscountTicketPhysicalStore;
use App\Model\DiscountTicketTemplate;
use App\Model\DiscountTicketVipCard;
use App\Model\Member;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class DiscountTicketService extends BaseService
{

    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 减免券活动信息
     * @return array
     */
    public function discountTicketMarketingInfo(): array
    {
        $nowDate = date('Y-m-d H:i:s');

        $discountTicketTemplateInfo = DiscountTicketTemplate::query()
            ->select(['name','amount','expire','end_at','describe'])
            ->where([['start_at','<=',$nowDate],['end_at','>',$nowDate],['is_deleted','=',0]])
            ->first();
        $discountTicketTemplateInfo = $discountTicketTemplateInfo?->toArray();
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$discountTicketTemplateInfo];
    }

    /**
     * 减免券活动参与信息
     * @return array
     */
    public function discountTicketParticipateInfo(): array
    {
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');

        $discountTicketTemplateInfo = DiscountTicketTemplate::query()
            ->select(['img_url','end_at','start_at'])
            ->first();
        $discountTicketTemplateInfo = $discountTicketTemplateInfo?->toArray();
        if(empty($discountTicketTemplateInfo) || $discountTicketTemplateInfo['end_at']<$nowDate || $discountTicketTemplateInfo['start_at']>$nowDate){
            $discountTicketTemplateInfo['status'] = 0;
            $discountTicketTemplateInfo['img_url'] = $discountTicketTemplateInfo['img_url'] ?? '';
        }else{
            $discountTicketTemplateInfo['status'] = 1;
        }

        $discountTicketList = DiscountTicket::query()
            ->select(['source_id'])
            ->where(['member_id'=>$memberId,'source_type'=>1])
            ->get();
        $discountTicketList = $discountTicketList->toArray();
        $sourceIdArray = array_column($discountTicketList,'source_id');

        $memberList = Member::query()
            ->select(['name','avatar'])
            ->whereIn('id',$sourceIdArray)
            ->get();
        $memberList = $memberList->toArray();

        $memberList = array_map(function ($value){
            $avatar = $value['avatar'] !== '' ? $value['avatar'] : 'https://jkc-1313504415.cos.ap-shanghai.myqcloud.com/wxmini_static/images/logo.png';
            if(str_contains($avatar, 'http') === false){
                $avatar = 'https://image.jkcspace.com/'.$avatar;
            }
            $value['avatar'] = $avatar;
            return $value;
        },$memberList);
        $discountTicketTemplateInfo['invitee'] = $memberList;
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$discountTicketTemplateInfo];
    }

    /**
     * 减免券列表
     * @param array $params
     * @return array
     */
    public function discountTicketList(array $params): array
    {
        $type = $params['type'];
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');

        if($type == 1){
            $discountTicketList = DiscountTicket::query()
                ->select(['id','name','amount','end_at','source_type','used_at','source_id','status','cancel_at'])
                ->where([['member_id','=',$memberId],['status','=',0],['end_at','>',$nowDate]])
                ->orderBy('created_at','desc')
                ->get();
        }else{
            $discountTicketList = DiscountTicket::query()
                ->select(['id','name','amount','end_at','source_type','used_at','source_id','status','cancel_at'])
                ->where(['member_id'=>$memberId])
                ->where(function ($query) use($nowDate){
                    $query->where('status', '!=', 0)->orWhere('end_at', '<', $nowDate);
                })
                ->orderBy('cancel_at','desc')->orderBy('used_at','desc')->orderBy('end_at','desc')
                ->get();
        }
        $discountTicketList = $discountTicketList->toArray();
        $sourceIdArray = array_column($discountTicketList,'source_id');
        $discountTicketIdArray = array_column($discountTicketList,'id');

        $memberList = Member::query()
            ->select(['id','avatar','name'])
            ->whereIn('id',$sourceIdArray)
            ->get();
        $memberList = $memberList->toArray();
        $combineMemberKey = array_column($memberList,'id');
        $memberList = array_combine($combineMemberKey,$memberList);

        $discountTicketPhysicalStoreList = DiscountTicketPhysicalStore::query()
            ->leftJoin('physical_store','discount_ticket_physical_store.physical_store_id','=','physical_store.id')
            ->select(['discount_ticket_physical_store.discount_ticket_id','physical_store.name'])
            ->whereIn('discount_ticket_physical_store.discount_ticket_id',$discountTicketIdArray)
            ->get();
        $discountTicketPhysicalStoreList = $discountTicketPhysicalStoreList->toArray();
        $discountTicketPhysicalStoreList = $this->functions->arrayGroupBy($discountTicketPhysicalStoreList,'discount_ticket_id');
        $discountTicketVipCardList = DiscountTicketVipCard::query()
            ->leftJoin('vip_card','discount_ticket_vip_card.vip_card_id','=','vip_card.id')
            ->select(['discount_ticket_vip_card.discount_ticket_id','vip_card.name'])
            ->whereIn('discount_ticket_vip_card.discount_ticket_id',$discountTicketIdArray)
            ->get();
        $discountTicketVipCardList = $discountTicketVipCardList->toArray();
        $discountTicketVipCardList = $this->functions->arrayGroupBy($discountTicketVipCardList,'discount_ticket_id');

        $sourceName = ['平台购送减免券','推荐好友减免券','自购奖励减免券'];
        foreach($discountTicketList as $key=>$value){
            $inviteeAvatar = '';
            $inviteeName = '';
            $physicalStore = '';
            $loseEfficacyTag = '';
            $vipCard = '';
            if($value['source_type'] == 1 && $value['source_id'] != 0){
                $inviteeAvatar = !empty($memberList[$value['source_id']]['avatar']) ? $memberList[$value['source_id']]['avatar'] : 'https://jkc-1313504415.cos.ap-shanghai.myqcloud.com/wxmini_static/images/logo.png';
                $inviteeName = $memberList[$value['source_id']]['name'];
            }
            if($inviteeAvatar !== '' && str_contains($inviteeAvatar, 'http') === false){
                $inviteeAvatar = 'https://image.jkcspace.com/'.$inviteeAvatar;
            }
            if(isset($discountTicketPhysicalStoreList[$value['id']])){
                $physicalStore = implode('、',array_column($discountTicketPhysicalStoreList[$value['id']],'name'));
            }
            if(isset($discountTicketVipCardList[$value['id']])){
                $vipCard = implode('、',array_column($discountTicketVipCardList[$value['id']],'name'));
            }
            if($type == 1){
                $loseEfficacyTag = date('Y.m.d',strtotime($value['end_at'])).'过期';
            }else if($type == 2 && $value['status'] == 1){
                $loseEfficacyTag = '于'.date('Y.m.d',strtotime($value['used_at'])).'已使用';
            }else if($type == 2 && $value['status'] == 2){
                $loseEfficacyTag = '于'.date('Y.m.d',strtotime($value['cancel_at'])).'已失效';
            }else if($type == 2 && $value['end_at'] < $nowDate){
                $loseEfficacyTag = '于'.date('Y.m.d',strtotime($value['end_at'])).'已过期';
            }

            $discountTicketList[$key]['name'] = $sourceName[$value['source_type']];
            $discountTicketList[$key]['invitee_avatar'] = $inviteeAvatar;
            $discountTicketList[$key]['invitee_name'] = $inviteeName;
            $discountTicketList[$key]['physical_store'] = $physicalStore;
            $discountTicketList[$key]['vip_card'] = $vipCard;
            $discountTicketList[$key]['lose_efficacy_tag'] = $loseEfficacyTag;
        }
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$discountTicketList];
    }
}