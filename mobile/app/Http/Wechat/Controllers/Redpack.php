<?php
//cgxlm
namespace App\Http\Wechat\Controllers;

class Redpack extends \App\Http\Base\Controllers\Frontend
{
	private $weObj = '';
	private $wechat_id = 0;
	private $market_id = 0;
	private $marketing_type = 'redpack';
	protected $config = array();

	public function __construct()
	{
		parent::__construct();
		$this->wechat_id = dao('wechat')->where(array('default_wx' => 1))->getField('id');
		$this->market_id = I('market_id', 0, 'intval');

		if (empty($this->market_id)) {
			$this->redirect(url('index/index/index'));
		}

		$this->plugin_themes = __ROOT__ . '/public/assets/wechat/' . $this->marketing_type;
		$this->assign('plugin_themes', $this->plugin_themes);
		$data = dao('wechat_marketing')->field('name, starttime, endtime, config')->where(array('id' => $this->market_id, 'marketing_type' => 'redpack', 'wechat_id' => $this->wechat_id))->find();
		$this->config['config'] = unserialize($data['config']);
		$this->config['config']['act_name'] = $data['name'];
		$wechat_arr = $this->get_wechat_config($this->wechat_id);
		$this->config['config']['appid'] = $wechat_arr['appid'];
		$this->config['starttime'] = $data['starttime'];
		$this->config['endtime'] = $data['endtime'];
	}

	public function actionActivity()
	{
		$info = dao('wechat_marketing')->field('id, name, logo, background, description, support')->where(array('id' => $this->market_id, 'marketing_type' => 'redpack', 'wechat_id' => $this->wechat_id))->find();

		if (!empty($info)) {
			$info['background'] = get_wechat_image_path($info['background']);

			if (strpos($info['background'], 'no_image') !== false) {
				unset($info['background']);
			}

			$status = get_status($this->config['starttime'], $this->config['endtime']);

			if ($status == 0) {
				$flag = '活动未开始';
			}

			if ($status == 2) {
				$flag = '活动已结束';
			}

			$is_subscribe = dao('wechat_user')->where(array('openid' => $_SESSION['openid']))->getField('subscribe');

			if ($is_subscribe == 0) {
				$flag = '请先关注微信公众号！';
			}

			$this->assign('flag', $flag);
			$shake_url = __HOST__ . url('shake', array('market_id', $this->market_id));
			$this->assign('shake_url', $shake_url);
			$page_title = $info['name'];
			$description = $info['description'];
			$page_img = get_wechat_image_path($info['background']);
		}

		$this->assign('info', $info);
		$this->assign('page_title', $page_title);
		$this->assign('description', $description);
		$this->assign('link', $link);
		$this->assign('page_img', $page_img);
		$this->display();
	}

