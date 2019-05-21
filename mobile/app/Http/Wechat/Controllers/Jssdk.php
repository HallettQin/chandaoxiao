<?php
//cgxlm
namespace App\Http\Wechat\Controllers;

class Jssdk extends \App\Http\Base\Controllers\Frontend
{
	private $weObj = '';
	private $wechat_id = 0;

	public function __construct()
	{
		parent::__construct();

		if (isset($_COOKIE['ectouch_ru_id'])) {
			$ru_id = $_COOKIE['ectouch_ru_id'];
		}
		else {
			$ru_id = 0;
		}

		$wxConf = $this->getConfig($ru_id);
		$this->weObj = new \App\Extensions\Wechat($wxConf);
		$this->wechat_id = $wxConf['id'];
	}

	public function actionIndex()
	{
		$url = input('url', '', 'addslashes');

		if (!empty($url)) {
			$sdk = $this->weObj->getJsSign($url);
			$data = array('status' => '200', 'data' => $sdk);
		}
		else {
			$data = array('status' => '100', 'message' => '缺少参数');
		}

		exit(json_encode($data));
	}

	public function actionCount()
	{
		$jsApiname = input('jsApiname', '', 'trim');
		$link = input('link', '', 'addslashes');
		$share_type = 0;

		switch ($jsApiname) {
		case 'shareTimeline':
			$share_type = 1;
			break;

		case 'sendAppMessage':
			$share_type = 2;
			break;

		case 'shareQQ':
			$share_type = 3;
			break;

		case 'shareQZone':
			$share_type = 4;
			break;

		default:
			break;
		}

		$openid = $_SESSION['openid'];
		if (!empty($share_type) && !empty($openid)) {
			$data = array('wechat_id' => $this->wechat_id, 'openid' => $openid, 'share_type' => $share_type, 'link' => $link, 'share_time' => gmtime());
			dao('wechat_share_count')->data($data)->add();
			$result = array('status' => '200', 'msg' => '统计成功');
		}
		else {
			$result = array('status' => 'fail', 'msg' => '统计失败');
		}

		exit(json($result));
	}

	private function getConfig($ru_id = 0)
	{
		if (0 < $ru_id) {
			$where = array('ru_id' => $ru_id, 'status' => 1);
		}
		else {
			$where = array('default_wx' => 1, 'status' => 1);
		}

		$wxinfo = dao('wechat')->field('id, token, appid, appsecret')->where($where)->find();
		return $wxinfo;
	}
}

?>
