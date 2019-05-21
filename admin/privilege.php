<?php

/**
 * ECSHOP 管理员信息以及权限管理程序
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author:  Hallett
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'login';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}
/* 初始化 $exc 对象 */
$exc = new exchange($ecs->table("admin_user"), $db, 'user_id', 'user_name');

$adminru = get_admin_ru_id();

//ecmoban模板堂 --zhuo start
if($adminru['ru_id'] == 0){
        $smarty->assign('priv_ru',   1);
}else{
        $smarty->assign('priv_ru',   0);
}
grade_expire();// 商家等级到期处理
$smarty->assign('seller',   1);
$php_self = get_php_self(1);
$smarty->assign('php_self',     $php_self);
//ecmoban模板堂 --zhuo end

/*------------------------------------------------------ */
//-- 退出登录
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'logout')
{
    /* 清除cookie */
    setcookie('ECSCP[admin_id]',   '', 1);
    setcookie('ECSCP[admin_pass]', '', 1);

    $sess->destroy_session();
    
    $Loaction = "privilege.php?act=logout";
    ecs_header("Location: $Loaction\n");
    exit;
}

/*------------------------------------------------------ */
//-- 登陆界面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'login')
{
    setcookie('dscActionParam', '', gmtime(), $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']); //进入登录页清空cookie信息 by wu
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
	
	$sql = "SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'admin_login_logo'";
	$admin_login_logo = strstr($GLOBALS['db']->getOne($sql),"images");
	$smarty->assign('admin_login_logo', $admin_login_logo);
    
    $dsc_token = get_dsc_token();
    $smarty->assign('dsc_token',  $dsc_token);
    
    if ((intval($_CFG['captcha']) & CAPTCHA_ADMIN) && gd_version() > 0)
    {
        $smarty->assign('gd_version', gd_version());
        $smarty->assign('random',     mt_rand());
    }

    $smarty->display('login.dwt');
}

/*------------------------------------------------------ */
//-- 验证登陆信息
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'signin')
{
    setcookie('dscActionParam', 'index|main', gmtime(), $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']); //登录后添加默认cookie信息 by wu
    setcookie("admin_type",0,gmtime(), $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    
    $_POST = get_request_filter($_POST, 1);
    
    $_POST['username'] = isset($_POST['username']) ? dsc_addslashes(trim($_POST['username'])) : '';
    $_POST['password'] = isset($_POST['password']) ? trim($_POST['password']) : '';
    $_POST['username'] = !empty($_POST['username']) ? str_replace(array("=", " "), '', $_POST['username']) : '';
	
    /* 检查验证码是否正确 */
    if (gd_version() > 0 && (intval($_CFG['captcha']) & CAPTCHA_ADMIN)) {
        require(ROOT_PATH . '/includes/cls_captcha_verify.php'); //验证码的类 TP
        /* 检查验证码是否正确 */
        $captcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';

        $verify = new Verify();
        if ($_REQUEST['type'] == 'captcha') {
            $captcha_code = $verify->check($captcha, 'admin_login', '', 'ajax');
            if (!$captcha_code) {
                die('false');
            } else {
                die('true');
            }
        }else{
            $captcha_code = $verify->check($captcha, 'admin_login');
            if (!$captcha_code) {
                sys_msg($_LANG['captcha_error'], 1);
            }
        }
    }

    /* 检查密码是否正确(验证码正确后才验证密码) */
    if($_REQUEST['type']  == 'password'){
        
        $sql="SELECT `ec_salt` FROM ". $ecs->table('admin_user') ."WHERE user_name = '" . $_POST['username']."'";
        $ec_salt =$db->getOne($sql);

        if(!empty($ec_salt))
        {
            $sql = "SELECT COUNT(*)".
                " FROM " . $ecs->table('admin_user') .
                " WHERE user_name = '" . $_POST['username']. "' AND password = '" . md5(md5($_POST['password']).$ec_salt) . "' AND ru_id = 0 "; //限制商家登录后台 by wu
        }
        else
        {
            $sql = "SELECT COUNT(*)".
                " FROM " . $ecs->table('admin_user') .
                " WHERE user_name = '" . $_POST['username']. "' AND password = '" . md5($_POST['password']) . "' AND ru_id = 0 "; //限制商家登录后台 by wu
        }

        $rs = $db->getOne($sql);
        if($rs)
            die('true');
        else
            die('false');
    }
    /*  @author-bylu  end  */

    $sql="SELECT `ec_salt` FROM ". $ecs->table('admin_user') ."WHERE user_name = '" . $_POST['username']."'";
    $ec_salt =$db->getOne($sql);
    
    if(!empty($ec_salt))
    {
         /* 检查密码是否正确 */
         $sql = "SELECT user_id, user_name, password, last_login, action_list, last_login,suppliers_id,ec_salt".
            " FROM " . $ecs->table('admin_user') .
            " WHERE user_name = '" . $_POST['username']. "' AND password = '" . md5(md5($_POST['password']).$ec_salt) . "' AND ru_id = 0 "; //限制商家登录后台 by wu
    }
    else
    {
         /* 检查密码是否正确 */
         $sql = "SELECT user_id, user_name, password, last_login, action_list, last_login,suppliers_id,ec_salt".
            " FROM " . $ecs->table('admin_user') .
            " WHERE user_name = '" . $_POST['username']. "' AND password = '" . md5($_POST['password']) . "' AND ru_id = 0 "; //限制商家登录后台 by wu
    }

    $row = $db->getRow($sql);
   
    if ($row)
    {
        // 检查是否为供货商的管理员 所属供货商是否有效
        if (!empty($row['suppliers_id']))
        {
            $supplier_is_check = suppliers_list_info(' is_check = 1 AND suppliers_id = ' . $row['suppliers_id']);
            if (empty($supplier_is_check))
            {
                sys_msg($_LANG['login_disable'], 1);
            }
        }

        // 登录成功
        set_admin_session($row['user_id'], $row['user_name'], $row['action_list'], $row['last_login']);
        $_SESSION['suppliers_id'] = $row['suppliers_id'];
        
        if (empty($row['ec_salt'])) {
            $ec_salt = rand(1, 9999);
            $new_possword = md5(md5($_POST['password']) . $ec_salt);
            $db->query("UPDATE " . $ecs->table('admin_user') .
                    " SET ec_salt='" . $ec_salt . "', password='" . $new_possword . "'" .
                    " WHERE user_id='$_SESSION[admin_id]'");
        }

        if($row['action_list'] == 'all' && empty($row['last_login']))
        {
            $_SESSION['shop_guide'] = true;
        }

        // 更新最后登录时间和IP
        $db->query("UPDATE " .$ecs->table('admin_user').
                 " SET last_login='" . gmtime() . "', last_ip='" . real_ip() . "'".
                 " WHERE user_id='$_SESSION[admin_id]'");

        
        if (!empty($_POST['remember']))
        {
            $time = gmtime() + 3600 * 24 * 7;//记住密码时间为7天 bylu;
            setcookie('ECSCP[admin_id]',   $row['user_id'],                            $time);
            setcookie('ECSCP[admin_pass]', md5($row['password'] . $_CFG['hash_code']), $time);
        }
        admin_log("", '', 'admin_login');//记录登陆日志
        // 清除进货单中过期的数据
        clear_cart();
        ecs_header("Location: ./index.php\n");

        exit;
    }
    else
    {
        sys_msg($_LANG['login_faild'], 1);
    }
}

/*------------------------------------------------------ */
//-- 管理员列表页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'list')
{
    /* 模板赋值 */
    $smarty->assign('ur_here',     $_LANG['01_admin_list']);
    $smarty->assign('action_link', array('href'=>'privilege.php?act=add', 'text' => $_LANG['admin_add']));
    $smarty->assign('full_page',   1);
    
    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);
    
    $admin_list = get_admin_userlist($adminru['ru_id']);

    $smarty->assign('admin_list',   $admin_list['list']);
    $smarty->assign('filter',       $admin_list['filter']);
    $smarty->assign('record_count', $admin_list['record_count']);
    $smarty->assign('page_count',   $admin_list['page_count']);
    /* 显示页面 */
    assign_query_info();
    $smarty->display('privilege_list.dwt');
}

/*------------------------------------------------------ */
//-- 查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    $admin_list = get_admin_userlist($adminru['ru_id']);
    
    $smarty->assign('admin_list',   $admin_list['list']);
    $smarty->assign('filter',       $admin_list['filter']);
    $smarty->assign('record_count', $admin_list['record_count']);
    $smarty->assign('page_count',   $admin_list['page_count']);
    make_json_result($smarty->fetch('privilege_list.dwt'), '', array('filter' => $admin_list['filter'], 'page_count' => $admin_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加管理员页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add')
{
    /* 检查权限 */
    admin_priv('admin_manage');

     /* 模板赋值 */
    $smarty->assign('ur_here',     $_LANG['admin_add']);
    $smarty->assign('action_link', array('href'=>'privilege.php?act=list', 'text' => $_LANG['01_admin_list']));
    $smarty->assign('form_act',    'insert');
    $smarty->assign('action',      'add');
    $smarty->assign('select_role',  get_role_list());
    $smarty->assign('role_manage',  admin_priv('role_manage', '', false));

    /* 显示页面 */
    assign_query_info();
    $smarty->display('privilege_info.dwt');
}

/*------------------------------------------------------ */
//-- 添加管理员的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert')
{
    admin_priv('admin_manage');
    
    $_POST['user_name'] = trim($_POST['user_name']);
    
    if($_POST['user_name'] == '买家' || $_POST['user_name'] == '卖家'){
        /* 提示信息 */
        $link[] = array('text' => "无效名称，不可使用", 'href'=>"privilege.php?act=modif");
        sys_msg("添加失败", 0, $link);
    }

    /* 判断管理员是否已经存在 */
    if (!empty($_POST['user_name']))
    {
        $is_only = $exc->is_only('user_name', stripslashes($_POST['user_name']));

        if (!$is_only)
        {
            sys_msg(sprintf($_LANG['user_name_exist'], stripslashes($_POST['user_name'])), 1);
        }
    }

    /* Email地址是否有重复 */
    if (!empty($_POST['email']))
    {
        $is_only = $exc->is_only('email', stripslashes($_POST['email']));

        if (!$is_only)
        {
            sys_msg(sprintf($_LANG['email_exist'], stripslashes($_POST['email'])), 1);
        }
    }

    /* 获取添加日期及密码 */
    $add_time = gmtime();
    
    $password  = md5($_POST['password']);
    $role_id = '';
    $action_list = '';
    if (!empty($_POST['select_role']))
    {
        $sql = "SELECT action_list FROM " . $ecs->table('role') . " WHERE role_id = '".$_POST['select_role']."'";
        $row = $db->getRow($sql);
        $action_list = $row['action_list'];
        $role_id = $_POST['select_role'];
    }

        $sql = "SELECT nav_list FROM " . $ecs->table('admin_user') . " WHERE action_list = 'all'";
        $row = $db->getRow($sql);

    $admin_id = get_admin_id();

    $sql = "INSERT INTO ".$ecs->table('admin_user')." (user_name, email, password, add_time, nav_list, action_list, role_id, parent_id) ".
           "VALUES ('".trim($_POST['user_name'])."', '".trim($_POST['email'])."', '$password', '$add_time', '$row[nav_list]', '$action_list', '$role_id', '$admin_id')";

    $db->query($sql);
    /* 转入权限分配列表 */
    $new_id = $db->Insert_ID();

    /*添加链接*/
    $link[0]['text'] = $_LANG['go_allot_priv'];
    $link[0]['href'] = 'privilege.php?act=allot&id='.$new_id.'&user='.$_POST['user_name'].'';

    $link[1]['text'] = $_LANG['continue_add'];
    $link[1]['href'] = 'privilege.php?act=add';

    sys_msg($_LANG['add'] . "&nbsp;" .$_POST['user_name'] . "&nbsp;" . $_LANG['action_succeed'],0, $link);

    /* 记录管理员操作 */
    admin_log($_POST['user_name'], 'add', 'privilege');
 }

/*------------------------------------------------------ */
//-- 编辑管理员信息
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit')
{
    /* 不能编辑demo这个管理员 */
    if ($_SESSION['admin_name'] == 'demo')
    {
       $link[] = array('text' => $_LANG['back_list'], 'href'=>'privilege.php?act=list');
       sys_msg($_LANG['edit_admininfo_cannot'], 0, $link);
    }

    $_REQUEST['id'] = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

    /* 查看是否有权限编辑其他管理员的信息 */
    if ($_SESSION['admin_id'] != $_REQUEST['id'])
    {
        admin_priv('admin_manage');
    }

    /* 获取管理员信息 */
    $sql = "SELECT user_id, user_name, email, password, agency_id, role_id FROM " .$ecs->table('admin_user').
           " WHERE user_id = '".$_REQUEST['id']."'";
    $user_info = $db->getRow($sql);


    /* 取得该管理员负责的办事处名称 */
    if ($user_info['agency_id'] > 0)
    {
        $sql = "SELECT agency_name FROM " . $ecs->table('agency') . " WHERE agency_id = '$user_info[agency_id]'";
        $user_info['agency_name'] = $db->getOne($sql);
    }

    /* 模板赋值 */
    $smarty->assign('ur_here',     $_LANG['admin_edit']);
    $smarty->assign('action_link', array('text' => $_LANG['01_admin_list'], 'href'=>'privilege.php?act=list'));
    $smarty->assign('user',        $user_info);

    /* 获得该管理员的权限 */
    $priv_str = $db->getOne("SELECT action_list FROM " .$ecs->table('admin_user'). " WHERE user_id = '$_GET[id]'");

    /* 如果被编辑的管理员拥有了all这个权限，将不能编辑 */
    if ($priv_str != 'all')
    {
       $smarty->assign('select_role',  get_role_list());
    }
    $smarty->assign('form_act',    'update');
    $smarty->assign('action',      'edit');
    $smarty->assign('role_manage',  admin_priv('role_manage', '', false));

    assign_query_info();
    $smarty->display('privilege_info.dwt');
} elseif ($_REQUEST['act'] == 'password') {
    /* 获取管理员信息 */
    $sql = "SELECT user_id, user_name, email, password, agency_id, role_id FROM " .$ecs->table('admin_user').
        " WHERE user_id = '".$_SESSION['admin_id']."'";
    $user_info = $db->getRow($sql);

    $smarty->assign('user',        $user_info);
    $smarty->assign('form_act',    'password_update');
    //口令
    $smarty->display('privilege_password.dwt');
}  elseif ($_REQUEST['act'] == 'sendsms') {
    require ROOT_PATH . 'includes/cls_json.php';
    $json = new JSON();
    $result = array('error' => 0, 'message' => '', 'content' => '');

    $mobile = '13808885067';
    include ROOT_PATH . 'includes/cls_sms.php';

    $code = random(6, 1);
    // 组装数据
    $smsParams = [
        'code' => $code,
        'mobile_phone' => $mobile,
        'mobilephone' => $mobile,
    ];

    if ($GLOBALS['_CFG']['sms_type'] == 0) {
        $sms = huyi_sms($smsParams, 'sms_code');

    } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {
        $_result = sms_ali($smsParams, 'sms_code'); //阿里大鱼短信变量传值，发送时机传值

        $_SESSION['sms_mobile_code'] = $code;

        if ($_result) {
            $resp = $GLOBALS['ecs']->ali_yu($_result);
        } else {
            sys_msg('阿里大鱼短信配置异常', 1);
        }
    }

    exit($json->encode($result));
} elseif ('password_update' == $_REQUEST['act']) {
    $sms_code = isset($_POST['mobile_code']) ? trim($_POST['mobile_code']) : '';

    if (!$sms_code || $sms_code != $_SESSION['sms_mobile_code']) {
        sys_msg('验证码不对');
    }

    /* 比较新密码和确认密码是否相同 */
    if ($_POST['new_password'] <> $_POST['pwd_confirm'])
    {
        $link[] = array('text' => $_LANG['go_back'], 'href'=>'javascript:history.back(-1)');
        sys_msg($_LANG['js_languages']['password_error'], 0, $link);
    }
    else
    {
        $pwd_modified = true;
    }

    $kouling = md5($_POST['new_password']);

    $admin_id = get_admin_id();

    $sql = "UPDATE " .$ecs->table('admin_user'). " SET ".
        "kouling = '$kouling' ".
        "WHERE user_id = '$admin_id'";

    $db->query($sql);

    $link[] = array('text' => strpos($g_link, 'list') ? $_LANG['back_admin_list'] : $_LANG['modif_info'], 'href'=>$g_link);
    sys_msg("$msg<script>parent.document.getElementById('header-frame').contentWindow.document.location.reload();</script>", 0, $link);

}

/*------------------------------------------------------ */
//-- 更新管理员信息
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update' || $_REQUEST['act'] == 'update_self')
{

    /* 变量初始化 */
    $admin_id    = !empty($_REQUEST['id'])        ? intval($_REQUEST['id'])      : 0;
    $admin_name  = !empty($_REQUEST['user_name']) ? trim($_REQUEST['user_name']) : '';
    $admin_email = !empty($_REQUEST['email'])     ? trim($_REQUEST['email'])     : '';
    $ec_salt=rand(1,9999);
    $password = !empty($_POST['new_password']) ? ", password = '".md5(md5(trim($_POST['new_password'])).$ec_salt)."'"    : '';
    
    if($admin_name == '买家' || $admin_name == '卖家'){
        /* 提示信息 */
        $link[] = array('text' => "无效名称，不可使用", 'href'=>"privilege.php?act=modif");
        sys_msg("编辑失败", 0, $link);
    }
    
    if ($_REQUEST['act'] == 'update')
    {
        /* 查看是否有权限编辑其他管理员的信息 */
        if ($_SESSION['admin_id'] != $_REQUEST['id'])
        {
            admin_priv('admin_manage');
        }
        $g_link = 'privilege.php?act=list';
        $nav_list = '';
    }
    else
    {
        $nav_list = !empty($_POST['nav_list'])     ? ", nav_list = '".@join(",", $_POST['nav_list'])."'" : '';
        $admin_id = $_SESSION['admin_id'];
        $g_link = 'privilege.php?act=modif';
    }
    /* 判断管理员是否已经存在 */
    if (!empty($admin_name))
    {
        $is_only = $exc->num('user_name', $admin_name, $admin_id);
        if ($is_only == 1)
        {
            sys_msg(sprintf($_LANG['user_name_exist'], stripslashes($admin_name)), 1);
        }
    }

    /* Email地址是否有重复 */
    if (!empty($admin_email))
    {
        $is_only = $exc->num('email', $admin_email, $admin_id);

        if ($is_only == 1)
        {
            sys_msg(sprintf($_LANG['email_exist'], stripslashes($admin_email)), 1);
        }
    }

    //如果要修改密码
    $pwd_modified = false;

    if (!empty($_POST['new_password']))
    {
        /* 查询旧密码并与输入的旧密码比较是否相同 */
        $sql = "SELECT password FROM ".$ecs->table('admin_user')." WHERE user_id = '$admin_id'";
        $old_password = $db->getOne($sql);
		$sql ="SELECT ec_salt FROM ".$ecs->table('admin_user')." WHERE user_id = '$admin_id'";
        $old_ec_salt= $db->getOne($sql);
        if (empty($old_ec_salt)) {
            $old_ec_password = md5($_POST['old_password']);
        } else {
            $old_ec_password = md5(md5($_POST['old_password']) . $old_ec_salt);
        }
        if ($old_password <> $old_ec_password)
        {
           $link[] = array('text' => $_LANG['go_back'], 'href'=>'javascript:history.back(-1)');
           sys_msg($_LANG['pwd_error'], 0, $link);
        }

        /* 比较新密码和确认密码是否相同 */
        if ($_POST['new_password'] <> $_POST['pwd_confirm'])
        {
           $link[] = array('text' => $_LANG['go_back'], 'href'=>'javascript:history.back(-1)');
           sys_msg($_LANG['js_languages']['password_error'], 0, $link);
        }
        else
        {
            $pwd_modified = true;
        }
    }

    $role_id = '';
    $action_list = '';
    if (!empty($_POST['select_role']))
    {
        $sql = "SELECT action_list FROM " . $ecs->table('role') . " WHERE role_id = '".$_POST['select_role']."'";
        $row = $db->getRow($sql);
        $action_list = ', action_list = \''.$row['action_list'].'\'';
        $role_id = ', role_id = '.$_POST['select_role'].' ';
    }
    
    //给商家发短信，邮件
    $sql = "SELECT ru_id FROM " . $ecs->table('admin_user') . " WHERE user_id = '$admin_id' LIMIT 1";
    $ru_id = $db->getOne($sql);

    if ($ru_id && $GLOBALS['_CFG']['sms_seller_signin'] == '1') {
        //商家名称
        $shop_name = get_shop_name($ru_id, 1);

        $sql = " SELECT mobile, seller_email FROM " . $ecs->table('seller_shopinfo') . " WHERE ru_id = '$ru_id' LIMIT 1";
        $shopinfo = $db->getRow($sql);
        
        if(empty($shopinfo['mobile'])){
            $field = get_table_file_name($GLOBALS['ecs']->table('merchants_steps_fields'), 'contactPhone');

            if ($field['bool']) {
                $sql = " SELECT contactPhone AS mobile FROM " . $ecs->table('merchants_steps_fields') . " WHERE user_id = '$ru_id' LIMIT 1";
                $stepsinfo = $db->getRow($sql);
                $shopinfo['mobile'] = $stepsinfo['mobile'];
            }
        }
        
        if(!empty($shopinfo['mobile'])){
            //短信接口参数
            $smsParams = array(
                'name' => $shop_name ? $shop_name : '',
                'seller_name' => $admin_name ? htmlspecialchars($admin_name) : '',
                'sellername' => $admin_name ? htmlspecialchars($admin_name) : '',
                'seller_password' => $_POST['new_password'] ? htmlspecialchars(trim($_POST['new_password'])) : '',
                'sellerpassword' => $_POST['new_password'] ? htmlspecialchars(trim($_POST['new_password'])) : '',
                'current_admin_name' => $current_admin_name ? $current_admin_name : '',
                'currentadminname' => $current_admin_name ? $current_admin_name : '',
                'edit_time' => local_date('Y-m-d H:i:s', gmtime()),
                'edittime' => local_date('Y-m-d H:i:s', gmtime()),
                'shop_name' => $_CFG['shop_name'],
                'shopname' => $_CFG['shop_name'],
                'mobile_phone' => $shopinfo['mobile'] ? $shopinfo['mobile'] : '',
                'mobilephone' => $shopinfo['mobile'] ? $shopinfo['mobile'] : ''
            );

            if ($GLOBALS['_CFG']['sms_type'] == 0) {
                /* 如果需要，发短信 */
                if ($adminru['ru_id'] == 0 && ($admin_name != '' || $_POST['new_password'] != '')) {
                    
                    $send_result = huyi_sms($smsParams, 'sms_seller_signin');

                    if (isset($send_result) && $send_result) {
                        $err_code = true;
                    } else {
                        $err_code = false;
                    }
                }
            } elseif ($GLOBALS['_CFG']['sms_type'] >=1) {

                $result = sms_ali($smsParams, 'sms_seller_signin'); //阿里大鱼短信变量传值，发送时机传值

                if ($result) {
                    $resp = $GLOBALS['ecs']->ali_yu($result);
                } else {
                    sys_msg('阿里大鱼短信配置异常', 1);
                }
            }
        }

        /* 发送邮件 */
        $template = get_mail_template('seller_signin');
        if ($adminru['ru_id'] == 0 && $template['template_content'] != '') {
            
            $field = get_table_file_name($GLOBALS['ecs']->table('merchants_steps_fields'), 'contactEmail');

            if ($field['bool']) {
                if (empty($shopinfo['seller_email'])) {
                    $sql = " SELECT contactEmail AS seller_email FROM " . $ecs->table('merchants_steps_fields') . " WHERE user_id = '$ru_id' LIMIT 1";
                    $stepsinfo = $db->getRow($sql);
                    $shopinfo['seller_email'] = $stepsinfo['seller_email'];
                }
            }
            
            if ($shopinfo['seller_email'] && ($admin_name != '' || $_POST['new_password'] != '')) {
                $smarty->assign('shop_name', $shop_name);
                $smarty->assign('seller_name', $admin_name);
                $smarty->assign('seller_psw', trim($_POST['new_password']));
                $smarty->assign('site_name', $_CFG['shop_name']);
                $smarty->assign('send_date', local_date($GLOBALS['_CFG']['time_format'], gmtime()));
                $content = $smarty->fetch('str:' . $template['template_content']);

                send_mail($admin_name, $shopinfo['seller_email'], $template['template_subject'], $content, $template['is_html']);
            }
        }
    }

    //更新管理员信息
    if($pwd_modified)
    {
        $sql = "UPDATE " .$ecs->table('admin_user'). " SET ".
               "user_name = '$admin_name', ".
               "email = '$admin_email', ".
               "ec_salt = '$ec_salt' ".
               $action_list.
               $role_id.
               $password.
               $nav_list.
               "WHERE user_id = '$admin_id'";
    }
    else
    {
        $sql = "UPDATE " .$ecs->table('admin_user'). " SET ".
               "user_name = '$admin_name', ".
               "email = '$admin_email' ".
               $action_list.
               $role_id.
               $nav_list.
               "WHERE user_id = '$admin_id'";
    }

    $db->query($sql);

   /* 取得当前管理员用户名 */
    $current_admin_name = $db->getOne("SELECT user_name FROM " . $ecs->table('admin_user') . " WHERE user_id = '" .$_SESSION['admin_id']. "'");
    
   /* 记录管理员操作 */
   admin_log($_POST['user_name'], 'edit', 'privilege');

   /* 如果修改了密码，则需要将session中该管理员的数据清空 */
   if ($pwd_modified && $_REQUEST['act'] == 'update_self')
   {
       $sess->delete_spec_admin_session($_SESSION['admin_id']);
       $msg = $_LANG['edit_password_succeed'];
   }
   else
   {
       $msg = $_LANG['edit_profile_succeed'];
   }
   
   /* 提示信息 */
   $link[] = array('text' => strpos($g_link, 'list') ? $_LANG['back_admin_list'] : $_LANG['modif_info'], 'href'=>$g_link);
   sys_msg("$msg<script>parent.document.getElementById('header-frame').contentWindow.document.location.reload();</script>", 0, $link);

}

/*------------------------------------------------------ */
//-- 编辑个人资料
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'modif')
{
    /* 不能编辑demo这个管理员 */
    if ($_SESSION['admin_name'] == 'demo')
    {
       $link[] = array('text' => $_LANG['back_admin_list'], 'href'=>'privilege.php?act=list');
       sys_msg($_LANG['edit_admininfo_cannot'], 0, $link);
    }

    include_once('includes/inc_menu.php');
    include_once('includes/inc_priv.php');

    /* 包含插件菜单语言项 */
    $sql = "SELECT code FROM ".$ecs->table('plugins');
    $rs = $db->query($sql);
    while ($row = $db->FetchRow($rs))
    {
        /* 取得语言项 */
        if (file_exists(ROOT_PATH.'plugins/'.$row['code'].'/languages/common_'.$_CFG['lang'].'.php'))
        {
            include_once(ROOT_PATH.'plugins/'.$row['code'].'/languages/common_'.$_CFG['lang'].'.php');
        }

        /* 插件的菜单项 */
        if (file_exists(ROOT_PATH.'plugins/'.$row['code'].'/languages/inc_menu.php'))
        {
            include_once(ROOT_PATH.'plugins/'.$row['code'].'/languages/inc_menu.php');
        }
    }

    foreach ($modules AS $key => $value)
    {
        ksort($modules[$key]);
    }
    ksort($modules);

    foreach ($modules AS $key => $val)
    {
        if (is_array($val))
        {
            foreach ($val AS $k => $v)
            {
                if (is_array($purview[$k]))
                {
                    $boole = false;
                    foreach ($purview[$k] as $action)
                    {
                         $boole = $boole || admin_priv($action, '', false);
                    }
                    if (!$boole)
                    {
                        unset($modules[$key][$k]);
                    }
                }
                elseif (! admin_priv($purview[$k], '', false))
                {
                    unset($modules[$key][$k]);
                }
            }
        }
    }

    /* 获得当前管理员数据信息 */
    $sql = "SELECT user_id, user_name, email, nav_list, ru_id ".
           "FROM " .$ecs->table('admin_user'). " WHERE user_id = '".$_SESSION['admin_id']."'";
    $user_info = $db->getRow($sql);

    /* 获取导航条 */
    $nav_arr = (trim($user_info['nav_list']) == '') ? array() : explode(",", $user_info['nav_list']);
    $nav_lst = array();
    foreach ($nav_arr AS $val)
    {
        $arr              = explode('|', $val);
        $nav_lst[$arr[1]] = $arr[0];
    }

    /* 模板赋值 */
    $smarty->assign('lang',        $_LANG);
    $smarty->assign('ur_here',     $_LANG['modif_info']);
    
    if($user_info['ru_id'] == 0){
        $smarty->assign('action_link', array('text' => $_LANG['01_admin_list'], 'href'=>'privilege.php?act=list'));
    }
    
    $smarty->assign('user',        $user_info);
    $smarty->assign('menus',       $modules);
    $smarty->assign('nav_arr',     $nav_lst);

    $smarty->assign('form_act',    'update_self');
    $smarty->assign('action',      'modif');
	
	/* 获得该管理员的权限 ecmoban模板堂 --zhuo*/
    $priv_str = $db->getOne("SELECT action_list FROM " .$ecs->table('admin_user'). " WHERE user_id = '$_GET[id]'");

    /* 如果被编辑的管理员拥有了all这个权限，将不能编辑 */
    if ($priv_str == 'all')
    {
        $smarty->assign('priv_str',      1);
    }

    /* 显示页面 */
    assign_query_info();
    $smarty->display('privilege_info.dwt');
}

/*------------------------------------------------------ */
//-- 为管理员分配权限
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'allot')
{
    include_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/' .ADMIN_PATH. '/priv_action.php');

    admin_priv('allot_priv');
    if ($_SESSION['admin_id'] == $_GET['id'])
    {
        admin_priv('all');
    }

    /* 获得该管理员的权限 */
    $priv_str = $db->getOne("SELECT action_list FROM " .$ecs->table('admin_user'). " WHERE user_id = '$_GET[id]'");

    /* 如果被编辑的管理员拥有了all这个权限，将不能编辑 */
    if ($priv_str == 'all')
    {
       $link[] = array('text' => $_LANG['back_admin_list'], 'href'=>'privilege.php?act=list');
       sys_msg($_LANG['edit_admininfo_cannot'], 0, $link);
    }
    
    //子管理员 start
    $admin_id = get_admin_id();
    $action_list = get_table_date('admin_user', "user_id='$admin_id'", array('action_list'), 2);
    $action_array = explode(',', $action_list);
    //子管理员 end

    /* 获取权限的分组数据 */
    $sql_query = "SELECT action_id, parent_id, action_code,relevance FROM " .$ecs->table('admin_action').
                 " WHERE parent_id = 0";
    $res = $db->query($sql_query);
    while ($rows = $db->FetchRow($res))
    {
        $priv_arr[$rows['action_id']] = $rows;
    }
    
    if($priv_arr){
        /* 按权限组查询底级的权限名称 */
        $sql = "SELECT action_id, parent_id, action_code,relevance FROM " .$ecs->table('admin_action').
               " WHERE parent_id " .db_create_in(array_keys($priv_arr));
        $result = $db->query($sql);
        while ($priv = $db->FetchRow($result))
        {
            //子管理员 start
            if(!empty($action_list) && $action_list != 'all' && !in_array($priv['action_code'], $action_array)){
                continue;
            }
            //子管理员 end
            $priv_arr[$priv["parent_id"]]["priv"][$priv["action_code"]] = $priv;
        }

        // 将同一组的权限使用 "," 连接起来，供JS全选 ecmoban模板堂 --zhuo
        foreach ($priv_arr AS $action_id => $action_group) {
            if (isset($action_group['priv']) && $action_group['priv']) {
                $priv = @array_keys($action_group['priv']);
                $priv_arr[$action_id]['priv_list'] = join(',', $priv);
                
                if (!empty($action_group['priv'])) {
                    foreach ($action_group['priv'] AS $key => $val) {
                        $priv_arr[$action_id]['priv'][$key]['cando'] = (strpos($priv_str, $val['action_code']) !== false || $priv_str == 'all') ? 1 : 0;
                    }
                }
            }
        }
    }

    /* 赋值 */
    $smarty->assign('lang',        $_LANG);
    $smarty->assign('ur_here',     $_LANG['allot_priv'] . ' [ '. $_GET['user'] . ' ] ');
    $smarty->assign('action_link', array('href'=>'privilege.php?act=list', 'text' => $_LANG['01_admin_list']));
    $smarty->assign('priv_arr',    $priv_arr);
    $smarty->assign('form_act',    'update_allot');
    $smarty->assign('user_id',     $_GET['id']);
    
    /* 显示页面 */
    assign_query_info();
    $smarty->display('privilege_allot.dwt');
}

/*------------------------------------------------------ */
//-- 更新管理员的权限
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update_allot')
{
    admin_priv('admin_manage');

    /* 取得当前管理员用户名 */
    $admin_name = $db->getOne("SELECT user_name FROM " .$ecs->table('admin_user'). " WHERE user_id = '$_POST[id]'");
    /* 更新管理员的权限 */
    $act_list = @join(",", $_POST['action_code']);
    $sql = "UPDATE " .$ecs->table('admin_user'). " SET action_list = '$act_list', role_id = '' ".
           "WHERE user_id = '$_POST[id]'";

    $db->query($sql);
    /* 动态更新管理员的SESSION */
    if ($_SESSION["admin_id"] == $_POST['id'])
    {
        $_SESSION["action_list"] = $act_list;
    }
    
    //----------------------------------start
    /*$server_domain = $ecs->get_domain();
    $server_ip = get_server_ip();

    $data = array(
        'user_name' => $GLOBALS['_CFG']['shop_name'],
        'user_mobile' => $GLOBALS['_CFG']['sms_shop_mobile'],
        'server_ip' => $server_ip,
        'server_domain' => $server_domain,
        'qq_chat' => $GLOBALS['_CFG']['qq'],
        'custom_key' => ''
    );

    $url = "aHR0cDovL2FwaTIuZWNtb2Jhbi5jb20vP3VybD1lY21vYmFuL3ZhbGlkYXRlL2xpY2Vuc2U=";
    $url = base64_decode($url);

    $Http = new Http();
    $sc_license = $Http->doPost($url, $data);
    $sc_license = json_decode($sc_license, true);*/
    //----------------------------------end

    /* 记录管理员操作 */
    admin_log(addslashes($admin_name), 'edit', 'privilege');

    /* 提示信息 */
    $link[] = array('text' => $_LANG['back_admin_list'], 'href'=>'privilege.php?act=list');
    sys_msg($_LANG['edit'] . "&nbsp;" . $admin_name . "&nbsp;" . $_LANG['action_succeed'], 0, $link);

}

/*------------------------------------------------------ */
//-- 删除一个管理员
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('admin_drop');

    $id = intval($_GET['id']);

    /* 获得管理员用户名 */
    $admin_name = $db->getOne('SELECT user_name FROM '.$ecs->table('admin_user')." WHERE user_id='$id'");

    /* demo这个管理员不允许删除 */
    if ($admin_name == 'demo')
    {
        make_json_error($_LANG['edit_remove_cannot']);
    }

    /* ID为1的不允许删除 */
    if ($id == 1)
    {
        make_json_error($_LANG['remove_cannot']);
    }

    /* 管理员不能删除自己 */
    if ($id == $_SESSION['admin_id'])
    {
        make_json_error($_LANG['remove_self_cannot']);
    }

    if ($exc->drop($id))
    {
        $sess->delete_spec_admin_session($id); // 删除session中该管理员的记录

        admin_log(addslashes($admin_name), 'remove', 'privilege');
        clear_cache_files();
    }

    $url = 'privilege.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);

    ecs_header("Location: $url\n");
    exit;
}

