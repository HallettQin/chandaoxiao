<?php
//cgxlm
namespace App\Http\Wechat\Controllers;

class Index extends \App\Http\Base\Controllers\Frontend
{
	private $weObj = '';
	private $secret_key = '';
	private $wechat_id = 0;
	private $ru_id = 0;

	public function __construct()
	{
		parent::__construct();
		$this->secret_key = I('get.key', '', 'trim');

		if ($this->secret_key) {
			$this->load_helper('passport');
			$wxinfo = $this->get_config($this->secret_key);
			$this->wechat_id = $wxinfo['id'];
			$this->ru_id = $wxinfo['ru_id'];

			if ($this->ru_id) {
				cookie('ectouch_ru_id', $this->ru_id, gmtime() + (3600 * 24));
			}
			else {
				cookie('ectouch_ru_id', '');
			}

			logResult($_COOKIE);
			logResult($_SESSION);
			$config['token'] = $wxinfo['token'];
			$config['appid'] = $wxinfo['appid'];
			$config['appsecret'] = $wxinfo['appsecret'];
			$config['encodingaeskey'] = $wxinfo['encodingaeskey'];
			$this->weObj = new \App\Extensions\Wechat($config);
			$this->weObj->valid();
		}
	}

	public function actionIndex()
	{
		$type = $this->weObj->getRev()->getRevType();
		$wedata = $this->weObj->getRev()->getRevData();
		$keywords = '';
		$event_data = array('subscribe', 'unsubscribe', 'LOCATION', 'VIEW', 'SCAN');

		if (!in_array($wedata['Event'], $event_data)) {
			if (!empty($wedata['EventKey']) || !empty($wedata['Content'])) {
				message_log_alignment_add($wedata, $this->wechat_id);
			}
		}

		switch ($type) {
		case \App\Extensions\Wechat::MSGTYPE_TEXT:
			$keywords = $wedata['Content'];
			break;

		case \App\Extensions\Wechat::MSGTYPE_EVENT:
			if ($wedata['Event'] == \App\Extensions\Wechat::EVENT_SUBSCRIBE) {
				$scene_id = '';
				$flag = false;
				if (isset($wedata['Ticket']) && !empty($wedata['Ticket'])) {
					$scene_id = $this->weObj->getRevSceneId();
					$flag = true;
					$this->subscribe($wedata['FromUserName'], $scene_id);
				}
				else {
					$this->subscribe($wedata['FromUserName']);
				}

				$this->msg_reply('subscribe');
			}
			else if ($wedata['Event'] == \App\Extensions\Wechat::EVENT_UNSUBSCRIBE) {
				$this->unsubscribe($wedata['FromUserName']);
				exit();
			}
			else if ($wedata['Event'] == \App\Extensions\Wechat::EVENT_SCAN) {
				$scene_id = $this->weObj->getRevSceneId();
			}
			else if ($wedata['Event'] == \App\Extensions\Wechat::EVENT_MENU_CLICK) {
				$keywords = $wedata['EventKey'];
			}
			else if ($wedata['Event'] == \App\Extensions\Wechat::EVENT_MENU_VIEW) {
				redirect($wedata['EventKey']);
			}
			else if ($wedata['Event'] == \App\Extensions\Wechat::EVENT_LOCATION) {
				exit();
			}
			else if ($wedata['Event'] == 'kf_create_session') {
			}
			else if ($wedata['Event'] == 'kf_close_session') {
			}
			else if ($wedata['Event'] == 'kf_switch_session') {
			}
			else if ($wedata['Event'] == 'MASSSENDJOBFINISH') {
				$data['status'] = $wedata['Status'];
				$data['totalcount'] = $wedata['TotalCount'];
				$data['filtercount'] = $wedata['FilterCount'];
				$data['sentcount'] = $wedata['SentCount'];
				$data['errorcount'] = $wedata['ErrorCount'];
				dao('wechat_mass_history')->data($data)->where(array('msg_id' => $wedata['MsgID'], 'wechat_id' => $this->wechat_id))->save();
				exit();
			}
			else if ($wedata['Event'] == 'TEMPLATESENDJOBFINISH') {
				if ($wedata['Status'] == 'success') {
					$data = array('status' => 1);
				}
				else if ($wedata['Status'] == 'failed:user block') {
					$data = array('status' => 2);
				}
				else {
					$data = array('status' => 0);
				}

				dao('wechat_template_log')->data($data)->where(array('msgid' => $wedata['MsgID'], 'openid' => $wedata['FromUserName'], 'wechat_id' => $this->wechat_id))->save();
			}

			break;

		case \App\Extensions\Wechat::MSGTYPE_IMAGE:
			exit();
			break;

		case \App\Extensions\Wechat::MSGTYPE_VOICE:
			exit();
			break;

		case \App\Extensions\Wechat::MSGTYPE_VIDEO:
			exit();
			break;

		case \App\Extensions\Wechat::MSGTYPE_SHORTVIDEO:
			exit();
			break;

		case \App\Extensions\Wechat::MSGTYPE_LOCATION:
			exit();
			break;

		case \App\Extensions\Wechat::MSGTYPE_LINK:
			exit();
			break;

		default:
			$this->msg_reply('msg');
			exit();
		}

		if (!empty($scene_id)) {
			$keywords = $this->do_qrcode_subscribe($scene_id, $flag);

			if (!empty($keywords)) {
				$rs1 = $this->get_function($wedata['FromUserName'], $keywords);
				$rs3 = $this->get_marketing($wedata['FromUserName'], $keywords);
				if (empty($rs1) || empty($rs3)) {
					$rs2 = $this->keywords_reply($keywords);

					if (empty($rs2)) {
						$this->msg_reply('msg');
					}
				}
			}
		}

		if ($wedata['MsgType'] == 'event') {
			$where = array('wechat_id' => $this->wechat_id, 'fromusername' => $wedata['FromUserName'], 'createtime' => $wedata['CreateTime'], 'keywords' => $keywords, 'is_send' => 0);
		}
		else {
			$where = array('wechat_id' => $this->wechat_id, 'msgid' => $wedata['MsgId'], 'keywords' => $keywords, 'is_send' => 0);
		}

		$contents = dao('wechat_message_log')->field('fromusername, createtime, keywords, msgid, msgtype')->where($where)->find();
		if (!empty($contents) && !empty($contents['keywords'])) {
			$keyword = html_in($contents['keywords']);
			$fromusername = $contents['fromusername'];
			$rs = $this->customer_service($fromusername, $keyword);

			if (empty($rs)) {
				$rs1 = $this->get_function($fromusername, $keyword);
				$rs3 = $this->get_marketing($fromusername, $keyword);
				if (empty($rs1) || empty($rs3)) {
					$rs2 = $this->keywords_reply($keyword);

					if (empty($rs2)) {
						$this->msg_reply('msg');
					}
				}
			}

			$this->record_msg($fromusername, $keyword);
			message_log_alignment_send($contents, $this->wechat_id);
		}
	}

