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

use App\Service\CourseCategoryService;

class CourseCategoryController extends AbstractController
{
    /**
     * 线上课程分类列表
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function courseOnlineCategoryList(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $parentId = $this->request->query('parent_id',0);

            $params = ['parent_id'=>$parentId];
            $courseCategoryService = new CourseCategoryService();
            $result = $courseCategoryService->courseOnlineCategoryList($params);
            $data = [
                'list' => $result['data']
            ];
        } catch (\Throwable $e) {
            return $this->responseError($e,'courseOnlineCategoryList');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
