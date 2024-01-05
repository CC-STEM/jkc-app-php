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

use App\Service\UploadService;

class UploadController extends AbstractController
{
    /**
     * 腾讯云文件上传
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function cosUpload(): \Psr\Http\Message\ResponseInterface
    {
        try {
            ini_set('memory_limit','-1');
            set_time_limit(0);
            if (!$this->request->hasFile('file')) {
                return $this->responseSuccess(null,'请选择上传的文件');
            }
            $file = $this->request->file('file')->toArray();
            $UploadService = new UploadService();
            $extension = $this->request->file('file')->getExtension();
            $result = $UploadService->cosUpload($file,$extension);
            $data = $result['data'];
        } catch (\Throwable $e) {
            return $this->responseError($e,'cosUpload');
        }
        return $this->responseSuccess($data,$result['msg'],$result['code']);
    }
}
