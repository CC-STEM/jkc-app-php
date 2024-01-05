<?php

declare(strict_types=1);

namespace App\Service;

use App\Common\Image;
use App\Lib\WeChat\MiniProgramFactory;
use App\Logger\Log;
use App\Model\CourseOnlineChild;
use App\Model\Goods;
use App\Model\GoodsSku;
use App\Model\GoodsFile;
use App\Model\GoodsPropReach;
use App\Constants\ErrorCode;
use App\Model\Member;
use App\Model\MemberGoodsInviteCode;
use Hyperf\Utils\Context;

class GoodsService extends BaseService
{

    /**
     * GoodsService constructor.
     */
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 教具商品列表
     * @param array $params
     * @return array
     */
    public function teachingAidsGoodsList(array $params): array
    {
        $category = $params['category'];
        $suitAge = $params['suit_age'];
        $price = $params['price'];
        $offset = $this->offset;
        $limit = $this->limit;

        $goodsModel = Goods::query();
        $where = [['online','=',1],['is_deleted','=',0]];
        if($category !== null){
            $where[] = ['category_id','=',$category];
        }
        if($suitAge !== null){
            [$searchAgeMin,$searchAgeMax] = explode('-',$suitAge);
            $goodsModel->whereBetween('suit_age_min',[$searchAgeMin,$searchAgeMax]);
        }
        if($price !== null){
            $priceMax = strstr($price,'及以上',true);
            if($priceMax === false){
                [$searchPriceMin,$searchPriceMax] = explode('-',$price);
                $goodsModel->whereBetween('min_price',[$searchPriceMin,$searchPriceMax]);
            }else{
                $where[] = ['min_price','>=',$priceMax];
            }
        }
        $count = $goodsModel->where($where)->count();
        $goodsList = $goodsModel
            ->select(['id','name','img_url','min_price','csale','suit_age_min','suit_age_max','sham_csale'])
            ->where($where)
            ->offset($offset)->limit($limit)
            ->orderBy('sort','desc')
            ->get();
        $goodsList = $goodsList->toArray();

        foreach($goodsList as $key=>$value){
            $minPrice = $value['min_price'];
            $minPrice = rtrim($minPrice,'0');
            $minPrice = rtrim($minPrice,'.');

            $goodsList[$key]['csale'] = $value['csale']+$value['sham_csale'];
            $goodsList[$key]['min_price'] = $minPrice;
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$goodsList,'count'=>$count]];
    }

    /**
     * 教具商品详情
     * @param string $id
     * @return array
     */
    public function teachingAidsGoodsDetail(string $sKey): array
    {
        if(strlen($sKey) === 6){
            $goodsInfo = Goods::query()
                ->select(['id','name','img_url','video_url','min_price','csale','describe','course_online_child_id','sham_csale'])
                ->where(['invite_code'=>$sKey,'is_deleted'=>0,'online'=>1])
                ->first();
        }else{
            $goodsInfo = Goods::query()
                ->select(['id','name','img_url','video_url','min_price','csale','describe','course_online_child_id','sham_csale'])
                ->where(['id'=>$sKey,'is_deleted'=>0,'online'=>1])
                ->first();
        }
        if(empty($goodsInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '商品已下架', 'data' => null];
        }
        $goodsInfo = $goodsInfo->toArray();
        $id = $goodsInfo['id'];


        //关联教程视频
        $courseOnlineChildList = [];
        $courseOnlineChildIdArray = !empty($goodsInfo['course_online_child_id']) ? json_decode($goodsInfo['course_online_child_id'],true): [];
        if(!empty($courseOnlineChildIdArray)){
            $courseOnlineChildList = CourseOnlineChild::query()
                ->select(['id','video_url','img_url'])
                ->whereIn('id',$courseOnlineChildIdArray)
                ->offset(0)->limit(3)
                ->get();
            $courseOnlineChildList = $courseOnlineChildList->toArray();
        }

        //商品sku
        $goodsSkuList = GoodsSku::query()->where(['goods_id'=>$id])->select(['id','prop_value_str','img_url','price'])->get();
        if(empty($goodsSkuList)){
            return ['code' => ErrorCode::WARNING, 'msg' => '商品已下架', 'data' => null];
        }
        $goodsSkuList = $goodsSkuList->toArray();

        //商品展示规格
        $goodsPropReachList = GoodsPropReach::query()
            ->select(['prop_name','prop_value'])
            ->where(['goods_id'=>$id])
            ->orderBy('sort','asc')
            ->get();
        $goodsPropReachList = $goodsPropReachList->toArray();
        $goodsPropReachList = $this->functions->arrayGroupBy($goodsPropReachList,'prop_name');
        $newGoodsPropReachList = [];
        foreach($goodsPropReachList as $value){
            $newGoodsPropReachList[] = $value;
        }

        //商品文件
        $goodsFileList = GoodsFile::query()->where(['goods_id'=>$id,'scene_type'=>1])->select(['url'])->get();
        $goodsFileList = $goodsFileList->toArray();

        $minPrice = $goodsInfo['min_price'];
        $minPrice = rtrim($minPrice,'0');
        $minPrice = rtrim($minPrice,'.');

        $goodsInfo['csale'] = $goodsInfo['csale']+$goodsInfo['sham_csale'];
        $goodsInfo['min_price'] = $minPrice;
        $goodsInfo['sku'] = $goodsSkuList;
        $goodsInfo['prop'] = $newGoodsPropReachList;
        $goodsInfo['imgs'] = $goodsFileList;
        $goodsInfo['teach_video'] = $courseOnlineChildList;
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $goodsInfo];
    }

    /**
     * 教具商品关联线上课程列表(翻页)
     * @param array $params
     * @return array
     */
    public function teachingAidsGoodsReachCourseOnlineList(array $params): array
    {
        $courseOnlineChildId = $params['course_online_child_id'];
        $offset = $this->offset;

        $courseOnlineChildIdArray = !empty($courseOnlineChildId) ? json_decode($courseOnlineChildId,true): [];
        if(empty($courseOnlineChildIdArray)){
            return ['code' => ErrorCode::WARNING, 'msg' => '视频查看失败', 'data' => null];
        }
        $courseOnlineChildList = CourseOnlineChild::query()
            ->leftJoin('course_online','course_online_child.course_online_id','=','course_online.id')
            ->select(['course_online_child.id','course_online_child.course_online_id','course_online_child.name','course_online_child.video_url','course_online_child.describe','course_online_child.img_url','course_online.suit_age_min','course_online.suit_age_max','course_online.type'])
            ->whereIn('course_online_child.id',$courseOnlineChildIdArray)
            ->offset($offset)->limit(1)
            ->get();
        $courseOnlineChildList = $courseOnlineChildList->toArray();
        $count = count($courseOnlineChildIdArray);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$courseOnlineChildList,'count'=>$count]];
    }

    /**
     * 商品小程序码
     * @param int $id
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function qRCode(int $id): array
    {
        $memberId = $this->memberId;

        $memberGoodsInviteCodeInfo = MemberGoodsInviteCode::query()
            ->select(['qc_code'])
            ->where(['member_id'=>$memberId,'goods_id'=>$id])
            ->first();

        if(empty($memberGoodsInviteCodeInfo)){
            $goodsInfo = Goods::query()
                ->select(['invite_code'])
                ->where(['id'=>$id])
                ->first();
            if(empty($goodsInfo)){
                return ['code' => ErrorCode::WARNING, 'msg' => '商品信息异常', 'data' => null];
            }
            $goodsInfo = $goodsInfo->toArray();
            $goodsInviteCode = $goodsInfo['invite_code'];
            if(empty($goodsInviteCode)){
                return ['code' => ErrorCode::WARNING, 'msg' => '商品分享码获取失败', 'data' => null];
            }

            $memberInfo = Member::query()
                ->select(['avatar','invite_code'])
                ->where(['id'=>$memberId])
                ->first();
            if(empty($memberInfo)){
                return ['code' => ErrorCode::WARNING, 'msg' => '登录信息异常', 'data' => null];
            }
            $memberInfo = $memberInfo->toArray();
            $memberInviteCode = $memberInfo['invite_code'];
            if(empty($memberInviteCode)){
                return ['code' => ErrorCode::WARNING, 'msg' => '分享码获取失败', 'data' => null];
            }
            $page = "pages/tool/details/index";
            $scene = "m={$memberInviteCode}&t=3&p={$goodsInviteCode}";

            $image = new Image();
            $miniProgramFactory = new MiniProgramFactory();
            $qRCode = $miniProgramFactory->getUnlimitedQRCode($scene,$page);
            if(strstr($qRCode,'errcode') !== false){
                Log::get()->info('qRCodeGoods:'.$qRCode);
                return ['code' => ErrorCode::WARNING, 'msg' => '小程序码获取失败', 'data' => null];
            }
            //用户头像图片变圆形
            $avatar = file_get_contents('https://jkc-1313504415.cos.ap-shanghai.myqcloud.com/'.$memberInfo['avatar']);
            if($avatar === false){
                $avatar = file_get_contents('https://jkc-1313504415.cos.ap-shanghai.myqcloud.com/wxmini_static/images/logo.png');
            }
            $logo = $image->toCircleImage($avatar);
            //二维码与头像结合
            $sharePic = $image->qrcodeWithLogo($qRCode,$logo);
            $randStr = intval(microtime(true) * 1000).'-'.$memberId;
            $randStr = md5($randStr);
            $path = "/tmp/{$randStr}.png";
            $path2 = "/tmp/{$randStr}-2.png";
            file_put_contents($path, $sharePic);
            $image->toHyalineImage($path,$path2);

            $file['tmp_file'] = $path2;
            $uploadService = new UploadService();
            $uploadService->setBucketDir('short_term');
            $result = $uploadService->cosUpload($file,'png');
            MemberGoodsInviteCode::query()->insert(['member_id'=>$memberId,'goods_id'=>$id,'qc_code'=>$result['data']['key']]);
            $qcCode = $result['data']['key'];
            unlink($path);
            unlink($path2);
        }else{
            $memberGoodsInviteCodeInfo = $memberGoodsInviteCodeInfo->toArray();
            $qcCode = $memberGoodsInviteCodeInfo['qc_code'];
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['qc_code'=>$qcCode]];
    }
}