<?php
//cgxlm
function get_nav()
{
	$result = get_filter();

	if ($result === false) {
		$filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'type DESC, vieworder' : 'type DESC, ' . trim($_REQUEST['sort_by']);
		$filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'ASC' : trim($_REQUEST['sort_order']);
		$sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('touch_nav');
		$filter['record_count'] = $GLOBALS['db']->getOne($sql);
		$filter = page_and_size($filter);
		$sql = 'SELECT id, name, ifshow, vieworder, opennew, url, type' . ' FROM ' . $GLOBALS['ecs']->table('touch_nav') . ' ORDER by ' . $filter['sort_by'] . ' ' . $filter['sort_order'] . ' LIMIT ' . $filter['start'] . ',' . $filter['page_size'];
		set_filter($filter, $sql);
	}
	else {
		$sql = $result['sql'];
		$filter = $result['filter'];
	}

	$navdb = $GLOBALS['db']->getAll($sql);
	$type = '';
	$navdb2 = array();

	foreach ($navdb as $k => $v) {
		if (!empty($type) && ($type != $v['type'])) {
			$navdb2[] = array();
		}

		$navdb2[] = $v;
		$type = $v['type'];
	}

	$arr = array('navdb' => $navdb2, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
	return $arr;
}

function sort_nav($a, $b)
{
	return $b['vieworder'] < $a['vieworder'] ? 1 : -1;
}

function get_sysnav()
{
	global $_LANG;
	$sysmain = array(
		array('进货单', 'index.php?r=cart'),
		array('店铺街', 'index.php?r=store'),
		array('品牌街', 'index.php?r=brand'),
		array('模式管理', 'index.php?r=activity'),
		array('最新团购', 'index.php?r=groupbuy'),
		array('积分换购', 'index.php?r=exchange'),
		array('微社区', 'index.php?r=community'),
		array('微众筹', 'index.php?r=crowd_funding'),
		array('拍卖活动', 'index.php?r=auction'),
		array('超值礼包', 'index.php?r=package'),
		array('专题汇', 'index.php?r=topic'),
		array('新品预定', 'index.php?r=presale'),
		array('内容文章', 'index.php?r=article'),
		array('会员中心', 'index.php?r=user')
		);
	return $sysmain;
}

function nav_update($id, $args)
{
	if (empty($args) || empty($id)) {
		return false;
	}

	return $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('touch_nav'), $args, 'update', 'id=\'' . $id . '\'');
}

function analyse_uri($uri)
{
	$uri = strtolower(str_replace('&amp;', '&', $uri));
	$arr = explode('-', $uri);

	switch ($arr[0]) {
	case 'category':
		return array('type' => 'c', 'id' => $arr[1]);
		break;

	case 'article_cat':
		return array('type' => 'a', 'id' => $arr[1]);
		break;

	default:
		break;
	}

	list($fn, $pm) = explode('?', $uri);

	if (strpos($uri, '&') === false) {
		$arr = array($pm);
	}
	else {
		$arr = explode('&', $pm);
	}

	switch ($fn) {
	case 'category.php':
		foreach ($arr as $k => $v) {
			list($key, $val) = explode('=', $v);

			if ($key == 'id') {
				return array('type' => 'c', 'id' => $val);
			}
		}

		break;

	case 'article_cat.php':
		foreach ($arr as $k => $v) {
			list($key, $val) = explode('=', $v);

			if ($key == 'id') {
				return array('type' => 'a', 'id' => $val);
			}
		}

		break;

	default:
		return false;
		break;
	}
}

function is_show_in_nav($type, $id)
{
	if ($type == 'c') {
		$tablename = $GLOBALS['ecs']->table('category');
	}
	else {
		$tablename = $GLOBALS['ecs']->table('article_cat');
	}

	return $GLOBALS['db']->getOne('SELECT show_in_nav FROM ' . $tablename . ' WHERE cat_id = \'' . $id . '\'');
}

