<?php

/**
 * ECSHOP 阿里大鱼短信模板管理程序
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author:Hallett
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

$_REQUEST['act_name'] = 'alitongxin';
require(ROOT_PATH . '/data/sms_templates.php');

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

/* 检查权限 */
admin_priv('sms_setting');

$smarty->assign('menu_select',array('action' => '01_system', 'current' => 'alitongxin_configure'));
/*------------------------------------------------------ */
//-- 阿里大鱼短信模板列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    
    $smarty->assign('action_link',  array('text' => $_LANG['alidayu_add'], 'href'=>'alitongxin_configure.php?act=add'));
    $smarty->assign('action_link2',  array('text2' => $_LANG['alidayu_set'], 'href'=>'alitongxin_configure.php?act=set_up'));
    
    /* 模板赋值 */
    $smarty->assign('ur_here',      $_LANG['alitongxin_configure']);
    $smarty->assign('form_act',    'insert');
    
    $note_list = note_list();
	
	foreach($note_list['note_list'] as $k=>$v){
		$note_list['note_list'][$k]['send_time'] = array_search($v['send_time'],$send_time);
		$note_list['note_list'][$k]['add_time'] = local_date('Y-m-d H:i:s',$v['add_time']);
	}
	
    $smarty->assign('note_list',    $note_list['note_list']);
    $smarty->assign('filter',       $note_list['filter']);
    $smarty->assign('record_count', $note_list['record_count']);
    $smarty->assign('page_count',   $note_list['page_count']);
    $smarty->assign('full_page',    1);

    /* 列表页面 */
    assign_query_info();
    $smarty->display('alitongxin_configure_list.dwt');
}

/*------------------------------------------------------ */
//-- ajax返回短信模板列表
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    $note_list = note_list();

	foreach($note_list['note_list'] as $k=>$v){
		$note_list['note_list'][$k]['send_time'] = array_search($v['send_time'],$send_time);
		$note_list['note_list'][$k]['add_time'] = local_date('Y-m-d H:i:s',$v['add_time']);
	}	
	
    $smarty->assign('note_list',    $note_list['note_list']);
    $smarty->assign('filter',       $note_list['filter']);
    $smarty->assign('record_count', $note_list['record_count']);
    $smarty->assign('page_count',   $note_list['page_count']);
    
    $sort_flag  = sort_flag($note_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('alitongxin_configure_list.dwt'), '', array('filter' => $note_list['filter'], 'page_count' => $note_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加短信模板
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'add')
{
    $smarty->assign('action_link',  array('text' => $_LANG['alidayu_list'], 'href'=>'alitongxin_configure.php?act=list'));
    
    /* 模板赋值 */
    $smarty->assign('ur_here',      $_LANG['alitongxin_configure']);
    $smarty->assign('form_act',    'insert');
	$smarty->assign('send_time',    $send_time);

    /* 列表页面 */
    assign_query_info();
    $smarty->display('alitongxin_configure_info.dwt');
}

/*------------------------------------------------------ */
//-- 编辑短信模板
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'edit')
{
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    
    $smarty->assign('action_link',  array('text' => $_LANG['alidayu_list'], 'href'=>'alitongxin_configure.php?act=list'));
    
    $date = array('*');
    $where = "id = '$id'";
    $note_info = get_table_date('alitongxin_configure', $where, $date);
    $smarty->assign('note',    $note_info);
	$smarty->assign('send_time',    $send_time);  
	
    /* 模板赋值 */
    $smarty->assign('ur_here',      $_LANG['alitongxin_configure']);
    $smarty->assign('form_act',    'update');

    /* 列表页面 */
    assign_query_info();
    $smarty->display('alitongxin_configure_info.dwt');
}