	private function subscribe($openid = '', $scene_id = '')
	{
		if (!empty($openid)) {
			$info = $this->weObj->getUserInfo($openid);

			if (empty($info)) {
				$this->weObj->resetAuth();
				exit('null');
			}

			$data['wechat_id'] = $this->wechat_id;
			$data['subscribe'] = $info['subscribe'];
			$data['openid'] = $info['openid'];
			$data['nickname'] = $info['nickname'];
			$data['sex'] = $info['sex'];
			$data['language'] = $info['language'];
			$data['city'] = $info['city'];
			$data['province'] = $info['province'];
			$data['country'] = $info['country'];
			$data['headimgurl'] = $info['headimgurl'];
			$data['subscribe_time'] = $info['subscribe_time'];
			$data['remark'] = $info['remark'];
			$data['groupid'] = isset($info['groupid']) ? $info['groupid'] : $this->weObj->getUserGroup($openid);
			$data['unionid'] = isset($info['unionid']) ? $info['unionid'] : '';
			if (($this->ru_id == 0) && empty($data['unionid'])) {
				exit('null');
			}

			if ($this->ru_id == 0) {
				update_wechat_unionid($info, $this->wechat_id);
			}

			$condition = array('unionid' => $data['unionid'], 'wechat_id' => $this->wechat_id);
			$result = dao('wechat_user')->field('ect_uid, openid, unionid')->where($condition)->find();

			if (isset($result)) {
				$users = dao('users')->where(array('user_id' => $result['ect_uid']))->find();
				if (empty($users) || empty($result['ect_uid'])) {
					dao('wechat_user')->where($condition)->delete();
					$result = array();
					unset($_SESSION['user_id']);
				}
			}

			if (empty($result)) {
				$old_users = dao('users')->field('user_id, parent_id')->where(array('aite_id' => 'wechat_' . $data['unionid']))->find();

				if (!empty($old_users)) {
					dao('users')->data(array('aite_id' => ''))->where(array('user_id' => $older_user['user_id']))->save();
					$res = array('unionid' => $data['unionid'], 'user_id' => $old_users['user_id']);
					update_connnect_user($res, 'wechat');
				}

				$userinfo = get_connect_user($data['unionid']);
				if (empty($userinfo) && ($this->ru_id == 0)) {
					$username = get_wechat_username($data['unionid'], 'wechat');
					$password = mt_rand(100000, 999999);
					$email = $username . '@qq.com';
					$is_drp = false;
					$parent_id = 0;
					$drp_parent_id = 0;

					if (!empty($scene_id)) {
						if (strpos($scene_id, 'u') == 0) {
							$scene_uid = str_replace('u=', '', $scene_id);
						}

						if (strpos($scene_id, 'd') == 0) {
							$scene_did = str_replace('d=', '', $scene_id);
							$is_drp = true;
						}

						$parent_id = intval($scene_uid);
						$drp_parent_id = intval($scene_did);

						if (0 < $parent_id) {
							$user_count = dao('users')->where(array('user_id' => $parent_id))->count();

							if (empty($user_count)) {
								$parent_id = 0;
							}
						}

						if (0 < $drp_parent_id) {
							$drp_count = dao('drp_shop')->where(array('user_id' => $drp_parent_id, 'audit' => 1))->count();

							if (empty($drp_count)) {
								$drp_parent_id = 0;
							}
						}
					}

					$extend = array('nick_name' => $data['nickname'], 'sex' => $data['sex'], 'user_picture' => $data['headimgurl']);

					if ($is_drp == true) {
						$extend['drp_parent_id'] = $drp_parent_id;
					}
					else {
						$extend['parent_id'] = $parent_id;
					}

					if (register($username, $password, $email, $extend) !== false) {
						$res = array('unionid' => $data['unionid'], 'user_id' => $_SESSION['user_id'], 'nickname' => $data['nickname'], 'sex' => $data['sex'], 'province' => $data['province'], 'city' => $data['city'], 'country' => $data['country'], 'headimgurl' => $data['headimgurl']);
						update_connnect_user($res, 'wechat');
						$data['ect_uid'] = $_SESSION['user_id'];
						$data['from'] = 0;
					}
					else {
						exit('null');
					}
				}

				dao('wechat_user')->data($data)->add();

				if ($this->ru_id == 0) {
					$data1['user_id'] = $_SESSION['user_id'];
					$bonus_num = dao('user_bonus')->where($data1)->count();

					if ($bonus_num <= 0) {
						$content = $this->send_message($openid, 'bonus', $this->weObj, 1);
						$bonus_msg = (empty($content) ? '' : $content['content']);

						if (!empty($bonus_msg)) {
							$msg = array(
								'touser'  => $openid,
								'msgtype' => 'text',
								'text'    => array('content' => $bonus_msg)
								);
							$this->weObj->sendCustomMessage($msg);
						}
					}
				}
			}
			else {
				$template = $data['nickname'] . '，欢迎您再次回来';
				$this->send_custom_message($openid, 'text', $template);
				dao('wechat_user')->data($data)->where($condition)->save();
			}

			check_template_log($data['openid'], $this->wechat_id, $this->weObj);
		}
	}

