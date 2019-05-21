<?php
//cgxlm
namespace App\Http\Wechat\Controllers;

class Sellerextend extends \App\Http\Base\Controllers\BackendSeller
{
	protected $plugin_name = '';
	protected $wechat_type = 0;
	protected $wechat_id = 0;
	protected $ru_id = 0;

	public function __construct()
	{
		parent::__construct();
		L(require MODULE_PATH . 'Language/' . C('shop.lang') . '/wechat.php');
		$this->assign('lang', array_change_key_case(L()));
		$this->seller_admin_priv('extend');
		$this->plugin_name = I('get.ks', '', 'trim');
		$seller = get_admin_ru_id_seller();
		if (!empty($seller) && (0 < $seller['ru_id'])) {
			$this->ru_id = $seller['ru_id'];
		}

		$menu = set_seller_menu();
		$this->assign('seller_menu', $menu);
		$menu_select = get_select_menu();
		$this->assign('menu_select', $menu_select);
		$this->assign('ru_id', $this->ru_id);
		$this->assign('seller_name', $_SESSION['seller_name']);
		$wechat = dao('wechat')->Field('id,type')->where(array('default_wx' => 0, 'ru_id' => $this->ru_id))->find();
		$this->wechat_id = $wechat['id'];
		$this->wechat_type = $wechat['type'];
	}

	public function actionIndex()
	{
		$extends = $this->model->table('wechat_extend')->field('name, keywords, command, config, enable, author, website')->where(array('type' => 'function', 'enable' => 1, 'wechat_id' => $this->wechat_id))->order('id asc')->select();

		if (!empty($extends)) {
			$kw = array();

			foreach ($extends as $key => $val) {
				$val['config'] = unserialize($val['config']);
				$kw[$val['command']] = $val;
			}
		}

		$modules = $this->read_wechat();

		if (!empty($modules)) {
			foreach ($modules as $k => $v) {
				$ks = $v['command'];

				if (isset($kw[$v['command']])) {
					$modules[$k]['keywords'] = $kw[$ks]['keywords'];
					$modules[$k]['config'] = $kw[$ks]['config'];
					$modules[$k]['enable'] = $kw[$ks]['enable'];
				}

				if (($this->wechat_type == 0) || ($this->wechat_type == 1)) {
					if (($modules[$k]['command'] == 'bd') || ($modules[$k]['command'] == 'bonus') || ($modules[$k]['command'] == 'ddcx') || ($modules[$k]['command'] == 'jfcx') || ($modules[$k]['command'] == 'sign') || ($modules[$k]['command'] == 'wlcx') || ($modules[$k]['command'] == 'zjd') || ($modules[$k]['command'] == 'dzp') || ($modules[$k]['command'] == 'ggk')) {
						unset($modules[$k]);
					}
				}

				if (!empty($_SESSION['seller_id']) && (0 < $_SESSION['seller_id'])) {
					if (($modules[$k]['command'] == 'bonus') || ($modules[$k]['command'] == 'ddcx') || ($modules[$k]['command'] == 'jfcx') || ($modules[$k]['command'] == 'sign') || ($modules[$k]['command'] == 'wlcx')) {
						unset($modules[$k]);
					}
				}
			}
		}

		$this->assign('modules', $modules);
		$postion = array('ur_here' => L('wechat_extend'));
		$this->assign('postion', $postion);
		$this->display();
	}

