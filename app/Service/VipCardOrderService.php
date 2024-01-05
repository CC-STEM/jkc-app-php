<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\VipCardConstant;
use App\Logger\Log;
use App\Model\Coupon;
use App\Model\CouponPhysicalStore;
use App\Model\DiscountTicket;
use App\Model\DiscountTicketPhysicalStore;
use App\Model\DiscountTicketVipCard;
use App\Model\Member;
use App\Model\MemberBelongTo;
use App\Model\VipCard;
use App\Model\VipCardBlacklist;
use App\Model\VipCardDynamicCourse;
use App\Model\VipCardOrder;
use App\Constants\ErrorCode;
use App\Constants\PayConstant;
use App\Model\VipCardOrderDynamicCourse;
use App\Model\VipCardOrderPhysicalStore;
use App\Model\VipCardPhysicalStore;
use App\Snowflake\IdGenerator;
use App\Lib\WeChat\WeChatPayFactory;
use Hyperf\Utils\Context;
use Hyperf\DbConnection\Db;

class VipCardOrderService extends BaseService
{
    /**
     * VipCardOrderService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 会员卡订单结算
     * @param array $params
     * @return array
     */
    public function vipCardOrderConfirm(array $params): array
    {
        $vipCardId = $params['id'];
        $physicalStoreId = $params['physical_store_id'];
        //0:默认，-1:不使用
        $couponId = $params['coupon_id'] ?? 0;
        //null:默认，-1:不使用
        $discountTicket = $params['discount_ticket'];
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');

        $vipCardInfo = VipCard::query()
            ->select(['price','max_deduction_price','theme_type'])
            ->where(['id'=>$vipCardId,'is_deleted'=>0])
            ->first();
        $vipCardInfo = $vipCardInfo->toArray();
        $vipCardAmount = $vipCardInfo['price'];
        $maxDeductionPrice = $vipCardInfo['max_deduction_price'];
        $themeType = $vipCardInfo['theme_type'];

        $discountTicketList = [];
        if($memberId != 0){
            //优惠券
            $couponList = Coupon::query()
                ->select(['id','name','threshold_amount','amount','end_at','applicable_theme_type'])
                ->where([['member_id','=',$memberId],['is_used','=',0],['end_at','>',$nowDate]])
                ->get();
            $couponList = $couponList->toArray();
            //抵扣票
            if($maxDeductionPrice>0){
                $discountTicketList = DiscountTicket::query()
                    ->leftJoin('discount_ticket_vip_card','discount_ticket.id','=','discount_ticket_vip_card.discount_ticket_id')
                    ->select(['discount_ticket.id','discount_ticket.name','discount_ticket.amount','discount_ticket.end_at','discount_ticket.source_type'])
                    ->where([['discount_ticket.member_id','=',$memberId],['discount_ticket.status','=',0],['discount_ticket.end_at','>',$nowDate],['discount_ticket_vip_card.vip_card_id','=',$vipCardId]])
                    ->get();
                $discountTicketList = $discountTicketList->toArray();
            }
        }
        $discountTicketAmount = '0';
        $discountTicketExcessReminder = '';
        if(!empty($discountTicketList)){
            $discountTicketIdArray = array_column($discountTicketList,'id');
            $discountTicketPhysicalStoreList = DiscountTicketPhysicalStore::query()
                ->leftJoin('physical_store','discount_ticket_physical_store.physical_store_id','=','physical_store.id')
                ->select(['discount_ticket_physical_store.discount_ticket_id','physical_store.name'])
                ->whereIn('discount_ticket_physical_store.discount_ticket_id',$discountTicketIdArray)
                ->get();
            $discountTicketPhysicalStoreList = $discountTicketPhysicalStoreList->toArray();
            $discountTicketPhysicalStoreList = $this->functions->arrayGroupBy($discountTicketPhysicalStoreList,'discount_ticket_id');
            $discountTicketVipCardList = DiscountTicketVipCard::query()
                ->leftJoin('vip_card','discount_ticket_vip_card.vip_card_id','=','vip_card.id')
                ->select(['discount_ticket_vip_card.discount_ticket_id','vip_card.name'])
                ->whereIn('discount_ticket_vip_card.discount_ticket_id',$discountTicketIdArray)
                ->get();
            $discountTicketVipCardList = $discountTicketVipCardList->toArray();
            $discountTicketVipCardList = $this->functions->arrayGroupBy($discountTicketVipCardList,'discount_ticket_id');

            $sourceName = ['平台购送减免券','推荐好友减免券','自购奖励减免券'];
            foreach($discountTicketList as $key=>$value){
                $physicalStore = '';
                $vipCard = '';
                if(isset($discountTicketPhysicalStoreList[$value['id']])){
                    $physicalStore = implode('、',array_column($discountTicketPhysicalStoreList[$value['id']],'name'));
                }
                if(isset($discountTicketVipCardList[$value['id']])){
                    $vipCard = implode('、',array_column($discountTicketVipCardList[$value['id']],'name'));
                }
                $discountTicketList[$key]['selected'] = 0;
                $discountTicketList[$key]['physical_store'] = $physicalStore;
                $discountTicketList[$key]['vip_card'] = $vipCard;
                $discountTicketList[$key]['end_at'] = date('Y.m.d',strtotime($value['end_at']));
                $discountTicketList[$key]['name'] = $sourceName[$value['source_type']];
                if($discountTicket == -1 || $discountTicketAmount>=$maxDeductionPrice){
                    if(is_array($discountTicket) && in_array($value['id'],$discountTicket)){
                        $discountTicketExcessReminder = '不可勾选超过最大减免金额';
                    }
                    continue;
                }
                if($discountTicket === [] || in_array($value['id'],$discountTicket)){
                    $discountTicketAmount = bcadd($discountTicketAmount,$value['amount'],2);
                    $discountTicketList[$key]['selected'] = 1;
                }
            }
            $discountTicketAmount = min($discountTicketAmount, $maxDeductionPrice);
        }
        $vipCardAmount = bcsub((string)$vipCardAmount,(string)$discountTicketAmount,2);
        $vipCardAmount = max($vipCardAmount, 0);

        $couponAmount = '0';
        $selectedCoupon = [];
        $usableCouponList = [];
        if(!empty($couponList)){
            $couponIdArray = array_column($couponList,'id');
            $couponPhysicalStoreList = CouponPhysicalStore::query()
                ->select(['coupon_id','physical_store_id'])
                ->whereIn('coupon_id',$couponIdArray)
                ->get();
            $couponPhysicalStoreList = $couponPhysicalStoreList->toArray();
            $couponPhysicalStoreList = $this->functions->arrayGroupBy($couponPhysicalStoreList,'coupon_id');

            foreach($couponList as $value){
                $value['threshold_amount'] = (float)$value['threshold_amount'];
                $value['amount'] = (float)$value['amount'];
                if($vipCardAmount < $value['threshold_amount']){
                    continue;
                }
                if(isset($couponPhysicalStoreList[$value['id']]) && !in_array($physicalStoreId,array_column($couponPhysicalStoreList[$value['id']],'physical_store_id'))){
                    continue;
                }
                if($value['applicable_theme_type'] != 0 && $value['applicable_theme_type'] != $themeType){
                    continue;
                }
                if($couponId == $value['id']){
                    $selectedCoupon = $value;
                    array_unshift($usableCouponList,$value);
                    continue;
                }
                $usableCouponList[] = $value;
            }
            unset($couponList);
        }
        if(!empty($usableCouponList)){
            if($couponId == 0){
                array_multisort(array_column($usableCouponList, 'amount'), SORT_DESC, $usableCouponList);
                $selectedCoupon = $usableCouponList[0];
            }
            if($couponId != -1 && $vipCardAmount>0){
                $couponAmount = $usableCouponList[0]['amount'];
            }
        }
        $selectedCoupon = $vipCardAmount>0 ? $selectedCoupon : [];
        $vipCardAmount = bcsub((string)$vipCardAmount,(string)$couponAmount,2);
        $vipCardAmount = max($vipCardAmount, 0);

        $returnData = [
            'amount' => $vipCardAmount,
            'coupon_list' => $usableCouponList,
            'selected_coupon' => $selectedCoupon,
            'discount_ticket_list' => $discountTicketList,
            'discount_ticket_amount' => $discountTicketAmount,
            'discount_ticket_max_deduction' => $maxDeductionPrice,
            'discount_ticket_is_usable' => $maxDeductionPrice == 0 ? 0 : 1
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>$discountTicketExcessReminder,'data'=>$returnData];
    }

