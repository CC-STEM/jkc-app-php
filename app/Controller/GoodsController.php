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

use App\Service\GoodsService;

class GoodsController extends AbstractController
{
    /**
     * 教具商品列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsGoodsList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $category = $this->request->query('category');
            $suitAge = $this->request->query('suit_age');
            $price = $this->request->query('price');
            $params = ['category'=>$category,'suit_age'=>$suitAge,'price'=>$price];
            $goodsService = new GoodsService();
            $goodsService->offset = $offset;
            $goodsService->limit = $pageSize;
            $result = $goodsService->teachingAidsGoodsList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingAidsGoodsList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 教具商品详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsGoodsDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');
            $goodsService = new GoodsService();
            $result = $goodsService->teachingAidsGoodsDetail((string)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingAidsGoodsDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 教具商品关联线上课程列表(翻页)
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function teachingAidsGoodsReachCourseOnlineList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $courseOnlineChildId = $this->request->query('course_online_child_id');
            $params = ['course_online_child_id'=>$courseOnlineChildId];
            $goodsService = new GoodsService();
            $goodsService->offset = $offset;
            $goodsService->limit = $pageSize;
            $result = $goodsService->teachingAidsGoodsReachCourseOnlineList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'teachingAidsGoodsReachCourseOnlineList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 商品小程序码
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function qRCode(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');

            $goodsService = new GoodsService();
            $result = $goodsService->qRCode((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'qRCode');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