/* 获取管理员列表 */
function get_admin_userlist($ru_id)
{
    $list = array();
    $result = get_filter();
    if ($result === false) {
        /* 过滤信息 */
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $filter['role_id'] = empty($_REQUEST['role_id']) ? 0 : intval($_REQUEST['role_id']);
        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
        
        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        } else {
            $filter['page_size'] = 15;
        }
        
        $where = '';
        if ($filter['keywords'])
        {
            $where .= " AND (user_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%')";
        }
        if($filter['role_id'] > 0){
            $where .= " AND au.role_id = '" .$filter['role_id']. "' ";
        }
        
        //子管理员 start
        $admin_id = get_admin_id();
        $action_list = get_table_date('admin_user', "user_id='$admin_id'", array('action_list'), 2);
        if($action_list != 'all'){
            $admin_ids = array();
            while($admin_id){
                $sql = " SELECT user_id FROM ".$GLOBALS['ecs']->table('admin_user')." WHERE parent_id " . db_create_in($admin_id);
                $admin_id = $GLOBALS['db']->getCol($sql);
                $admin_ids = array_merge($admin_ids, $admin_id);
            }
            if($admin_ids){
                $where .= " AND user_id " . db_create_in($admin_ids);
            }else{
                $where .= " AND user_id IS NULL ";
            }
        }
        //子管理员 end
        
        //管理员查询的权限 -- 店铺查询 start
        $filter['store_search'] = empty($_REQUEST['store_search']) ? 0 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';
        
        $store_where = '';
        $store_search_where = '';
        if($filter['store_search'] !=0){
           if($ru_id == 0){ 
               
               if($_REQUEST['store_type']){
                    $store_search_where = "AND msi.shopNameSuffix = '" .$_REQUEST['store_type']. "'";
                }
               
                if($filter['store_search'] == 1){
                    $where .= " AND au.ru_id = '" .$filter['merchant_id']. "' ";
                }elseif($filter['store_search'] == 2){
                    $store_where .= " AND msi.rz_shopName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%'";
                }elseif($filter['store_search'] == 3){
                    $store_where .= " AND msi.shoprz_brandName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%' " . $store_search_where;
                }
                
                if($filter['store_search'] > 1){
                    $where .= " AND (SELECT msi.user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') .' as msi ' .  
                              " WHERE msi.user_id = au.ru_id $store_where) > 0 ";
                }
           }
        }
        //管理员查询的权限 -- 店铺查询 end
        
        /* 记录总数 */
        $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('admin_user') ." AS au " . " WHERE 1 AND ru_id = 0 $where" ;
        $record_count = $GLOBALS['db']->getOne($sql);
        
        $filter['record_count'] = $record_count;
        $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        $sql = 'SELECT user_id, user_name, au.ru_id, email, add_time, last_login, agency_id, parent_id ' .
                'FROM ' . $GLOBALS['ecs']->table('admin_user') . ' AS au '.
                'WHERE 1 AND ru_id = 0 '. $where .' ORDER BY user_id DESC ' .
                "LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
        
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }
    $list = $GLOBALS['db']->getAll($sql);

    foreach ($list AS $key=>$val)
    {
        $list[$key]['ru_name'] = get_shop_name($val['ru_id'], 1); //ecmoban模板堂 --zhuo
        $list[$key]['add_time']     = local_date($GLOBALS['_CFG']['time_format'], $val['add_time']);
        $list[$key]['last_login']   = local_date($GLOBALS['_CFG']['time_format'], $val['last_login']);
        if($val['agency_id']){
            $list[$key]['agency'] = get_agency_user($val['agency_id']);
        }
        $list[$key]['parent_name'] = get_table_date('admin_user', "user_id='$val[parent_id]'", array('user_name'), 2);
    }

    $arr = array('list' => $list, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    
    return $arr;
}

