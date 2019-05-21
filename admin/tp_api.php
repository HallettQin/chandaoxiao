<?php

/**
 * ECSHOP 第三方服务
 * ===========================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ==========================================================
 * $Author: Hallett
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

$adminru = get_admin_ru_id();

//admin_priv('');

//默认
if (empty($_REQUEST['act']))
{
	die('Error');
}

//快递鸟信息
elseif ($_REQUEST['act'] == 'kdniao')
{
    admin_priv('shop_config');

    $smarty->assign('ur_here', $_LANG['kdniao']);
    $smarty->assign('form_act', 'kdniao_update');
    $smarty->assign('type', 'kdniao');
    $smarty->assign('api_lang', $_LANG['tpApi']['kdniao']);

    $api_config = array();
    $api_config['client_id'] = get_table_date('shop_config', "code='kdniao_client_id'", array('value'), 2);
    $api_config['appkey'] = get_table_date('shop_config', "code='kdniao_appkey'", array('value'), 2);
    $smarty->assign('api_config', $api_config);

    assign_query_info();
    $smarty->display('tp_api.dwt');
}

//快递鸟更新
elseif ($_REQUEST['act'] == 'kdniao_update')
{
    admin_priv('shop_config');

    $client_id = empty($_REQUEST['client_id']) ? '' : trim($_REQUEST['client_id']);
    $appkey = empty($_REQUEST['appkey']) ? '' : trim($_REQUEST['appkey']);
    $sql = " UPDATE " . $GLOBALS['ecs']->table('shop_config') . " SET value = '$client_id' WHERE code = 'kdniao_client_id' ";
    $GLOBALS['db']->query($sql);
    $sql = " UPDATE " . $GLOBALS['ecs']->table('shop_config') . " SET value = '$appkey' WHERE code = 'kdniao_appkey' ";
    $GLOBALS['db']->query($sql);
    $link[] = array('text' => $_LANG['go_back'], 'href' => 'tp_api.php?act=kdniao');
    sys_msg($_LANG['save_success'], 0, $link);
}

//快递鸟打印
elseif ($_REQUEST['act'] == 'kdniao_print')
{
    require_once(ROOT_PATH . 'includes/lib_order.php');
    require_once(ROOT_PATH . 'includes/lib_goods.php');
    require_once(ROOT_PATH . '/plugins/tpApi/kdniao.php');

    $order_id = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
    $order_sn = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
    $order_ids = array();
    if (!empty($order_id)) {
        $order_ids[] = $order_id;
    }
    if (!empty($order_sn)) {
        $sql = " SELECT order_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn " . db_create_in($order_sn);
        $ids = $GLOBALS['db']->getCol($sql);
        $order_ids = array_merge($order_ids, $ids);
    }

    $link[] = array('text' => '关闭窗口', 'href' => 'javascript:window.close()');

    //判断订单
    if (empty($order_ids)) {
        sys_msg("没有选择订单", 1, $link);
    }


    //判断快递是否一样
    $sql = " SELECT shipping_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id " . db_create_in($order_ids);
    $shipping_ids = $GLOBALS['db']->getCol($sql);
    $shipping_ids = array_unique($shipping_ids);
    if (count($shipping_ids) > 1) {
        sys_msg("请选择快递方式相同的订单进行批量打印", 1, $link);
    }

    //by zxk 未生产发货单 不能打印
    foreach ($order_ids as $order_id) {
        /* 取得能执行的操作列表 */
        $delivery_id = $GLOBALS['db']->getOne("SELECT delivery_id FROM " . $ecs->table('delivery_order') . " WHERE order_id = '" .$order_id. "'", true);
        if (!$delivery_id) {
            $order = order_info($order_id);
            sys_msg("订单：".$order['order_sn']." 未生产发货单，不能打印", 1, $link);
        }

        /* 判断退换货订单申请是否通过 strat */
        $sql = "SELECT count(ret_id) FROM " . $ecs->table('order_return') . " WHERE order_id = '$order_id' AND return_status IN (-1, 0) AND refound_status = 0";
        //判断是否存在未处理的退换货申请
        $order_return_exist = $db->getOne($sql);
       if (intval($order_return_exist)) {
            sys_msg("订单：".$order['order_sn']." 已确认为退换货订单，无法继续订单操作！", 1, $link);
        }
    }

    //处理数据
    $batch_html = array();
    $batch_error = array();
    if($order_ids && $order_ids[0]){
        $order_info = order_info($order_ids[0]);

        //识别快递
        $shipping_info = get_table_date('shipping', "shipping_id='" .$order_info['shipping_id']. "'", array('*'));
        $set_modules = true;
        include(ROOT_PATH . 'includes/modules/shipping/' . $shipping_info['shipping_code'] . '.php');
        $shipping_spec = $modules[0];
        $GLOBALS['smarty']->assign('shipping_info', $shipping_info);
        $GLOBALS['smarty']->assign('shipping_spec', $shipping_spec);

        foreach ($order_ids as $order_id) {
            $result = get_kdniao_print_content($order_id, $shipping_spec, $shipping_info);

            //判断是否成功
            if ($result["ResultCode"] != "100") {
                $batch_error[] = "订单（{$order_id}）：电子面单下单失败：{$result['Reason']}";
                continue;
            }

            //输出打印模板
            if (!empty($result['PrintTemplate'])) {
                $batch_html[] = $result['PrintTemplate'];
            } else {
                $batch_error[] = "订单（{$order_id}）：无打印模板";
                continue;
            }

            //将物流单号填入系统
            if (isset($result['Order']['LogisticCode'])) {
                $sql = " UPDATE " . $GLOBALS['ecs']->table('order_info') . " SET invoice_no = '{$result['Order']['LogisticCode']}' WHERE order_id = '$order_id' ";
                $GLOBALS['db']->query($sql);
            }
        }
    }

    $smarty->assign('batch_html', $batch_html);
    $smarty->assign('batch_error', implode(',', $batch_error));
    $smarty->assign('kdniao_printer', get_table_date('seller_shopinfo', "ru_id='{$adminru['ru_id']}'", array('kdniao_printer'), 2));

    $smarty->display('kdniao_print.dwt');
}

