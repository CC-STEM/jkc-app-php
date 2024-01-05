<?php

declare(strict_types=1);

namespace App\Service;

use App\Event\CourseOfflineCompleteRegistered;
use App\Model\AsyncTask;
use App\Model\CourseOfflineClassroomSituation;
use App\Model\SalaryTemplateLevel;
use App\Model\Teacher;
use App\Model\Member;
use App\Model\CourseOffline;
use App\Model\CourseOfflinePlan;
use App\Model\CourseOfflineOrder;
use App\Constants\ErrorCode;
use App\Model\TeacherSalaryBill;
use App\Model\TeacherSalaryBillDetailed;
use App\Model\VipCardOrder;
use App\Snowflake\IdGenerator;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Context;
use Psr\EventDispatcher\EventDispatcherInterface;

class TeacherIdentityService extends BaseService
{
    #[Inject]
    private EventDispatcherInterface $eventDispatcher;

    /**
     * TeacherIdentityService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 老师薪资数据统计
     * @return array
     */
    public function teacherSalaryStatistics(): array
    {
        $month = date('Ym');
        $memberId = $this->memberId;

        //会员信息
        $memberInfo = Member::query()->select(['mobile','avatar'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"登录异常",'data'=>null];
        }
        $memberInfo = $memberInfo->toArray();
        $mobile = $memberInfo['mobile'];

        //老师信息
        $teacherInfo = Teacher::query()->select(['id','name','rank_status'])->where(['mobile'=>$mobile])->first();
        if(empty($teacherInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"身份信息不存在",'data'=>null];
        }
        $teacherInfo = $teacherInfo->toArray();
        $teacherId = $teacherInfo['id'];
        $rankStatus = $teacherInfo['rank_status'];

        $teacherSalaryBillInfo = TeacherSalaryBill::query()
            ->select(['id','basic_salary'])
            ->where(['teacher_id'=>$teacherId,'month'=>$month])
            ->first();
        if(empty($teacherSalaryBillInfo)){
            $returnData = [
                'month'=>$month,
                'salary'=>0,
                'attendance_ratio'=>'0%',
                'commission1'=>$rankStatus == 1 ? '无提成' : 0,
                'commission2'=>$rankStatus == 1 ? '无提成' : 0,
                'commission3'=>$rankStatus == 1 ? '无提成' : 0,
                'commission4'=>0,
                'commission5'=>0,
                'commission6'=>0,
                'course_offline_theme1_count1'=>0,
                'course_offline_theme1_count2'=>0,
                'course_offline_theme2_count1'=>0,
                'course_offline_theme2_count2'=>0,
                'course_offline_theme3_count1'=>0,
                'course_offline_theme3_count2'=>0,
            ];
            return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
        }
        $teacherSalaryBillInfo = $teacherSalaryBillInfo->toArray();

        //薪资账单清单数据
        $teacherSalaryBillDetailedData = TeacherSalaryBillDetailed::query()
            ->selectRaw('sum(commission) as sum_commission,type')
            ->where(['teacher_salary_bill_id'=>$teacherSalaryBillInfo['id'],'status'=>1])
            ->groupBy('type')
            ->get();
        $teacherSalaryBillDetailedData = $teacherSalaryBillDetailedData->toArray();
        $teacherSalaryBillDetailedData = $this->functions->arrayGroupBy($teacherSalaryBillDetailedData,'type');
        $commission1 = $teacherSalaryBillDetailedData['1'][0]['sum_commission'] ?? 0;
        $commission2 = $teacherSalaryBillDetailedData['2'][0]['sum_commission'] ?? 0;
        $commission3 = $teacherSalaryBillDetailedData['3'][0]['sum_commission'] ?? 0;
        $commission4 = $teacherSalaryBillDetailedData['4'][0]['sum_commission'] ?? 0;
        $commission5 = $teacherSalaryBillDetailedData['5'][0]['sum_commission'] ?? 0;
        $commission6 = $teacherSalaryBillDetailedData['6'][0]['sum_commission'] ?? 0;
        $totalCommission = $commission1+$commission2+$commission3+$commission4+$commission5+$commission6;

        //常规班报名人数
        $courseOfflineTheme1Count1 = CourseOfflineOrder::query()
            ->leftJoin('course_offline_plan','course_offline_order.course_offline_plan_id','=','course_offline_plan.id')
            ->whereRaw('course_offline_order.teacher_id=? AND DATE_FORMAT(course_offline_order.start_at, "%Y%m")=? AND course_offline_order.order_status=0 AND course_offline_order.pay_status=1 AND course_offline_order.theme_type=1 AND course_offline_plan.classroom_situation=1',[$teacherId,$month])
            ->count();
        //常规班实到人数
        $courseOfflineTheme1Count2 = CourseOfflineOrder::query()
            ->leftJoin('course_offline_plan','course_offline_order.course_offline_plan_id','=','course_offline_plan.id')
            ->whereRaw('course_offline_order.teacher_id=? AND DATE_FORMAT(course_offline_order.start_at, "%Y%m")=? AND course_offline_order.order_status=0 AND course_offline_order.pay_status=1 AND course_offline_order.class_status=1 AND course_offline_order.theme_type=1 AND course_offline_plan.classroom_situation=1',[$teacherId,$month])
            ->count();

        //精品小班报名人数
        $courseOfflineTheme2Count1 = CourseOfflineOrder::query()
            ->leftJoin('course_offline_plan','course_offline_order.course_offline_plan_id','=','course_offline_plan.id')
            ->whereRaw('course_offline_order.teacher_id=? AND DATE_FORMAT(course_offline_order.start_at, "%Y%m")=? AND course_offline_order.order_status=0 AND course_offline_order.pay_status=1 AND course_offline_order.theme_type=2 AND course_offline_plan.classroom_situation=1',[$teacherId,$month])
            ->count();
        //精品小班实到人数
        $courseOfflineTheme2Count2 = CourseOfflineOrder::query()
            ->leftJoin('course_offline_plan','course_offline_order.course_offline_plan_id','=','course_offline_plan.id')
            ->whereRaw('course_offline_order.teacher_id=? AND DATE_FORMAT(course_offline_order.start_at, "%Y%m")=? AND course_offline_order.order_status=0 AND course_offline_order.pay_status=1 AND course_offline_order.class_status=1 AND course_offline_order.theme_type=2 AND course_offline_plan.classroom_situation=1',[$teacherId,$month])
            ->count();

        //竞赛班报名人数
        $courseOfflineTheme3Count1 = CourseOfflineOrder::query()
            ->leftJoin('course_offline_plan','course_offline_order.course_offline_plan_id','=','course_offline_plan.id')
            ->whereRaw('course_offline_order.teacher_id=? AND DATE_FORMAT(course_offline_order.start_at, "%Y%m")=? AND course_offline_order.order_status=0 AND course_offline_order.pay_status=1 AND course_offline_order.theme_type=3 AND course_offline_plan.classroom_situation=1',[$teacherId,$month])
            ->count();
        //竞赛班实到人数
        $courseOfflineTheme3Count2 = CourseOfflineOrder::query()
            ->leftJoin('course_offline_plan','course_offline_order.course_offline_plan_id','=','course_offline_plan.id')
            ->whereRaw('course_offline_order.teacher_id=? AND DATE_FORMAT(course_offline_order.start_at, "%Y%m")=? AND course_offline_order.order_status=0 AND course_offline_order.pay_status=1 AND course_offline_order.class_status=1 AND course_offline_order.theme_type=3 AND course_offline_plan.classroom_situation=1',[$teacherId,$month])
            ->count();

        $totalCourseOfflineCount1 = $courseOfflineTheme1Count1+$courseOfflineTheme2Count1+$courseOfflineTheme3Count1;
        $totalCourseOfflineCount2 = $courseOfflineTheme1Count2+$courseOfflineTheme2Count2+$courseOfflineTheme3Count2;
        $attendanceRatio = $totalCourseOfflineCount1>0 ? bcdiv((string)$totalCourseOfflineCount2,(string)$totalCourseOfflineCount1,2) : '0';
        $salary = bcadd((string)$totalCommission,$teacherSalaryBillInfo['basic_salary'],2);

        $returnData = [
            'month'=>$month,
            'salary'=>$salary,
            'attendance_ratio'=>bcmul($attendanceRatio, '100').'%',
            'commission1'=>$rankStatus == 1 ? '无提成' : $commission1,
            'commission2'=>$rankStatus == 1 ? '无提成' : $commission2,
            'commission3'=>$rankStatus == 1 ? '无提成' : $commission3,
            'commission4'=>$commission4,
            'commission5'=>$commission5,
            'commission6'=>$commission6,
            'course_offline_theme1_count1'=>$courseOfflineTheme1Count1,
            'course_offline_theme1_count2'=>$courseOfflineTheme1Count2,
            'course_offline_theme2_count1'=>$courseOfflineTheme2Count1,
            'course_offline_theme2_count2'=>$courseOfflineTheme2Count2,
            'course_offline_theme3_count1'=>$courseOfflineTheme3Count1,
            'course_offline_theme3_count2'=>$courseOfflineTheme3Count2,
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
    }

    /**
     * 老师薪资数据详情
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function teacherSalaryDetailed(array $params): array
    {
        $month = $params['month'] ?? date('Y.m');
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');
        $month = date('Ym',strtotime(str_replace('.','-',$month)));

        //会员信息
        $memberInfo = Member::query()->select(['mobile','avatar'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"登录异常",'data'=>null];
        }
        $memberInfo = $memberInfo->toArray();
        $mobile = $memberInfo['mobile'];

        //老师信息
        $teacherInfo = Teacher::query()->select(['id','name','created_at','rank_status'])->where(['mobile'=>$mobile])->first();
        if(empty($teacherInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"身份信息不存在",'data'=>null];
        }
        $teacherInfo = $teacherInfo->toArray();
        $teacherId = $teacherInfo['id'];
        $teacherEntryAt = $teacherInfo['created_at'];
        $rankStatus = $teacherInfo['rank_status'];

        //月份选择数据
        for ($i=0; $i<6; $i++) {
            $selectedMonthData[] = date('Y.m', strtotime(-$i.'month'));
        }
        $diffMonth = $this->functions->diffMonth($teacherEntryAt,$nowDate)+1;
        if($diffMonth<6){
            $selectedMonthData = array_slice($selectedMonthData,0,$diffMonth);
        }
        $teacherSalaryBillInfo = TeacherSalaryBill::query()
            ->select(['id','basic_salary'])
            ->where(['teacher_id'=>$teacherId,'month'=>$month])
            ->first();
        if(empty($teacherSalaryBillInfo)){
            $returnData = [
                'id'=>'0',
                'salary'=>0,
                'commission1'=>$rankStatus == 1 ? '无提成' : 0,
                'commission2'=>$rankStatus == 1 ? '无提成' : 0,
                'commission3'=>$rankStatus == 1 ? '无提成' : 0,
                'commission4'=>0,
                'commission5'=>0,
                'commission6'=>0,
                'selected_month'=>$selectedMonthData
            ];
            return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
        }
        $teacherSalaryBillInfo = $teacherSalaryBillInfo->toArray();

        //薪资账单清单数据
        $teacherSalaryBillDetailedData = TeacherSalaryBillDetailed::query()
            ->selectRaw('sum(commission) as sum_commission,type')
            ->where(['teacher_salary_bill_id'=>$teacherSalaryBillInfo['id'],'status'=>1])
            ->groupBy('type')
            ->get();
        $teacherSalaryBillDetailedData = $teacherSalaryBillDetailedData->toArray();
        $teacherSalaryBillDetailedData = $this->functions->arrayGroupBy($teacherSalaryBillDetailedData,'type');
        $commission1 = $teacherSalaryBillDetailedData['1'][0]['sum_commission'] ?? 0;
        $commission2 = $teacherSalaryBillDetailedData['2'][0]['sum_commission'] ?? 0;
        $commission3 = $teacherSalaryBillDetailedData['3'][0]['sum_commission'] ?? 0;
        $commission4 = $teacherSalaryBillDetailedData['4'][0]['sum_commission'] ?? 0;
        $commission5 = $teacherSalaryBillDetailedData['5'][0]['sum_commission'] ?? 0;
        $commission6 = $teacherSalaryBillDetailedData['6'][0]['sum_commission'] ?? 0;
        $totalCommission = $commission1+$commission2+$commission3+$commission4+$commission5+$commission6;
        $salary = bcadd((string)$totalCommission,$teacherSalaryBillInfo['basic_salary'],2);

        $returnData = [
            'id'=>$teacherSalaryBillInfo['id'],
            'salary'=>$salary,
            'commission1'=>$rankStatus == 1 ? '无提成' : $commission1,
            'commission2'=>$rankStatus == 1 ? '无提成' : $commission2,
            'commission3'=>$rankStatus == 1 ? '无提成' : $commission3,
            'commission4'=>$commission4,
            'commission5'=>$commission5,
            'commission6'=>$commission6,
            'selected_month'=>$selectedMonthData
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
    }

    /**
     * 老师薪资详情列表
     * @param array $params
     * @return array
     */
    public function teacherSalaryDetailedList(array $params): array
    {
        $id = $params['id'];
        $type = $params['type'];
        $offset = $this->offset;
        $limit = $this->limit;

        switch ($type){
            case 1:
            case 2:
            case 3:
                $model = TeacherSalaryBillDetailed::query()
                    ->leftJoin('member','teacher_salary_bill_detailed.member_id','=','member.id')
                    ->select(['member.name as member_name','member.mobile','teacher_salary_bill_detailed.amount','teacher_salary_bill_detailed.commission','teacher_salary_bill_detailed.commission_rate','teacher_salary_bill_detailed.status','teacher_salary_bill_detailed.created_at','teacher_salary_bill_detailed.source'])
                    ->where(['teacher_salary_bill_detailed.teacher_salary_bill_id'=>$id,'teacher_salary_bill_detailed.type'=>$type]);
                $count = $model->count();
                $teacherSalaryBillDetailedList = $model->orderBy('teacher_salary_bill_detailed.id','desc')->offset($offset)->limit($limit)->get();
                $teacherSalaryBillDetailedList = $teacherSalaryBillDetailedList->toArray();
                foreach($teacherSalaryBillDetailedList as $key=>$value){
                    if($value['source'] == 2){
                        $teacherSalaryBillDetailedList[$key]['commission'] = '推荐好友抵扣券提成金额'.$value['commission'];
                    }else if($value['source'] == 3){
                        $teacherSalaryBillDetailedList[$key]['commission'] = '会员权益课提成金额'.$value['commission'];
                    }
                }
                break;
            case 4:
                $model = TeacherSalaryBillDetailed::query()
                    ->leftJoin('order_info','teacher_salary_bill_detailed.outer_parent_id','=','order_info.id')
                    ->leftJoin('member','teacher_salary_bill_detailed.member_id','=','member.id')
                    ->select(['order_info.order_no','member.name as member_name','member.mobile','teacher_salary_bill_detailed.amount','teacher_salary_bill_detailed.commission','teacher_salary_bill_detailed.commission_rate','teacher_salary_bill_detailed.status','teacher_salary_bill_detailed.created_at','teacher_salary_bill_detailed.source'])
                    ->where(['teacher_salary_bill_detailed.teacher_salary_bill_id'=>$id,'teacher_salary_bill_detailed.type'=>$type]);
                $count = $model->count();
                $teacherSalaryBillDetailedList = $model->orderBy('teacher_salary_bill_detailed.id','desc')->offset($offset)->limit($limit)->get();
                $teacherSalaryBillDetailedList = $teacherSalaryBillDetailedList->toArray();
                break;
            case 5:
                $model = TeacherSalaryBillDetailed::query()
                    ->leftJoin('vip_card_order','teacher_salary_bill_detailed.outer_id','=','vip_card_order.id')
                    ->leftJoin('member','teacher_salary_bill_detailed.member_id','=','member.id')
                    ->select(['member.name as member_name','member.mobile','vip_card_order.order_title','teacher_salary_bill_detailed.amount','teacher_salary_bill_detailed.commission','teacher_salary_bill_detailed.commission_rate','teacher_salary_bill_detailed.status','teacher_salary_bill_detailed.created_at','teacher_salary_bill_detailed.source'])
                    ->where(['teacher_salary_bill_detailed.teacher_salary_bill_id'=>$id,'teacher_salary_bill_detailed.type'=>$type]);
                $count = $model->count();
                $teacherSalaryBillDetailedList = $model->orderBy('teacher_salary_bill_detailed.id','desc')->offset($offset)->limit($limit)->get();
                $teacherSalaryBillDetailedList = $teacherSalaryBillDetailedList->toArray();
                break;
            case 6:
                $model = TeacherSalaryBillDetailed::query()
                    ->select(['commission','notes','created_at','source'])
                    ->where(['teacher_salary_bill_id'=>$id,'type'=>$type]);
                $count = $model->count();
                $teacherSalaryBillDetailedList = $model->orderBy('teacher_salary_bill_detailed.id','desc')->offset($offset)->limit($limit)->get();
                $teacherSalaryBillDetailedList = $teacherSalaryBillDetailedList->toArray();
                break;
            default:
                return ['code' => ErrorCode::WARNING, 'msg' => '参数错误', 'data' => ['list'=>[],'count'=>0]];
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$teacherSalaryBillDetailedList,'count'=>$count]];
    }

