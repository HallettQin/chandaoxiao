<?php
//cgxlm
namespace app\modules\wechat\sign;

class Sign extends \app\http\wechat\controllers\Plugin
{
	protected $plugin_name = '';
	protected $cfg = array();

	public function __construct($cfg = array())
	{
		parent::__construct();
		$this->plugin_name = strtolower(basename(__FILE__, '.php'));
		$this->cfg = $cfg;
	}

	public function install()
	{
		$this->plugin_display('install', $this->cfg);
	}

	public function returnData($fromusername, $info)
	{
		$articles = array('type' => 'text', 'content' => '签到失败');
		$config = array();
		$config = unserialize($info['config']);
		if (isset($config['point_status']) && ($config['point_status'] == 1)) {
			$uid = dao('wechat_user')->where(array('openid' => $fromusername))->getField('ect_uid');

			if ($uid) {
				$nowtime = gmtime();
				$todaystart = strtotime(date('Y-m-d', gmtime()));
				$todayend = strtotime(date('Y-m-d', gmtime() + (3600 * 24)));
				$condition['openid'] = $fromusername;
				$condition['keywords'] = $info['command'];
				$condition['createtime'] = array(
	'between',
	array($todaystart, $todayend)
	);
				$num = dao('wechat_point')->where($condition)->count();
				if (($num == 0) && ($todaystart < $nowtime) && ($nowtime < $todayend)) {
					if (!empty($config['rank_point_value']) || !empty($config['pay_point_value'])) {
						$this->updatePoint($fromusername, $info, $config['rank_point_value'], $config['pay_point_value']);
						$tips = '系统赠送您 ';
						$tips .= (!empty($config['rank_point_value']) ? $config['rank_point_value'] . ' 等级积分 ' : '');
						$tips .= (!empty($config['pay_point_value']) ? $config['pay_point_value'] . ' 消费积分 ' : '');
						$articles['content'] = '签到成功！' . $tips;
					}
				}
				else {
					$articles['content'] = '今日签到次数已用完，请明天再来';
				}
			}
			else {
				$articles['content'] = '尚未绑定商城会员,请先绑定';
			}
		}
		else {
			$articles['content'] = '未启用签到送积分';
		}

		return $articles;
	}

	public function updatePoint($fromusername, $info, $rank_point_value = 0, $pay_point_value = 0)
	{
		$this->do_point($fromusername, $info, $rank_point_value, $pay_point_value);
	}

	public function executeAction()
	{
	}
}

?>