	public function actionEdit()
	{
		if (IS_POST) {
			$handler = I('post.handler');
			$cfg_value = I('post.cfg_value');
			$data = I('post.data');

			if (empty($data['keywords'])) {
				$this->message('请填写扩展词', null, 2, true);
			}

			$data['type'] = 'function';
			$data['wechat_id'] = $this->wechat_id;
			$rs = $this->model->table('wechat_extend')->field('name, config, enable')->where(array('command' => $data['command'], 'wechat_id' => $this->wechat_id))->find();

			if (!empty($rs)) {
				if (empty($handler) && !empty($rs['enable'])) {
					$this->message('插件已安装', null, 2, true);
				}
				else {
					if (empty($cfg_value['media_id'])) {
						$media_id = $this->model->table('wechat_media')->where(array('command' => $this->plugin_name, 'wechat_id' => $this->wechat_id))->getField('id');

						if ($media_id) {
							$cfg_value['media_id'] = $media_id;
						}
						else {
							$sql_file = MODULE_PATH . 'plugins/' . $this->plugin_name . '/install.sql';

							if (file_exists($sql_file)) {
								$sql = file_get_contents($sql_file);
								$sql = str_replace(array('ecs_wechat_media', '(0', 'http://', 'views/images'), array('{pre}wechat_media', '(' . $this->wechat_id, __HOST__ . url('wechat/index/plugin_show', array('name' => $this->plugin_name, 'ru_id' => $this->ru_id)), 'public/assets/wechat/' . $this->plugin_name . '/images'), $sql);
								$this->model->query($sql);
								$cfg_value['media_id'] = $this->model->table('wechat_media')->where(array('command' => $this->plugin_name, 'wechat_id' => $this->wechat_id))->getField('id');
							}
						}
					}

					$data['config'] = serialize($cfg_value);
					$data['enable'] = 1;
					$this->model->table('wechat_extend')->data($data)->where(array('command' => $data['command'], 'wechat_id' => $this->wechat_id))->save();
				}
			}
			else {
				$sql_file = MODULE_PATH . 'plugins/' . $this->plugin_name . '/install.sql';

				if (file_exists($sql_file)) {
					$sql = file_get_contents($sql_file);
					$sql = str_replace(array('ecs_wechat_media', '(0', 'http://', 'views/images'), array('{pre}wechat_media', '(' . $this->wechat_id, __HOST__ . url('wechat/index/plugin_show', array('name' => $this->plugin_name, 'ru_id' => $this->ru_id)), 'public/assets/wechat/' . $this->plugin_name . '/images'), $sql);
					$this->model->query($sql);
					$cfg_value['media_id'] = $this->model->table('wechat_media')->where(array('command' => $this->plugin_name, 'wechat_id' => $this->wechat_id))->getField('id');
				}

				$data['config'] = serialize($cfg_value);
				$data['enable'] = 1;
				$this->model->table('wechat_extend')->data($data)->add();
			}

			$this->message('安装编辑成功', url('index'), 1, true);
		}

		$handler = I('get.handler', '', 'trim');

		if (!empty($handler)) {
			$info = $this->model->table('wechat_extend')->field('name, keywords, command, config, enable, author, website')->where(array('command' => $this->plugin_name, 'wechat_id' => $this->wechat_id, 'enable' => 1))->find();

			if (empty($info)) {
				$this->message('请选择要编辑的功能扩展', null, 2, true);
			}

			$info['config'] = unserialize($info['config']);
		}

		$postion = array('ur_here' => L('wechat_extend'));
		$this->assign('postion', $postion);
		$plugin = '\\app\\http\\wechat\\plugins\\' . $this->plugin_name . '\\' . ucfirst($this->plugin_name);

		if (class_exists($plugin)) {
			if (!empty($info['enable'])) {
				$config = $info;
				$config['handler'] = 'edit';
			}
			else {
				$config_file = ROOT_PATH . dirname(str_replace('\\', '/', substr($plugin, 1))) . '/config.php';
				$config = require_once $config_file;
			}

			if (!is_array($config)) {
				$config = array();
			}

			$current_time = gmtime();
			$config['config']['starttime'] = empty($config['config']['starttime']) ? date('Y-m-d', $current_time) : $config['config']['starttime'];
			$config['config']['endtime'] = empty($config['config']['endtime']) ? date('Y-m-d', strtotime('+1 months')) : $config['config']['endtime'];
			$obj = new $plugin($config);
			$obj->install();
		}
	}