    /**
     * 老师最近上课数据统计
     * @return array
     */
    public function teacherCourseStatistics(): array
    {
        $memberId = $this->memberId;

        //当前时间
        $nowTime = time();
        //本周开始/结束时间
        $weekStartDate1 =  date("Y-m-d H:i:s", mktime(0,0,0,(int)date("m"),(int)date("d")-(int)date("w")+1,(int)date("Y")));
        $weekEndDate1 =  date("Y-m-d H:i:s", mktime(23,59,59,(int)date("m"),(int)date("d")-(int)date("w")+7,(int)date("Y")));
        $weekStartTime1 = strtotime($weekStartDate1);
        $weekEndTime1 = strtotime($weekEndDate1);
        //上周开始/结束时间
        $weekStartDate2 =  date("Y-m-d H:i:s", mktime(0,0,0,(int)date("m"),(int)date("d")-(int)date("w")+1-7,(int)date("Y")));
        $weekEndDate2 =  date("Y-m-d H:i:s", mktime(23,59,59,(int)date("m"),(int)date("d")-(int)date("w")+7-7,(int)date("Y")));
        $weekStartTime2 = strtotime($weekStartDate2);
        $weekEndTime2 = strtotime($weekEndDate2);
        //上上周开始/结束时间
        $weekStartDate3 =  date("Y-m-d H:i:s", mktime(0,0,0,(int)date("m"),(int)date("d")-(int)date("w")+1-14,(int)date("Y")));
        $weekEndDate3 =  date("Y-m-d H:i:s", mktime(23,59,59,(int)date("m"),(int)date("d")-(int)date("w")+7-14,(int)date("Y")));
        $weekStartTime3 = strtotime($weekStartDate3);
        $weekEndTime3 = strtotime($weekEndDate3);
        //上上上周开始/结束时间
        $weekStartDate4 =  date("Y-m-d H:i:s", mktime(0,0,0,(int)date("m"),(int)date("d")-(int)date("w")+1-21,(int)date("Y")));
        $weekEndDate4 =  date("Y-m-d H:i:s", mktime(23,59,59,(int)date("m"),(int)date("d")-(int)date("w")+7-21,(int)date("Y")));
        $weekStartTime4 = strtotime($weekStartDate4);
        $weekEndTime4 = strtotime($weekEndDate4);

        //会员信息
        $memberInfo = Member::query()->select(['mobile','avatar'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"登录异常",'data'=>null];
        }
        $memberInfo = $memberInfo->toArray();
        $mobile = $memberInfo['mobile'];

        //老师信息
        $teacherInfo = Teacher::query()->select(['id','name'])->where(['mobile'=>$mobile])->first();
        if(empty($teacherInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"身份信息不存在",'data'=>null];
        }
        $teacherInfo = $teacherInfo->toArray();
        $teacherId = $teacherInfo['id'];
        //待上课总数
        $noLectureCount = CourseOfflinePlan::query()
            ->where([['teacher_id','=',$teacherId],['is_deleted','=',0],['class_end_time','>=',$nowTime]])
            ->count();
        //已上课总数
        $lecturedCount = CourseOfflinePlan::query()
            ->where([['teacher_id','=',$teacherId],['is_deleted','=',0],['class_end_time','<',$nowTime]])
            ->count('id');

        //本周上课次数/人数
        $weekLecturedCount1 = CourseOfflinePlan::query()
            ->where(['teacher_id'=>$teacherId,'is_deleted'=>0])->whereBetween('class_end_time',[$weekStartTime1,$nowTime])
            ->count('id');
        $weekStudentCount1 = CourseOfflinePlan::query()
            ->where(['teacher_id'=>$teacherId,'is_deleted'=>0])->whereBetween('class_end_time',[$weekStartTime1,$nowTime])
            ->sum('sign_up_num');
        //上周上课次数/人数
        $weekLecturedCount2 = CourseOfflinePlan::query()
            ->where(['teacher_id'=>$teacherId,'is_deleted'=>0])->whereBetween('class_end_time',[$weekStartTime2,$weekEndTime2])
            ->count('id');
        $weekStudentCount2 = CourseOfflinePlan::query()
            ->where(['teacher_id'=>$teacherId,'is_deleted'=>0])->whereBetween('class_end_time',[$weekStartTime2,$weekEndTime2])
            ->sum('sign_up_num');
        //上上周上课次数/人数
        $weekLecturedCount3 = CourseOfflinePlan::query()
            ->where(['teacher_id'=>$teacherId,'is_deleted'=>0])->whereBetween('class_end_time',[$weekStartTime3,$weekEndTime3])
            ->count('id');
        $weekStudentCount3 = CourseOfflinePlan::query()
            ->where(['teacher_id'=>$teacherId,'is_deleted'=>0])->whereBetween('class_end_time',[$weekStartTime3,$weekEndTime3])
            ->sum('sign_up_num');
        //上上上周上课次数/人数
        $weekLecturedCount4 = CourseOfflinePlan::query()
            ->where(['teacher_id'=>$teacherId,'is_deleted'=>0])->whereBetween('class_end_time',[$weekStartTime4,$weekEndTime4])
            ->count('id');
        $weekStudentCount4 = CourseOfflinePlan::query()
            ->where(['teacher_id'=>$teacherId,'is_deleted'=>0])->whereBetween('class_end_time',[$weekStartTime4,$weekEndTime4])
            ->sum('sign_up_num');
        $showDateText1 = date('d',$weekStartTime1).'日-'.date('d',$weekEndTime1).'日';
        $showDateText2 = date('d',$weekStartTime2).'日-'.date('d',$weekEndTime2).'日';
        $showDateText3 = date('d',$weekStartTime3).'日-'.date('d',$weekEndTime3).'日';
        $showDateText4 = date('d',$weekStartTime4).'日-'.date('d',$weekEndTime4).'日';
        $weekData = [
            ['date_text'=>$showDateText4,'student_count'=>$weekStudentCount4,'lectured_count'=>$weekLecturedCount4],
            ['date_text'=>$showDateText3,'student_count'=>$weekStudentCount3,'lectured_count'=>$weekLecturedCount3],
            ['date_text'=>$showDateText2,'student_count'=>$weekStudentCount2,'lectured_count'=>$weekLecturedCount2],
            ['date_text'=>$showDateText1,'student_count'=>$weekStudentCount1,'lectured_count'=>$weekLecturedCount1],
        ];
        $returnData = [
            'teacher_id' => $teacherId,
            'teacher_name' => $teacherInfo['name'],
            'avatar' => $memberInfo['avatar'],
            'no_lecture_count' => $noLectureCount,
            'lectured_count' => $lecturedCount,
            'week_data' => $weekData
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$returnData];
    }