	public function actionShake()
	{
		if (IS_POST) {
			$time = I('time');
			$last = I('last');
			$openid = $_SESSION['openid'];
			if (empty($openid) || ($openid == '') || ($openid == null)) {
				$result = array('icon' => $this->plugin_themes . '/images/error.png', 'content' => '请关注微信公众号或在微信客户端中打开！！', 'url' => '');
				exit(json_encode($result));
			}

			$cha = $time - $last;

			if ($cha < 4000) {
				$result = array('status' => 0, 'icon' => $this->plugin_themes . '/images/icon.jpg', 'content' => '歇一会，您摇得过于频繁了！请隔4秒以上再试 ~~', 'url' => '');
				exit(json_encode($result));
			}

			$min = $this->config['config']['randmin'];
			$max = $this->config['config']['randmax'];
			$sendNum = $this->config['config']['sendnum'];
			$sendArr = explode(',', $sendNum);
			$rand = rand($min, $max);
			$isInclude = in_array($rand, $sendArr);
			$hb_type = $this->config['config']['hb_type'];

			if ($isInclude) {
				$status = get_status($this->config['starttime'], $this->config['endtime']);

				if ($status == 0) {
					$result = array('status' => 0, 'icon' => $this->plugin_themes . '/images/icon.jpg', 'content' => '您来早了，活动还没开始！！！', 'url' => '');
					exit(json_encode($result));
				}
				else if ($status == 2) {
					$result = array('status' => 0, 'icon' => $this->plugin_themes . '/images/icon.jpg', 'content' => '您来迟了，活动已结束！！！', 'url' => '');
					exit(json_encode($result));
				}
				else {
					$log = dao('wechat_redpack_log')->field('hassub')->where(array('wechat_id' => $this->wechat_id, 'market_id' => $this->market_id, 'openid' => $openid))->find();

					if (count($log) == 1) {
						if ($log['hassub'] == 1) {
							$temp = '您已参与过本活动，请不要重复操作！';
							$result = array('status' => 0, 'icon' => $this->plugin_themes . '/images/icon.jpg', 'content' => $temp, 'url' => '');
						}
						else {
							$temp = $this->sendRedpack($openid, $hb_type);
							$result = array('status' => 1, 'icon' => $this->plugin_themes . '/images/icon.jpg', 'content' => $temp, 'url' => '');
						}
					}
					else if (count($log) == 0) {
						$data = array('wechat_id' => $this->wechat_id, 'market_id' => $this->market_id, 'hb_type' => $hb_type, 'openid' => $openid, 'hassub' => 0);
						dao('wechat_redpack_log')->data($data)->add();
						$temp = $this->sendRedpack($openid, $hb_type);
						$result = array('status' => 1, 'icon' => $this->plugin_themes . '/images/icon.jpg', 'content' => $temp, 'url' => '');
					}

					exit(json_encode($result));
				}
			}
			else {
				$total = dao('wechat_redpack_advertice')->where(array('wechat_id' => $this->wechat_id, 'market_id' => $this->market_id))->count();

				if ($total == 0) {
					$result = array('icon' => $this->plugin_themes . '/images/icon.jpg', 'content' => '什么都没摇到~~~', 'url' => '');
					exit(json_encode($result));
				}

				$pageindex = rand(0, $total - 1);
				$temp = dao('wechat_redpack_advertice')->field('icon, content, url')->where(array('wechat_id' => $this->wechat_id, 'market_id' => $this->market_id))->limit($pageindex, 1)->select();
				$temp = reset($temp);
				$temp['icon'] = get_wechat_image_path($temp['icon']);
				$result = array('icon' => $temp['icon'], 'content' => $temp['content'], 'url' => $temp['url']);
				exit(json_encode($result));
			}
		}

		$this->assign('back_url', __HOST__ . url('activity', array('market_id' => $this->market_id)));
		$this->assign('market_id', $this->market_id);
		$this->assign('page_title', '微信摇一摇活动页面');
		$this->display();
	}

