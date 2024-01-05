<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\StoreManagerIdentityCache;
use App\Event\MemberRegisterRegistered;
use App\Logger\Log;
use App\Model\MemberRegisterCoordinate;
use App\Model\PhysicalStoreAdmins;
use App\Model\PhysicalStoreAdminsPhysicalStore;
use App\Model\Teacher;
use App\Token\Jwt;
use App\Snowflake\IdGenerator;
use App\Cache\MemberCache;
use App\Constants\ErrorCode;
use App\Model\Member;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Context;
use Psr\EventDispatcher\EventDispatcherInterface;
use Wechat\OAuth\OAuth2;

class AuthService extends BaseService
{
    #[Inject]
    private EventDispatcherInterface $eventDispatcher;

    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 微信小程序会话session
     * @param array $params
     * @return array
     * @throws \RedisException
     * @throws \Wechat\OAuth\Exception\ApiException
     */
    public function wxMiniProgramSession(array $params): array
    {
        $code = $params['code'];

        $miniProgramConfig = json_decode(env('MINIPROGRAM'), true);
        $oauth2 = new OAuth2($miniProgramConfig['appId'],$miniProgramConfig['appSecret']);
        $oauth2->getSessionKey($code);
        $accessResult = $oauth2->getAccessResult();
        $memberCache = new MemberCache();
        $memberCache->setWxMiniAccess($code,$accessResult);

        $returnData = ['code'=>$code];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 微信小程序手机号登录
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function wxMiniProgramMobile(array $params): array
    {
        try{
            $code = $params['code'];
            $encryptedData = urldecode($params['encrypted_data']);
            $iv = $params['iv'];
            $memberLongitude = $params['longitude'] ?? 0;
            $memberLatitude = $params['latitude'] ?? 0;
            $date = date('Y-m-d H:i:s');
            if($encryptedData === 'undefined'){
                return ['code' => ErrorCode::WARNING, 'msg' => '登录失败', 'data' => null];
            }

            $miniProgramConfig = json_decode(env('MINIPROGRAM'), true);
            $oauth2 = new OAuth2($miniProgramConfig['appId'],$miniProgramConfig['appSecret']);
            $memberCache = new MemberCache();
            $wxSessionKey = $memberCache->getWxMiniAccess($code,'session_key');
            $openid = $memberCache->getWxMiniAccess($code,'openid');
            $decryptedData = $oauth2->decryptedData($encryptedData,$iv,$wxSessionKey);
            $mobile = $decryptedData['phoneNumber'];
            if(empty($mobile)){
                return ['code' => ErrorCode::WARNING, 'msg' => '手机号获取失败请重试', 'data' => null];
            }
            //老账户
            $oldMemberExists = Member::query()->where(['mini_openid'=>$openid,'mobile'=>''])->exists();
            if($oldMemberExists === true){
                $mobileExists = Member::query()->where(['mobile'=>$mobile])->exists();
                if($mobileExists === false){
                    Member::where(['mini_openid'=>$openid,'mobile'=>''])->update(['mobile'=>$mobile]);
                }else{
                    Log::get('老账户合并异常:'.$openid.'#'.$mobile);
                    return ['code' => ErrorCode::WARNING, 'msg' => '手机号已绑定其它微信账号', 'data' => null];
                }
            }

            $memberInfo = Member::query()
                ->select(['id','register_type'])
                ->where(['mobile'=>$mobile])
                ->first();
            $memberInfo = $memberInfo?->toArray();
            if($memberInfo === null){
                $inviteCode = $this->functions->randomCode();
                $memberExists = Member::query()->where(['invite_code'=>$inviteCode])->exists();
                if($memberExists === true){
                    return ['code' => ErrorCode::WARNING, 'msg' => '登录异常请重试', 'data' => null];
                }
                $memberId = IdGenerator::generate();
                $insertMemberData = [
                    'id' => $memberId,
                    'mobile' => $mobile,
                    'mini_openid' => $openid,
                    'last_login_at' => $date,
                    'invite_code' => $inviteCode
                ];
                Member::insert($insertMemberData);
                $insertMemberRegisterCoordinateData['id'] = IdGenerator::generate();
                $insertMemberRegisterCoordinateData['member_id'] = $memberId;
                $insertMemberRegisterCoordinateData['longitude'] = $memberLongitude;
                $insertMemberRegisterCoordinateData['latitude'] = $memberLatitude;
                MemberRegisterCoordinate::query()->insert($insertMemberRegisterCoordinateData);
                $this->eventDispatcher->dispatch(new MemberRegisterRegistered($memberId));
            }else{
                $memberId = (int)$memberInfo['id'];
                $updateData = ['last_login_at'=>$date];
                if($memberInfo['register_type'] == 2){
                    $updateData['register_type'] = 3;
                    $updateData['mini_openid'] = $openid;
                }
                go(function ()use($updateData,$memberId){
                    Member::where('id', $memberId)->update($updateData);
                });
            }
            $identity = 1;
            $teacherExists = Teacher::query()->where(['mobile'=>$mobile,'is_deleted'=>0])->exists();
            $physicalStoreAdminsInfo = PhysicalStoreAdmins::query()->select(['id'])->where(['mobile'=>$mobile,'is_store_manager'=>1,'is_deleted'=>0])->first();
            $physicalStoreAdminsInfo = $physicalStoreAdminsInfo?->toArray();
            if($physicalStoreAdminsInfo !== null){
                $physicalStoreAdminsPhysicalStoreInfo = PhysicalStoreAdminsPhysicalStore::query()
                    ->select(['physical_store_id'])
                    ->where(['physical_store_admins_id'=>$physicalStoreAdminsInfo['id']])
                    ->first();
                $physicalStoreAdminsPhysicalStoreInfo = $physicalStoreAdminsPhysicalStoreInfo->toArray();
                $storeManagerIdentityCache = new StoreManagerIdentityCache();
                $storeManagerIdentityCache->setPhysicalStoreId((int)$physicalStoreAdminsPhysicalStoreInfo['physical_store_id'],$memberId);
                $identity = $teacherExists === false ? 2 : 3;
            }

            // 生成token
            $tokenParams = ['memberId' => $memberId];
            $jwt = new Jwt();
            $jwt->expire = 90*24*3600;
            $token = $jwt->getToken($tokenParams);
            $md5Token = md5($token);
            $memberCache->setAuthTokenWxMini($memberId,$md5Token);

            $returnData = ['token'=>$token,'identity'=>$identity,'id'=>(string)$memberId];
        } catch(\Throwable $e){
            $errMsg = $e->getMessage();
            if($errMsg === '反序列化数据失败'){
                return ['code' => ErrorCode::WARNING, 'msg' => '网络异常请重试', 'data' => null];
            }
            throw new \Exception($errMsg, 1);
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

}

