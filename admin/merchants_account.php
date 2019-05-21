<?php

/**
 * ECSHOP 管理中心服务站管理
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Hallett
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require(ROOT_PATH . 'includes/lib_order.php');

//ecmoban模板堂 --zhuo start
$adminru = get_admin_ru_id();
if($adminru['ru_id'] == 0){
    $smarty->assign('priv_ru',   1);
}else{
    $smarty->assign('priv_ru',   0);
}
//ecmoban模板堂 --zhuo end

/* 检查权限 */
admin_priv('seller_account');

if(!isset($_REQUEST['act_type'])){
    $_REQUEST['act_type'] = 'detail';
}

/*------------------------------------------------------ */
//-- 账户管理
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    $store_list = get_common_store_list();
    $smarty->assign('store_list', $store_list);
    
    if(isset($_REQUEST['ru_id'])){
        $action_link = "&ru_id=" . $_REQUEST['ru_id'];
        $smarty->assign('ru_id', $_REQUEST['ru_id']);
    }
    $smarty->assign('action_link6',  array('text' => $_LANG['fund_details'], 'href'=>'merchants_account.php?act=account_log_list'));  
    $smarty->assign('action_link2',  array('text' => $_LANG['presentation_record'], 'href'=>'merchants_account.php?act=list&act_type=detail&log_type=4'.$action_link));
    $smarty->assign('action_link1',  array('text' => $_LANG['recharge_record'], 'href'=>'merchants_account.php?act=list&act_type=detail&log_type=3'.$action_link));
    $smarty->assign('action_link4',  array('text' => $_LANG['settlement_record'], 'href'=>'merchants_account.php?act=list&act_type=detail&log_type=2'.$action_link));
    $smarty->assign('action_link5',  array('text' => $_LANG['thawing_record'], 'href'=>'merchants_account.php?act=list&act_type=detail&log_type=5'.$action_link));
    $smarty->assign('action_link',  array('text' => $_LANG['05_seller_account_log'], 'href'=>'merchants_account.php?act=list&act_type=account_log'.$action_link));
    $smarty->assign('action_link3',  array('text' => $_LANG['merchant_funds_list'], 'href'=>'merchants_account.php?act=list&act_type=merchants_seller_account'.$action_link));
    $smarty->assign('full_page',    1);
    
    if ($_REQUEST['act_type'] == 'detail') {
        $log_type = isset($_REQUEST['log_type']) ? $_REQUEST['log_type'] : 4;
        $smarty->assign('ur_here', $_LANG['04_seller_detail']);
        $smarty->assign('log_type', $log_type);
        $list = get_account_log_list($adminru['ru_id'], array($log_type));

        $smarty->assign('log_list', $list['log_list']);
        $smarty->assign('filter', $list['filter']);
        $smarty->assign('record_count', $list['record_count']);
        $smarty->assign('page_count', $list['page_count']);
        $smarty->assign('act_type', 'detail');

        assign_query_info();
        $smarty->display('merchants_detail.dwt');
    } 
    
    else if ($_REQUEST['act_type'] == 'account_log') 
    {
        
        $smarty->assign('ur_here', $_LANG['05_seller_account_log']);
        $list = get_account_log_list($adminru['ru_id'], array(1, 4,5));

        $smarty->assign('log_list', $list['log_list']);
        $smarty->assign('filter', $list['filter']);
        $smarty->assign('record_count', $list['record_count']);
        $smarty->assign('page_count', $list['page_count']);
        $smarty->assign('act_type', 'account_log');

        assign_query_info();
        $smarty->display('merchants_account_log.dwt');
    }elseif($_REQUEST['act_type'] == 'merchants_seller_account'){
        $smarty->assign('ur_here', $_LANG['merchant_funds_list']);
        $list = get_merchants_seller_account();
        $list['filter']['act_type'] = $_REQUEST['act_type'];
        $smarty->assign('log_list', $list['log_list']);
        $smarty->assign('filter', $list['filter']);
        $smarty->assign('record_count', $list['record_count']);
        $smarty->assign('page_count', $list['page_count']);
        $smarty->assign('act_type', 'merchants_seller_account');

        assign_query_info();
        $smarty->display('merchants_seller_account.dwt');
    }
}

