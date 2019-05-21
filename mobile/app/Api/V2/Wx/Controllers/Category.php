<?php
//cgxlm
namespace app\api\v2\wx\controllers;

class Category extends \app\api\foundation\Controller
{
	private $categoryService;

	public function __construct(\app\services\CategoryService $categoryService)
	{
		parent::__construct();
		$this->categoryService = $categoryService;
	}

	public function actionIndex()
	{
		$list = $this->categoryService->categoryList();
		return $this->apiReturn(array('category' => $list));
	}
}

?>