	public function unsubscribe($openid = '')
	{
		$where['openid'] = $openid;
		$where['wechat_id'] = $this->wechat_id;
		$rs = dao('wechat_user')->where($where)->count();

		if (0 < $rs) {
			$data['subscribe'] = 0;
			dao('wechat_user')->data($data)->where($where)->save();
			dao('wechat_user_tag')->where($where)->delete();
		}
	}

	private function do_qrcode_subscribe($scene_id, $flag = false)
	{
		if (strpos($scene_id, 'u') == 0) {
			$scene_id = str_replace('u=', '', $scene_id);
		}

		$scene_id = intval($scene_id);
		$qrcode_fun = dao('wechat_qrcode')->where(array('scene_id' => $scene_id, 'wechat_id' => $this->wechat_id))->getField('function');

		if ($flag == true) {
			$this->db->query('UPDATE {pre}wechat_qrcode SET scan_num = scan_num + 1 WHERE scene_id = ' . $scene_id . ' and wechat_id = ' . $this->wechat_id);
		}

		return $qrcode_fun;
	}

	private function msg_reply($type, $return = 0)
	{
		$replyInfo = $this->db->table('wechat_reply')->field('content, media_id')->where(array('type' => $type, 'wechat_id' => $this->wechat_id))->find();

		if (!empty($replyInfo)) {
			if (!empty($replyInfo['media_id'])) {
				$replyInfo['media'] = $this->db->table('wechat_media')->field('title, content, file, type, file_name')->where(array('id' => $replyInfo['media_id']))->find();

				if ($replyInfo['media']['type'] == 'news') {
					$replyInfo['media']['type'] = 'image';
				}

				$filename = (!empty($replyInfo['command']) ? dirname(ROOT_PATH) . '/mobile/' . $replyInfo['media']['file'] : dirname(ROOT_PATH) . '/' . $replyInfo['media']['file']);
				$rs = $this->weObj->uploadMedia(array('media' => realpath_wechat($filename)), $replyInfo['media']['type']);

				if (empty($rs)) {
					logResult($this->weObj->errMsg);
				}

				if (($rs['type'] == 'image') || ($rs['type'] == 'voice')) {
					$replyData = array(
						'ToUserName'         => $this->weObj->getRev()->getRevFrom(),
						'FromUserName'       => $this->weObj->getRev()->getRevTo(),
						'CreateTime'         => time(),
						'MsgType'            => $rs['type'],
						ucfirst($rs['type']) => array('MediaId' => $rs['media_id'])
						);
				}
				else if ('video' == $rs['type']) {
					$replyData = array(
						'ToUserName'         => $this->weObj->getRev()->getRevFrom(),
						'FromUserName'       => $this->weObj->getRev()->getRevTo(),
						'CreateTime'         => time(),
						'MsgType'            => $rs['type'],
						ucfirst($rs['type']) => array('MediaId' => $rs['media_id'], 'Title' => $replyInfo['media']['title'], 'Description' => strip_tags($replyInfo['media']['content']))
						);
				}

				if ($return) {
					return array('type' => 'media', 'content' => $replyData);
				}

				$this->weObj->reply($replyData);
				$this->record_msg($this->weObj->getRev()->getRevTo(), '图文信息', 1);
			}
			else {
				$replyInfo['content'] = html_out($replyInfo['content']);

				if ($return) {
					return array('type' => 'text', 'content' => $replyInfo['content']);
				}

				$this->weObj->text($replyInfo['content'])->reply();
				$this->record_msg($this->weObj->getRev()->getRevTo(), $replyInfo['content'], 1);
			}
		}
	}