/*------------------------------------------------------ */
//-- ajax返回列表
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    if($_REQUEST['act_type'] == 'detail'){
        $log_type = isset($_REQUEST['log_type']) ? $_REQUEST['log_type'] : 4;
        $smarty->assign('log_type', $log_type);
        $list = get_account_log_list($adminru['ru_id'], array($log_type));
        $fetch = "merchants_detail";
    }
    
    else if($_REQUEST['act_type'] == 'account_log')
    {
        $list = get_account_log_list($adminru['ru_id'],array(1, 4));
        $fetch = "merchants_account_log";
    }
    else if($_REQUEST['act_type'] == 'merchants_seller_account')
    {
        $list = get_merchants_seller_account();
        $list['filter']['act_type'] = $_REQUEST['act_type'];
        $fetch = "merchants_seller_account";
    }
    
    if($_REQUEST['act_type'] == 'detail' || $_REQUEST['act_type'] == 'account_log' || $_REQUEST['act_type'] == 'merchants_seller_account')
    {
        $smarty->assign('log_list', $list['log_list']);
        $smarty->assign('filter',       $list['filter']);
        $smarty->assign('record_count', $list['record_count']);
        $smarty->assign('page_count',   $list['page_count']);

        $sort_flag  = sort_flag($list['filter']);
        $smarty->assign($sort_flag['tag'], $sort_flag['img']);

        make_json_result($smarty->fetch($fetch . '.dwt'), '', array('filter' => $list['filter'], 'page_count' => $list['page_count']));
    }
}

/*------------------------------------------------------ */
//-- 查看
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'check')
{
    $smarty->assign('action_link2',  array('text' => $_LANG['04_seller_detail'], 'href'=>'merchants_account.php?act=list&act_type=detail'));
    $smarty->assign('action_link',  array('text' => $_LANG['05_seller_account_log'], 'href'=>'merchants_account.php?act=list&act_type=account_log'));
    $smarty->assign('ur_here', $_LANG['check']);
    $log_id = isset($_REQUEST['log_id']) ? intval($_REQUEST['log_id']) : 0;
    $act_type = isset($_REQUEST['act_type']) ? addslashes($_REQUEST['act_type']) : 0;
    
    $smarty->assign('log_id',   $log_id);
    $smarty->assign('form_action',   "update_check");
    
    $log_info = get_account_log_info($log_id);
    $smarty->assign('log_info',   $log_info);
    $smarty->assign('act_type',   $act_type);
    
    if($log_info){
        $seller_shopinfo = array(
            'seller_money' => $log_info['seller_money'],
            'frozen_money' => $log_info['seller_frozen'],
        );
    }else{
        $seller_shopinfo = array();
    }
    
    $smarty->assign('seller_shopinfo', $seller_shopinfo);
    
    $users_real = get_users_real($log_info['ru_id'], 1);
    $smarty->assign('real', $users_real);
    
    assign_query_info();
    $smarty->display('merchants_log_check.dwt');
}

