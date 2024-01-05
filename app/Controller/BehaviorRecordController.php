<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BehaviorRecordService;

class BehaviorRecordController extends AbstractController
{
    /**
     * 用户分享记录
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function shareRecord(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $params = $this->request->post();
            $behaviorRecordService = new BehaviorRecordService();
            $result = $behaviorRecordService->shareRecord($params);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'shareRecord');
        }

        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }

}


