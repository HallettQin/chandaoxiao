<?php
//cgxlm
namespace app\http\wechat\market\redpack;

class RedpackAdmin extends \app\http\wechat\controllers\Plugin
{
	protected $marketing_type = '';
	protected $wechat_id = 0;
	protected $page_num = 10;
	protected $cfg = array();

	public function __construct($cfg = array())
	{
		parent::__construct();
		$this->cfg = $cfg;
		$this->cfg['plugin_path'] = 'market';
		$this->marketing_type = $cfg['keywords'];
		$this->plugin_name = $this->marketing_type;
		$this->wechat_id = $cfg['wechat_id'];
		$this->page_num = $cfg['page_num'];
	}

	public function marketList()
	{
		$list = dao('wechat_marketing')->field('id, name, command, starttime, endtime, status')->where(array('marketing_type' => $this->marketing_type, 'wechat_id' => $this->wechat_id))->order('id DESC')->select();

		if ($list[0]['id']) {
			foreach ($list as $k => $v) {
				$list[$k]['starttime'] = local_date('Y-m-d H:i:s', $v['starttime']);
				$list[$k]['endtime'] = local_date('Y-m-d H:i:s', $v['endtime']);
				$config = $this->get_market_config($v['id'], $v['marketing_type']);
				$list[$k]['hb_type'] = $config['hb_type'] == 1 ? L('group_redpack') : L('normal_redpack');
				$status = get_status($v['starttime'], $v['endtime']);

				if ($status == 0) {
					$list[$k]['status'] = L('no_start');
				}
				else if ($status == 1) {
					$list[$k]['status'] = L('start');
				}
				else if ($status == 2) {
					$list[$k]['status'] = L('over');
				}
			}
		}
		else {
			$list = array();
		}

		$this->assign('list', $list);
		$this->plugin_display('market_list', $this->cfg);
	}

