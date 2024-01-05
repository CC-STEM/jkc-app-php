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

use App\Service\ArticleService;

class ArticleController extends AbstractController
{
    /**
     * 帮助中心
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function helpCenter(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $articleService = new ArticleService();
            $result = $articleService->helpCenter();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'helpCenter');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 关于我们
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function aboutUs(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $articleService = new ArticleService();
            $result = $articleService->aboutUs();
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'aboutUs');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 平台协议
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function platformAgreement(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $articleService = new ArticleService();
            $result = $articleService->platformAgreement();
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'platformAgreement');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 文章主题列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function articleThemeList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $type = $this->request->query('type');

            $params = ['type'=>$type];
            $articleService = new ArticleService();
            $result = $articleService->articleThemeList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'articleThemeList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 文章列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function articleList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            [$page, $pageSize, $offset] = $this->getPagingParams();
            $articleThemeId = $this->request->query('article_theme_id');

            $params = ['article_theme_id'=>$articleThemeId];
            $articleService = new ArticleService();
            $articleService->offset = $offset;
            $articleService->limit = $pageSize;
            $result = $articleService->articleList($params);
            $data = [
                'list' => $result['data']['list'],
                'page' => ['page' => $page, 'page_size' => $pageSize,'count'=>$result['data']['count']],
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'articleList');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

    /**
     * 文章详情
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function articleDetail(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $id = $this->request->query('id');
            $articleService = new ArticleService();
            $result = $articleService->articleDetail((int)$id);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'articleDetail');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}