	private function keywords_reply($keywords)
	{
		$endrs = false;
		$sql = 'SELECT r.content, r.media_id, r.reply_type FROM {pre}wechat_reply r LEFT JOIN {pre}wechat_rule_keywords k ON r.id = k.rid WHERE k.rule_keywords = "' . $keywords . '" and r.wechat_id = ' . $this->wechat_id . ' order by r.add_time desc LIMIT 1';
		$result = $this->db->query($sql);

		if (!empty($result)) {
			if (!empty($result[0]['media_id'])) {
				$mediaInfo = $this->db->table('wechat_media')->field('id, title, digest, content, file, type, file_name, article_id, link')->where(array('id' => $result[0]['media_id']))->find();
				if (($result[0]['reply_type'] == 'image') || ($result[0]['reply_type'] == 'voice')) {
					$filename = (!empty($mediaInfo['command']) ? dirname(ROOT_PATH) . '/mobile/' . $mediaInfo['file'] : dirname(ROOT_PATH) . '/' . $mediaInfo['file']);
					$rs = $this->weObj->uploadMedia(array('media' => realpath_wechat($filename)), $result[0]['reply_type']);

					if (empty($rs)) {
						logResult($this->weObj->errMsg);
					}

					$replyData = array(
						'ToUserName'         => $this->weObj->getRev()->getRevFrom(),
						'FromUserName'       => $this->weObj->getRev()->getRevTo(),
						'CreateTime'         => time(),
						'MsgType'            => $rs['type'],
						ucfirst($rs['type']) => array('MediaId' => $rs['media_id'])
						);
					$this->weObj->reply($replyData);
					$endrs = true;
				}
				else if ('video' == $result[0]['reply_type']) {
					$filename = (!empty($mediaInfo['command']) ? dirname(ROOT_PATH) . '/mobile/' . $mediaInfo['file'] : dirname(ROOT_PATH) . '/' . $mediaInfo['file']);
					$rs = $this->weObj->uploadMedia(array('media' => realpath_wechat($filename)), $result[0]['reply_type']);

					if (empty($rs)) {
						logResult($this->weObj->errMsg);
					}

					$replyData = array(
						'ToUserName'         => $this->weObj->getRev()->getRevFrom(),
						'FromUserName'       => $this->weObj->getRev()->getRevTo(),
						'CreateTime'         => time(),
						'MsgType'            => $rs['type'],
						ucfirst($rs['type']) => array('MediaId' => $rs['media_id'], 'Title' => $replyInfo['media']['title'], 'Description' => strip_tags($replyInfo['media']['content']))
						);
					$this->weObj->reply($replyData);
					$endrs = true;
				}
				else if ('news' == $result[0]['reply_type']) {
					$articles = array();

					if (!empty($mediaInfo['article_id'])) {
						$artids = explode(',', $mediaInfo['article_id']);

						foreach ($artids as $key => $val) {
							$artinfo = $this->db->table('wechat_media')->field('id, title, file, digest, content, link')->where(array('id' => $val))->find();
							$artinfo['content'] = sub_str(strip_tags(html_out($artinfo['content'])), 100);
							$articles[$key]['Title'] = $artinfo['title'];
							$articles[$key]['Description'] = empty($artinfo['digest']) ? $artinfo['content'] : $artinfo['digest'];
							$articles[$key]['PicUrl'] = get_wechat_image_path($artinfo['file']);
							$articles[$key]['Url'] = empty($artinfo['link']) ? __HOST__ . url('article/index/wechat', array('id' => $artinfo['id'])) : strip_tags(html_out($artinfo['link']));
						}
					}
					else {
						$articles[0]['Title'] = $mediaInfo['title'];
						$articles[0]['Description'] = empty($mediaInfo['digest']) ? sub_str(strip_tags(html_out($mediaInfo['content'])), 100) : $mediaInfo['digest'];
						$articles[0]['PicUrl'] = get_wechat_image_path($mediaInfo['file']);
						$articles[0]['Url'] = empty($mediaInfo['link']) ? __HOST__ . url('article/index/wechat', array('id' => $mediaInfo['id'])) : strip_tags(html_out($mediaInfo['link']));
					}

					$this->weObj->news($articles)->reply();
					$this->record_msg($this->weObj->getRev()->getRevTo(), '图文信息', 1);
					$endrs = true;
				}
			}
			else {
				$result[0]['content'] = html_out($result[0]['content']);
				$this->weObj->text($result[0]['content'])->reply();
				$this->record_msg($this->weObj->getRev()->getRevTo(), $result[0]['content'], 1);
				$endrs = true;
			}
		}

		return $endrs;
	}

