<?php
//cgxlm
namespace App\Http\Wechat\Controllers;

class Admin extends \App\Http\Base\Controllers\Backend
{
	protected $weObj = '';
	protected $wechat_id = 0;
	protected $page_num = 0;

	public function __construct()
	{
		parent::__construct();
		L(require MODULE_PATH . 'Language/' . C('shop.lang') . '/wechat.php');
		$this->assign('lang', array_change_key_case(L()));
		$this->wechat_id = 1;
		$condition['id'] = $this->wechat_id;
		$wechatinfo = $this->model->table('wechat')->field('*')->where($condition)->find();

		if (empty($wechatinfo)) {
			$data = array('id' => $this->wechat_id, 'time' => gmtime(), 'type' => 2, 'status' => 1, 'default_wx' => 1);
			$this->model->table('wechat')->data($data)->add();
			$this->redirect('modify');
		}

		$this->get_config();
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
		$this->redirect('modify');
	}

	public function actionAppend()
	{
		$this->redirect('index');
	}

	public function actionModify()
	{
		$this->admin_priv('wechat_admin');
		$condition['id'] = $this->wechat_id;

		if (IS_POST) {
			$data = I('post.data', '', 'trim');
			$form = new \App\Extensions\Form();

			if (!$form->isEmpty($data['name'], 1)) {
				$this->message(L('must_name'), null, 2);
			}

			if (!$form->isEmpty($data['orgid'], 1)) {
				$this->message(L('must_id'), null, 2);
			}

			if (!$form->isEmpty($data['token'], 1)) {
				$this->message(L('must_token'), null, 2);
			}

			if (strpos($data['appsecret'], '*') == true) {
				unset($data['appsecret']);
			}

			$data['secret_key'] = md5($data['orgid'] . $data['appid']);
			$this->model->table('wechat')->data($data)->where($condition)->save();
			$this->message(L('wechat_editor') . L('success'), url('modify'));
		}

		$data = $this->model->table('wechat')->where($condition)->find();
		$data['secret_key'] = isset($data['orgid']) && isset($data['appid']) ? $data['secret_key'] : '';
		$data['url'] = url('wechat/index/index', array('key' => $data['secret_key']), false, true);
		$data['appsecret'] = string_to_star($data['appsecret']);
		$this->assign('data', $data);
		$this->display();
	}

	public function actionMenuList()
	{
		$this->admin_priv('menu');
		$list = $this->model->table('wechat_menu')->where(array('wechat_id' => $this->wechat_id))->order('sort asc')->select();
		$result = array();

		if (is_array($list)) {
			foreach ($list as $vo) {
				if ($vo['pid'] == 0) {
					$vo['val'] = $vo['type'] == 'click' ? $vo['key'] : $vo['url'];
					$sub_button = array();

					foreach ($list as $val) {
						$val['val'] = $val['type'] == 'click' ? $val['key'] : $val['url'];

						if ($val['pid'] == $vo['id']) {
							$sub_button[] = $val;
						}
					}

					$vo['sub_button'] = $sub_button;
					$result[] = $vo;
				}
			}
		}

		$this->assign('list', $result);
		$this->display();
	}

	public function actionMenuEdit()
	{
		$this->admin_priv('menu');

		if (IS_POST) {
			$id = I('post.id');
			$data = I('post.data');
			$data['wechat_id'] = $this->wechat_id;

			if ('click' == $data['type']) {
				if (empty($data['key'])) {
					exit(json_encode(array('status' => 0, 'msg' => L('menu_keyword') . L('empty'))));
				}

				$data['url'] = '';
			}
			else {
				if (empty($data['url'])) {
					exit(json_encode(array('status' => 0, 'msg' => L('menu_url') . L('empty'))));
				}

				if (substr($data['url'], 0, 4) !== 'http') {
					exit(json_encode(array('status' => 0, 'msg' => L('menu_url') . L('link_err'))));
				}

				if (120 < strlen($data['url'])) {
					exit(json_encode(array('status' => 0, 'msg' => L('menu_url_length'))));
				}

				$data['key'] = '';
			}

			if (!empty($id)) {
				$this->model->table('wechat_menu')->data($data)->where(array('id' => $id))->save();
			}
			else {
				$this->model->table('wechat_menu')->data($data)->add();
			}

			exit(json_encode(array('status' => 1, 'msg' => L('menu_edit') . L('success'))));
		}

		$id = I('get.id');
		$info = array();
		$top_menu = $this->model->table('wechat_menu')->where(array('pid' => 0, 'wechat_id' => $this->wechat_id))->select();

		if (!empty($id)) {
			$info = $this->model->table('wechat_menu')->where(array('id' => $id))->find();
			$top_menu = $this->model->query('SELECT * FROM {pre}wechat_menu WHERE id <> ' . $id . ' AND pid = 0 AND wechat_id = ' . $this->wechat_id);
		}

		if (empty($info)) {
			$info['status'] = 1;
			$info['sort'] = 0;
			$info['type'] = 'click';
		}

		$this->assign('top_menu', $top_menu);
		$this->assign('info', $info);
		$this->display();
	}

	public function actionMenuDel()
	{
		$this->admin_priv('menu');
		$id = I('get.id');

		if (empty($id)) {
			$this->message(L('menu_select_del'), null, 2);
		}

		$minfo = $this->model->table('wechat_menu')->field('id, pid')->where(array('id' => $id))->find();

		if ($minfo['pid'] == 0) {
			$this->model->table('wechat_menu')->where(array('pid' => $minfo['id']))->delete();
		}

		$this->model->table('wechat_menu')->where(array('id' => $minfo['id']))->delete();
		$this->message(L('drop') . L('success'), url('menu_list'));
	}

	public function actionSysMenu()
	{
		$this->admin_priv('menu');
		$list = $this->model->table('wechat_menu')->where(array('status' => 1, 'wechat_id' => $this->wechat_id))->order('sort asc')->select();

		if (empty($list)) {
			$this->message(L('menu_empty'), null, 2);
		}

		$data = array();

		if (is_array($list)) {
			foreach ($list as $val) {
				if ($val['pid'] == 0) {
					$sub_button = array();

					foreach ($list as $v) {
						if ($v['pid'] == $val['id']) {
							$sub_button[] = $v;
						}
					}

					$val['sub_button'] = $sub_button;
					$data[] = $val;
				}
			}
		}

		$menu_list = array();

		foreach ($data as $key => $val) {
			if (empty($val['sub_button'])) {
				$menu_list['button'][$key]['type'] = $val['type'];
				$menu_list['button'][$key]['name'] = $val['name'];

				if ('click' == $val['type']) {
					$menu_list['button'][$key]['key'] = $val['key'];
				}
				else {
					$menu_list['button'][$key]['url'] = html_out($val['url']);
				}
			}
			else {
				$menu_list['button'][$key]['name'] = $val['name'];

				foreach ($val['sub_button'] as $k => $v) {
					$menu_list['button'][$key]['sub_button'][$k]['type'] = $v['type'];
					$menu_list['button'][$key]['sub_button'][$k]['name'] = $v['name'];

					if ('click' == $v['type']) {
						$menu_list['button'][$key]['sub_button'][$k]['key'] = $v['key'];
					}
					else {
						$menu_list['button'][$key]['sub_button'][$k]['url'] = html_out($v['url']);
					}
				}
			}
		}

		$rs = $this->weObj->createMenu($menu_list);

		if (empty($rs)) {
			$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, null, 2);
		}

