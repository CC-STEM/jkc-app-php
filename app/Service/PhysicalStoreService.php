<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\VipCard;
use App\Model\PhysicalStore;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class PhysicalStoreService extends BaseService
{

    /**
     * PhysicalStoreService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 门店列表
     * @param array $params
     * @return array
     */
    public function physicalStoreList(array $params): array
    {
        $memberLongitude = $params['longitude'];
        $memberLatitude = $params['latitude'];
        $linearDistance = 0;

        $physicalStoreList = PhysicalStore::query()
            ->select(['id','name','city_name','district_name','address','longitude','latitude','img_url','wechat_qr_code','store_phone'])
            ->where(['is_deleted'=>0])
            ->get();
        $physicalStoreList = $physicalStoreList->toArray();
        if(empty($physicalStoreList)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }
        foreach($physicalStoreList as $key => $value){
            if($memberLatitude != 0 && $memberLongitude != 0){
                $linearDistance = $this->functions->linearDistance((float)$memberLatitude,(float)$memberLongitude,(float)$value['latitude'],(float)$value['longitude']);
            }
            unset($physicalStoreList[$key]['latitude']);
            unset($physicalStoreList[$key]['longitude']);
            $physicalStoreList[$key]['distance'] = bcdiv((string)$linearDistance,'1000',2);
        }
        array_multisort(array_column($physicalStoreList,'distance'), SORT_ASC, $physicalStoreList);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $physicalStoreList];
    }

    /**
     * 门店详情
     * @param array $params
     * @return array
     */
    public function physicalStoreDetail(array $params): array
    {
        $physicalStoreId = $params['id'];
        $memberLongitude = $params['longitude'];
        $memberLatitude = $params['latitude'];
        $linearDistance = 0;

        if(empty($physicalStoreId)){
            $physicalStoreList = PhysicalStore::query()
                ->select(['id','name','mobile','city_name','district_name','address','longitude','latitude','img_url','wechat_qr_code','store_phone'])
                ->where(['is_deleted'=>0])
                ->get();
            $physicalStoreList = $physicalStoreList->toArray();
            if(empty($physicalStoreList)){
                return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
            }
            foreach($physicalStoreList as $key => $value){
                if($memberLatitude != 0 && $memberLongitude != 0){
                    $linearDistance = $this->functions->linearDistance($memberLatitude,$memberLongitude,$value['latitude'],$value['longitude']);
                }
                //$linearDistance = $this->functions->linearDistance($memberLatitude,$memberLongitude,$value['latitude'],$value['longitude']);
                unset($physicalStoreList[$key]['latitude']);
                unset($physicalStoreList[$key]['longitude']);
                $physicalStoreList[$key]['distance'] = bcdiv((string)$linearDistance,'1000',2);
            }
            array_multisort(array_column($physicalStoreList,'distance'), SORT_ASC, $physicalStoreList);
            $physicalStoreInfo = $physicalStoreList[0];
        }else{
            $physicalStoreInfo = PhysicalStore::query()
                ->select(['id','name','mobile','city_name','district_name','address','longitude','latitude','img_url','wechat_qr_code','store_phone'])
                ->where(['id'=>$physicalStoreId,'is_deleted'=>0])
                ->first();
            if(empty($physicalStoreInfo)){
                return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
            }
            $physicalStoreInfo = $physicalStoreInfo->toArray();
            if($memberLatitude != 0 && $memberLongitude != 0){
                $linearDistance = $this->functions->linearDistance($memberLatitude,$memberLongitude,$physicalStoreInfo['latitude'],$physicalStoreInfo['longitude']);
            }
            //$linearDistance = $this->functions->linearDistance($memberLatitude,$memberLongitude,$physicalStoreInfo['latitude'],$physicalStoreInfo['longitude']);
            unset($physicalStoreInfo['latitude']);
            unset($physicalStoreInfo['longitude']);
            $physicalStoreInfo['distance'] = bcdiv((string)$linearDistance,'1000',2);
        }

        $vipCardList = VipCard::query()->select(['id','name','price'])->where(['is_deleted'=>0])->limit(3)->get();
        $physicalStoreInfo['vip_card'] = $vipCardList;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $physicalStoreInfo];
    }

}