	public function get_function($fromusername, $keywords)
	{
		$return = false;
		$rs = $this->db->query('SELECT name, keywords, command, config FROM {pre}wechat_extend WHERE keywords like \'%' . $keywords . '%\' OR command = \'' . $keywords . '\' and enable = 1 and wechat_id = \'' . $this->wechat_id . '\' ORDER BY id ASC LIMIT 12');

		if (empty($rs)) {
			$rs = $this->db->query('SELECT name, keywords, command, config FROM {pre}wechat_extend WHERE command = \'search\' and enable = 1 and wechat_id = \'' . $this->wechat_id . '\' ');
		}

		$info = reset($rs);
		$info['user_keywords'] = $keywords;
		$file = MODULE_PATH . 'plugins/' . $info['command'] . '/' . ucfirst($info['command']) . '.php';

		if (file_exists($file)) {
			require_once $file;
			$new_command = '\\app\\http\\wechat\\plugins\\' . $info['command'] . '\\' . ucfirst($info['command']);
			$cfg = array('ru_id' => $this->ru_id);
			$wechat = new $new_command($cfg);
			$data = $wechat->returnData($fromusername, $info);

			if (!empty($data)) {
				if ($data['type'] == 'text') {
					$this->weObj->text($data['content'])->reply();
					$this->record_msg($fromusername, $data['content'], 1);
				}
				else if ($data['type'] == 'news') {
					$this->weObj->news($data['content'])->reply();
					$this->record_msg($fromusername, '图文消息', 1);
				}
				else if ($data['type'] == 'image') {
					$filename = dirname(ROOT_PATH) . '/' . $data['path'];
					$rs = $this->weObj->uploadMedia(array('media' => realpath_wechat($filename)), 'image');

					if (empty($rs)) {
						logResult($this->weObj->errMsg);
					}

					$this->weObj->image($rs['media_id'])->reply();
					$this->record_msg($fromusername, '图片', 1);
				}

				$return = true;
			}
		}

		return $return;
	}

