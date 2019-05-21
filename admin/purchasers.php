<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/8
 * Time: 16:05
 */
define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
define('SUPPLIERS_ACTION_LIST', 'delivery_view,back_view');

//采购商列表
function purchasers_list($audit = '') {
    $result = get_filter();

    if ($result === false) {
        $aiax = (isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0);
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'purchasers_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'ASC' : trim($_REQUEST['sort_order']);
        $where = 'WHERE 1 ';

        $filter['audit'] = $audit;
        if ($audit !== '') {
            $where .= ' AND audit_status in (' . $audit . ')';
        }
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
        if (isset($_REQUEST['page_size']) && (0 < intval($_REQUEST['page_size']))) {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        } else {
            if (isset($_COOKIE['ECSCP']['page_size']) && (0 < intval($_COOKIE['ECSCP']['page_size']))) {
                $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
            }
            else {
                $filter['page_size'] = 15;
            }
        }

        $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('purchasers') . $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        $filter['page_count'] = 0 < $filter['record_count'] ? ceil($filter['record_count'] / $filter['page_size']) : 1;
        $sql = "SELECT purchasers_id, user_id, store_name, real_name, audit_status\r\n                FROM " . $GLOBALS['ecs']->table('purchasers') . "\r\n                " . $where . "\r\n                ORDER BY " . $filter['sort_by'] . ' ' . $filter['sort_order'] . "\r\n                LIMIT " . (($filter['page'] - 1) * $filter['page_size']) . ', ' . $filter['page_size'] . ' ';
        set_filter($filter, $sql);
    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $GLOBALS['db']->getAll($sql);
    foreach ($row  as $key => $res) {
        $row[$key]['user_name'] = $GLOBALS['db']->getOne("SELECT user_name FROM " .$GLOBALS['ecs']->table('users'). " WHERE user_id = '" .$res['user_id']. "'", true);
    }

    $arr = array('result' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
}

if ($_REQUEST['act'] == 'list') {
    admin_priv('ad_manage');
    $smarty->assign('ur_here', $_LANG['purchasers_list']);
    $result = purchasers_list('1, 2');

    $smarty->assign('purchasers_list', $result['result']);
    $smarty->assign('filter', $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count', $result['page_count']);

    $smarty->assign('menu_select', array('action' => 'purchasers_list', 'current' => '01_purchasers_list'));
    $smarty->assign('full_page', 1);
    $smarty->display('purchasers_list.dwt');
} elseif ($_REQUEST['act'] == 'audit') {
    admin_priv('ad_manage');
    $smarty->assign('ur_here', $_LANG['purchasers_list']);
    $result = purchasers_list('0');
    $smarty->assign('purchasers_list', $result['result']);
    $smarty->assign('filter', $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count', $result['page_count']);

    $smarty->assign('menu_select', array('action' => 'purchasers_list', 'current' => '01_purchasers_audit'));
    $smarty->assign('full_page', 1);
    $smarty->display('purchasers_audit_list.dwt');
}   elseif ($_REQUEST['act'] == 'query') {
    $result = purchasers_list($_REQUEST['audit']);
    $smarty->assign('purchasers_list', $result['result']);

    if ($_REQUEST['audit'] === 0) {
        make_json_result($smarty->fetch('purchasers_audit_list.dwt'), '', array('filter' => $result['filter'], 'page_count' => $result['page_count']));
    } else {
        make_json_result($smarty->fetch('purchasers_list.dwt'), '', array('filter' => $result['filter'], 'page_count' => $result['page_count']));

    }

} elseif ($_REQUEST['act'] == 'view') {
    admin_priv('ad_manage');
    $purchasers_id       = intval($_GET['id']);

    if (!$purchasers_id) {
        $lnk[] = array('text' => '返回上一步', 'href' => 'javascript:history.back(-1)');
        sys_msg('无效数据', 0, $lnk);
    }

    $sql = "select * from ".$ecs->table('purchasers')."WHERE purchasers_id = '".$purchasers_id."' LIMIT 1";
    $purchasers = $db->getRow($sql);
    if (!$purchasers) {
        $lnk[] = array('text' => '返回上一步', 'href' => 'javascript:history.back(-1)');
        sys_msg('无效数据', 0, $lnk);
    }

    //行业
    $sql = "select * from ".$ecs->table('category')."WHERE parent_id = 0";
    $categorys = $db->getAll($sql);

    //获取完美地址
    $region = get_complete_address($purchasers);

    $purchasers['region'] = $region;
    $smarty->assign('categorys', $categorys);
    $purchasers['work_file'] = unserialize($purchasers['work_file']);
    $smarty->assign('purchasers', $purchasers);
    $smarty->assign('action_link', ['href'=>'purchasers.php?act=audit']);
    $smarty->display('purchasers_view.dwt');
} elseif ($_REQUEST['act'] == 'save_audit') {
    admin_priv('ad_manage');

    $purchasers_id      = intval($_POST['id']);
    if (!$purchasers_id) {
        $lnk[] = array('text' => '返回上一步', 'href' => 'javascript:history.back(-1)');
        sys_msg('无效数据', 0, $lnk);
    }

    $sql = "select * from ".$ecs->table('purchasers')."WHERE purchasers_id = '".$purchasers_id."' LIMIT 1";
    $purchasers = $db->getRow($sql);
    if (!$purchasers) {
        $lnk[] = array('text' => '返回上一步', 'href' => 'javascript:history.back(-1)');
        sys_msg('无效数据', 0, $lnk);
    }

    $audit_status = intval($_POST[audit_status]);

    if ($audit_status == 1) {
        $notice = trim($_POST['notice']);
        if (!$notice) {
            sys_msg('请填写不通过原因', 0, $href);
        }
    } else {
        $notice = '';
    }

    if ($audit_status == 1) {
        //不通过发送通知
        $_uid = $purchasers['user_id'];
        $user_info = get_table_date('users', 'user_id=\'' . $_uid . '\'', array('mobile_phone', 'user_name'), 0);

        $smsParams = array(
            'username' => $user_info['user_name'],
            'mobile_phone' => $user_info['mobile_phone'],
            'mobilephone' => $user_info['mobile_phone'],
            'addtime' => date('Y-m-d H:i:s', gmtime()),
            'reason' => $notice
        );

        if ($GLOBALS['_CFG']['sms_type'] == 0) {
            $sms = huyi_sms($smsParams, 'purchasers_refuse');

        } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
            $result = sms_ali($smsParams, 'purchasers_refuse'); //阿里大鱼短信变量传值，发送时机传值

            if ($result) {
                $resp = $GLOBALS['ecs']->ali_yu($result);
            } else {
                sys_msg('阿里大鱼短信配置异常', 1);
            }
        }
    } elseif ($audit_status == 2) {
        $_uid = $purchasers['user_id'];
        $user_info = get_table_date('users', 'user_id=\'' . $_uid . '\'', array('mobile_phone', 'user_name'), 0);

        $smsParams = array(
            'username' => $user_info['user_name'],
            'mobile_phone' => $user_info['mobile_phone'],
            'mobilephone' => $user_info['mobile_phone'],
            'addtime' => date('Y-m-d H:i:s', gmtime())
        );

        if ($GLOBALS['_CFG']['sms_type'] == 0) {
            $sms = huyi_sms($smsParams, 'purchasers_agree');

        } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
            $result = sms_ali($smsParams, 'purchasers_agree'); //阿里大鱼短信变量传值，发送时机传值

            if ($result) {
                $resp = $GLOBALS['ecs']->ali_yu($result);
            } else {
                sys_msg('阿里大鱼短信配置异常', 1);
            }
        }
    }


    $sql = "UPDATE " .$ecs->table('purchasers'). " SET ". "audit_status = $audit_status, notice = '".$notice. "'";
    $sql .=  " WHERE purchasers_id = '$purchasers_id'";
    $db->query($sql);


    $user_id = $purchasers['user_id'];

    if ($audit_status == 2) {
        $sql2 = "UPDATE " .$ecs->table('users'). " SET ". "is_purchasers = 1";
        $sql2 .=  " WHERE user_id = '$user_id'";
        $db->query($sql2);
    } else {
        $sql2 = "UPDATE " .$ecs->table('users'). " SET ". "is_purchasers = 0";
        $sql2 .=  " WHERE user_id = '$user_id'";
        $db->query($sql2);
    }

    //管理员日志
    admin_log($purchasers['store_name'], 'save_audit', 'purchasers');
    clear_cache_files();

    /* 提示信息 */
    $href[] = array('text' => $_LANG['back_ads_list'], 'href' => 'purchasers.php?act=audit');
    sys_msg('保存成功', 0, $href);
}