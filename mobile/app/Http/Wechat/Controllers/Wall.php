<?php
//cgxlm
namespace App\Http\Wechat\Controllers;

class Wall extends \App\Http\Base\Controllers\Frontend
{
	private $weObj = '';
	private $wechat_id = 0;
	private $market_id = 0;
	private $marketing_type = 'wall';
	protected $config = array();

	public function __construct()
	{
		parent::__construct();
		$this->wechat_id = dao('wechat')->where(array('default_wx' => 1))->getField('id');
		$this->market_id = I('wall_id', 0, 'intval');

		if (empty($this->market_id)) {
			$this->redirect(url('index/index/index'));
		}

		$this->plugin_themes = __ROOT__ . '/app/modules/wechatmarket' . '/' . $this->marketing_type . '/views';
		$this->assign('plugin_themes', $this->plugin_themes);
	}

	public function actionWallMsg()
	{
		$wall = dao('wechat_marketing')->field('id, name, logo, background, starttime, endtime, config, description, support')->where(array('id' => $this->market_id, 'marketing_type' => 'wall', 'wechat_id' => $this->wechat_id))->find();
		$wall['status'] = get_status($wall['starttime'], $wall['endtime']);
		$wall['logo'] = get_wechat_image_path($wall['logo']);
		$wall['background'] = get_wechat_image_path($wall['background']);

		if ($wall['status'] == 1) {
			$cache_key = md5('cache_0');
			$list = cache($cache_key);

			if ($list === false) {
				$sql = 'SELECT u.nickname, u.headimg, m.content, m.addtime FROM {pre}wechat_wall_msg m LEFT JOIN {pre}wechat_wall_user u ON m.user_id = u.id WHERE m.status = 1 and u.wall_id = ' . $this->market_id . ' ORDER BY m.addtime DESC LIMIT 0, 10';
				$data = $this->model->query($sql);

				if ($data) {
					usort($data, function($a, $b) {
						if ($a['addtime'] == $b['addtime']) {
							return 0;
						}

						return $b['addtime'] < $a['addtime'] ? 1 : -1;
					});

					foreach ($data as $k => $v) {
						$data[$k]['addtime'] = local_date('Y-m-d H:i:s', $v['addtime']);
					}
				}

				cache($cache_key, $data, 10);
				$list = cache($cache_key);
			}

			$sql = 'SELECT count(*) as num FROM {pre}wechat_wall_msg m LEFT JOIN {pre}wechat_wall_user u ON m.user_id = u.id WHERE m.status = 1 AND u.wall_id = ' . $this->market_id . ' ORDER BY m.addtime DESC';
			$num = $this->model->query($sql);
			$this->assign('msg_count', $num[0]['num']);
		}

		$this->assign('wall', $wall);
		$this->assign('list', $list);
		$this->display();
	}

	public function actionWallUser()
	{
		$wall = dao('wechat_marketing')->field('id, name, logo, background, starttime, endtime, config, description, support,status')->where(array('id' => $this->market_id, 'marketing_type' => 'wall', 'wechat_id' => $this->wechat_id))->find();
		$wall['status'] = get_status($wall['starttime'], $wall['endtime']);
		$wall['logo'] = get_wechat_image_path($wall['logo']);
		$wall['background'] = get_wechat_image_path($wall['background']);
		$list = dao('wechat_wall_user')->field('nickname, headimg')->where(array('wall_id' => $this->market_id, 'status' => 1, 'wechat_id' => $this->wechat_id))->order('addtime desc')->select();
		$this->assign('wall', $wall);
		$this->assign('list', $list);
		$this->display();
	}

