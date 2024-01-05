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

use App\Service\MemberAddressService;

class MemberAddressController extends AbstractController
{
    /**
     * 添加收货地址
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function addMemberAddress(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $memberAddressService = new MemberAddressService();
            $result = $memberAddressService->addMemberAddress($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addMemberAddress');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 修改收货地址
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function updateMemberAddress(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $memberAddressService = new MemberAddressService();
            $result = $memberAddressService->updateMemberAddress($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'addMemberAddress');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 收货地址详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function memberAddressDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $memberAddressService = new MemberAddressService();
            $result = $memberAddressService->memberAddressDetail();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'memberAddressDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 地区数据
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function getRegionTree(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $memberAddressService = new MemberAddressService();
            $result = $memberAddressService->getRegionTree();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'getRegionsTree');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }


}