/* 清除进货单中过期的数据 */
function clear_cart()
{
    /* 取得有效的session */
    $sql = "SELECT DISTINCT session_id " .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " .
                $GLOBALS['ecs']->table('sessions') . " AS s " .
            "WHERE c.session_id = s.sesskey ";
    $valid_sess = $GLOBALS['db']->getCol($sql);

    // 删除cart中无效的数据
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE session_id NOT " . db_create_in($valid_sess);
    $GLOBALS['db']->query($sql);
	// 删除cart_combo中无效的数据 by mike
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_combo') .
            " WHERE session_id NOT " . db_create_in($valid_sess);
    $GLOBALS['db']->query($sql);
}

/* 获取角色列表 */
function get_role_list()
{
    $list = array();
    $sql  = 'SELECT role_id, role_name, action_list '.
            'FROM ' .$GLOBALS['ecs']->table('role');
    $list = $GLOBALS['db']->getAll($sql);
    return $list;
}

/* 查询管理员所属办事处 */
function get_agency_user($agency_id = 0){
    $sql = "SELECT agency_id, agency_name FROM " .$GLOBALS['ecs']->table('agency'). " WHERE agency_id = '$agency_id'";
    $res = $GLOBALS['db']->getRow($sql);
    
    return $res;
}

function random($length = 6, $numeric = 0)
{
    (PHP_VERSION < '4.2.0') && mt_srand((double) microtime() * 1000000);

    if ($numeric) {
        $hash = sprintf('%0' . $length . 'd', mt_rand(0, pow(10, $length) - 1));
    }
    else {
        $hash = '';
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789abcdefghjkmnpqrstuvwxyz';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }
    }

    return $hash;
}

?>