	public function actionWallPrize()
	{
		$wall = dao('wechat_marketing')->field('id, name, logo, background, starttime, endtime, config, description, support')->where(array('id' => $this->market_id, 'marketing_type' => 'wall', 'wechat_id' => $this->wechat_id))->find();

		if ($wall) {
			$wall['config'] = unserialize($wall['config']);
			$wall['logo'] = get_wechat_image_path($wall['logo']);
			$wall['background'] = get_wechat_image_path($wall['background']);
		}

		$sql = 'SELECT u.nickname, u.headimg, u.id, u.wechatname, u.headimgurl FROM {pre}wechat_prize p LEFT JOIN {pre}wechat_wall_user u ON u.openid = p.openid WHERE u.wall_id = ' . $this->market_id . ' AND u.status = 1 AND u.openid in (SELECT openid FROM {pre}wechat_prize WHERE market_id = ' . $this->market_id . ' AND wechat_id = ' . $this->wechat_id . ' AND activity_type = \'wall\' AND prize_type = 1) GROUP BY u.id ORDER BY p.dateline ASC';
		$rs = $this->model->query($sql);
		$list = array();

		if ($rs) {
			foreach ($rs as $k => $v) {
				$list[$k + 1] = $v;
			}
		}

		$total = dao('wechat_wall_user')->where(array('status' => 1, 'wechat_id' => $this->wechat_id))->count();
		$this->assign('total', $total);
		$this->assign('prize_num', count($list));
		$this->assign('list', $list);
		$this->assign('wall', $wall);
		$this->display();
	}

	public function actionNoPrize()
	{
		if (IS_AJAX) {
			$result['errCode'] = 0;
			$result['errMsg'] = '';
			$wall_id = I('get.wall_id');

			if (empty($wall_id)) {
				$result['errCode'] = 1;
				$result['errMsg'] = url('index/index');
				exit(json_encode($result));
			}

			$sql = 'SELECT nickname, headimg, id, wechatname, headimgurl FROM {pre}wechat_wall_user WHERE wall_id = \'' . $wall_id . '\' AND status = 1 AND openid not in (SELECT openid FROM {pre}wechat_prize WHERE market_id = \'' . $wall_id . '\' AND wechat_id = \'' . $this->wechat_id . '\' AND activity_type = \'wall\') ORDER BY addtime DESC';
			$no_prize = $this->model->query($sql);

			if (empty($no_prize)) {
				$result['errCode'] = 2;
				$result['errMsg'] = '暂无参与抽奖用户';
				exit(json_encode($result));
			}

			$result['data'] = $no_prize;
			exit(json_encode($result));
		}
	}

	public function actionStartDraw()
	{
		if (IS_AJAX) {
			$result['errCode'] = 0;
			$result['errMsg'] = '';
			$wall_id = I('get.wall_id');

			if (empty($wall_id)) {
				$result['errCode'] = 1;
				$result['errMsg'] = url('index/index');
				exit(json_encode($result));
			}

			$wall = dao('wechat_marketing')->field('id, name, logo, background, starttime, endtime, config, description, support')->where(array('id' => $wall_id, 'marketing_type' => 'wall', 'status' => 1, 'wechat_id' => $this->wechat_id))->find();

			if (empty($wall)) {
				$result['errCode'] = 1;
				$result['errMsg'] = url('index/index');
				exit(json_encode($result));
			}

			$nowtime = gmtime();
			if (($nowtime < $wall['starttime']) || ($wall['endtime'] < $nowtime)) {
				$result['errCode'] = 2;
				$result['errMsg'] = '活动尚未开始或者已结束';
				exit(json_encode($result));
			}

			$sql = 'SELECT u.nickname, u.headimg, u.openid, u.id, u.wechatname, u.headimgurl FROM {pre}wechat_wall_user u LEFT JOIN {pre}wechat_prize p ON u.openid = p.openid WHERE u.wall_id = \'' . $wall_id . '\' AND u.status = 1 AND u.openid not in (SELECT openid FROM {pre}wechat_prize WHERE market_id = \'' . $wall_id . '\' AND wechat_id = \'' . $this->wechat_id . '\' AND activity_type = \'wall\') ORDER BY u.addtime DESC';
			$list = $this->model->query($sql);

			if ($list) {
				$key = mt_rand(0, count($list) - 1);
				$rs = (isset($list[$key]) ? $list[$key] : $list[0]);
				$data['wechat_id'] = $this->wechat_id;
				$data['openid'] = $rs['openid'];
				$data['issue_status'] = 0;
				$data['dateline'] = $nowtime;
				$data['prize_type'] = 1;
				$data['activity_type'] = 'wall';
				$data['market_id'] = $wall_id;
				dao('wechat_prize')->data($data)->add();
				$rs['prize_num'] = dao('wechat_prize')->where(array('market_id' => $wall_id, 'wechat_id' => $this->wechat_id, 'activity_type' => 'wall'))->count();
				$result['data'] = $rs;
				exit(json_encode($result));
			}
		}

		$result['errCode'] = 2;
		$result['errMsg'] = '暂无数据';
		exit(json_encode($result));
	}