/*------------------------------------------------------ */
//-- 添加/编辑短信模板
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert' || $_REQUEST['act'] == 'update')
{
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $signature = empty($_POST['signature']) ? 0 : intval($_POST['signature']);    
	
    $other['temp_id'] = empty($_POST['temp_id']) ? '' : trim($_POST['temp_id']);
    $other['temp_content'] = empty($_POST['temp_content']) ? '' : trim($_POST['temp_content']);
    $other['set_sign'] = empty($_POST['set_sign']) ? '' : trim($_POST['set_sign']);	
    $other['send_time'] = empty($_POST['send_time']) ? '' : trim($_POST['send_time']);
    $other['add_time'] = gmtime();
    
    if($id){
        $db->autoExecute($ecs->table('alitongxin_configure'), $other, "UPDATE", "id = '$id'");
        $href = 'alitongxin_configure.php?act=edit&id=' . $id;
        
        $lang_name = $_LANG['edit_success'];
    }else{
        $db->autoExecute($ecs->table('alitongxin_configure'), $other);
        $href = 'alitongxin_configure.php?act=list';
        $lang_name = $_LANG['add_success'];
    }
    
    /* 提示信息 */
    $link[] = array('text' => $_LANG['go_back'], 'href'=>$href);
    sys_msg(sprintf($lang_name, htmlspecialchars(stripslashes($other['temp_id']))), 0, $link);
}

/*------------------------------------------------------ */
//-- 删除短信模板
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    
    $sql = "SELECT temp_id FROM " . $GLOBALS['ecs']->table('alitongxin_configure') . " WHERE id = '$id'";
    $temp_id = $GLOBALS['db']->getOne($sql);
    
    $sql = "DELETE FROM " .$GLOBALS['ecs']->table('alitongxin_configure'). " WHERE id = '$id'";
    $GLOBALS['db']->query($sql);
    
    /* 提示信息 */
    $link[] = array('text' => $_LANG['go_back'], 'href'=>'alitongxin_configure.php?act=list');
    sys_msg(sprintf($_LANG['remove_success'], $temp_id), 0, $link);
}

/* ------------------------------------------------------ */
//-- 读取默认模板信息
/* ------------------------------------------------------ */
if ($_REQUEST['act'] == 'loat_template') {
    include_once(ROOT_PATH . 'includes/cls_json.php');
    $result = array('error' => 0, 'message' => '', 'content' => '');
    
    $id = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    $tpl = !empty($_REQUEST['tpl']) ? trim($_REQUEST['tpl']) : '';
    
    if($id){
        $dayu = get_table_date('alitongxin_configure', "id='$id'", array('temp_content'));
        $content = $dayu['temp_content'];
    }else{
        $content = $template[$tpl];
    }

    $result['content'] = $content;
    
    $result['tpl'] = $tpl;

    $json = new JSON;
    die($json->encode($result));
}

/* ------------------------------------------------------ */
//-- 读取默认模板信息
/* ------------------------------------------------------ */
if ($_REQUEST['act'] == 'sms_template') {
    include_once(ROOT_PATH . 'includes/cls_json.php');
    $res = array('error' => 0, 'message' => '', 'content' => '');
    
    $set_sign = isset($_REQUEST['set_sign']) && !empty($_REQUEST['set_sign']) ? trim($_REQUEST['set_sign']) : '';
    $temp_id = isset($_REQUEST['temp_id']) && !empty($_REQUEST['temp_id']) ? trim($_REQUEST['temp_id']) : '';
    $temp_content = isset($_REQUEST['temp_content']) && !empty($_REQUEST['temp_content']) ? trim($_REQUEST['temp_content']) : '';
    $send_time = isset($_REQUEST['send_time']) && !empty($_REQUEST['send_time']) ? trim($_REQUEST['send_time']) : '';
    $sms_type = isset($_REQUEST['sms_type']) && !empty($_REQUEST['sms_type']) ? intval($_REQUEST['sms_type']) : 0;
    
    if($GLOBALS['_CFG']['sms_shop_mobile']){
        
        $smsParams = get_test_sms_params($send_time, $test);

        if ($sms_type == 1) {
            
            $data = array('set_sign'=>$set_sign, 'temp_id'=>$temp_id, 'temp_content'=>$temp_content);
            $result = sms_ali($smsParams, $send_time, $data); //阿里大鱼短信变量传值，发送时机传值
            $resp = $GLOBALS['ecs']->ali_yu($result);
            
            if(isset($resp->code) && $resp->code == 15){
                $res['error'] = 2;  //短信模板有误
            }
        }else {

            $huyi = huyi_sms($smsParams, $send_time, $temp_content);
            
            if($huyi === false){
                $res['error'] = 2;  //短信模板有误
            }
        }

    }else{
        $res['error'] = 1;
    }
    
    $res['set_sign'] = $set_sign;
    $res['temp_id'] = $temp_id;
    $res['temp_content'] = $temp_content;
    $res['send_time'] = $send_time;

    $json = new JSON;
    die($json->encode($res));
}

