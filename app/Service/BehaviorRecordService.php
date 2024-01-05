<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\ShareRecord;
use App\Model\VisitRecord;
use App\Snowflake\IdGenerator;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class BehaviorRecordService extends BaseService
{

    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 用户访问记录
     * @param int|null $memberId
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function visitRecord(?int $memberId=null): array
    {
        $memberId = $memberId ?? $this->memberId;

        go(function ()use($memberId){
            $visitDate = date('Ymd');
            $visitRecordInfo = VisitRecord::query()
                ->select(['id'])
                ->where(['member_id'=>$memberId,'visit_date'=>$visitDate])
                ->first();
            if(empty($visitRecordInfo)){
                $insertVisitRecordData['id'] = IdGenerator::generate();
                $insertVisitRecordData['member_id'] = $memberId;
                $insertVisitRecordData['visit_date'] = $visitDate;
                VisitRecord::query()->insert($insertVisitRecordData);
            }
        });
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

    /**
     * 用户分享记录
     * @param array $params
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function shareRecord(array $params): array
    {
        $type = $params['type'];
        $memberId = $this->memberId;

        go(function ()use($memberId,$type){
            if(empty($memberId)){
                return;
            }
            $insertShareRecordData['id'] = IdGenerator::generate();
            $insertShareRecordData['member_id'] = $memberId;
            $insertShareRecordData['type'] = $type;
            ShareRecord::query()->insert($insertShareRecordData);
        });
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }

}