	public function actionResetDraw()
	{
		if (IS_AJAX) {
			$result['errCode'] = 0;
			$result['errMsg'] = '';
			$wall_id = I('get.wall_id');

			if (empty($wall_id)) {
				$result['errCode'] = 1;
				$result['errMsg'] = url('index/index');
				exit(json_encode($result));
			}

			dao('wechat_prize')->data(array('prize_type' => 0))->where(array('market_id' => $wall_id, 'activity_type' => 'wall', 'wechat_id' => $this->wechat_id))->save();
			exit(json_encode($result));
		}

		$result['errCode'] = 2;
		$result['errMsg'] = '无效的请求';
		exit(json_encode($result));
	}

	public function actionWallUserWechat()
	{
		if (!empty($_SESSION)) {
			if (IS_POST) {
				$wall_id = I('post.wall_id');

				if (empty($wall_id)) {
					show_message('请选择对应的活动');
				}

				$data['nickname'] = I('post.nickname');
				$data['headimg'] = I('post.headimg');
				$data['sex'] = I('post.sex');
				$data['wall_id'] = $wall_id;
				$data['wechat_id'] = $this->wechat_id;
				$data['addtime'] = gmtime();
				$data['openid'] = !empty($_SESSION['openid']) ? $_SESSION['openid'] : '';
				$data['wechatname'] = !empty($_SESSION['nickname']) ? $_SESSION['nickname'] : '';
				$data['headimgurl'] = !empty($_SESSION['avatar']) ? $_SESSION['avatar'] : '';
				dao('wechat_wall_user')->data($data)->add();
				$this->redirect(url('wall_msg_wechat', array('wall_id' => $wall_id)));
				exit();
			}

			$wall_id = $this->market_id;
			$wechat_user = dao('wechat_wall_user')->where(array('openid' => $_SESSION['openid']))->count();

			if (0 < $wechat_user) {
				$this->redirect(url('wall_msg_wechat', array('wall_id' => $wall_id)));
			}

			$user = $_SESSION;
			$this->assign('user', $user);
			$this->assign('wall_id', $wall_id);
			$this->display();
		}
	}