function set_show_in_nav($type, $id, $val)
{
	if ($type == 'c') {
		$tablename = $GLOBALS['ecs']->table('category');
	}
	else {
		$tablename = $GLOBALS['ecs']->table('article_cat');
	}

	$GLOBALS['db']->query('UPDATE ' . $tablename . ' SET show_in_nav = \'' . $val . '\' WHERE cat_id = \'' . $id . '\'');
	clear_cache_files();
}

define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
admin_priv('touch_nav_admin');
$exc = new exchange($ecs->table('touch_nav'), $db, 'id', 'name');

if ($_REQUEST['act'] == 'list') {
	$smarty->assign('ur_here', $_LANG['navigator']);
	$smarty->assign('action_link', array('text' => $_LANG['add_new'], 'href' => 'touch_navigator.php?act=add'));
	$smarty->assign('full_page', 1);
	$navdb = get_nav();
	$smarty->assign('navdb', $navdb['navdb']);
	$smarty->assign('filter', $navdb['filter']);
	$smarty->assign('record_count', $navdb['record_count']);
	$smarty->assign('page_count', $navdb['page_count']);
	assign_query_info();
	$smarty->display('touch_navigator.dwt');
}
else if ($_REQUEST['act'] == 'query') {
	$navdb = get_nav();
	$smarty->assign('navdb', $navdb['navdb']);
	$smarty->assign('filter', $navdb['filter']);
	$smarty->assign('record_count', $navdb['record_count']);
	$smarty->assign('page_count', $navdb['page_count']);
	$sort_flag = sort_flag($navdb['filter']);
	$smarty->assign($sort_flag['tag'], $sort_flag['img']);
	make_json_result($smarty->fetch('touch_navigator.dwt'), '', array('filter' => $navdb['filter'], 'page_count' => $navdb['page_count']));
}
else if ($_REQUEST['act'] == 'add') {
	if (empty($_REQUEST['step'])) {
		$rt = array('act' => 'add');
		set_default_filter();
		$sysmain = get_sysnav();
		$smarty->assign('action_link', array('text' => $_LANG['go_list'], 'href' => 'touch_navigator.php?act=list'));
		$smarty->assign('ur_here', $_LANG['navigator']);
		assign_query_info();
		$smarty->assign('sysmain', $sysmain);
		$smarty->assign('rt', $rt);
		$smarty->display('touch_navigator_add.dwt');
	}
	else if ($_REQUEST['step'] == 2) {
		$item_name = $_REQUEST['item_name'];
		$item_url = $_REQUEST['item_url'];
		$item_ifshow = $_REQUEST['item_ifshow'];
		$item_opennew = $_REQUEST['item_opennew'];
		$item_type = $_REQUEST['item_type'];
		$pic = '';
		if ((isset($_FILES['pic']['error']) && ($_FILES['pic']['error'] == 0)) || (!isset($_FILES['pic']['error']) && isset($_FILES['pic']['tmp_name']) && ($_FILES['pic']['tmp_name'] != 'none'))) {
			$typename = explode('.', $_FILES['pic']['name']);
			$typename = '.' . $typename[1];
			$newname = time() . $typename;
			$fil_pic = ROOT_PATH . 'data/attached/nav';

			if (!file_exists($fil_pic)) {
				make_dir($fil_pic);
			}

			copy($_FILES['pic']['tmp_name'], ROOT_PATH . 'data/attached/nav/' . $newname);
			$pic = $newname;
		}

		if ($pic) {
			get_oss_add_file(array('data/attached/nav/' . $pic));
		}

		$vieworder = $db->getOne('SELECT max(vieworder) FROM ' . $ecs->table('touch_nav') . ' WHERE type = \'' . $item_type . '\'');
		$item_vieworder = (empty($_REQUEST['item_vieworder']) ? $vieworder + 1 : $_REQUEST['item_vieworder']);
		if (($item_ifshow == 1) && ($item_type == 'middle')) {
			$arr = analyse_uri($item_url);

			if ($arr) {
				set_show_in_nav($arr['type'], $arr['id'], 1);
				$sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('touch_nav') . ' (name,ctype,cid,ifshow,vieworder,opennew,url,type,pic) VALUES(\'' . $item_name . '\',\'' . $arr['type'] . '\',\'' . $arr['id'] . '\',\'' . $item_ifshow . '\',\'' . $item_vieworder . '\',\'' . $item_opennew . '\',\'' . $item_url . '\',\'' . $item_type . '\',\'' . $pic . '\')';
			}
		}

		if (empty($sql)) {
			$sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('touch_nav') . ' (name,ifshow,vieworder,opennew,url,type,pic) VALUES(\'' . $item_name . '\',\'' . $item_ifshow . '\',\'' . $item_vieworder . '\',\'' . $item_opennew . '\',\'' . $item_url . '\',\'' . $item_type . '\',\'' . $pic . '\')';
		}

		$db->query($sql);
		clear_cache_files();
		$links[] = array('text' => $_LANG['navigator'], 'href' => 'touch_navigator.php?act=list');
		$links[] = array('text' => $_LANG['add_new'], 'href' => 'touch_navigator.php?act=add');
		sys_msg($_LANG['edit_ok'], 0, $links);
	}
}
else if ($_REQUEST['act'] == 'edit') {
	$id = $_REQUEST['id'];

	if (empty($_REQUEST['step'])) {
		$rt = array('act' => 'edit', 'id' => $id);
		$row = $db->getRow('SELECT * FROM ' . $GLOBALS['ecs']->table('touch_nav') . ' WHERE id=\'' . $id . '\'');
		$rt['item_name'] = $row['name'];
		$rt['item_url'] = $row['url'];
		$rt['item_vieworder'] = $row['vieworder'];
		$rt['item_ifshow_' . $row['ifshow']] = 'selected';
		$rt['item_opennew_' . $row['opennew']] = 'selected';
		$rt['item_type'] = $row['type'];
		$rt['ifshow'] = $row['ifshow'];
		$rt['opennew'] = $row['opennew'];

		if ($row['pic']) {
			$rt['pic'] = 'data/attached/nav/' . $row['pic'];
		}

		set_default_filter();
		$sysmain = get_sysnav();
		$smarty->assign('action_link', array('text' => $_LANG['go_list'], 'href' => 'touch_navigator.php?act=list'));
		$smarty->assign('ur_here', $_LANG['navigator']);
		assign_query_info();
		$smarty->assign('sysmain', $sysmain);
		$smarty->assign('rt', $rt);
		$smarty->display('touch_navigator_add.dwt');
	}
	else if ($_REQUEST['step'] == 2) {
		$item_name = $_REQUEST['item_name'];
		$item_url = $_REQUEST['item_url'];
		$item_ifshow = $_REQUEST['item_ifshow'];
		$item_opennew = $_REQUEST['item_opennew'];
		$item_type = $_REQUEST['item_type'];
		$item_vieworder = (int) $_REQUEST['item_vieworder'];
		$pic = '';
		if ((isset($_FILES['pic']['error']) && ($_FILES['pic']['error'] == 0)) || (!isset($_FILES['pic']['error']) && isset($_FILES['pic']['tmp_name']) && ($_FILES['pic']['tmp_name'] != 'none'))) {
			$typename = explode('.', $_FILES['pic']['name']);
			$typename = '.' . $typename[1];
			$newname = time() . $typename;
			$fil_pic = ROOT_PATH . 'data/attached/nav';

			if (!file_exists($fil_pic)) {
				make_dir($fil_pic);
			}

			copy($_FILES['pic']['tmp_name'], ROOT_PATH . 'data/attached/nav/' . $newname);
			$pic = $newname;
		}

		$row = $db->getRow('SELECT ctype,cid,ifshow,type FROM ' . $GLOBALS['ecs']->table('touch_nav') . ' WHERE id = \'' . $id . '\'');
		$arr = analyse_uri($item_url);

		if ($pic) {
			$where_pic = ',pic=\'' . $pic . '\'';
			get_oss_add_file(array('data/attached/nav/' . $pic));
		}

		if ($arr) {
			if (($row['ctype'] == $arr['type']) && ($row['cid'] == $arr['id'])) {
				if ($item_type != 'middle') {
					set_show_in_nav($arr['type'], $arr['id'], 0);
				}
			}
			else {
				if (($row['ifshow'] == 1) && ($row['type'] == 'middle')) {
					set_show_in_nav($row['ctype'], $row['cid'], 0);
				}
				else {
					if (($row['ifshow'] == 0) && ($row['type'] == 'middle')) {
					}
				}
			}

			if (($item_ifshow != is_show_in_nav($arr['type'], $arr['id'])) && ($item_type == 'middle')) {
				set_show_in_nav($arr['type'], $arr['id'], $item_ifshow);
			}

			$sql = 'UPDATE ' . $GLOBALS['ecs']->table('touch_nav') . ' SET name=\'' . $item_name . '\',ctype=\'' . $arr['type'] . '\',cid=\'' . $arr['id'] . '\',ifshow=\'' . $item_ifshow . '\',vieworder=\'' . $item_vieworder . '\',opennew=\'' . $item_opennew . '\',url=\'' . $item_url . '\',type=\'' . $item_type . '\'' . $where_pic . '  WHERE id=\'' . $id . '\'';
		}
		else {
			if ($row['ctype'] && $row['cid']) {
				set_show_in_nav($row['ctype'], $row['cid'], 0);
			}

			$sql = 'UPDATE ' . $GLOBALS['ecs']->table('touch_nav') . ' SET name=\'' . $item_name . '\',ctype=\'\',cid=\'\',ifshow=\'' . $item_ifshow . '\',vieworder=\'' . $item_vieworder . '\',opennew=\'' . $item_opennew . '\',url=\'' . $item_url . '\',type=\'' . $item_type . '\'' . $where_pic . '  WHERE id=\'' . $id . '\'';
		}

		$db->query($sql);
		clear_cache_files();
		$links[] = array('text' => $_LANG['navigator'], 'href' => 'touch_navigator.php?act=list');
		sys_msg($_LANG['edit_ok'], 0, $links);
	}
}
else if ($_REQUEST['act'] == 'del') {
	$id = (int) $_GET['id'];
	$row = $db->getRow('SELECT ctype,cid,type FROM ' . $GLOBALS['ecs']->table('touch_nav') . ' WHERE id = \'' . $id . '\' LIMIT 1');
	if (($row['type'] == 'middle') && $row['ctype'] && $row['cid']) {
		set_show_in_nav($row['ctype'], $row['cid'], 0);
	}

	$sql = ' DELETE FROM ' . $GLOBALS['ecs']->table('touch_nav') . ' WHERE id=\'' . $id . '\' LIMIT 1';
	$db->query($sql);
	clear_cache_files();
	ecs_header("Location: touch_navigator.php?act=list\n");
	exit();
}
else if ($_REQUEST['act'] == 'edit_sort_order') {
	check_authz_json('nav');
	$id = intval($_POST['id']);
	$order = json_str_iconv(trim($_POST['val']));

	if (!preg_match('/^[0-9]+$/', $order)) {
		make_json_error(sprintf($_LANG['enter_int'], $order));
	}
	else if ($exc->edit('vieworder = \'' . $order . '\'', $id)) {
		clear_cache_files();
		make_json_result(stripslashes($order));
	}
	else {
		make_json_error($db->error());
	}
}

if ($_REQUEST['act'] == 'toggle_ifshow') {
	$id = intval($_POST['id']);
	$val = intval($_POST['val']);
	$row = $db->getRow('SELECT type,ctype,cid FROM ' . $GLOBALS['ecs']->table('touch_nav') . ' WHERE id = \'' . $id . '\' LIMIT 1');
	if (($row['type'] == 'middle') && $row['ctype'] && $row['cid']) {
		set_show_in_nav($row['ctype'], $row['cid'], $val);
	}

	if (nav_update($id, array('ifshow' => $val)) != false) {
		clear_cache_files();
		make_json_result($val);
	}
	else {
		make_json_error($db->error());
	}
}

if ($_REQUEST['act'] == 'toggle_opennew') {
	$id = intval($_POST['id']);
	$val = intval($_POST['val']);

	if (nav_update($id, array('opennew' => $val)) != false) {
		clear_cache_files();
		make_json_result($val);
	}
	else {
		make_json_error($db->error());
	}
}

?>
