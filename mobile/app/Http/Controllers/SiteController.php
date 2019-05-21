<?php
//cgxlm
namespace app\http\controllers;

class SiteController extends \yii\web\Controller
{
	public function actions()
	{
		return array(
	'error' => array('class' => 'yii\\web\\ErrorAction')
	);
	}

	public function actionIndex()
	{
		return 'ready.';
	}
}

?>
