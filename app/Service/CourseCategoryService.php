<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\CourseOnlineCategory;
use App\Constants\ErrorCode;

class CourseCategoryService extends BaseService
{
    /**
     * 线上课程分类列表
     * @param array $params
     * @return array
     */
    public function courseOnlineCategoryList(array $params): array
    {
        $parentId = $params['parent_id'];

        $courseOnlineCategoryList = CourseOnlineCategory::query()
            ->select(['id','name'])
            ->where(['parent_id'=>$parentId])
            ->get();
        $courseOnlineCategoryList = $courseOnlineCategoryList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $courseOnlineCategoryList];
    }
}