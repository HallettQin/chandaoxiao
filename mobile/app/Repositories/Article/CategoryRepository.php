<?php

namespace App\Repositories\Article;

use App\Models\ArticleCat;
use App\Contracts\Repositories\Article\CategoryRepositoryInterface;

class CategoryRepository implements CategoryRepositoryInterface
{

    /**
     * 返回指定文章类别的子类
     * @param $cat_id
     * @param array $columns
     * @param int $size
     * @return mixed
     */
    public function all($cat_id = 0, $columns = ['*'], $size = 100)
    {
        if (is_array($cat_id)) {
            $field = key($cat_id);
            $value = $cat_id[$field];
            $model = ArticleCat::where($field, '=', $value)->where('parent_id', 0);
        } else {
            $model = ArticleCat::where('parent_id', $cat_id);
        }

        $category = $model->orderBy('sort_order')
            ->orderBy('cat_id')
            ->paginate($size, $columns)
            ->toArray();

        foreach($category['data'] as $key => $val){

            $category['data'][$key]['child'] = $this->article_category_child($val['id']);
        }
        return $category;
    }

    public function article_category_child($parent_id)
    {
        $res = ArticleCat::where('parent_id', $parent_id)
                ->get()
                ->toArray();
        $arr = [];
        foreach ($res as $key => $row) {
            $arr[$key]['cat_id'] = $row['id'];
            $arr[$key]['cat_name'] = $row['cat_name'];
            $arr[$key]['url'] = url('article', ['id' => $row['id'], 'urid' => $ru_id], $row['cat_name']);
            $arr[$key]['child'] = $this->article_category_child($row['cat_id']);
        }
        return $arr;
    }

    /**
     * 返回指定文章类别的详情
     * @param $cat_id
     * @param array $columns
     * @return mixed
     */
    public function detail($cat_id, $columns = ['*'])
    {
        if (is_array($cat_id)) {
            $field = key($cat_id);
            $value = $cat_id[$field];
            $model = ArticleCat::where($field, '=', $value)->first($columns);
        } else {
            $model = ArticleCat::find($cat_id, $columns);
        }

        return $model->toArray();
    }

    /**
     * @return mixed
     */
    public function create(array $data)
    {
        return false;
    }

    /**
     * @return mixed
     */
    public function update(array $data)
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
