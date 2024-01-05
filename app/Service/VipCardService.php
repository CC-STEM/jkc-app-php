<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\VipCard;
use App\Model\VipCardDynamicCourse;
use App\Model\VipCardOrderDynamicCourse;
use App\Model\VipCardPhysicalStore;
use App\Model\VipCardPrivilege;
use App\Constants\ErrorCode;
use App\Model\VipCardSort;

class VipCardService extends BaseService
{
    /**
     * 会员卡列表
     * @param array $params
     * @return array
     */
    public function vipCardList(array $params): array
    {
        $physicalStoreId = $params['physical_store_id'];
        $nowTime = date('Y-m-d H:i:s');
        $weekArray = [7=>"每周日",1=>"每周一",2=>"每周二",3=>"每周三",4=>"每周四",5=>"每周五",6=>"每周六"];

        $vipCardList = VipCard::query()
            ->select(['id','name','price','original_price','expire','rule','thum_img_url','img_url','type','explain','applicable_store_type','theme_type'])
            ->where(['is_deleted'=>0])->whereIn('type',[1,2])->where([['start_at','<=',$nowTime],['end_at','>',$nowTime]])
            ->get();
        $vipCardList = $vipCardList->toArray();
        $vipCardIdArray = array_column($vipCardList,'id');
        $vipCardPrivilegeList = VipCardPrivilege::query()->select(['vip_card_id','title','img_url','describe'])->whereIn('vip_card_id',$vipCardIdArray)->get();
        $vipCardPrivilegeList = $vipCardPrivilegeList->toArray();
        $vipCardPrivilegeList = $this->functions->arrayGroupBy($vipCardPrivilegeList,'vip_card_id');
        $vipCardPhysicalStoreList = VipCardPhysicalStore::query()
            ->leftJoin('physical_store','vip_card_physical_store.physical_store_id','=','physical_store.id')
            ->select(['vip_card_physical_store.vip_card_id','vip_card_physical_store.physical_store_id','physical_store.name'])
            ->whereIn('vip_card_id',$vipCardIdArray)
            ->get();
        $vipCardPhysicalStoreList = $vipCardPhysicalStoreList->toArray();
        $vipCardPhysicalStoreList = $this->functions->arrayGroupBy($vipCardPhysicalStoreList,'vip_card_id');

        $vipCardSortList = VipCardSort::query()
            ->select(['vip_card_id','sort'])
            ->where(['physical_store_id'=>$physicalStoreId])
            ->get();
        $vipCardSortList = $vipCardSortList->toArray();
        $combineVipCardSortKey = array_column($vipCardSortList,'vip_card_id');
        $vipCardSortList = array_combine($combineVipCardSortKey,$vipCardSortList);

        $vipCardDynamicCourseList = VipCardDynamicCourse::query()
            ->select(['vip_card_id','name','week','course'])
            ->whereIn('vip_card_id',$vipCardIdArray)
            ->get();
        $vipCardDynamicCourseList = $vipCardDynamicCourseList->toArray();
        foreach($vipCardDynamicCourseList as $key=>$value){
            $newWeek = [];
            $week = json_decode($value['week'],true);
            foreach($week as $item){
                $newWeek[] = $weekArray[$item];
            }
            $vipCardDynamicCourseList[$key]['week'] = implode(',',$newWeek);
        }
        $vipCardDynamicCourseList = $this->functions->arrayGroupBy($vipCardDynamicCourseList,'vip_card_id');

        foreach($vipCardList as $key=>$value){
            $vipCardId = $value['id'];
            $dynamicCourse = $vipCardDynamicCourseList[$vipCardId] ?? [];
            $rule = json_decode($value['rule'],true);
            $rule['course1'] = (int)$rule['course1'];
            $rule['course2'] = (int)$rule['course2'];
            $rule['course3'] = (int)$rule['course3'];
            $totalSections = $rule['course1']+$rule['course2']+$rule['course3'];
            $vipCardPhysicalStore = $vipCardPhysicalStoreList[$vipCardId] ?? [];
            //$totalDynamicCourse = (int)array_sum(array_column($dynamicCourse,'course'));
            //$totalSections += $totalDynamicCourse;

            if($value['applicable_store_type'] == 2){
                if(!in_array($physicalStoreId,array_column($vipCardPhysicalStore,'physical_store_id'))){
                    unset($vipCardList[$key]);
                    continue;
                }
            }
            $vipCardList[$key]['dynamic_course'] = $dynamicCourse;
            $vipCardList[$key]['sort'] = $vipCardSortList[$vipCardId]['sort'] ?? 1;
            $vipCardList[$key]['rule'] = $rule;
            $vipCardList[$key]['privilege'] = $vipCardPrivilegeList[$vipCardId] ?? [];
            $vipCardList[$key]['average_price'] = $totalSections>0 ? bcdiv((string)$value['price'],(string)$totalSections) : '0';
            $vipCardList[$key]['price'] = (int)$value['price'];
            $vipCardList[$key]['original_price'] = (int)$value['original_price'];
            $vipCardList[$key]['physical_store'] = !empty($vipCardPhysicalStore) ? array_column($vipCardPhysicalStore,'name') : [];
        }
        array_multisort(array_column($vipCardList,'sort'), SORT_ASC, $vipCardList);
        $vipCardList = $this->functions->arrayGroupBy($vipCardList,'theme_type');
        foreach($vipCardList as $key=>$value){
            $value = $this->functions->arrayGroupBy($value,'type');
            $valuePri['child'] = $value['1'] ?? [];
            $valuePri['juvenile'] = $value['2'] ?? [];
            $vipCardList[$key] = $valuePri;
        }
        if(isset($vipCardList['1'])){
            $themeData['theme1'] = $vipCardList['1'];
        }
        if(isset($vipCardList['2'])){
            $themeData['theme2'] = $vipCardList['2'];
        }
        if(isset($vipCardList['3'])){
            $themeData['theme3'] = $vipCardList['3'];
        }

        if(isset($themeData)){
            $returnData['is_open'] = 1;
            $returnData['theme_list'] = $themeData;
        }else{
            $returnData['is_open'] = 0;
            $returnData['theme_list'] = ['theme1'=>['child' => [], 'juvenile' => []]];
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 新人礼包会员卡
     * @param array $params
     * @return array
     */
    public function newcomerVipCardInfo(array $params): array
    {
        $physicalStoreId = $params['physical_store_id'];
        $nowTime = date('Y-m-d H:i:s');

        $vipCardPhysicalStoreList = VipCardPhysicalStore::query()
            ->leftJoin('vip_card','vip_card_physical_store.vip_card_id','=','vip_card.id')
            ->select(['vip_card_physical_store.vip_card_id','vip_card_physical_store.physical_store_id'])
            ->where(['vip_card_physical_store.physical_store_id'=>$physicalStoreId,'vip_card.type'=>3,'vip_card.is_deleted'=>0])->where([['vip_card.start_at','<=',$nowTime],['vip_card.end_at','>',$nowTime]])
            ->get();
        $vipCardPhysicalStoreList = $vipCardPhysicalStoreList->toArray();
        if(!empty($vipCardPhysicalStoreList)){
            $vipCardIdArray = array_column($vipCardPhysicalStoreList,'vip_card_id');
            $vipCardInfo = VipCard::query()
                ->select(['id','name','price','original_price','expire','rule','thum_img_url','img_url','type','explain'])
                ->whereIn('id',$vipCardIdArray)
                ->orderBy('start_at')
                ->first();
        }else{
            $vipCardInfo = VipCard::query()
                ->select(['id','name','price','original_price','expire','rule','thum_img_url','img_url','type','explain'])
                ->where(['type'=>3,'is_deleted'=>0,'applicable_store_type'=>1])->where([['start_at','<=',$nowTime],['end_at','>',$nowTime]])
                ->orderBy('start_at')
                ->first();
        }
        if(empty($vipCardInfo)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '该门店暂未提供新人礼包', 'data' => []];
        }
        $vipCardInfo = $vipCardInfo->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $vipCardInfo];
    }

}