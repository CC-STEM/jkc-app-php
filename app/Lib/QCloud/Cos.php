<?php
declare(strict_types=1);

namespace App\Lib\QCloud;

use Qcloud\Cos\Client;

class Cos
{
    /**
     * 区域
     * @var string
     */
    private string $region = '';
    /**
     * 存储桶名字
     * @var string
     */
    private string $bucket = '';
    /**
     * @var Client
     */
    public Client $client;

    /**
     * Cos constructor.
     * @param string $bucket
     * @param string $region
     */
    public function __construct(string $bucket = '', string $region = '')
    {
        $this->region = $region;
        $this->bucket = $bucket;
        $env = json_decode(getenv('TENCENT_CLOUD'), true);
        $config = [
            'credentials' => [
                'secretId' => $env['secretId'],
                'secretKey' => $env['secretKey']
            ],
            'region' => $this->region
        ];
        $this->client = new Client($config);
    }

    /**
     * 上传文件
     * @param array $file
     * @param string $key
     * @return array
     * @throws \Exception
     */
    public function putObject(array $file, string $key): array
    {
        try{
            $this->client->putObject(array(
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                    'Body' => fopen($file['tmp_file'], 'rb'))
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            throw new \Exception("cos文件上传错误:{$error}", 1);
        }
        return ['code' => 0, 'msg' => 'SUCCESS', 'data' => ['key'=>$key]];
    }

    /**
     * 生成上传文件名
     * @return string
     */
    public function generateName(): string
    {
        $microtime = intval(microtime(true) * 1000);
        mt_srand();
        $randStr = $microtime.'-'.mt_rand(10000000, 99999999);
        $randStr = md5($randStr);
        return $randStr;
    }

}