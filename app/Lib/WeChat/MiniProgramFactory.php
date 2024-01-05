<?php

declare(strict_types=1);

namespace App\Lib\WeChat;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Guzzle\ClientFactory;

class MiniProgramFactory
{
    #[Inject]
    private ClientFactory $guzzleClientFactory;

    public string $appId = '';

    public string $appSecret = '';

    public string $envVersion = '';

    public function __construct()
    {
        $config = json_decode(env('MINIPROGRAM'), true);
        $this->appId = $config['appId'];
        $this->appSecret = $config['appSecret'];
        $this->envVersion = $config['envVersion'] ?? 'release';
    }

    /**
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getAccessToken(): string
    {
        $client = $this->guzzleClientFactory->create();
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appId}&secret={$this->appSecret}";
        $response = $client->request('GET', $url);
        $r = $response->getBody()->getContents();
        $data = json_decode($r,true);
        return $data['access_token'];
    }

    /**
     * 小程序码
     * @param string $scene
     * @param string $page
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUnlimitedQRCode(string $scene, string $page = 'pages/index/index'): string
    {
        $accessToken = $this->getAccessToken();
        $client = $this->guzzleClientFactory->create();
        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$accessToken}";
        $response = $client->request('POST', $url,[
            'json' => [
                "check_path" => false,
                "is_hyaline" => true,
                "page" => $page,
                "scene" => $scene,
                "env_version" => $this->envVersion
            ]
        ]);
        return $response->getBody()->getContents();
    }
}