	public function get_marketing($fromusername, $keywords)
	{
		$return = false;
		$sql = 'SELECT id, name, command, status FROM {pre}wechat_marketing WHERE (command = \'' . $keywords . '\') AND wechat_id = \'' . $this->wechat_id . '\' ORDER BY id DESC ';
		$rs = $this->db->query($sql);
		$rs = reset($rs);

		if ($rs) {
			$where = array('id' => $rs['id'], 'command' => $rs['command'], 'wechat_id' => $this->wechat_id);
			$result = dao('wechat_marketing')->field('id, name, background, description, status, url')->where($where)->find();
		}

		if ($result) {
			$articles = array('type' => 'text', 'content' => '活动未启用');

			if ($result['status'] == 1) {
				$articles = array();
				$articles['type'] = 'news';
				$articles['content'][0]['Title'] = $result['name'];
				$articles['content'][0]['Description'] = $result['description'];
				$articles['content'][0]['PicUrl'] = get_wechat_image_path($result['background']);
				$articles['content'][0]['Url'] = strip_tags(html_out($result['url']));
			}

			if ($articles['type'] == 'text') {
				$this->weObj->text($articles['content'])->reply();
				$this->record_msg($fromusername, $articles['content'], 1);
			}
			else if ($articles['type'] == 'news') {
				$this->weObj->news($articles['content'])->reply();
				$this->record_msg($fromusername, '图文消息', 1);
			}

			$return = true;
		}

		return $return;
	}

	public function send_message($fromusername, $keywords, $weObj, $return = 0)
	{
		$result = false;
		$condition = array('command' => $keywords, 'enable' => 1, 'wechat_id' => $this->wechat_id);
		$rs = dao('wechat_extend')->field('name, command, config')->where($condition)->find();
		$file = MODULE_PATH . 'plugins/' . $rs['command'] . '/' . ucfirst($rs['command']) . '.php';

		if (file_exists($file)) {
			require_once $file;
			$new_command = '\\app\\http\\wechat\\plugins\\' . $rs['command'] . '\\' . ucfirst($rs['command']);
			$cfg = array('ru_id' => $this->ru_id);
			$wechat = new $new_command($cfg);
			$data = $wechat->returnData($fromusername, $rs);

			if (!empty($data)) {
				if ($return) {
					$result = $data;
				}
				else {
					$weObj->sendCustomMessage($data['content']);
					$result = true;
				}
			}
		}

		return $result;
	}