	public function marketEdit()
	{
		if (IS_POST) {
			$json_result = array('error' => 0, 'msg' => '', 'url' => '');
			$id = I('post.id');
			$data = I('post.data');
			$config = I('post.config');
			if (empty($data['name']) && (32 <= strlen($data['name']))) {
				$json_result = array('error' => 1, 'msg' => '活动名称必填，并且须少于32个字符');
				exit(json_encode($json_result));
			}

			if (($config['base_money'] < 1) || (200 < $config['base_money'])) {
				$json_result = array('error' => 1, 'msg' => '红包金额必须在1元~200元之间，请重新填写');
				exit(json_encode($json_result));
			}

			if (empty($config['nick_name']) && (16 <= strlen($config['nick_name']))) {
				$json_result = array('error' => 1, 'msg' => '提供方名称必填，并且须少于16个字符');
				exit(json_encode($json_result));
			}

			if (empty($config['send_name']) && (32 <= strlen($config['send_name']))) {
				$json_result = array('error' => 1, 'msg' => '红包发送方名称必填，并且须少于32个字符');
				exit(json_encode($json_result));
			}

			$data['wechat_id'] = $this->wechat_id;
			$data['marketing_type'] = I('post.marketing_type');
			$data['starttime'] = local_strtotime($data['starttime']);
			$data['endtime'] = local_strtotime($data['endtime']);
			$data['status'] = get_status($data['starttime'], $data['endtime']);
			$background_path = I('post.background_path');
			$background_path = edit_upload_image($background_path);

			if ($_FILES['background']['name']) {
				$type = array('image/jpeg', 'image/png');
				if ($_FILES['background']['type'] && !in_array($_FILES['background']['type'], $type)) {
					$json_result = array('error' => 1, 'msg' => L('not_file_type'));
					exit(json_encode($json_result));
				}

				$result = $this->upload('data/attached/redpack', true);

				if (0 < $result['error']) {
					$json_result = array('error' => 1, 'msg' => $result['message']);
					exit(json_encode($json_result));
				}
			}

			if ($_FILES['background']['name'] && $result['url']) {
				$data['background'] = $result['url'];
			}
			else {
				$data['background'] = $background_path;
			}

			$form = new \App\Extensions\Form();

			if (!$form->isEmpty($data['background'], 1)) {
				$json_result = array('error' => 1, 'msg' => L('please_upload'));
				exit(json_encode($json_result));
			}

			if ($config) {
				file_write('index.html', '');
				$data['config'] = serialize($config);
			}

			if (strpos($data['background'], 'no_image') !== false) {
				unset($data['background']);
			}

			if ($id) {
				if ($data['background'] && ($background_path != $data['background'])) {
					$background_path = (strpos($background_path, 'no_image') == false ? $background_path : '');
					$this->remove($background_path);
				}

				$where = array('id' => $id, 'wechat_id' => $this->wechat_id, 'marketing_type' => $data['marketing_type']);
				dao('wechat_marketing')->data($data)->where($where)->save();
				$json_result = array('error' => 0, 'msg' => L('market_edit') . L('success'), 'url' => url('list', array('type' => $data['marketing_type'])));
				exit(json_encode($json_result));
			}
			else {
				$data['addtime'] = gmtime();
				dao('wechat_marketing')->data($data)->add();
				$json_result = array('error' => 0, 'msg' => L('market_add') . L('success'), 'url' => url('list', array('type' => $data['marketing_type'])));
				exit(json_encode($json_result));
			}
		}

		$nowtime = gmtime();
		$info = array();
		$market_id = $this->cfg['market_id'];

		if (!empty($market_id)) {
			$info = dao('wechat_marketing')->field('id, name, command, logo, background, starttime, endtime, config, description, support')->where(array('id' => $market_id, 'marketing_type' => $this->marketing_type, 'wechat_id' => $this->wechat_id))->find();

			if ($info) {
				$info['starttime'] = isset($info['starttime']) ? local_date('Y-m-d H:i:s', $info['starttime']) : local_date('Y-m-d H:i:s', $nowtime);
				$info['endtime'] = isset($info['endtime']) ? local_date('Y-m-d H:i:s', $info['endtime']) : local_date('Y-m-d H:i:s', local_strtotime('+1 months', $nowtime));
				$info['config'] = unserialize($info['config']);
				$info['background'] = get_wechat_image_path($info['background']);
			}
		}
		else {
			$info['starttime'] = local_date('Y-m-d H:i:s', $nowtime);
			$info['endtime'] = local_date('Y-m-d H:i:s', local_strtotime('+1 months', $nowtime));
			$info['config']['hb_type'] = 0;
			$info['config']['money_extra'] = 0;
			$last_id = dao('wechat_marketing')->where(array('wechat_id' => $this->wechat_id))->order('id desc')->getField('id');
			$market_id = (!empty($last_id) ? $last_id + 1 : 1);
		}

		$info['url'] = __HOST__ . url('wechat/redpack/activity', array('market_id' => $market_id));
		$this->assign('info', $info);
		$this->plugin_display('market_edit', $this->cfg);
	}

