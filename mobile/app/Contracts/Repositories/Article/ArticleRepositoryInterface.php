<?php

namespace App\Contracts\Repositories\Article;

/**
 * Interface ArticleRepositoryInterface
 * @package App\Contracts\Repositories\article
 */
interface ArticleRepositoryInterface
{
    /**
     * @param $cat_id
     * @param $columns
     * @param $offset
     * @param $requirement
     * @return mixed
     */
    public function all($cat_id, $columns, $page, $offset);

    /**
     * @param $id
     * @return mixed
     */
    public function detail($id);

    /**
     * @param $data
     * @return mixed
     */
    public function create($data);

    /**
     * @param $data
     * @return mixed
     */
    public function update($data);

    /**
     * @param $id
     * @return mixed
     */
    public function delete($id);
}