/*------------------------------------------------------ */
//-- 查看
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update_check')
{
    include_once(ROOT_PATH . '/includes/cls_image.php');
    $image = new cls_image($_CFG['bgcolor']);
    $log = array();
    $log_id = isset($_REQUEST['log_id']) ? intval($_REQUEST['log_id']) : 0;
    $log_reply = isset($_REQUEST['log_reply']) ? addslashes(trim($_REQUEST['log_reply'])) : 0;
    $log_status = isset($_REQUEST['log_status']) ? intval($_REQUEST['log_status']) : 0;
    $certificate_img = isset($_FILES['certificate_img']) ? $_FILES['certificate_img'] : array();
    $msg_type = 0;
    $log_info = get_account_log_info($log_id);
    
    if ($log_status > 0 && $log_status <= 2) {
        if ($log_info['log_type'] == 5) {
            
            $log_type = 5;
            
            if ($log_status == 1) {
                /* 改变商家金额 */
                $sql = " UPDATE " . $ecs->table('seller_shopinfo') . " SET seller_money = seller_money + " . $log_info['frozen_money'] . " WHERE ru_id = '" . $log_info['ru_id'] . "'";
                $db->query($sql);
                
                $handler = $_LANG['frozen_money_success'];
                
                $log = array(
                    'user_id' => $log_info['ru_id'],
                    'user_money' => $log_info['frozen_money'],
                    'change_time' => gmtime(),
                    'change_desc' => sprintf($_LANG['check_change_desc'], $_SESSION['admin_name']),
                    'change_type' => 4
                );
            } else {
                
                $handler = $_LANG['frozen_money_failure'];
                
                //商家资金变动
                log_seller_account_change($log_info['ru_id'], 0, $log_info['frozen_money']);
                
                //商家资金明细记录
                merchants_account_log($log_info['ru_id'], 0, $log_info['frozen_money'], "【" . $_SESSION['admin_name'] ."】". $_LANG['08_refuse_apply_for']);
            }
            
            $href = "merchants_account.php?act=list&act_type=account_log";
            $text = $_LANG['05_seller_account_log'];

            /* 改变商家资金记录 */
            $sql = " UPDATE " . $ecs->table('seller_account_log') . " SET is_paid = $log_status, admin_note = '$log_reply', log_type = '$log_type' WHERE log_id = '$log_id'";
            $db->query($sql);
            
        } else {
            
            if ($log_info['seller_frozen'] < $log_info['amount'] && $log_info['payment_info']['pay_code'] != 'bank') {
                $handler = $_LANG['not_sufficient_funds'];
                $msg_type = 1;
                $text = $_LANG['go_back'];
                
                if($log_info['log_type'] == 3){
                    $href = "merchants_account.php?act=check&log_id=" .$log_info['log_id']. "&act_type=detail";
                }elseif($log_info['log_type'] == 1 || $log_info['log_type'] == 4){
                    $href = "merchants_account.php?act=check&log_id=" .$log_info['log_id']. "&act_type=account_log";
                }else{
                    $href = "merchants_account.php?act=list&act_type=account_log";
                }
            } else {

                if ($certificate_img['name']) {
                    $certificate = $image->upload_image('', 'seller_account', '', 1, $certificate_img['name'], $certificate_img['type'], $certificate_img['tmp_name'], $certificate_img['error'], $certificate_img['size']);  //图片存放地址 -- data/seller_account
                }
                
                //银行转账
                if ($log_info['payment_info']['pay_code'] == 'bank') {
                    
                    /* 改变商家金额 */
                    $sql = " UPDATE " . $ecs->table('seller_shopinfo') . " SET seller_money = seller_money + " . $log_info['amount'] . " WHERE ru_id = '" . $log_info['ru_id'] . "'";
                    $db->query($sql);

                    $log_type = 3;
                    $handler = $_LANG['topup_account_ok'];
                    $href = "merchants_account.php?act=check&log_id=" .$log_id. "&act_type=detail";
                    $text = $_LANG['04_seller_detail'];
                    $log = array(
                        'user_id' => $log_info['ru_id'],
                        'user_money' => $log_info['amount'],
                        'change_time' => gmtime(),
                        'change_desc' => sprintf($_LANG['07_seller_top_up'], $_SESSION['admin_name']),
                        'change_type' => 1
                    );
                } else {
                    
                    /* 转账至前台会员余额账户 start */ 
                    if ($log_info['deposit_mode'] == 1) {
                        /* 改变会员金额 */
                        $sql = " UPDATE " . $ecs->table('users') . " SET user_money = user_money + " . $log_info['amount'] . " WHERE user_id = '" . $log_info['ru_id'] . "'";
                        $db->query($sql);
                    }
                    /* 转账至前台会员余额账户 end */ 
                    
                    /* 改变商家金额 */
                    $sql = " UPDATE " . $ecs->table('seller_shopinfo') . " SET frozen_money = frozen_money - " . $log_info['amount'] . " WHERE ru_id = '" . $log_info['ru_id'] . "'";
                    $db->query($sql);

                    $change_desc = sprintf($_LANG['06_seller_deposit'], $_SESSION['admin_name']);

                    $log_type = 4;
                    $handler = $_LANG['deposit_account_ok'];
                    $href = "merchants_account.php?act=list&act_type=account_log";
                    $text = $_LANG['05_seller_account_log'];
                    
                    /* 转账至前台会员余额账户 start */ 
                    if ($log_info['deposit_mode'] == 1) {
                        $user_account_log = array(
                            'user_id' => $log_info['ru_id'],
                            'user_money' => "+" . $log_info['amount'],
                            'change_desc' => $change_desc,
                            'process_type' => 0,
                            'payment' => '',
                            'change_time' => gmtime(),
                            'change_type' => 2,
                        );

                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('account_log'), $user_account_log, 'INSERT');
                    }
                    /* 转账至前台会员余额账户 end */ 
                    
                    $log = array(
                        'user_id' => $log_info['ru_id'],
                        'change_time' => gmtime(),
                        'change_desc' => $change_desc
                    );
                    
                    $log['frozen_money'] = "-" . $log_info['amount'];
                }
                
                $update_set = '';
                if($certificate){
                    $update_set .= ", certificate_img = '$certificate'";
                }
                
                /* 改变会员金额 */
                $sql = " UPDATE " . $ecs->table('seller_account_log') . " SET is_paid = '$log_status', $update_set admin_note = '$log_reply', log_type = '$log_type' WHERE log_id = '$log_id'";
                $db->query($sql);
            }
        }
    } else {
        $handler = $_LANG['handler_failure'];
        $msg_type = 1;
        $text = $_LANG['go_back'];
        if ($log_info['payment_info']['pay_name'] == '银行汇款/转帐') {
            $href = "merchants_account.php?act=list";
        } else {
            $href = "merchants_account.php?act=list&act_type=account_log";
        }
    }
    
    if (!empty($log)) {
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_account_log'), $log, 'INSERT');
    }
    
    $link[0] = array('href' => $href, 'text' => $text);
    sys_msg($handler, $msg_type, $link);
}
/*------------------------------------------------------ */
//-- 调节商家账户
/*------------------------------------------------------ */
elseif($_REQUEST['act'] == 'edit_seller')
{
    $smarty->assign('ur_here', $_LANG['adjust_merchant_account']);
    $smarty->assign('action_link',  array('text' => $_LANG['merchant_funds_list'], 'href'=>'merchants_account.php?act=list&act_type=merchants_seller_account'));
    
    $ru_id = isset($_REQUEST['ru_id'])  ?  intval($_REQUEST['ru_id']) : 0;
    
    $sql = "SELECT  sal.seller_money,sal.frozen_money,sal.ru_id FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS sal " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS mis ON sal.ru_id = mis.user_id  WHERE sal.ru_id = '$ru_id' " .
                " LIMIT 1 ";
    $seller_info = $db->getRow($sql);
    
    $seller_info['shop_name'] = get_shop_name($ru_id, 1);
    $seller_info['formated_seller_money'] = price_format($seller_info['seller_money'], false);
    $seller_info['formated_frozen_money'] = price_format($seller_info['frozen_money'], false);
    $smarty->assign("seller_info",$seller_info);
    
    $sc_rand = rand(1000, 9999);
    $sc_guid = sc_guid();

    $seller_account_cookie = MD5($sc_guid . "-" . $sc_rand);
    setcookie('seller_account_cookie', $seller_account_cookie, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $smarty->assign('sc_guid', $sc_guid);
    $smarty->assign('sc_rand', $sc_rand);
    
    $smarty->display("seller_account_info.dwt");
}

/*------------------------------------------------------ */
//-- 调节商家账户
/*------------------------------------------------------ */
elseif($_REQUEST['act'] == 'insert')
{
   /* 检查参数 */
    $user_id = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
    
    if ($user_id <= 0)
    {
        sys_msg('invalid param');
    }
    
    /* 提示信息 */
    $links = array(
        array('href' => 'merchants_account.php?act=account_log_list&ru_id=' . $user_id, 'text' => $_LANG['account_list']),
        array('href' => 'merchants_account.php?act=edit_seller&ru_id=' . $user_id, 'text' => $_LANG['add_account'])
    );
    
    /* 防止重复提交 start */
    $sc_rand = isset($_POST['sc_rand']) && !empty($_POST['sc_rand']) ? trim($_POST['sc_rand']) : '';
    $sc_guid = isset($_POST['sc_guid']) && !empty($_POST['sc_guid']) ? trim($_POST['sc_guid']) : '';

    $seller_account_cookie = MD5($sc_guid . "-" . $sc_rand);
    
    if (!empty($sc_guid) && !empty($sc_rand) && isset($_COOKIE['seller_account_cookie'])) {
        
        if (!empty($_COOKIE['seller_account_cookie'])) {
            if (!($_COOKIE['seller_account_cookie'] == $seller_account_cookie)) {
                sys_msg($_LANG['repeat_submit'], 0, $links);
            }
        } else {
            sys_msg($_LANG['log_account_change_no'], 0, $links);
        }
        
        $sql = "SELECT id, seller_money, frozen_money FROM".$ecs->table("seller_shopinfo")." WHERE ru_id = '$user_id' LIMIT 1";
        $seller_info = $db->getRow($sql);

        if (!$seller_info)
        {
            sys_msg($_LANG['user_not_exist']);
        }
		

        $money_status    = intval($_POST['money_status']);
        $add_sub_user_money    = floatval($_POST['add_sub_user_money']);  // 值：1（增加） 值：-1（减少）
        $add_sub_frozen_money  = floatval($_POST['add_sub_frozen_money']); // 值：1（增加） 值：-1（减少）
        $change_desc    = sub_str($_POST['change_desc'], 255, false);
        $user_money     = isset($_POST['user_money']) && !empty($_POST['user_money']) ? $add_sub_user_money * abs(floatval($_POST['user_money'])) : 0;
        $frozen_money   = isset($_POST['frozen_money']) && !empty($_POST['frozen_money']) ? $add_sub_frozen_money * abs(floatval($_POST['frozen_money'])) : 0;
		if($money_status == 0 && abs($user_money) > $seller_info['seller_money'] && $add_sub_user_money < 0){
			sys_msg("你所填的金额已超过当前余额！");
		}
		
		if($money_status == 1 && abs($frozen_money) > $seller_info['frozen_money'] && $add_sub_user_money > 0){
			sys_msg("你所填的冻结资金已超过当前余额！");
		}
		
        if ($user_money == 0 && $frozen_money == 0)
        {
            sys_msg($_LANG['no_account_change']);
        }
		

        if($money_status == 1){
            if($frozen_money > 0){
                $user_money = '-' . $frozen_money;
            }else{

                if(!empty($frozen_money) && !(strpos($frozen_money, "-") === false)){
                    $user_money = substr($frozen_money, 1);
                }
            }
        }

        if ($seller_info) {

            $user_money = get_return_money($user_money, $seller_info['seller_money']);
            $frozen_money = get_return_money($frozen_money, $seller_info['frozen_money']);

            if($money_status == 1){
                if($frozen_money == 0){
                    $user_money = 0;
                }
            }
        }

        //更新商家资金
        log_seller_account_change($user_id, $user_money, $frozen_money);

        /* 记录明细 */
        $change_desc = sprintf($_LANG['seller_change_money'], $_SESSION['admin_name']) . $change_desc;
        merchants_account_log($user_id, $user_money, $frozen_money, $change_desc, 3);
        
        //防止重复提交
        setcookie('seller_account_cookie', '', gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }
    /* 防止重复提交 end */
    
    sys_msg($_LANG['merchant_funds_list'], 0, $links);
}

/*------------------------------------------------------ */
//-- 日志列表
/*------------------------------------------------------ */
elseif($_REQUEST['act'] == 'account_log_list')
{
    $store_list = get_common_store_list();
    $smarty->assign('store_list', $store_list);
    
     $smarty->assign('ur_here', $_LANG['04_seller_detail']);
    $smarty->assign('action_link6',  array('text' => $_LANG['fund_details'], 'href'=>'merchants_account.php?act=account_log_list'));  
    $smarty->assign('action_link2',  array('text' => $_LANG['presentation_record'], 'href'=>'merchants_account.php?act=list&act_type=detail&log_type=4'));
    $smarty->assign('action_link1',  array('text' => $_LANG['recharge_record'], 'href'=>'merchants_account.php?act=list&act_type=detail&log_type=3'));
    $smarty->assign('action_link4',  array('text' => $_LANG['settlement_record'], 'href'=>'merchants_account.php?act=list&act_type=detail&log_type=2'));
    $smarty->assign('action_link5',  array('text' => $_LANG['thawing_record'], 'href'=>'merchants_account.php?act=list&act_type=detail&log_type=5'));
    $smarty->assign('action_link',  array('text' => $_LANG['05_seller_account_log'], 'href'=>'merchants_account.php?act=list&act_type=account_log'));
    $smarty->assign('action_link3',  array('text' => $_LANG['merchant_funds_list'], 'href'=>'merchants_account.php?act=list&act_type=merchants_seller_account'));
    
     $smarty->assign('full_page',    1);
    $list = get_seller_account_log();
    $smarty->assign('log_list', $list['log_list']);
    $smarty->assign('filter', $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count', $list['page_count']);
    $smarty->assign('act_type', 'account_log_list');

    assign_query_info();
    $smarty->display('account_log_list.dwt');
}
elseif($_REQUEST['act'] == 'account_query'){
    
    $list = get_seller_account_log();
    $smarty->assign('log_list', $list['log_list']);
    $smarty->assign('filter', $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count', $list['page_count']);
    $smarty->assign('act_type', 'account_log_list');

    assign_query_info();
    make_json_result($smarty->fetch('account_log_list.dwt'), '', array('filter' => $list['filter'], 'page_count' => $list['page_count']));
}

/**
 * 商家资金列表
 */
function get_merchants_seller_account() {

    $result = get_filter();
    if ($result === false) {
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'sal.ru_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $ex_where = ' WHERE mis.merchants_audit = 1 ';

        if($filter['keywords']){
            $sql = "SELECT user_id FROM".$GLOBALS['ecs']->table('users')." WHERE (user_name LIKE '%" . mysql_like_quote($filter['keywords']) ."%' OR nick_name LIKE '%" . mysql_like_quote($filter['keywords']) ."%') ";
            $user_id = $GLOBALS['db']->getAll($sql);
            if($user_id){
                $user_id = implode(',', arr_foreach($user_id));
                $ex_where .= ' AND sal.ru_id in ('.$user_id.') ';
            }
        }
        //管理员查询的权限 -- 店铺查询 start
        $filter['store_search'] = empty($_REQUEST['store_search']) ? 0 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';
        
        $store_where = '';
        $store_search_where = '';
        if ($filter['store_search'] != 0) {
            if ($ru_id == 0) {

                if ($_REQUEST['store_type']) {
                    $store_search_where = "AND mis.shopNameSuffix = '" . $_REQUEST['store_type'] . "'";
                }

                if ($filter['store_search'] == 1) {
                    $ex_where .= " AND mis.user_id = '" . $filter['merchant_id'] . "' ";
                } elseif ($filter['store_search'] == 2) {
                    $store_where .= " AND mis.rz_shopName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%'";
                } elseif ($filter['store_search'] == 3) {
                    $store_where .= " AND mis.shoprz_brandName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%' " . $store_search_where;
                }

                if ($filter['store_search'] > 1) {
                    $ex_where .= " AND mis.user_id > 0 $store_where ";
                }
            }
        }
        //管理员查询的权限 -- 店铺查询 end

        $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS sal " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS mis ON sal.ru_id = mis.user_id " .
                " $ex_where";
        
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        /* 分页大小 */
        $filter = page_and_size($filter);

        $sql = "SELECT  sal.seller_money,sal.frozen_money,sal.ru_id FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS sal " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS mis ON sal.ru_id = mis.user_id " .
                " $ex_where " .
                " ORDER BY " . $filter['sort_by'] . ' ' . $filter['sort_order'] .
                " LIMIT " . $filter['start'] . ',' . $filter['page_size'];

        $filter['keywords'] = stripslashes($filter['keywords']);
        set_filter($filter, $sql);
    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    for ($i = 0; $i < count($res); $i++) {
        $res[$i]['shop_name'] = get_shop_name($res[$i]['ru_id'], 1);
    }

    $arr = array('log_list' => $res, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}
?>