    /**
     * 老师课程列表
     * @param array $params
     * @return array
     */
    public function teacherCourseList(array $params): array
    {
        $type = $params['type'];
        $memberId = $this->memberId;
        $offset = $this->offset;
        $limit = $this->limit;
        //当前时间
        $nowTime = time();
        //14天后的时间
        $afterDay14Time = strtotime(date("Y-m-d H:i:s",strtotime("+14 day")));

        //会员信息
        $memberInfo = Member::query()->select(['mobile'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"登录异常",'data'=>null];
        }
        $memberInfo = $memberInfo->toArray();
        $mobile = $memberInfo['mobile'];

        //老师信息
        $teacherInfo = Teacher::query()->select(['id','name'])->where(['mobile'=>$mobile])->first();
        if(empty($teacherInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"身份信息不存在",'data'=>null];
        }
        $teacherInfo = $teacherInfo->toArray();
        $teacherId = $teacherInfo['id'];

        if($type == 0){
            //待上课
            $courseList = CourseOfflinePlan::query()
                ->leftJoin('course_offline', 'course_offline_plan.course_offline_id', '=', 'course_offline.id')
                ->select(['course_offline.name','course_offline.video_url','course_offline_plan.id','course_offline_plan.classroom_name','course_offline_plan.class_start_time','course_offline_plan.class_end_time','course_offline_plan.sign_up_num'])
                ->where([['course_offline_plan.teacher_id','=',$teacherId],['course_offline_plan.is_deleted','=',0],['course_offline_plan.class_end_time','>=',$nowTime]])
                ->orderBy('course_offline_plan.class_start_time')
                ->offset($offset)->limit($limit)
                ->get();
            $count = CourseOfflinePlan::query()->where([['course_offline_plan.teacher_id','=',$teacherId],['course_offline_plan.is_deleted','=',0],['course_offline_plan.class_end_time','>=',$nowTime]])->count();
        }else{
            //已上课
            $courseList = CourseOfflinePlan::query()
                ->leftJoin('course_offline', 'course_offline_plan.course_offline_id', '=', 'course_offline.id')
                ->select(['course_offline.name','course_offline.video_url','course_offline_plan.id','course_offline_plan.classroom_name','course_offline_plan.class_start_time','course_offline_plan.class_end_time','course_offline_plan.sign_up_num'])
                ->where([['course_offline_plan.teacher_id','=',$teacherId],['course_offline_plan.is_deleted','=',0],['course_offline_plan.class_end_time','<',$nowTime]])
                ->orderBy('course_offline_plan.class_start_time','desc')
                ->offset($offset)->limit($limit)
                ->get();
            $count = CourseOfflinePlan::query()->where([['course_offline_plan.teacher_id','=',$teacherId],['course_offline_plan.is_deleted','=',0],['course_offline_plan.class_end_time','<',$nowTime]])->count();
        }
        $courseList = $courseList->toArray();

        foreach($courseList as $key=>$value){
            $classStartTime = $value['class_start_time'];
            $classEndTime = $value['class_end_time'];
            unset($courseList[$key]['class_start_time']);
            unset($courseList[$key]['class_end_time']);
            $classStartTime = date('Y-m-d H:i',$classStartTime);
            $classEndTime = date('H:i',$classEndTime);
            $courseList[$key]['class_time'] = "{$classStartTime} 至 {$classEndTime}";
        }
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>['list'=>$courseList,'count'=>$count]];
    }

    /**
     * 老师课程详情
     * @param int $id
     * @return array
     */
    public function teacherCourseDetail(int $id): array
    {
        $nowTime = time();
        $nowDate = date('Y-m-d H:i:s');
        //排课信息
        $courseOfflinePlanInfo = CourseOfflinePlan::query()
            ->select(['course_offline_id','classroom_name','class_start_time','class_end_time','sign_up_num'])
            ->where(['id'=>$id])
            ->first();
        if(empty($courseOfflinePlanInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"查看失败",'data'=>null];
        }
        $courseOfflinePlanInfo = $courseOfflinePlanInfo->toArray();
        $status = 0;
        if($courseOfflinePlanInfo['class_end_time'] <= $nowTime){
            $status = 1;
        }
        $courseOfflineId = $courseOfflinePlanInfo['course_offline_id'];
        //课程信息
        $courseOfflineInfo = CourseOffline::query()
            ->select(['name','video_url','student_video_url'])
            ->where(['id'=>$courseOfflineId])
            ->first();
        if(empty($courseOfflineInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"查看失败",'data'=>null];
        }
        $courseOfflineInfo = $courseOfflineInfo->toArray();
        //课程订单
        $courseOfflineOrderList = CourseOfflineOrder::query()
            ->select(['id','member_id','class_status','is_sample'])
            ->where(['course_offline_plan_id'=>$id,'pay_status'=>1,'order_status'=>0])
            ->get();
        $courseOfflineOrderList = $courseOfflineOrderList->toArray();
        $memberIdArray = array_column($courseOfflineOrderList, 'member_id');
        //上课会员
        $memberList = Member::query()
            ->select(['id','name','mobile','avatar','school','birthday'])
            ->whereIn('id',$memberIdArray)
            ->get();
        $memberList = $memberList->toArray();
        $combineMemberKey = array_column($memberList,'id');
        $memberList = array_combine($combineMemberKey,$memberList);

        $courseOfflineClassroomSituationList = CourseOfflineClassroomSituation::query()
            ->select(['img_url'])
            ->where(['course_offline_plan_id'=>$id])
            ->get();
        $courseOfflineClassroomSituationList = $courseOfflineClassroomSituationList->toArray();

        foreach($courseOfflineOrderList as $key=>$value){
            $studentMemberId = $value['member_id'];
            $studentMemberInfo = $memberList[$studentMemberId];
            $mobile = $studentMemberInfo['mobile'];

            //会员卡信息
            $vipCardOrderList = VipCardOrder::query()
                ->select(['id','course1','course1_used','course2','course2_used','course3','course3_used','currency_course','currency_course_used'])
                ->where([['member_id','=',$studentMemberId],['pay_status','=',1],['order_status','=',0],['expire_at','>',$nowDate]])
                ->get();
            $vipCardOrderList = $vipCardOrderList->toArray();
            //会员卡账户信息
            $totalSurplusSectionCourse = 0;
            foreach($vipCardOrderList as $item){
                $surplusSectionCourse1 = $item['course1']-$item['course1_used'];
                $surplusSectionCourse2 = $item['course2']-$item['course2_used'];
                $surplusSectionCourse3 = $item['course3']-$item['course3_used'];
                $surplusSectionCurrencyCourse = $item['currency_course']-$item['currency_course_used'];
                $surplusSectionCourse = $surplusSectionCourse1+$surplusSectionCourse2+$surplusSectionCourse3+$surplusSectionCurrencyCourse;
                $totalSurplusSectionCourse += $surplusSectionCourse;
            }
            //已上课
            $classAttendanceCount = CourseOfflineOrder::query()->where(['member_id'=>$studentMemberId,'class_status'=>1,'order_status'=>0])->count();
            //已约课
            $totalCourseUsed = CourseOfflineOrder::query()->where(['member_id'=>$studentMemberId,'order_status'=>0,'class_status'=>0])->count();
            //会员卡过期
            $vipCardStatusText = $totalSurplusSectionCourse<=4 && $totalSurplusSectionCourse>0 ? '即将过期' : '';

            $courseOfflineOrderList[$key]['mobile'] = '尾号'.substr((string)$mobile, -4);
            $courseOfflineOrderList[$key]['name'] = $studentMemberInfo['name'];
            $courseOfflineOrderList[$key]['avatar'] = $studentMemberInfo['avatar'];
            $courseOfflineOrderList[$key]['school'] = $studentMemberInfo['school'];
            $courseOfflineOrderList[$key]['birthday'] = $studentMemberInfo['birthday'];
            $courseOfflineOrderList[$key]['surplus_course_count'] = $totalSurplusSectionCourse;
            $courseOfflineOrderList[$key]['used_course_count'] = $totalCourseUsed;
            $courseOfflineOrderList[$key]['class_attendance_count'] = $classAttendanceCount;
            $courseOfflineOrderList[$key]['vip_card_status_text'] = $vipCardStatusText;
        }

        $classStartTime = date('Y-m-d H:i',$courseOfflinePlanInfo['class_start_time']);
        $classEndTime = date('H:i',$courseOfflinePlanInfo['class_end_time']);
        unset($courseOfflinePlanInfo['class_start_time']);
        unset($courseOfflinePlanInfo['class_end_time']);
        unset($courseOfflinePlanInfo['course_offline_id']);
        $courseOfflinePlanInfo['class_time'] = "{$classStartTime} {$classEndTime}";
        $courseOfflinePlanInfo['name'] = $courseOfflineInfo['name'];
        $courseOfflinePlanInfo['video_url'] = $courseOfflineInfo['video_url'];
        $courseOfflinePlanInfo['student_video_url'] = $courseOfflineInfo['student_video_url'];
        $courseOfflinePlanInfo['students'] = $courseOfflineOrderList;
        $courseOfflinePlanInfo['status'] = $status;
        $courseOfflinePlanInfo['imgs'] = $courseOfflineClassroomSituationList;

        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$courseOfflinePlanInfo];
    }

    /**
     * 老师点名
     * @param array $params
     * @return array
     * @throws \Throwable
     */
    public function teacherRollCall(array $params): array
    {
        $id = $params['course_offline_order'];
        $classStatus = $params['class_status'] ?? 1;
        $nowDate = date('Y-m-d H:i:s');

        $courseOfflineOrderInfo = CourseOfflineOrder::query()
            ->select(['member_id','class_status','end_at'])
            ->where(['id'=>$id])
            ->first();
        $courseOfflineOrderInfo = $courseOfflineOrderInfo->toArray();
        $memberId = $courseOfflineOrderInfo['member_id'];
        if($courseOfflineOrderInfo['end_at']<$nowDate && $classStatus == 0 && $courseOfflineOrderInfo['class_status'] == 1){
            return ['code'=>ErrorCode::WARNING,'msg'=>"课程已结束，暂无法调整",'data'=>null];
        }
        $scanAt = $courseOfflineOrderInfo['end_at'];

        if($classStatus == 1){
            Db::connection('jkc_edu')->transaction(function () use($id,$memberId,$scanAt){
                $data = ['member_id'=>$memberId,'course_offline_order_id'=>$id];
                AsyncTask::query()->insert(['data'=>json_encode($data),'type'=>1,'scan_at'=>$scanAt]);
                CourseOfflineOrder::query()->where(['id'=>$id])->update(['class_status'=>1]);
            });
        }else if($classStatus == 0){
            CourseOfflineOrder::query()->where(['id'=>$id])->update(['class_status'=>0]);
        }
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>null];
    }

    /**
     * 添加课堂情景图片
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Throwable
     */
    public function addClassroomSituation(array $params): array
    {
        $courseOfflinePlanId = $params['id'];
        $imgs = $params['imgs'];
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');

        if(empty($imgs)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"参数不能为空",'data'=>null];
        }
        //会员信息
        $memberInfo = Member::query()->select(['mobile'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"登录异常",'data'=>null];
        }
        $memberInfo = $memberInfo->toArray();
        $mobile = $memberInfo['mobile'];

        //老师信息
        $teacherInfo = Teacher::query()->select(['id','salary_template_id','rank_level'])->where(['mobile'=>$mobile])->first();
        if(empty($teacherInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"身份信息不存在",'data'=>null];
        }
        $teacherInfo = $teacherInfo->toArray();
        $teacherId = $teacherInfo['id'];

        $courseOfflinePlanInfo = CourseOfflinePlan::query()
            ->select(['theme_type','classroom_situation'])
            ->where(['id'=>$courseOfflinePlanId,'teacher_id'=>$teacherId])
            ->first();
        if(empty($courseOfflinePlanInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"课程信息错误",'data'=>null];
        }
        $courseOfflinePlanInfo = $courseOfflinePlanInfo->toArray();
        $themeType = $courseOfflinePlanInfo['theme_type'];
        $classroomSituation = $courseOfflinePlanInfo['classroom_situation'];

        $insertCourseOfflineClassroomSituationData = [];
        foreach($imgs as $value){
            $courseOfflineClassroomSituationData = [];
            $courseOfflineClassroomSituationData['id'] = IdGenerator::generate();
            $courseOfflineClassroomSituationData['course_offline_plan_id'] = $courseOfflinePlanId;
            $courseOfflineClassroomSituationData['img_url'] = $value;
            $insertCourseOfflineClassroomSituationData[] = $courseOfflineClassroomSituationData;
        }
        //佣金比例
        $commissionRate = 0;
        if($teacherInfo['salary_template_id'] != 0 && $teacherInfo['rank_level'] != 0){
            $salaryTemplateLevelInfo = SalaryTemplateLevel::query()
                ->leftJoin('salary_template','salary_template_level.salary_template_id','=','salary_template.id')
                ->select(['salary_template_level.course_theme_type1','salary_template_level.course_theme_type2','salary_template_level.course_theme_type3'])
                ->where(['salary_template_level.salary_template_id'=>$teacherInfo['salary_template_id'],'salary_template_level.level'=>$teacherInfo['rank_level'],'salary_template.is_deleted'=>0])
                ->first();
            if(empty($salaryTemplateLevelInfo)){
                return ['code'=>ErrorCode::WARNING,'msg'=>"薪资信息设定异常",'data'=>null];
            }
            $salaryTemplateLevelInfo = $salaryTemplateLevelInfo->toArray();
            switch ($themeType){
                case 1:
                    $commissionRate = $salaryTemplateLevelInfo['course_theme_type1'];
                    break;
                case 2:
                    $commissionRate = $salaryTemplateLevelInfo['course_theme_type2'];
                    break;
                case 3:
                    $commissionRate = $salaryTemplateLevelInfo['course_theme_type3'];
                    break;
            }
        }

        Db::connection('jkc_edu')->transaction(function () use($courseOfflinePlanId,$insertCourseOfflineClassroomSituationData,$commissionRate,$nowDate,$classroomSituation){
            CourseOfflineClassroomSituation::query()->where(['course_offline_plan_id'=>$courseOfflinePlanId])->delete();
            CourseOfflineClassroomSituation::query()->insert($insertCourseOfflineClassroomSituationData);
            if($classroomSituation == 0){
                CourseOfflinePlan::query()->where(['id'=>$courseOfflinePlanId])->update(['classroom_situation'=>1]);
                CourseOfflineOrder::query()->where(['course_offline_plan_id'=>$courseOfflinePlanId])->update(['commission_rate'=>$commissionRate,'classroom_situation_feedback_at'=>$nowDate]);
            }
        });
        $this->eventDispatcher->dispatch(new CourseOfflineCompleteRegistered((int)$courseOfflinePlanId));
        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>null];
    }

    /**
     * 老师身份信息
     * @return array
     */
    public function teacherIdentityInfo(): array
    {
        $memberId = $this->memberId;

        //会员信息
        $memberInfo = Member::query()->select(['mobile','avatar'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"登录异常",'data'=>null];
        }
        $memberInfo = $memberInfo->toArray();
        $mobile = $memberInfo['mobile'];
        $avatar = $memberInfo['avatar'];
        if($avatar !== '' && str_contains($avatar, 'http') === false){
            $avatar = 'https://image.jkcspace.com/'.$avatar;
        }

        //老师信息
        $teacherInfo = Teacher::query()
            ->leftJoin('physical_store','teacher.physical_store_id','=','physical_store.id')
            ->select(['teacher.name as teacher_name','physical_store.name as physical_store_name'])
            ->where(['teacher.mobile'=>$mobile])
            ->first();
        if(empty($teacherInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"身份信息不存在",'data'=>null];
        }
        $teacherInfo = $teacherInfo->toArray();
        $teacherInfo['id'] = $memberId;
        $teacherInfo['avatar'] = $avatar;

        return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>$teacherInfo];
    }
}