<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\MarketInfo;
use App\Constants\ErrorCode;

class MarketService extends BaseService
{

    /**
     * 营销信息列表
     * @return array
     */
    public function marketInfoList(): array
    {
        $offset = $this->offset;
        $limit = $this->limit;
        $nowDate = date('Y-m-d H:i:s');

        $marketInfoList = MarketInfo::query()
            ->select(['id','name','img_url','start_at','end_at'])
            ->where([['start_at','<=',$nowDate]])
            ->offset($offset)->limit($limit)
            ->get();
        $marketInfoList = $marketInfoList->toArray();
        $count = MarketInfo::query()->count();

        foreach($marketInfoList as $key=>$value){
            $status = 0;
            if($value['start_at'] <= $nowDate && $value['end_at'] > $nowDate){
                $status = 1;
            }else if($value['end_at'] <= $nowDate){
                $status = 2;
            }
            $marketInfoList[$key]['status'] = $status;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$marketInfoList,'count'=>$count]];
    }

    /**
     * 营销信息详情
     * @param int $id
     * @return array
     */
    public function marketInfoDetail(int $id): array
    {
        $marketInfo = MarketInfo::query()->select(['name','describe'])->where(['id'=>$id])->first();
        if(empty($marketInfo)){
            return ['code' => ErrorCode::SUCCESS, 'msg' => '活动结束了', 'data' => null];
        }
        $marketInfo = $marketInfo->toArray();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $marketInfo];
    }

}