    /**
     * 创建会员卡订单
     * @param array $params
     * @return array
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function vipCardOrderCreate(array $params): array
    {
        $vipCardId = $params['id'];
        $recommendCode = $params['recommend_code'] ?? '';
        $physicalStoreId = $params['physical_store_id'] ?? 0;
        $couponId = $params['coupon_id'];
        $paymentType = $params['payment_type'] ?? 1;
        $discountTicket = $params['discount_ticket'];
        $memberId = $this->memberId;
        $nowTime = date('Y-m-d H:i:s');
        $payCode = $paymentType == 1 ? 'WXPAY' : 'ALIPAY';

        $memberInfo = Member::query()
            ->select(['mobile'])
            ->where(['id'=>$memberId])
            ->first();
        $memberInfo = $memberInfo->toArray();
        $vipCardBlacklistExists = VipCardBlacklist::query()->where(['vip_card_id'=>$vipCardId,'mobile'=>$memberInfo['mobile']])->exists();
        if($vipCardBlacklistExists === true){
            return ['code'=>ErrorCode::WARNING,'msg'=>"很抱歉，该新人礼包您已购买达到上限！",'data'=>null];
        }
        $vipCardInfo = VipCard::query()
            ->select(['name','price','expire','rule','grade','type','applicable_store_type','apiece_quota','start_at','end_at','theme_type','commission_rate','max_deduction_price'])
            ->where(['id'=>$vipCardId,'is_deleted'=>0])
            ->first();
        if(empty($vipCardInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"信息错误",'data'=>null];
        }
        $vipCardInfo = $vipCardInfo->toArray();
        if($vipCardInfo['start_at']>$nowTime || $vipCardInfo['end_at']<$nowTime){
            return ['code'=>ErrorCode::WARNING,'msg'=>"该会员卡暂时无法购买",'data'=>null];
        }
        $cardType = $vipCardInfo['type'];
        $orderType = 1;
        if($vipCardInfo['type'] == 3){
            $apieceQuotaCount = VipCardOrder::query()->where(['member_id'=>$memberId,'pay_status'=>1,'order_status'=>0,'vip_card_id'=>$vipCardId])->count();
            if($vipCardInfo['apiece_quota']<=$apieceQuotaCount){
                return ['code'=>ErrorCode::WARNING,'msg'=>"该新人礼包超过可购买次数",'data'=>null];
            }
            $cardType = 0;
            $orderType = 2;
        }
        $rule = json_decode($vipCardInfo['rule'],true);
        $vipCardDynamicCourseList = VipCardDynamicCourse::query()
            ->select(['name','course','type','week'])
            ->where(['vip_card_id'=>$vipCardId])
            ->get();
        $vipCardDynamicCourseList = $vipCardDynamicCourseList->toArray();

        //订单总金额
        $orderTotalAmount = $vipCardInfo['price'];
        //抵扣票
        $discountTicketAmount = '0';
        if(!empty($discountTicket) && $vipCardInfo['max_deduction_price']>0){
            $discountTicketList = DiscountTicket::query()
                ->leftJoin('discount_ticket_vip_card','discount_ticket.id','=','discount_ticket_vip_card.discount_ticket_id')
                ->select(['discount_ticket.id','discount_ticket.amount'])
                ->where([['discount_ticket.member_id','=',$memberId],['discount_ticket.status','=',0],['discount_ticket.end_at','>',$nowTime],['discount_ticket_vip_card.vip_card_id','=',$vipCardId]])
                ->whereIn('discount_ticket.id',$discountTicket)
                ->get();
            $discountTicketList = $discountTicketList->toArray();
            if(count($discountTicket) !== count($discountTicketList)){
                return ['code'=>ErrorCode::WARNING,'msg'=>"减免券使用失败",'data'=>null];
            }
            $discountTicketIdArray = array_column($discountTicketList,'id');
            $discountTicketAmount = array_sum(array_column($discountTicketList,'amount'));
            $discountTicketAmount = min($vipCardInfo['max_deduction_price'],$discountTicketAmount);
        }
        //优惠券
        $couponAmount = '0';
        if(!empty($couponId)){
            $couponInfo = Coupon::query()
                ->select(['id','threshold_amount','amount','applicable_theme_type'])
                ->where([['id','=',$couponId],['member_id','=',$memberId],['is_used','=',0],['end_at','>',$nowTime]])
                ->first();
            if(empty($couponInfo)){
                return ['code'=>ErrorCode::WARNING,'msg'=>"优惠券不可用",'data'=>null];
            }
            $couponInfo = $couponInfo->toArray();
            if($orderTotalAmount<$couponInfo['threshold_amount']){
                return ['code'=>ErrorCode::WARNING,'msg'=>"优惠券不满足使用条件",'data'=>null];
            }
            $couponPhysicalStoreList = CouponPhysicalStore::query()
                ->select(['physical_store_id'])
                ->where(['coupon_id'=>$couponInfo['id']])
                ->get();
            $couponPhysicalStoreList = $couponPhysicalStoreList->toArray();
            if(!empty($couponPhysicalStoreList) && !in_array($physicalStoreId,array_column($couponPhysicalStoreList,'physical_store_id'))){
                return ['code'=>ErrorCode::WARNING,'msg'=>"优惠券该门店不可用",'data'=>null];
            }
            if($couponInfo['applicable_theme_type'] != 0 && $couponInfo['applicable_theme_type'] != $vipCardInfo['theme_type']){
                return ['code'=>ErrorCode::WARNING,'msg'=>"优惠券该班级不可用",'data'=>null];
            }
            $couponAmount = $couponInfo['amount'];
        }
        $orderTotalAmount = bcsub((string)$orderTotalAmount,(string)$couponAmount,2);
        $orderTotalAmount = bcsub($orderTotalAmount,(string)$discountTicketAmount,2);
        $orderTotalAmount = (string)max($orderTotalAmount, 0);
        $payCode = $orderTotalAmount == 0 ? 'ZERO' : $payCode;
        //课单价
        $totalCourse = $rule['course1']+$rule['course2']+$rule['course3']+$rule['currency_course'];
        $courseUnitPrice = $totalCourse>0 ? bcdiv($orderTotalAmount,(string)$totalCourse,2) : '0';
        //推荐人信息
        $recommendTeacherId = 0;
        $recommendPhysicalStoreId = 0;
        $memberBelongToInfo = MemberBelongTo::query()
            ->select(['teacher_id','physical_store_id'])
            ->where([['member_id','=',$memberId]])
            ->first();
        $memberBelongToInfo = $memberBelongToInfo?->toArray();
        if(!empty($memberBelongToInfo)){
            $recommendTeacherId = $memberBelongToInfo['teacher_id'];
            $recommendPhysicalStoreId = $memberBelongToInfo['physical_store_id'];
        }

        $orderId = IdGenerator::generate();
        $insertVipCardOrderPhysicalStoreData = [];
        if($vipCardInfo['applicable_store_type'] == 2){
            $vipCardPhysicalStoreList = VipCardPhysicalStore::query()
                ->select(['physical_store_id'])
                ->where(['vip_card_id'=>$vipCardId])
                ->get();
            $vipCardPhysicalStoreList = $vipCardPhysicalStoreList->toArray();
            foreach($vipCardPhysicalStoreList as $value){
                $vipCardOrderPhysicalStoreData = [];
                $vipCardOrderPhysicalStoreData['id'] = IdGenerator::generate();
                $vipCardOrderPhysicalStoreData['vip_card_order_id'] = $orderId;
                $vipCardOrderPhysicalStoreData['physical_store_id'] = $value['physical_store_id'];
                $insertVipCardOrderPhysicalStoreData[] = $vipCardOrderPhysicalStoreData;
            }
        }
        //优惠信息数据
        $insertVipCardOrderOfferInfoData = [];
        if(!empty($couponInfo)){
            $vipCardOrderOfferInfoData['id'] = IdGenerator::generate();
            $vipCardOrderOfferInfoData['vip_card_order_id'] = $orderId;
            $vipCardOrderOfferInfoData['offer_info_id'] = $couponInfo['id'];
            $vipCardOrderOfferInfoData['amount'] = $couponInfo['amount'];
            $vipCardOrderOfferInfoData['type'] = 1;
            $insertVipCardOrderOfferInfoData[] = $vipCardOrderOfferInfoData;
        }
        if(!empty($discountTicketList)){
            foreach($discountTicketList as $value){
                $vipCardOrderOfferInfoData['id'] = IdGenerator::generate();
                $vipCardOrderOfferInfoData['vip_card_order_id'] = $orderId;
                $vipCardOrderOfferInfoData['offer_info_id'] = $value['id'];
                $vipCardOrderOfferInfoData['amount'] = $value['amount'];
                $vipCardOrderOfferInfoData['type'] = 2;
                $insertVipCardOrderOfferInfoData[] = $vipCardOrderOfferInfoData;
            }
        }

        //订单数据
        $orderNo = $this->functions->orderNo();
        $insertOrder['id'] = $orderId;
        $insertOrder['member_id'] = $memberId;
        $insertOrder['order_no'] = $orderNo;
        $insertOrder['price'] = $orderTotalAmount;
        $insertOrder['order_title'] = $vipCardInfo['name'];
        $insertOrder['vip_card_id'] = $vipCardId;
        $insertOrder['expire'] = $vipCardInfo['expire'];
        $insertOrder['expire_at'] = VipCardConstant::DEFAULT_EXPIRE_AT;
        $insertOrder['course1'] = $rule['course1'];
        $insertOrder['course2'] = $rule['course2'];
        $insertOrder['course3'] = $rule['course3'];
        $insertOrder['currency_course'] = $rule['currency_course'] ?? 0;
        $insertOrder['grade'] = $vipCardInfo['grade'];
        $insertOrder['card_type'] = $cardType;
        $insertOrder['recommend_code'] = $recommendCode;
        $insertOrder['applicable_store_type'] = $vipCardInfo['applicable_store_type'];
        $insertOrder['order_type'] = $orderType;
        $insertOrder['physical_store_id'] = $physicalStoreId;
        $insertOrder['pay_code'] = $payCode;
        $insertOrder['card_theme_type'] = $vipCardInfo['theme_type'];
        $insertOrder['commission_rate'] = $vipCardInfo['commission_rate'];
        $insertOrder['recommend_teacher_id'] = $recommendTeacherId;
        $insertOrder['recommend_physical_store_id'] = $recommendPhysicalStoreId;
        $insertOrder['course_unit_price'] = $courseUnitPrice;

        //支付申请数据
        $outTradeNo = $this->functions->outTradeNo();
        $insertPayApplyData['id'] = IdGenerator::generate();
        $insertPayApplyData['order_no'] = $orderNo;
        $insertPayApplyData['out_trade_no'] = $outTradeNo;
        $insertPayApplyData['pay_code'] = $payCode;
        $insertPayApplyData['order_type'] = 2;

        $insertVipCardOrderDynamicCourseData = [];
        foreach($vipCardDynamicCourseList as $value){
            $vipCardOrderDynamicCourseData['id'] = IdGenerator::generate();
            $vipCardOrderDynamicCourseData['vip_card_order_id'] = $orderId;
            $vipCardOrderDynamicCourseData['name'] = $value['name'];
            $vipCardOrderDynamicCourseData['course'] = $value['course'];
            $vipCardOrderDynamicCourseData['type'] = $value['type'];
            $vipCardOrderDynamicCourseData['week'] = $value['week'];
            $insertVipCardOrderDynamicCourseData[] = $vipCardOrderDynamicCourseData;
        }

        Db::connection('jkc_edu')->beginTransaction();
        try{
            Db::connection('jkc_edu')->table('vip_card_order')->insert($insertOrder);
            Db::connection('jkc_edu')->table('pay_apply')->insert($insertPayApplyData);
            if(!empty($insertVipCardOrderPhysicalStoreData)){
                Db::connection('jkc_edu')->table('vip_card_order_physical_store')->insert($insertVipCardOrderPhysicalStoreData);
            }
            if(!empty($insertVipCardOrderOfferInfoData)){
                Db::connection('jkc_edu')->table('vip_card_order_offer_info')->insert($insertVipCardOrderOfferInfoData);
            }
            if(!empty($couponId)){
                $couponAffected = Db::connection('jkc_edu')->update("UPDATE coupon SET is_used = ?,used_at = ? WHERE id = ? AND is_used = ?", [1,$nowTime,$couponId,0]);
                if(!$couponAffected){
                    Db::connection('jkc_edu')->rollBack();
                    Log::get()->info("vipCardOrderCreate[{$couponId}]:优惠券使用失败");
                    return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                }
            }
            if(!empty($discountTicketIdArray)){
                $discountTicketAffected = DiscountTicket::query()->whereIn('id',$discountTicketIdArray)->where(['status'=>0])->update(['status'=>1,'used_at'=>$nowTime]);
                if($discountTicketAffected !== count($discountTicketIdArray)){
                    Db::connection('jkc_edu')->rollBack();
                    Log::get()->info("vipCardOrderCreate[".json_encode($discountTicketIdArray)."]:减免卷使用失败");
                    return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                }
            }
            if(!empty($insertVipCardOrderDynamicCourseData)){
                Db::connection('jkc_edu')->table('vip_card_order_dynamic_course')->insert($insertVipCardOrderDynamicCourseData);
            }
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }

        $payOrderType = PayConstant::VIP_CARD_ORDER;
        switch ($payCode){
            case 'WXPAY':
                $memberInfo = Member::query()->select(['mini_openid'])->find($memberId);
                if(empty($memberInfo['mini_openid'])){
                    return ['code' => ErrorCode::WARNING, 'msg' => '账号信息失效', 'data' => null];
                }
                $memberInfo = $memberInfo->toArray();

                $appIdConfig = json_decode(env('WXPAYAPPID'), true);
                $attach = "{$memberId}|{$payOrderType}";
                $weChatPayFactory = new WeChatPayFactory();
                $weChatPayFactory->setAppId($appIdConfig['miniProgram']);
                $weChatPayFactory->amount = ['total'=>(int)bcmul($orderTotalAmount,"100"),'currency'=>'CNY'];
                $weChatPayFactory->timeExpire = date("c", strtotime("+15 minutes"));
                $weChatPayFactory->notifyUrl = env('APP_DOMAIN').'/api/pay/callback/wxmini';
                $weChatPayFactory->outTradeNo = $outTradeNo;
                $weChatPayFactory->attach = $attach;
                $weChatPayFactory->description = $vipCardInfo['name'];
                $weChatPayFactory->payerOpenid = $memberInfo['mini_openid'];
                $result = $weChatPayFactory->jsapi();
                $prepayId = $result['data']['prepay_id'];

                $signData = $weChatPayFactory->paySign($prepayId);
                $returnData = $signData['data'];
                break;
            case 'ALIPAY':
                $aLiPayConfig = json_decode(env('ALIPAY'), true);
                $merchantPrivateKey = file_get_contents($aLiPayConfig['merchantPrivateKey']);
                $appCertPath = $aLiPayConfig['merchantCertPath']; //应用公钥证书路径（要确保证书文件可读），例如：/home/admin/cert/appCertPublicKey_2019051064521003.crt
                $alipayCertPath = $aLiPayConfig['alipayCertPath']; //支付宝公钥证书路径（要确保证书文件可读），例如：/home/admin/cert/alipayCertPublicKey_RSA2.crt
                $rootCertPath = $aLiPayConfig['alipayRootCertPath']; //支付宝根证书路径（要确保证书文件可读），例如：/home/admin/cert/alipayRootCert.crt
                $c = new \AopCertClient();
                $c->gatewayUrl = "https://openapi.alipay.com/gateway.do";
                $c->appId = $aLiPayConfig['appId'];
                $c->rsaPrivateKey = $merchantPrivateKey;
                $c->signType= "RSA2";
                //调用getPublicKey从支付宝公钥证书中提取公钥
                $c->alipayrsaPublicKey = $c->getPublicKey($alipayCertPath);
                //是否校验自动下载的支付宝公钥证书，如果开启校验要保证支付宝根证书在有效期内
                $c->isCheckAlipayPublicCert = true;
                //调用getCertSN获取证书序列号
                $c->appCertSN = $c->getCertSN($appCertPath);
                //调用getRootCertSN获取支付宝根证书序列号
                $c->alipayRootCertSN = $c->getRootCertSN($rootCertPath);

                //SDK已经封装掉了公共参数，这里只需要传入业务参数
                $passbackParams = "{$memberId}|{$payOrderType}";
                $bizContent = [
                    'out_trade_no'=>$outTradeNo,
                    'total_amount'=>$orderTotalAmount,
                    'subject'=>$vipCardInfo['name'],
                    'product_code'=>'QUICK_WAP_WAY',
                    'passback_params'=>$passbackParams,
                    'time_expire'=>date('Y-m-d H:i:s',strtotime("+15 minutes"))
                ];
                $notifyUrl = env('APP_DOMAIN').'/api/pay/callback/alipay';
                //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.wap.pay
                $request = new \AlipayTradeWapPayRequest();
                $request->setNotifyUrl($notifyUrl);
                $request->setBizContent(json_encode($bizContent));
                $response = $c->pageExecute($request,'GET');
                $returnData['body'] = $response;
                break;
            case 'ZERO':
                $payService = new PayService();
                $result = $payService->vipCardOrderCallback(['out_trade_no'=>$outTradeNo]);
                if($result['code'] === ErrorCode::FAILURE){
                    return ['code' => ErrorCode::WARNING, 'msg' => '支付失败', 'data' => null];
                }
                $returnData['body'] = 'zero';
                break;
            default:
                return ['code' => ErrorCode::WARNING, 'msg' => '支付方式错误', 'data' => null];
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 会员卡订单列表
     * @return array
     */
    public function vipCardOrderList(): array
    {
        $memberId = $this->memberId;
        $date = date('Y-m-d H:i:s');
        $weekArray = [7=>"每周日",1=>"每周一",2=>"每周二",3=>"每周三",4=>"每周四",5=>"每周五",6=>"每周六"];
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => []];
        }

        $vipCardOrderList = VipCardOrder::query()
            ->select(['id','order_title','price','expire_at','course1','course2','course3','course1_used','course2_used','course3_used','order_type','currency_course','currency_course_used','order_status','card_theme_type','applicable_store_type'])
            ->where(['member_id'=>$memberId,'pay_status'=>1])
            ->orderBy('expire_at')
            ->get();
        $vipCardOrderList = $vipCardOrderList->toArray();
        $vipCardOrderIdArray = array_column($vipCardOrderList,'id');
        $vipCardOrderDynamicCourseList = VipCardOrderDynamicCourse::query()
            ->select(['vip_card_order_id','name','week','course','course_used'])
            ->whereIn('vip_card_order_id',$vipCardOrderIdArray)
            ->get();
        $vipCardOrderDynamicCourseList = $vipCardOrderDynamicCourseList->toArray();
        foreach($vipCardOrderDynamicCourseList as $key=>$value){
            $newWeek = [];
            $week = json_decode($value['week'],true);
            foreach($week as $item){
                $newWeek[] = $weekArray[$item];
            }
            $vipCardOrderDynamicCourseList[$key]['course_surplus'] = $value['course']-$value['course_used'];
            $vipCardOrderDynamicCourseList[$key]['week'] = implode(',',$newWeek);
        }
        $vipCardOrderDynamicCourseList = $this->functions->arrayGroupBy($vipCardOrderDynamicCourseList,'vip_card_order_id');

        $usableVipCardOrderList = [];
        $unusableVipCardOrderList = [];
        foreach($vipCardOrderList as $value){
            $vipCardOrderId = $value['id'];
            $orderTitle = $value['order_title'];
            $expireAt = $value['expire_at'];
            $orderType = $value['order_type'] === 4 ? 1 : $value['order_type'];
            $dynamicCourse = $vipCardOrderDynamicCourseList[$vipCardOrderId] ?? [];
            $orderTitle = $orderType == 2 ? '新人专享' : $orderTitle;
            $status = 1;
            $surplusNumCourse1 = $value['course1']-$value['course1_used'];
            $surplusNumCourse2 = $value['course2']-$value['course2_used'];
            $surplusNumCourse3 = $value['course3']-$value['course3_used'];
            $surplusNumCurrencyCourse = $value['currency_course']-$value['currency_course_used'];
            if($value['order_status'] == 3){
                $status = 4;
            }else if($expireAt === VipCardConstant::DEFAULT_EXPIRE_AT){
                $status = 5;
            }else if($surplusNumCourse1 == 0 && $surplusNumCourse2 == 0 && $surplusNumCourse3 == 0 && $surplusNumCurrencyCourse == 0){
                $status = 2;
            }else if($expireAt<=$date){
                $status = 3;
            }
            $vipCardOrderPhysicalStoreList = [];
            if($value['applicable_store_type'] == 2){
                $vipCardOrderPhysicalStoreList = VipCardOrderPhysicalStore::query()
                    ->leftJoin('physical_store','vip_card_order_physical_store.physical_store_id','=','physical_store.id')
                    ->select(['physical_store.name'])
                    ->where(['vip_card_order_physical_store.vip_card_order_id'=>$value['id']])
                    ->get();
                $vipCardOrderPhysicalStoreList = $vipCardOrderPhysicalStoreList->toArray();
                $vipCardOrderPhysicalStoreList = array_column($vipCardOrderPhysicalStoreList,'name');
            }

            $value['dynamic_course'] = $dynamicCourse;
            $value['expire_at'] = $expireAt === VipCardConstant::DEFAULT_EXPIRE_AT ? '' : $expireAt;
            $value['order_title'] = $orderTitle;
            $value['price'] = (int)$value['price'];
            $value['status'] = $status;
            $value['course1_surplus'] = $surplusNumCourse1;
            $value['course2_surplus'] = $surplusNumCourse2;
            $value['course3_surplus'] = $surplusNumCourse3;
            $value['currency_course_surplus'] = $surplusNumCurrencyCourse;
            $value['order_type'] = $orderType;
            $value['physical_store'] = $vipCardOrderPhysicalStoreList;
            $value['card_theme_type'] = in_array($orderType,[2,3]) ? 0 : $value['card_theme_type'];

            if($status === 1 || $status === 5){
                $usableVipCardOrderList[] = $value;
            }else{
                $unusableVipCardOrderList[] = $value;
            }
        }
        $newVipCardOrderList = array_merge($usableVipCardOrderList,$unusableVipCardOrderList);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $newVipCardOrderList];
    }

}