	public function customer_service($fromusername, $keywords)
	{
		$result = false;
		$kfsession = $this->weObj->getKFSession($fromusername);
		if (empty($kfsession) || empty($kfsession['kf_account'])) {
			$kefu = dao('wechat_user')->where(array('openid' => $fromusername, 'wechat_id' => $this->wechat_id))->getField('openid');
			if ($kefu && ($keywords == 'kefu')) {
				$rs = $this->db->table('wechat_extend')->where(array('command' => 'kefu', 'enable' => 1, 'wechat_id' => $this->wechat_id))->getField('config');

				if (!empty($rs)) {
					$config = unserialize($rs);
					$msg = array(
						'touser'  => $fromusername,
						'msgtype' => 'text',
						'text'    => array('content' => '欢迎进入多客服系统')
						);
					$this->weObj->sendCustomMessage($msg);
					$this->record_msg($fromusername, $msg['text']['content'], 1);
					$online_list = $this->weObj->getCustomServiceOnlineKFlist();

					if ($online_list['kf_online_list']) {
						foreach ($online_list['kf_online_list'] as $key => $val) {
							if (($config['customer'] == $val['kf_account']) && (0 < $val['status']) && ($val['accepted_case'] < $val['auto_accept'])) {
								$customer = $config['customer'];
							}
							else {
								$customer = '';
							}
						}
					}

					$this->weObj->transfer_customer_service($customer)->reply();
					$result = true;
				}
			}
		}

		return $result;
	}

	public function close_kf($openid, $keywords)
	{
		$openid = $this->model->table('wechat_user')->where(array('openid' => $openid, 'wechat_id' => $this->wechat_id))->getField('openid');

		if ($openid) {
			$kfsession = $this->weObj->getKFSession($openid);
			if (($keywords == 'q') && isset($kfsession['kf_account']) && !empty($kfsession['kf_account'])) {
				$rs = $this->weObj->closeKFSession($openid, $kfsession['kf_account'], '客户已主动关闭多客服');

				if ($rs) {
					$msg = array(
						'touser'  => $openid,
						'msgtype' => 'text',
						'text'    => array('content' => '您已退出多客服系统')
						);
					$this->weObj->sendCustomMessage($msg);
					return true;
				}
			}
		}

		return false;
	}

	public function record_msg($fromusername, $keywords, $is_wechat_admin = 0)
	{
		$uid = dao('wechat_user')->where(array('openid' => $fromusername, 'wechat_id' => $this->wechat_id))->getField('uid');

		if ($uid) {
			$data['uid'] = $uid;
			$data['msg'] = $keywords;
			$data['wechat_id'] = $this->wechat_id;
			$data['send_time'] = gmtime();

			if ($is_wechat_admin) {
				$data['is_wechat_admin'] = $is_wechat_admin;
			}

			dao('wechat_custom_message')->data($data)->add();
		}
	}

	public function actionPluginShow()
	{
		if (is_wechat_browser() && (!isset($_SESSION['unionid']) || empty($_SESSION['unionid']) || empty($_SESSION['openid']))) {
			$back_url = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : __HOST__ . $_SERVER['REQUEST_URI']);
			$this->redirect('oauth/index/index', array('type' => 'wechat', 'back_url' => urlencode($back_url)));
		}

		$plugin_name = I('get.name', '', 'trim');
		$ru_id = I('get.ru_id', 0, 'intval');
		$ru_id = (!empty($ru_id) ? $ru_id : $_COOKIE['ectouch_ru_id']);
		$file = MODULE_PATH . 'plugins/' . $plugin_name . '/' . ucfirst($plugin_name) . '.php';