/*------------------------------------------------------ */
//-- 电子面单列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'order_print_setting')
{
    admin_priv('order_print_setting');

    $smarty->assign('ur_here', $_LANG['order_print_setting']);
    $smarty->assign('action_link', array('text' => $_LANG['order_print_setting_add'], 'href' => 'tp_api.php?act=order_print_setting_add'));
    $smarty->assign('full_page', 1);

    $print_setting = get_order_print_setting($adminru['ru_id']);

    $smarty->assign('print_setting', $print_setting['list']);
    $smarty->assign('filter', $print_setting['filter']);
    $smarty->assign('record_count', $print_setting['record_count']);
    $smarty->assign('page_count', $print_setting['page_count']);

    $sort_flag = sort_flag($print_setting['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    assign_query_info();
    $smarty->display('order_print_setting.dwt');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'order_print_setting_query')
{
    check_authz_json('order_print_setting');

    $print_setting = get_order_print_setting($adminru['ru_id']);

    $smarty->assign('print_setting', $print_setting['list']);
    $smarty->assign('filter', $print_setting['filter']);
    $smarty->assign('record_count', $print_setting['record_count']);
    $smarty->assign('page_count', $print_setting['page_count']);

    $sort_flag = sort_flag($print_setting['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('order_print_setting.dwt'), '', array('filter' => $print_setting['filter'], 'page_count' => $print_setting['page_count']));
}

/*------------------------------------------------------ */
//-- 删除
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'order_print_setting_remove')
{
    check_authz_json('order_print_setting');

    $id = intval($_GET['id']);

    $exc = new exchange($ecs->table("order_print_setting"), $db, 'id', 'ru_id');
    $exc->drop($id);
    //clear_cache_files();

    $url = 'tp_api.php?act=order_print_setting_query&' . str_replace('act=order_print_setting_remove', '', $_SERVER['QUERY_STRING']);
    ecs_header("Location: $url\n");
    exit;
}

/*------------------------------------------------------ */
//-- 编辑打印机
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_order_printer')
{
    check_authz_json('order_print_setting');

    $id = intval($_POST['id']);
    $val = trim($_POST['val']);

    $sql = " UPDATE " . $ecs->table('order_print_setting') . " SET printer = '$val' WHERE ru_id = '{$adminru['ru_id']}' AND id = '$id' ";
    $db->query($sql);

    //clear_cache_files();
    make_json_result($val);
}

/*------------------------------------------------------ */
//-- 编辑宽度
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_print_width')
{
    check_authz_json('order_print_setting');

    $id = intval($_POST['id']);
    $val = trim($_POST['val']);

    $sql = " UPDATE " . $ecs->table('order_print_setting') . " SET width = '$val' WHERE ru_id = '{$adminru['ru_id']}' AND id = '$id' ";
    $db->query($sql);

    //clear_cache_files();
    make_json_result($val);
}

/*------------------------------------------------------ */
//-- 编辑打印机排序
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_sort_order')
{
    check_authz_json('order_print_setting');

    $id = intval($_POST['id']);
    $val = trim($_POST['val']);

    $sql = " UPDATE " . $ecs->table('order_print_setting') . " SET sort_order = '$val' WHERE ru_id = '{$adminru['ru_id']}' AND id = '$id' ";
    $db->query($sql);

    //clear_cache_files();
    make_json_result($val);
}

/*------------------------------------------------------ */
//-- 切换默认
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'toggle_order_is_default')
{
    check_authz_json('order_print_setting');

    $id = intval($_POST['id']);
    $val = intval($_POST['val']);

    $sql = " UPDATE " . $ecs->table('order_print_setting') . " SET is_default = '$val' WHERE ru_id = '{$adminru['ru_id']}' AND id = '$id' ";
    $db->query($sql);

    if ($val) {
        $sql = " UPDATE " . $ecs->table('order_print_setting') . " SET is_default = '0' WHERE ru_id = '{$adminru['ru_id']}' AND id <> '$id' ";
        $db->query($sql);
    }

    //clear_cache_files();
    make_json_result($val);
}

/*------------------------------------------------------ */
//-- 添加、编辑电子面单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'order_print_setting_add' || $_REQUEST['act'] == 'order_print_setting_edit')
{
    admin_priv('order_print_setting');

    $id = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    $print_size = get_table_date('order_print_size', "1", array('*'), 1);
    $smarty->assign('print_size', $print_size);
    if ($id > 0) {
        $print_setting = get_table_date('order_print_setting', "id='$id'", array('*'));
        $smarty->assign('print_setting', $print_setting);
        $smarty->assign('ur_here', $_LANG['order_print_setting_edit']);
        $smarty->assign('form_action', 'order_print_setting_update');
    } else {
        $smarty->assign('ur_here', $_LANG['order_print_setting_add']);
        $smarty->assign('form_action', 'order_print_setting_insert');
    }
    $smarty->assign('action_link', array('text' => $_LANG['order_print_setting'], 'href' => 'tp_api.php?act=order_print_setting'));

    assign_query_info();
    $smarty->display('order_print_setting_info.dwt');
}

/*------------------------------------------------------ */
//-- 添加、编辑电子面单
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'order_print_setting_insert' || $_REQUEST['act'] == 'order_print_setting_update')
{
    admin_priv('order_print_setting');

    $data = array();
    $id = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    $data['ru_id'] = $adminru['ru_id'];
    $data['is_default'] = !empty($_REQUEST['is_default']) ? intval($_REQUEST['is_default']) : 0;
    $data['specification'] = !empty($_REQUEST['specification']) ? trim($_REQUEST['specification']) : '';
    $data['printer'] = !empty($_REQUEST['printer']) ? trim($_REQUEST['printer']) : '';
    $data['width'] = !empty($_REQUEST['width']) ? intval($_REQUEST['width']) : 0;
    if (empty($data['width'])) {
        $print_size = get_table_date('order_print_size', "specification='{$data['specification']}'", array('height', 'width'));
        $data['width'] = $print_size['width'];
    }

    /* 检查是否重复 */
    $sql = " SELECT id FROM " . $ecs->table('order_print_setting') . " WHERE ru_id = '{$adminru['ru_id']}' AND specification = '{$data['specification']}' AND id <> '$id' LIMIT 1 ";
    $is_only = $db->getOne($sql);
    if (!empty($is_only)) {
        sys_msg($_LANG['specification_exist'], 1);
    }
    /* 插入、更新 */
    if ($id > 0) {
        $db->autoExecute($ecs->table('order_print_setting'), $data, 'UPDATE', "id = '$id'");
        $msg = $_LANG['edit_success'];
    } else {
        $db->autoExecute($ecs->table('order_print_setting'), $data, 'INSERT');
        $id = $db->insert_id();
        $msg = $_LANG['add_success'];
    }
    /* 默认设置 */
    if ($data['is_default']) {
        $db->autoExecute($ecs->table('order_print_setting'), array('is_default' => 0), 'UPDATE', "id <> '$id'");
    }

    $link[] = array('text' => $_LANG['back_list'], 'href' => 'tp_api.php?act=order_print_setting');
    sys_msg($msg, 0, $link);
}

/*------------------------------------------------------ */
//-- 电子面单 by wu
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'order_print')
{
    /* 检查权限 */
    admin_priv('order_view');

    /* 打印数据 */
    $print_specification = get_table_date("order_print_setting", "ru_id='{$adminru['ru_id']}' AND is_default='1'", array("specification"), 2);
    if (empty($print_specification)) {
        $print_specification = get_table_date("order_print_setting", "ru_id='{$adminru['ru_id']}' ORDER BY sort_order, id", array("specification"), 2);
    }

    $print_size_info = get_table_date("order_print_size", "specification='$print_specification'", array("*"));
    $print_size_list = get_table_date("order_print_setting", "ru_id='{$adminru['ru_id']}' ORDER BY sort_order, id", array("*"), 1);
    $print_spec_info = get_table_date("order_print_setting", "specification='$print_specification'", array("*"));

    if (empty($print_size_list)) {
        $link[] = array('text' => $_LANG['back_set'], 'href' => 'tp_api.php?act=order_print_setting');
        sys_msg($_LANG['no_print_setting'], 1, $link);
    }

    $smarty->assign('print_specification', $print_specification);
    $smarty->assign('print_size_info', $print_size_info);
    $smarty->assign('print_size_list', $print_size_list);
    $smarty->assign('print_spec_info', $print_spec_info);

    /* 订单数据 */
    $order_id = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
    $order_sn = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
	$order_type = empty($_REQUEST['order_type']) ? '' : trim($_REQUEST['order_type']);
	if($order_type == 'order'){
		$table = $GLOBALS['ecs']->table('order_info');
	}else{
		$table = $GLOBALS['ecs']->table('wholesale_order_info');
	}
    $order_ids = array();
    if (!empty($order_id)) {
        $order_ids[] = $order_id;
    }
    if (!empty($order_sn)) {
        $sql = " SELECT order_id FROM " . $table . " WHERE order_sn " . db_create_in($order_sn);
        $ids = $GLOBALS['db']->getCol($sql);
        $order_ids = array_merge($order_ids, $ids);
    }

    $web_url = $ecs->url() . ADMIN_PATH . "/";
    $smarty->assign('web_url', $web_url);

	$smarty->assign('order_type', $order_type);

    $part_html = array();
    foreach ($order_ids as $order_id) {
        $order_info = get_print_order_info($order_id,$order_type);
        $smarty->assign('order_info', $order_info);
        $part_html[] = $smarty->fetch('library/order_print_part.lbi');
    }
    $smarty->assign('part_html', $part_html);

    /* 模板赋值 */
    //$smarty->assign('ur_here', $_LANG['order_print']);

    /* 显示模板 */
    assign_query_info();
    $smarty->display('order_print.dwt');
}