		$this->message(L('menu_create') . L('success'), url('menu_list'));
	}

	public function actionSubscribeList()
	{
		$this->admin_priv('fans');
		$offset = $this->pageLimit(url('subscribe_list'), $this->page_num);
		$total = $this->model->table('wechat_user')->where(array('wechat_id' => $this->wechat_id, 'subscribe' => 1))->order('subscribe_time desc')->count();
		$sql = 'SELECT u.* FROM {pre}wechat_user u LEFT JOIN {pre}wechat_user_tag t ON u.openid = t.openid  where u.subscribe = 1 and u.wechat_id = ' . $this->wechat_id . ' group by u.unionid order by u.subscribe_time desc limit ' . $offset;
		$list = $this->model->query($sql);

		foreach ($list as $key => $value) {
			$list[$key]['taglist'] = $this->get_user_tag($value['openid']);
			$users = get_connect_user($value['unionid']);

			if (!empty($users)) {
				$list[$key]['user_name'] = $users['user_name'];
				$list[$key]['from'] = get_wechat_user_from($value['from']);
				$list[$key]['look_user_url'] = '../' . ADMIN_PATH . '/users.php?act=edit&id=' . $users['user_id'];
			}
		}

		$where['wechat_id'] = $this->wechat_id;
		$tag_list = $this->model->table('wechat_user_taglist')->field('id, tag_id, name, count')->where($where)->order('id, sort desc')->select();
		$this->assign('tag_list', $tag_list);
		$where1['wechat_id'] = $this->wechat_id;
		$group_list = $this->model->table('wechat_user_group')->field('id, group_id, name, count')->where($where1)->order('id, sort desc')->select();
		$this->assign('page', $this->pageShow($total));
		$this->assign('list', $list);
		$this->assign('group_list', $group_list);
		$this->display();
	}

	public function actionSubscribeSearch()
	{
		$this->admin_priv('fans');
		$keywords = I('request.keywords', '', 'trim');
		$group_id = I('request.group_id', 0, 'intval');
		$tag_id = I('request.tag_id', 0, 'intval');
		$where = '';
		$where1 = '';

		if (!empty($keywords)) {
			$where .= ' and (u.nickname like "%' . $keywords . '%" or u.city like "%' . $keywords . '%" or u.country like "%' . $keywords . '%" or u.province like "%' . $keywords . '%" )';
		}

		if (isset($group_id) && (0 < $group_id)) {
			$where .= ' and u.groupid = ' . $group_id;
		}

		if (isset($tag_id) && (0 < $tag_id)) {
			$where .= ' and t.tag_id = ' . $tag_id;
		}

		$filter['group_id'] = $group_id;
		$filter['tag_id'] = $tag_id;
		$filter['keywords'] = $keywords;
		$offset = $this->pageLimit(url('subscribe_search', $filter), $this->page_num);
		$sql = "SELECT count(*) as number FROM {pre}wechat_user u\r\n            LEFT JOIN {pre}wechat_user_tag t ON u.openid = t.openid\r\n            WHERE u.subscribe = 1 AND u.wechat_id = " . $this->wechat_id . $where . ' group by u.unionid order by u.subscribe_time desc';
		$total = $this->model->query($sql);
		$sql1 = "SELECT u.* FROM {pre}wechat_user u\r\n            LEFT JOIN {pre}wechat_user_tag t ON u.openid = t.openid\r\n            WHERE u.subscribe = 1 AND u.wechat_id = " . $this->wechat_id . $where . ' group by u.unionid order by u.subscribe_time desc limit ' . $offset;
		$list = $this->model->query($sql1);

		foreach ($list as $key => $value) {
			$list[$key]['taglist'] = $this->get_user_tag($value['openid']);
			$users = get_connect_user($value['unionid']);

			if (!empty($users)) {
				$list[$key]['user_name'] = $users['user_name'];
				$list[$key]['look_user_url'] = '../' . ADMIN_PATH . '/users.php?act=edit&id=' . $users['user_id'];
			}
		}

		$where2['wechat_id'] = $this->wechat_id;
		$group_list = $this->model->table('wechat_user_group')->field('id, group_id, name, count')->where($where2)->order('id, sort desc')->select();
		$where3['wechat_id'] = $this->wechat_id;
		$tag_list = $this->model->table('wechat_user_taglist')->field('id, tag_id, name, count')->where($where3)->order('id, sort desc')->select();
		$this->assign('tag_list', $tag_list);
		$this->assign('page', $this->pageShow($total[0]['number']));
		$this->assign('list', $list);
		$this->assign('group_id', $group_id);
		$this->assign('group_list', $group_list);
		$this->assign('tag_id', $tag_id);
		$this->assign('tag_list', $tag_list);
		$this->display('subscribelist');
	}

	private function get_user_tag($openid = '')
	{
		$sql = 'SELECT tl.tag_id, tl.name FROM {pre}wechat_user_taglist tl LEFT JOIN {pre}wechat_user_tag t ON tl.tag_id = t.tag_id LEFT JOIN {pre}wechat_user u ON u.openid = t.openid where u.openid = \'' . $openid . '\' and u.subscribe = 1 and tl.wechat_id = \'' . $this->wechat_id . '\' ';
		$tags = $this->model->query($sql);
		$num = count($tags);

		if ($num < 3) {
			$rs = $this->weObj->getUserTaglist($openid);

			if (!empty($rs)) {
				foreach ($rs as $key => $val) {
					$data['wechat_id'] = $this->wechat_id;
					$data['tag_id'] = $val;
					$data['openid'] = $openid;
					$where = array('tag_id' => $val, 'wechat_id' => $this->wechat_id);
					$tag_num = $this->model->table('wechat_user_tag')->where($where)->count();

					if (0 < $tag_num) {
						$this->model->table('wechat_user_tag')->data($data)->where($where)->save();
					}
					else {
						$this->model->table('wechat_user_tag')->data($data)->add();
					}
				}
			}
		}

		return $tags;
	}

	public function actionSubscribeMove()
	{
		if (IS_POST) {
			if (empty($this->wechat_id)) {
				$this->message(L('wechat_empty'), null, 2);
			}

			$group_id = I('post.group_id', 0, 'intval');
			$openid = I('post.id');

			if (is_array($openid)) {
				foreach ($openid as $v) {
					$this->weObj->updateGroupMembers($group_id, $v);
					$this->model->table('wechat_user')->data(array('groupid' => $group_id))->where(array('openid' => $v, 'wechat_id' => $this->wechat_id))->save();
				}

				$this->message(L('sub_move_sucess'), url('subscribe_list'));
			}
			else {
				$this->message(L('select_please'), null, 2);
			}
		}
	}

	public function actionSysfans()
	{
		$wechat_user = $this->weObj->getUserList();

		foreach ($wechat_user['data']['openid'] as $v) {
			$info = $this->weObj->getUserInfo($v);
			$info['wechat_id'] = $this->wechat_id;
			$this->model->table('wechat_user')->data($info)->add();
		}

		$this->actionSysTags();
		$this->redirect('subscribe_list', array('wechat_id' => $this->wechat_id));
	}

	public function actionSubscribeUpdate()
	{
		if (empty($this->wechat_id)) {
			$this->message(L('wechat_empty'), null, 2);
		}

		$where['wechat_id'] = $this->wechat_id;
		$local_user = $this->model->table('wechat_user')->field('openid')->where($where)->select();

		if (empty($local_user)) {
			$local_user = array();
		}

		$user_list = array();

		foreach ($local_user as $v) {
			$user_list[] = $v['openid'];
		}

		$wechat_user = $this->weObj->getUserList();

		if ($wechat_user['total'] <= 10000) {
			$wechat_user_list = $wechat_user['data']['openid'];
		}
		else {
			$num = ceil($wechat_user['total'] / 10000);
			$wechat_user_list = $wechat_user['data']['openid'];

			for ($i = 0; $i <= $num; $i++) {
				$wechat_user1 = $this->weObj->getUserList($wechat_user['next_openid']);
				$wechat_user_list = array_merge($wechat_user_list, $wechat_user1['data']['openid']);
			}
		}

		foreach ($local_user as $val) {
			if (in_array($val['openid'], $wechat_user_list)) {
				$info = $this->weObj->getUserInfo($val['openid']);
				$where1['openid'] = $val['openid'];
				$where1['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_user')->data($info)->where($where1)->save();
			}
			else {
				$data['subscribe'] = 0;
				$where2['openid'] = $val['openid'];
				$where2['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_user')->data($data)->where($where2)->save();
			}
		}

		foreach ($wechat_user_list as $vs) {
			if (!in_array($vs, $user_list)) {
				$info = $this->weObj->getUserInfo($vs);
				$info['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_user')->data($info)->add();
			}
		}

		$this->redirect('subscribe_list');
	}

	public function actionSendCustomMessage()
	{
		$this->admin_priv('fans');

		if (IS_POST) {
			$data = I('post.data');
			$openid = I('post.openid');
			$form = new \App\Extensions\Form();

			if (!$form->isEmpty($openid, 1)) {
				exit(json_encode(array('status' => 0, 'msg' => L('select_openid'))));
			}

			if (!$form->isEmpty($data['msg'], 1)) {
				exit(json_encode(array('status' => 0, 'msg' => L('message_content') . L('empty'))));
			}

			$data['send_time'] = gmtime();
			$data['wechat_id'] = $this->wechat_id;
			$data['is_wechat_admin'] = 1;
			$msg = array(
				'touser'  => $openid,
				'msgtype' => 'text',
				'text'    => array('content' => $data['msg'])
				);
			$rs = $this->weObj->sendCustomMessage($msg);

			if (empty($rs)) {
				$errmsg = '仅48小时内给公众号发送过信息的粉丝才能接收到信息';
				exit(json_encode(array('status' => 0, 'msg' => $errmsg)));
			}

			dao('wechat_custom_message')->data($data)->add();
			exit(json_encode(array('status' => 1)));
		}

		$uid = I('get.uid');
		$openid = I('get.openid');

		if ($openid) {
			$where['openid'] = $openid;
		}
		else {
			$where['uid'] = $uid;
		}

		$where['wechat_id'] = $this->wechat_id;
		$info = dao('wechat_user')->field('uid, headimgurl, nickname, openid')->where($where)->find();
		$info['headimgurl'] = substr($info['headimgurl'], 0, -1) . '64';
		$list = dao('wechat_custom_message')->field('msg, send_time, is_wechat_admin')->where(array('uid' => $uid, 'wechat_id' => $this->wechat_id))->order('send_time DESC, id DESC')->limit(6)->select();
		$list = array_reverse($list);

		foreach ($list as $key => $value) {
			$list[$key]['send_time'] = local_date('Y-m-d H:i:s', $value['send_time']);
			$list[$key]['headimgurl'] = $info['headimgurl'];
			$list[$key]['wechat_headimgurl'] = __TPL__ . '/img/shop_app_icon.png';
		}

		$this->assign('list', $list);
		$this->assign('info', $info);
		$this->display();
	}

	public function actionCustomMessageList()
	{
		$this->admin_priv('fans');
		$uid = I('get.uid', 0, 'intval');

		if (empty($uid)) {
			$this->message(L('select_openid'), null, 2);
		}

		$nickname = $this->model->table('wechat_user')->where(array('uid' => $uid, 'wechat_id' => $this->wechat_id))->getField('nickname');
		$filter['uid'] = $uid;
		$offset = $this->pageLimit(url('custom_message_list', $filter), $this->page_num);
		$total = $this->model->table('wechat_custom_message')->where(array('uid' => $uid, 'wechat_id' => $this->wechat_id))->order('send_time desc')->count();
		$list = $this->model->table('wechat_custom_message')->field('msg, send_time, wechat_id')->where(array('uid' => $uid, 'wechat_id' => $this->wechat_id))->order('send_time desc, id desc')->limit($offset)->select();

		foreach ($list as $key => $value) {
			$list[$key]['send_time'] = local_date('Y-m-d H:i:s', $value['send_time']);
		}

		$this->assign('page', $this->pageShow($total));
		$this->assign('list', $list);
		$this->assign('nickname', $nickname);
		$this->display();
	}

	public function actionTagsList()
	{
		$where['wechat_id'] = $this->wechat_id;
		$tag_list = $this->model->table('wechat_user_taglist')->where($where)->order('id, sort desc')->select();
		$this->assign('list', $tag_list);
		$this->display();
	}

	public function actionSysTags()
	{
		if (empty($this->wechat_id)) {
			$this->message(L('wechat_empty'), null, 2);
		}

		$list = $this->weObj->getTags();

		if (empty($list)) {
			$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, null, 2);
		}

		$where['wechat_id'] = $this->wechat_id;
		$this->model->table('wechat_user_taglist')->where($where)->delete();

		foreach ($list['tags'] as $key => $val) {
			$data['wechat_id'] = $this->wechat_id;
			$data['tag_id'] = $val['id'];
			$data['name'] = $val['name'];
			$data['count'] = $val['count'];
			$where = array('tag_id' => $val['id'], 'wechat_id' => $this->wechat_id);
			$tag_num = $this->model->table('wechat_user_taglist')->where($where)->count();

			if (0 < $tag_num) {
				$this->model->table('wechat_user_taglist')->data($data)->where($where)->save();
			}
			else {
				$this->model->table('wechat_user_taglist')->data($data)->add();
			}
		}

		$this->redirect('subscribe_list');
	}

	public function actionTagsEdit()
	{
		if (empty($this->wechat_id)) {
			$this->message(L('wechat_empty'), null, 2);
		}

		if (IS_POST) {
			$name = I('post.name');
			$id = I('post.id', 0, 'intval');
			$tag_id = I('post.tag_id', 0, 'intval');

			if (empty($name)) {
				exit(json_encode(array('status' => 0, 'msg' => L('group_name') . L('empty'))));
			}

			$data['name'] = $name;

			if (!empty($id)) {
				$rs = $this->weObj->updateTags($tag_id, $name);

				if (empty($rs)) {
					exit(json_encode(array('status' => 0, 'msg' => L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg)));
				}

				$where['id'] = $id;
				$where['wechat_id'] = $this->wechat_id;
				$data['tag_id'] = !empty($rs['tag']['id']) ? $rs['tag']['id'] : $tag_id;
				$this->model->table('wechat_user_taglist')->data($data)->where($where)->save();
			}
			else {
				$rs = $this->weObj->createTags($name);

				if (empty($rs)) {
					exit(json_encode(array('status' => 0, 'msg' => L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg)));
				}

				$data['tag_id'] = !empty($rs['tag']['id']) ? $rs['tag']['id'] : $tag_id;
				$data['name'] = $rs['tag']['name'];
				$data['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_user_taglist')->data($data)->add();
			}

			exit(json_encode(array('status' => 1)));
		}

		$id = I('get.id', 0, 'intval');
		$taglist = array();

		if (!empty($id)) {
			$where['id'] = $id;
			$where['wechat_id'] = $this->wechat_id;
			$taglist = $this->model->table('wechat_user_taglist')->field('id, tag_id, name')->where($where)->find();
		}

		$this->assign('taglist', $taglist);
		$this->display();
	}

	public function actionBatchTagging()
	{
		if (IS_POST) {
			if (empty($this->wechat_id)) {
				$this->message(L('wechat_empty'), null, 2);
			}

			$tag_id = I('post.tag_id', 0, 'intval');
			$openlist = I('post.id');

			if (is_array($openlist)) {
				$rs = $this->weObj->batchtaggingTagsMembers($tag_id, $openlist);

				if (empty($rs)) {
					$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, url('subscribe_list'), 2);
				}

				$is_true = 0;

				foreach ($openlist as $v) {
					$sql = 'SELECT u.uid, count(t.openid) as openid_num FROM {pre}wechat_user_tag t LEFT JOIN {pre}wechat_user u ON t.openid = u.openid WHERE u.openid = \'' . $v . '\' AND u.subscribe = 1 AND u.wechat_id = \' ' . $this->wechat_id . '\' ';
					$res = $this->model->query($sql);

					if (!empty($res)) {
						if ($res[0]['openid_num'] < 3) {
							$data['wechat_id'] = $this->wechat_id;
							$data['tag_id'] = $tag_id;
							$data['openid'] = $v;
							$where2 = array('tag_id' => $tag_id, 'openid' => $v);
							$tag_num = $this->model->table('wechat_user_tag')->where($where2)->count();

							if ($tag_num == 0) {
								$this->model->table('wechat_user_tag')->data($data)->add();
							}
							else {
								$is_true = 1;
							}
						}
						else {
							$is_true = 3;
						}
					}
				}

				if ($is_true == 0) {
					$this->message(L('tag_move_sucess'), url('subscribe_list'));
				}
				else if ($is_true == 1) {
					$this->message(L('tag_move_fail') . ', ' . L('tag_move_exit'), url('subscribe_list'), 2);
				}
				else if ($is_true == 3) {
					$this->message(L('tag_move_fail') . ', ' . L('tag_move_three'), url('subscribe_list'), 2);
				}
			}
			else {
				$this->message(L('select_please'), null, 2);
			}
		}
	}

	public function actionBatchUnTagging()
	{
		if (IS_POST) {
			if (empty($this->wechat_id)) {
				$this->message(L('wechat_empty'), null, 2);
			}

			$tag_id = I('post.tagid', 0, 'intval');
			$openid = I('post.openid');
			$openlist = array($openid);

			if (is_array($openlist)) {
				$rs = $this->weObj->batchuntaggingTagsMembers($tag_id, $openlist);

				if (empty($rs)) {
					exit(json_encode(array('status' => 0, 'msg' => L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg)));
				}

				foreach ($openlist as $v) {
					$where = array('tag_id' => $tag_id, 'openid' => $v);
					$this->db->table('wechat_user_tag')->where($where)->delete();
				}

				exit(json_encode(array('status' => 1, 'msg' => L('tag_move_sucess'))));
			}
			else {
				exit(json_encode(array('status' => 0, 'msg' => L('select_please') . L('empty'))));
			}
		}
	}

	public function actionEditUserRemark()
	{
		if (IS_POST) {
			if (empty($this->wechat_id)) {
				$this->message(L('wechat_empty'), null, 2);
			}

			$remark = input('remark', '', 'trim');
			$uid = input('uid', 0, 'intval');

			if (!empty($remark)) {
				$openid = dao('wechat_user')->where(array('uid' => $uid, 'wechat_id' => $this->wechat_id))->getField('openid');
				$rs = $this->weObj->updateUserRemark($openid, $remark);

				if (empty($rs)) {
					exit(json_encode(array('status' => 0, 'msg' => L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg)));
				}

				dao('wechat_user')->data(array('remark' => $remark))->where(array('openid' => $openid, 'wechat_id' => $this->wechat_id))->save();
				exit(json_encode(array('status' => 1, 'msg' => '修改备注名成功')));
			}
			else {
				exit(json_encode(array('status' => 0, 'msg' => L('empty'))));
			}
		}
	}

	public function actionGroupsList()
	{
		$where['wechat_id'] = $this->wechat_id;
		$local_list = $this->model->table('wechat_user_group')->where($where)->order('id, sort desc')->select();
		$this->assign('list', $local_list);
		$this->display();
	}

	public function actionSysGroups()
	{
		if (empty($this->wechat_id)) {
			$this->message(L('wechat_empty'), null, 2);
		}

		$list = $this->weObj->getGroup();

		if (empty($list)) {
			$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, null, 2);
		}

		$where['wechat_id'] = $this->wechat_id;
		$this->model->table('wechat_user_group')->where($where)->delete();

		foreach ($list['groups'] as $key => $val) {
			$data['wechat_id'] = $this->wechat_id;
			$data['group_id'] = $val['id'];
			$data['name'] = $val['name'];
			$data['count'] = $val['count'];
			$this->model->table('wechat_user_group')->data($data)->add();
		}

		$this->redirect('subscribe_list');
	}

	public function actionGroupsEdit()
	{
		if (empty($this->wechat_id)) {
			$this->message(L('wechat_empty'), null, 2);
		}

		if (IS_POST) {
			$name = I('post.name');
			$id = I('post.id', 0, 'intval');
			$group_id = I('post.group_id');

			if (empty($name)) {
				exit(json_encode(array('status' => 0, 'msg' => L('group_name') . L('empty'))));
			}

			$data['name'] = $name;

			if (!empty($id)) {
				$rs = $this->weObj->updateGroup($group_id, $name);

				if (empty($rs)) {
					exit(json_encode(array('status' => 0, 'msg' => L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg)));
				}

				$where['id'] = $id;
				$where['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_user_group')->data($data)->where($where)->save();
			}
			else {
				$rs = $this->weObj->createGroup($name);

				if (empty($rs)) {
					exit(json_encode(array('status' => 0, 'msg' => L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg)));
				}

				$data['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_user_group')->data($data)->add();
			}

			exit(json_encode(array('status' => 1)));
		}

		$id = I('get.id', 0, 'intval');
		$group = array();

		if (!empty($id)) {
			$where['id'] = $id;
			$where['wechat_id'] = $this->wechat_id;
			$group = $this->model->table('wechat_user_group')->field('id, group_id, name')->where($where)->find();
		}

		$this->assign('group', $group);
		$this->display();
	}

	public function actionQrcodeList()
	{
		$this->admin_priv('qrcode');
		$offset = $this->pageLimit(url('qrcode_list'), $this->page_num);
		$total = $this->model->query('SELECT count(*) as count FROM {pre}wechat_qrcode WHERE username is null or username = \'\' AND wechat_id = ' . $this->wechat_id . ' ');
		$list = $this->model->query('SELECT * FROM {pre}wechat_qrcode WHERE username is null or username = \'\' AND wechat_id = ' . $this->wechat_id . ' ORDER BY sort ASC, id DESC');
		$this->assign('page', $this->pageShow($total[0]['count']));
		$this->assign('list', $list);
		$this->display();
	}

	public function actionQrcodeEdit()
	{
		$this->admin_priv('qrcode');

		if (IS_POST) {
			$data = I('post.data');
			$data['wechat_id'] = $this->wechat_id;
			$form = new \App\Extensions\Form();

			if (!$form->isEmpty($data['function'], 1)) {
				exit(json_encode(array('status' => 0, 'msg' => L('qrcode_function') . L('empty'))));
			}

			if (!$form->isEmpty($data['scene_id'], 1)) {
				exit(json_encode(array('status' => 0, 'msg' => L('qrcode_scene_value') . L('empty'))));
			}

			if (!$form->isEmpty($data['expire_seconds'], 1)) {
				$data['expire_seconds'] = 1800;
			}

			$rs = $this->model->table('wechat_qrcode')->where(array('scene_id' => $data['scene_id'], 'wechat_id' => $this->wechat_id))->count();

			if (0 < $rs) {
				exit(json_encode(array('status' => 0, 'msg' => L('qrcode_scene_value_limit'))));
			}

			$this->model->table('wechat_qrcode')->data($data)->add();
			exit(json_encode(array('status' => 1, 'msg' => L('add') . L('success'))));
		}

		$id = I('get.id', 0, 'intval');

		if (!empty($id)) {
			$status = I('get.status', 0, 'intval');
			$this->model->table('wechat_qrcode')->data(array('status' => $status))->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->save();
			$this->redirect('qrcode_list');
		}

		$this->display();
	}

	public function actionShareList()
	{
		$this->admin_priv('share');
		$offset = $this->pageLimit(url('share_list'), $this->page_num);
		$total = $this->model->query('SELECT count(*) as count FROM {pre}wechat_qrcode WHERE username is not null AND username != \'\' AND wechat_id = ' . $this->wechat_id . ' ');
		$list = $this->model->query('SELECT * FROM {pre}wechat_qrcode WHERE username is not null AND username != \'\' AND wechat_id = ' . $this->wechat_id . ' ORDER BY sort ASC, id DESC');

		if ($list) {
			foreach ($list as $key => $val) {
				$list[$key]['share_account'] = $this->model->table('affiliate_log')->where(array('separate_type' => 0, 'user_id' => $val['scene_id']))->getField('sum(money)');
			}
		}

		$this->assign('page', $this->pageShow($total[0]['count']));
		$this->assign('list', $list);
		$this->display();
	}

	public function actionShareEdit()
	{
		$this->admin_priv('qrcode');

		if (IS_POST) {
			$data = I('post.data');
			$data['wechat_id'] = $this->wechat_id;
			$form = new \App\Extensions\Form();

			if (!$form->isEmpty($data['username'], 1)) {
				exit(json_encode(array('status' => 0, 'msg' => L('share_name') . L('empty'))));
			}

			if (!$form->isEmpty($data['scene_id'], 1)) {
				exit(json_encode(array('status' => 0, 'msg' => L('share_userid') . L('empty'))));
			}

			$rs = $this->model->table('wechat_qrcode')->where(array('scene_id' => $data['scene_id'], 'wechat_id' => $this->wechat_id))->count();

			if (0 < $rs) {
				exit(json_encode(array('status' => 0, 'msg' => L('qrcode_scene_limit'))));
			}

			if (empty($data['expire_seconds'])) {
				$data['type'] = 1;
			}
			else {
				$data['type'] = 0;
			}

			$this->model->table('wechat_qrcode')->data($data)->add();
			exit(json_encode(array('status' => 1)));
		}

		$this->display();
	}

	public function actiongetUserId()
	{
		if (IS_AJAX) {
			$username = input('username', '', 'trim');
			$user_id = dao('users')->where(array('user_name' => $username))->getField('user_id');

			if (!empty($user_id)) {
				exit(json_encode(array('error' => 0, 'user_id' => $user_id)));
			}
			else {
				exit(json_encode(array('error' => 1, 'msg' => '用户名不存在')));
			}
		}
	}

	public function actionQrcodeDel()
	{
		$this->admin_priv('qrcode');
		$id = I('get.id', 0, 'intval');

		if (empty($id)) {
			$this->message(L('select_please') . L('qrcode'), null, 2);
		}

		$this->model->table('wechat_qrcode')->where(array('id' => $id))->delete();
		$url = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('qrcode_list'));
		$this->message(L('qrcode') . L('drop') . L('success'), $url);
	}

	public function actionQrcodeGet()
	{
		$id = I('get.id', 0, 'intval');

		if (empty($id)) {
			exit(json_encode(array('status' => 0, 'msg' => L('select_please') . L('qrcode'))));
		}

		$rs = $this->model->table('wechat_qrcode')->field('type, scene_id, username, expire_seconds, qrcode_url, status')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->find();

		if (empty($rs['status'])) {
			exit(json_encode(array('status' => 0, 'msg' => L('qrcode_isdisabled'))));
		}

		if (empty($rs['qrcode_url'])) {
			if (($rs['type'] == 1) && !empty($rs['username'])) {
				$scene_id = 'u=' . $rs['scene_id'];
				$ticket = $this->weObj->getQRCode($scene_id, 2, $rs['expire_seconds']);
			}
			else {
				$ticket = $this->weObj->getQRCode((int) $rs['scene_id'], $rs['type'], $rs['expire_seconds']);
			}

			if (empty($ticket)) {
				exit(json_encode(array('status' => 0, 'msg' => L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg)));
			}

			$data['ticket'] = $ticket['ticket'];
			$data['expire_seconds'] = $ticket['expire_seconds'];
			$data['endtime'] = gmtime() + $ticket['expire_seconds'];
			$qrcode_url = $this->weObj->getQRUrl($ticket['ticket']);
			$data['qrcode_url'] = $qrcode_url;
			$this->model->table('wechat_qrcode')->data($data)->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->save();
		}
		else {
			$qrcode_url = $rs['qrcode_url'];
		}

		$short_url = $this->weObj->getShortUrl($qrcode_url);
		$this->assign('short_url', $short_url);
		$this->assign('qrcode_url', $qrcode_url);
		$this->display();
	}

	public function actionArticle()
	{
		$this->admin_priv('media');
		$offset = $this->pageLimit(url('article'), $this->page_num);
		$where['wechat_id'] = $this->wechat_id;
		$where['type'] = 'news';
		$total = $this->model->table('wechat_media')->where($where)->count();
		$list = $this->model->table('wechat_media')->field('id, title, file, digest, content, add_time, sort, article_id')->where($where)->order('sort DESC, add_time DESC')->limit($offset)->select();

		foreach ((array) $list as $key => $val) {
			if (!empty($val['article_id'])) {
				$id = explode(',', $val['article_id']);

				foreach ($id as $k => $v) {
					$list[$key]['articles'][] = $this->model->table('wechat_media')->field('id, title, file, add_time')->where(array('id' => $v, 'wechat_id' => $this->wechat_id))->find();
					$list[$key]['articles'][$k]['file'] = get_wechat_image_path($list[$key]['articles'][$k]['file']);
				}
			}

			if (!strstr($val['file'], 'public/assets/wechat/')) {
				$list[$key]['file'] = get_wechat_image_path($val['file']);
			}
			else {
				$list[$key]['is_prize'] = 1;
			}

			$list[$key]['content'] = empty($val['digest']) ? sub_str(strip_tags(html_out($val['content'])), 50) : $val['digest'];
		}

		$this->assign('page', $this->pageShow($total));
		$this->assign('list', $list);
		$this->display();
	}

	public function actionArticleEdit()
	{
		$this->admin_priv('media');

		if (IS_POST) {
			$id = I('post.id');
			$data = I('post.data');
			$data['content'] = I('post.content', '', 'new_html_in');
			$pic_path = I('post.file_path');
			$form = new \App\Extensions\Form();

			if (!$form->isEmpty($data['title'], 1)) {
				$this->message(L('title') . L('empty'), null, 2);
			}

			if (!$form->isEmpty($data['content'], 1)) {
				$this->message(L('content') . L('empty'), null, 2);
			}

			$pic_path = edit_upload_image($pic_path);
			$cover = $_FILES['pic'];

			if ($cover['name']) {
				$type = array('image/jpeg', 'image/png');

				if (!in_array($_FILES['pic']['type'], $type)) {
					$this->message(L('not_file_type'), null, 2);
				}

				$result = $this->upload('data/attached/article', true);

				if (0 < $result['error']) {
					$this->message($result['message'], null, 2);
				}

				$data['file'] = $result['url'];
				$data['file_name'] = $cover['name'];
				$data['size'] = $cover['size'];
			}
			else {
				$data['file'] = $pic_path;
			}

			if (!$form->isEmpty($data['file'], 1)) {
				$this->message(L('please_upload'), null, 2);
			}

			$data['wechat_id'] = $this->wechat_id;
			$data['type'] = 'news';

			if (strpos($data['file'], 'no_image') !== false) {
				unset($data['file']);
			}

			if (empty($data['digest'])) {
				$data['digest'] = sub_str(strip_tags(html_out($data['content'])), 50);
			}

			if (!empty($id)) {
				if ($data['file'] && ($pic_path != $data['file'])) {
					$pic_path = (strpos($pic_path, 'no_image') == false ? $pic_path : '');
					$this->remove($pic_path);
				}

				if (!strstr($data['link'], __HOST__) && strstr($data['link'], 'mobile')) {
					$http_prefix = strstr($data['link'], 'mobile', true);

					if ($http !== __HOST__) {
						$http_suffix = strstr($data['link'], 'mobile');
						$data['link'] = __HOST__ . '/' . $http_suffix;
					}
				}

				$data['edit_time'] = gmtime();
				$this->model->table('wechat_media')->data($data)->where(array('id' => $id))->save();
			}
			else {
				$data['add_time'] = gmtime();
				$this->model->table('wechat_media')->data($data)->add();
			}

			$this->message(L('wechat_editor') . L('success'), url('article'));
		}

		$id = I('get.id');

		if (!empty($id)) {
			$article = $this->model->table('wechat_media')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->find();

			if (strstr($article['file'], 'app/modules/')) {
				$replace_str = 'app/modules/';
				$article['file'] = str_replace($replace_str, 'public/assets/', $article['file']);
				$article['file'] = str_replace('views/', '', $article['file']);
			}

			$article['file'] = get_wechat_image_path($article['file']);
			$this->assign('article', $article);
		}

		$this->display();
	}

	public function actionArticleEditNews()
	{
		$this->admin_priv('media');

		if (IS_POST) {
			$id = I('post.id');
			$article_id = I('post.article');
			$data['sort'] = I('post.sort');

			if (is_array($article_id)) {
				$data['article_id'] = implode(',', $article_id);
				$data['wechat_id'] = $this->wechat_id;
				$data['type'] = 'news';

				if (!empty($id)) {
					$data['edit_time'] = gmtime();
					$this->model->table('wechat_media')->data($data)->where(array('id' => $id))->save();
				}
				else {
					$data['add_time'] = gmtime();
					$this->model->table('wechat_media')->data($data)->add();
				}

				$this->redirect('article');
			}
			else {
				$this->message(L('please_add_again'), null, 2);
			}
		}

		$id = I('get.id');

		if (!empty($id)) {
			$rs = $this->model->table('wechat_media')->field('article_id, sort')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->find();

			if (!empty($rs['article_id'])) {
				$articles = array();
				$art = explode(',', $rs['article_id']);

				foreach ($art as $key => $val) {
					$articles[] = $this->model->table('wechat_media')->field('id, title, file, add_time')->where(array('id' => $val))->find();
					$articles[$key]['file'] = get_wechat_image_path($articles[$key]['file']);
				}

				$this->assign('articles', $articles);
			}

			$this->assign('sort', $rs['sort']);
		}

		$this->assign('id', $id);
		$this->display();
	}

	public function actionArticlesList()
	{
		$this->page_num = isset($_COOKIE['ECSCP']['page_size']) && !empty($_COOKIE['ECSCP']['page_size']) ? $_COOKIE['ECSCP']['page_size'] : 4;
		$offset = $this->pageLimit(url('articles_list'), $this->page_num);
		$total = $this->model->query('SELECT count(*) as count  FROM {pre}wechat_media WHERE wechat_id =  ' . $this->wechat_id . '  and type = \'news\' and article_id is NULL or article_id = \'\' ');
		$article = $this->model->query('SELECT id, title, file, digest, content, add_time FROM {pre}wechat_media WHERE wechat_id =  ' . $this->wechat_id . '  and type = \'news\' and article_id is NULL or article_id = \'\' ORDER BY sort DESC, add_time DESC limit ' . $offset);

		if (!empty($article)) {
			foreach ($article as $k => $v) {
				$article[$k]['file'] = get_wechat_image_path($v['file']);
				$article[$k]['content'] = empty($v['digest']) ? sub_str(strip_tags(html_out($v['content'])), 50) : $v['digest'];
			}
		}

		$this->assign('page', $this->pageShow($total[0]['count']));
		$this->assign('page_num', $this->page_num);
		$this->assign('article', $article);
		$this->display();
	}

	public function actionArticleNewsDel()
	{
		$id = I('get.id');

		if (!empty($id)) {
			$this->model->table('wechat_media')->data(array('article_id' => 0))->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->save();
		}

		$this->redirect('article_edit_news');
	}

	public function actionArticleDel()
	{
		$this->admin_priv('media');
		$id = I('get.id');

		if (empty($id)) {
			$this->message(L('select_please') . L('article'), null, 2);
		}

		$pic = $this->model->table('wechat_media')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->getField('file');
		$this->model->table('wechat_media')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->delete();
		$this->remove($pic);
		$this->redirect('article');
	}

	public function actionPicture()
	{
		$this->admin_priv('media');

		if (IS_POST) {
			if ($_FILES['pic']['name']) {
				$type = array('image/jpeg', 'image/png');

				if (!in_array($_FILES['pic']['type'], $type)) {
					$this->message(L('not_file_type'), url('picture'), 2);
				}

				$size = round($_FILES['pic']['size'] / (1024 * 1024), 1);

				if (1 < $size) {
					$this->message(L('file_size_limit'), null, 2);
				}

				$result = $this->upload('data/attached/article', true, 1);

				if (0 < $result['error']) {
					$this->message($result['message'], url('picture'), 2);
				}

				$data['file'] = $result['url'];
				$data['file_name'] = $_FILES['pic']['name'];
				$data['size'] = $_FILES['pic']['size'];
				$data['type'] = 'image';
				$data['add_time'] = gmtime();
				$data['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_media')->data($data)->add();
				$this->redirect('picture');
			}
		}

		$offset = $this->pageLimit(url('picture'), $this->page_num);
		$total = $this->model->query('SELECT count(*) as count FROM {pre}wechat_media WHERE wechat_id = ' . $this->wechat_id . ' and file is NOT NULL and (type = \'image\')');
		$list = $this->model->query('SELECT id, file, file_name, thumb, size FROM {pre}wechat_media WHERE wechat_id = ' . $this->wechat_id . ' and file is NOT NULL and (type = \'image\') order by sort DESC, add_time DESC limit ' . $offset);

		if (empty($list)) {
			$list = array();
		}

		foreach ($list as $key => $val) {
			if ((1024 * 1024) < $val['size']) {
				$list[$key]['size'] = round($val['size'] / (1024 * 1024), 1) . 'MB';
			}
			else {
				$list[$key]['size'] = round($val['size'] / 1024, 1) . 'KB';
			}

			if (!strstr($val['file'], 'public/assets/wechat/')) {
				$list[$key]['file'] = get_wechat_image_path($val['file']);
			}
			else {
				$list[$key]['is_prize'] = 1;
			}
		}

		$this->assign('page', $this->pageShow($total[0]['count']));
		$this->assign('list', $list);
		$this->display();
	}

	public function actionVoice()
	{
		$this->admin_priv('media');

		if (IS_POST) {
			if ($_FILES['voice']['name']) {
				$type = array('audio/amr', 'audio/x-mpeg');

				if (!in_array($_FILES['voice']['type'], $type)) {
					$this->message(L('not_file_type'), url('voice'), 2);
				}

				$result = $this->upload('data/attached/voice', true);

				if (0 < $result['error']) {
					$this->message($result['message'], url('voice'), 2);
				}

				$data['file'] = $result['url'];
				$data['file_name'] = $_FILES['voice']['name'];
				$data['size'] = $_FILES['voice']['size'];
				$data['type'] = 'voice';
				$data['add_time'] = gmtime();
				$data['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_media')->data($data)->add();
				$this->redirect('voice');
			}
		}

		$offset = $this->pageLimit(url('voice'), $this->page_num);
		$total = $this->model->table('wechat_media')->where(array('wechat_id' => $this->wechat_id, 'type' => 'voice'))->count();
		$list = $this->model->table('wechat_media')->field('id, file, file_name, size')->where(array('wechat_id' => $this->wechat_id, 'type' => 'voice'))->order('add_time desc, sort asc')->limit($offset)->select();

		if (empty($list)) {
			$list = array();
		}

		foreach ($list as $key => $val) {
			if ((1024 * 1024) < $val['size']) {
				$list[$key]['size'] = round($val['size'] / (1024 * 1024), 1) . 'MB';
			}
			else {
				$list[$key]['size'] = round($val['size'] / 1024, 1) . 'KB';
			}
		}

		$this->assign('page', $this->pageShow($total));
		$this->assign('list', $list);
		$this->display();
	}

	public function actionVideo()
	{
		$this->admin_priv('media');
		$offset = $this->pageLimit(url('video'), $this->page_num);
		$total = $this->model->table('wechat_media')->where(array('wechat_id' => $this->wechat_id, 'type' => 'video'))->count();
		$list = $this->model->table('wechat_media')->field('id, file, file_name, size')->where(array('wechat_id' => $this->wechat_id, 'type' => 'video'))->order('add_time desc, sort asc')->limit($offset)->select();

		if (empty($list)) {
			$list = array();
		}

		foreach ($list as $key => $val) {
			if ((1024 * 1024) < $val['size']) {
				$list[$key]['size'] = round($val['size'] / (1024 * 1024), 1) . 'MB';
			}
			else {
				$list[$key]['size'] = round($val['size'] / 1024, 1) . 'KB';
			}
		}

		$this->assign('page', $this->pageShow($total));
		$this->assign('list', $list);
		$this->display();
	}

	public function actionVideoEdit()
	{
		if (IS_POST) {
			$data = I('post.data');
			$id = I('post.id');
			if (empty($data['file']) || empty($data['file_name']) || empty($data['size'])) {
				$this->message(L('video_empty'), null, 2);
			}

			$size = round($data['size'] / (1024 * 1024), 1);

			if (2 < $size) {
				$this->message(L('file_size_limit'), null, 2);
			}

			if (empty($data['title'])) {
				$this->message(L('title') . L('empty'), null, 2);
			}

			$data['type'] = 'video';
			$data['wechat_id'] = $this->wechat_id;

			if (!empty($id)) {
				$data['edit_time'] = gmtime();
				$this->model->table('wechat_media')->data($data)->where(array('id' => $id))->save();
			}
			else {
				$data['add_time'] = gmtime();
				$this->model->table('wechat_media')->data($data)->add();
			}

			$this->message(L('upload_video') . L('success'), url('video'));
		}

		$id = I('get.id');

		if (!empty($id)) {
			$video = $this->model->table('wechat_media')->field('id, file, file_name, size, title, content')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->find();
			$this->assign('video', $video);
		}

		$this->display();
	}

	public function actionVideoUpload()
	{
		if (IS_POST && !empty($_FILES['file']['name'])) {
			$vid = I('post.vid');

			if (!empty($vid)) {
				$file = $this->model->table('wechat_media')->where(array('id' => $vid, 'wechat_id' => $this->wechat_id))->getField('file');
				$this->remove($file);
			}

			$result = $this->upload('data/attached/video', true, 2);

			if (0 < $result['error']) {
				$data['errcode'] = 1;
				$data['errmsg'] = $result['message'];
				echo json_encode($data);
				exit();
			}

			$data['errcode'] = 0;
			$data['file'] = $result['url'];
			$data['file_name'] = $_FILES['file']['name'];
			$data['size'] = $_FILES['file']['size'];
			echo json_encode($data);
			exit();
		}
	}

	public function actionMediaEdit()
	{
		$this->admin_priv('media');

		if (IS_POST) {
			$id = I('post.id');
			$pic_name = I('post.file_name');
			$form = new \App\Extensions\Form();

			if (!$form->isEmpty($id, 1)) {
				$this->message(L('empty'), null, 2);
			}

			if (!$form->isEmpty($pic_name, 1)) {
				$this->message(L('empty'), null, 2);
			}

			$data['file_name'] = $pic_name;
			$data['edit_time'] = gmtime();
			$num = $this->model->table('wechat_media')->data($data)->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->save();
			exit(json_encode(array('status' => $num)));
		}

		$id = I('get.id');
		$pic = $this->model->table('wechat_media')->field('id, file_name')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->find();

		if (empty($pic)) {
			redirect($_SERVER['HTTP_REFERER']);
		}

		$this->assign('pic', $pic);
		$this->display();
	}

	public function actionMediaDel()
	{
		$this->admin_priv('media');
		$id = I('get.id');

		if (empty($id)) {
			$this->message(L('empty'), null, 2);
		}

		$pic = $this->model->table('wechat_media')->field('file, thumb')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->find();

		if (!empty($pic)) {
			$this->model->table('wechat_media')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->delete();
		}

		$this->remove($pic['file']);
		$this->remove($pic['thumb']);
		redirect($_SERVER['HTTP_REFERER']);
	}

	public function actionDownload()
	{
		$id = I('get.id');
		$pic = $this->model->table('wechat_media')->field('file, file_name')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->find();
		$filename = dirname(ROOT_PATH) . '/' . $pic['file'];

		if (file_exists($filename)) {
			\App\Extensions\Http::download($filename, $pic['file_name']);
		}
		else {
			$this->message(L('file_not_exist'), null, 2);
		}
	}

	public function actionMassList()
	{
		$this->admin_priv('mass_message');
		$offset = $this->pageLimit(url('mass_list'), $this->page_num);
		$total = $this->model->table('wechat_mass_history')->where(array('wechat_id' => $this->wechat_id))->count();
		$this->assign('page', $this->pageShow($total));
		$list = $this->model->table('wechat_mass_history')->field('id, media_id, type, status, send_time, totalcount, sentcount, filtercount, errorcount')->where(array('wechat_id' => $this->wechat_id))->order('send_time desc')->limit($offset)->select();

		foreach ((array) $list as $key => $val) {
			$media = $this->model->table('wechat_media')->field('title, digest, content, file, article_id')->where(array('id' => $val['media_id']))->find();

			if (!empty($media['article_id'])) {
				$artids = explode(',', $media['article_id']);
				$artinfo = $this->model->table('wechat_media')->field('title, digest, content, file')->where(array('id' => $artids[0]))->find();
			}
			else {
				$artinfo = $media;
			}

			if ('news' == $val['type']) {
				$artinfo['type'] = '图文消息';
			}

			$artinfo['file'] = get_wechat_image_path($artinfo['file']);
			$artinfo['content'] = empty($artinfo['digest']) ? sub_str(strip_tags(html_out($artinfo['content'])), 50) : $artinfo['digest'];
			$list[$key]['artinfo'] = $artinfo;
		}

		$this->assign('list', $list);
		$this->display();
	}

	public function actionMassMessage()
	{
		$this->admin_priv('mass_message');

		if (IS_POST) {
			$tag_id = I('post.tag_id', 0, 'intval');
			$media_id = I('post.media_id');
			if (empty($tag_id) || ($tag_id == 0) || empty($media_id)) {
				$this->message(L('please_select'), null, 2);
			}

			$article = array();
			$article_info = $this->model->table('wechat_media')->field('id, title, command, author, file, is_show, digest, content, link, type, article_id')->where(array('id' => $media_id, 'wechat_id' => $this->wechat_id))->find();

			if (!empty($article_info['article_id'])) {
				$articles = explode(',', $article_info['article_id']);

				foreach ($articles as $key => $val) {
					$artinfo = $this->model->table('wechat_media')->field('title, command, author, file, is_show, digest, content, link')->where(array('id' => $val, 'wechat_id' => $this->wechat_id))->find();
					$filename = (!empty($artinfo['command']) ? dirname(ROOT_PATH) . '/mobile/' . $artinfo['file'] : dirname(ROOT_PATH) . '/' . $artinfo['file']);
					$rs = $this->weObj->uploadMedia(array('media' => realpath_wechat($filename)), 'image');

					if (empty($rs)) {
						$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, null, 2);
					}

					$article[$key]['thumb_media_id'] = $rs['media_id'];
					$article[$key]['author'] = $artinfo['author'];
					$article[$key]['title'] = $artinfo['title'];
					$article[$key]['content_source_url'] = $artinfo['link'];
					$article[$key]['content'] = html_out($artinfo['content']);
					$article[$key]['digest'] = $artinfo['digest'];
					$article[$key]['show_cover_pic'] = $artinfo['is_show'];
				}
			}
			else {
				$filename = (!empty($article_info['command']) ? dirname(ROOT_PATH) . '/mobile/' . $article_info['file'] : dirname(ROOT_PATH) . '/' . $article_info['file']);
				$rs = $this->weObj->uploadMedia(array('media' => realpath_wechat($filename)), 'image');

				if (empty($rs)) {
					$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, null, 2);
				}

				$article[0]['thumb_media_id'] = $rs['media_id'];
				$article[0]['author'] = $article_info['author'];
				$article[0]['title'] = $article_info['title'];
				$article[0]['content_source_url'] = $article_info['link'];
				$article[0]['content'] = html_out($article_info['content']);
				$article[0]['digest'] = $article_info['digest'];
				$article[0]['show_cover_pic'] = $article_info['is_show'];
			}

			$article_list = array('articles' => $article);
			$rs1 = $this->weObj->uploadArticles($article_list);

			if (empty($rs1)) {
				$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, null, 2);
			}

			$mass_id = dao('wechat_mass_history')->where(array('wechat_id' => $this->wechat_id))->order('id DESC')->getField('id');
			$clientmsgid = (!empty($mass_id) ? $mass_id + 1 : 0);
			$massmsg = array(
				'filter'              => array('is_to_all' => false, 'tag_id' => $tag_id),
				'mpnews'              => array('media_id' => $rs1['media_id']),
				'msgtype'             => 'mpnews',
				'send_ignore_reprint' => 0,
				'clientmsgid'         => $clientmsgid
				);
			$rs2 = $this->weObj->sendGroupMassMessage($massmsg);

			if (empty($rs2)) {
				$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, null, 2);
			}

			$msg_data['wechat_id'] = $this->wechat_id;
			$msg_data['media_id'] = $article_info['id'];
			$msg_data['type'] = $article_info['type'];
			$msg_data['send_time'] = gmtime();
			$msg_data['msg_id'] = $rs2['msg_id'];
			$id = $this->model->table('wechat_mass_history')->data($msg_data)->add();
			$this->message(L('mass_sending_wait'), url('mass_message'));
		}

		$tags = $this->model->table('wechat_user_taglist')->field('tag_id, name')->where(array('wechat_id' => $this->wechat_id))->order('tag_id')->select();
		$article = $this->model->table('wechat_media')->field('id, title, file, content, article_id, add_time')->where(array('wechat_id' => $this->wechat_id, 'type' => 'news'))->order('sort asc, add_time desc')->select();

		foreach ((array) $article as $key => $val) {
			if (!empty($val['article_id'])) {
				$id = explode(',', $val['article_id']);

				foreach ($id as $k => $v) {
					$article[$key]['articles'][] = $this->model->table('wechat_media')->field('id, title, file, add_time')->where(array('id' => $v))->find();
					$article[$key]['articles'][$k]['file'] = get_wechat_image_path($article[$key]['articles'][$k]['file']);
				}
			}

			if (empty($val['article_id'])) {
				$article[$key]['file'] = get_wechat_image_path($val['file']);
			}

			$article[$key]['content'] = sub_str(strip_tags(html_out($val['content'])), 100);
		}

		$this->assign('tags', $tags);
		$this->assign('article', $article);
		$this->display();
	}

	public function actionMassDel()
	{
		$this->admin_priv('mass_message');
		$id = I('get.id');
		$msg_id = $this->model->table('wechat_mass_history')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->getField('msg_id');

		if (empty($msg_id)) {
			$this->message(L('massage_not_exist'), null, 2);
		}

		$delmass = array('msg_id' => $msg_id, 'article_idx' => 0);
		$rs = $this->weObj->deleteMassMessage($delmass);

		if (empty($rs)) {
			$this->message(L('errcode') . $this->weObj->errCode . L('errmsg') . $this->weObj->errMsg, null, 2);
		}

		$data['status'] = 'send success(已删除)';
		$this->model->table('wechat_mass_history')->data($data)->where(array('id' => $id))->save();
		$this->redirect('mass_list');
	}

	public function actionGetArticle()
	{
		if (IS_AJAX) {
			$data = I('post.article');
			$article = array();

			if (is_array($data)) {
				$id = implode(',', $data);
				$article = $this->model->query('SELECT id, title, file, link, digest, content, add_time FROM {pre}wechat_media WHERE id in (' . $id . ') AND wechat_id = ' . $this->wechat_id . ' ORDER BY sort DESC, add_time DESC');

				foreach ($article as $key => $val) {
					$article[$key]['file'] = get_wechat_image_path($val['file']);
					$article[$key]['add_time'] = date('Y年m月d日', $val['add_time']);
					$article[$key]['content'] = empty($val['digest']) ? sub_str(strip_tags(html_out($val['content'])), 50) : $val['digest'];
				}
			}

			echo json_encode($article);
		}
	}

	public function actionAutoReply()
	{
		$this->admin_priv('auto_reply');
		$type = I('get.type');

		if (!empty($type)) {
			$filter['type'] = $type;
			$offset = $this->pageLimit(url('auto_reply', $filter), $this->page_num);

			if ('image' == $type) {
				$where = 'wechat_id = ' . $this->wechat_id . ' and file is NOT NULL and type = "image"';
				$list = $this->model->query('SELECT id, file, file_name, size, add_time, type FROM {pre}wechat_media WHERE ' . $where . ' ORDER BY sort DESC, add_time DESC limit ' . $offset);
			}
			else if ('voice' == $type) {
				$where = 'wechat_id = ' . $this->wechat_id . ' and file is NOT NULL and type = "voice"';
				$list = $this->model->query('SELECT id, file, file_name, size, add_time, type FROM {pre}wechat_media WHERE ' . $where . ' ORDER BY sort DESC, add_time DESC limit ' . $offset);
			}
			else if ('video' == $type) {
				$where = 'wechat_id = ' . $this->wechat_id . ' and file is NOT NULL and type = "video"';
				$list = $this->model->query('SELECT id, file, file_name, size, add_time, type FROM {pre}wechat_media WHERE ' . $where . ' ORDER BY sort DESC, add_time DESC limit ' . $offset);
			}
			else if ('news' == $type) {
				$no_list = I('get.no_list', 0, 'intval');
				$this->assign('no_list', $no_list);

				if (!empty($no_list)) {
					$where = 'wechat_id = ' . $this->wechat_id . ' and type=\'news\' and article_id is NULL or article_id = \'\' ';
				}
				else {
					$where = 'wechat_id = ' . $this->wechat_id . ' and type=\'news\' ';
				}

				$list = $this->model->query('SELECT id, title, file, file_name, size, digest, content, add_time, type, article_id FROM {pre}wechat_media WHERE ' . $where . ' ORDER BY sort DESC, add_time DESC limit ' . $offset);

				foreach ((array) $list as $key => $val) {
					if (!empty($val['article_id'])) {
						$id = explode(',', $val['article_id']);

						foreach ($id as $k => $v) {
							$list[$key]['articles'][] = $this->model->table('wechat_media')->field('id, title, digest, file, add_time')->where(array('id' => $v))->find();
							$list[$key]['articles'][$k]['file'] = get_wechat_image_path($list[$key]['articles'][$k]['file']);
						}
					}

					$list[$key]['content'] = empty($val['digest']) ? sub_str(strip_tags(html_out($val['content'])), 50) : $val['digest'];
				}
			}

			foreach ((array) $list as $key => $val) {
				if ((1024 * 1024) < $val['size']) {
					$list[$key]['size'] = round($val['size'] / (1024 * 1024), 1) . 'MB';
				}
				else {
					$list[$key]['size'] = round($val['size'] / 1024, 1) . 'KB';
				}

				if (empty($val['article_id'])) {
					$list[$key]['file'] = get_wechat_image_path($val['file']);
				}
			}

			$total = $this->model->query('SELECT count(*) as count FROM {pre}wechat_media WHERE ' . $where . ' ');

			foreach ($total as $key => $value) {
				$num = $value['count'];
			}

			$this->assign('page', $this->pageShow($num));
			$this->assign('list', $list);
			$this->assign('type', $type);
			$this->display();
		}
	}

	public function actionReplySubscribe()
	{
		$this->admin_priv('auto_reply');

		if (IS_POST) {
			$content_type = I('post.content_type');

			if ($content_type == 'text') {
				$data['content'] = I('post.content', '', 'new_html_in');
				$data['media_id'] = 0;
			}
			else {
				$data['media_id'] = I('post.media_id');
				$data['content'] = '';
			}

			$data['type'] = 'subscribe';
			if (is_array($data) && (!empty($data['media_id']) || !empty($data['content']))) {
				$where['type'] = $data['type'];
				$where['wechat_id'] = $this->wechat_id;
				$id = $this->model->table('wechat_reply')->where($where)->getField('id');

				if (!empty($id)) {
					$this->model->table('wechat_reply')->data($data)->where($where)->save();
				}
				else {
					$data['wechat_id'] = $this->wechat_id;
					$this->model->table('wechat_reply')->data($data)->add();
				}

				$this->message(L('wechat_editor') . L('success'), url('reply_subscribe'));
			}
			else {
				$this->message(L('empty'), null, 2);
			}
		}

		$subscribe = $this->model->table('wechat_reply')->where(array('type' => 'subscribe', 'wechat_id' => $this->wechat_id))->find();

		if (!empty($subscribe['media_id'])) {
			$subscribe['media'] = $this->model->table('wechat_media')->field('file, type, file_name')->where(array('id' => $subscribe['media_id'], 'wechat_id' => $this->wechat_id))->find();
			$subscribe['media']['file'] = get_wechat_image_path($subscribe['media']['file']);
		}

		$this->assign('subscribe', $subscribe);
		$this->display();
	}

	public function actionReplyMsg()
	{
		$this->admin_priv('auto_reply');

		if (IS_POST) {
			$content_type = I('post.content_type');

			if ($content_type == 'text') {
				$data['content'] = I('post.content', '', 'new_html_in');
				$data['media_id'] = 0;
			}
			else {
				$data['media_id'] = I('post.media_id');
				$data['content'] = '';
			}

			$data['type'] = 'msg';

			if (is_array($data)) {
				$where['type'] = $data['type'];
				$where['wechat_id'] = $this->wechat_id;
				$id = $this->model->table('wechat_reply')->where($where)->getField('id');

				if (!empty($id)) {
					$this->model->table('wechat_reply')->data($data)->where($where)->save();
				}
				else {
					$data['wechat_id'] = $this->wechat_id;
					$this->model->table('wechat_reply')->data($data)->add();
				}

				$this->message(L('wechat_editor') . L('success'), url('reply_msg'));
			}
			else {
				$this->message(L('empty'), null, 2);
			}
		}

		$msg = $this->model->table('wechat_reply')->where(array('type' => 'msg', 'wechat_id' => $this->wechat_id))->find();

		if (!empty($msg['media_id'])) {
			$msg['media'] = $this->model->table('wechat_media')->field('file, type, file_name')->where(array('id' => $msg['media_id']))->find();
			$msg['media']['file'] = get_wechat_image_path($msg['media']['file']);
		}

		$this->assign('msg', $msg);
		$this->display();
	}

	public function actionReplyKeywords()
	{
		$this->admin_priv('auto_reply');
		$list = $this->model->table('wechat_reply')->field('id, rule_name, content, media_id, reply_type')->where(array('type' => 'keywords', 'wechat_id' => $this->wechat_id))->order('add_time desc')->select();

		foreach ((array) $list as $key => $val) {
			if (!empty($val['media_id'])) {
				$media = $this->model->table('wechat_media')->field('title, file, file_name, type, digest, content, add_time, article_id')->where(array('id' => $val['media_id'], 'wechat_id' => $this->wechat_id))->find();
				$media['file'] = get_wechat_image_path($media['file']);
				$media['content'] = empty($media['digest']) ? sub_str(strip_tags(html_out($media['content'])), 50) : $media['digest'];

				if (!empty($media['article_id'])) {
					$artids = explode(',', $media['article_id']);

					foreach ($artids as $k => $v) {
						$list[$key]['medias'][] = $this->model->table('wechat_media')->field('title, file, add_time')->where(array('id' => $v, 'wechat_id' => $this->wechat_id))->find();
						$list[$key]['medias'][$k]['file'] = get_wechat_image_path($list[$key]['medias'][$k]['file']);
					}
				}
				else {
					$list[$key]['media'] = $media;
				}
			}

			$keywords = $this->model->table('wechat_rule_keywords')->field('rule_keywords')->where(array('rid' => $val['id'], 'wechat_id' => $this->wechat_id))->order('id desc')->select();
			$list[$key]['rule_keywords'] = $keywords;

			if (!empty($keywords)) {
				$rule_keywords = array();

				foreach ($keywords as $k => $v) {
					$rule_keywords[] = $v['rule_keywords'];
				}

				$rule_keywords = implode(',', $rule_keywords);
				$list[$key]['rule_keywords_string'] = $rule_keywords;
			}
		}

		$this->assign('list', $list);
		$this->display();
	}

	public function actionRuleEdit()
	{
		if (IS_POST) {
			$id = I('post.id');
			$content_type = I('post.content_type');
			$rule_keywords = I('post.rule_keywords');
			$data['rule_name'] = I('post.rule_name', '', 'trim');
			$data['media_id'] = I('post.media_id', 0, 'intval');
			$data['content'] = I('post.content', '', 'new_html_in');
			$data['reply_type'] = $content_type;

			if ($content_type == 'text') {
				$data['media_id'] = 0;
			}
			else {
				$data['content'] = '';
			}

			$form = new \App\Extensions\Form();

			if (!$form->isEmpty($data['rule_name'], 1)) {
				$this->message(L('rule_name_empty'), null, 2);
			}

			if (!$form->isEmpty($rule_keywords, 1)) {
				$this->message(L('rule_keywords_empty'), null, 2);
			}

			if (empty($data['content']) && empty($data['media_id'])) {
				$this->message(L('rule_content_empty'), null, 2);
			}

			if (60 < strlen($data['rule_name'])) {
				$this->message(L('rule_name_length_limit'), null, 2);
			}

			$rule_keywords = explode(',', $rule_keywords);

			foreach ($rule_keywords as $val) {
				$where = array();

				if (!empty($id)) {
					$where['rid'] = array('neq', $id);
					$where['rule_keywords'] = $val;
					$where['wechat_id'] = $this->wechat_id;
				}
				else {
					$where['rule_keywords'] = $val;
					$where['wechat_id'] = $this->wechat_id;
				}

				$count = dao('wechat_rule_keywords')->where($where)->count();

				if (1 <= $count) {
					$this->message('关键词' . $val . '已经存在了，请更换一个！', null, 2);
				}
			}

			$data['type'] = 'keywords';

			if (!empty($id)) {
				$this->model->table('wechat_reply')->data($data)->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->save();
				$this->model->table('wechat_rule_keywords')->where(array('rid' => $id, 'wechat_id' => $this->wechat_id))->delete();
			}
			else {
				$data['add_time'] = gmtime();
				$data['wechat_id'] = $this->wechat_id;
				$id = $this->model->table('wechat_reply')->data($data)->add();
			}

			foreach ($rule_keywords as $val) {
				$kdata['rid'] = $id;
				$kdata['rule_keywords'] = $val;
				$kdata['wechat_id'] = $this->wechat_id;
				$this->model->table('wechat_rule_keywords')->data($kdata)->add();
			}

			$this->message(L('wechat_editor') . L('success'), url('reply_keywords'));
		}
	}

	public function actionReplyDel()
	{
		$this->admin_priv('auto_reply');
		$id = I('get.id');

		if (empty($id)) {
			$this->message(L('empty'), null, 2);
		}

		$this->model->table('wechat_reply')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->delete();
		$this->model->table('wechat_rule_keywords')->where(array('rid' => $id, 'wechat_id' => $this->wechat_id))->delete();
		$this->redirect('reply_keywords');
	}

	public function actionMediaList()
	{
		$this->display();
	}

	public function actionRemind()
	{
		if (IS_POST) {
			$command = I('post.command');
			$data = I('post.data');
			$config = I('post.config');
			$info = Check::rule(array(Check::must($command), '关键词不正确'));

			if ($info !== true) {
				$this->message($info, null, 2);
			}

			if (!empty($config)) {
				$data['config'] = serialize($config);
			}

			$data['wechat_id'] = $this->wechat_id;
			$num = $this->model->table('wechat_extend')->where('command = "' . $command . '" and wechat_id = ' . $this->wechat_id)->count();

			if (0 < $num) {
				$this->model->table('wechat_extend')->data($data)->where('command = "' . $command . '" and wechat_id = ' . $this->wechat_id)->save();
			}
			else {
				$data['command'] = $command;
				$this->model->table('wechat_extend')->data($data)->add();
			}

			redirect($_SERVER['HTTP_REFERER']);
		}

		$order_remind = $this->model->table('wechat_extend')->field('name, enable, config')->where('command = "order_remind" and wechat_id = ' . $this->wechat_id)->find();

		if ($order_remind['config']) {
			$order_remind['config'] = unserialize($order_remind['config']);
		}

		$pay_remind = $this->model->table('wechat_extend')->field('name, enable, config')->where('command = "pay_remind" and wechat_id = ' . $this->wechat_id)->find();

		if ($pay_remind['config']) {
			$pay_remind['config'] = unserialize($pay_remind['config']);
		}

		$send_remind = $this->model->table('wechat_extend')->field('name, enable, config')->where('command = "send_remind" and wechat_id = ' . $this->wechat_id)->find();

		if ($send_remind['config']) {
			$send_remind['config'] = unserialize($send_remind['config']);
		}

		$register_remind = $this->model->table('wechat_extend')->field('name, enable, config')->where('command = "register_remind" and wechat_id = ' . $this->wechat_id)->find();

		if ($register_remind['config']) {
			$register_remind['config'] = unserialize($register_remind['config']);
		}

		$this->assign('order_remind', $order_remind);
		$this->assign('pay_remind', $pay_remind);
		$this->assign('send_remind', $send_remind);
		$this->assign('register_remind', $register_remind);
		$this->display();
	}

	public function actionCustomerService()
	{
		$command = 'kefu';

		if (IS_POST) {
			$data = I('post.data');
			$config = I('post.config');

			if (!empty($config)) {
				$data['config'] = serialize($config);
			}

			$num = $this->model->table('wechat_extend')->where(array('command' => $command, 'wechat_id' => $this->wechat_id))->count();

			if (0 < $num) {
				$this->model->table('wechat_extend')->data($data)->where(array('command' => $command, 'wechat_id' => $this->wechat_id))->save();
			}
			else {
				$data['wechat_id'] = $this->wechat_id;
				$data['command'] = $command;
				$data['name'] = '多客服';
				$this->model->table('wechat_extend')->data($data)->add();
			}

			redirect($_SERVER['HTTP_REFERER']);
		}

		$customer_service = $this->model->table('wechat_extend')->field('name, enable, config')->where(array('command' => $command, 'wechat_id' => $this->wechat_id))->find();

		if ($customer_service['config']) {
			$customer_service['config'] = unserialize($customer_service['config']);
		}

		$this->assign('customer_service', $customer_service);
		$this->display();
	}

	public function actionAddKf()
	{
		$account = 'test@gh_1ca465561479';
		$nickname = 'test';
		$password = '123123';
		$rs = $this->weObj->addKFAccount($account, $nickname, $password);
		echo $this->weObj->errMsg;
		dump($rs);
	}

	public function actionTemplate()
	{
		$this->admin_priv('template');
		$condition['wechat_id'] = $this->wechat_id;
		$list = $this->model->table('wechat_template')->where($condition)->order('id asc')->select();

		if ($list) {
			foreach ($list as $key => $val) {
				$list[$key]['add_time'] = local_date('Y-m-d H:i', $val['add_time']);
			}
		}

		$this->assign('list', $list);
		$this->display();
	}

	public function actionEditTemplate()
	{
		if (IS_AJAX) {
			$id = I('post.id');
			$data = I('post.data', '', 'trim');

			if ($id) {
				$condition['id'] = $id;
				$condition['wechat_id'] = $this->wechat_id;
				$data['add_time'] = gmtime();
				$this->model->table('wechat_template')->data($data)->where($condition)->save();
				exit(json_encode(array('status' => 1)));
			}
			else {
				exit(json_encode(array('status' => 0, 'msg' => L('template_edit_fail'))));
			}
		}

		$id = I('get.id', 0, 'intval');

		if ($id) {
			$condition['id'] = $id;
			$condition['wechat_id'] = $this->wechat_id;
			$template = $this->model->table('wechat_template')->where($condition)->find();
			$this->assign('template', $template);
		}

		$this->display();
	}

	public function actionSwitch()
	{
		$id = I('get.id', 0, 'intval');
		$status = I('get.status', 0, 'intval');

		if (empty($id)) {
			$this->message(L('empty'), null, 2);
		}

		$condition['id'] = $id;
		$condition['wechat_id'] = $this->wechat_id;
		$data = array();
		$data['add_time'] = gmtime();

		if ($status == 1) {
			$template = $this->model->table('wechat_template')->field('template_id, code')->where($condition)->find();

			if (empty($template['template_id'])) {
				$template_id = $this->weObj->addTemplateMessage($template['code']);

				if ($template_id) {
					$data['template_id'] = $template_id;
					$this->model->table('wechat_template')->data($data)->where($condition)->save();
				}
				else {
					$this->message($this->weObj->errMsg, null, 2);
				}
			}

			$data['status'] = 1;
			$this->model->table('wechat_template')->data($data)->where($condition)->save();
		}
		else {
			$data['status'] = 0;
			$this->model->table('wechat_template')->data($data)->where($condition)->save();
		}

		$this->redirect('template');
	}

	public function actionShareCount()
	{
		$this->admin_priv('fans');
		$keywords = I('request.keywords', '', 'trim');
		$where = '';

		if (!empty($keywords)) {
			$where .= ' and (u.nickname like "%' . $keywords . '%" )';
		}

		$offset = $this->pageLimit(url('share_count'), $this->page_num);
		$sql = "SELECT count(*) as number FROM {pre}wechat_share_count sh\r\n            LEFT JOIN {pre}wechat_user u ON u.openid = sh.openid\r\n            WHERE sh.wechat_id = " . $this->wechat_id . $where . ' order by sh.share_time desc';
		$total = $this->model->query($sql);
		$sql = "SELECT sh.*, u.nickname FROM {pre}wechat_share_count sh\r\n            LEFT JOIN {pre}wechat_user u ON u.openid = sh.openid\r\n            WHERE sh.wechat_id = " . $this->wechat_id . $where . ' order by sh.share_time desc limit ' . $offset;
		$list = $this->model->query($sql);

		foreach ($list as $key => $val) {
			$list[$key]['share_type'] = get_share_type($val['share_type']);
			$list[$key]['share_time'] = local_date('Y-m-d H:i:s', $val['share_time']);
		}

		$this->assign('page', $this->pageShow($total[0]['number']));
		$this->assign('list', $list);
		$this->display();
	}

	public function actionShareCountDelete()
	{
		$this->admin_priv('fans');
		$id = input('id', 0, 'intval');

		if (empty($id)) {
			$this->message(L('empty'), null, 2);
		}
		else {
			dao('wechat_share_count')->where(array('id' => $id, 'wechat_id' => $this->wechat_id))->delete();
			$this->message(L('drop') . L('success'), url('share_count'));
		}
	}

	private function get_config()
	{
		$without = array('index', 'append', 'modify', 'delete', 'set_default');

		if (!in_array(strtolower(ACTION_NAME), $without)) {
			$where['id'] = $this->wechat_id;
			$wechat = $this->model->table('wechat')->field('token, appid, appsecret, type, status')->where($where)->find();

			if (empty($wechat)) {
				$wechat = array();
			}

			if (empty($wechat['status'])) {
				$this->message(L('open_wechat'), url('modify'), 2);
				exit();
			}

			$config = array();
			$config['token'] = $wechat['token'];
			$config['appid'] = $wechat['appid'];
			$config['appsecret'] = $wechat['appsecret'];
			$this->weObj = new \App\Extensions\Wechat($config);
			$this->assign('type', $wechat['type']);
		}
	}
}

?>