	public function marketShake()
	{
		$market_id = $this->cfg['market_id'];
		$function = I('get.function', '', 'trim');
		$handler = I('get.handler', '', 'trim');
		if ($handler && ($handler == 'edit')) {
			if (IS_POST) {
				$json_result = array('error' => 0, 'msg' => '', 'url' => '');
				$id = I('post.advertice_id', 0, 'intval');
				$data = I('post.advertice');
				$icon_path = I('post.icon_path');
				$form = new \App\Extensions\Form();

				if (!$form->isEmpty($data['content'], 1)) {
					$json_result = array('error' => 1, 'msg' => L('advertice_content'));
					exit(json_encode($json_result));
				}

				if (substr($data['url'], 0, 4) !== 'http') {
					$json_result = array('error' => 1, 'msg' => L('link_err'));
					exit(json_encode($json_result));
				}

				$icon_path = edit_upload_image($icon_path);
				$file = $_FILES['icon'];

				if ($file['name']) {
					$type = array('image/jpeg', 'image/png');

					if (!in_array($file['type'], $type)) {
						$json_result = array('error' => 1, 'msg' => L('not_file_type'));
						exit(json_encode($json_result));
					}

					$result = $this->upload('data/attached/redpack', true);

					if (0 < $result['error']) {
						$json_result = array('error' => 1, 'msg' => $result['message']);
						exit(json_encode($json_result));
					}

					$data['icon'] = $result['url'];
					$data['file_name'] = $file['name'];
					$data['size'] = $file['size'];
				}
				else {
					$data['icon'] = $icon_path;
				}

				if (!$form->isEmpty($data['icon'], 1)) {
					$json_result = array('error' => 1, 'msg' => L('please_upload'));
					exit(json_encode($json_result));
				}

				if (strpos($data['icon'], 'no_image') !== false) {
					unset($data['icon']);
				}

				if ($id) {
					if ($data['icon'] && ($icon_path != $data['icon'])) {
						$icon_path = (strpos($icon_path, 'no_image') == false ? $icon_path : '');
						$this->remove($icon_path);
					}

					$where = array('id' => $id, 'wechat_id' => $this->wechat_id);
					dao('wechat_redpack_advertice')->data($data)->where($where)->save();
					$json_result = array('error' => 0, 'msg' => L('wechat_editor') . L('success'));
					exit(json_encode($json_result));
				}
				else {
					$data['wechat_id'] = $this->wechat_id;
					dao('wechat_redpack_advertice')->data($data)->add();
					$json_result = array('error' => 0, 'msg' => L('add') . L('success'));
					exit(json_encode($json_result));
				}
			}

			$advertices_id = I('get.advertice_id', 0, 'intval');

			if ($advertices_id) {
				$condition = array('id' => $advertices_id, 'wechat_id' => $this->wechat_id);
				$info = dao('wechat_redpack_advertice')->where($condition)->find();
				$info['icon'] = get_wechat_image_path($info['icon']);
			}

			$where = array('id' => $market_id, 'wechat_id' => $this->wechat_id, 'marketing_type' => $this->marketing_type);
			$info['act_name'] = dao('wechat_marketing')->where($where)->getField('name');
			$this->assign('act_name', $info['act_name']);
			$this->assign('info', $info);
			$this->plugin_display('market_shake_edit', $this->cfg);
		}
		else {
			$filter['type'] = $this->marketing_type;
			$filter['function'] = $function;
			$filter['id'] = $market_id;
			$offset = $this->pageLimit(url('data_list', $filter), $this->page_num);
			$condition = array('market_id' => $market_id, 'wechat_id' => $this->wechat_id);
			$total = dao('wechat_redpack_advertice')->where($condition)->count();
			$this->assign('page', $this->pageShow($total));
			$list = dao('wechat_redpack_advertice')->where($condition)->order('id desc')->limit($offset)->select();

			if ($list) {
				foreach ($list as $key => $value) {
					$list[$key]['icon'] = get_wechat_image_path($value['icon']);
				}
			}

			$where = array('id' => $market_id, 'wechat_id' => $this->wechat_id, 'marketing_type' => $this->marketing_type);
			$act_name = dao('wechat_marketing')->where($where)->getField('name');
			$this->assign('act_name', $act_name);
			$this->assign('list', $list);
			$this->plugin_display('market_shake', $this->cfg);
		}
	}

	public function marketLog_list()
	{
		$market_id = $this->cfg['market_id'];
		$function = I('get.function', '', 'trim');
		$handler = I('get.handler', '', 'trim');
		if ($handler && ($handler == 'info')) {
			$log_id = I('get.log_id', 0, 'intval');

			if ($log_id) {
				$condition = array('id' => $log_id, 'wechat_id' => $this->wechat_id);
				$info = dao('wechat_redpack_log')->where($condition)->find();
				$info['nickname'] = dao('wechat_user')->where(array('wechat_id' => $this->wechat_id, 'openid' => $info['openid']))->getField('nickname');
				$info['hb_type'] = $info['hb_type'] == 1 ? '裂变红包' : '普通红包';
				$info['time'] = !empty($info['time']) ? local_date('Y-m-d H:i:s', $info['time']) : '';
				$info['hassub'] = $info['hassub'] == 1 ? '已领取' : '未领取';

				if ($info['hassub'] == 1) {
					$condition = array('id' => $market_id, 'wechat_id' => $this->wechat_id);
					$data = dao('wechat_marketing')->field('config')->where($condition)->find();
					$config = unserialize($data['config']);
					$configure = array('appid' => $info['wxappid'], 'partnerkey' => $config['partnerkey']);
					$WxHongbao = new \App\Extensions\WxHongbao($configure);
					$WxHongbao->setParameter('nonce_str', $WxHongbao->create_noncestr());
					$WxHongbao->setParameter('mch_billno', $info['mch_billno']);
					$WxHongbao->setParameter('mch_id', $info['mch_id']);
					$WxHongbao->setParameter('appid', $info['wxappid']);
					$WxHongbao->setParameter('bill_type', 'MCHT');
					$responseObj = $WxHongbao->query_redpack();
					$return_code = $responseObj->return_code;
					$result_code = $responseObj->result_code;

					if ($return_code == 'SUCCESS') {
						if ($result_code == 'SUCCESS') {
							$info['status'] = $responseObj->status;
							$info['total_num'] = $responseObj->total_num;
							$info['hb_type'] = $responseObj->hb_type;
							$info['openid'] = $responseObj->openid;
							$info['send_time'] = $responseObj->send_time;
							$info['rcv_time'] = $responseObj->rcv_time;
						}
					}
				}
			}

			$this->assign('info', $info);
			$this->plugin_display('market_log_info', $this->cfg);
		}
		else {
			$filter['type'] = $this->marketing_type;
			$filter['function'] = $function;
			$filter['id'] = $market_id;
			$offset = $this->pageLimit(url('data_list', $filter), $this->page_num);
			$where = array('wechat_id' => $this->wechat_id, 'market_id' => $market_id);
			$total = dao('wechat_redpack_log')->where($where)->count();
			$list = dao('wechat_redpack_log')->where($where)->order('id desc')->limit($offset)->select();

			foreach ($list as $key => $value) {
				$list[$key]['nickname'] = dao('wechat_user')->where(array('wechat_id' => $this->wechat_id, 'openid' => $value['openid']))->getField('nickname');
				$list[$key]['time'] = !empty($value['time']) ? local_date('Y-m-d H:i:s', $value['time']) : '';
			}

			$this->assign('page', $this->pageShow($total));
			$this->assign('market_id', $market_id);
			$this->assign('redpacks', $list);
			$this->plugin_display('market_log_list', $this->cfg);
		}
	}