/*------------------------------------------------------ */
//-- 切换电子面单 by wu
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'change_order_print')
{
    /* 检查权限 */
    check_authz_json('order_view');

    /* 打印数据 */
    $print_specification = empty($_REQUEST['specification']) ? '' : trim($_REQUEST['specification']);

    $print_size_info = get_table_date("order_print_size", "specification='$print_specification'", array("*"));
    $print_size_list = get_table_date("order_print_setting", "ru_id='{$adminru['ru_id']}' ORDER BY sort_order, id", array("*"), 1);
    $print_spec_info = get_table_date("order_print_setting", "specification='$print_specification'", array("*"));

    $smarty->assign('print_specification', $print_specification);
    $smarty->assign('print_size_info', $print_size_info);
    $smarty->assign('print_size_list', $print_size_list);
    $smarty->assign('print_spec_info', $print_spec_info);

    /* 订单数据 */
    $order_id = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
    $order_sn = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
    $order_ids = array();
    if (!empty($order_id)) {
        $order_ids[] = $order_id;
    }
    if (!empty($order_sn)) {
        $sql = " SELECT order_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn " . db_create_in($order_sn);
        $ids = $GLOBALS['db']->getCol($sql);
        $order_ids = array_merge($order_ids, $ids);
    }

    $web_url = $ecs->url() . ADMIN_PATH . "/";
    $smarty->assign('web_url', $web_url);

    $part_html = array();
    foreach ($order_ids as $order_id) {
        $order_info = get_print_order_info($order_id);
        $smarty->assign('order_info', $order_info);
        $part_html[] = $smarty->fetch('library/order_print_part.lbi');
    }
    $smarty->assign('part_html', $part_html);

    /* 模板赋值 */
    //$smarty->assign('ur_here', $_LANG['order_print']);

    /* 显示模板 */
    $content = $smarty->fetch('library/order_print.lbi');
    make_json_result($content);
}

