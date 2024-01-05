<?php

declare(strict_types=1);

namespace App\Lib\WeChat;

use App\Constants\ErrorCode;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Util\PemUtil;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Formatter;

class WeChatPayFactory
{
    /**
     * 商户号
     * @var string
     */
    private string $merchantId = '';
    /**
     * @var string
     */
    private string $appId = '';
    /**
     * 商户私钥
     * @var string
     */
    private string $merchantPrivateKeyFilePath = '';
    /**
     * 商户证书序列号
     * @var string
     */
    private string $merchantCertificateSerial = '';
    /**
     * 平台证书(公钥)
     * @var string
     */
    private string $platformCertificateFilePath = '';
    /**
     * @var string
     */
    private string $apiV3Key = '';

    /**
     * @var string
     */
    public string $outTradeNo = '';
    /**
     * @var string
     */
    public string $notifyUrl = '';
    /**
     * @var string
     */
    public string $description = '';
    /**
     * @var string
     */
    public string $timeExpire = '';
    /**
     * @var string
     */
    public string $attach = '';
    /**
     * @var array
     */
    public array $amount = [];
    /**
     * @var string
     */
    public string $payerOpenid = '';
    /**
     * @var string
     */
    public string $inWechatpaySignature = '';
    /**
     * @var string
     */
    public string $inWechatpayTimestamp = '';
    /**
     * @var string
     */
    public string $inWechatpaySerial = '';
    /**
     * @var string
     */
    public string $inWechatpayNonce = '';
    /**
     * @var string
     */
    public string $inWechatpayBody = '';
    /**
     * @var string
     */
    public string $transactionId = '';
    /**
     * @var string
     */
    public string $outRefundNo = '';

    public function __construct()
    {
        $config = json_decode(env('WXPAY'), true);
        $this->merchantId = $config['merchantId'];
        $this->merchantPrivateKeyFilePath = $config['merchantPrivateKeyFilePath'];
        $this->merchantCertificateSerial = $config['merchantCertificateSerial'];
        $this->platformCertificateFilePath = $config['platformCertificateFilePath'];
        $this->apiV3Key = $config['apiV3Key'];
    }

    /**
     * 设置 appid
     * @param string $appId
     * @return void
     */
    public function setAppId(string $appId): void
    {
        $this->appId = $appId;
    }

    /**
     * @return \WeChatPay\BuilderChainable
     */
    private function instance(): \WeChatPay\BuilderChainable
    {
        $merchantId = $this->merchantId;
        $merchantPrivateKeyFilePath = 'file://'.$this->merchantPrivateKeyFilePath;
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
        $merchantCertificateSerial = $this->merchantCertificateSerial;
        $platformCertificateFilePath = 'file://'.$this->platformCertificateFilePath;
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);

        return Builder::factory([
            'mchid'      => $merchantId,
            'serial'     => $merchantCertificateSerial,
            'privateKey' => $merchantPrivateKeyInstance,
            'certs'      => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);
    }

    public function jsapi(): array
    {
        $instance = $this->instance();
        $resp = $instance->v3->pay->transactions->jsapi->post(['json' => [
            'mchid'        => $this->merchantId,
            'out_trade_no' => $this->outTradeNo,
            'appid'        => $this->appId,
            'description'  => $this->description,
            'time_expire'  => $this->timeExpire,
            'attach'       => $this->attach,
            'amount'       => $this->amount,
            'notify_url'   => $this->notifyUrl,
            'payer'        => [
                'openid'   => $this->payerOpenid
            ],
        ]]);

        $statusCode = $resp->getStatusCode();
        if($statusCode != 200){
            return ['code' => ErrorCode::FAILURE, 'msg' => "支付失败:{$statusCode}", 'data' => null];
        }
        $result = $resp->getBody();
        $result = (string)$result;
        $result = json_decode($result, true);

        $returnData = ['prepay_id'=>$result['prepay_id']];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    public function refunds(): array
    {
        $instance = $this->instance();
        $resp = $instance->v3->refund->domestic->refunds->post([
            'json' => [
                'out_trade_no' => $this->outTradeNo,
                'out_refund_no'  => $this->outRefundNo,
                'amount'         => $this->amount,
                'notify_url'   => $this->notifyUrl,
            ],
        ]);
        $statusCode = $resp->getStatusCode();
        if($statusCode != 200){
            return ['code' => ErrorCode::FAILURE, 'msg' => "退款失败:{$statusCode}", 'data' => null];
        }
        $result = $resp->getBody();
        $result = (string)$result;
        $result = json_decode($result, true);
        $prepayId = $result['prepay_id'];

        $returnData = ['prepay_id'=>$prepayId];
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $returnData];
    }

    public function verify(): array
    {
        $apiV3Key = $this->apiV3Key;
        $platformCertificateFilePath = 'file://'.$this->platformCertificateFilePath;
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$this->inWechatpayTimestamp);
        $verifiedStatus = Rsa::verify(
            Formatter::joinedByLineFeed($this->inWechatpayTimestamp, $this->inWechatpayNonce, $this->inWechatpayBody),
            $this->inWechatpaySignature,
            $platformPublicKeyInstance
        );
        if($timeOffsetStatus === false || $verifiedStatus === false){
            return ['code' => ErrorCode::FAILURE, 'msg' => '签名验证失败', 'data' => null];
        }
        $inBodyArray = (array)json_decode($this->inWechatpayBody, true);
        ['resource' => [
            'ciphertext' => $ciphertext,
            'nonce' => $nonce,
            'associated_data' => $aad
        ]] = $inBodyArray;
        $inBodyResource = AesGcm::decrypt($ciphertext, $apiV3Key, $nonce, $aad);
        $inBodyResourceArray = (array)json_decode($inBodyResource, true);
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $inBodyResourceArray];
    }

    public function paySign(string $prepayId): array
    {
        $merchantPrivateKeyFilePath = 'file://'.$this->merchantPrivateKeyFilePath;
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath);

        $params = [
            'app_id'     => $this->appId,
            'time_stamp' => (string)Formatter::timestamp(),
            'nonce_str'  => Formatter::nonce(),
            'package'   => 'prepay_id='.$prepayId,
        ];
        $params += ['pay_sign' => Rsa::sign(
            Formatter::joinedByLineFeed(...array_values($params)),
            $merchantPrivateKeyInstance
        ), 'sign_type' => 'RSA'];

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $params];
    }
}