	public function actionUninstall()
	{
		$keywords = I('get.ks');

		if (empty($keywords)) {
			$this->message('请选择要卸载的功能扩展', null, 2, true);
		}

		$config = $this->model->table('wechat_extend')->where(array('command' => $keywords, 'wechat_id' => $this->wechat_id))->getField('enable');
		$data['enable'] = 0;
		$this->model->table('wechat_extend')->data($data)->where(array('command' => $keywords, 'wechat_id' => $this->wechat_id))->save();
		$media_count = $this->model->table('wechat_media')->where(array('command' => $keywords, 'wechat_id' => $this->wechat_id))->count();

		if (0 < $media_count) {
			$this->model->table('wechat_media')->where(array('command' => $keywords, 'wechat_id' => $this->wechat_id))->delete();
		}

		$this->message('卸载成功', url('index'), 1, true);
	}

	public function actionWinnerList()
	{
		$ks = I('get.ks', '', 'trim');

		if (empty($ks)) {
			$this->message('请选择插件', null, 2, true);
		}

		$page_num = 10;
		$this->assign('page_num', $page_num);
		$keywords = I('request.keywords', '', 'trim');
		$where = '';

		if (!empty($keywords)) {
			$where .= ' and (u.nickname like "%' . $keywords . '%" )';
		}

		$filter['ks'] = $ks;
		$offset = $this->pageLimit(url('winner_list', $filter), $page_num);
		$sql_count = 'SELECT count(*) as number FROM {pre}wechat_prize p LEFT JOIN {pre}wechat_user u ON p.openid = u.openid WHERE p.activity_type = \'' . $ks . '\' and p.prize_type = 1 and u.subscribe = 1 and u.wechat_id = \'' . $this->wechat_id . '\'  ' . $where . '  ORDER BY dateline desc ';
		$total = $this->model->query($sql_count);
		$sql = 'SELECT p.id, p.prize_name, p.issue_status, p.winner, p.dateline, p.openid, u.nickname FROM {pre}wechat_prize p LEFT JOIN {pre}wechat_user u ON p.openid = u.openid WHERE p.activity_type = "' . $ks . '" and u.wechat_id = ' . $this->wechat_id . ' and p.prize_type = 1 and u.subscribe = 1 ' . $where . ' ORDER BY dateline desc  limit ' . $offset;
		$list = $this->model->query($sql);

		if (empty($list)) {
			$list = array();
		}

		foreach ($list as $key => $val) {
			$list[$key]['winner'] = unserialize($val['winner']);
			$list[$key]['dateline'] = local_date($GLOBALS['_CFG']['time_format'], $val['dateline']);
		}

		$this->assign('activity_type', $ks);
		$this->assign('page', $this->pageShow($total[0]['number']));
		$this->assign('list', $list);
		$extend_name = $this->model->table('wechat_extend')->where(array('command' => $ks, 'wechat_id' => $this->wechat_id))->getField('name');
		$postion = array('ur_here' => $extend_name);
		$this->assign('postion', $postion);
		$this->display();
	}

	public function actionWinnerIssue()
	{
		$id = I('get.id', 0, 'intval');
		$cancel = I('get.cancel');
		$activity_type = I('get.ks', '', 'trim');

		if (empty($id)) {
			$this->message('请选择中奖记录', null, 2, true);
		}

		if (!empty($cancel)) {
			$data['issue_status'] = 0;
			$this->model->table('wechat_prize')->data($data)->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->save();
			$this->message('取消成功', url('winner_list', array('ks' => $activity_type)), 1, true);
		}
		else {
			$data['issue_status'] = 1;
			$this->model->table('wechat_prize')->data($data)->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->save();
			$this->message('发放成功', url('winner_list', array('ks' => $activity_type)), 1, true);
		}
	}

	public function actionWinnerDel()
	{
		$id = I('get.id', 0, 'intval');
		$activity_type = I('get.ks', '', 'trim');

		if (empty($id)) {
			$this->message('请选择中奖记录', null, 2, true);
		}

		$this->model->table('wechat_prize')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->delete();
		$this->message('删除成功', url('winner_list', array('ks' => $activity_type)), 1, true);
	}

	private function read_wechat()
	{
		$modules = glob(MODULE_PATH . 'plugins/*/config.php');

		foreach ($modules as $file) {
			$config[] = require_once $file;
		}

		return $config;
	}
}

?>