	public function marketShare_setting()
	{
		$this->plugin_display('market_share_setting', $this->cfg);
	}

	public function marketQrcode()
	{
		$market_id = I('get.id', 0, 'intval');

		if (!empty($market_id)) {
			$url = __HOST__ . url('wechat/redpack/activity', array('market_id' => $market_id));
			$info = dao('wechat_marketing')->field('qrcode')->where(array('id' => $market_id, 'marketing_type' => $this->marketing_type, 'wechat_id' => $this->wechat_id))->find();
			$errorCorrectionLevel = 'M';
			$matrixPointSize = 7;
			$path = dirname(ROOT_PATH) . '/data/attached/redpack/';
			$water_logo = ROOT_PATH . 'public/img/shop_app_icon.png';
			$water_logo_out = $path . 'water_logo' . $market_id . '.png';
			$filename = $path . $errorCorrectionLevel . $matrixPointSize . $market_id . '.png';

			if (!is_dir($path)) {
				@mkdir($path);
			}

			\App\Extensions\QRcode::png($url, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
			$img = new \Think\Image();
			$img->open($water_logo)->thumb(80, 80)->save($water_logo_out);
			$img->open($filename)->water($water_logo_out, 5, 100)->save($filename);
			$qrcode_url = __HOST__ . __STATIC__ . '/data/attached/redpack/' . basename($filename) . '?t=' . time();
			$this->cfg['qrcode_url'] = $qrcode_url;
		}

		$this->plugin_display('market_qrcode', $this->cfg);
	}

	public function get_market_config($id, $marketing_type)
	{
		$info = dao('wechat_marketing')->field('config')->where(array('id' => $id, 'marketing_type' => $this->marketing_type, 'wechat_id' => $this->wechat_id))->find();
		$result = unserialize($info['config']);
		return $result;
	}

	public function executeAction()
	{
		if (IS_AJAX) {
			$json_result = array('error' => 0, 'msg' => '', 'url' => '');
			$handler = I('get.handler', '', 'trim');
			$market_id = I('get.market_id', 0, 'intval');
			if ($handler && ($handler == 'log_delete')) {
				$log_id = I('get.log_id', 0, 'intval');

				if (!empty($log_id)) {
					dao('wechat_redpack_log')->where(array('id' => $log_id, 'wechat_id' => $this->wechat_id, 'market_id' => $market_id))->delete();
					$json_result['msg'] = '删除成功！';
					exit(json_encode($json_result));
				}
				else {
					$json_result['msg'] = '删除失败！';
					exit(json_encode($json_result));
				}
			}
		}
	}

	public function returnData($fromusername, $info)
	{
	}

	public function updatePoint($fromusername, $info)
	{
	}

	public function html_show()
	{
	}

	private function get_config($code = '')
	{
	}
}

?>