/* 获取电子面单设置列表 */
function get_order_print_setting($ru_id)
{
    /* 过滤查询 */
    $filter = array();

    $filter['keyword'] = !empty($_REQUEST['keyword']) ? trim($_REQUEST['keyword']) : '';
    if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1) {
        $filter['keyword'] = json_str_iconv($filter['keyword']);
    }

    $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'ops.sort_order' : trim($_REQUEST['sort_by']);
    $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'ASC' : trim($_REQUEST['sort_order']);

    $where = 'WHERE 1 ';

    /* 关键字 */
    if (!empty($filter['keyword'])) {
        $where .= " AND (ops.specification LIKE '%" . mysql_like_quote($filter['keyword']) . "%'" . " OR ops.printer LIKE '%" . mysql_like_quote($filter['keyword']) . "%'" . ")";
    }

    if (isset($ru_id)) {
        $where .= " AND ops.ru_id = '$ru_id' ";
    }

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('order_print_setting') . ' AS ops ' . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得数据 */
    $arr = array();
    $sql = 'SELECT ops.* FROM ' . $GLOBALS['ecs']->table('order_print_setting') . 'AS ops ' .
            $where . 'ORDER by ' . $filter['sort_by'] . ' ' . $filter['sort_order'];

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {
        $arr[] = $rows;
    }

    return array('list' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

?>
