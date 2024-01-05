<?php

declare(strict_types=1);

namespace App\Service;

use App\Logger\Log;
use App\Model\Article;
use App\Model\ArticleTheme;
use App\Constants\ErrorCode;

class ArticleService extends BaseService
{

    /**
     * 帮助中心
     * @return array
     */
    public function helpCenter(): array
    {
        $articleThemeList = ArticleTheme::query()
            ->select(['id','name','img'])
            ->where(['type'=>1])
            ->get();
        $articleThemeList = $articleThemeList->toArray();

        $articleList = Article::query()->select(['id','article_theme_id','title'])->where(['type'=>1])->get();
        $articleList = $articleList->toArray();
        $articleList = $this->functions->arrayGroupBy($articleList,'article_theme_id');

        foreach($articleThemeList as $key=>$value){
            $id = $value['id'];
            $article = $articleList[$id] ?? [];
            $articleThemeList[$key]['article'] = array_slice($article,0,3);
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $articleThemeList];
    }

    /**
     * 关于我们
     * @return array
     */
    public function aboutUs(): array
    {
        $articleThemeList = ArticleTheme::query()
            ->select(['id','name'])
            ->where(['type'=>2])
            ->get();
        $articleThemeList = $articleThemeList->toArray();

        $articleList = Article::query()->select(['article_theme_id','content'])->where(['type'=>2])->get();
        $articleList = $articleList->toArray();
        $combineArticleKey = array_column($articleList,'article_theme_id');
        $articleList = array_combine($combineArticleKey,$articleList);

        foreach($articleThemeList as $key=>$value){
            $id = $value['id'];
            $article = $articleList[$id] ?? [];
            $articleThemeList[$key]['content'] = $article['content'];
        }
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $articleThemeList];
    }

    /**
     * 平台协议
     * @return array
     */
    public function platformAgreement(): array
    {
        $articleInfo = Article::query()->select(['content'])->where(['type'=>3])->first();
        if(empty($articleInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '数据错误', 'data' => null];
        }
        $articleInfo = $articleInfo->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $articleInfo];
    }

    /**
     * 文章主题列表
     * @param array $params
     * @return array
     */
    public function articleThemeList(array $params): array
    {
        $type = $params['type'];
        $articleThemeList = ArticleTheme::query()->select(['id','name'])->where(['type'=>$type])->get();
        $articleThemeList = $articleThemeList->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $articleThemeList];
    }

    /**
     * 文章列表
     * @param array $params
     * @return array
     */
    public function articleList(array $params): array
    {
        $articleThemeId = $params['article_theme_id'];
        $offset = $this->offset;
        $limit = $this->limit;

        $articleList = Article::query()
            ->select(['id','title'])
            ->where(['article_theme_id'=>$articleThemeId])
            ->offset($offset)->limit($limit)
            ->get();
        $articleList = $articleList->toArray();
        $count = Article::query()->where(['article_theme_id'=>$articleThemeId])->count();

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => ['list'=>$articleList,'count'=>$count]];
    }

    /**
     * 文章详情
     * @param int $id
     * @return array
     */
    public function articleDetail(int $id): array
    {
        $articleInfo = Article::query()->select(['title','content'])->where(['id'=>$id])->first();
        if(empty($articleInfo)){
            return ['code' => ErrorCode::WARNING, 'msg' => '数据错误', 'data' => null];
        }
        $articleInfo = $articleInfo->toArray();
        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $articleInfo];
    }

}