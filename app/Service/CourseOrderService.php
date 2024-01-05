<?php

declare(strict_types=1);

namespace App\Service;

use App\Event\CourseOfflineCancelRegistered;
use App\Event\CourseOfflinePayRegistered;
use App\Model\CourseOfflineClassroomSituation;
use App\Model\CourseOfflineOrderEvaluation;
use App\Model\CourseOfflineOrderReadjust;
use App\Model\CourseOfflineOutline;
use App\Model\VipCardOrder;
use App\Model\CourseOffline;
use App\Model\CourseOfflinePlan;
use App\Model\CourseOfflineOrder;
use App\Model\CourseOfflineCategory;
use App\Model\PhysicalStore;
use App\Logger\Log;
use App\Constants\ErrorCode;
use App\Model\VipCardOrderDynamicCourse;
use App\Model\VipCardOrderPhysicalStore;
use App\Snowflake\IdGenerator;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Context;
use Psr\EventDispatcher\EventDispatcherInterface;

class CourseOrderService extends BaseService
{
    #[Inject]
    private EventDispatcherInterface $eventDispatcher;

    /**
     * CourseOrderService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 线下课程订单信息确认
     * @param array $params
     * @return array
     */
    public function courseOfflineConfirmOrder(array $params): array
    {
        $isSample = $params['is_sample'];
        $courseType = $params['course_type'];
        $courseOfflinePlanId = $params['course_offline_plan'];
        $memberId = $this->memberId;
        $nowTime = time();
        $nowDate = date('Y-m-d H:i:s');
        $weekArray = [7,1,2,3,4,5,6];

        //排课信息
        $courseOfflinePlanList = CourseOfflinePlan::query()
            ->select(['id','course_offline_id','physical_store_id','class_start_time','batch_no','theme_type'])
            ->whereIn('id',$courseOfflinePlanId)->where(['is_deleted'=>0])
            ->orderBy('class_start_time')
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();
        if(empty($courseOfflinePlanList)){
            return ['code' => ErrorCode::WARNING, 'msg' => '课程信息错误1', 'data' => null];
        }
        $physicalStoreId = $courseOfflinePlanList[0]['physical_store_id'];
        $themeType = $courseOfflinePlanList[0]['theme_type'];

        //课程信息
        $courseOfflineIdArray = array_column($courseOfflinePlanList,'course_offline_id');
        $courseOfflineList = CourseOffline::query()
            ->select(['id','course_category_id','name','price','img_url'])
            ->whereIn('id',$courseOfflineIdArray)
            ->where(['type'=>$courseType,'theme_type'=>$themeType])
            ->get();
        $courseOfflineList = $courseOfflineList->toArray();
        if(empty($courseOfflineList)){
            return ['code' => ErrorCode::WARNING, 'msg' => '课程信息错误5', 'data' => null];
        }
        $courseCategoryId = $courseOfflineList[0]['course_category_id'];
        $imgUrl = $courseOfflineList[0]['img_url'];
        $combineCourseOfflineKey = array_column($courseOfflineList,'id');
        $courseOfflineList = array_combine($combineCourseOfflineKey,$courseOfflineList);

        //门店信息
        $physicalStoreInfo = PhysicalStore::query()
            ->select(['city_name','district_name','address'])
            ->where(['id'=>$physicalStoreId])
            ->first();
        if(empty($physicalStoreInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '门店信息错误', 'data' => null];
        }
        $physicalStoreInfo = $physicalStoreInfo->toArray();

        //课程分类信息
        $courseOfflineCategoryInfo = CourseOfflineCategory::query()->select(['name'])->where(['id'=>$courseCategoryId])->first();
        if(empty($courseOfflineCategoryInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '课程信息错误3', 'data' => null];
        }
        $courseOfflineCategoryInfo = $courseOfflineCategoryInfo->toArray();

        //课程数据整合
        $totalPrice = '0';
        foreach($courseOfflinePlanList as $key=>$value){
            $courseOfflineId = $value['course_offline_id'];
            $classStartTime = $value['class_start_time'];
            if(!isset($courseOfflineList[$courseOfflineId])){
                return ['code' => ErrorCode::WARNING, 'msg' => '课程信息错误2', 'data' => null];
            }
            $courseOfflineInfo = $courseOfflineList[$courseOfflineId];
            if($classStartTime <= $nowTime){
                return ['code' => ErrorCode::WARNING, 'msg' => '课程'.$courseOfflineInfo['name'].'已开课，已无法报名', 'data' => null];
            }
            $price = $courseOfflineInfo['price'];
            $totalPrice = bcadd($totalPrice,$price,2);
            unset($courseOfflinePlanList[$key]['physical_store_id']);
            unset($courseOfflinePlanList[$key]['course_offline_id']);

            $courseOfflinePlanList[$key]['w'] = $weekArray[date("w",$classStartTime)];
            $courseOfflinePlanList[$key]['name'] = $courseOfflineInfo['name'];
            $courseOfflinePlanList[$key]['class_start_time'] = date('Y-m-d H:i:s',$classStartTime);
        }

        //会员卡信息
        $vipCardOrderList = VipCardOrder::query()
            ->select(['id','course1','course1_used','course2','course2_used','course3','course3_used'])
            ->where([['member_id','=',$memberId],['pay_status','=',1],['expire_at','>',$nowDate],['order_status','=',0],['card_theme_type','=',$themeType]])
            ->whereIn('order_type',[1,4])
            ->orderBy('expire_at')
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $vipCardOrderIdArray = array_column($vipCardOrderList,'id');
        $vipCardOrderDynamicCourseList = VipCardOrderDynamicCourse::query()
            ->select(['vip_card_order_id','course','course_used','type','week'])
            ->whereIn('vip_card_order_id',$vipCardOrderIdArray)
            ->get();
        $vipCardOrderDynamicCourseList = $vipCardOrderDynamicCourseList->toArray();
        $vipCardOrderDynamicCourseList = $this->functions->arrayGroupBy($vipCardOrderDynamicCourseList,'vip_card_order_id');

        //会员卡账户信息
        $totalCourse1 = 0;
        $totalCourse2 = 0;
        $totalCourse3 = 0;
        $totalDynamicCourse1 = 0;
        $totalDynamicCourse2 = 0;
        $totalDynamicCourse3 = 0;
        foreach($vipCardOrderList as $key=>$value){
            $vipCardOrderDynamicCourse = $vipCardOrderDynamicCourseList[$value['id']] ?? [];
            foreach($vipCardOrderDynamicCourse as $k=>$item){
                $surplusSectionDynamicCourse = $item['course']-$item['course_used'];
                if($item['type'] == 1){
                    $totalDynamicCourse1 += $surplusSectionDynamicCourse;
                }else if($item['type'] == 2){
                    $totalDynamicCourse2 += $surplusSectionDynamicCourse;
                }else if($item['type'] == 3){
                    $totalDynamicCourse3 += $surplusSectionDynamicCourse;
                }
                $vipCardOrderDynamicCourse[$k]['week'] = !empty($item['week']) ? json_decode($item['week'],true) : [];
            }
            $surplusSectionCourse1 = $value['course1']-$value['course1_used'];
            $surplusSectionCourse2 = $value['course2']-$value['course2_used'];
            $surplusSectionCourse3 = $value['course3']-$value['course3_used'];
            $totalCourse1 = $surplusSectionCourse1>0 ? $totalCourse1+$surplusSectionCourse1 : $totalCourse1;
            $totalCourse2 = $surplusSectionCourse2>0 ? $totalCourse2+$surplusSectionCourse2 : $totalCourse2;
            $totalCourse3 = $surplusSectionCourse3>0 ? $totalCourse3+$surplusSectionCourse3 : $totalCourse3;

            $vipCardOrderList[$key]['dynamic_course'] = $vipCardOrderDynamicCourse;
        }
        $totalCourse1 += $totalDynamicCourse1;
        $totalCourse2 += $totalDynamicCourse2;
        $totalCourse3 += $totalDynamicCourse3;
        //体验卡信息
        if($isSample == 1){
            $sampleVipCardOrderList = VipCardOrder::query()
                ->select(['id','currency_course','currency_course_used'])
                ->where([['member_id','=',$memberId],['pay_status','=',1],['order_status','=',0],['expire_at','>',$nowDate]])
                ->whereIn('order_type',[2,3])
                ->orderBy('expire_at')
                ->get();
            if(empty($sampleVipCardOrderList)){
                return ['code' => ErrorCode::WARNING, 'msg' => '暂无可用体验次数', 'data' => null];
            }
            $sampleVipCardOrderList = $sampleVipCardOrderList->toArray();
        }
        $usedVipCardOrderList = $isSample == 1 ? $sampleVipCardOrderList : $vipCardOrderList;
        //总节数
        $totalSection = count($courseOfflinePlanList);
        //总课程数
        $totalCourseNumber = $totalSection;
        if($isSample == 1){
            $existingSection = 0;
            foreach($usedVipCardOrderList as $value){
                $cardCourse = $value['currency_course'];
                $cardCourseUsed = $value['currency_course_used'];
                //总节数差值
                $differSection = $totalCourseNumber-$existingSection;
                if($differSection <= 0){
                    break;
                }
                if($cardCourse <= $cardCourseUsed){
                    continue;
                }
                //当前卡剩余节数
                $surplusSection = $cardCourse-$cardCourseUsed;
                $existingSection += min($surplusSection, $differSection);
            }
            if($existingSection != $totalCourseNumber){
                return ['code' => ErrorCode::WARNING, 'msg' => '会员卡可预约次数不足', 'data' => null];
            }
        }else{
            foreach($courseOfflinePlanList as $value){
                $isPass = 0;
                $w = $value['w'];
                foreach($usedVipCardOrderList as $k=>$item){
                    $dynamicCourse = $item['dynamic_course'];
                    switch ($courseType){
                        case 1:
                            foreach($dynamicCourse as $k1=>$item1){
                                if($item1['type'] == 1 && $item1['course']>$item1['course_used'] && in_array($w,$item1['week'])){
                                    $dynamicCourse[$k1]['course_used'] = $item1['course_used']+1;
                                    $isPass = 1;
                                    break;
                                }
                            }
                            if($isPass === 0 && $item['course1']>$item['course1_used']){
                                $usedVipCardOrderList[$k]['course1_used'] = $item['course1_used']+1;
                                $isPass = 1;
                            }
                            if($isPass === 1){
                                $usedVipCardOrderList[$k]['dynamic_course'] = $dynamicCourse;
                            }
                            break;
                        case 2:
                            foreach($dynamicCourse as $k1=>$item1){
                                if($item1['type'] == 2 && $item1['course']>$item1['course_used'] && in_array($w,$item1['week'])){
                                    $dynamicCourse[$k1]['course_used'] = $item1['course_used']+1;
                                    $isPass = 1;
                                    break;
                                }
                            }
                            if($isPass === 0 && $item['course2']>$item['course2_used']){
                                $usedVipCardOrderList[$k]['course2_used'] = $item['course2_used']+1;
                                $isPass = 1;
                            }
                            if($isPass === 1){
                                $usedVipCardOrderList[$k]['dynamic_course'] = $dynamicCourse;
                            }
                            break;
                        case 3:
                            foreach($dynamicCourse as $k1=>$item1){
                                if($item1['type'] == 3 && $item1['course']>$item1['course_used'] && in_array($w,$item1['week'])){
                                    $dynamicCourse[$k1]['course_used'] = $item1['course_used']+1;
                                    $isPass = 1;
                                    break;
                                }
                            }
                            if($isPass === 0 && $item['course3']>$item['course3_used']){
                                $usedVipCardOrderList[$k]['course3_used'] = $item['course3_used']+1;
                                $isPass = 1;
                            }
                            if($isPass === 1){
                                $usedVipCardOrderList[$k]['dynamic_course'] = $dynamicCourse;
                            }
                            break;
                    }
                    if($isPass === 1){
                        break;
                    }
                }
                if($isPass === 0){
                    return ['code' => ErrorCode::WARNING, 'msg' => '会员卡可预约次数不足', 'data' => null];
                }
            }
        }
        $payCompanyType = $isSample == 1 ? 4 : $courseType;
        $payCompanyEnum = [1=>'次幼儿课',2=>'次活动主题课',3=>'次少儿课',4=>'次体验课'];
        $payAmount = $totalCourseNumber;

        $returnData = [
            'category_name' => $courseOfflineCategoryInfo['name'],
            'img_url' => $imgUrl,
            'city_name' => $physicalStoreInfo['city_name'],
            'district_name' => $physicalStoreInfo['district_name'],
            'address' => $physicalStoreInfo['address'],
            'total_section' => $totalSection,
            'course' => $courseOfflinePlanList,
            'course1' => $totalCourse1,
            'course2' => $totalCourse2,
            'course3' => $totalCourse3,
            'pay_amount' => $payAmount,
            'pay_code' => 'PPPAY',
            'pay_company' => $payCompanyEnum[$payCompanyType],
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 创建线下课程订单
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineCreateOrder(array $params): array
    {
        $isSample = $params['is_sample'] ?? 0;
        $courseType = $params['course_type'];
        $courseOfflinePlan = $params['course_offline_plan'];
        $memberId = $this->memberId;
        $nowTime = time();
        $nowDate = date('Y-m-d H:i:s');
        $weekArray = [7,1,2,3,4,5,6];

        $lockResult = $this->functions->atomLock("course_offline_create_order:{$memberId}",2);
        if($lockResult === false){
            return ['code' => ErrorCode::WARNING, 'msg' => '操作太频繁', 'data' => null];
        }
        //排课信息
        $courseOfflinePlanList = CourseOfflinePlan::query()
            ->select(['id','course_offline_id','course_category_id','physical_store_id','teacher_id','classroom_id','classroom_name','teacher_name','class_start_time','class_end_time','batch_no','section_no','theme_type'])
            ->whereIn('id',$courseOfflinePlan)->where(['is_deleted'=>0])
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();
        if(empty($courseOfflinePlanList)){
            return ['code' => ErrorCode::WARNING, 'msg' => '课程信息错误1', 'data' => null];
        }
        $physicalStoreId = $courseOfflinePlanList[0]['physical_store_id'];
        $themeType = $courseOfflinePlanList[0]['theme_type'];

        //数据校验
        $courseOfflinePlanIdArray = array_column($courseOfflinePlanList,'id');
        $courseOfflineOrderExists = CourseOfflineOrder::query()->where(['member_id'=>$memberId,'pay_status'=>1,'order_status'=>0])->whereIn('course_offline_plan_id',$courseOfflinePlanIdArray)->exists();
        if($courseOfflineOrderExists === true){
            return ['code' => ErrorCode::WARNING, 'msg' => '课程不能重复预约', 'data' => null];
        }
        //课程信息
        $courseOfflineIdArray = array_column($courseOfflinePlanList,'course_offline_id');
        $courseOfflineList = CourseOffline::query()
            ->select(['id','name','price','img_url'])
            ->whereIn('id',$courseOfflineIdArray)
            ->where(['type'=>$courseType,'theme_type'=>$themeType])
            ->get();
        $courseOfflineList = $courseOfflineList->toArray();
        $combineCourseOfflineKey = array_column($courseOfflineList,'id');
        $courseOfflineList = array_combine($combineCourseOfflineKey,$courseOfflineList);

        //门店信息
        $physicalStoreInfo = PhysicalStore::query()
            ->select(['name'])
            ->where(['id'=>$physicalStoreId])
            ->first();
        if(empty($physicalStoreInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '门店信息错误', 'data' => null];
        }
        $physicalStoreInfo = $physicalStoreInfo->toArray();

        //课程总金额
        $totalPrice = '0';
        //线下课程节数数据
        $courseOfflineSectionData = [];
        foreach($courseOfflinePlanList as $key=>$value){
            $courseOfflineId = $value['course_offline_id'];
            $classStartTime = $value['class_start_time'];
            if(!isset($courseOfflineSectionData[$courseOfflineId])){
                $courseOfflineSectionData[$courseOfflineId] = 0;
            }
            if(!isset($courseOfflineList[$courseOfflineId])){
                return ['code' => ErrorCode::WARNING, 'msg' => '课程信息错误2', 'data' => null];
            }
            $courseOfflineInfo = $courseOfflineList[$courseOfflineId];
            if($value['class_start_time']<=$nowTime){
                return ['code' => ErrorCode::WARNING, 'msg' => '课程'.$courseOfflineInfo['name'].'已开课，已无法报名', 'data' => null];
            }
            $courseOfflineSectionData[$courseOfflineId] += 1;
            $price = $courseOfflineInfo['price'];
            $totalPrice = bcadd($totalPrice,$price,2);

            $courseOfflinePlanList[$key]['w'] = $weekArray[date("w",$classStartTime)];
            $courseOfflinePlanList[$key]['course_name'] = $courseOfflineInfo['name'];
            $courseOfflinePlanList[$key]['img_url'] = $courseOfflineInfo['img_url'];
            $courseOfflinePlanList[$key]['price'] = $price;
        }
        //会员卡信息
        if($isSample == 1){
            $vipCardOrderPhysicalStoreList = VipCardOrderPhysicalStore::query()
                ->leftJoin('vip_card_order','vip_card_order_physical_store.vip_card_order_id','=','vip_card_order.id')
                ->select(['vip_card_order_physical_store.vip_card_order_id'])
                ->where([['vip_card_order.member_id','=',$memberId],['vip_card_order.pay_status','=',1],['vip_card_order.expire_at','>=',$nowDate],['vip_card_order.order_status','=',0],['vip_card_order_physical_store.physical_store_id','=',$physicalStoreId]])
                ->whereIn('vip_card_order.order_type',[2,3])
                ->get();
            $vipCardOrderPhysicalStoreList = $vipCardOrderPhysicalStoreList->toArray();
            $vipCardOrderIdPhysicalStoreArray = array_column($vipCardOrderPhysicalStoreList,'vip_card_order_id');
            if(empty($vipCardOrderIdPhysicalStoreArray)){
                $vipCardOrderModel = VipCardOrder::query()
                    ->select(['id','currency_course','currency_course_used','course_unit_price'])
                    ->where([['member_id','=',$memberId],['pay_status','=',1],['expire_at','>=',$nowDate],['order_status','=',0],['applicable_store_type','=',1]])
                    ->whereIn('order_type',[2,3]);
            }else{
                $vipCardOrderModel = VipCardOrder::query()
                    ->select(['id','currency_course','currency_course_used','course_unit_price'])
                    ->whereIn('id',$vipCardOrderIdPhysicalStoreArray)->orWhere(function ($query)use($memberId,$nowDate) {
                        $query->where([['member_id','=',$memberId],['pay_status','=',1],['expire_at','>=',$nowDate],['order_status','=',0],['applicable_store_type','=',1]])
                            ->whereIn('order_type',[2,3]);
                    });
            }
        }else{
            $vipCardOrderPhysicalStoreList = VipCardOrderPhysicalStore::query()
                ->leftJoin('vip_card_order','vip_card_order_physical_store.vip_card_order_id','=','vip_card_order.id')
                ->select(['vip_card_order_physical_store.vip_card_order_id'])
                ->where([['vip_card_order.member_id','=',$memberId],['vip_card_order.pay_status','=',1],['vip_card_order.expire_at','>=',$nowDate],['vip_card_order.order_status','=',0],['vip_card_order.card_theme_type','=',$themeType],['vip_card_order_physical_store.physical_store_id','=',$physicalStoreId]])
                ->whereIn('vip_card_order.order_type',[1,4])
                ->get();
            $vipCardOrderPhysicalStoreList = $vipCardOrderPhysicalStoreList->toArray();
            $vipCardOrderIdPhysicalStoreArray = array_column($vipCardOrderPhysicalStoreList,'vip_card_order_id');
            if(empty($vipCardOrderIdPhysicalStoreArray)){
                $vipCardOrderModel = VipCardOrder::query()
                    ->select(['id','course1','course1_used','course2','course2_used','course3','course3_used','course_unit_price'])
                    ->where([['member_id','=',$memberId],['pay_status','=',1],['expire_at','>=',$nowDate],['order_status','=',0],['applicable_store_type','=',1],['card_theme_type','=',$themeType]])
                    ->whereIn('order_type',[1,4]);
            }else{
                $vipCardOrderModel = VipCardOrder::query()
                    ->select(['id','course1','course1_used','course2','course2_used','course3','course3_used','course_unit_price'])
                    ->whereIn('id',$vipCardOrderIdPhysicalStoreArray)->orWhere(function ($query)use($memberId,$nowDate,$themeType) {
                        $query->where([['member_id','=',$memberId],['pay_status','=',1],['expire_at','>=',$nowDate],['order_status','=',0],['applicable_store_type','=',1],['card_theme_type','=',$themeType]])
                            ->whereIn('order_type',[1,4]);
                    });
            }
        }
        $vipCardOrderList = $vipCardOrderModel->orderBy('expire_at')->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $vipCardOrderIdArray = array_column($vipCardOrderList,'id');
        $vipCardOrderDynamicCourseList = VipCardOrderDynamicCourse::query()
            ->select(['id','vip_card_order_id','course','course_used','type','week'])
            ->whereIn('vip_card_order_id',$vipCardOrderIdArray)
            ->get();
        $vipCardOrderDynamicCourseList = $vipCardOrderDynamicCourseList->toArray();
        $vipCardOrderDynamicCourseList = $this->functions->arrayGroupBy($vipCardOrderDynamicCourseList,'vip_card_order_id');

        $useVipCardOrderList = [];
        $useVipCardOrderChildList = [];
        foreach($courseOfflinePlanList as $key=>$value){
            $isPass = 0;
            $w = $value['w'];
            $useVipCardOrderInfo = [];
            $useVipCardOrderChildInfo = [];
            $useCourseUnitPrice = 0;
            $useVipCardOrderId = 0;
            $useVipCardOrderChildId = 0;
            foreach($vipCardOrderList as $k=>$item){
                $vipCardOrderId = $item['id'];
                $dynamicCourse = $vipCardOrderDynamicCourseList[$vipCardOrderId] ?? [];
                if($isSample == 1){
                    if($item['currency_course']>$item['currency_course_used']){
                        $vipCardOrderList[$k]['currency_course_used'] = $item['currency_course_used']+1;
                        $isPass = 1;
                        $useVipCardOrderInfo = ['id'=>$vipCardOrderId];
                        $useCourseUnitPrice = $item['course_unit_price'];
                        $useVipCardOrderId = $vipCardOrderId;
                    }
                }else{
                    switch ($courseType){
                        case 1:
                            foreach($dynamicCourse as $k1=>$item1){
                                $applyWeek = json_decode($item1['week'],true);
                                if($item1['type'] == 1 && $item1['course']>$item1['course_used'] && in_array($w,$applyWeek)){
                                    $dynamicCourse[$k1]['course_used'] = $item1['course_used']+1;
                                    $isPass = 1;
                                    $useVipCardOrderChildInfo = ['id'=>$item1['id']];
                                    $useVipCardOrderChildId = $item1['id'];
                                    break;
                                }
                            }
                            if($isPass === 0 && $item['course1']>$item['course1_used']){
                                $vipCardOrderList[$k]['course1_used'] = $item['course1_used']+1;
                                $isPass = 1;
                                $useVipCardOrderInfo = ['id'=>$vipCardOrderId];
                            }
                            if($isPass === 1){
                                $vipCardOrderDynamicCourseList[$vipCardOrderId] = $dynamicCourse;
                                $useCourseUnitPrice = $item['course_unit_price'];
                                $useVipCardOrderId = $vipCardOrderId;
                            }
                            break;
                        case 2:
                            foreach($dynamicCourse as $k1=>$item1){
                                $applyWeek = json_decode($item1['week'],true);
                                if($item1['type'] == 2 && $item1['course']>$item1['course_used'] && in_array($w,$applyWeek)){
                                    $dynamicCourse[$k1]['course_used'] = $item1['course_used']+1;
                                    $isPass = 1;
                                    $useVipCardOrderChildInfo = ['id'=>$item1['id']];
                                    $useVipCardOrderChildId = $item1['id'];
                                    break;
                                }
                            }
                            if($isPass === 0 && $item['course2']>$item['course2_used']){
                                $vipCardOrderList[$k]['course2_used'] = $item['course2_used']+1;
                                $isPass = 1;
                                $useVipCardOrderInfo = ['id'=>$vipCardOrderId];
                            }
                            if($isPass === 1){
                                $vipCardOrderDynamicCourseList[$vipCardOrderId] = $dynamicCourse;
                                $useCourseUnitPrice = $item['course_unit_price'];
                                $useVipCardOrderId = $vipCardOrderId;
                            }
                            break;
                        case 3:
                            foreach($dynamicCourse as $k1=>$item1){
                                $applyWeek = json_decode($item1['week'],true);
                                if($item1['type'] == 3 && $item1['course']>$item1['course_used'] && in_array($w,$applyWeek)){
                                    $dynamicCourse[$k1]['course_used'] = $item1['course_used']+1;
                                    $isPass = 1;
                                    $useVipCardOrderChildInfo = ['id'=>$item1['id']];
                                    $useVipCardOrderChildId = $item1['id'];
                                    break;
                                }
                            }
                            if($isPass === 0 && $item['course3']>$item['course3_used']){
                                $vipCardOrderList[$k]['course3_used'] = $item['course3_used']+1;
                                $isPass = 1;
                                $useVipCardOrderInfo = ['id'=>$vipCardOrderId];
                            }
                            if($isPass === 1){
                                $vipCardOrderDynamicCourseList[$vipCardOrderId] = $dynamicCourse;
                                $useCourseUnitPrice = $item['course_unit_price'];
                                $useVipCardOrderId = $vipCardOrderId;
                            }
                            break;
                    }
                }
                if($isPass === 1){
                    break;
                }
            }
            if($isPass === 0){
                return ['code' => ErrorCode::WARNING, 'msg' => '会员卡可预约次数不足', 'data' => null];
            }
            $courseOfflinePlanList[$key]['vip_card_order_id'] = $useVipCardOrderId;
            $courseOfflinePlanList[$key]['course_unit_price'] = $useCourseUnitPrice;
            $courseOfflinePlanList[$key]['vip_card_order_child_id'] = $useVipCardOrderChildId;
            if(!empty($useVipCardOrderInfo)){
                $useVipCardOrderList[] = $useVipCardOrderInfo;
            }
            if(!empty($useVipCardOrderChildInfo)){
                $useVipCardOrderChildList[] = $useVipCardOrderChildInfo;
            }
        }
        //订单数据
        $payCode = 'PPPAY';
        $payStatus = 1;
        $orderNo = $this->functions->orderNo();
        $insertCourseOfflineOrderData = [];
        foreach($courseOfflinePlanList as $value){
            $courseOfflineOrderInfo = [];
            $classStartTime = date('Y-m-d H:i:s',$value['class_start_time']);
            $classEndTime = date('Y-m-d H:i:s',$value['class_end_time']);
            if($value['vip_card_order_id'] == 0){
                return ['code' => ErrorCode::WARNING, 'msg' => '会员卡可预约次数不足', 'data' => null];
            }

            $courseOfflineOrderId = IdGenerator::generate();
            $courseOfflineOrderInfo['id'] = $courseOfflineOrderId;
            $courseOfflineOrderInfo['order_no'] = $orderNo;
            $courseOfflineOrderInfo['member_id'] = $memberId;
            $courseOfflineOrderInfo['batch_no'] = $value['batch_no'];
            $courseOfflineOrderInfo['section_no'] = $value['section_no'];
            $courseOfflineOrderInfo['course_category_id'] = $value['course_category_id'];
            $courseOfflineOrderInfo['course_offline_id'] = $value['course_offline_id'];
            $courseOfflineOrderInfo['course_offline_plan_id'] = $value['id'];
            $courseOfflineOrderInfo['classroom_id'] = $value['classroom_id'];
            $courseOfflineOrderInfo['teacher_id'] = $value['teacher_id'];
            $courseOfflineOrderInfo['physical_store_id'] = $value['physical_store_id'];
            $courseOfflineOrderInfo['physical_store_name'] = $physicalStoreInfo['name'];
            $courseOfflineOrderInfo['course_name'] = $value['course_name'];
            $courseOfflineOrderInfo['classroom_name'] = $value['classroom_name'];
            $courseOfflineOrderInfo['teacher_name'] = $value['teacher_name'];
            $courseOfflineOrderInfo['price'] = $value['price'];
            $courseOfflineOrderInfo['start_at'] = $classStartTime;
            $courseOfflineOrderInfo['end_at'] = $classEndTime;
            $courseOfflineOrderInfo['course_type'] = $courseType;
            $courseOfflineOrderInfo['pay_status'] = $payStatus;
            $courseOfflineOrderInfo['pay_code'] = $payCode;
            $courseOfflineOrderInfo['img_url'] = $value['img_url'];
            $courseOfflineOrderInfo['vip_card_order_id'] = $value['vip_card_order_id'];
            $courseOfflineOrderInfo['theme_type'] = $themeType;
            $courseOfflineOrderInfo['course_unit_price'] = $value['course_unit_price'];
            $courseOfflineOrderInfo['is_sample'] = $isSample;
            $courseOfflineOrderInfo['vip_card_order_child_id'] = $value['vip_card_order_child_id'];
            $insertCourseOfflineOrderData[] = $courseOfflineOrderInfo;
        }

        Db::connection('jkc_edu')->beginTransaction();
        try{
            Db::connection('jkc_edu')->table('course_offline_order')->insert($insertCourseOfflineOrderData);
            foreach($courseOfflinePlanList as $value){
                $_courseOfflinePlanId = $value['id'];
                $courseOfflinePlanAffected = Db::connection('jkc_edu')->update("UPDATE course_offline_plan SET sign_up_num = sign_up_num + ? WHERE id = ? AND classroom_capacity >= sign_up_num+1", [1, $_courseOfflinePlanId]);
                if(!$courseOfflinePlanAffected){
                    Db::connection('jkc_edu')->rollBack();
                    Log::get()->info("courseOfflineCreateOrder[{$memberId}#{$_courseOfflinePlanId}]:排课信息修改失败");
                    return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                }
            }
            foreach($courseOfflineSectionData as $id=>$value){
                Db::connection('jkc_edu')->update("UPDATE course_offline SET sign_up_num = sign_up_num + ? WHERE id = ?", [$value, $id]);
            }
            if($isSample == 1){
                foreach($useVipCardOrderList as $value){
                    $vipCardOrderId = $value['id'];
                    $deductNum = 1;
                    $vipCardOrderAffected = Db::connection('jkc_edu')->update("UPDATE vip_card_order SET currency_course_used = currency_course_used + ? WHERE id = ? AND currency_course >= currency_course_used+{$deductNum}", [$deductNum, $vipCardOrderId]);
                    if(!$vipCardOrderAffected){
                        Db::connection('jkc_edu')->rollBack();
                        Log::get()->info("courseOfflineCreateOrder[{$memberId}#{$vipCardOrderId}]:会员卡信息修改失败");
                        return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                    }
                }
            }else if($courseType == 1){
                foreach($useVipCardOrderList as $value){
                    $vipCardOrderId = $value['id'];
                    $vipCardOrderAffected = Db::connection('jkc_edu')->update("UPDATE vip_card_order SET course1_used = course1_used + ? WHERE id = ? AND course1 >= course1_used+1", [1, $vipCardOrderId]);
                    if(!$vipCardOrderAffected){
                        Db::connection('jkc_edu')->rollBack();
                        Log::get()->info("courseOfflineCreateOrder[{$memberId}#{$vipCardOrderId}]:会员卡信息修改失败");
                        return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                    }
                }
                foreach($useVipCardOrderChildList as $value){
                    $vipCardOrderId = $value['id'];
                    $vipCardOrderAffected = Db::connection('jkc_edu')->update("UPDATE vip_card_order_dynamic_course SET course_used = course_used + ? WHERE id = ? AND course >= course_used+1 AND `type`=1", [1, $vipCardOrderId]);
                    if(!$vipCardOrderAffected){
                        Db::connection('jkc_edu')->rollBack();
                        Log::get()->info("courseOfflineCreateOrder[{$memberId}#{$vipCardOrderId}]:会员卡信息修改失败2");
                        return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                    }
                }
            }else if($courseType == 2){
                foreach($useVipCardOrderList as $value){
                    $vipCardOrderId = $value['id'];
                    $vipCardOrderAffected = Db::connection('jkc_edu')->update("UPDATE vip_card_order SET course2_used = course2_used + ? WHERE id = ? AND course2 >= course2_used+1", [1, $vipCardOrderId]);
                    if(!$vipCardOrderAffected){
                        Db::connection('jkc_edu')->rollBack();
                        Log::get()->info("courseOfflineCreateOrder[{$memberId}#{$vipCardOrderId}]:会员卡信息修改失败");
                        return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                    }
                }
                foreach($useVipCardOrderChildList as $value){
                    $vipCardOrderId = $value['id'];
                    $vipCardOrderAffected = Db::connection('jkc_edu')->update("UPDATE vip_card_order_dynamic_course SET course_used = course_used + ? WHERE id = ? AND course >= course_used+1 AND `type`=2", [1, $vipCardOrderId]);
                    if(!$vipCardOrderAffected){
                        Db::connection('jkc_edu')->rollBack();
                        Log::get()->info("courseOfflineCreateOrder[{$memberId}#{$vipCardOrderId}]:会员卡信息修改失败2");
                        return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                    }
                }
            }else if($courseType == 3){
                foreach($useVipCardOrderList as $value){
                    $vipCardOrderId = $value['id'];
                    $vipCardOrderAffected = Db::connection('jkc_edu')->update("UPDATE vip_card_order SET course3_used = course3_used + ? WHERE id = ? AND course3 >= course3_used+1", [1, $vipCardOrderId]);
                    if(!$vipCardOrderAffected){
                        Db::connection('jkc_edu')->rollBack();
                        Log::get()->info("courseOfflineCreateOrder[{$memberId}#{$vipCardOrderId}]:会员卡信息修改失败");
                        return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                    }
                }
                foreach($useVipCardOrderChildList as $value){
                    $vipCardOrderId = $value['id'];
                    $vipCardOrderAffected = Db::connection('jkc_edu')->update("UPDATE vip_card_order_dynamic_course SET course_used = course_used + ? WHERE id = ? AND course >= course_used+1 AND `type`=3", [1, $vipCardOrderId]);
                    if(!$vipCardOrderAffected){
                        Db::connection('jkc_edu')->rollBack();
                        Log::get()->info("courseOfflineCreateOrder[{$memberId}#{$vipCardOrderId}]:会员卡信息修改失败2");
                        return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                    }
                }
            }
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }
        $this->eventDispatcher->dispatch(new CourseOfflinePayRegistered((int)$memberId,(int)$isSample,$orderNo));
        $returnData = ['order_no'=>$orderNo];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 线下课程订单列表
     * @param array $params
     * @return array
     */
    public function courseOfflineOrderList(array $params): array
    {
        $memberLongitude = $params['longitude'];
        $memberLatitude = $params['latitude'];
        $searchStatus = $params['status'];
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        //线下课程订单信息
        if($searchStatus == 0){
            //待上课
            $courseOfflineOrderList = CourseOfflineOrder::query()
                ->select(['id','start_at','end_at','physical_store_id','course_name','img_url','classroom_name','course_offline_id','course_offline_plan_id','order_status','class_status','physical_store_name','course_type'])
                ->where([['member_id','=',$memberId],['pay_status','=',1],['order_status','=',0],['end_at','>=',$nowDate]])
                ->orderBy('start_at')
                ->get();
        }else{
            //已上课
            $courseOfflineOrderList = CourseOfflineOrder::query()
                ->select(['id','start_at','end_at','physical_store_id','course_name','img_url','classroom_name','course_offline_id','course_offline_plan_id','order_status','class_status','physical_store_name','course_type'])
                ->where([['member_id','=',$memberId],['pay_status','=',1]])->where(function ($query) use($nowDate) {
                    $query->where([['end_at','<',$nowDate],['order_status','=',0]])->orWhere('order_status','=',2);
                })
                ->orderBy('start_at','desc')
                ->get();
        }
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        if(empty($courseOfflineOrderList)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        //门店信息
        $physicalStoreIdArray = array_column($courseOfflineOrderList, 'physical_store_id');
        $physicalStoreIdArray = array_values(array_unique($physicalStoreIdArray));
        $physicalStoreList = PhysicalStore::query()
            ->select(['id','city_name','district_name','address','longitude','latitude','wechat_qr_code','store_phone'])
            ->whereIn('id',$physicalStoreIdArray)
            ->get();
        $physicalStoreList = $physicalStoreList->toArray();
        if(empty($physicalStoreList)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }
        $combinePhysicalStoreKey = array_column($physicalStoreList,'id');
        $physicalStoreList = array_combine($combinePhysicalStoreKey,$physicalStoreList);

        foreach($courseOfflineOrderList as $key=>$value){
            $physicalStoreId = $value['physical_store_id'];

            $status = 0;
            if($value['end_at'] < $nowDate){
                $status = 1;
                if($value['class_status'] == 0){
                    $status = 2;
                }
            }else{
                if($value['start_at']<=$nowDate){
                    $status = 4;
                }
            }
            if($value['order_status'] == 2){
                $status = 3;
            }
            $physicalStoreInfo = $physicalStoreList[$physicalStoreId];
            $linearDistance = '0';
            if($memberLatitude != 0 && $memberLongitude != 0){
                $linearDistance = $this->functions->linearDistance($memberLatitude,$memberLongitude,$physicalStoreInfo['latitude'],$physicalStoreInfo['longitude']);
            }
            unset($courseOfflineOrderList[$key]['physical_store_id']);

            $courseOfflineOrderList[$key]['city_name'] = $physicalStoreInfo['city_name'];
            $courseOfflineOrderList[$key]['district_name'] = $physicalStoreInfo['district_name'];
            $courseOfflineOrderList[$key]['address'] = $physicalStoreInfo['address'];
            $courseOfflineOrderList[$key]['distance'] = bcdiv((string)$linearDistance,'1000',2);
            $courseOfflineOrderList[$key]['status'] = $status;
            $courseOfflineOrderList[$key]['wechat_qr_code'] = $physicalStoreInfo['wechat_qr_code'];
            $courseOfflineOrderList[$key]['store_phone'] = $physicalStoreInfo['store_phone'];
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineOrderList];
    }

    /**
     * 线下课程课后反馈列表
     * @return array
     */
    public function courseOfflineFeedbackList(): array
    {
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        //线下课程订单信息
        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['id','start_at','end_at','physical_store_name','teacher_name','course_name','classroom_name','course_offline_id','course_offline_plan_id','course_type'])
            ->where([['member_id','=',$memberId],['pay_status','=',1],['end_at','<',$nowDate],['order_status','=',0]])
            ->orderBy('start_at','desc')
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        if(empty($courseOfflineOrderList)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }
        $courseOfflineIdArray = array_column($courseOfflineOrderList,'course_offline_id');
        $courseOfflinePlanIdArray = array_column($courseOfflineOrderList,'course_offline_plan_id');
        $courseOfflineOrderIdArray = array_column($courseOfflineOrderList,'id');
        //课程课堂情况
        $courseOfflineClassroomSituationList = CourseOfflineClassroomSituation::query()
            ->select(['course_offline_plan_id','img_url'])
            ->whereIn('course_offline_plan_id',$courseOfflinePlanIdArray)
            ->get();
        $courseOfflineClassroomSituationList = $courseOfflineClassroomSituationList->toArray();
        $courseOfflineClassroomSituationList = $this->functions->arrayGroupBy($courseOfflineClassroomSituationList,'course_offline_plan_id');

        $courseOfflineOutlineList = CourseOfflineOutline::query()
                ->select(['course_offline_id','content'])
                ->whereIn('course_offline_id',$courseOfflineIdArray)
                ->get();
        $courseOfflineOutlineList = $courseOfflineOutlineList->toArray();
        $courseOfflineOutlineList = $this->functions->arrayGroupBy($courseOfflineOutlineList,'course_offline_id');

        $courseOfflineOrderEvaluationList = CourseOfflineOrderEvaluation::query()
            ->select(['course_offline_order_id'])
            ->whereIn('course_offline_order_id',$courseOfflineOrderIdArray)
            ->get();
        $courseOfflineOrderEvaluationList = $courseOfflineOrderEvaluationList->toArray();
        $courseOfflineOrderEvaluationList = array_column($courseOfflineOrderEvaluationList,'course_offline_order_id');

        foreach($courseOfflineOrderList as $key=>$value){
            $courseOfflineId = $value['course_offline_id'];
            $courseOfflinePlanId = $value['course_offline_plan_id'];
            $classStartTime = date('m.d H:i',strtotime($value['start_at']));
            $classEndTime = date('H:i',strtotime($value['end_at']));
            $evaluationStatus = 0;
            if(isset($courseOfflineClassroomSituationList[$courseOfflinePlanId])){
                $evaluationStatus = in_array($value['id'],$courseOfflineOrderEvaluationList) === true ? 2 : 1;
            }

            $courseOfflineClassroomSituationInfo = $courseOfflineClassroomSituationList[$courseOfflinePlanId] ?? [];
            $courseOfflineClassroomSituationInfo = array_column($courseOfflineClassroomSituationInfo,'img_url');
            $courseOfflineOutlineInfo = $courseOfflineOutlineList[$courseOfflineId] ?? [];
            $courseOfflineOutlineInfo = array_column($courseOfflineOutlineInfo,'content');
            $courseOfflineOrderList[$key]['class_time'] = "{$classStartTime} 至 {$classEndTime}";
            $courseOfflineOrderList[$key]['classroom_situation'] = $courseOfflineClassroomSituationInfo;
            $courseOfflineOrderList[$key]['outline'] = $courseOfflineOutlineInfo;
            $courseOfflineOrderList[$key]['evaluation_status'] = $evaluationStatus;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineOrderList];
    }

    /**
     * 线下课程订单调课
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOfflineOrderReadjust(array $params): array
    {
        $id = $params['id'];
        $memberRemarks = $params['member_remarks'];
        $memberId = $this->memberId;

        $courseOfflineOrderInfo = CourseOfflineOrder::query()
            ->select(['course_offline_plan_id','physical_store_id'])
            ->where(['id'=>$id])
            ->first();
        if(empty($courseOfflineOrderInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '约课信息错误', 'data' => null];
        }
        $courseOfflineOrderInfo = $courseOfflineOrderInfo->toArray();

        $insertCourseOfflineOrderReadjustData['id'] = IdGenerator::generate();
        $insertCourseOfflineOrderReadjustData['member_id'] = $memberId;
        $insertCourseOfflineOrderReadjustData['course_offline_order_id'] = $id;
        $insertCourseOfflineOrderReadjustData['course_offline_plan_id'] = $courseOfflineOrderInfo['course_offline_plan_id'];
        $insertCourseOfflineOrderReadjustData['physical_store_id'] = $courseOfflineOrderInfo['physical_store_id'];
        $insertCourseOfflineOrderReadjustData['member_remarks'] = $memberRemarks;

        CourseOfflineOrderReadjust::query()->insert($insertCourseOfflineOrderReadjustData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 线下课程订单取消
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function courseOfflineOrderCancel(array $params): array
    {
        $id = $params['id'];
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');

        $courseOfflineOrderInfo = CourseOfflineOrder::query()
            ->select(['vip_card_order_id','start_at','course_type','is_sample','course_offline_plan_id','batch_no','order_no','vip_card_order_child_id'])
            ->where(['id'=>$id,'member_id'=>$memberId,'pay_status'=>1,'order_status'=>0])
            ->first();
        if(empty($courseOfflineOrderInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '订单信息错误', 'data' => null];
        }
        $courseOfflineOrderInfo = $courseOfflineOrderInfo->toArray();
        if($nowDate >= $courseOfflineOrderInfo['start_at']){
            return ['code' => ErrorCode::WARNING, 'msg' => '已开课，无法取消', 'data' => null];
        }

        Db::connection('jkc_edu')->beginTransaction();
        try{
            $courseOfflineOrderAffected = CourseOfflineOrder::query()->where(['id'=>$id,'member_id'=>$memberId,'pay_status'=>1,'order_status'=>0])->update(['order_status'=>2]);
            if(!$courseOfflineOrderAffected){
                Db::connection('jkc_edu')->rollBack();
                return ['code' => ErrorCode::FAILURE, 'msg' => '课程订单修改失败', 'data' => null];
            }
            Db::connection('jkc_edu')->update('UPDATE course_offline_plan SET sign_up_num=sign_up_num-1 WHERE id=?', [$courseOfflineOrderInfo['course_offline_plan_id']]);
            if($courseOfflineOrderInfo['is_sample'] == 1){
                Db::connection('jkc_edu')->update('UPDATE vip_card_order SET currency_course_used=currency_course_used-1 WHERE id=?', [$courseOfflineOrderInfo['vip_card_order_id']]);
            }else{
                if($courseOfflineOrderInfo['vip_card_order_child_id'] != 0){
                    Db::connection('jkc_edu')->update('UPDATE vip_card_order_dynamic_course SET course_used=course_used-1 WHERE id=?', [$courseOfflineOrderInfo['vip_card_order_child_id']]);
                }else{
                    if($courseOfflineOrderInfo['course_type'] == 1){
                        Db::connection('jkc_edu')->update('UPDATE vip_card_order SET course1_used=course1_used-1 WHERE id=?', [$courseOfflineOrderInfo['vip_card_order_id']]);
                    }else if($courseOfflineOrderInfo['course_type'] == 2){
                        Db::connection('jkc_edu')->update('UPDATE vip_card_order SET course2_used=course2_used-1 WHERE id=?', [$courseOfflineOrderInfo['vip_card_order_id']]);
                    }else if($courseOfflineOrderInfo['course_type'] == 3){
                        Db::connection('jkc_edu')->update('UPDATE vip_card_order SET course3_used=course3_used-1 WHERE id=?', [$courseOfflineOrderInfo['vip_card_order_id']]);
                    }
                }
            }
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }
        $this->eventDispatcher->dispatch(new CourseOfflineCancelRegistered((int)$memberId,$courseOfflineOrderInfo['is_sample']));
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }
}