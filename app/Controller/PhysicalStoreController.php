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

use App\Service\PhysicalStoreService;

class PhysicalStoreController extends AbstractController
{
    /**
     * 门店列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function physicalStoreList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $longitude = $this->request->query('longitude');
            $latitude = $this->request->query('latitude');

            $params = ['longitude'=>$longitude,'latitude'=>$latitude];
            $physicalStoreService = new PhysicalStoreService();
            $result = $physicalStoreService->physicalStoreList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'physicalStoreList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 门店详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function physicalStoreDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');
            $longitude = $this->request->query('longitude');
            $latitude = $this->request->query('latitude');

            $params = ['id'=>$id,'longitude'=>$longitude,'latitude'=>$latitude];
            $physicalStoreService = new PhysicalStoreService();
            $result = $physicalStoreService->physicalStoreDetail($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'physicalStoreDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
