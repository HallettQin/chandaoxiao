<?php
// DATA：20170923
namespace App\Http\Wechat\Controllers;

abstract class Plugin extends \App\Http\Base\Controllers\Foundation
{
	protected $_data = array();

	abstract protected function returnData($fromusername, $info);

	abstract protected function updatePoint($fromusername, $info);

	abstract protected function executeAction();

	public function do_point($fromusername, $info, $rank_points = 0, $pay_points = 0)
	{
		$time = gmtime();
		$users = get_wechat_user_id($fromusername);

		if ($users) {
			try {
				$data['user_id'] = $users['user_id'];
				$data['user_money'] = 0;
				$data['frozen_money'] = 0;
				$data['rank_points'] = intval($rank_points);
				$data['pay_points'] = intval($pay_points);
				$data['change_time'] = $time;
				$data['change_desc'] = $info['name'] . '积分赠送';
				$data['change_type'] = ACT_OTHER;
				$log_id = dao('account_log')->data($data)->add();
				$data1['log_id'] = $log_id;
				$data1['openid'] = $fromusername;
				$data1['keywords'] = $info['command'];
				$data1['createtime'] = $time;
				$log_id = dao('wechat_point')->data($data1)->add();
			}
			catch (\Exception $e) {
				logResult($e->getMessage());
				return false;
			}

			$sql = 'UPDATE {pre}users SET rank_points = rank_points + ' . intval($rank_points) . ' WHERE user_id = \'' . $users['user_id'] . '\' ';
			$GLOBALS['db']->query($sql);
			$sql = 'UPDATE {pre}users SET pay_points = pay_points + ' . intval($pay_points) . ' WHERE user_id = \'' . $users['user_id'] . '\' ';
			$GLOBALS['db']->query($sql);
			return true;
		}
	}

	public function do_takeout_point($fromusername, $info, $point_value)
	{
		$time = gmtime();
		$users = get_wechat_user_id($fromusername);

		if ($users) {
			$usable_points = dao('users')->where(array('user_id' => $users['user_id']))->getField('pay_points');

			if (intval($point_value) <= intval($usable_points)) {
				$sql = 'UPDATE {pre}users SET pay_points = pay_points - ' . intval($point_value) . ' WHERE user_id = \'' . $users['user_id'] . '\' ';
				$GLOBALS['db']->query($sql);
				$data['user_id'] = $users['user_id'];
				$data['user_money'] = 0;
				$data['frozen_money'] = 0;
				$data['rank_points'] = 0;
				$data['pay_points'] = $point_value;
				$data['change_time'] = $time;
				$data['change_desc'] = $info['name'] . '积分扣除';
				$data['change_type'] = ACT_OTHER;
				$log_id = dao('account_log')->data($data)->add();
				$data1['log_id'] = $log_id;
				$data1['openid'] = $fromusername;
				$data1['keywords'] = $info['command'];
				$data1['createtime'] = $time;
				$log_id = dao('wechat_point')->data($data1)->add();
				return true;
			}
			else {
				return false;
			}
		}
	}

	public function plugin_display($tpl = '', $config = array())
	{
		L(require MODULE_PATH . 'Language/' . C('shop.lang') . '/wechat.php');

		$this->_data['lang'] = array_change_key_case(L());
		$this->_data['config'] = $config;
		$this->assign($this->_data);
		$plugin_path = (!empty($config['plugin_path']) ? $config['plugin_path'] : 'Plugins');

		$this->plugin_name = ucfirst($this->plugin_name);

		if (0 < $_SESSION['seller_id']) {
			$tpl = 'app/Http/' . MODULE_NAME . '/' . $plugin_path . '/' . $this->plugin_name . '/views_seller/' . $tpl . C('TMPL_TEMPLATE_SUFFIX');
		}
		else {
			$tpl = 'app/Http/' . MODULE_NAME . '/' . $plugin_path . '/' . $this->plugin_name . '/Views/' . $tpl . C('TMPL_TEMPLATE_SUFFIX');
		}

		$this->template_content = $this->fetch(ROOT_PATH . $tpl);  

		$this->assign('template_content', $this->template_content);

		$this->display('wechat@wechat.layout');


	}

	public function show_display($tpl = '', $config = array())
	{
		L(require MODULE_PATH . 'Language/' . C('shop.lang') . '/wechat.php');
		$this->_data['lang'] = array_change_key_case(L());
		$this->_data['config'] = $config;
		$this->assign($this->_data);
		$plugin_path = (!empty($config['plugin_path']) ? $config['plugin_path'] : 'plugins');

		if (0 < $config['ru_id']) {
			$tpl = 'app/http/' . MODULE_NAME . '/' . $plugin_path . '/' . $this->plugin_name . '/views_seller/' . $tpl . C('TMPL_TEMPLATE_SUFFIX');
		}
		else {
			$tpl = 'app/http/' . MODULE_NAME . '/' . $plugin_path . '/' . $this->plugin_name . '/views/' . $tpl . C('TMPL_TEMPLATE_SUFFIX');
		}

		$this->template_content = $this->fetch(ROOT_PATH . $tpl);
		$this->assign('template_content', $this->template_content);
		$this->display('wechat@show.layout');
	}

	public function get_rand($proArr)
	{
		$result = '';
		$proSum = array_sum($proArr);

		foreach ($proArr as $key => $proCur) {
			$randNum = mt_rand(1, $proSum);

			if ($randNum <= $proCur) {
				$result = $key;
				break;
			}
			else {
				$proSum -= $proCur;
			}
		}

		unset($proArr);
		return $result;
	}

	public function __get($name)
	{
		return isset($this->_data[$name]) ? $this->_data[$name] : null;
	}

	public function __set($name, $value)
	{
		$this->_data[$name] = $value;
	}
}

?>
