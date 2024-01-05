<?php
declare(strict_types=1);

namespace App\Common;

use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;

class Functions
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Functions constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * 订单号生成器
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function orderNo(): string
    {
        mt_srand();
        $expire = 25*60*60;
        $date = date('YmdHi');
        $key = "order_sign_generator_{$date}";
        $redis = $this->container->get(Redis::class);
        if(!$redis->exists($key)){
            $baseIncrement = mt_rand(100000,500000);
            $redis->set($key,$baseIncrement,$expire);
        }
        $stepNumber = mt_rand(5,50);
        $id = $redis->incrBy($key,$stepNumber);
        $orderSn = "{$date}{$id}";
        return $orderSn;
    }

    /**
     * 商户订单号生成器(支付)
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function outTradeNo(): string
    {
        $expire = 25*60*60;
        $date = date('YmdHi');
        $key = "order_payment_sign_generator_{$date}";
        $redis = $this->container->get(Redis::class);
        if(!$redis->exists($key)){
            $redis->set($key,0,$expire);
        }
        $id = $redis->incr($key);
        $id = str_pad((string)$id, 6, "0", STR_PAD_LEFT);
        $outerOrderSn = "{$date}{$id}";
        return $outerOrderSn;
    }

    /**
     * 原子锁
     * @param string $key
     * @param int $expire
     * @return bool
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function atomLock(string $key, int $expire): bool
    {
        $redis = $this->container->get(Redis::class);
        $extend[0] = 'nx';
        if ($expire !== 0) {
            $extend['ex'] = $expire;
        }
        $result = $redis->set($key, 1, $extend);
        return $result;
    }

    /**
     * 删除原子锁
     * @param string $key
     * @return int
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function delAtomLock(string $key): int
    {
        $redis = $this->container->get(Redis::class);
        $result = $redis->del($key);
        return $result;
    }

    /**
     * 获取客户端ip地址
     * @author WangWenBin
     * @param $serverParams
     * @return mixed
     */
    public function getHyperfIp(array $serverParams)
    {
        if (isset($serverParams['http_client_ip'])) {
            return $serverParams['http_client_ip'];
        } elseif (isset($serverParams['http_x_real_ip'])) {
            return $serverParams['http_x_real_ip'];
        } elseif (isset($serverParams['http_x_forwarded_for'])) {
            // 部分CDN会获取多层代理IP，所以转成数组取第一个值
            $arr = explode(',', $serverParams['http_x_forwarded_for']);

            return $arr[0];
        } else {
            return $serverParams['remote_addr'];
        }
    }

    /**
     * 数组非递归分组
     * @param array $array
     * @param string $pkey
     * @param string|null $ckey
     * @return array
     */
    public function arrayGroupBy(array $array, string $pkey, string $ckey = null): array
    {
        if(empty($array)){
            return [];
        }
        $grouped = [];
        foreach ($array as $value) {
            if($ckey === null){
                $grouped[$value[$pkey]][] = $value;
            }else{
                $grouped[$value[$pkey]][$value[$ckey]] = $value;
            }
        }
        return $grouped;
    }

    /**
     * 根据经纬度计算直线距离(单位:米)
     * @param $lat1
     * @param $lng1
     * @param $lat2
     * @param $lng2
     * @return float
     */
    public function linearDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6367996;
        $lat1 = ($lat1 * pi() ) / 180;
        $lng1 = ($lng1 * pi() ) / 180;
        $lat2 = ($lat2 * pi() ) / 180;
        $lng2 = ($lng2 * pi() ) / 180;

        $calcLongitude = $lng2 - $lng1;
        $calcLatitude = $lat2 - $lat1;
        $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
        $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
        $calculatedDistance = $earthRadius * $stepTwo;
        return round($calculatedDistance);
    }

    /**
     * 计算两个日期之间相隔的月数
     * @param string $date1 Y-m-d
     * @param string $date2 Y-m-d
     * @return int
     * @throws \Exception
     */
    public function diffMonth(string $date1, string $date2): int
    {
        $date1 = new \DateTime($date1);
        $date2 = new \DateTime($date2);
        $interval = $date1->diff($date2);
        return $interval->y * 12 + $interval->m;
    }

    /**
     * 随机code
     * @return string
     */
    public function randomCode(): string
    {
        $element = ['g','z','A','h','M','b','3','w','N','i','S','O','H','6','P','a','W','L','j','v','9','G','k','I','5','F','u','G','l','E','K','1','e','t','0','D','p','C','7','Z','m','n','2','V','x','d','o','Y','s','R','y','f','B','4','U','X','q','T','Q','8','r','c'];
        $randomKeys = array_rand($element,6);
        $randomStr = '';
        foreach($randomKeys as $value){
            $randomStr .= $element[$value];
        }
        return $randomStr;
    }

}