	public function actionWallMsgWechat()
	{
		if (!empty($_SESSION['openid'])) {
			if (IS_POST && IS_AJAX) {
				$wall_id = I('post.wall_id');

				if (empty($wall_id)) {
					exit(json_encode(array('code' => 1, 'errMsg' => '请选择对应的活动')));
				}

				$data['user_id'] = I('post.user_id');
				$data['content'] = I('post.content', '', 'trim,htmlspecialchars');
				if (empty($data['user_id']) || empty($data['content'])) {
					exit(json_encode(array('code' => 1, 'errMsg' => '请先登录或者发表的内容不能为空')));
				}

				$data['addtime'] = gmtime();
				$data['wechat_id'] = $this->wechat_id;
				dao('wechat_wall_msg')->data($data)->add();
				exit(json_encode(array('code' => 0, 'errMsg' => '您的留言正在进行审查，请关注微信墙')));
			}

			$wall_id = I('get.wall_id');

			if (empty($wall_id)) {
				$this->redirect(url('index/index'));
			}

			$openid = $_SESSION['openid'];
			$wechat_user = dao('wechat_wall_user')->field('id')->where(array('openid' => $openid))->find();
			$user_num = dao('wechat_wall_msg')->field('user_id')->count();
			$cache_key = md5('cache_wechat_0');
			$list = cache($cache_key);

			if ($list === false) {
				$sql = 'SELECT m.content, m.addtime, u.nickname, u.headimg, u.id FROM {pre}wechat_wall_msg m LEFT JOIN {pre}wechat_wall_user u ON m.user_id = u.id WHERE (m.status = 1 OR u.openid = \'' . $openid . '\') AND u.wall_id = \'' . $wall_id . '\' ORDER BY m.addtime DESC LIMIT 0, 10';
				$data = $this->model->query($sql);

				if ($data) {
					usort($data, function($a, $b) {
						if ($a['addtime'] == $b['addtime']) {
							return 0;
						}

						return $b['addtime'] < $a['addtime'] ? 1 : -1;
					});
				}

				cache($cache_key, $data, 10);
				$list = cache($cache_key);
			}

			$sql = 'SELECT count(*) as num FROM {pre}wechat_wall_msg m LEFT JOIN {pre}wechat_wall_user u ON m.user_id = u.id WHERE (m.status = 1 OR u.openid = \'' . $openid . '\') AND u.wall_id = \'' . $wall_id . '\' ORDER BY m.addtime DESC';
			$num = $this->model->query($sql);
			$this->assign('list', $list);
			$this->assign('msg_count', $num[0]['num']);
			$this->assign('user_num', $user_num);
			$this->assign('user', $wechat_user);
			$this->assign('wall_id', $wall_id);
			$this->display();
		}
	}

	public function actionGetWallMsg()
	{
		if (IS_AJAX && IS_GET) {
			$start = I('get.start', 0, 'intval');
			$num = I('get.num', 5);
			$wall_id = I('get.wall_id');
			if ((!empty($start) || ($start === 0)) && $num) {
				$cache_key = md5('cache_' . $start);
				if (isset($_SESSION) && !empty($_SESSION['openid'])) {
					$cache_key = md5('cache_wechat_' . $start);
				}

				$list = cache($cache_key);

				if ($list === false) {
					$sql = 'SELECT m.content, m.addtime, u.nickname, u.headimg, u.id, m.status FROM {pre}wechat_wall_msg m LEFT JOIN {pre}wechat_wall_user u ON m.user_id = u.id WHERE m.status = 1 AND u.wall_id = \'' . $wall_id . '\' ORDER BY m.addtime ASC LIMIT ' . $start . ', ' . $num;
					if (isset($_SESSION) && !empty($_SESSION['openid'])) {
						$openid = $_SESSION['openid'];
						$sql = 'SELECT m.content, m.addtime, u.nickname, u.headimg, u.id, m.status FROM {pre}wechat_wall_msg m LEFT JOIN {pre}wechat_wall_user u ON m.user_id = u.id WHERE (m.status = 1 OR u.openid = \'' . $openid . '\') AND u.wall_id = \'' . $wall_id . '\' ORDER BY m.addtime ASC LIMIT ' . $start . ', ' . $num;
					}

					$data = $this->model->query($sql);
					cache($cache_key, $data, 10);
					$list = cache($cache_key);
				}

				foreach ($list as $k => $v) {
					$list[$k]['addtime'] = local_date('Y-m-d H:i:s', $v['addtime']);
				}

				$result = array('code' => 0, 'data' => $list);
				exit(json_encode($result));
			}
		}
		else {
			$result = array('code' => 1, 'errMsg' => '请求不合法');
			exit(json_encode($result));
		}
	}
}

?>
