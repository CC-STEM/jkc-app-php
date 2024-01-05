<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Logger\Log;
use Qcloud\Sms\SmsSingleSender;

class SmsService extends BaseService
{
    /**
     * 登录短信验证码发送
     * @param string $mobile
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function loginSmsSend(string $mobile): array
    {
        mt_srand();
        $code = mt_rand(10000, 99999);
        $env = json_decode(getenv('SMS'), true);
        $appid = $env['appid'];
        $appkey = $env['appkey'];
        $ssender = new SmsSingleSender($appid, $appkey);
        $result = $ssender->send(0, "86", $mobile, "【甲壳虫】您的验证码是: {$code}", "", "");
        $rsp = json_decode($result,true);
        if ($rsp['errmsg'] !== 'OK') {
            Log::get()->info("mobile[{$mobile}]:{$result}");
            return ['code' => ErrorCode::WARNING, 'msg' => '验证码发送失败，请稍后重试', 'data' => null];
        }

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }
}