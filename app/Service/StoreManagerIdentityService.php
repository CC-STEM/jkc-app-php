<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\StoreManagerIdentityCache;
use App\Constants\ErrorCode;
use App\Constants\VipCardConstant;
use App\Logger\Log;
use App\Model\CourseOffline;
use App\Model\CourseOfflineOrder;
use App\Model\CourseOfflinePlan;
use App\Model\Member;
use App\Model\MemberBelongTo;
use App\Model\OrderGoods;
use App\Model\OrderInfo;
use App\Model\OrderRefund;
use App\Model\PhysicalStore;
use App\Model\PhysicalStoreAdmins;
use App\Model\PhysicalStoreAdminsPhysicalStore;
use App\Model\Teacher;
use App\Model\TeacherRevenueTarget;
use App\Model\VipCardOrder;
use App\Model\VipCardOrderMonthlyStatistics;
use App\Snowflake\IdGenerator;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;

class StoreManagerIdentityService extends BaseService
{
    public int $storeId;

    /**
     * StoreManagerIdentityService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
        $storeManagerIdentityCache = new StoreManagerIdentityCache();
        $this->storeId = $storeManagerIdentityCache->getPhysicalStoreId((int)$this->memberId);
    }

    /**
     * 管理门店列表
     * @param array $params
     * @return array
     */
    public function managePhysicalStoreList(array $params): array
    {
        $memberLongitude = $params['longitude'];
        $memberLatitude = $params['latitude'];
        $memberId = $this->memberId;
        $linearDistance = 0;

        //会员信息
        $memberInfo = Member::query()->select(['mobile'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"登录异常",'data'=>null];
        }
        $memberInfo = $memberInfo->toArray();
        $mobile = $memberInfo['mobile'];
        //店长
        $physicalStoreAdminsInfo = PhysicalStoreAdmins::query()
            ->select(['id'])
            ->where(['mobile'=>$mobile,'is_deleted'=>0,'is_store_manager'=>1])
            ->first();
        if(empty($physicalStoreAdminsInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '账户不存在', 'data' => null];
        }
        $physicalStoreAdminsInfo = $physicalStoreAdminsInfo->toArray();
        $physicalStoreAdminsId = $physicalStoreAdminsInfo['id'];

        //管理员门店
        $physicalStoreAdminsPhysicalStoreList = PhysicalStoreAdminsPhysicalStore::query()
            ->leftJoin('physical_store','physical_store_admins_physical_store.physical_store_id','=','physical_store.id')
            ->select(['physical_store_admins_physical_store.physical_store_id','physical_store.name','physical_store.longitude','physical_store.latitude'])
            ->where(['physical_store_admins_physical_store.physical_store_admins_id'=>$physicalStoreAdminsId])
            ->get();
        $physicalStoreAdminsPhysicalStoreList = $physicalStoreAdminsPhysicalStoreList->toArray();

        foreach($physicalStoreAdminsPhysicalStoreList as $key => $value){
            if($memberLatitude != 0 && $memberLongitude != 0){
                $linearDistance = $this->functions->linearDistance((float)$memberLatitude,(float)$memberLongitude,(float)$value['latitude'],(float)$value['longitude']);
            }
            unset($physicalStoreAdminsPhysicalStoreList[$key]['latitude']);
            unset($physicalStoreAdminsPhysicalStoreList[$key]['longitude']);
            $physicalStoreAdminsPhysicalStoreList[$key]['distance'] = bcdiv((string)$linearDistance,'1000',2);
        }
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$physicalStoreAdminsPhysicalStoreList];
    }

    /**
     * 指定门店
     * @param array $params
     * @return array
     * @throws \RedisException
     */
    public function selectedPhysicalStore(array $params): array
    {
        $id = $params['id'];
        $memberId = $this->memberId;

        $storeManagerIdentityCache = new StoreManagerIdentityCache();
        $storeManagerIdentityCache->setPhysicalStoreId((int)$id,(int)$memberId);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 门店老师列表
     * @return array
     */
    public function physicalStoreTeacherList(): array
    {
        $physicalStoreId = $this->storeId;

        $teacherList = Teacher::query()
            ->select(['id','name'])
            ->where(['physical_store_id'=>$physicalStoreId])
            ->get();
        $teacherList = $teacherList->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $teacherList];
    }

    /**
     * 门店营收统计数据
     * @param array $params
     * @return array
     */
    public function storeRevenueStatistics(array $params): array
    {
        $month = $params['month'] ?? date('Y-m');
        $monthStartAt = date('Y-m-01 00:00:00',strtotime($month));
        $monthEndAt = date('Y-m-t 23:59:59',strtotime($month));
        $physicalStoreId = $this->storeId;

        $physicalStoreInfo = PhysicalStore::query()
            ->select(['course_target_amount','revenue_target_amount'])
            ->where(['id'=>$physicalStoreId])
            ->first();
        $physicalStoreInfo = $physicalStoreInfo?->toArray();

        $vipCardOrderMonthAmount = VipCardOrder::query()
            ->where(['recommend_physical_store_id'=>$physicalStoreId,'pay_status'=>1,'order_status'=>0])
            ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
            ->sum('price');
        $orderGoodsMonthAmount = OrderGoods::query()
            ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
            ->where(['order_info.recommend_physical_store_id'=>$physicalStoreId,'order_goods.pay_status'=>1,'order_goods.order_status'=>0])
            ->whereBetween('order_goods.created_at',[$monthStartAt,$monthEndAt])
            ->sum(DB::connection('jkc_edu')->raw('pay_price*quantity'));
        $courseOfflineOrderMonthAmount = CourseOfflineOrder::query()
            ->where(['physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1,'class_status'=>1])
            ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
            ->sum('course_unit_price');

        $returnData = [
            'revenue_amount' => bcadd((string)$vipCardOrderMonthAmount,(string)$orderGoodsMonthAmount,2),
            'course_amount' => $courseOfflineOrderMonthAmount,
            'revenue_target_amount' => $physicalStoreInfo['revenue_target_amount'] ?? 0,
            'course_target_amount' => $physicalStoreInfo['course_target_amount'] ?? 0,
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
    }

    /**
     * 门店今日数据统计
     * @return array
     */
    public function storeTodayStatistics(): array
    {
        $todayStartAt = date('Y-m-d 00:00:00');
        $todayEndAt = date('Y-m-d 23:59:59');
        $todayStartTime = strtotime($todayStartAt);
        $todayEndTime = strtotime($todayEndAt);
        $physicalStoreId = $this->storeId;

        //课程订单
        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['member_id','course_offline_plan_id','order_status','course_unit_price','theme_type','class_status'])
            ->where(['physical_store_id'=>$physicalStoreId,'pay_status'=>1])
            ->whereBetween('start_at',[$todayStartAt,$todayEndAt])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $courseOfflineOrderList = $this->functions->arrayGroupBy($courseOfflineOrderList,'theme_type');
        //排课数据
        $courseOfflinePlanList = CourseOfflinePlan::query()
            ->select(['theme_type'])
            ->where(['physical_store_id'=>$physicalStoreId,'is_deleted'=>0])
            ->whereBetween('class_start_time',[$todayStartTime,$todayEndTime])
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();
        $courseOfflinePlanList = $this->functions->arrayGroupBy($courseOfflinePlanList,'theme_type');
        //会员卡订单
        $vipCardOrderList = VipCardOrder::query()
            ->select(['price'])
            ->where(['recommend_physical_store_id'=>$physicalStoreId,'pay_status'=>1,'order_status'=>0])
            ->whereBetween('created_at',[$todayStartAt,$todayEndAt])
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        //商品订单
        $orderGoodsList = OrderGoods::query()
            ->select(['order_goods.quantity','order_goods.pay_price'])
            ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
            ->where(['order_info.recommend_physical_store_id'=>$physicalStoreId,'order_goods.pay_status'=>1,'order_goods.order_status'=>0])
            ->whereBetween('order_goods.created_at',[$todayStartAt,$todayEndAt])
            ->get();
        $orderGoodsList = $orderGoodsList->toArray();
        $orderGoodsList = array_map(function ($value){
            $value['amount'] = bcmul((string)$value['pay_price'],(string)$value['quantity'],2);
            return $value;
        },$orderGoodsList);

        //常规班
        $courseOfflineOrderCancelMember1 = [];
        $courseOfflinePlanCount1 = isset($courseOfflinePlanList[1]) ? count($courseOfflinePlanList[1]) : 0;
        $courseOfflineOrderCount1 = 0;
        $courseOfflineOrderCancelCount1 = 0;
        $courseOfflineOrderAmount1 = '0';
        $courseOfflineOrderList1 = $courseOfflineOrderList[1] ?? [];
        foreach($courseOfflineOrderList1 as $value){
            $courseOfflineOrderCancelKey = $value['member_id'].'-'.$value['course_offline_plan_id'];
            if($value['order_status'] == 0){
                $courseOfflineOrderCount1++;
                if($value['class_status'] == 1){
                    $courseOfflineOrderAmount1 = bcadd($courseOfflineOrderAmount1,(string)$value['course_unit_price'],2);
                }
            }else if($value['order_status'] == 2 && !in_array($courseOfflineOrderCancelKey,$courseOfflineOrderCancelMember1)){
                $courseOfflineOrderCancelCount1++;
                $courseOfflineOrderCancelMember1[] = $courseOfflineOrderCancelKey;
            }
        }
        //精品小班
        $courseOfflineOrderCancelMember2 = [];
        $courseOfflinePlanCount2 = isset($courseOfflinePlanList[2]) ? count($courseOfflinePlanList[2]) : 0;
        $courseOfflineOrderCount2 = 0;
        $courseOfflineOrderCancelCount2 = 0;
        $courseOfflineOrderAmount2 = '0';
        $courseOfflineOrderList2 = $courseOfflineOrderList[2] ?? [];
        foreach($courseOfflineOrderList2 as $value){
            $courseOfflineOrderCancelKey = $value['member_id'].'-'.$value['course_offline_plan_id'];
            if($value['order_status'] == 0){
                $courseOfflineOrderCount2++;
                if($value['class_status'] == 1){
                    $courseOfflineOrderAmount2 = bcadd($courseOfflineOrderAmount2,(string)$value['course_unit_price'],2);
                }
            }else if($value['order_status'] == 2 && !in_array($courseOfflineOrderCancelKey,$courseOfflineOrderCancelMember2)){
                $courseOfflineOrderCancelCount2++;
                $courseOfflineOrderCancelMember2[] = $courseOfflineOrderCancelKey;
            }
        }
        //竞赛班
        $courseOfflineOrderCancelMember3 = [];
        $courseOfflinePlanCount3 = isset($courseOfflinePlanList[3]) ? count($courseOfflinePlanList[3]) : 0;
        $courseOfflineOrderCount3 = 0;
        $courseOfflineOrderCancelCount3 = 0;
        $courseOfflineOrderAmount3 = '0';
        $courseOfflineOrderList3 = $courseOfflineOrderList[3] ?? [];
        foreach($courseOfflineOrderList3 as $value){
            $courseOfflineOrderCancelKey = $value['member_id'].'-'.$value['course_offline_plan_id'];
            if($value['order_status'] == 0){
                $courseOfflineOrderCount3++;
                if($value['class_status'] == 1){
                    $courseOfflineOrderAmount3 = bcadd($courseOfflineOrderAmount3,(string)$value['course_unit_price'],2);
                }
            }else if($value['order_status'] == 2 && !in_array($courseOfflineOrderCancelKey,$courseOfflineOrderCancelMember3)){
                $courseOfflineOrderCancelCount3++;
                $courseOfflineOrderCancelMember3[] = $courseOfflineOrderCancelKey;
            }
        }
        //会员卡
        $vipCardOrderCount = count($vipCardOrderList);
        $vipCardOrderAmount = array_sum(array_column($vipCardOrderList,'price'));
        $vipCardOrderRefundAmount = VipCardOrder::query()
            ->where(['recommend_physical_store_id'=>$physicalStoreId,'pay_status'=>1,'order_status'=>3])
            ->whereBetween('created_at',[$todayStartAt,$todayEndAt])
            ->sum('price');
        //商品
        $orderGoodsCount = count($orderGoodsList);
        $orderGoodsAmount = array_sum(array_column($orderGoodsList,'amount'));
        $orderGoodsRefundAmount = OrderGoods::query()
            ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
            ->where(['order_info.recommend_physical_store_id'=>$physicalStoreId,'order_goods.pay_status'=>1,'order_goods.order_status'=>3])
            ->whereBetween('order_goods.created_at',[$todayStartAt,$todayEndAt])
            ->sum(DB::connection('jkc_edu')->raw('pay_price*quantity'));

        $returnData = [
            'course_offline_plan1' => $courseOfflinePlanCount1,
            'course_offline_order1' => $courseOfflineOrderCount1,
            'course_offline_order_cancel1' => $courseOfflineOrderCancelCount1,
            'course_offline_order_amount1' => $courseOfflineOrderAmount1,
            'course_offline_plan2' => $courseOfflinePlanCount2,
            'course_offline_order2' => $courseOfflineOrderCount2,
            'course_offline_order_cancel2' => $courseOfflineOrderCancelCount2,
            'course_offline_order_amount2' => $courseOfflineOrderAmount2,
            'course_offline_plan3' => $courseOfflinePlanCount3,
            'course_offline_order3' => $courseOfflineOrderCount3,
            'course_offline_order_cancel3' => $courseOfflineOrderCancelCount3,
            'course_offline_order_amount3' => $courseOfflineOrderAmount3,
            'vip_card_order' => $vipCardOrderCount,
            'vip_card_order_amount' => $vipCardOrderAmount,
            'vip_card_order_refund_amount' => $vipCardOrderRefundAmount,
            'order_goods' => $orderGoodsCount,
            'order_goods_amount' => $orderGoodsAmount,
            'order_goods_refund_amount' => $orderGoodsRefundAmount,
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
    }

    /**
     * 门店体验课
     * @param array $params
     * @return array
     */
    public function storeSampleCourseOfflineOrder(array $params): array
    {
        $searchDate = $params['date'] ?? date('Y-m-d');
        $startAt = date('Y-m-d 00:00:00',strtotime($searchDate));
        $endAt = date('Y-m-d 23:59:59',strtotime($searchDate));
        $physicalStoreId = $this->storeId;

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['member_id','course_offline_id','course_name','theme_type','start_at','end_at','vip_card_order_id','teacher_name'])
            ->where(['physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1,'is_sample'=>1])
            ->whereBetween('start_at',[$startAt,$endAt])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();

        $themeTypeEnum = [1=>'常规班',2=>'精品小班',3=>'代码编程'];
        foreach($courseOfflineOrderList as $key=>$value){
            $memberId = $value['member_id'];
            $courseOfflineId = $value['course_offline_id'];
            $vipCardOrderId = $value['vip_card_order_id'];

            $vipCardOrderExists = VipCardOrder::query()->where(['member_id'=>$memberId,'pay_status'=>1,'order_status'=>0,'order_type'=>1])->exists();
            if($vipCardOrderExists === true){
                unset($courseOfflineOrderList[$key]);
                continue;
            }
            $memberInfo = Member::query()->select(['name','mobile','parent_id'])->where(['id'=>$memberId])->first();
            $memberInfo = $memberInfo->toArray();

            $courseOfflineInfo = CourseOffline::query()->select(['suit_age_min'])->where(['id'=>$courseOfflineId])->first();
            $courseOfflineInfo = $courseOfflineInfo->toArray();

            $vipCardOrderInfo = VipCardOrder::query()->select(['order_type'])->where(['id'=>$vipCardOrderId])->first();
            $vipCardOrderInfo = $vipCardOrderInfo->toArray();

            if($vipCardOrderInfo['order_type'] == 3 && $memberInfo['parent_id'] != 0){
                $parentMemberInfo = Member::query()->select(['name'])->where(['id'=>$memberInfo['parent_id']])->first();
                $parentMemberInfo = $parentMemberInfo->toArray();
            }

            $courseOfflineOrderList[$key]['member_name'] = $memberInfo['name'];
            $courseOfflineOrderList[$key]['member_mobile'] = $memberInfo['mobile'];
            $courseOfflineOrderList[$key]['suit_age_min'] = $courseOfflineInfo['suit_age_min'];
            $courseOfflineOrderList[$key]['parent_name'] = $parentMemberInfo['name'] ?? '';
            $courseOfflineOrderList[$key]['start_at'] = date('H:i',strtotime($value['start_at']));
            $courseOfflineOrderList[$key]['end_at'] = date('H:i',strtotime($value['end_at']));
            $courseOfflineOrderList[$key]['course_type_text'] = $themeTypeEnum[$value['theme_type']];
            $courseOfflineOrderList[$key]['order_type'] = $vipCardOrderInfo['order_type'];
        }
        $courseOfflineOrderList = array_values($courseOfflineOrderList);

        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$courseOfflineOrderList];
    }

    /**
     * 门店每日数据统计
     * @param array $params
     * @return array
     */
    public function storeDailyStatistics(array $params): array
    {
        $nowMonth = date('Y-m');
        $month = $params['month'] ?? $nowMonth;
        $monthStartAt = date('Y-m-01 00:00:00',strtotime($month));
        $monthEndAt = date('Y-m-t 23:59:59',strtotime($month));
        $physicalStoreId = $this->storeId;

        $monthlyDateList = [];
        $monthStartTime = strtotime($monthStartAt);
        $week = date('d', $monthStartTime);
        $d = $month === $nowMonth ? date('d') : date('t', $monthStartTime);
        for ($i=1; $i<= $d; $i++){
            $monthlyDateList[$i] = date('Y-m-d' ,strtotime( '+'.$i-$week.' days',$monthStartTime));
        }
        $monthlyDateList = array_reverse($monthlyDateList,true);
        $monthlyDateList = array_flip($monthlyDateList);

        //课程订单
        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(Db::connection('jkc_edu')->raw('SUM(course_unit_price) as amount_sum'),DB::connection('jkc_edu')->raw('CAST(start_at AS date) as date_time'))
            ->where(['physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1,'class_status'=>1])
            ->whereBetween('start_at',[$monthStartAt,$monthEndAt])
            ->groupBy(DB::connection('jkc_edu')->raw('CAST(start_at AS date)'))
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $combineCourseOfflineOrderKey = array_column($courseOfflineOrderList,'date_time');
        $courseOfflineOrderList = array_combine($combineCourseOfflineOrderKey,$courseOfflineOrderList);
        //会员卡订单
        $vipCardOrderList = VipCardOrder::query()
            ->select(Db::connection('jkc_edu')->raw('SUM(price) as amount_sum'),DB::connection('jkc_edu')->raw('CAST(created_at AS date) as date_time'))
            ->where(['recommend_physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1])
            ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
            ->groupBy(DB::connection('jkc_edu')->raw('CAST(created_at AS date)'))
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $combineVipCardOrderKey = array_column($vipCardOrderList,'date_time');
        $vipCardOrderList = array_combine($combineVipCardOrderKey,$vipCardOrderList);
        //商品订单
        $orderGoodsList = OrderGoods::query()
            ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
            ->select(Db::connection('jkc_edu')->raw('SUM(order_goods.pay_price*order_goods.quantity) as amount_sum'),DB::connection('jkc_edu')->raw('CAST(order_goods.created_at AS date) as date_time'))
            ->where(['order_info.recommend_physical_store_id'=>$physicalStoreId,'order_goods.order_status'=>0,'order_goods.pay_status'=>1])
            ->whereBetween('order_goods.created_at',[$monthStartAt,$monthEndAt])
            ->groupBy(DB::connection('jkc_edu')->raw('CAST(order_goods.created_at AS date)'))
            ->get();
        $orderGoodsList = $orderGoodsList->toArray();
        $combineOrderGoodsKey = array_column($orderGoodsList,'date_time');
        $orderGoodsList = array_combine($combineOrderGoodsKey,$orderGoodsList);
        //商品退款
        $orderRefundList = OrderGoods::query()
            ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
            ->selectRaw('SUM(order_goods.pay_price*order_goods.quantity) as amount_sum,CAST(order_goods.created_at AS date) as date_time')
            ->where(['order_info.recommend_physical_store_id'=>$physicalStoreId,'order_goods.pay_status'=>1,'order_goods.order_status'=>3])
            ->whereBetween('order_goods.created_at',[$monthStartAt,$monthEndAt])
            ->groupBy(DB::connection('jkc_edu')->raw('CAST(order_goods.created_at AS date)'))
            ->get();
        $orderRefundList = $orderRefundList->toArray();
        $combineOrderRefundKey = array_column($orderRefundList,'date_time');
        $orderRefundList = array_combine($combineOrderRefundKey,$orderRefundList);
        //会员卡退款
        $vipCardOrderRefundList = VipCardOrder::query()
            ->select(Db::connection('jkc_edu')->raw('SUM(price) as amount_sum'),DB::connection('jkc_edu')->raw('CAST(created_at AS date) as date_time'))
            ->where(['recommend_physical_store_id'=>$physicalStoreId,'order_status'=>3,'pay_status'=>1])
            ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
            ->groupBy(DB::connection('jkc_edu')->raw('CAST(created_at AS date)'))
            ->get();
        $vipCardOrderRefundList = $vipCardOrderRefundList->toArray();
        $combineVipCardOrderRefundKey = array_column($vipCardOrderRefundList,'date_time');
        $vipCardOrderRefundList = array_combine($combineVipCardOrderRefundKey,$vipCardOrderRefundList);

        foreach($monthlyDateList as $key=>$value){
            $course = '0';
            $vipCard = '0';
            $goods = '0';
            $refund = '0';
            if(isset($courseOfflineOrderList[$key])){
                $course = (string)$courseOfflineOrderList[$key]['amount_sum'];
            }
            if(isset($vipCardOrderList[$key])){
                $vipCard = (string)$vipCardOrderList[$key]['amount_sum'];
            }
            if(isset($orderGoodsList[$key])){
                $goods = (string)$orderGoodsList[$key]['amount_sum'];
            }
            if(isset($orderRefundList[$key])){
                $refund = (string)$orderRefundList[$key]['amount_sum'];
            }
            if(isset($vipCardOrderRefundList[$key])){
                $refund = bcadd($refund,(string)$vipCardOrderRefundList[$key]['amount_sum'],2);
            }
            $revenue = bcadd($vipCard,$goods,2);
            $monthlyDateList[$key] = ['course'=>$course,'vip_card'=>$vipCard,'goods'=>$goods,'refund'=>$refund,'revenue'=>$revenue,'date_text'=>date('Y.m.d',strtotime($key))];
        }
        $monthlyDateList = array_values($monthlyDateList);

        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$monthlyDateList];
    }

    /**
     * 门店每日明细数据
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function storeDailyDetail(array $params): array
    {
        $nowDate = date('Y-m-d H:i:s');
        $searchType = !empty($params['type']) ? $params['type'] : 1;
        $searchKeywords = (string)$params['keywords'];
        $teacherId = $params['teacher_id'];
        $searchDate = $params['date'] ?? date('Y-m-d');
        $startAt = date('Y-m-d 00:00:00',strtotime($searchDate));
        $endAt = date('Y-m-d 23:59:59',strtotime($searchDate));
        $endAt = min($nowDate,$endAt);
        $startTime = strtotime($startAt);
        $endTime = strtotime($endAt);
        $physicalStoreId = $this->storeId;

        $memberIdArray = [];
        if(!empty($searchKeywords)){
            if(is_numeric($searchKeywords) && strlen($searchKeywords)===11){
                $memberList = Member::query()->select(['id'])->where(['mobile'=>$searchKeywords])->get();
            }else{
                $memberList = Member::query()->select(['id'])->where(['name'=>$searchKeywords])->get();
            }
            $memberList = $memberList->toArray();
            if(empty($memberList)){
                return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>[]];
            }
            $memberIdArray = array_column($memberList,'id');
        }

        $courseOfflinePlanList = [];
        $orderGoodsList = [];
        $vipCardOrderList = [];
        if($searchType == 1){
            //课程数据
            if($memberIdArray !== []){
                $courseOfflineOrderList = CourseOfflineOrder::query()
                    ->select(['course_offline_plan_id'])
                    ->whereIn('member_id',$memberIdArray)
                    ->whereBetween('start_at',[$startAt,$endAt])
                    ->groupBy('course_offline_plan_id')
                    ->get();
                $courseOfflineOrderList = $courseOfflineOrderList->toArray();
                $courseOfflinePlanIdArray = array_column($courseOfflineOrderList,'course_offline_plan_id');
            }
            if($memberIdArray === [] || (!empty($memberIdArray) && !empty($courseOfflinePlanIdArray))){
                $where1 = ['course_offline_plan.physical_store_id'=>$physicalStoreId];
                if(!empty($teacherId)){
                    $where1['course_offline_plan.teacher_id'] = $teacherId;
                }
                $courseOfflinePlanModel = CourseOfflinePlan::query()
                    ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
                    ->select(['course_offline_plan.id','course_offline_plan.class_start_time','course_offline_plan.class_end_time','course_offline_plan.teacher_name','course_offline_plan.sign_up_num','course_offline.suit_age_min','course_offline.type','course_offline.name'])
                    ->where($where1)
                    ->whereBetween('course_offline_plan.class_start_time',[$startTime,$endTime]);
                if(!empty($courseOfflinePlanIdArray)){
                    $courseOfflinePlanModel->whereIn('course_offline_plan.id',$courseOfflinePlanIdArray);
                }
                $courseOfflinePlanList = $courseOfflinePlanModel->get();
                $courseOfflinePlanList = $courseOfflinePlanList->toArray();
            }

            //商品数据
            $where2 = ['order_info.recommend_physical_store_id'=>$physicalStoreId,'order_goods.pay_status'=>1,'order_goods.order_status'=>0];
            if(!empty($teacherId)){
                $where2['order_info.recommend_teacher_id'] = $teacherId;
            }
            $orderGoodsModel = OrderGoods::query()
                ->leftJoin('member','order_goods.member_id','=','member.id')
                ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
                ->select(['member.name as member_name','member.mobile as member_mobile','order_goods.goods_name','order_goods.prop_value_str','order_goods.pay_at','order_goods.pay_price','order_goods.quantity','order_info.recommend_teacher_id'])
                ->where($where2)
                ->whereBetween('order_goods.pay_at',[$startAt,$endAt]);
            if(!empty($memberIdArray)){
                $orderGoodsModel->whereIn('order_goods.member_id',$memberIdArray);
            }
            $orderGoodsList = $orderGoodsModel->get();
            $orderGoodsList = $orderGoodsList->toArray();

            //会员卡数据
            $where3 = ['vip_card_order.recommend_physical_store_id'=>$physicalStoreId,'vip_card_order.pay_status'=>1,'vip_card_order.order_status'=>0];
            if(!empty($teacherId)){
                $where3['vip_card_order.recommend_teacher_id'] = $teacherId;
            }
            $vipCardOrderModel = VipCardOrder::query()
                ->leftJoin('member','vip_card_order.member_id','=','member.id')
                ->select(['member.name as member_name','member.mobile as member_mobile','vip_card_order.order_title','vip_card_order.price','vip_card_order.created_at','vip_card_order.recommend_teacher_id'])
                ->where($where3)
                ->whereBetween('vip_card_order.created_at',[$startAt,$endAt]);
            if(!empty($memberIdArray)){
                $vipCardOrderModel->whereIn('vip_card_order.member_id',$memberIdArray);
            }
            $vipCardOrderList = $vipCardOrderModel->get();
            $vipCardOrderList = $vipCardOrderList->toArray();
        }

        //会员卡退款
        $where4 = ['vip_card_order.recommend_physical_store_id'=>$physicalStoreId,'vip_card_order.pay_status'=>1,'vip_card_order.order_status'=>3];
        if(!empty($teacherId)){
            $where4['vip_card_order.recommend_teacher_id'] = $teacherId;
        }
        $vipCardOrderRefundModel = VipCardOrder::query()
            ->leftJoin('member','vip_card_order.member_id','=','member.id')
            ->select(['member.name as member_name','member.mobile as member_mobile','vip_card_order.order_title','vip_card_order.price','vip_card_order.created_at','vip_card_order.recommend_teacher_id'])
            ->where($where4)
            ->whereBetween('vip_card_order.created_at',[$startAt,$endAt]);
        if(!empty($memberIdArray)){
            $vipCardOrderRefundModel->whereIn('vip_card_order.member_id',$memberIdArray);
        }
        $vipCardOrderRefundList = $vipCardOrderRefundModel->get();
        $vipCardOrderRefundList = $vipCardOrderRefundList->toArray();

        //商品退款
        $where5 = ['order_info.recommend_physical_store_id'=>$physicalStoreId,'order_goods.pay_status'=>1,'order_goods.order_status'=>3];
        if(!empty($teacherId)){
            $where5['order_info.recommend_teacher_id'] = $teacherId;
        }
        $orderGoodsRefundModel = OrderGoods::query()
            ->leftJoin('member','order_goods.member_id','=','member.id')
            ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
            ->select(['member.name as member_name','member.mobile as member_mobile','order_goods.goods_name','order_goods.prop_value_str','order_goods.pay_at','order_goods.pay_price','order_goods.quantity','order_info.recommend_teacher_id'])
            ->where($where5)
            ->whereBetween('order_goods.created_at',[$startAt,$endAt]);
        if(!empty($memberIdArray)){
            $orderGoodsRefundModel->whereIn('order_goods.member_id',$memberIdArray);
        }
        $orderGoodsRefundList = $orderGoodsRefundModel->get();
        $orderGoodsRefundList = $orderGoodsRefundList->toArray();

        $dailyDetailList = [];
        $courseTypeEnum = [1=>'常规课',2=>'活动课',3=>'专业课'];
        foreach($courseOfflinePlanList as $value){
            $dailyData = [];
            $courseOfflineOrderSignInCount = CourseOfflineOrder::query()->where(['course_offline_plan_id'=>$value['id'],'class_status'=>1])->count();
            $courseOfflineOrderAmount = CourseOfflineOrder::query()->where(['course_offline_plan_id'=>$value['id'],'pay_status'=>1,'order_status'=>0,'class_status'=>1])->sum('course_unit_price');
            $dailyData['detail_type'] = 1;
            $dailyData['course_offline_plan_id'] = $value['id'];
            $dailyData['detail_name'] = $value['name'];
            $dailyData['member_name'] = $value['member_name'];
            $dailyData['member_mobile'] = $value['member_mobile'];
            $dailyData['amount'] = $courseOfflineOrderAmount;
            $dailyData['detail_prop'] = $courseTypeEnum[$value['type']];
            $dailyData['belong_to_name'] = $value['teacher_name'];
            $dailyData['suit_age_min'] = $value['suit_age_min'];
            $dailyData['course_sign_up_num'] = $value['sign_up_num'];
            $dailyData['course_class_attendance_num'] = $courseOfflineOrderSignInCount;
            $dailyData['date1'] = date('H:i',$value['class_start_time']);
            $dailyData['date2'] = date('H:i',$value['class_end_time']);
            $dailyData['sort'] = $value['class_start_time'];
            $dailyDetailList[] = $dailyData;
        }

        foreach($orderGoodsList as $value){
            $dailyData = [];
            $teacherInfo = null;
            if($value['recommend_teacher_id'] != 0){
                $teacherInfo = Teacher::query()
                    ->select(['name'])
                    ->where(['id'=>$value['recommend_teacher_id']])
                    ->first();
                $teacherInfo = $teacherInfo?->toArray();
            }
            $orderGoodsAmount = bcmul((string)$value['pay_price'],(string)$value['quantity'],2);
            $belongToName = $teacherInfo['name'] ?? '无';

            $dailyData['detail_type'] = 2;
            $dailyData['course_offline_plan_id'] = 0;
            $dailyData['detail_name'] = $value['goods_name'];
            $dailyData['member_name'] = $value['member_name'];
            $dailyData['member_mobile'] = $value['member_mobile'];
            $dailyData['amount'] = $orderGoodsAmount;
            $dailyData['detail_prop'] = $value['prop_value_str'];
            $dailyData['belong_to_name'] = $belongToName;
            $dailyData['suit_age_min'] = 0;
            $dailyData['course_sign_up_num'] = 0;
            $dailyData['course_class_attendance_num'] = 0;
            $dailyData['date1'] = $value['pay_at'];
            $dailyData['date2'] = null;
            $dailyData['sort'] = strtotime($value['pay_at']);
            $dailyDetailList[] = $dailyData;
        }

        foreach($vipCardOrderList as $value){
            $dailyData = [];
            $teacherInfo = null;
            if($value['recommend_teacher_id'] != 0){
                $teacherInfo = Teacher::query()
                    ->select(['name'])
                    ->where(['id'=>$value['recommend_teacher_id']])
                    ->first();
                $teacherInfo = $teacherInfo?->toArray();
            }
            $belongToName = $teacherInfo['name'] ?? '无';

            $dailyData['detail_type'] = 3;
            $dailyData['course_offline_plan_id'] = 0;
            $dailyData['detail_name'] = $value['order_title'];
            $dailyData['member_name'] = $value['member_name'];
            $dailyData['member_mobile'] = $value['member_mobile'];
            $dailyData['amount'] = $value['price'];
            $dailyData['detail_prop'] = '';
            $dailyData['belong_to_name'] = $belongToName;
            $dailyData['suit_age_min'] = 0;
            $dailyData['course_sign_up_num'] = 0;
            $dailyData['course_class_attendance_num'] = 0;
            $dailyData['date1'] = $value['created_at'];
            $dailyData['date2'] = null;
            $dailyData['sort'] = strtotime($value['created_at']);
            $dailyDetailList[] = $dailyData;
        }

        foreach($vipCardOrderRefundList as $value){
            $dailyData = [];
            $teacherInfo = null;
            if($value['recommend_teacher_id'] != 0){
                $teacherInfo = Teacher::query()
                    ->select(['name'])
                    ->where(['id'=>$value['recommend_teacher_id']])
                    ->first();
                $teacherInfo = $teacherInfo?->toArray();
            }
            $belongToName = $teacherInfo['name'] ?? '无';

            $dailyData['detail_type'] = 4;
            $dailyData['course_offline_plan_id'] = 0;
            $dailyData['detail_name'] = $value['order_title'];
            $dailyData['member_name'] = $value['member_name'];
            $dailyData['member_mobile'] = $value['member_mobile'];
            $dailyData['amount'] = $value['price'];
            $dailyData['detail_prop'] = '';
            $dailyData['belong_to_name'] = $belongToName;
            $dailyData['suit_age_min'] = 0;
            $dailyData['course_sign_up_num'] = 0;
            $dailyData['course_class_attendance_num'] = 0;
            $dailyData['date1'] = $value['created_at'];
            $dailyData['date2'] = null;
            $dailyData['sort'] = strtotime($value['created_at']);
            $dailyDetailList[] = $dailyData;
        }

        foreach($orderGoodsRefundList as $value){
            $dailyData = [];
            $teacherInfo = null;
            if($value['recommend_teacher_id'] != 0){
                $teacherInfo = Teacher::query()
                    ->select(['name'])
                    ->where(['id'=>$value['recommend_teacher_id']])
                    ->first();
                $teacherInfo = $teacherInfo?->toArray();
            }
            $orderGoodsAmount = bcmul((string)$value['pay_price'],(string)$value['quantity'],2);
            $belongToName = $teacherInfo['name'] ?? '无';

            $dailyData['detail_type'] = 5;
            $dailyData['course_offline_plan_id'] = 0;
            $dailyData['detail_name'] = $value['goods_name'];
            $dailyData['member_name'] = $value['member_name'];
            $dailyData['member_mobile'] = $value['member_mobile'];
            $dailyData['amount'] = $orderGoodsAmount;
            $dailyData['detail_prop'] = $value['prop_value_str'];
            $dailyData['belong_to_name'] = $belongToName;
            $dailyData['suit_age_min'] = 0;
            $dailyData['course_sign_up_num'] = 0;
            $dailyData['course_class_attendance_num'] = 0;
            $dailyData['date1'] = $value['pay_at'];
            $dailyData['date2'] = null;
            $dailyData['sort'] = strtotime($value['pay_at']);
            $dailyDetailList[] = $dailyData;
        }
        array_multisort(array_column($dailyDetailList, 'sort'), SORT_DESC, $dailyDetailList);
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$dailyDetailList];
    }

    /**
     * 排课详情
     * @param int $id
     * @return array
     */
    public function courseOfflinePlanDetail(int $id): array
    {
        $nowTime = time();

        $courseOfflinePlanInfo = CourseOfflinePlan::query()
            ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
            ->leftJoin('physical_store','course_offline_plan.physical_store_id','=','physical_store.id')
            ->select(['course_offline.suit_age_min','course_offline.img_url','course_offline.name as course_name','course_offline_plan.teacher_name','course_offline_plan.class_start_time','course_offline_plan.class_end_time','physical_store.name as physical_store_name'])
            ->where(['course_offline_plan.id'=>$id])
            ->first();
        $courseOfflinePlanInfo = $courseOfflinePlanInfo->toArray();
        $classEndTime = $courseOfflinePlanInfo['class_end_time'];

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->leftJoin('member','course_offline_order.member_id','=','member.id')
            ->select(['member.name as member_name','member.mobile as member_mobile','member.gender as member_gender','member.avatar','course_offline_order.member_id','course_offline_order.course_unit_price','course_offline_order.class_status','course_offline_order.order_status'])
            ->where(['course_offline_order.course_offline_plan_id'=>$id])
            ->orderBy('course_offline_order.order_status')
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $courseOfflinePlanInfo['class_start_time'] = date('H:i',$courseOfflinePlanInfo['class_start_time']);
        $courseOfflinePlanInfo['class_end_time'] = date('H:i',$courseOfflinePlanInfo['class_end_time']);

        $courseOfflineOrderMemberIdArray = [];
        $classAttendanceNum = 0;
        $signUpNum = 0;
        foreach($courseOfflineOrderList as $key=>$value){
            if(in_array($value['member_id'],$courseOfflineOrderMemberIdArray)){
                unset($courseOfflineOrderList[$key]);
                continue;
            }
            $signUpNum++;
            $orderStatus = 1;
            if($value['order_status'] == 2){
                $orderStatus = 4;
            }else if($value['class_status'] == 1){
                $orderStatus = 2;
                $classAttendanceNum++;
            }else if($classEndTime<$nowTime && $value['class_status'] == 0){
                $orderStatus = 3;
            }
            unset($courseOfflineOrderList[$key]['class_status']);
            $courseOfflineOrderMemberIdArray[] = $value['member_id'];

            $courseOfflineOrderList[$key]['order_status'] = $orderStatus;
        }
        $courseOfflinePlanInfo['sign_up_num'] = $signUpNum;
        $courseOfflinePlanInfo['course_class_attendance_num'] = $classAttendanceNum;
        $courseOfflinePlanInfo['students'] = !empty($courseOfflineOrderList) ? array_values($courseOfflineOrderList) : [];

        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$courseOfflinePlanInfo];
    }

    /**
     * 门店课表
     * @param array $params
     * @return array
     */
    public function storeCurriculum(array $params): array
    {
        $searchDate = $params['date'] ?? date('Y-m-d');
        $startTime = strtotime(date('Y-m-d 00:00:00',strtotime($searchDate)));
        $endTime = strtotime(date('Y-m-d 23:59:59',strtotime($searchDate)));
        $nowTime = time();
        $physicalStoreId = $this->storeId;

        $courseOfflinePlanList = CourseOfflinePlan::query()
            ->leftJoin('course_offline','course_offline_plan.course_offline_id','=','course_offline.id')
            ->select(['course_offline_plan.id as course_offline_plan_id','course_offline_plan.teacher_name','course_offline_plan.class_start_time','course_offline_plan.class_end_time','course_offline.suit_age_min','course_offline.name as course_name'])
            ->where([['course_offline_plan.physical_store_id','=',$physicalStoreId]])
            ->whereBetween('class_start_time',[$startTime,$endTime])
            ->orderBy('class_start_time')
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();

        foreach($courseOfflinePlanList as $key=>$value){
            $courseStatus = 0;
            $courseOfflineOrderCount1 = CourseOfflineOrder::query()->where(['course_offline_plan_id'=>$value['course_offline_plan_id']])->distinct()->count('member_id');
            $courseOfflineOrderCount2 = 0;
            if($nowTime>=$value['class_start_time']){
                $courseStatus = 1;
                $courseOfflineOrderCount2 = CourseOfflineOrder::query()->where(['course_offline_plan_id'=>$value['course_offline_plan_id'],'class_status'=>1,'order_status'=>0])->count();
            }

            $courseOfflinePlanList[$key]['status'] = $courseStatus;
            $courseOfflinePlanList[$key]['sign_up_num'] = $courseOfflineOrderCount1;
            $courseOfflinePlanList[$key]['course_class_attendance_num'] = $courseOfflineOrderCount2;
            $courseOfflinePlanList[$key]['class_start_time'] = date('H:i',$value['class_start_time']);
            $courseOfflinePlanList[$key]['class_end_time'] = date('H:i',$value['class_end_time']);
        }
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$courseOfflinePlanList];
    }

    /**
     * 门店经营分析
     * @param array $params
     * @return array
     */
    public function storeBusinessAnalysis(array $params): array
    {
        $nowDate = date('Y-m-d H:i:s');
        $month = $params['month'] ?? date('Y-m');
        $monthStartAt = date('Y-m-01 00:00:00',strtotime($month));
        $monthEndAt = date('Y-m-t 23:59:59',strtotime($month));
        $monthEndAt = min($monthEndAt, $nowDate);
        $monthStartTime = strtotime($monthStartAt);
        $monthEndTime = strtotime($monthEndAt);
        $physicalStoreId = $this->storeId;

        $physicalStoreInfo = PhysicalStore::query()
            ->select(['course_target_amount','revenue_target_amount'])
            ->where(['id'=>$physicalStoreId])
            ->first();
        $physicalStoreInfo = $physicalStoreInfo?->toArray();
        //经营目标
        $revenueTargetAmount = $physicalStoreInfo['revenue_target_amount'] ?? '0';
        //会员卡首购金额
        $vipCardOrderAmount1 = VipCardOrder::query()
            ->where(['recommend_physical_store_id'=>$physicalStoreId,'pay_status'=>1,'order_status'=>0,'order_counter'=>1])
            ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
            ->whereIn('order_type',[1,2,4])
            ->sum('price');
        //会员卡复购金额
        $vipCardOrderAmount2 = VipCardOrder::query()
            ->select(['price'])
            ->where([['recommend_physical_store_id','=',$physicalStoreId],['pay_status','=',1],['order_status','=',0],['order_counter','>',1]])
            ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
            ->whereIn('order_type',[1,2,4])
            ->sum('price');
        $vipCardOrderAmount = bcadd((string)$vipCardOrderAmount1,(string)$vipCardOrderAmount2,2);
        $vipCardOrderAmountRate1 = $vipCardOrderAmount == 0 ? '0' : bcdiv((string)$vipCardOrderAmount1,$vipCardOrderAmount,4);
        $vipCardOrderAmountRate1 = bcmul($vipCardOrderAmountRate1,'100',2);
        $vipCardOrderAmountRate2 = '0';
        if($vipCardOrderAmount>0){
            $vipCardOrderAmountRate2 = bcsub('100',$vipCardOrderAmountRate1,2);
        }
        //商品订单
        $orderGoodsList = OrderGoods::query()
            ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
            ->selectRaw('order_goods.member_id,order_goods.quantity*order_goods.pay_price as amount')
            ->where(['order_info.recommend_physical_store_id'=>$physicalStoreId,'order_goods.pay_status'=>1,'order_goods.order_status'=>0])
            ->whereBetween('order_goods.created_at',[$monthStartAt,$monthEndAt])
            ->get();
        $orderGoodsList = $orderGoodsList->toArray();
        $orderGoodsList = $this->functions->arrayGroupBy($orderGoodsList,'member_id');
        $orderAmount1 = '0';
        $orderAmount2 = '0';
        foreach($orderGoodsList as $value){
            $firstOrder = $value[0];
            unset($value[0]);
            $orderAmount1 = bcadd($orderAmount1,(string)$firstOrder['amount'],2);
            if(!empty($value)){
                $amountSum = (string)array_sum(array_column($value,'amount'));
                $orderAmount2 = bcadd($orderAmount2,$amountSum,2);
            }
        }
        $orderAmount = bcadd($orderAmount1,$orderAmount2,2);
        $orderAmountRate1 = $orderAmount == 0 ? '0' : bcdiv($orderAmount1,$orderAmount,4);
        $orderAmountRate1 = bcmul($orderAmountRate1,'100',2);
        $orderAmountRate2 = '0';
        if($orderAmount>0){
            $orderAmountRate2 = bcsub('100',$orderAmountRate1,2);
        }

        $revenueAmount = bcadd($vipCardOrderAmount,$orderAmount,2);
        $revenueCompletionRate = $revenueTargetAmount == 0 ? '0' : bcdiv($revenueAmount,$revenueTargetAmount,4);
        $revenueCompletionRate = (string)min($revenueCompletionRate, 1);
        $revenueCompletionRate = bcmul($revenueCompletionRate,'100',2);

        //课程
        $courseTargetAmount = $physicalStoreInfo['course_target_amount'] ?? '0';
        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['course_unit_price','theme_type'])
            ->where(['physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1])
            ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $courseOfflineAmount = (string)array_sum(array_column($courseOfflineOrderList,'course_unit_price'));
        $courseOfflineRevenueCompletionRate = $courseTargetAmount == 0 ? '0' : bcdiv($courseOfflineAmount,$courseTargetAmount,4);
        $courseOfflineRevenueCompletionRate = (string)min($courseOfflineRevenueCompletionRate, 1);
        $courseOfflineRevenueCompletionRate = bcmul($courseOfflineRevenueCompletionRate,'100',2);

        $courseOfflineOrderList = $this->functions->arrayGroupBy($courseOfflineOrderList,'theme_type');
        $courseOfflineOrderAmount1 = isset($courseOfflineOrderList[1]) ? (string)array_sum(array_column($courseOfflineOrderList[1],'course_unit_price')) : '0';
        $courseOfflineOrderAmount2 = isset($courseOfflineOrderList[2]) ? (string)array_sum(array_column($courseOfflineOrderList[2],'course_unit_price')) : '0';
        $courseOfflineOrderAmount3 = isset($courseOfflineOrderList[3]) ? (string)array_sum(array_column($courseOfflineOrderList[3],'course_unit_price')) : '0';

        $courseOfflinePlanList = CourseOfflinePlan::query()
            ->select(['theme_type'])
            ->where([['physical_store_id','=',$physicalStoreId],['is_deleted','=',0]])
            ->whereBetween('class_end_time',[$monthStartTime,$monthEndTime])
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();
        $courseOfflinePlanList = $this->functions->arrayGroupBy($courseOfflinePlanList,'theme_type');
        $courseOfflinePlan1 = isset($courseOfflinePlanList[1]) ? count($courseOfflinePlanList[1]) : 0;
        $courseOfflinePlan2 = isset($courseOfflinePlanList[2]) ? count($courseOfflinePlanList[2]) : 0;
        $courseOfflinePlan3 = isset($courseOfflinePlanList[3]) ? count($courseOfflinePlanList[3]) : 0;

        $courseOfflineOrderBePresentList = CourseOfflineOrder::query()
            ->select(['theme_type'])
            ->where(['physical_store_id'=>$physicalStoreId,'order_status'=>0,'pay_status'=>1,'class_status'=>1])
            ->whereBetween('end_at',[$monthStartAt,$monthEndAt])
            ->get();
        $courseOfflineOrderBePresentList = $courseOfflineOrderBePresentList->toArray();
        $courseOfflineOrderBePresentList = $this->functions->arrayGroupBy($courseOfflineOrderBePresentList,'theme_type');
        $courseOfflineOrderBePresent1 = isset($courseOfflineOrderBePresentList[1]) ? count($courseOfflineOrderBePresentList[1]) : 0;
        $courseOfflineOrderBePresent2 = isset($courseOfflineOrderBePresentList[2]) ? count($courseOfflineOrderBePresentList[1]) : 0;
        $courseOfflineOrderBePresent3 = isset($courseOfflineOrderBePresentList[3]) ? count($courseOfflineOrderBePresentList[1]) : 0;
        $courseOfflineStudentNum1 = $courseOfflinePlan1 == 0 ? '0' : bcdiv((string)$courseOfflineOrderBePresent1,(string)$courseOfflinePlan1,2);
        $courseOfflineStudentNum2 = $courseOfflinePlan2 == 0 ? '0' : bcdiv((string)$courseOfflineOrderBePresent2,(string)$courseOfflinePlan2,2);
        $courseOfflineStudentNum3 = $courseOfflinePlan3 == 0 ? '0' : bcdiv((string)$courseOfflineOrderBePresent3,(string)$courseOfflinePlan3,2);

        $returnData = [
            'revenue_target_amount' => (int)$revenueTargetAmount,
            'revenue_amount' => $revenueAmount,
            'revenue_completion_rate' => $revenueCompletionRate,
            'vip_card_order_amount1' => $vipCardOrderAmount1,
            'vip_card_order_amount_rate1' => $vipCardOrderAmountRate1,
            'vip_card_order_amount2' => $vipCardOrderAmount2,
            'vip_card_order_amount_rate2' => $vipCardOrderAmountRate2,
            'order_amount1' => $orderAmount1,
            'order_amount_rate1' => $orderAmountRate1,
            'order_amount2' => $orderAmount2,
            'order_amount_rate2' => $orderAmountRate2,
            'course_revenue_target_amount' => (int)$courseTargetAmount,
            'course_offline_amount' => $courseOfflineAmount,
            'course_offline_revenue_completion_rate' => $courseOfflineRevenueCompletionRate,
            'course_offline_order_amount1' => $courseOfflineOrderAmount1,
            'course_offline_plan1' => $courseOfflinePlan1,
            'course_offline_student_num1' => $courseOfflineStudentNum1,
            'course_offline_order_amount2' => $courseOfflineOrderAmount2,
            'course_offline_plan2' => $courseOfflinePlan2,
            'course_offline_student_num2' => $courseOfflineStudentNum2,
            'course_offline_order_amount3' => $courseOfflineOrderAmount3,
            'course_offline_plan3' => $courseOfflinePlan3,
            'course_offline_student_num3' => $courseOfflineStudentNum3
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
    }

    /**
     * 门店老师管理
     * @param array $params
     * @return array
     */
    public function storeTeacherManage(array $params): array
    {
        $nowDate = date('Y-m-d H:i:s');
        $month = $params['month'] ?? date('Y-m');
        $monthToTime = strtotime($month);
        $monthStartAt = date('Y-m-01 00:00:00',$monthToTime);
        $monthEndAt = date('Y-m-t 23:59:59',$monthToTime);
        $monthEndAt = min($nowDate,$monthEndAt);
        $month0 = date('Ym',$monthToTime);
        $physicalStoreId = $this->storeId;

        $teacherList = Teacher::query()
            ->select(['id','name'])
            ->where(['physical_store_id'=>$physicalStoreId])
            ->get();
        $teacherList = $teacherList->toArray();

        foreach($teacherList as $key=>$value){
            $teacherId = $value['id'];

            $teacherRevenueTargetInfo = TeacherRevenueTarget::query()
                ->select(['revenue_target_amount'])
                ->where(['teacher_id'=>$teacherId,'month'=>$month0])
                ->first();
            $teacherRevenueTargetInfo = $teacherRevenueTargetInfo?->toArray();
            $revenueTargetAmount = $teacherRevenueTargetInfo['revenue_target_amount'] ?? '0';

            $vipCardOrderAmount = VipCardOrder::query()
                ->where(['recommend_teacher_id'=>$teacherId,'pay_status'=>1,'order_type'=>1,'order_status'=>0])
                ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
                ->sum('price');

            $orderGoodsAmount = OrderGoods::query()
                ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
                ->where(['order_info.recommend_teacher_id'=>$teacherId,'order_goods.pay_status'=>1,'order_goods.order_status'=>0])
                ->whereBetween('order_goods.pay_at',[$monthStartAt,$monthEndAt])
                ->sum(DB::connection('jkc_edu')->raw('pay_price*quantity'));

            $courseOfflineOrderAmount = CourseOfflineOrder::query()
                ->where(['teacher_id'=>$teacherId,'order_status'=>0,'pay_status'=>1])
                ->whereBetween('start_at',[$monthStartAt,$monthEndAt])
                ->sum('course_unit_price');

            $vipCardOrderRefundAmount = VipCardOrder::query()
                ->where(['recommend_teacher_id'=>$teacherId,'pay_status'=>1,'order_type'=>1,'order_status'=>3])
                ->whereBetween('created_at',[$monthStartAt,$monthEndAt])
                ->sum('price');

            $orderGoodsRefundAmount = OrderGoods::query()
                ->leftJoin('order_info','order_goods.order_info_id','=','order_info.id')
                ->where(['order_info.recommend_teacher_id'=>$teacherId,'order_goods.pay_status'=>1,'order_goods.order_status'=>3])
                ->whereBetween('order_goods.created_at',[$monthStartAt,$monthEndAt])
                ->sum(DB::connection('jkc_edu')->raw('pay_price*quantity'));

            $teacherList[$key]['revenue_target_amount'] = $revenueTargetAmount;
            $teacherList[$key]['avatar'] = '';
            $teacherList[$key]['revenue_amount'] = bcadd((string)$vipCardOrderAmount,(string)$orderGoodsAmount,2);
            $teacherList[$key]['vip_card_order_amount'] = (string)$vipCardOrderAmount;
            $teacherList[$key]['order_amount'] = (string)$orderGoodsAmount;
            $teacherList[$key]['course_offline_order_amount'] = (string)$courseOfflineOrderAmount;
            $teacherList[$key]['refund_amount'] = bcadd((string)$vipCardOrderRefundAmount,(string)$orderGoodsRefundAmount,2);
        }

        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$teacherList];
    }

    /**
     * 门店会员管理
     * @return array
     */
    public function storeMemberManage(): array
    {
        $lastMonthStartAt = date("Y-m-01 00:00:00", strtotime(date('Y-m-01 00:00:00')." -1 month"));
        $lastMonthEndAt = date("Y-m-t 23:59:59", strtotime(date('Y-m-01 00:00:00')." -1 month"));
        $lastMonth = date("Ym", strtotime(date('Y-m-01 00:00:00')." -1 month"));
        $nowDate = date('Y-m-d H:i:s');
        $physicalStoreId = $this->storeId;

        $memberBelongToList = MemberBelongTo::query()
            ->leftJoin('vip_card_order','member_belong_to.member_id','=','vip_card_order.member_id')
            ->select(['member_belong_to.member_id'])
            ->where(['member_belong_to.physical_store_id'=>$physicalStoreId,'vip_card_order.pay_status'=>1])
            ->whereIn('vip_card_order.order_type',[1,2,4])
            ->get();
        $memberBelongToList = $memberBelongToList->toArray();
        $memberIdArray = array_values(array_unique(array_column($memberBelongToList,'member_id')));

        $vipCardOrderList = VipCardOrder::query()
            ->selectRaw('member_id,sum(course1) as course1_sum,sum(course2) as course2_sum,sum(course3) as course3_sum,sum(currency_course) as currency_course_sum,sum(course1_used) as course1_used_sum,sum(course2_used) as course2_used_sum,sum(course3_used) as course3_used_sum,sum(currency_course_used) as currency_course_used_sum')
            ->where([['pay_status','=',1],['order_status','=',0],['expire_at','>',$nowDate]])
            ->whereIn('member_id',$memberIdArray)
            ->groupBy('member_id')
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $combineVipCardOrderKey = array_column($vipCardOrderList,'member_id');
        $vipCardOrderList = array_combine($combineVipCardOrderKey,$vipCardOrderList);

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['member_id','theme_type'])
            ->where(['order_status'=>0,'class_status'=>1])
            ->whereIn('member_id',$memberIdArray)
            ->orderBy('start_at')
            ->whereBetween('start_at',[$lastMonthStartAt,$lastMonthEndAt])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $courseOfflineOrderMemberIdArray = array_column($courseOfflineOrderList,'member_id');
        $courseOfflineOrderCount = count($courseOfflineOrderList);
        $courseOfflineOrderList = $this->functions->arrayGroupBy($courseOfflineOrderList,'member_id');

        $vipCardOrderMonthlyStatisticsCount = VipCardOrderMonthlyStatistics::query()
            ->where(['month'=>$lastMonth])
            ->whereIn('member_id',$courseOfflineOrderMemberIdArray)
            ->count();

        $memberCount = count($memberIdArray);
        $memberThemeTypeCount1 = 0;
        $memberThemeTypeCount2 = 0;
        $memberThemeTypeCount3 = 0;
        $memberStatusTypeCount1 = 0;
        $memberStatusTypeCount2 = 0;
        $memberStatusTypeCount3 = 0;
        $memberStatusTypeCount4 = 0;
        foreach($memberIdArray as $memberId){
            $courseOfflineOrderData = $courseOfflineOrderList[$memberId] ?? [];
            $vipCardOrderData = $vipCardOrderList[$memberId] ?? null;
            $vipCardSurplusCount = 0;
            if($vipCardOrderData !== null){
                $vipCardSurplusCount = $vipCardOrderData['course1_sum']+$vipCardOrderData['course2_sum']+$vipCardOrderData['course3_sum']+$vipCardOrderData['currency_course_sum']-$vipCardOrderData['course1_used_sum']-$vipCardOrderData['course2_used_sum']-$vipCardOrderData['course3_used_sum']-$vipCardOrderData['currency_course_used_sum'];
            }
            $memberCourseOfflineOrderCount = count($courseOfflineOrderData);
            if($vipCardSurplusCount>0 && $memberCourseOfflineOrderCount>=2){
                $memberStatusTypeCount1++;
            }else if($vipCardSurplusCount>0 && $memberCourseOfflineOrderCount===1){
                $memberStatusTypeCount2++;
            }else if($vipCardSurplusCount>0 && $memberCourseOfflineOrderCount===0){
                $memberStatusTypeCount3++;
            }else{
                $memberStatusTypeCount4++;
            }
            if($memberCourseOfflineOrderCount !== 0){
                $lastCourseOfflineOrder = $courseOfflineOrderData[$memberCourseOfflineOrderCount-1];
                if($lastCourseOfflineOrder['theme_type'] == 1){
                    $memberThemeTypeCount1++;
                }else if($lastCourseOfflineOrder['theme_type'] == 2){
                    $memberThemeTypeCount2++;
                }else if($lastCourseOfflineOrder['theme_type'] == 3){
                    $memberThemeTypeCount3++;
                }
            }
        }
        $memberThemeTypeCountRate1 = $memberCount === 0 ? '0' : (string)(bcdiv((string)$memberThemeTypeCount1,(string)$memberCount,4)*100);
        $memberThemeTypeCountRate2 = $memberCount === 0 ? '0' : (string)(bcdiv((string)$memberThemeTypeCount2,(string)$memberCount,4)*100);
        $memberThemeTypeCountRate3 = $memberCount === 0 ? '0' : (string)(bcdiv((string)$memberThemeTypeCount3,(string)$memberCount,4)*100);
        $memberThemeTypeCountRate4 = 0;
        if($memberCount !== 0 && $courseOfflineOrderCount !== 0){
            $memberThemeTypeCountRate4 = bcsub('100',$memberThemeTypeCountRate1,2);
            $memberThemeTypeCountRate4 = bcsub($memberThemeTypeCountRate4,$memberThemeTypeCountRate2,2);
            $memberThemeTypeCountRate4 = bcsub($memberThemeTypeCountRate4,$memberThemeTypeCountRate3,2);
        }
        $perCapitaClassFrequency = $vipCardOrderMonthlyStatisticsCount === 0 ? 0 : bcdiv((string)$courseOfflineOrderCount,(string)$vipCardOrderMonthlyStatisticsCount,1);

        $returnData = [
            'member_count' => $memberCount,
            'member_theme_type_rate1' => $memberThemeTypeCountRate1,
            'member_theme_type_rate2' => $memberThemeTypeCountRate2,
            'member_theme_type_rate3' => $memberThemeTypeCountRate3,
            'member_theme_type_rate4' => $memberThemeTypeCountRate4,
            'member_status_type_count1' => $memberStatusTypeCount1,
            'member_status_type_count2' => $memberStatusTypeCount2,
            'member_status_type_count3' => $memberStatusTypeCount3,
            'member_status_type_count4' => $memberStatusTypeCount4,
            'per_capita_class_frequency' => $perCapitaClassFrequency
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
    }

    /**
     * 门店会员列表
     * @param array $params
     * @return array
     */
    public function storeMemberList(array $params): array
    {
        $searchKeywords = (string)$params['keywords'];
        $searchMemberStatusType = $params['member_status_type'];
        $searchTeacherId = $params['teacher_id'];
        $physicalStoreId = $this->storeId;
        $lastMonthStartAt = date("Y-m-01 00:00:00", strtotime(date('Y-m-01 00:00:00')." -1 month"));
        $lastMonthEndAt = date("Y-m-t 23:59:59", strtotime(date('Y-m-01 00:00:00')." -1 month"));
        $nowDate = date('Y-m-d H:i:s');

        $where = ['member_belong_to.physical_store_id'=>$physicalStoreId,'vip_card_order.pay_status'=>1];
        $memberBelongToModel = MemberBelongTo::query()
            ->leftJoin('member','member_belong_to.member_id','=','member.id')
            ->leftJoin('vip_card_order','member_belong_to.member_id','=','vip_card_order.member_id')
            ->select(['member_belong_to.member_id'])
            ->whereIn('vip_card_order.order_type',[1,2,4]);
        if(!empty($searchKeywords)){
            if(is_numeric($searchKeywords) && strlen($searchKeywords)===11){
                $where['member.mobile'] = $searchKeywords;
            }else{
                $where['member.name'] = $searchKeywords;
            }
        }
        $memberBelongToModel->where($where);
        $memberBelongToList = $memberBelongToModel->get();
        $memberBelongToList = $memberBelongToList->toArray();
        if(empty($memberBelongToList)){
            return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>[]];
        }
        $memberIdArray = array_values(array_unique(array_column($memberBelongToList,'member_id')));

        $vipCardOrderList = VipCardOrder::query()
            ->selectRaw('member_id,sum(course1) as course1_sum,sum(course2) as course2_sum,sum(course3) as course3_sum,sum(currency_course) as currency_course_sum,sum(course1_used) as course1_used_sum,sum(course2_used) as course2_used_sum,sum(course3_used) as course3_used_sum,sum(currency_course_used) as currency_course_used_sum')
            ->where([['pay_status','=',1],['order_status','=',0],['expire_at','>',$nowDate]])
            ->whereIn('member_id',$memberIdArray)
            ->groupBy('member_id')
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $combineVipCardOrderKey = array_column($vipCardOrderList,'member_id');
        $vipCardOrderList = array_combine($combineVipCardOrderKey,$vipCardOrderList);

        $memberList = Member::query()
            ->select(['id','mobile','name','avatar'])
            ->whereIn('id',$memberIdArray)
            ->get();
        $memberList = $memberList->toArray();

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['member_id','theme_type'])
            ->where(['order_status'=>0,'class_status'=>1])
            ->whereIn('member_id',$memberIdArray)
            ->orderBy('start_at')
            ->whereBetween('start_at',[$lastMonthStartAt,$lastMonthEndAt])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $courseOfflineOrderList = $this->functions->arrayGroupBy($courseOfflineOrderList,'member_id');

        foreach($memberList as $key=>$value){
            $memberId = $value['id'];
            $courseOfflineOrderData = $courseOfflineOrderList[$memberId] ?? [];
            $vipCardOrderData = $vipCardOrderList[$memberId] ?? null;
            $vipCardSurplusCount = 0;
            $memberStatusType = 4;
            if($vipCardOrderData !== null){
                $vipCardSurplusCount = $vipCardOrderData['course1_sum']+$vipCardOrderData['course2_sum']+$vipCardOrderData['course3_sum']+$vipCardOrderData['currency_course_sum']-$vipCardOrderData['course1_used_sum']-$vipCardOrderData['course2_used_sum']-$vipCardOrderData['course3_used_sum']-$vipCardOrderData['currency_course_used_sum'];
            }
            $memberCourseOfflineOrderCount = count($courseOfflineOrderData);
            if($vipCardSurplusCount>0 && $memberCourseOfflineOrderCount>=2){
                $memberStatusType = 1;
            }else if($vipCardSurplusCount>0 && $memberCourseOfflineOrderCount===1){
                $memberStatusType = 2;
            }else if($vipCardSurplusCount>0 && $memberCourseOfflineOrderCount===0){
                $memberStatusType = 3;
            }

            $recentlyEvent = '无';
            $recentlyEventTeacher = '无';
            $recentlyEventTeacherId = 0;
            $courseOfflineOrderInfo = CourseOfflineOrder::query()
                ->select(['start_at','teacher_name','teacher_id'])
                ->where(['member_id'=>$memberId,'pay_status'=>1,'order_status'=>0])
                ->orderBy('created_at','desc')
                ->first();
            if(!empty($courseOfflineOrderInfo)){
                $courseOfflineOrderInfo = $courseOfflineOrderInfo->toArray();
                $recentlyEventTeacher = $courseOfflineOrderInfo['teacher_name'];
                $recentlyEventTeacherId = $courseOfflineOrderInfo['teacher_id'];
                if($courseOfflineOrderInfo['start_at']>$nowDate){
                    $recentlyEvent = date('m-d',strtotime($courseOfflineOrderInfo['start_at'])).'约课';
                }else{
                    $recentlyEvent = date('m-d',strtotime($courseOfflineOrderInfo['start_at'])).'上课';
                }
            }
            $memberCourseOfflineOrderCount1 = CourseOfflineOrder::query()
                ->where([['member_id','=',$memberId],['pay_status','=',1],['order_status','=',0],['start_at','>',$nowDate]])
                ->count();
            $memberCourseOfflineOrderCount2 = CourseOfflineOrder::query()
                ->where([['member_id','=',$memberId],['pay_status','=',1],['order_status','=',0],['start_at','<',$nowDate],['class_status','=',1]])
                ->count();

            $memberList[$key]['recently_event_teacher_id'] = $recentlyEventTeacherId;
            $memberList[$key]['recently_event_teacher'] = $recentlyEventTeacher;
            $memberList[$key]['recently_event'] = $recentlyEvent;
            $memberList[$key]['member_status_type'] = $memberStatusType;
            $memberList[$key]['member_course_offline1'] = $memberCourseOfflineOrderCount1;
            $memberList[$key]['member_course_offline2'] = $memberCourseOfflineOrderCount2;
            $memberList[$key]['vip_card_surplus'] = $vipCardSurplusCount;
        }

        if(!empty($searchTeacherId)){
            $memberList = array_filter($memberList,function ($value)use($searchTeacherId){
                return $value['recently_event_teacher_id'] == $searchTeacherId;
            });
        }
        if(!empty($searchMemberStatusType)){
            $memberList = array_filter($memberList,function ($value)use($searchMemberStatusType){
                return $value['member_status_type'] == $searchMemberStatusType;
            });
        }
        $memberList = !empty($memberList) ? array_values($memberList) : [];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$memberList];
    }

    /**
     * 门店会员详情
     * @param int $id
     * @return array
     */
    public function storeMemberDetail(int $id): array
    {
        $lastMonthStartAt = date("Y-m-01 00:00:00", strtotime(date('Y-m-01 00:00:00')." -1 month"));
        $lastMonthEndAt = date("Y-m-t 23:59:59", strtotime(date('Y-m-01 00:00:00')." -1 month"));
        $nowDate = date('Y-m-d H:i:s');

        $memberInfo = Member::query()
            ->select(['mobile','name','avatar'])
            ->where(['id'=>$id])
            ->first();
        $memberInfo = $memberInfo->toArray();

        $vipCardOrderList = VipCardOrder::query()
            ->select(['id','order_title','course1','course2','course3','currency_course','course1_used','course2_used','course3_used','currency_course_used','expire_at','extension_days'])
            ->where([['member_id','=',$id],['pay_status','=',1],['order_status','=',0],['expire_at','>',$nowDate]])
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['member_id','theme_type'])
            ->where(['member_id'=>$id,'order_status'=>0,'class_status'=>1])
            ->orderBy('start_at')
            ->whereBetween('start_at',[$lastMonthStartAt,$lastMonthEndAt])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $courseOfflineOrderCount = count($courseOfflineOrderList);

        $vipCardSurplusCount = 0;
        $memberStatusType = 4;
        if(!empty($vipCardOrderList)){
            $course1Total = array_sum(array_column($vipCardOrderList,'course1'));
            $course2Total = array_sum(array_column($vipCardOrderList,'course2'));
            $course3Total = array_sum(array_column($vipCardOrderList,'course3'));
            $currencyCourseTotal = array_sum(array_column($vipCardOrderList,'currency_course'));
            $course1UsedTotal = array_sum(array_column($vipCardOrderList,'course1_used'));
            $course2UsedTotal = array_sum(array_column($vipCardOrderList,'course2_used'));
            $course3UsedTotal = array_sum(array_column($vipCardOrderList,'course3_used'));
            $currencyCourseUsedTotal = array_sum(array_column($vipCardOrderList,'currency_course_used'));
            $vipCardSurplusCount = $course1Total+$course2Total+$course3Total+$currencyCourseTotal-$course1UsedTotal-$course2UsedTotal-$course3UsedTotal-$currencyCourseUsedTotal;
        }

        if($vipCardSurplusCount>0 && $courseOfflineOrderCount>=2){
            $memberStatusType = 1;
        }else if($vipCardSurplusCount>0 && $courseOfflineOrderCount===1){
            $memberStatusType = 2;
        }else if($vipCardSurplusCount>0 && $courseOfflineOrderCount===0){
            $memberStatusType = 3;
        }

        $recentlyEvent = '无';
        $recentlyEventTeacher = '无';
        $courseOfflineOrderInfo = CourseOfflineOrder::query()
            ->select(['start_at','teacher_name'])
            ->where(['member_id'=>$id,'pay_status'=>1,'order_status'=>0])
            ->orderBy('created_at','desc')
            ->first();
        if(!empty($courseOfflineOrderInfo)){
            $courseOfflineOrderInfo = $courseOfflineOrderInfo->toArray();
            $recentlyEventTeacher = $courseOfflineOrderInfo['teacher_name'];
            if($courseOfflineOrderInfo['start_at']>$nowDate){
                $recentlyEvent = date('m-d',strtotime($courseOfflineOrderInfo['start_at'])).'约课';
            }else{
                $recentlyEvent = date('m-d',strtotime($courseOfflineOrderInfo['start_at'])).'上课';
            }
        }
        $memberCourseOfflineOrderCount1 = CourseOfflineOrder::query()
            ->where([['member_id','=',$id],['pay_status','=',1],['order_status','=',0],['start_at','>',$nowDate]])
            ->count();
        $memberCourseOfflineOrderCount2 = CourseOfflineOrder::query()
            ->where([['member_id','=',$id],['pay_status','=',1],['order_status','=',0],['start_at','<',$nowDate],['class_status','=',1]])
            ->count();

        foreach($vipCardOrderList as $key=>$value){
            $totalCourse = $value['course1']+$value['course2']+$value['course3']+$value['currency_course'];
            $totalUsedCourse = $value['course1_used']+$value['course2_used']+$value['course3_used']+$value['currency_course_used'];
            $totalSurplusCourse = $totalCourse-$totalUsedCourse;
            $isActivation = 1;
            if($value['expire_at'] === VipCardConstant::DEFAULT_EXPIRE_AT || $value['extension_days'] != 0){
                $isActivation = 0;
            }
            unset($vipCardOrderList[$key]['course1']);
            unset($vipCardOrderList[$key]['course2']);
            unset($vipCardOrderList[$key]['course3']);
            unset($vipCardOrderList[$key]['currency_course']);
            unset($vipCardOrderList[$key]['course1_used']);
            unset($vipCardOrderList[$key]['course2_used']);
            unset($vipCardOrderList[$key]['course3_used']);
            unset($vipCardOrderList[$key]['currency_course_used']);

            $vipCardOrderList[$key]['is_activation'] = $isActivation;
            $vipCardOrderList[$key]['total_course'] = $totalCourse;
            $vipCardOrderList[$key]['surplus_course'] = $totalSurplusCourse;
        }

        $memberInfo['recently_event_teacher'] = $recentlyEventTeacher;
        $memberInfo['recently_event'] = $recentlyEvent;
        $memberInfo['member_status_type'] = $memberStatusType;
        $memberInfo['member_course_offline1'] = $memberCourseOfflineOrderCount1;
        $memberInfo['member_course_offline2'] = $memberCourseOfflineOrderCount2;
        $memberInfo['vip_card_surplus'] = $vipCardSurplusCount;
        $memberInfo['vip_card_order_list'] = $vipCardOrderList;

        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$memberInfo];
    }

    /**
     * 门店会员课程订单列表
     * @param array $params
     * @return array
     */
    public function storeMemberCourseOfflineOrderList(array $params): array
    {
        $memberId = $params['id'];
        $searchStatus = $params['status'];
        $nowDate = date('Y-m-d H:i:s');

        if($searchStatus == 0){
            $courseOfflineOrderList = CourseOfflineOrder::query()
                ->leftJoin('course_offline_plan','course_offline_order.course_offline_plan_id','=','course_offline_plan.id')
                ->select(['course_offline_order.course_name','course_offline_order.teacher_name','course_offline_order.start_at','course_offline_order.end_at','course_offline_order.course_offline_plan_id','course_offline_plan.sign_up_num'])
                ->where([['course_offline_order.order_status','=',0],['course_offline_order.member_id','=',$memberId],['course_offline_order.start_at','>',$nowDate]])
                ->get();
        }else{
            $courseOfflineOrderList = CourseOfflineOrder::query()
                ->leftJoin('course_offline_plan','course_offline_order.course_offline_plan_id','=','course_offline_plan.id')
                ->select(['course_offline_order.course_name','course_offline_order.teacher_name','course_offline_order.start_at','course_offline_order.end_at','course_offline_order.course_offline_plan_id'])
                ->where([['course_offline_order.order_status','=',0],['course_offline_order.member_id','=',$memberId],['course_offline_order.class_status','=',1],['course_offline_order.start_at','<',$nowDate]])
                ->orderBy('course_offline_order.start_at','desc')
                ->get();
        }
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();

        foreach($courseOfflineOrderList as $key=>$value){
            $courseOfflinePlanId = $value['course_offline_plan_id'];
            $classAttendanceCount = 0;
            $signUpNumCount = CourseOfflineOrder::query()->where(['course_offline_plan_id'=>$courseOfflinePlanId])->distinct()->count('member_id');
            if($searchStatus == 1){
                $classAttendanceCount = CourseOfflineOrder::query()->where(['course_offline_plan_id'=>$courseOfflinePlanId,'order_status'=>0,'class_status'=>1])->count();
            }

            $courseOfflineOrderList[$key]['sign_up_num'] = $signUpNumCount;
            $courseOfflineOrderList[$key]['end_at'] = date('H:i',strtotime($value['end_at']));
            $courseOfflineOrderList[$key]['start_at'] = date('Y.m.d H:i',strtotime($value['start_at']));
            $courseOfflineOrderList[$key]['course_class_attendance_num'] = $classAttendanceCount;
        }
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$courseOfflineOrderList];
    }

    /**
     * 设置老师营收目标
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function setTeacherRevenueTargetAmount(array $params): array
    {
        $id = $params['id'];
        $amount = $params['amount'];
        $month = date('Ym');

        $teacherRevenueTargetInfo = TeacherRevenueTarget::query()
            ->select(['id'])
            ->where(['teacher_id'=>$id,'month'=>$month])
            ->first();
        if($teacherRevenueTargetInfo === null){
            $insertTeacherRevenueTargetData['id'] = IdGenerator::generate();
            $insertTeacherRevenueTargetData['teacher_id'] = $id;
            $insertTeacherRevenueTargetData['month'] = $month;
            $insertTeacherRevenueTargetData['revenue_target_amount'] = $amount;

            TeacherRevenueTarget::query()->insert($insertTeacherRevenueTargetData);
        }else{
            $teacherRevenueTargetInfo = $teacherRevenueTargetInfo->toArray();
            TeacherRevenueTarget::query()->where(['id'=>$teacherRevenueTargetInfo['id']])->update(['revenue_target_amount'=>$amount]);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 会员卡订单延期
     * @param array $params
     * @return array
     */
    public function vipCardOrderExtension(array $params): array
    {
        $id = $params['id'];
        $days = $params['days'];

        $vipCardOrderInfo = VipCardOrder::query()
            ->select(['expire_at'])
            ->where(['id'=>$id])
            ->first();
        $vipCardOrderInfo = $vipCardOrderInfo->toArray();
        $expireAt = $vipCardOrderInfo['expire_at'];
        if($expireAt === VipCardConstant::DEFAULT_EXPIRE_AT){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }
        $newExpireAt = date('Y-m-d H:i:s',strtotime("$expireAt +$days day"));

        VipCardOrder::query()->where(['id'=>$id,'extension_days'=>0])->update(['expire_at'=>$newExpireAt,'extension_days'=>$days]);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }
}