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

use App\Service\MarketService;

class MarketController extends AbstractController
{
    /**
     * 营销信息列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function marketInfoList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();

            $fitUpService = new MarketService();
            $fitUpService->offset = $offset;
            $fitUpService->limit = $pageSize;
            $result = $fitUpService->marketInfoList();
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'marketInfoList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 营销信息详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function marketInfoDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');
            $fitUpService = new MarketService();
            $result = $fitUpService->marketInfoDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'marketInfoDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
