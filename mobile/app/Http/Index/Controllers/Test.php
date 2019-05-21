<?php
//cgxlm
namespace app\http\index\controllers;

class Test extends \App\Http\Base\Controllers\Frontend
{
	private $article;

	public function __construct(\app\repositories\Article $articles)
	{
		parent::__construct();
		$this->article = $articles;
	}

	public function actionIndex()
	{
		$res = $this->article->find(58);
		dump($res);
	}
}

?>