		if (file_exists($file)) {
			include_once $file;
			$new_plugin = '\\app\\http\\wechat\\plugins\\' . $plugin_name . '\\' . ucfirst($plugin_name);
			$cfg = array('ru_id' => $ru_id);
			$wechat = new $new_plugin($cfg);
			$wechat->html_show();
		}
	}

	public function actionPluginAction()
	{
		$plugin_name = I('get.name', '', 'trim');
		$ru_id = I('get.ru_id', 0, 'intval');
		$ru_id = (!empty($ru_id) ? $ru_id : $_COOKIE['ectouch_ru_id']);
		$file = MODULE_PATH . 'plugins/' . $plugin_name . '/' . ucfirst($plugin_name) . '.php';

		if (file_exists($file)) {
			include_once $file;
			$new_plugin = '\\app\\http\\wechat\\plugins\\' . $plugin_name . '\\' . ucfirst($plugin_name);
			$cfg = array('ru_id' => $ru_id);
			$wechat = new $new_plugin($cfg);
			$wechat->executeAction();
		}
	}

	private function get_config($secret_key = '')
	{
		$config = dao('wechat')->field('id, token, appid, appsecret, encodingaeskey, ru_id')->where(array('secret_key' => $secret_key, 'status' => 1))->find();

		if (empty($config)) {
			$config = array();
		}

		return $config;
	}

	public function check_auth()
	{
		$appid = I('get.appid');
		$appsecret = I('get.appsecret');
		if (empty($appid) || empty($appsecret)) {
			echo json_encode(array('errmsg' => '信息不完整，请提供完整信息', 'errcode' => 1));
			exit();
		}

		$config = dao('wechat')->field('token, appid, appsecret')->where(array('appid' => $appid, 'appsecret' => $appsecret, 'status' => 1))->find();

		if (empty($config)) {
			echo json_encode(array('errmsg' => '信息错误，请检查提供的信息', 'errcode' => 1));
			exit();
		}

		$obj = new \App\Extensions\Wechat($config);
		$access_token = $obj->checkAuth();

		if ($access_token) {
			echo json_encode(array('access_token' => $access_token, 'errcode' => 0));
			exit();
		}
		else {
			echo json_encode(array('errmsg' => $obj->errmsg, 'errcode' => $obj->errcode));
			exit();
		}
	}

	static public function snsapi_base($ru_id = 0)
	{
		$where = array('ru_id' => $ru_id);
		$wxinfo = dao('wechat')->field('token, appid, appsecret, status')->where($where)->find();
		if (!empty($wxinfo) && ($wxinfo['status'] == 1) && is_wechat_browser() && (empty($_SESSION['openid']) || empty($_COOKIE['openid']))) {
			$config = array('appid' => $wxinfo['appid'], 'appsecret' => $wxinfo['appsecret'], 'token' => $wxinfo['token']);
			$obj = new \App\Extensions\Wechat($config);
			if (isset($_GET['code']) && ($_GET['state'] == 'repeat')) {
				$token = $obj->getOauthAccessToken();
				$_SESSION['openid'] = $token['openid'];
				cookie('openid', $token['openid'], gmtime() + (3600 * 24));
				$userinfo['openid'] = $token['openid'];
				logResult($userinfo);
				update_seller_wechat($userinfo, $ru_id);
			}

			$callback = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : __HOST__ . $_SERVER['REQUEST_URI']);
			$url = $obj->getOauthRedirect($callback, 'repeat', 'snsapi_base');
			redirect($url);
		}
	}

	public function send_custom_message($openid = 0, $msgtype = '', $data)
	{
		$msg = array();

		if ($msgtype == 'text') {
			$msg = array(
				'touser'  => $openid,
				'msgtype' => 'text',
				'text'    => array('content' => $data)
				);
		}
		else if ($msgtype == 'image') {
			$msg = array(
				'touser'  => $openid,
				'msgtype' => 'image',
				'image'   => array('media_id' => $data)
				);
		}
		else if ($msgtype == 'voice') {
			$msg = array(
				'touser'  => $openid,
				'msgtype' => 'voice',
				'voice'   => array('media_id' => $data)
				);
		}
		else if ($msgtype == 'video') {
			$msg = array(
				'touser'  => $openid,
				'msgtype' => 'video',
				'video'   => array('media_id' => $data['media_id'], 'thumb_media_id' => $data['media_id'], 'title' => $data['title'], 'description' => $data['description'])
				);
		}
		else if ($msgtype == 'music') {
			$msg = array(
				'touser'  => $openid,
				'msgtype' => 'music',
				'music'   => array('title' => $data['title'], 'description' => $data['description'], 'musicurl' => $data['musicurl'], 'hqmusicurl' => $data['hqmusicurl'], 'thumb_media_id' => $data['thumb_media_id'])
				);
		}
		else if ($msgtype == 'news') {
			$newsData = $data;
			$msg = array(
				'touser'  => $openid,
				'msgtype' => 'news',
				'news'    => array('articles' => $newsData)
				);
		}

		$this->weObj->sendCustomMessage($msg);
	}
}

?>
