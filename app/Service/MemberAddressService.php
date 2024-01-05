<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\MemberAddressCache;
use App\Model\Region;
use App\Model\MemberAddress;
use App\Snowflake\IdGenerator;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class MemberAddressService extends BaseService
{
    /**
     * MemberAddressService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 添加收货地址
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addMemberAddress(array $params): array
    {
        $memberId = $this->memberId;
        $mobile = $params['mobile'];
        $consignee = $params['consignee'];
        $province = $params['province_id'];
        $city = $params['city_id'];
        $district = $params['district_id'];
        $address = $params['address'];

        $regionList = Region::query()
            ->select(['id','name'])
            ->whereIn('id',[$province,$city,$district])
            ->get();
        if(empty($regionList)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"数据错误",'data'=>null];
        }
        $regionList = $regionList->toArray();
        $combineRegionKey = array_column($regionList,'id');
        $regionList = array_combine($combineRegionKey,$regionList);
        if(!isset($regionList[$province]) || !isset($regionList[$city]) || !isset($regionList[$district])){
            return ['code'=>ErrorCode::WARNING,'msg'=>"选择的区域不存在",'data'=>null];
        }

        $insertMemberAddressData['id'] = IdGenerator::generate();
        $insertMemberAddressData['member_id'] = $memberId;
        $insertMemberAddressData['consignee'] = $consignee;
        $insertMemberAddressData['mobile'] = $mobile;
        $insertMemberAddressData['province_id'] = $province;
        $insertMemberAddressData['city_id'] = $city;
        $insertMemberAddressData['district_id'] = $district;
        $insertMemberAddressData['province_name'] = $regionList[$province]['name'];
        $insertMemberAddressData['city_name'] = $regionList[$city]['name'];
        $insertMemberAddressData['district_name'] = $regionList[$district]['name'];
        $insertMemberAddressData['address'] = $address;
        MemberAddress::query()->insert($insertMemberAddressData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 修改收货地址
     * @param array $params
     * @return array
     */
    public function updateMemberAddress(array $params)
    {
        $memberId = $this->memberId;
        $id = $params['id'];
        $mobile = $params['mobile'];
        $consignee = $params['consignee'];
        $province = $params['province_id'];
        $city = $params['city_id'];
        $district = $params['district_id'];
        $address = $params['address'];

        $memberAddressInfo = MemberAddress::query()->select(['id'])->where(['id'=>$id,'member_id'=>$memberId])->first();
        if(empty($memberAddressInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"信息错误",'data'=>null];
        }

        $regionList = Region::query()
            ->select(['id','name'])
            ->whereIn('id',[$province,$city,$district])
            ->get();
        if(empty($regionList)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"数据错误",'data'=>null];
        }
        $regionList = $regionList->toArray();
        $combineRegionKey = array_column($regionList,'id');
        $regionList = array_combine($combineRegionKey,$regionList);
        if(!isset($regionList[$province]) || !isset($regionList[$city]) || !isset($regionList[$district])){
            return ['code'=>ErrorCode::WARNING,'msg'=>"选择的区域不存在",'data'=>null];
        }

        $updateMemberAddressData['consignee'] = $consignee;
        $updateMemberAddressData['mobile'] = $mobile;
        $updateMemberAddressData['province_id'] = $province;
        $updateMemberAddressData['city_id'] = $city;
        $updateMemberAddressData['district_id'] = $district;
        $updateMemberAddressData['province_name'] = $regionList[$province]['name'];
        $updateMemberAddressData['city_name'] = $regionList[$city]['name'];
        $updateMemberAddressData['district_name'] = $regionList[$district]['name'];
        $updateMemberAddressData['address'] = $address === 'undefined' ? '' : $address;
        MemberAddress::query()->where(['id'=>$id])->update($updateMemberAddressData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 收货地址详情
     * @return array
     */
    public function memberAddressDetail()
    {
        $memberId = $this->memberId;
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => []];
        }

        $memberAddressInfo = MemberAddress::query()
            ->select(['id','consignee','mobile','province_id','city_id','district_id','province_name','city_name','district_name','address'])
            ->where(['member_id'=>$memberId])
            ->first();
        if(empty($memberAddressInfo)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '收货地址不存在', 'data' => []];
        }
        $memberAddressInfo = $memberAddressInfo->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $memberAddressInfo];
    }

    public function getRegionTree()
    {
        $memberAddressCache = new MemberAddressCache();
        $regionList = $memberAddressCache->getRegionTree();
        if(!empty($regionList)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $regionList];
        }

        $regionList = Region::query()
            ->select(['id','name','parent_id'])
            ->where([['parent_id','<>','0']])
            ->orderBy('listorder','asc')
            ->get();
        $regionList = $regionList->toArray();
        $grouped = [];
        foreach ($regionList as $value) {
            $pKeyValue = $value['parent_id'];
            unset($value['parent_id']);
            $grouped[$pKeyValue][] = $value;
        }
        $memberAddressCache->setRegionTree($grouped);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $grouped];
    }
}