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

use App\Service\VipCardService;

class VipCardController extends AbstractController
{
    /**
     * 会员卡列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function vipCardList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $physicalStoreId = $this->request->query('physical_store_id');

            $params = ['physical_store_id'=>$physicalStoreId];
            $vipCardService = new VipCardService();
            $result = $vipCardService->vipCardList($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'vipCardList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 新人礼包会员卡
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function newcomerVipCardInfo(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $physicalStoreId = $this->request->query('physical_store_id');

            $params = ['physical_store_id'=>$physicalStoreId];
            $vipCardService = new VipCardService();
            $result = $vipCardService->newcomerVipCardInfo($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'newcomerVipCardInfo');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
