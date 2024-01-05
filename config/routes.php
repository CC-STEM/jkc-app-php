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
use Hyperf\HttpServer\Router\Router;
use App\Middleware\AuthMiddleware;

//登录
Router::addGroup('/login/',function (){
    Router::post('wxmini_session',[\App\Controller\AuthController::class, 'wxMiniProgramSession']);
    Router::post('wxmini_mobile',[\App\Controller\AuthController::class, 'wxMiniProgramMobile']);

});

//退出登录
Router::addGroup('/sign_out/',function (){
    Router::post('wxmini_mobile',[\App\Controller\SignOutController::class, 'wxMiniProgramMobileSignOut']);

}, ['middleware' => [AuthMiddleware::class]]);

//首页
Router::addGroup('/home/',function (){
    Router::get('banner', [\App\Controller\IndexController::class, 'banner']);
    Router::get('course', [\App\Controller\IndexController::class, 'boutiqueCourse']);

});

//会员
Router::addGroup('/member/',function (){
    Router::get('info',[\App\Controller\MemberController::class, 'memberInfo']);
    Router::get('center',[\App\Controller\MemberController::class, 'memberCenter']);
    Router::get('qr_code',[\App\Controller\MemberController::class, 'qRCode'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('vip_card',[\App\Controller\MemberController::class, 'memberVipCard'], ['middleware' => [AuthMiddleware::class]]);
    Router::post('set_member_data',[\App\Controller\MemberController::class, 'setMemberData'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('member_data',[\App\Controller\MemberController::class, 'getMemberData'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('sample_vip_card',[\App\Controller\MemberController::class, 'memberSampleVipCard']);
    Router::post('bind_superior',[\App\Controller\MemberController::class, 'bindSuperior']);

});

//门店
Router::addGroup('/store/',function (){
    Router::get('list',[\App\Controller\PhysicalStoreController::class, 'physicalStoreList']);
    Router::get('detail',[\App\Controller\PhysicalStoreController::class, 'physicalStoreDetail']);

});

//课程分类
Router::addGroup('/course_category/',function (){
    Router::get('list_online',[\App\Controller\CourseCategoryController::class, 'courseOnlineCategoryList']);

});

//线下课程
Router::addGroup('/offline_course/',function (){
    Router::get('list',[\App\Controller\CourseController::class, 'courseOfflineList']);
    Router::get('detail',[\App\Controller\CourseController::class, 'courseOfflineDetail']);
    Router::get('store_course_plan_calendar',[\App\Controller\CourseController::class, 'physicalStoreCourseOfflinePlanCalendar']);
    Router::get('store_course_plan',[\App\Controller\CourseController::class, 'physicalStoreCourseOfflinePlan']);
    Router::get('plan_detail',[\App\Controller\CourseController::class, 'courseOfflinePlanDetail']);
    Router::get('package',[\App\Controller\CourseController::class, 'courseOfflinePackage']);
    Router::get('batch_reservation_detail',[\App\Controller\CourseController::class, 'courseOfflineBatchReservationDetail']);
    Router::get('course_detail_set_up_list',[\App\Controller\CourseController::class, 'courseDetailSetUpList']);
    Router::get('age_tag',[\App\Controller\CourseController::class, 'courseOfflineAgeTag']);

});

//线下课程订单
Router::addGroup('/offline_course_order/',function (){
    Router::get('list',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderList']);
    Router::post('confirm',[\App\Controller\CourseOrderController::class, 'courseOfflineConfirmOrder'], ['middleware' => [AuthMiddleware::class]]);
    Router::post('submit',[\App\Controller\CourseOrderController::class, 'courseOfflineCreateOrder'], ['middleware' => [AuthMiddleware::class]]);
    Router::post('readjust',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderReadjust'], ['middleware' => [AuthMiddleware::class]]);
    Router::post('cancel',[\App\Controller\CourseOrderController::class, 'courseOfflineOrderCancel'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('course_feedback_list',[\App\Controller\CourseOrderController::class, 'courseOfflineFeedbackList']);

});

//线上课程
Router::addGroup('/online_course/',function (){
    Router::get('list',[\App\Controller\CourseController::class, 'courseOnlineList']);
    Router::get('detail',[\App\Controller\CourseController::class, 'courseOnlineDetail']);
    Router::get('child_list',[\App\Controller\CourseController::class, 'courseOnlineChildList']);
    Router::get('child_detail',[\App\Controller\CourseController::class, 'courseOnlineChildDetail']);
    Router::post('add_collect',[\App\Controller\CourseController::class, 'addCourseOnlineCollect'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('collect_list',[\App\Controller\CourseController::class, 'courseOnlineCollectList']);
    Router::post('del_collect',[\App\Controller\CourseController::class, 'deleteCourseOnlineCollect'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('collect_detail',[\App\Controller\CourseController::class, 'courseOnlineCollectDetail']);
    Router::get('collect_child_detail',[\App\Controller\CourseController::class, 'courseOnlineChildCollectDetail']);
    Router::post('add_study_opus',[\App\Controller\CourseController::class, 'addCourseOnlineChildCollectStudyOpus'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('study_opus_share',[\App\Controller\CourseController::class, 'courseOnlineChildCollectStudyOpusShare'], ['middleware' => [AuthMiddleware::class]]);

});

//教具商品
Router::addGroup('/ta_goods/',function (){
    Router::get('list',[\App\Controller\GoodsController::class, 'teachingAidsGoodsList']);
    Router::get('detail',[\App\Controller\GoodsController::class, 'teachingAidsGoodsDetail']);
    Router::get('reach_course_list',[\App\Controller\GoodsController::class, 'teachingAidsGoodsReachCourseOnlineList']);
    Router::get('qr_code',[\App\Controller\GoodsController::class, 'qRCode']);

});

//教具商品订单
Router::addGroup('/ta_order/',function (){
    Router::post('confirm',[\App\Controller\OrderController::class, 'teachingAidsGoodsOrderConfirm'], ['middleware' => [AuthMiddleware::class]]);
    Router::post('add',[\App\Controller\OrderController::class, 'teachingAidsGoodsCreateOrder'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('list',[\App\Controller\OrderController::class, 'teachingAidsOrderList']);
    Router::get('detail',[\App\Controller\OrderController::class, 'teachingAidsOrderDetail']);
    Router::post('refund_apply',[\App\Controller\OrderController::class, 'teachingAidsOrderRefundApply'], ['middleware' => [AuthMiddleware::class]]);
    Router::post('add_refund_package',[\App\Controller\OrderController::class, 'addTeachingAidsOrderRefundPackage'], ['middleware' => [AuthMiddleware::class]]);
    Router::post('cancel_refund_apply',[\App\Controller\OrderController::class, 'cancelTeachingAidsOrderRefundApply'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('refund_reason',[\App\Controller\OrderController::class, 'refundReasonList']);
});

//收货地址
Router::addGroup('/address/',function (){
    Router::post('add',[\App\Controller\MemberAddressController::class, 'addMemberAddress'], ['middleware' => [AuthMiddleware::class]]);
    Router::post('edit',[\App\Controller\MemberAddressController::class, 'updateMemberAddress'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('detail',[\App\Controller\MemberAddressController::class, 'memberAddressDetail']);
    //Router::get('region_tree',[\App\Controller\MemberAddressController::class, 'getRegionTree']);

});

//会员卡
Router::addGroup('/vip_card/',function (){
    Router::get('list',[\App\Controller\VipCardController::class, 'vipCardList']);
    Router::post('confirm',[\App\Controller\VipCardOrderController::class, 'vipCardOrderConfirm']);
    Router::post('buy',[\App\Controller\VipCardOrderController::class, 'vipCardOrderCreate'], ['middleware' => [AuthMiddleware::class]]);
    Router::get('order_list',[\App\Controller\VipCardOrderController::class, 'vipCardOrderList']);
    Router::get('newcomer',[\App\Controller\VipCardController::class, 'newcomerVipCardInfo']);

});

//营销活动
Router::addGroup('/market/',function (){
    Router::get('list',[\App\Controller\MarketController::class, 'marketInfoList']);
    Router::get('detail',[\App\Controller\MarketController::class, 'marketInfoDetail']);

});

//文章
Router::addGroup('/article/',function (){
    Router::get('help',[\App\Controller\ArticleController::class, 'helpCenter']);
    Router::get('about_us',[\App\Controller\ArticleController::class, 'aboutUs']);
    Router::get('platform_agreement',[\App\Controller\ArticleController::class, 'platformAgreement']);
    Router::get('theme_list',[\App\Controller\ArticleController::class, 'articleThemeList']);
    Router::get('list',[\App\Controller\ArticleController::class, 'articleList']);
    Router::get('detail',[\App\Controller\ArticleController::class, 'articleDetail']);

});

//减免券
Router::addGroup('/discount_ticket/',function (){
    Router::get('participate_info',[\App\Controller\DiscountTicketController::class, 'discountTicketParticipateInfo']);
    Router::get('marketing_info',[\App\Controller\DiscountTicketController::class, 'discountTicketMarketingInfo']);
    Router::get('list',[\App\Controller\DiscountTicketController::class, 'discountTicketList']);

});

//老师端
Router::addGroup('/teacher_identity/',function (){
    Router::get('course_statistics',[\App\Controller\TeacherIdentityController::class, 'teacherCourseStatistics']);
    Router::get('course_list',[\App\Controller\TeacherIdentityController::class, 'teacherCourseList']);
    Router::get('course_detail',[\App\Controller\TeacherIdentityController::class, 'teacherCourseDetail']);
    Router::post('roll_call',[\App\Controller\TeacherIdentityController::class, 'teacherRollCall']);
    Router::post('add_classroom_situation',[\App\Controller\TeacherIdentityController::class, 'addClassroomSituation']);
    Router::get('salary_statistics',[\App\Controller\TeacherIdentityController::class, 'teacherSalaryStatistics']);
    Router::get('salary_detailed',[\App\Controller\TeacherIdentityController::class, 'teacherSalaryDetailed']);
    Router::get('salary_detailed_list',[\App\Controller\TeacherIdentityController::class, 'teacherSalaryDetailedList']);
    Router::get('teacher_info',[\App\Controller\TeacherIdentityController::class, 'teacherIdentityInfo']);

}, ['middleware' => [AuthMiddleware::class]]);

//店长端
Router::addGroup('/store_manager/',function (){
    Router::get('manage_physical_store',[\App\Controller\StoreManagerIdentityController::class, 'managePhysicalStoreList']);
    Router::post('selected_physical_store',[\App\Controller\StoreManagerIdentityController::class, 'selectedPhysicalStore']);
    Router::get('store_revenue_statistics',[\App\Controller\StoreManagerIdentityController::class, 'storeRevenueStatistics']);
    Router::get('store_today_statistics',[\App\Controller\StoreManagerIdentityController::class, 'storeTodayStatistics']);
    Router::get('store_sample_course_offline',[\App\Controller\StoreManagerIdentityController::class, 'storeSampleCourseOfflineOrder']);
    Router::get('store_daily_statistics',[\App\Controller\StoreManagerIdentityController::class, 'storeDailyStatistics']);
    Router::get('store_daily_detail',[\App\Controller\StoreManagerIdentityController::class, 'storeDailyDetail']);
    Router::get('course_offline_plan_detail',[\App\Controller\StoreManagerIdentityController::class, 'courseOfflinePlanDetail']);
    Router::get('store_curriculum',[\App\Controller\StoreManagerIdentityController::class, 'storeCurriculum']);
    Router::get('store_business_analysis',[\App\Controller\StoreManagerIdentityController::class, 'storeBusinessAnalysis']);
    Router::get('store_teacher_manage',[\App\Controller\StoreManagerIdentityController::class, 'storeTeacherManage']);
    Router::get('store_member_manage',[\App\Controller\StoreManagerIdentityController::class, 'storeMemberManage']);
    Router::get('store_member_list',[\App\Controller\StoreManagerIdentityController::class, 'storeMemberList']);
    Router::get('store_member_detail',[\App\Controller\StoreManagerIdentityController::class, 'storeMemberDetail']);
    Router::get('member_course_offline_order',[\App\Controller\StoreManagerIdentityController::class, 'storeMemberCourseOfflineOrderList']);
    Router::get('teacher_list',[\App\Controller\StoreManagerIdentityController::class, 'physicalStoreTeacherList']);
    Router::post('set_teacher_revenue',[\App\Controller\StoreManagerIdentityController::class, 'setTeacherRevenueTargetAmount']);
    Router::post('vip_card_order_extension',[\App\Controller\StoreManagerIdentityController::class, 'vipCardOrderExtension']);

}, ['middleware' => [AuthMiddleware::class]]);

//学习计划
Router::addGroup('/study_plan/',function (){
    Router::post('enrollment',[\App\Controller\StudyPlanController::class, 'studyPlanEnrollment']);

}, ['middleware' => [AuthMiddleware::class]]);

//用户行为记录
Router::addGroup('/behavior_record/',function (){
    Router::post('share',[\App\Controller\BehaviorRecordController::class, 'shareRecord']);

});

//微信消息
Router::addGroup('/wx_message/',function (){
    Router::post('mp_subscribe',[\App\Controller\MessageController::class, 'mpSubscribeMessage']);

});

//线下课程评价
Router::addGroup('/offline_course_evaluation/',function (){
    Router::post('add',[\App\Controller\CourseEvaluationController::class, 'addCourseOfflineOrderEvaluation']);
    Router::get('detail',[\App\Controller\CourseEvaluationController::class, 'courseOfflineOrderEvaluationDetail']);

});

//支付
Router::addGroup('/pay/callback/',function (){
    Router::post('wxmini',[\App\Controller\PayController::class, 'wxMiniProgramCallback']);
    Router::post('alipay',[\App\Controller\PayController::class, 'aLiPayCallback']);
});

//文件上传
Router::addGroup('/upload/',function (){
    Router::post('cos',[\App\Controller\UploadController::class, 'cosUpload']);

}, ['middleware' => [AuthMiddleware::class]]);

//短信
Router::addGroup('/sms/',function (){
    Router::post('verify',[\App\Controller\SmsController::class, 'loginSmsSend']);

}, ['middleware' => [AuthMiddleware::class]]);

Router::get('/favicon.ico', function () {
    return '';
});