	public function sendRedpack($param_openid, $hb_type = 0)
	{
		$randmin = $this->config['config']['randmin'];
		$randmax = $this->config['config']['randmax'];
		$sendnum = $this->config['config']['sendnum'];
		$sendArr = explode(',', $sendnum);
		$rand = rand($randmin, $randmax);
		$isInclude = in_array($rand, $sendArr);

		if ($isInclude) {
			$mch_billno = $mchid . date('YmdHis') . rand(1000, 9999);
			$money = $this->config['config']['base_money'] + rand(0, $this->config['config']['money_extra']);
			$money = $money * 100;

			if ($hb_type == 0) {
				$total_num = 1;
			}
			else {
				$total_num = (3 < $total_num ? $total_num : 3);
			}

			$appid = $this->config['config']['appid'];
			$mchid = $this->config['config']['mchid'];
			$partnerkey = $this->config['config']['partner'];
			$nick_name = $this->config['config']['nick_name'];
			$send_name = $this->config['config']['send_name'];
			$wishing = $this->config['config']['wishing'];
			$act_name = $this->config['config']['act_name'];
			$remark = $this->config['config']['remark'];
			$scene_id = strtoupper($this->config['config']['scene_id']);
			$configure = array('appid' => $appid, 'partnerkey' => $partnerkey);
			$WxHongbao = new \App\Extensions\WxHongbao($configure);

			if ($hb_type == 0) {
				$WxHongbao->setParameter('nonce_str', $WxHongbao->create_noncestr());
				$WxHongbao->setParameter('mch_billno', $mch_billno);
				$WxHongbao->setParameter('mch_id', $mchid);
				$WxHongbao->setParameter('wxappid', $appid);
				$WxHongbao->setParameter('nick_name', $nick_name);
				$WxHongbao->setParameter('send_name', $send_name);
				$WxHongbao->setParameter('re_openid', $param_openid);
				$WxHongbao->setParameter('total_amount', $money);
				$WxHongbao->setParameter('min_value', $money);
				$WxHongbao->setParameter('max_value', $money);
				$WxHongbao->setParameter('total_num', $total_num);
				$WxHongbao->setParameter('wishing', $wishing);
				$WxHongbao->setParameter('client_ip', $_SERVER['REMOTE_ADDR']);
				$WxHongbao->setParameter('act_name', $act_name);
				$WxHongbao->setParameter('remark', $remark);
			}
			else if ($hb_type == 1) {
				$WxHongbao->setParameter('nonce_str', $WxHongbao->create_noncestr());
				$WxHongbao->setParameter('mch_billno', $mch_billno);
				$WxHongbao->setParameter('mch_id', $mchid);
				$WxHongbao->setParameter('wxappid', $appid);
				$WxHongbao->setParameter('nick_name', $nick_name);
				$WxHongbao->setParameter('send_name', $send_name);
				$WxHongbao->setParameter('re_openid', $param_openid);
				$WxHongbao->setParameter('total_amount', $money);
				$WxHongbao->setParameter('total_num', $total_num);
				$WxHongbao->setParameter('amt_type', 'ALL_RAND');
				$WxHongbao->setParameter('wishing', $wishing);
				$WxHongbao->setParameter('act_name', $act_name);
				$WxHongbao->setParameter('remark', $remark);
			}

			if ($scene_id && (0 < $scene_id)) {
				$WxHongbao->setParameter('scene_id', $scene_id);
			}

			$hb_type = ($hb_type == 1 ? 'GROUP' : 'NORMAL');
			$responseObj = $WxHongbao->creat_sendredpack($hb_type);
			$return_code = $responseObj->return_code;
			$result_code = $responseObj->result_code;

			if ($return_code == 'SUCCESS') {
				if ($result_code == 'SUCCESS') {
					$total_amount = ($responseObj->total_amount * 1) / 100;
					$re_openid = $responseObj->re_openid;
					$mch_billno = $responseObj->mch_billno;
					$mch_id = $responseObj->mch_id;
					$wxappid = $responseObj->wxappid;
					$where = array('wechat_id' => $this->wechat_id, 'market_id' => $this->market_id, 'openid' => !empty($re_openid) ? $re_openid : $param_openid);
					$data = array('hassub' => 1, 'money' => $total_amount, 'time' => gmtime(), 'mch_billno' => $mch_billno, 'mch_id' => $mch_id, 'wxappid' => $wxappid, 'bill_type' => 'MCHT');
					$result = dao('wechat_redpack_log')->data($data)->where($where)->save();
					return '红包发放成功！金额为：' . $total_amount . '元！拆开发放的红包即可领取红包！';
				}
				else {
					if ($responseObj->err_code == 'NOTENOUGH') {
						return '您来迟了，红包已经发完！！！';
					}
					else if ($responseObj->err_code == 'TIME_LIMITED') {
						return '现在非红包发放时间，请在北京时间0:00-8:00之外的时间前来领取';
					}
					else if ($responseObj->err_code == 'SYSTEMERROR') {
						return '系统繁忙，请稍后再试！';
					}
					else if ($responseObj->err_code == 'DAY_OVER_LIMITED') {
						return '今日红包已达上限，请明日再试！';
					}
					else if ($responseObj->err_code == 'SECOND_OVER_LIMITED') {
						return '每分钟红包已达上限，请稍后再试！';
					}

					return '红包发放失败！' . $responseObj->return_msg . '！请稍后再试！';
				}
			}
			else {
				if ($responseObj->err_code == 'NOTENOUGH') {
					return '您来迟了，红包已经发放完！！!';
				}
				else if ($responseObj->err_code == 'TIME_LIMITED') {
					return '现在非红包发放时间，请在北京时间0:00-8:00之外的时间前来领取';
				}
				else if ($responseObj->err_code == 'SYSTEMERROR') {
					return '系统繁忙，请稍后再试！';
				}
				else if ($responseObj->err_code == 'DAY_OVER_LIMITED') {
					return '今日红包已达上限，请明日再试！';
				}
				else if ($responseObj->err_code == 'SECOND_OVER_LIMITED') {
					return '每分钟红包已达上限，请稍后再试！';
				}

				return '红包发放失败！' . $responseObj->return_msg . '！请稍后再试！';
			}
		}
		else {
			$where = array('wechat_id' => $this->wechat_id, 'market_id' => $this->market_id, 'openid' => $param_openid);
			$data = array('hassub' => 1, 'money' => 0, 'time' => gmtime());
			$result = dao('wechat_redpack_log')->data($data)->where($where)->save();
			return '很遗憾，您没有抢到红包！感谢您的参与！';
		}
	}

	private function get_wechat_config($wechat_id = 0)
	{
		$config = dao('wechat')->field('appid, appsecret')->where(array('id' => $wechat_id, 'status' => 1))->find();

		if (empty($config)) {
			$config = array();
		}

		return $config;
	}
}

?>
