<?php

namespace App\Api\Controllers\Wx;

use App\Services\CategoryService;
use App\Api\Controllers\Controller;

/**
 * Class CategoryController
 * @package App\Api\Controllers\Wx
 */
class CategoryController extends Controller
{
    private $categoryService;

    /**
     * CategoryController constructor.
     * @param CategoryService $categoryService
     */
    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * 分类列表
     * @return array
     */
    public function index()
    {
        $list = $this->categoryService->categoryList();

        return $this->apiReturn($list);
    }
}
