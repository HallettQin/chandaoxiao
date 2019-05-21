<?php
//cgxlm
namespace App\Services;

class CategoryService
{
	private $categoryRepository;

	public function __construct(\App\Repositories\Category\CategoryRepository $categoryRepository)
	{
		$this->categoryRepository = $categoryRepository;
	}

	public function categoryList()
	{
		$list = $this->categoryRepository->getAllCategorys();
		return $list;
	}

	public function categoryDetail($catId)
	{
		$list = $this->categoryRepository->getCategoryGetGoods($catId);
		return $list;
	}
}


?>
