<?php
//cgxlm
namespace App\Http\Wechat\Controllers;

class Market extends \App\Http\Base\Controllers\Backend
{
	protected $weObj = '';
	protected $wechat_id = 0;
	protected $market_type = '';
	protected $page_num = 0;

	public function __construct()
	{
		parent::__construct();
		L(require MODULE_PATH . 'Language/' . C('shop.lang') . '/wechat.php');
		$this->assign('lang', array_change_key_case(L()));
		$this->admin_priv('market');
		$this->market_type = I('get.type', '', 'trim');
		$this->wechat_id = dao('wechat')->where(array('default_wx' => 1))->getField('id');
		$this->init_params();
	}

	private function init_params()
	{
		if (IS_POST) {
			$page_num = I('page_num', 0, 'intval');

			if (0 < $page_num) {
				cookie('ECSCP[page_size]', $page_num);
				exit(json_encode(array('status' => 1)));
			}
		}

		$this->page_num = isset($_COOKIE['ECSCP']['page_size']) && !empty($_COOKIE['ECSCP']['page_size']) ? $_COOKIE['ECSCP']['page_size'] : 10;
		$this->assign('page_num', $this->page_num);
	}

	public function actionIndex()
	{
		$markets = $this->read_markets();
		$arr = array();

		foreach ($markets as $key => $val) {
			$markets[$key]['url'] = url('list', array('type' => $val['keywords']));
			$arr[] = $val['sort'];
		}

		array_multisort($arr, SORT_ASC, $markets);
		$this->assign('list', $markets);
		$this->display();
	}

	public function actionList()
	{
		$plugin = '\\app\\http\\wechat\\market\\' . $this->market_type . '\\' . ucfirst($this->market_type) . 'Admin';

		if (class_exists($plugin)) {
			$config_file = ROOT_PATH . dirname(str_replace('\\', '/', substr($plugin, 1))) . '/config.php';
			$config = require_once $config_file;
			$config['wechat_id'] = $this->wechat_id;
			$obj = new $plugin($config);
			$obj->marketList();
		}
	}

	public function actionEdit()
	{
		$market_id = I('id', 0, 'intval');
		$plugin = '\\app\\http\\wechat\\market\\' . $this->market_type . '\\' . ucfirst($this->market_type) . 'Admin';

		if (class_exists($plugin)) {
			$config_file = ROOT_PATH . dirname(str_replace('\\', '/', substr($plugin, 1))) . '/config.php';
			$config = require_once $config_file;

			if (!empty($market_id)) {
				$config['market_id'] = $market_id;
				$config['handler'] = 'edit';
			}

			$config['wechat_id'] = $this->wechat_id;
			$key = dao('wechat_marketing')->where(array('marketing_type' => $this->market_type, 'wechat_id' => $this->wechat_id))->count();
			$key = (!empty($key) ? $key + 1 : 1);
			$config['command'] = $this->market_type . $key;
			$obj = new $plugin($config);
			$obj->marketEdit();
		}
	}

	public function actionDel()
	{
		$id = I('get.id', 0, 'intval');

		if (!$id) {
			$this->message(L('empty'), null, 2);
		}

		dao('wechat_marketing')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->delete();
		$this->message(L('market_delete') . L('success'), url('list', array('type' => $this->market_type)));
	}

	public function actionDataList()
	{
		$market_id = I('get.id', 0, 'intval');
		$function = I('get.function', '', 'trim');
		$plugin = '\\app\\http\\wechat\\market\\' . $this->market_type . '\\' . ucfirst($this->market_type) . 'Admin';
		if (class_exists($plugin) && !empty($function)) {
			$config_file = ROOT_PATH . dirname(str_replace('\\', '/', substr($plugin, 1))) . '/config.php';
			$config = require_once $config_file;
			$config['wechat_id'] = $this->wechat_id;
			$config['page_num'] = $this->page_num;
			$config['market_id'] = $market_id;
			$obj = new $plugin($config);
			$function_name = 'market' . ucfirst($function);
			$obj->$function_name();
		}
	}

	public function actionMarketQrcode()
	{
		$plugin = '\\app\\http\\wechat\\market\\' . $this->market_type . '\\' . ucfirst($this->market_type) . 'Admin';

		if (class_exists($plugin)) {
			$config_file = ROOT_PATH . dirname(str_replace('\\', '/', substr($plugin, 1))) . '/config.php';
			$config = require_once $config_file;
			$config['wechat_id'] = $this->wechat_id;
			$obj = new $plugin($config);
			$obj->marketQrcode();
		}
	}

	public function actionMarketAction()
	{
		$file = MODULE_PATH . 'market/' . $this->market_type . '/' . ucfirst($this->market_type) . 'Admin.php';

		if (file_exists($file)) {
			include_once $file;
			$market = '\\app\\http\\wechat\\market\\' . $this->market_type . '\\' . ucfirst($this->market_type) . 'Admin';

			if (class_exists($market)) {
				$config_file = ROOT_PATH . dirname(str_replace('\\', '/', substr($market, 1))) . '/config.php';
				$config = require_once $config_file;
				$config['wechat_id'] = $this->wechat_id;
			}

			$obj = new $market($config);
			$obj->executeAction();
		}
	}

	private function read_markets()
	{
		$modules = glob(MODULE_PATH . 'market/*/config.php');

		foreach ($modules as $file) {
			$config[] = require_once $file;
		}

		return $config;
	}

	public function get_config()
	{
		$where['id'] = $this->wechat_id;
		$wechat = dao('wechat')->field('token, appid, appsecret, type, status')->where($where)->find();

		if (empty($wechat)) {
			$wechat = array();
		}

		if (empty($wechat['status'])) {
			$this->message(L('open_wechat'), url('index'), 2);
			exit();
		}

		$config = array();
		$config['token'] = $wechat['token'];
		$config['appid'] = $wechat['appid'];
		$config['appsecret'] = $wechat['appsecret'];
		$this->weObj = new \App\Extensions\Wechat($config);
	}
}

?>