/**
 *  返回sms列表数据
 *
 * @access  public
 * @param
 *
 * @return void
 */
function note_list()
{
    $result = get_filter();
    if ($result === false)
    {
        /* 过滤条件 */
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }
        
        $filter['sort_by']    = empty($_REQUEST['sort_by'])    ? 'id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC'     : trim($_REQUEST['sort_order']);
        
        $where = " WHERE 1 ";
        
        $filter['record_count'] = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('alitongxin_configure') . $where);
        /* 分页大小 */
        $filter = page_and_size($filter);
        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('alitongxin_configure') . $where .
                " ORDER by " . $filter['sort_by'] . ' ' . $filter['sort_order'] .
                " LIMIT " . $filter['start'] . ',' . $filter['page_size'];

        $filter['keywords'] = stripslashes($filter['keywords']);
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }
    
    $note_list = $GLOBALS['db']->getAll($sql);
    
    $arr = array('note_list' => $note_list, 'filter' => $filter,
        'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
} 

/**
 * 测试短信
 */
function get_test_sms_params($send_time, $test){
   
    $smsParams['mobile_phone'] = $GLOBALS['_CFG']['sms_shop_mobile'];
    
    switch ($send_time){
        case 'sms_order_placed': //客户下单时
            
            $smsParams['user_name'] = $test['user_name'];
            $smsParams['order_sn'] = $test['order_sn'];
            $smsParams['consignee'] = $test['consignee'];
            
            break;
        case 'sms_order_payed': //客户付款时
            
            $smsParams['shop_name'] = $test['shop_name'];
            $smsParams['order_sn'] = $test['order_sn'];
            $smsParams['consignee'] = $test['consignee'];
            $smsParams['order_region'] = $test['order_region'];
            $smsParams['address'] = $test['address'];
            $smsParams['order_mobile'] = $test['order_mobile'];
            
            break;
        case 'sms_order_shipped': //商家发货时
            
            $smsParams['shop_name'] = $test['shop_name'];
            $smsParams['order_sn'] = $test['order_sn'];
            $smsParams['consignee'] = $test['consignee'];
            $smsParams['user_name'] = $test['user_name'];
            
            break;
        case 'store_order_code': //门店提货码
            
            $smsParams['user_name'] = $test['user_name'];
            $smsParams['order_sn'] = $test['order_sn'];
            $smsParams['store_address'] = $test['store_address'];
            $smsParams['code'] = $test['code'];
            
            break;
        case 'sms_signin': //客户注册时
            
            $smsParams['code'] = $test['code'];
            $smsParams['product'] = $test['product'];
            
            break;
        case 'sms_find_signin': //客户密码找回时
            
            $smsParams['code'] = $test['code'];
            
            break;
        case 'sms_code': //验证码通知
            
            $smsParams['code'] = $test['code'];
            
            break;
        case 'sms_price_notic': //商品降价时
            
            $smsParams['user_name'] = $test['user_name'];
            $smsParams['goods_sn'] = $test['goods_sn'];
            
            break;
        case 'sms_seller_signin': //修改商家密码时
            
            $smsParams['seller_name'] = $test['seller_name'];
            $smsParams['login_name'] = $test['login_name'];
            $smsParams['password'] = $test['password'];
            
            break;
        default :
            $smsParams = array();
    }
    
    return $smsParams;
}
?>