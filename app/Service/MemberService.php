<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\LocationCache;
use App\Common\Image;
use App\Lib\WeChat\MiniProgramFactory;
use App\Logger\Log;
use App\Model\Member;
use App\Constants\ErrorCode;
use App\Model\MemberBelongTo;
use App\Model\MemberQcCode;
use App\Model\Teacher;
use App\Model\VipCardOrder;
use App\Model\VipCardOrderDynamicCourse;
use App\Snowflake\IdGenerator;
use Hyperf\Utils\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Guzzle\ClientFactory;
use Psr\EventDispatcher\EventDispatcherInterface;

class MemberService extends BaseService
{
    #[Inject]
    private ClientFactory $guzzleClientFactory;

    #[Inject]
    private EventDispatcherInterface $eventDispatcher;

    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 会员中心
     * @return array
     */
    public  function memberCenter(): array
    {
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        $memberInfo = Member::query()->select(['name','avatar'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '用户信息异常', 'data' => null];
        }
        $memberInfo = $memberInfo->toArray();

        //会员卡信息
        $vipCardOrderList = VipCardOrder::query()
            ->select(['id','course1','course1_used','course2','course2_used','course3','course3_used','expire_at','order_title','price'])
            ->where([['member_id','=',$memberId],['pay_status','=',1],['order_status','=',0]])
            ->whereIn('order_type',[1,4])
            ->orderBy('price','desc')
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $vipCardOrderIdArray = array_column($vipCardOrderList,'id');
        $vipCardOrderDynamicCourseList = VipCardOrderDynamicCourse::query()
            ->select(['vip_card_order_id','course','course_used','type'])
            ->whereIn('vip_card_order_id',$vipCardOrderIdArray)
            ->get();
        $vipCardOrderDynamicCourseList = $vipCardOrderDynamicCourseList->toArray();
        $vipCardOrderDynamicCourseList = $this->functions->arrayGroupBy($vipCardOrderDynamicCourseList,'vip_card_order_id');
        $expiredList = [];
        $notExpiredList = [];
        foreach($vipCardOrderList as $value){
            $vipCardOrderDynamicCourse = $vipCardOrderDynamicCourseList[$value['id']] ?? [];
            $value['dynamic_course'] = $vipCardOrderDynamicCourse;
            if($value['expire_at']>$nowDate){
                $notExpiredList[] = $value;
            }else{
                $expiredList[] = $value;
            }
        }

        //会员卡账户信息
        $totalCourse1 = 0;
        $totalCourse2 = 0;
        $totalCourse3 = 0;
        $totalDynamicCourse1 = 0;
        $totalDynamicCourse2 = 0;
        $totalDynamicCourse3 = 0;
        foreach($notExpiredList as $value){
            $vipCardOrderDynamicCourse = $vipCardOrderDynamicCourseList[$value['id']] ?? [];
            foreach($vipCardOrderDynamicCourse as $item){
                $surplusSectionDynamicCourse = $item['course']-$item['course_used'];
                if($item['type'] == 1){
                    $totalDynamicCourse1 += $surplusSectionDynamicCourse;
                }else if($item['type'] == 2){
                    $totalDynamicCourse2 += $surplusSectionDynamicCourse;
                }else if($item['type'] == 3){
                    $totalDynamicCourse3 += $surplusSectionDynamicCourse;
                }
            }
            $surplusSectionCourse1 = $value['course1']-$value['course1_used'];
            $surplusSectionCourse2 = $value['course2']-$value['course2_used'];
            $surplusSectionCourse3 = $value['course3']-$value['course3_used'];
            $totalCourse1 = $surplusSectionCourse1>0 ? $totalCourse1+$surplusSectionCourse1 : $totalCourse1;
            $totalCourse2 = $surplusSectionCourse2>0 ? $totalCourse2+$surplusSectionCourse2 : $totalCourse2;
            $totalCourse3 = $surplusSectionCourse3>0 ? $totalCourse3+$surplusSectionCourse3 : $totalCourse3;
        }
        $totalCourse1 += $totalDynamicCourse1;
        $totalCourse2 += $totalDynamicCourse2;
        $totalCourse3 += $totalDynamicCourse3;

        $vipName = '';
        $vipStatus = 0;
        if(!empty($notExpiredList) && $notExpiredList[0]['price']>0){
            $vipName = $notExpiredList[0]['order_title'];
            $vipStatus = 1;
        }else if(!empty($expiredList) && $expiredList[0]['price']>0){
            $vipName = $expiredList[0]['order_title'];
        }
        $memberInfo['course1'] = $totalCourse1;
        $memberInfo['course2'] = $totalCourse2;
        $memberInfo['course3'] = $totalCourse3;
        $memberInfo['vip_name'] = $vipName;
        $memberInfo['vip_status'] = $vipStatus;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $memberInfo];
    }

    /**
     * 设置用户资料
     * @param array $params
     * @return array
     */
    public function setMemberData(array $params): array
    {
        $avatar = $params['avatar'] ?? '';
        $name = $params['name'] ?? '';
        $gender = $params['gender'] ?? 0;
        $birthday = $params['birthday'] ?? '';
        $parentMobile = $params['parent_mobile'] ?? '';
        $school = $params['school'] ?? '';
        $channel = $params['channel'] ?? '';
        $memberId = $this->memberId;

        $age = 0;
        if($birthday !== ''){
            $age = strtotime($birthday);
            [$y1,$m1,$d1] = explode("-",date("Y-m-d",$age));
            $now = strtotime("now");
            [$y2,$m2,$d2] = explode("-",date("Y-m-d",$now));
            $age = $y2 - $y1;
            if((int)($m2.$d2) < (int)($m1.$d1)){
                $age -= 1;
            }
        }
        $updateMemberData = [
            'name' => $name,
            'gender' => $gender,
            'birthday' => $birthday,
            'parent_mobile' => $parentMobile,
            'age' => $age,
            'avatar' => $avatar,
            'school' => $school,
            'channel' => $channel,
        ];
        Member::query()->where(['id'=>$memberId])->update($updateMemberData);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 会员资料
     * @return array
     */
    public function getMemberData(): array
    {
        $memberId = $this->memberId;
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        $memberInfo = Member::query()
            ->select(['name','gender','birthday','parent_mobile','mobile','avatar','school','channel'])
            ->where(['id'=>$memberId])
            ->first();
        $memberInfo = $memberInfo->toArray();

        $identity = 1;
        if(!empty($memberInfo['mobile'])){
            $teacherInfo = Teacher::query()
                ->select(['id'])
                ->where(['mobile'=>$memberInfo['mobile'],'is_deleted'=>0])
                ->first();
            $identity = empty($teacherInfo) ? 1 : 2;
        }

        $memberInfo['identity'] = $identity;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $memberInfo];
    }

    /**
     * 会员信息
     * @return array
     */
    public  function memberInfo(): array
    {
        $memberId = $this->memberId;
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        $memberInfo = Member::query()->select(['name'])->where(['id'=>$memberId])->first();
        if(empty($memberInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '用户信息异常', 'data' => null];
        }
        $memberInfo = $memberInfo->toArray();
        $memberInfo['id'] = (string)$memberId;
        $memberInfo['entrant_reward_status'] = 1;
        $memberInfo['information_card_status'] = empty($memberInfo['name']) ? 0 : 1;

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $memberInfo];
    }

    /**
     * 用户会员卡账户
     * @return array
     */
    public function memberVipCard(): array
    {
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        //会员卡信息
        $vipCardOrderList = VipCardOrder::query()
            ->select(['id','course1','course1_used','course2','course2_used','course3','course3_used'])
            ->where([['member_id','=',$memberId],['pay_status','=',1],['order_status','=',0],['expire_at','>',$nowDate]])
            ->whereIn('order_type',[1,4])
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $vipCardOrderIdArray = array_column($vipCardOrderList,'id');
        $vipCardOrderDynamicCourseList = VipCardOrderDynamicCourse::query()
            ->select(['vip_card_order_id','course','course_used','type'])
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
        foreach($vipCardOrderList as $value){
            $vipCardOrderDynamicCourse = $vipCardOrderDynamicCourseList[$value['id']] ?? [];
            foreach($vipCardOrderDynamicCourse as $item){
                $surplusSectionDynamicCourse = $item['course']-$item['course_used'];
                if($item['type'] == 1){
                    $totalDynamicCourse1 += $surplusSectionDynamicCourse;
                }else if($item['type'] == 2){
                    $totalDynamicCourse2 += $surplusSectionDynamicCourse;
                }else if($item['type'] == 3){
                    $totalDynamicCourse3 += $surplusSectionDynamicCourse;
                }
            }
            $surplusSectionCourse1 = $value['course1']-$value['course1_used'];
            $surplusSectionCourse2 = $value['course2']-$value['course2_used'];
            $surplusSectionCourse3 = $value['course3']-$value['course3_used'];
            $totalCourse1 = $surplusSectionCourse1>0 ? $totalCourse1+$surplusSectionCourse1 : $totalCourse1;
            $totalCourse2 = $surplusSectionCourse2>0 ? $totalCourse2+$surplusSectionCourse2 : $totalCourse2;
            $totalCourse3 = $surplusSectionCourse3>0 ? $totalCourse3+$surplusSectionCourse3 : $totalCourse3;
        }
        $totalCourse1 += $totalDynamicCourse1;
        $totalCourse2 += $totalDynamicCourse2;
        $totalCourse3 += $totalDynamicCourse3;
        $returnData = [
            'course1' => $totalCourse1,
            'course2' => $totalCourse2,
            'course3' => $totalCourse3
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 用户体验卡账户
     * @return array
     */
    public function memberSampleVipCard(): array
    {
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');
        $returnData = ['currency_course' => 0];
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
        }

        //会员卡信息
        $vipCardOrderList = VipCardOrder::query()
            ->select(['id','currency_course','currency_course_used'])
            ->where([['member_id','=',$memberId],['expire_at','>',$nowDate],['pay_status','=',1],['order_status','=',0]])->whereIn('order_type',[2,3])
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        //体验卡账户信息
        $totalCurrencyCourse = 0;
        foreach($vipCardOrderList as $value){
            $surplusSectionCurrencyCourse = $value['currency_course']-$value['currency_course_used'];
            $totalCurrencyCourse = $surplusSectionCurrencyCourse>0 ? $totalCurrencyCourse+$surplusSectionCurrencyCourse : $totalCurrencyCourse;
        }
        $returnData = [
            'currency_course' => $totalCurrencyCourse
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 会员位置
     * @param array $params
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \RedisException
     */
    public function memberLocation(array $params): array
    {
        $memberLatitude = $params['latitude'];
        $memberLongitude = $params['longitude'];
        $cacheKey = "{$memberLatitude}:{$memberLongitude}";

        $province = '';
        $city = '';
        $district = '';
        $locationCache = new LocationCache();
        $location = $locationCache->getLocation($cacheKey);
        if(!empty($location)){
            [$province,$city,$district] = explode(',',$location);
        }else{
            $client = $this->guzzleClientFactory->create();
            $url = "https://apis.map.qq.com/ws/geocoder/v1/?location={$memberLatitude},{$memberLongitude}&key=ZVEBZ-YULLK-VTWJK-ANWGB-NAJU2-PZFPC";
            $response = $client->request('GET', $url);
            $r = $response->getBody()->getContents();
            $data = json_decode($r,true);
            if($data['status'] == 0){
                $addressComponent = $data['result']['address_component'];
                $province = $addressComponent['province'];
                $city = $addressComponent['city'];
                $district = $addressComponent['district'];
                $location = "{$province},{$city},{$district}";
                $locationCache->setLocation($cacheKey,$location);
            }
        }
        $returnData = [
            'province_name' => $province,
            'city_name' => $city,
            'district_name' => $district,
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 会员小程序码
     * @param array $params
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function qRCode(array $params): array
    {
        $type = $params['type'] ?? 1;
        $memberId = $this->memberId;
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }
        $memberQcCodeInfo = MemberQcCode::query()
            ->select(['qc_code'])
            ->where(['member_id'=>$memberId,'type'=>$type])
            ->first();
        $memberQcCodeInfo = $memberQcCodeInfo?->toArray();

        $memberInfo = Member::query()
            ->select(['avatar','invite_code'])
            ->where(['id'=>$memberId])
            ->first();
        if(empty($memberInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '登录信息异常', 'data' => null];
        }
        $memberInfo = $memberInfo->toArray();
        $memberInviteCode = $memberInfo['invite_code'];

        if(empty($memberQcCodeInfo['qc_code'])){
            if(empty($memberInviteCode)){
                return ['code' => ErrorCode::WARNING, 'msg' => '分享码获取失败', 'data' => null];
            }
            $image = new Image();
            if($type == 1){
                $page = 'pages/index/index';
                $scene = "m={$memberInviteCode}&t=3";
            }else{
                $page = 'pages/ground/index';
                $scene = "m={$memberInviteCode}&t=1";
            }

            $miniProgramFactory = new MiniProgramFactory();
            $qRCode = $miniProgramFactory->getUnlimitedQRCode($scene,$page);
            if(str_contains($qRCode, 'errcode')){
                Log::get()->info('qRCodeGoods:'.$qRCode);
                return ['code' => ErrorCode::WARNING, 'msg' => '小程序码获取失败', 'data' => null];
            }
            //用户头像图片变圆形
            $avatar = file_get_contents('https://jkc-1313504415.cos.ap-shanghai.myqcloud.com/'.$memberInfo['avatar']);
            if($avatar === false){
                $avatar = file_get_contents('https://jkc-1313504415.cos.ap-shanghai.myqcloud.com/wxmini_static/images/logo.png');
            }
            $logo = $image->toCircleImage($avatar);
            //二维码与头像结合
            $sharePic = $image->qrcodeWithLogo($qRCode,$logo);
            $randStr = intval(microtime(true) * 1000).'-'.$memberId;
            $randStr = md5($randStr);
            $path = "/tmp/{$randStr}.png";
            $path2 = "/tmp/{$randStr}-2.png";
            file_put_contents($path, $sharePic);
            $image->toHyalineImage($path,$path2);

            $file['tmp_file'] = $path2;
            $uploadService = new UploadService();
            $uploadService->setBucketDir('short_term');
            $result = $uploadService->cosUpload($file,'png');
            $qcCode = $result['data']['key'];
            unlink($path);
            unlink($path2);
            $insertMemberQcCodeData = ['member_id'=>$memberId,'qc_code'=>$qcCode,'type'=>$type];
            go(function ()use($insertMemberQcCodeData){
                MemberQcCode::query()->insert($insertMemberQcCodeData);
            });
        }else{
            $qcCode = $memberQcCodeInfo['qc_code'];
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['qc_code'=>$qcCode]];
    }

    /**
     * 绑定上级
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function bindSuperior(array $params): array
    {
        $parentId = $params['id'];
        $type = $params['type'] ?? 1;
        $memberId = $this->memberId;

        go(function ()use($parentId,$memberId,$type){
            if(strlen($parentId) === 6){
                $parentMemberInfo = Member::query()
                    ->select(['id'])
                    ->where(['invite_code'=>$parentId])
                    ->first();
                if(!empty($parentMemberInfo)){
                    $parentMemberInfo = $parentMemberInfo->toArray();
                    $parentId = $parentMemberInfo['id'];
                }
            }
            if(empty($parentId) || empty($memberId) || $parentId == $memberId){
                return;
            }
            switch ($type){
                case 1:
                    $memberInfo = Member::query()
                        ->select(['parent_id','mobile'])
                        ->where('id', $memberId)
                        ->first();
                    $memberInfo = $memberInfo->toArray();
                    if($memberInfo['parent_id'] == 0){
                        Member::where('id', $memberId)->update(['parent_id'=>$parentId]);
                    }
                    break;
                case 3:
                    $memberBelongToExists = MemberBelongTo::query()->where(['id'=>$memberId])->exists();
                    if($memberBelongToExists === true){
                        return;
                    }
                    $teacherId = 0;
                    $physicalStoreId = 0;
                    $recommendMemberInfo = Member::query()->select(['mobile'])->where(['id'=>$parentId])->first();
                    $recommendMemberInfo = $recommendMemberInfo?->toArray();
                    $recommendMemberMobile = $recommendMemberInfo['mobile'] ?? null;
                    if($recommendMemberMobile !== null){
                        //推荐老师信息
                        $recommendTeacherInfo = Teacher::query()->select(['id','physical_store_id'])->where(['mobile'=>$recommendMemberMobile,'is_deleted'=>0])->first();
                        $recommendTeacherInfo = $recommendTeacherInfo?->toArray();
                        $teacherId = $recommendTeacherInfo['id'] ?? 0;
                        $physicalStoreId = $recommendTeacherInfo['physical_store_id'] ?? 0;
                    }
                    if($teacherId !== 0){
                        $insertMemberBelongToData['id'] = IdGenerator::generate();
                        $insertMemberBelongToData['member_id'] = $memberId;
                        $insertMemberBelongToData['physical_store_id'] = $physicalStoreId;
                        $insertMemberBelongToData['teacher_id'] = $teacherId;
                        $insertMemberBelongToData['teacher_member_id'] = $parentId;
                        MemberBelongTo::query()->insert($insertMemberBelongToData);
                    }
                    break;
            }
        });
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

}