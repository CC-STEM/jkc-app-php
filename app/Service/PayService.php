<?php

declare(strict_types=1);

namespace App\Service;

use App\Event\GoodsPayRegistered;
use App\Event\VipCardPayRegistered;
use App\Model\PayApply;
use App\Model\VipCardOrder;
use App\Model\OrderInfo;
use App\Constants\ErrorCode;
use App\Lib\WeChat\WeChatPayFactory;
USE App\Logger\Log;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\EventDispatcher\EventDispatcherInterface;

class PayService extends BaseService
{
    #[Inject]
    private EventDispatcherInterface $eventDispatcher;

    /**
     * 商品订单支付回调
     * @param array $params
     * @return array
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function goodsOrderCallback(array $params): array
    {
        $outTradeNo = $params['out_trade_no'];
        $bodyResource = $params['body_resource'];
        $paramsString = $bodyResource !== null ? json_encode($bodyResource,JSON_UNESCAPED_UNICODE) : '';
        $date = date('Y-m-d H:i:s');
        $payApplyInfo = PayApply::query()->select(['order_no'])->where(['out_trade_no'=>$outTradeNo])->first();
        if(empty($payApplyInfo)){
            return ['code' => ErrorCode::FAILURE, 'msg' => '订单信息不存在', 'data' => null];
        }
        $payApplyInfo = $payApplyInfo->toArray();
        $orderNo = $payApplyInfo['order_no'];

        $orderInfo = OrderInfo::query()->select(['id','member_id'])->where(['order_no'=>$orderNo,'pay_status'=>0])->first();
        if(empty($orderInfo)){
            Log::get()->info("goodsOrderCallback[{$orderNo}]:订单信息不存在");
            return ['code' => ErrorCode::FAILURE, 'msg' => '订单信息不存在', 'data' => null];
        }
        $orderInfo = $orderInfo->toArray();
        $orderInfoId = $orderInfo['id'];

        Db::connection('jkc_edu')->beginTransaction();
        try{
            $orderInfoAffected = Db::connection('jkc_edu')->table('order_info')->where(['order_no'=>$orderNo,'pay_status'=>0])->update(['pay_status'=>1]);
            if(!$orderInfoAffected){
                Db::connection('jkc_edu')->rollBack();
                Log::get()->info("goodsOrderCallback[{$orderNo}]:订单信息修改失败");
                return ['code' => ErrorCode::FAILURE, 'msg' => '订单信息修改失败', 'data' => null];
            }
            $orderGoodsAffected = Db::connection('jkc_edu')->table('order_goods')->where(['order_info_id'=>$orderInfoId,'pay_status'=>0])->update(['pay_status'=>1,'pay_at'=>$date]);
            if(!$orderGoodsAffected){
                Db::connection('jkc_edu')->rollBack();
                Log::get()->info("goodsOrderCallback[{$orderNo}]:订单商品信息修改失败");
                return ['code' => ErrorCode::FAILURE, 'msg' => '订单商品信息修改失败', 'data' => null];
            }
            $payApplyAffected = Db::connection('jkc_edu')->table('pay_apply')->where(['out_trade_no'=>$outTradeNo,'status'=>0,'order_type'=>3])->update(['status'=>1,'resp_data'=>$paramsString]);
            if(!$payApplyAffected){
                Db::connection('jkc_edu')->rollBack();
                Log::get()->info("goodsOrderCallback[{$orderNo}]:支付申请信息修改失败");
                return ['code' => ErrorCode::FAILURE, 'msg' => '订单信息异常', 'data' => null];
            }
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }
        $this->eventDispatcher->dispatch(new GoodsPayRegistered((int)$orderInfo['member_id'],$orderNo));
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 会员卡订单支付回调
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function vipCardOrderCallback(array $params): array
    {
        $outTradeNo = $params['out_trade_no'];
        $bodyResource = $params['body_resource'];
        $paramsString = $bodyResource !== null ? json_encode($bodyResource,JSON_UNESCAPED_UNICODE) : '';
        $payApplyInfo = PayApply::query()->select(['order_no'])->where(['out_trade_no'=>$outTradeNo])->first();
        if(empty($payApplyInfo)){
            return ['code' => ErrorCode::FAILURE, 'msg' => '订单信息不存在', 'data' => null];
        }
        $payApplyInfo = $payApplyInfo->toArray();
        $orderNo = $payApplyInfo['order_no'];

        $vipCardOrderInfo = VipCardOrder::query()->select(['id','member_id','created_at','order_type'])->where(['order_no'=>$orderNo,'pay_status'=>0])->first();
        if(empty($vipCardOrderInfo)){
            Log::get()->info("vipCardOrderCallbackWx[{$orderNo}]:订单信息不存在");
            return ['code' => ErrorCode::FAILURE, 'msg' => '订单信息不存在', 'data' => null];
        }
        $vipCardOrderInfo = $vipCardOrderInfo->toArray();
        $memberId = $vipCardOrderInfo['member_id'];
        $vipCardOrderCount = VipCardOrder::query()
            ->where(['member_id'=>$memberId,'pay_status'=>1])
            ->whereIn('order_type',[1,2,4])
            ->count();
        $orderCounter = $vipCardOrderCount+1;

        Db::connection('jkc_edu')->beginTransaction();
        try{
            $vipCardOrderAffected = Db::connection('jkc_edu')->table('vip_card_order')->where(['order_no'=>$orderNo,'pay_status'=>0])->update(['pay_status'=>1,'order_counter'=>$orderCounter]);
            if(!$vipCardOrderAffected){
                Db::connection('jkc_edu')->rollBack();
                Log::get()->info("vipCardOrderCallbackWx[{$orderNo}]:会员卡订单信息修改失败");
                return ['code' => ErrorCode::FAILURE, 'msg' => '订单信息异常', 'data' => null];
            }
            $payApplyAffected = Db::connection('jkc_edu')->table('pay_apply')->where(['out_trade_no'=>$outTradeNo,'status'=>0,'order_type'=>2])->update(['status'=>1,'resp_data'=>$paramsString]);
            if(!$payApplyAffected){
                Db::connection('jkc_edu')->rollBack();
                Log::get()->info("vipCardOrderCallbackWx[{$orderNo}]:支付申请信息修改失败");
                return ['code' => ErrorCode::FAILURE, 'msg' => '订单信息异常', 'data' => null];
            }
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }
        $this->eventDispatcher->dispatch(new VipCardPayRegistered((int)$memberId,(int)$vipCardOrderInfo['order_type'],$orderNo));
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 微信支付回调验签
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function weChatVerify(array $params): array
    {
        $weChatPayFactory = new WeChatPayFactory();
        $weChatPayFactory->inWechatpaySignature = $params['inWechatpaySignature'];
        $weChatPayFactory->inWechatpayTimestamp = $params['inWechatpayTimestamp'];
        $weChatPayFactory->inWechatpayNonce = $params['inWechatpayNonce'];
        $weChatPayFactory->inWechatpaySerial = $params['inWechatpaySerial'];
        $weChatPayFactory->inWechatpayBody = $params['inWechatpayBody'];
        $result = $weChatPayFactory->verify();
        if($result['code'] === ErrorCode::FAILURE){
            Log::get()->info("weChatVerify：签名验证失败:".json_encode($params));
        }
        return $result;
    }

    /**
     * 支付宝支付回调验签
     * @param array $params
     * @return bool
     */
    public function aLiPayVerify(array $params): bool
    {
        $aLiPayConfig = json_decode(env('ALIPAY'), true);
        $c = new \AopCertClient();
        $alipayCertPath = $aLiPayConfig['alipayCertPath'];
        //支付宝公钥赋值
        $c->alipayrsaPublicKey = $c->getPublicKey($alipayCertPath);

        //验签代码
        return $c->rsaCheckV1($params, null, "RSA2");
    }

}