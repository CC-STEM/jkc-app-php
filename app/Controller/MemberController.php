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

use App\Service\MemberService;

class MemberController extends AbstractController
{
    /**
     * 会员中心
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function memberCenter(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $memberService = new MemberService();
            $result = $memberService->memberCenter();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'memberCenter');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 设置用户资料
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function setMemberData(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $memberService = new MemberService();
            $result = $memberService->setMemberData($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'setMemberData');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 会员资料
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function getMemberData(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $memberService = new MemberService();
            $result = $memberService->getMemberData();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'getMemberData');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 会员信息
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function memberInfo(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $memberService = new MemberService();
            $result = $memberService->memberInfo();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'memberInfo');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 用户会员卡账户
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function memberVipCard(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $memberService = new MemberService();
            $result = $memberService->memberVipCard();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'memberVipCard');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 用户体验卡账户
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function memberSampleVipCard(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $memberService = new MemberService();
            $result = $memberService->memberSampleVipCard();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'memberSampleVipCard');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 会员位置
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function memberLocation(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $longitude = $this->request->query('longitude');
            $latitude = $this->request->query('latitude');

            $params = ['longitude'=>$longitude,'latitude'=>$latitude];
            $memberService = new MemberService();
            $result = $memberService->memberLocation($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'memberLocation');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 会员小程序码
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function qRCode(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $type = $this->request->query('type');

            $params = ['type'=>$type];
            $memberService = new MemberService();
            $result = $memberService->qRCode($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'qRCode');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 绑定上级
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function bindSuperior(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $memberService = new MemberService();
            $result = $memberService->bindSuperior($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'bindSuperior');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
