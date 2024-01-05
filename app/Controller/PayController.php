<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Controller;

use App\Logger\Log;
use App\Service\PayService;
use App\Constants\ErrorCode;
use App\Constants\PayConstant;

class PayController extends AbstractController
{
    /**
     * 微信小程序支付回调
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function wxMiniProgramCallback(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $weChatPaySignature = $this->request->header('Wechatpay-Signature', '');
            $weChatPayTimestamp = $this->request->header('Wechatpay-Timestamp', '');
            $weChatPayNonce = $this->request->header('Wechatpay-Nonce', '');
            $weChatPaySerial = $this->request->header('Wechatpay-Serial', '');
            $body = $this->request->post();
            $body = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : $body;

            $payService = new PayService();
            $verifyData = [
                'inWechatpaySignature' => $weChatPaySignature,
                'inWechatpayTimestamp' => $weChatPayTimestamp,
                'inWechatpayNonce' => $weChatPayNonce,
                'inWechatpaySerial' => $weChatPaySerial,
                'inWechatpayBody' => $body,
            ];
            $verifyResult = $payService->weChatVerify($verifyData);
            if($verifyResult['code'] === ErrorCode::FAILURE){
                return $this->response->withStatus(ErrorCode::SERVER_ERROR)->json(['code' => 'FAIL', 'message' => '失败']);
            }
            $inBodyResource = $verifyResult['data'];

            $attach = explode('|',$inBodyResource['attach']);
            $orderPayType = $attach[1];
            $inBodyResource['attach'] = $attach;
            $params = ['out_trade_no'=>$inBodyResource['out_trade_no'],'body_resource'=>$inBodyResource];

            if($orderPayType == PayConstant::GOODS_ORDER){
                $result = $payService->goodsOrderCallback($params);
            }else if($orderPayType == PayConstant::VIP_CARD_ORDER){
                $result = $payService->vipCardOrderCallback($params);
            }else{
                return $this->response(['code' => 'FAIL', 'message' => '失败'],500);
            }

            if($result['code'] === ErrorCode::FAILURE){
                return $this->response(['code' => 'FAIL', 'message' => '失败'],500);
            }
        } catch (\Throwable $e) {
            return $this->response(['code' => 'FAIL', 'message' => '失败'],500,$e,'wxMiniProgramCallback');
        }
        return $this->response(['code' => 'SUCCESS', 'message' => '成功']);
    }

    /**
     * 支付宝回调
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function aLiPayCallback(): string
    {
        try {
            $body = $this->request->post();

            $payService = new PayService();
            $verifyResult = $payService->aLiPayVerify($body);
            if($verifyResult !== true){
                Log::get()->info("aLiPayCallbackVerify[验签失败]:".json_encode($body));
                return "fail";
            }
            if(isset($body['refund_fee'])){
                Log::get()->info("aLiPayCallbackRefund:".json_encode($body));
                return "success";
            }
            $passbackParams = explode('|',$body['passback_params']);
            $orderPayType = $passbackParams[1];
            $params = ['out_trade_no'=>$body['out_trade_no'],'body_resource'=>$body];

            if($orderPayType == PayConstant::VIP_CARD_ORDER){
                $result = $payService->vipCardOrderCallback($params);
            }else{
                return "fail";
            }

            if($result['code'] === ErrorCode::FAILURE){
                return "fail";
            }
        } catch (\Throwable $e) {
            Log::get()->error("aLiPayCallback:".$e->getMessage());
            return "fail";
        }
        return "success";
    }
}
