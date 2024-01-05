<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Coupon;
use App\Model\CouponGoods;
use App\Model\Member;
use App\Model\Goods;
use App\Model\GoodsSku;
use App\Model\MemberAddress;
use App\Model\MemberBelongTo;
use App\Model\OrderInfo;
use App\Model\OrderGoods;
use App\Model\OrderRefund;
use App\Model\OrderPackage;
use App\Snowflake\IdGenerator;
use App\Lib\WeChat\WeChatPayFactory;
use App\Constants\ErrorCode;
use App\Constants\PayConstant;
use Hyperf\Utils\Context;
use Hyperf\DbConnection\Db;

class OrderService extends BaseService
{

    /**
     * OrderService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 教具商品订单结算
     * @param array $params
     * @return array
     */
    public function teachingAidsGoodsOrderConfirm(array $params): array
    {
        //0:默认，-1:不使用
        $couponId = $params['coupon_id'] ?? 0;
        $quantity = $params['quantity'];
        $skuId = $params['sku_id'];
        $memberId = $this->memberId;
        $nowDate = date('Y-m-d H:i:s');

        $goodsSkuInfo = GoodsSku::query()
            ->select(['price','goods_id'])
            ->where(['id'=>$skuId])
            ->first();
        if(empty($goodsSkuInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"商品已下架",'data'=>null];
        }
        $goodsSkuInfo = $goodsSkuInfo->toArray();
        $goodsAmount = bcmul($goodsSkuInfo['price'],(string)$quantity,2);
        $goodsId = $goodsSkuInfo['goods_id'];

        if($memberId == 0){
            $returnData = [
                'amount' => $goodsAmount,
                'coupon_list' => [],
                'selected_coupon' => null,
                'address' => null,
            ];
            return ['code'=>ErrorCode::SUCCESS,'msg'=>'','data'=>$returnData];
        }

        //收货地址
        $memberAddressInfo = MemberAddress::query()
            ->select(['id','consignee','mobile','province_id','city_id','district_id','province_name','city_name','district_name','address'])
            ->where(['member_id'=>$memberId])
            ->first();
        $memberAddressInfo = $memberAddressInfo?->toArray();
        //优惠券
        $couponList = Coupon::query()
            ->select(['id','name','threshold_amount','amount','end_at'])
            ->where([['member_id','=',$memberId],['is_used','=',0],['end_at','>',$nowDate]])
            ->get();
        $couponList = $couponList->toArray();

        $couponAmount = '0';
        $selectedCoupon = null;
        $usableCouponList = [];
        if(!empty($couponList)){
            $couponIdArray = array_column($couponList,'id');
            $couponGoodsList = CouponGoods::query()
                ->select(['coupon_id','goods_id'])
                ->whereIn('coupon_id',$couponIdArray)
                ->get();
            $couponGoodsList = $couponGoodsList->toArray();
            $couponGoodsList = $this->functions->arrayGroupBy($couponGoodsList,'coupon_id');

            foreach($couponList as $value){
                $value['threshold_amount'] = (float)$value['threshold_amount'];
                $value['amount'] = (float)$value['amount'];
                if($goodsAmount < $value['threshold_amount']){
                    continue;
                }
                $couponGoodsIdArray = isset($couponGoodsList[$value['id']]) ? array_column($couponGoodsList[$value['id']],'goods_id') : [];
                if(!in_array($goodsId,$couponGoodsIdArray)){
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
            if($couponId != -1 && $goodsAmount>0){
                $couponAmount = $usableCouponList[0]['amount'];
            }
        }
        $goodsAmount = bcsub($goodsAmount,(string)$couponAmount,2);
        $goodsAmount = max($goodsAmount, 0);

        $returnData = [
            'amount' => $goodsAmount,
            'coupon_list' => $usableCouponList,
            'selected_coupon' => $selectedCoupon,
            'address' => $memberAddressInfo,
        ];
        return ['code'=>ErrorCode::SUCCESS,'msg'=>'','data'=>$returnData];
    }

    /**
     * 创建教具商品订单
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsGoodsCreateOrder(array $params): array
    {
        $quantity = $params['quantity'];
        $skuId = $params['sku_id'];
        $couponId = $params['coupon_id'];
        $memberId = $this->memberId;
        $nowTime = date('Y-m-d H:i:s');

        $goodsSkuInfo = GoodsSku::query()
            ->select(['goods_id','price','stock','img_url','prop_value_str'])
            ->where(['id'=>$skuId])
            ->first();
        if(empty($goodsSkuInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"商品已下架",'data'=>null];
        }
        $goodsSkuInfo = $goodsSkuInfo->toArray();
        if($goodsSkuInfo['stock'] < $quantity){
            return ['code'=>ErrorCode::WARNING,'msg'=>"商品库存不足",'data'=>null];
        }
        $goodsId = $goodsSkuInfo['goods_id'];
        $goodsInfo = Goods::query()
            ->select(['name','online','is_deleted','commission_rate'])
            ->where(['id'=>$goodsId])
            ->first();
        if(empty($goodsInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"商品已下架",'data'=>null];
        }
        $goodsInfo = $goodsInfo->toArray();
        if($goodsInfo['online'] == 0 || $goodsInfo['is_deleted'] == 1){
            return ['code'=>ErrorCode::WARNING,'msg'=>"商品已下架",'data'=>null];
        }
        //收货地址
        $memberAddressInfo = MemberAddress::query()
            ->select(['consignee','mobile','city_name','province_name','district_name','address'])
            ->where(['member_id'=>$memberId])
            ->first();
        if(empty($memberAddressInfo)){
            return ['code'=>ErrorCode::WARNING,'msg'=>"请添加收货地址",'data'=>null];
        }
        $memberAddressInfo = $memberAddressInfo->toArray();
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
        $goodsAmount = bcmul($goodsSkuInfo['price'],$quantity,2);
        //优惠券
        $couponAmount = '0';
        if(!empty($couponId)){
            $couponInfo = Coupon::query()
                ->select(['id','threshold_amount','amount'])
                ->where([['id','=',$couponId],['member_id','=',$memberId],['is_used','=',0],['end_at','>',$nowTime]])
                ->first();
            if(empty($couponInfo)){
                return ['code'=>ErrorCode::WARNING,'msg'=>"优惠券不可用",'data'=>null];
            }
            $couponInfo = $couponInfo->toArray();
            if($goodsAmount<$couponInfo['threshold_amount']){
                return ['code'=>ErrorCode::WARNING,'msg'=>"优惠券不满足使用条件",'data'=>null];
            }
            $couponGoodsList = CouponGoods::query()
                ->select(['goods_id'])
                ->where(['coupon_id'=>$couponInfo['id']])
                ->get();
            $couponGoodsList = $couponGoodsList->toArray();
            if(!in_array($goodsId,array_column($couponGoodsList,'goods_id'))){
                return ['code'=>ErrorCode::WARNING,'msg'=>"优惠券该商品不可用",'data'=>null];
            }
            $couponAmount = $couponInfo['amount'];
        }
        //订单总金额
        $orderTotalAmount = bcsub($goodsAmount,(string)$couponAmount,2);
        $orderTotalAmount = (string)max($orderTotalAmount, 0);
        $payCode = $orderTotalAmount == 0 ? 'ZERO' : 'WXPAY';
        $payPrice = bcdiv($orderTotalAmount,(string)$quantity,2);

        //订单数据
        $orderNo = $this->functions->orderNo();
        $orderId = IdGenerator::generate();
        $insertOrder['id'] = $orderId;
        $insertOrder['member_id'] = $memberId;
        $insertOrder['order_no'] = $orderNo;
        $insertOrder['amount'] = $orderTotalAmount;
        $insertOrder['goods_amount'] = $orderTotalAmount;
        $insertOrder['consignee'] = $memberAddressInfo['consignee'];
        $insertOrder['mobile'] = $memberAddressInfo['mobile'];
        $insertOrder['province_name'] = $memberAddressInfo['province_name'];
        $insertOrder['city_name'] = $memberAddressInfo['city_name'];
        $insertOrder['district_name'] = $memberAddressInfo['district_name'];
        $insertOrder['address'] = $memberAddressInfo['address'];
        $insertOrder['order_title'] = $goodsInfo['name'];
        $insertOrder['pay_code'] = $payCode;
        $insertOrder['recommend_teacher_id'] = $recommendTeacherId;
        $insertOrder['recommend_physical_store_id'] = $recommendPhysicalStoreId;

        //订单商品数据
        $orderGoodsId = IdGenerator::generate();
        $insertOrderGoods['id'] = $orderGoodsId;
        $insertOrderGoods['order_info_id'] = $orderId;
        $insertOrderGoods['member_id'] = $memberId;
        $insertOrderGoods['goods_id'] = $goodsId;
        $insertOrderGoods['goods_img'] = $goodsSkuInfo['img_url'];
        $insertOrderGoods['quantity'] = $quantity;
        $insertOrderGoods['price'] = $goodsSkuInfo['price'];
        $insertOrderGoods['prop_value_str'] = $goodsSkuInfo['prop_value_str'];
        $insertOrderGoods['pay_price'] = $payPrice;
        $insertOrderGoods['goods_name'] = $goodsInfo['name'];
        $insertOrderGoods['commission_rate'] = $goodsInfo['commission_rate'];
        $insertOrderGoods['amount'] = $orderTotalAmount;

        //优惠信息数据
        $insertOrderOfferInfoData = [];
        if(!empty($couponInfo)){
            $orderOfferInfoData['id'] = IdGenerator::generate();
            $orderOfferInfoData['order_info_id'] = $orderId;
            $orderOfferInfoData['offer_info_id'] = $couponInfo['id'];
            $orderOfferInfoData['amount'] = $couponInfo['amount'];
            $orderOfferInfoData['type'] = 1;
            $insertOrderOfferInfoData[] = $orderOfferInfoData;
        }

        //支付申请数据
        $outTradeNo = $this->functions->outTradeNo();
        $insertPayApplyData['id'] = IdGenerator::generate();
        $insertPayApplyData['order_no'] = $orderNo;
        $insertPayApplyData['out_trade_no'] = $outTradeNo;
        $insertPayApplyData['pay_code'] = $payCode;
        $insertPayApplyData['order_type'] = 3;

        Db::connection('jkc_edu')->beginTransaction();
        try{
            $goodsSkuAffected = Db::connection('jkc_edu')->update('UPDATE goods_sku SET stock=stock-? WHERE id=? AND stock>=?', [$quantity,$skuId,$quantity]);
            if(!$goodsSkuAffected){
                Db::connection('jkc_edu')->rollBack();
                return ['code' => ErrorCode::FAILURE, 'msg' => '商品库存不足', 'data' => null];
            }
            if(!empty($couponId)){
                $couponAffected = Db::connection('jkc_edu')->update("UPDATE coupon SET is_used = ?,used_at = ? WHERE id = ? AND is_used = ?", [1,$nowTime,$couponId,0]);
                if(!$couponAffected){
                    Db::connection('jkc_edu')->rollBack();
                    return ['code' => ErrorCode::FAILURE, 'msg' => '购买失败请重试', 'data' => null];
                }
            }
            Db::connection('jkc_edu')->update('UPDATE goods SET stock=stock-?,csale=csale+? WHERE id=? AND stock>=?', [$quantity,$quantity,$goodsId,$quantity]);
            Db::connection('jkc_edu')->table('order_info')->insert($insertOrder);
            Db::connection('jkc_edu')->table('order_goods')->insert($insertOrderGoods);
            Db::connection('jkc_edu')->table('pay_apply')->insert($insertPayApplyData);
            if(!empty($insertOrderOfferInfoData)){
                Db::connection('jkc_edu')->table('order_offer_info')->insert($insertOrderOfferInfoData);
            }
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }

        $payOrderType = PayConstant::GOODS_ORDER;
        switch ($payCode){
            case 'WXPAY':
                $memberInfo = Member::query()->select(['mini_openid'])->where(['id'=>$memberId])->first();
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
                $weChatPayFactory->description = $goodsInfo['name'];
                $weChatPayFactory->payerOpenid = $memberInfo['mini_openid'];
                $result = $weChatPayFactory->jsapi();
                $prepayId = $result['data']['prepay_id'];

                $signData = $weChatPayFactory->paySign($prepayId);
                $paySign = $signData['data'] ?? '';
                break;
            case 'ZERO':
                $payService = new PayService();
                $result = $payService->goodsOrderCallback(['out_trade_no'=>$outTradeNo]);
                if($result['code'] === ErrorCode::FAILURE){
                    return ['code' => ErrorCode::WARNING, 'msg' => '支付失败', 'data' => null];
                }
                $paySign['body'] = 'zero';
                break;
            default:
                return ['code' => ErrorCode::WARNING, 'msg' => '支付方式错误', 'data' => null];
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $paySign];
    }

    /**
     * 教具订单列表
     * @param array $params
     * @return array
     */
    public function teachingAidsOrderList(array $params): array
    {
        $type = $params['type'];
        $memberId = $this->memberId;
        $offset = $this->offset;
        $limit = $this->limit;
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        $selectField = ['order_goods.id','order_goods.goods_name','order_goods.goods_id','order_goods.goods_img','order_goods.prop_value_str','order_goods.quantity','order_goods.order_status','order_goods.shipping_status','order_goods.price','order_goods.pay_price','order_goods.amount'];
        if($type == 0){
            //全部
            $orderGoodsModel = OrderGoods::query()
                ->select($selectField)
                ->where(['order_goods.member_id'=>$memberId,'order_goods.pay_status'=>1]);
        }else if($type == 1){
            //待发货
            $orderGoodsModel = OrderGoods::query()
                ->select($selectField)
                ->where(['order_goods.member_id'=>$memberId,'order_goods.pay_status'=>1,'order_goods.order_status'=>0,'order_goods.shipping_status'=>0]);
        }else if($type == 2){
            //待完成
            $orderGoodsModel = OrderGoods::query()
                ->select($selectField)
                ->where(['order_goods.member_id'=>$memberId,'order_goods.pay_status'=>1,'order_goods.order_status'=>0,'order_goods.shipping_status'=>1]);
        }else if($type == 3){
            //已完成
            $orderGoodsModel = OrderGoods::query()
                ->select($selectField)
                ->where(['order_goods.member_id'=>$memberId,'order_goods.pay_status'=>1,'order_goods.order_status'=>0,'order_goods.shipping_status'=>2]);
        }else{
            return ['code'=>ErrorCode::SUCCESS,'msg'=>"",'data'=>[]];
        }
        $count = $orderGoodsModel->count();
        $orderGoodsList = $orderGoodsModel->orderBy('order_goods.id','desc')->offset($offset)->limit($limit)->get();
        $orderGoodsList = $orderGoodsList->toArray();
        $orderGoodsIdArray = array_column($orderGoodsList,'id');
        $orderRefundList = [];
        if(!empty($orderGoodsIdArray)){
            $orderRefundList = OrderRefund::query()
                ->select(['order_goods_id'])
                ->whereIn('order_goods_id',$orderGoodsIdArray)->whereIn('status',[10,15,20,24])
                ->get();
            $orderRefundList = $orderRefundList->toArray();
            $combineOrderRefundKey = array_column($orderRefundList,'order_goods_id');
            $orderRefundList = array_combine($combineOrderRefundKey,$orderRefundList);
        }

        foreach($orderGoodsList as $key=>$value){
            $orderGoodsId = $value['id'];
            //待发货
            $status = 1;
            if(!empty($orderRefundList[$orderGoodsId])){
                //售后中
                $status = 4;
            }else if($value['order_status'] == 0 && $value['shipping_status'] == 1){
                //待完成
                $status = 2;
            }else if($value['order_status'] == 0 && $value['shipping_status'] == 2){
                //已完成
                $status = 3;
            }else if($value['order_status'] == 3){
                //已关闭
                $status = 5;
            }
            unset($orderGoodsList[$key]['shipping_status']);
            $orderGoodsList[$key]['order_status'] = $status;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$orderGoodsList,'count'=>$count]];
    }

    /**
     * 教具订单详情
     * @param int $id
     * @return array
     */
    public function teachingAidsOrderDetail(int $id): array
    {
        $memberId = $this->memberId;
        if($memberId == 0){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
        }

        //订单商品信息
        $orderGoodsInfo = OrderGoods::query()
            ->select(['order_info_id','goods_name','goods_img','quantity','pay_price','prop_value_str','order_status','shipping_status','pay_at','shipment_at','receipt_at','extend_days','price','amount'])
            ->where(['id'=>$id,'member_id'=>$memberId,'pay_status'=>1])
            ->first();
        if(empty($orderGoodsInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '订单信息错误', 'data' => null];
        }
        $orderGoodsInfo = $orderGoodsInfo->toArray();
        $orderInfoId = $orderGoodsInfo['order_info_id'];
        //订单信息
        $orderInfo = OrderInfo::query()
            ->select(['order_no','consignee','mobile','province_name','city_name','district_name','address'])
            ->where(['id'=>$orderInfoId])
            ->first();
        $orderInfo = $orderInfo->toArray();
        //退款信息
        $orderRefundInfo = OrderRefund::query()
            ->select(['status','is_mail','mail_at','created_at'])
            ->where(['order_goods_id'=>$id])->whereIn('status',[10,15,20,24])
            ->first();
        if(!empty($orderRefundInfo)){
            $orderRefundInfo = $orderRefundInfo->toArray();
        }
        //订单包裹信息
        $orderPackageList = OrderPackage::query()
            ->select(['type','logis_name','express_number'])
            ->where(['order_goods_id'=>$id])
            ->get();
        $orderPackageList = $orderPackageList->toArray();
        $orderPackageList = $this->functions->arrayGroupBy($orderPackageList,'type');

        //寄件状态
        $mailingStatus = 0;
        //待发货
        $orderStatus = 1;
        if(!empty($orderRefundInfo)){
            //售后中
            $orderStatus = 4;
            if(in_array($orderRefundInfo['status'],[15,20]) && $orderGoodsInfo['shipping_status'] != 0){
                if($orderRefundInfo['is_mail'] == 0){
                    $mailingStatus = 1;
                }else{
                    $mailingStatus = 2;
                }
            }
        }else if($orderGoodsInfo['order_status'] == 0 && $orderGoodsInfo['shipping_status'] == 1){
            //待完成
            $orderStatus = 2;
        }else if($orderGoodsInfo['order_status'] == 0 && $orderGoodsInfo['shipping_status'] == 2){
            //已完成
            $orderStatus = 3;
        }else if($orderGoodsInfo['order_status'] == 3){
            //已关闭
            $orderStatus = 5;
        }

        $address = [
            'consignee' => $orderInfo['consignee'],
            'mobile' => $orderInfo['mobile'],
            'province_name' => $orderInfo['province_name'],
            'city_name' => $orderInfo['city_name'],
            'district_name' => $orderInfo['district_name'],
            'address' => $orderInfo['address'],
        ];
        $sellerAddress = [
            'consignee' => '甲壳虫',
            'mobile' => '123456',
            'province_name' => '浙江省',
            'city_name' => '杭州市',
            'district_name' => '西湖区',
            'address' => '西湖',
        ];
        $goods = [
            'goods_name' => $orderGoodsInfo['goods_name'],
            'goods_img' => $orderGoodsInfo['goods_img'],
            'quantity' => $orderGoodsInfo['quantity'],
            'pay_price' => $orderGoodsInfo['pay_price'],
            'amount' => $orderGoodsInfo['amount'],
            'prop_value_str' => $orderGoodsInfo['prop_value_str'],
            'price' => $orderGoodsInfo['price']
        ];
        $finishedAt = '';
        if($orderGoodsInfo['shipping_status'] == 1){
            $finishedAt = date('Y-m-d H:i:s',strtotime($orderGoodsInfo['shipment_at'])+(3600*24*(14+$orderGoodsInfo['extend_days'])));
        }

        $returnData['id'] = "{$id}";
        $returnData['pay_at'] = $orderGoodsInfo['pay_at'];
        $returnData['shipment_at'] = $orderGoodsInfo['shipment_at'];
        $returnData['receipt_at'] = $orderGoodsInfo['receipt_at'];
        $returnData['refund_at'] = $orderRefundInfo['created_at'] ?? '0000-00-00 00:00:00';
        $returnData['finished_at'] = $finishedAt;
        $returnData['order_no'] = $orderInfo['order_no'];
        $returnData['status'] = $orderStatus;
        $returnData['mailing_status'] = $mailingStatus;
        $returnData['goods'] = $goods;
        $returnData['consignee'] = $address;
        $returnData['seller_address'] = $sellerAddress;
        $returnData['package1'] = $orderPackageList[1] ?? [];
        $returnData['package2'] = $orderPackageList[2] ?? [];
        $returnData['refund_refuse_reason'] = '未收到寄件商品';
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    /**
     * 教具订单退款申请
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsOrderRefundApply(array $params): array
    {
        $orderGoodsId = $params['order_goods_id'];
        $reason = $params['reason'];
        $memo = $params['memo'] ?? '';
        $imgUrl = $params['img_url'];
        $memberId = $this->memberId;

        $orderRefundInfo = OrderRefund::query()
            ->select(['id'])
            ->where(['member_id'=>$memberId,'order_goods_id'=>$orderGoodsId])->whereIn('status',[10,15,20,24,25])
            ->first();
        if(!empty($orderRefundInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '订单已申请退款不能重复申请', 'data' => null];
        }

        $orderGoodsInfo = OrderGoods::query()
            ->select(['order_info_id','amount'])
            ->where(['id'=>$orderGoodsId,'pay_status'=>1,'order_status'=>0])
            ->first();
        if(empty($orderGoodsInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '订单暂无法申请退款', 'data' => null];
        }
        $orderGoodsInfo = $orderGoodsInfo->toArray();
        $totalAmount = $orderGoodsInfo['amount'];
        $imgUrl = !empty($imgUrl) ? json_encode(explode(',',$imgUrl)) : [];

        $insertOrderRefundData['id'] = IdGenerator::generate();
        $insertOrderRefundData['member_id'] = $memberId;
        $insertOrderRefundData['order_goods_id'] = $orderGoodsId;
        $insertOrderRefundData['refund_order_no'] = $this->functions->orderNo();
        $insertOrderRefundData['amount'] = $totalAmount;
        $insertOrderRefundData['memo'] = $memo;
        $insertOrderRefundData['reason'] = $reason;
        $insertOrderRefundData['img_url'] = $imgUrl;
        $insertOrderRefundData['order_info_id'] = $orderGoodsInfo['order_info_id'];

        Db::connection('jkc_edu')->beginTransaction();
        try{
            OrderRefund::query()->insert($insertOrderRefundData);
            OrderGoods::query()->where(['id'=>$orderGoodsId,'is_refund'=>0])->update(['is_refund'=>1]);
            Db::connection('jkc_edu')->commit();
        } catch(\Throwable $e){
            Db::connection('jkc_edu')->rollBack();
            throw new \Exception($e->getMessage(), 1);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 添加教具订单退款寄件信息
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addTeachingAidsOrderRefundPackage(array $params): array
    {
        $orderGoodsId = $params['order_goods_id'];
        $logisName = $params['logis_name'];
        $expressNumber = $params['express_number'];
        $memberId = $this->memberId;
        $date = date('Y-m-d H:i:s');

        $orderRefundInfo = OrderRefund::query()
            ->select(['id'])
            ->where(['order_goods_id'=>$orderGoodsId,'member_id'=>$memberId,'status'=>15,'is_mail'=>0])
            ->first();
        if(empty($orderRefundInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '订单无法填写寄件信息', 'data' => null];
        }
        $orderRefundInfo = $orderRefundInfo->toArray();
        $orderRefundId = $orderRefundInfo['id'];

        $insertOrderPackageData['id'] = IdGenerator::generate();
        $insertOrderPackageData['order_goods_id'] = $orderGoodsId;
        $insertOrderPackageData['resource_id'] = $orderRefundId;
        $insertOrderPackageData['logis_name'] = $logisName;
        $insertOrderPackageData['express_number'] = $expressNumber;
        $insertOrderPackageData['type'] = 2;
        $orderRefundAffected = OrderRefund::query()->where(['id'=>$orderRefundId,'is_mail'=>0])->update(['mail_at'=>$date,'is_mail'=>1]);
        if($orderRefundAffected){
            OrderPackage::query()->insert($insertOrderPackageData);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 取消教具订单退款申请
     * @param array $params
     * @return array
     */
    public function cancelTeachingAidsOrderRefundApply(array $params): array
    {
        $orderGoodsId = $params['order_goods_id'];
        $memberId = $this->memberId;

        $orderRefundAffected = OrderRefund::query()->where(['order_goods_id'=>$orderGoodsId,'member_id'=>$memberId])->whereIn('status',[10,15,20])->update(['status'=>31]);
        if($orderRefundAffected){
            OrderGoods::query()->where(['id'=>$orderGoodsId,'is_refund'=>1])->update(['is_refund'=>0]);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 退款原因
     * @return array
     */
    public function refundReasonList(): array
    {
        $refundReasonList = [
            ['name'=>'不喜欢'],
            ['name'=>'颜色/图案/款式与商品描述不符'],
            ['name'=>'质量问题']
        ];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $refundReasonList];
    }
}