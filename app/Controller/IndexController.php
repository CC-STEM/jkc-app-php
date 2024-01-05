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

use App\Service\HomeService;

class IndexController extends AbstractController
{
    /**
     * 首页 banner
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function banner(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $homeService = new HomeService();
            $result = $homeService->banner();
            $data = [
                'list' => $result['data'],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'banner');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 首页推荐课程
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function boutiqueCourse(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $homeService = new HomeService();
            $homeService->offset = $offset;
            $homeService->limit = $pageSize;
            $result = $homeService->boutiqueCourse();
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'boutiqueCourse');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
