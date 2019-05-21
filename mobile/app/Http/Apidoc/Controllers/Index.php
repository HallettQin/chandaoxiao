<?php
//cgxlm
namespace app\http\apidoc\controllers;

class Index extends \App\Http\Base\Controllers\Frontend
{
	public function actionIndex()
	{
		$type = input('type', 'api');
		$detail = input('detail');
		$menu = $this->parseHtml($type);
		$content = $this->parseHtml($type, $detail);
		$this->assign('menu', $menu);
		$this->assign('content', $content);
		return $this->display();
	}

	private function parseMenu($content = '')
	{
		return array();
	}

	private function parseHtml($type = 'api', $detail = 'READMD.md')
	{
		$basePath = ROOT_PATH . 'storage/markdown/';
		$basePath .= (empty($detail) ? $type . '/READMD.md' : $type . '/' . $detail);

		if (file_exists($basePath)) {
			$content = file_get_contents($basePath);
			$content = preg_replace('/\\((.+\\.md)\\)/i', '(' . url('index', array('type' => $type)) . '&detail=$1)', $content);
		}
		else {
			$content = 'Please wait.';
		}

		return \Parsedown::instance()->text($content);
	}
}

?>
