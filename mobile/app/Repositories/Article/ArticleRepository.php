<?php

namespace App\Repositories\Article;

use App\Models\Article;
use App\Models\ArticleExtend;
use App\Contracts\Repositories\Article\ArticleRepositoryInterface;

class ArticleRepository implements ArticleRepositoryInterface
{
    /**
     * @param $cat_id
     * @param array $columns
     * @param int $size
     * @param string $requirement
     * @return mixed
     */
    public function all($cat_id, $columns = ['*'],  $page, $size)
    {
        if ($page == 1) {
            $current = 0;
        } else {
            $current = $page - 1;
        }
        $article = Article::where('is_open', '=', 1);

        // 取出所有非0的文章
        if ($cat_id == '-1') {
            $article = $article->where('cat_id', '>', 0);
        } else {
            //            $cat_str = get_article_children($cat_id);
            $article = $article->where('cat_id', $cat_id);
        }

        $article = $article->orderBy('add_time', 'DESC')
            ->offset($current)->limit($size)
            ->orderBy('article_id', 'DESC')
            ->paginate($current)
            ->toArray();

        foreach ($article['data'] as $key => $val) {
            $default = ['click' => 1, 'likenum' => 0, 'hatenum' => 0];
            $extend = ArticleExtend::where('article_id', $val['id'])->first();
            unset($extend['id']);
            $article['data'][$key] = array_merge($article['data'][$key], is_null($extend) ? $default : $extend->toArray());
        }

        return $article;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function detail($id)
    {
        if (is_array($id)) {
            $field = key($id);
            $value = $id[$field];
            $model = Article::where($field, '=', $value)->first();
        } else {
            $model = Article::find($id);
        }

        if (is_null($model)) {
            return false;
        }

        $article = $model->toArray();

        // 关联扩展字段
        if (is_null($model->extend)) {
            $data = [
                'article_id' => $model->article_id,
                'click' => 1,
                'likenum' => 0,
                'hatenum' => 0,
            ];
            ArticleExtend::create($data);
        } else {
            $data = $model->extend->toArray();
            unset($data['id']);
        }
        // 合并扩展字段
        $article = array_merge($article, $data);

        // 关联评论
        foreach ($model->comment as $vo) {
            $model->comment->push($vo->user);
        }
        $article['comment'] = $model->comment->where('id_value', '=', $id)->where('status', '=', 1)->toArray();
        // 关联商品
        $article['goods'] = $model->goods->toArray();
        return $article;
    }

    /**
     * @return mixed
     */
    public function create($data)
    {
        return false;
    }

    /**
     * @return mixed
     */
    public function update($data)
    {
        return false;
    }

    /**
     * @return mixed
     */
    public function delete($id)
    {
        return false;
    }
}
