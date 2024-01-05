<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\CourseDetailSetUp;
use App\Model\CourseOfflineAgeTag;
use App\Model\CourseOfflineCategory;
use App\Model\CourseOfflineOrder;
use App\Model\CourseOfflinePlan;
use App\Model\Member;
use App\Model\PhysicalStore;
use App\Model\CourseOffline;
use App\Model\CourseOnlineCollect;
use App\Model\CourseOnlineChildCollect;
use App\Model\CourseOnline;
use App\Model\CourseOnlineChild;
use App\Constants\ErrorCode;
use App\Snowflake\IdGenerator;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Context;
use Hyperf\Utils\Coroutine;

class CourseService extends BaseService
{

    /**
     * CourseService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 门店排课日历
     * @param array $params
     * @return array
     */
    public function physicalStoreCourseOfflinePlanCalendar(array $params): array
    {
        $physicalStoreId = $params['physical_store_id'];
        $themeType = $params['theme_type'] ?? 1;
        $startTime = strtotime(date("Y-m-d 00:00:00",strtotime("+1 day")));
        $todayDate = date('Y-m-d');

        $courseOfflinePlanList = CourseOfflinePlan::query()
            ->select(['class_date'])
            ->where([['physical_store_id','=',$physicalStoreId],['is_deleted','=',0],['class_start_time','>=',$startTime],['theme_type','=',$themeType]])
            ->orderBy('class_date')
            ->groupBy('class_date')
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();

        $classDateList = [];
        foreach($courseOfflinePlanList as $value){
            $classDate = date('Y-m-d',$value['class_date']);
            $classDateList[] = $classDate;
        }
        $classDateList = array_unique($classDateList);
        $classDateList = array_values($classDateList);
        array_unshift($classDateList,$todayDate);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $classDateList];
    }

    /**
     * 门店线下课程排课
     * @param array $params
     * @return array
     */
    public function physicalStoreCourseOfflinePlan(array $params): array
    {
        $physicalStoreId = $params['physical_store_id'];
        $searchDate = $params['date'];
        $suitAge = $params['suit_age'];
        $themeType = $params['type'] ?? 1;
        $nowTime = time();
        list($suitAgeMin,$suitAgeMax) = explode('|',$suitAge);

        $startTime = strtotime(date('Y-m-d 00:00:00',strtotime($searchDate)));
        $endTime = strtotime(date('Y-m-d 23:59:59',strtotime($searchDate)));
        $startTime = $nowTime<$startTime ? $startTime : $nowTime;
        $model = CourseOfflinePlan::query()
            ->leftJoin('course_offline', 'course_offline_plan.course_offline_id', '=', 'course_offline.id')
            ->select(['course_offline.id as course_offline_id','course_offline.name','course_offline.img_url','course_offline.type','course_offline.price','course_offline.suit_age_min','course_offline.suit_age_max','course_offline.phase','course_offline_plan.id','course_offline_plan.course_category_id','course_offline_plan.classroom_name','course_offline_plan.teacher_name','course_offline_plan.class_start_time','course_offline_plan.class_end_time','course_offline_plan.batch_no','course_offline_plan.classroom_capacity','course_offline_plan.sign_up_num'])
            ->where([['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline_plan.is_deleted','=',0],['course_offline.theme_type','=',$themeType]])
            ->whereBetween('course_offline_plan.class_start_time', [$startTime, $endTime]);
        if(!empty($suitAge)){
            $model->whereRaw("course_offline.suit_age_min BETWEEN $suitAgeMin AND $suitAgeMax");
        }
        $courseOfflinePlanList = $model->orderBy('course_offline_plan.class_start_time')->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();

        foreach($courseOfflinePlanList as $key=>$value){
            $classStartTime = $value['class_start_time'];
            $classEndTime = $value['class_end_time'];
            unset($courseOfflinePlanList[$key]['classroom_capacity']);
            unset($courseOfflinePlanList[$key]['sign_up_num']);
            unset($courseOfflinePlanList[$key]['class_start_time']);
            unset($courseOfflinePlanList[$key]['class_end_time']);
            $classStartDate = date('d',$classStartTime);
            $classStartTime = date('H:i',$classStartTime);
            $classEndTime = date('H:i',$classEndTime);
            $courseOfflinePlanList[$key]['class_time'] = "{$classStartDate}日 {$classStartTime}-{$classEndTime}";
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflinePlanList];
    }

    /**
     * 线下课程排期详情
     * @param int $courseOfflinePlanId
     * @return array
     */
    public function courseOfflinePlanDetail(int $courseOfflinePlanId): array
    {
        $memberId = $this->memberId;

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['course_offline_plan_id'])
            ->where(['member_id'=>$memberId,'pay_status'=>1,'order_status'=>0])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $courseOfflineOrderCourseOfflinePlanIdArray = [];
        if(!empty($courseOfflineOrderList)){
            $courseOfflineOrderCourseOfflinePlanIdArray = array_column($courseOfflineOrderList,'course_offline_plan_id');
        }
        $courseOfflinePlanInfo = CourseOfflinePlan::query()
            ->select(['id','course_offline_id','physical_store_id','batch_no','classroom_name','teacher_name','class_start_time','class_end_time','classroom_capacity','sign_up_num'])
            ->where(['id'=>$courseOfflinePlanId])
            ->first();
        if(empty($courseOfflinePlanInfo)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }
        $courseOfflinePlanInfo = $courseOfflinePlanInfo->toArray();
        $batchNo = $courseOfflinePlanInfo['batch_no'];
        $courseOfflineId = $courseOfflinePlanInfo['course_offline_id'];
        $physicalStoreId = $courseOfflinePlanInfo['physical_store_id'];

        $courseOfflineInfo = CourseOffline::query()
            ->select(['name','img_url','type','suit_age_min','suit_age_max','sign_up_num','phase','price'])
            ->where(['id'=>$courseOfflineId])
            ->first();
        if(empty($courseOfflineInfo)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }
        $courseOfflineInfo = $courseOfflineInfo->toArray();

        $physicalStoreInfo = PhysicalStore::query()
            ->select(['wechat_qr_code','store_phone','city_name','district_name','address'])
            ->where(['id'=>$physicalStoreId])
            ->first();
        if(empty($physicalStoreInfo)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }
        $physicalStoreInfo = $physicalStoreInfo->toArray();
        $classStartDate = date('Y-m-d H:i:s',$courseOfflinePlanInfo['class_start_time']);
        $classStartTime = date('Y-m-d H:i',$courseOfflinePlanInfo['class_start_time']);
        $classEndTime = date('H:i',$courseOfflinePlanInfo['class_end_time']);
        $classroomStatus = 0;
        if($courseOfflinePlanInfo['classroom_capacity'] <= $courseOfflinePlanInfo['sign_up_num']){
            $classroomStatus = 1;
        }
        $isReserved = 0;
        if(in_array($courseOfflinePlanId,$courseOfflineOrderCourseOfflinePlanIdArray)){
            $isReserved = 1;
        }

        $courseOfflineInfo['id'] = $courseOfflinePlanInfo['id'];
        $courseOfflineInfo['classroom_name'] = $courseOfflinePlanInfo['classroom_name'];
        $courseOfflineInfo['teacher_name'] = $courseOfflinePlanInfo['teacher_name'];
        $courseOfflineInfo['wechat_qr_code'] = $physicalStoreInfo['wechat_qr_code'];
        $courseOfflineInfo['store_phone'] = $physicalStoreInfo['store_phone'];
        $courseOfflineInfo['class_time'] = "{$classStartTime} 至 {$classEndTime}";
        $courseOfflineInfo['city_name'] = $physicalStoreInfo['city_name'];
        $courseOfflineInfo['district_name'] = $physicalStoreInfo['district_name'];
        $courseOfflineInfo['address'] = $physicalStoreInfo['address'];
        $courseOfflineInfo['batch_no'] = $batchNo;
        $courseOfflineInfo['is_reserved'] = $isReserved;
        $courseOfflineInfo['classroom_status'] = $classroomStatus;
        $courseOfflineInfo['class_start_time'] = $classStartDate;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineInfo];
    }

    /**
     * 线下课程批量约课详情
     * @param array $params
     * @return array
     */
    public function courseOfflineBatchReservationDetail(array $params): array
    {
        $courseOfflineId = $params['course_offline_id'];
        $physicalStoreId = $params['physical_store_id'];
        $memberId = $this->memberId;
        $nowTime = time();
        $weekArray = ["日","一","二","三","四","五","六"];

        $courseOfflineInfo = CourseOffline::query()
            ->select(['suit_age_min','type','theme_type'])
            ->where(['id'=>$courseOfflineId])
            ->first();
        $courseOfflineInfo = $courseOfflineInfo->toArray();
        $courseType = $courseOfflineInfo['type'];
        $suitAgeMin = $courseOfflineInfo['suit_age_min'];
        $themeType = $courseOfflineInfo['theme_type'];

        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['course_offline_plan_id','class_status'])
            ->where(['member_id'=>$memberId,'pay_status'=>1,'order_status'=>0])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        if(!empty($courseOfflineOrderList)){
            $combineCourseOfflineOrderKey = array_column($courseOfflineOrderList,'course_offline_plan_id');
            $courseOfflineOrderList = array_combine($combineCourseOfflineOrderKey,$courseOfflineOrderList);
        }

        $model = CourseOfflinePlan::query()
            ->leftJoin('course_offline', 'course_offline_plan.course_offline_id', '=', 'course_offline.id')
            ->select(['course_offline_plan.id','course_offline_plan.class_start_time','course_offline_plan.class_end_time','course_offline_plan.classroom_capacity','course_offline_plan.sign_up_num','course_offline_plan.batch_no','course_offline.type','course_offline.name','course_offline.theme_type']);
        /*if($courseType == 3){
            $model->where([['course_offline.suit_age_min','=',$suitAgeMin],['course_offline_plan.class_start_time','>',$nowTime],['course_offline.type','=',$courseType],['course_offline_plan.section_no','=',1],['course_offline_plan.is_deleted','=',0],['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline.theme_type','=',$themeType]]);
        }else{
            $model->where([['course_offline.suit_age_min','=',$suitAgeMin],['course_offline_plan.class_start_time','>',$nowTime],['course_offline.type','=',$courseType],['course_offline_plan.is_deleted','=',0],['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline.theme_type','=',$themeType]]);
        }*/
        $model->where([['course_offline.suit_age_min','=',$suitAgeMin],['course_offline_plan.class_start_time','>',$nowTime],['course_offline.type','=',$courseType],['course_offline_plan.is_deleted','=',0],['course_offline_plan.physical_store_id','=',$physicalStoreId],['course_offline.theme_type','=',$themeType]]);
        $courseOfflinePlanList = $model->orderBy('course_offline_plan.class_start_time')->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();

        foreach($courseOfflinePlanList as $key=>$value){
            $classStatus = 0;
            $courseOfflinePlanId = $value['id'];
            $classStartTime = $value['class_start_time'];
            $classEndTime = $value['class_end_time'];
            $classroomCapacity = $value['classroom_capacity'];
            $signUpNum = $value['sign_up_num'];
            unset($courseOfflinePlanList[$key]['classroom_capacity']);
            unset($courseOfflinePlanList[$key]['sign_up_num']);
            unset($courseOfflinePlanList[$key]['class_start_time']);
            unset($courseOfflinePlanList[$key]['class_end_time']);
            $week = date('n月j日',$classStartTime)." 周".$weekArray[date("w",$classStartTime)];
            $classStartTime = date('H:i',$classStartTime);
            $classEndTime = date('H:i',$classEndTime);

            if(isset($courseOfflineOrderList[$courseOfflinePlanId])){
                if($courseOfflineOrderList[$courseOfflinePlanId]['class_status'] == 0){
                    $classStatus = 1;
                }else{
                    $classStatus = 2;
                }
            }else if($signUpNum>=$classroomCapacity){
                $classStatus = 3;
            }
            $courseOfflinePlanList[$key]['status'] = $classStatus;
            $courseOfflinePlanList[$key]['class_time'] = "{$week} {$classStartTime}至{$classEndTime}";
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflinePlanList];
    }

    /**
     * 线下课程套餐(三级课程整套课程列表)
     * @param array $params
     * @return array
     */
    public function courseOfflinePackage(array $params): array
    {
        $batchNo = $params['batch_no'];

        $courseOfflinePlanList = CourseOfflinePlan::query()
            ->leftJoin('course_offline', 'course_offline_plan.course_offline_id', '=', 'course_offline.id')
            ->select(['course_offline.name','course_offline.course_category_id','course_offline.img_url','course_offline_plan.class_start_time','course_offline_plan.physical_store_id'])
            ->where(['course_offline_plan.batch_no'=>$batchNo,'course_offline_plan.is_deleted'=>0])
            ->orderBy('course_offline_plan.class_start_time','asc')
            ->get();
        $courseOfflinePlanList = $courseOfflinePlanList->toArray();
        $physicalStoreId = $courseOfflinePlanList[0]['physical_store_id'];
        $courseCategoryId = $courseOfflinePlanList[0]['course_category_id'];
        $imgUrl = $courseOfflinePlanList[0]['img_url'];
        $totalSection = count($courseOfflinePlanList);

        $physicalStoreInfo = PhysicalStore::query()->select(['city_name','district_name','address'])->where(['id'=>$physicalStoreId])->first();
        if(empty($physicalStoreInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '门店信息错误', 'data' => null];
        }
        $physicalStoreInfo = $physicalStoreInfo->toArray();

        $courseOfflineCategoryInfo = CourseOfflineCategory::query()->select(['name'])->where(['id'=>$courseCategoryId])->first();
        if(empty($courseOfflineCategoryInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '信息错误', 'data' => null];
        }
        $courseOfflineCategoryInfo = $courseOfflineCategoryInfo->toArray();
        $returnData = [
            'category_name' => $courseOfflineCategoryInfo['name'],
            'img_url' => $imgUrl,
            'city_name' => $physicalStoreInfo['city_name'],
            'district_name' => $physicalStoreInfo['district_name'],
            'address' => $physicalStoreInfo['address'],
            'total_section' => $totalSection
        ];

        foreach($courseOfflinePlanList as $key=>$value){
            $classStartTime = $value['class_start_time'];
            unset($courseOfflinePlanList[$key]['course_category_id']);
            unset($courseOfflinePlanList[$key]['img_url']);
            unset($courseOfflinePlanList[$key]['physical_store_id']);
            $courseOfflinePlanList[$key]['class_start_time'] = date('Y-m-d H:i:s',$classStartTime);
        }
        $returnData['course'] = $courseOfflinePlanList;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 线上课程列表
     * @param array $params
     * @return array
     */
    public function courseOnlineList(array $params): array
    {
        $courseCategory = $params['course_category_id'];
        $suitAge = $params['suit_age'];
        $offset = $this->offset;
        $limit = $this->limit;

        $model = CourseOnline::query();
        $where = [['is_deleted','=',0]];
        if($courseCategory !== null){
            $where[] = ['course_category_id','=',$courseCategory];
        }
        if($suitAge !== null){
            [$suitAgeMin,$suitAgeMax] = explode('-',$suitAge);
            $model->whereBetween('suit_age_min',[$suitAgeMin,$suitAgeMax])->orWhere(function ($query) use($suitAgeMin,$suitAgeMax) {
                $query->whereBetween('suit_age_max',[$suitAgeMin,$suitAgeMax]);
            })->orWhere(function ($query) use($suitAgeMin,$suitAgeMax) {
                $query->where([['suit_age_min','<=',$suitAgeMin],['suit_age_max','>=',$suitAgeMin]]);
            });
        }
        $courseOnlineList = $model->select(['id','name','img_url','suit_age_min','suit_age_max','total_section'])->where($where)->offset($offset)->limit($limit)->get();
        $courseOnlineList = $courseOnlineList->toArray();
        $count = $model->where($where)->count('id');

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$courseOnlineList,'count'=>$count]];
    }

    /**
     * 线上课程详情
     * @param int $id
     * @return array
     */
    public function courseOnlineDetail(int $id): array
    {
        $courseOnlineInfo = CourseOnline::query()
            ->select(['id','name','img_url','suit_age_min','suit_age_max','total_section'])
            ->where(['id'=>$id])
            ->first();
        if(empty($courseOnlineInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '数据错误', 'data' => null];
        }
        $courseOnlineInfo = $courseOnlineInfo->toArray();

        $courseOnlineChildList = CourseOnlineChild::query()
            ->select(['id','name','video_url'])
            ->where(['course_online_id'=>$id,'is_deleted'=>0])
            ->get();
        $courseOnlineChildList = $courseOnlineChildList->toArray();

        $courseOnlineInfo['child_course'] = $courseOnlineChildList;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOnlineInfo];
    }

    /**
     * 线上子课程列表
     * @param int $courseOnlineId
     * @return array
     */
    public function courseOnlineChildList(int $courseOnlineId): array
    {
        $courseOnlineChildList = CourseOnlineChild::query()
            ->select(['course_online_id','name','img_url'])
            ->where(['course_online_id'=>$courseOnlineId,'is_deleted'=>0])
            ->get();
        $courseOnlineChildList = $courseOnlineChildList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOnlineChildList];
    }

    /**
     * 线上子课程详情
     * @param int $id
     * @return array
     */
    public function courseOnlineChildDetail(int $id): array
    {
        $courseOnlineChildInfo = CourseOnlineChild::query()
            ->leftJoin('course_online','course_online_child.course_online_id','=','course_online.id')
            ->select(['course_online_child.course_online_id','course_online_child.name','course_online_child.video_url','course_online_child.describe','course_online_child.goods_id','course_online.suit_age_min','course_online.suit_age_max','course_online.type'])
            ->where(['course_online_child.id'=>$id,'course_online_child.is_deleted'=>0])
            ->first();
        if(empty($courseOnlineChildInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '数据错误', 'data' => null];
        }
        $courseOnlineChildInfo = $courseOnlineChildInfo->toArray();
        $courseOnlineChildInfo['is_reach_goods'] = 0;
        if(!empty($courseOnlineChildInfo['goods_id'])){
            $goodsId = json_decode($courseOnlineChildInfo['goods_id'],true);
            $courseOnlineChildInfo['goods_id'] = $goodsId[0] ?? 0;
            $courseOnlineChildInfo['is_reach_goods'] = 1;
        }

        Coroutine::create(function() use($id){
            Db::connection('jkc_edu')->update("UPDATE home_boutique_course SET page_views = page_views + ? WHERE course_online_child_id = ?", [1, $id]);
        });
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOnlineChildInfo];
    }

    /**
     * 添加线上课程收藏
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function addCourseOnlineCollect(array $params): array
    {
        $id = $params['id'];
        $memberId = $this->memberId;

        $courseOnlineInfo = CourseOnline::query()
            ->select(['name','total_section','suit_age_min','suit_age_max','img_url'])
            ->where(['id'=>$id,'is_deleted'=>0])
            ->first();
        if(empty($courseOnlineInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '暂无课程可添加', 'data' => null];
        }
        $courseOnlineInfo = $courseOnlineInfo->toArray();

        $courseOnlineChildList = CourseOnlineChild::query()
            ->select(['id','name','video_url'])
            ->where(['course_online_id'=>$id,'is_deleted'=>0])
            ->get();
        $courseOnlineChildList = $courseOnlineChildList->toArray();
        if(empty($courseOnlineChildList)){
            return ['code' => ErrorCode::WARNING, 'msg' => '暂无课程可添加', 'data' => null];
        }

        $courseOnlineCollectId = IdGenerator::generate();
        $insertCourseOnlineCollectData['id'] = $courseOnlineCollectId;
        $insertCourseOnlineCollectData['member_id'] = $memberId;
        $insertCourseOnlineCollectData['course_online_id'] = $id;
        $insertCourseOnlineCollectData['total_section'] = $courseOnlineInfo['total_section'];
        $insertCourseOnlineCollectData['suit_age_min'] = $courseOnlineInfo['suit_age_min'];
        $insertCourseOnlineCollectData['suit_age_max'] = $courseOnlineInfo['suit_age_max'];
        $insertCourseOnlineCollectData['img_url'] = $courseOnlineInfo['img_url'];
        $insertCourseOnlineCollectData['name'] = $courseOnlineInfo['name'];

        $insertCourseOnlineChildCollectData = [];
        foreach($courseOnlineChildList as $value){
            $courseOnlineChildId = $value['id'];

            $courseOnlineChildCollectData = [];
            $courseOnlineChildCollectData['id'] = IdGenerator::generate();
            $courseOnlineChildCollectData['course_online_collect_id'] = $courseOnlineCollectId;
            $courseOnlineChildCollectData['member_id'] = $memberId;
            $courseOnlineChildCollectData['course_online_id'] = $id;
            $courseOnlineChildCollectData['course_online_child_id'] = $courseOnlineChildId;
            $courseOnlineChildCollectData['name'] = $value['name'];
            $courseOnlineChildCollectData['video_url'] = $value['video_url'];
            $insertCourseOnlineChildCollectData[] = $courseOnlineChildCollectData;
        }

        Db::connection('jkc_edu')->transaction(function () use($insertCourseOnlineCollectData,$insertCourseOnlineChildCollectData){
            CourseOnlineCollect::query()->insert($insertCourseOnlineCollectData);
            CourseOnlineChildCollect::query()->insert($insertCourseOnlineChildCollectData);
        });
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 删除线上课程收藏
     * @param int $id
     * @return array
     * @throws \Throwable
     */
    public function deleteCourseOnlineCollect(int $id): array
    {
        $memberId = $this->memberId;

        Db::connection('jkc_edu')->transaction(function () use($memberId,$id){
            CourseOnlineCollect::query()->where(['member_id'=>$memberId,'id'=>$id])->delete();
            CourseOnlineChildCollect::query()->where(['member_id'=>$memberId,'course_online_collect_id'=>$id])->delete();
        });
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 线上课程收藏列表
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineCollectList(array $params): array
    {
        $studyStatus = $params['study_status'];
        $memberId = $this->memberId;
        $offset = $this->offset;
        $limit = $this->limit;

        $model = CourseOnlineCollect::query()
            ->leftJoin('course_online','course_online_collect.course_online_id','=','course_online.id')
            ->select(['course_online_collect.id','course_online_collect.name','course_online_collect.img_url','course_online_collect.suit_age_min','course_online_collect.suit_age_max','course_online_collect.total_section','course_online_collect.study_section','course_online.total_study'])
            ->where(['course_online_collect.member_id'=>$memberId]);
        if($studyStatus == 0){
            $model->whereRaw('course_online_collect.study_section < course_online_collect.total_section');
        }else if($studyStatus == 1){
            $model->whereRaw('course_online_collect.study_section >= course_online_collect.total_section');
        }
        $count = $model->count();
        $courseOnlineCollectList = $model->offset($offset)->limit($limit)->get();
        $courseOnlineCollectList = $courseOnlineCollectList->toArray();

        foreach($courseOnlineCollectList as $key=>$value){
            $studyStatus = 0;
            if($value['total_section'] <= $value['study_section']){
                $studyStatus = 1;
            }
            $courseOnlineCollectList[$key]['study_status'] = $studyStatus;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$courseOnlineCollectList,'count'=>$count]];
    }

    /**
     * 线上课程收藏详情
     * @param int $id
     * @return array
     */
    public function courseOnlineCollectDetail(int $id): array
    {
        $memberId = $this->memberId;

        $courseOnlineChildCollectList = CourseOnlineChildCollect::query()
            ->leftJoin('course_online_child','course_online_child_collect.course_online_child_id','=','course_online_child.id')
            ->select(['course_online_child_collect.id','course_online_child_collect.name','course_online_child_collect.video_url','course_online_child_collect.study_video_url','course_online_child_collect.status','course_online_child.total_study','course_online_child.img_url'])
            ->where(['course_online_child_collect.member_id'=>$memberId,'course_online_child_collect.course_online_collect_id'=>$id])
            ->get();
        $courseOnlineChildCollectList = $courseOnlineChildCollectList->toArray();

        $courseOnlineCollectInfo = CourseOnlineCollect::query()
            ->leftJoin('course_online','course_online_collect.course_online_id','=','course_online.id')
            ->select(['course_online_collect.name','course_online_collect.suit_age_min','course_online_collect.suit_age_max','course_online_collect.total_section','course_online.total_study'])
            ->where(['course_online_collect.id'=>$id])
            ->get();
        $courseOnlineCollectInfo = $courseOnlineCollectInfo->toArray();

        $courseOnlineCollectInfo['child_course'] = $courseOnlineChildCollectList;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOnlineCollectInfo];
    }

    /**
     * 线上子课程收藏详情
     * @param int $id
     * @return array
     */
    public function courseOnlineChildCollectDetail(int $id): array
    {
        $memberId = $this->memberId;

        $courseOnlineChildCollectInfo = CourseOnlineChildCollect::query()
            ->select(['id','name','video_url','study_video_url','status','member_explain','examine_explain'])
            ->where(['id'=>$id,'member_id'=>$memberId])
            ->first();
        $courseOnlineChildCollectInfo = $courseOnlineChildCollectInfo->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOnlineChildCollectInfo];
    }

    /**
     * 添加收藏线上子课程学习作品
     * @param array $params
     * @return array
     */
    public function addCourseOnlineChildCollectStudyOpus(array $params): array
    {
        $id = $params['id'];
        $studyVideoUrl = $params['study_video_url'];
        $memberExplain = $params['member_explain'];
        $memberId = $this->memberId;
        $date = date('Y-m-d H:i:s');

        $updateCourseOnlineChildCollect['study_video_url'] = $studyVideoUrl;
        $updateCourseOnlineChildCollect['member_explain'] = $memberExplain;
        $updateCourseOnlineChildCollect['status'] = 1;
        $updateCourseOnlineChildCollect['study_at'] = $date;
        CourseOnlineChildCollect::query()->where(['id'=>$id,'member_id'=>$memberId])->update($updateCourseOnlineChildCollect);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    public function courseOnlineChildCollectStudyOpusShare(int $id): array
    {
        $memberId = $this->memberId;

        $courseOnlineChildCollectInfo = CourseOnlineChildCollect::query()
            ->leftJoin('course_online_child','course_online_child_collect.course_online_child_id','=','course_online_child.id')
            ->select(['course_online_child.img_url'])
            ->where(['course_online_child_collect.id'=>$id])
            ->first();
        $courseOnlineChildCollectInfo = $courseOnlineChildCollectInfo->toArray();

        $memberInfo = Member::query()
            ->select(['name','avatar'])
            ->where(['id'=>$memberId])
            ->first();
        $memberInfo = $memberInfo->toArray();

        $returnData = [
            'img_url' => $courseOnlineChildCollectInfo['img_url'],
            'name' => $memberInfo['name'],
            'avatar' => $memberInfo['avatar'],
            'qc_code' => '',
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 课程详情配置列表
     * @return array
     */
    public function courseDetailSetUpList(): array
    {
        $courseDetailSetUpList = CourseDetailSetUp::query()
            ->select(['title','content','price','original_price','vip_card_id','type','theme_type'])
            ->get();
        $courseDetailSetUpList = $courseDetailSetUpList->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseDetailSetUpList];
    }

    /**
     * 线下课程年龄标签数据
     * @return array
     */
    public function courseOfflineAgeTag(): array
    {
        $courseOfflineAgeTagList = CourseOfflineAgeTag::query()
            ->select(['theme_type','suit_age','describe'])
            ->get();
        $courseOfflineAgeTagList = $courseOfflineAgeTagList->toArray();
        $courseOfflineAgeTagList = $this->functions->arrayGroupBy($courseOfflineAgeTagList,'theme_type');

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOfflineAgeTagList];
    }

}