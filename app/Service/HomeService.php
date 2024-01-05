<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\CourseOnline;
use App\Model\Member;
use App\Model\HomeAd;
use App\Model\HomeBoutiqueCourse;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class HomeService extends BaseService
{

    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 首页 banner
     * @return array
     */
    public function banner(): array
    {
        $homeAdList = HomeAd::query()->select(['img_url','link'])->get();
        $homeAdList = $homeAdList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $homeAdList];
    }

    /**
     * 首页推荐课程
     * @return array
     */
    public function boutiqueCourse(): array
    {
        $date = date('Y-m-d H:i:s');
        $offset = $this->offset;
        $limit = $this->limit;

        $homeBoutiqueCourseList = HomeBoutiqueCourse::query()
            ->leftJoin('course_online_child', 'home_boutique_course.course_online_child_id', '=', 'course_online_child.id')
            ->select(['course_online_child.id','course_online_child.course_online_id','course_online_child.name','course_online_child.img_url'])
            ->where([['home_boutique_course.start_at','<=',$date],['home_boutique_course.end_at','>',$date]])
            ->offset($offset)->limit($limit)
            ->get();
        $homeBoutiqueCourseList = $homeBoutiqueCourseList->toArray();
        $count = HomeBoutiqueCourse::query()->where([['start_at','<=',$date],['end_at','>',$date]])->count('id');

        //线上课程信息
        $courseOnlineIdArray = array_column($homeBoutiqueCourseList,'course_online_id');
        $courseOnlineList = CourseOnline::query()
            ->select(['id','member_id','author','type','suit_age_min','suit_age_max'])
            ->whereIn('id',$courseOnlineIdArray)
            ->get();
        $courseOnlineList = $courseOnlineList->toArray();
        $courseMemberIdArray = array_column($courseOnlineList,'member_id');
        $combineCourseOnlineKey = array_column($courseOnlineList,'id');
        $courseOnlineList = array_combine($combineCourseOnlineKey,$courseOnlineList);

        //课程作者信息
        $courseMemberIdArray = array_filter($courseMemberIdArray,function($value){
            if($value != 0){
                return true;
            }
            return false;
        });
        $courseMemberIdArray = array_values($courseMemberIdArray);
        if(!empty($courseMemberIdArray)){
            $memberList = Member::query()->select(['id','avatar'])->whereIn('id',$courseMemberIdArray)->get();
            $memberList = $memberList->toArray();
            $combineMemberKey = array_column($memberList,'id');
            $memberList = array_combine($combineMemberKey,$memberList);
        }
        foreach($homeBoutiqueCourseList as $key=>$value){
            $courseOnlineId = $value['course_online_id'];
            unset($homeBoutiqueCourseList[$key]['course_online_id']);
            if(!isset($courseOnlineList[$courseOnlineId])){
                unset($homeBoutiqueCourseList[$key]);
            }
            $courseOnlineInfo = $courseOnlineList[$courseOnlineId];
            $courseOnlineMemberId = $courseOnlineInfo['member_id'];

            $homeBoutiqueCourseList[$key]['avatar'] = "";
            $homeBoutiqueCourseList[$key]['author'] = $courseOnlineInfo['author'];
            $homeBoutiqueCourseList[$key]['type'] = $courseOnlineInfo['type'];
            $homeBoutiqueCourseList[$key]['suit_age_min'] = $courseOnlineInfo['suit_age_min'];
            $homeBoutiqueCourseList[$key]['suit_age_max'] = $courseOnlineInfo['suit_age_max'];
            if($courseOnlineInfo['type'] == 2 && isset($memberList[$courseOnlineMemberId])){
                $homeBoutiqueCourseList[$key]['avatar'] = $memberList[$courseOnlineMemberId]['avatar'];
            }
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$homeBoutiqueCourseList,'count'=>$count]];
    }
}