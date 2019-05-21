<?php
//cgxlm
namespace App\Http\Search\Controllers;

class Index extends \App\Http\Base\Controllers\Frontend
{
	public function actionIndex()
	{
		$this->assign('page_title', L('search'));
		$this->display();
	}
}

?>
