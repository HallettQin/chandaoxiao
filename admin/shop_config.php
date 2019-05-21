<?php

/**
 * ECSHOP 管理中心商店设置
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

/* 代码 */
require(dirname(__FILE__) . '/includes/init.php');

if($GLOBALS['_CFG']['certificate_id']  == '')
{
    $certi_id='error';
}
else
{
    $certi_id=$GLOBALS['_CFG']['certificate_id'];
}

$sess_id = $GLOBALS['sess']->get_session_id();

$auth = local_mktime();
$ac = md5($certi_id.'SHOPEX_SMS'.$auth);


/*------------------------------------------------------ */
//-- 列表编辑 ?act=list_edit
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list_edit')
{
    /* 检查权限 */
    admin_priv('shop_config');

    /* 可选语言 */
    $dir = opendir('../languages');
    $lang_list = array();
    while (@$file = readdir($dir))
    {
        if ($file != '.' && $file != '..' &&  $file != '.svn' && $file != '_svn' && is_dir('../languages/' .$file))
        {
            $lang_list[] = $file;
        }
    }
    @closedir($dir);

    $smarty->assign('lang_list',    $lang_list);
    $smarty->assign('ur_here',      $_LANG['01_shop_config']);
    
    $group_list = get_settings(null, array('5'));
    $smarty->assign('group_list',   $group_list);
    $smarty->assign('countries',    get_regions());

    if (strpos(strtolower($_SERVER['SERVER_SOFTWARE']), 'iis') !== false)
    {
        $rewrite_confirm = $_LANG['rewrite_confirm_iis'];
    }
    else
    {
        $rewrite_confirm = $_LANG['rewrite_confirm_apache'];
    }
    $smarty->assign('rewrite_confirm', $rewrite_confirm);

    if ($_CFG['shop_country'] > 0)
    {
        $smarty->assign('provinces', get_regions(1, $_CFG['shop_country']));
        if ($_CFG['shop_province'])
        {
            $smarty->assign('cities', get_regions(2, $_CFG['shop_province']));
        }
    }
    $smarty->assign('cfg', $_CFG);
    
    $invoice_list = get_invoice_list($_CFG['invoice_type']);
    $smarty->assign('invoice_list', $invoice_list); //发票类型及税率
    
    assign_query_info();
    $smarty->display('shop_config.dwt');
}

/*------------------------------------------------------ */
//-- 邮件服务器设置
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'mail_settings')
{
    /* 检查权限 */
    admin_priv('mail_settings');

    $arr = get_settings(array(5));

    assign_query_info();

    $smarty->assign('ur_here',      $_LANG['01_mail_settings']);
    $smarty->assign('cfg', $arr[5]['vars']);
    $smarty->display('shop_config_mail_settings.dwt');
}

/*------------------------------------------------------ */
//-- 提交   ?act=post
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'post')
{
	
    $type = empty($_POST['type']) ? '' : $_POST['type'];
    /* 检查权限 */
    admin_priv('shop_config');

    /* 允许上传的文件类型 */
    $allow_file_types = '|GIF|JPG|PNG|BMP|SWF|DOC|XLS|PPT|MID|WAV|ZIP|RAR|PDF|CHM|RM|TXT|CERT|';

    /* 保存变量值 */
    $count = count($_POST['value']);
    $arr = array();
    $sql = 'SELECT id, value FROM ' . $ecs->table('shop_config');
    $res= $db->query($sql);
    while($row = $db->fetchRow($res))
    {
        $arr[$row['id']] = $row['value'];
    }
    
    $region_info = get_region_info($_POST['value'][107]);
    if($region_info && $_POST['value'][106] != $region_info['parent_id']){
        $_POST['value'][107] = 0;
    }
	
	
    //平台同步设置手机，邮箱 by wu start  by kong 改
	$back_array = array('mail_setting', 'seller_setup', 'report_conf', 'complaint_conf', 'sms_setup', 'goods_setup');
    if (!in_array($type, $back_array)) {
		$update_arr = array(
			'mobile' => $_POST['value'][801], //手机
			'seller_email' => $_POST['value'][114], //邮箱
			'kf_qq' => $_POST['value'][109], //QQ
			'kf_ww' => $_POST['value'][110], //旺旺
			'shop_title' => $_POST['value'][102], //商店标题
			'shop_keyword' => $_POST['value'][104], //商店关键字
			'country' => $_POST['value'][105], //国家
			'province' => $_POST['value'][106], //省份
			'city' => $_POST['value'][107], //城市
			'district' => 0, //区域
			'shop_address' => $_POST['value'][108], //详细地址
			'kf_tel' => $_POST['value'][115], //客服电话
			'notice' => $_POST['value'][121], //店铺公告
		);
		foreach ($update_arr as $key => $val) {
			$sql = "UPDATE " . $GLOBALS['ecs']->table('seller_shopinfo') . " SET " . $key . " = '" . $val . "' WHERE ru_id = 0 ";
			$GLOBALS['db']->query($sql);
		}
	}
    //平台同步设置手机，邮箱 by wu end
	
    $sql = "SELECT value FROM " .$GLOBALS['ecs']->table('shop_config'). " WHERE id = 919 LIMIT 1";
    $sms_password = $GLOBALS['db']->getOne($sql);
	

    foreach ($_POST['value'] AS $key => $val)
    {
        //ecmoban模板堂 --zhuo start
        if($key == 919){
            
            if(str_len($val) != 32){
                $sms_val = md5(trim($val));
            }else{
                $sms_val = $val;
            }

            if($sms_password != $sms_val){
                $val = md5(trim($val));
            }else{
                $val = $sms_password;
            }
        }
        //ecmoban模板堂 --zhuo end
		
        if($arr[$key] != $val)
        {
            $sql = "UPDATE " . $ecs->table('shop_config') . " SET value = '" . trim($val) . "' WHERE id = '" . $key . "'";
            $db->query($sql);
        }
		
    }

    /* 处理上传文件 */
    $file_var_list = array();
    $sql = "SELECT * FROM " . $ecs->table('shop_config') . " WHERE parent_id > 0 AND type = 'file'";
    $res = $db->query($sql);
    while ($row = $db->fetchRow($res))
    {
        $file_var_list[$row['code']] = $row;
    }

    foreach ($_FILES AS $code => $file)
    {
        /* 判断用户是否选择了文件 */
        if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none'))
        {
            /* 检查上传的文件类型是否合法 */
            if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types))
            {
                sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
            }
            else
            {
                $code_store_dir = array('ecjia_qrcode', 'ectouch_qrcode', 'index_down_logo', 'site_commitment', 'user_login_logo', 'login_logo_pic', 'admin_login_logo', 'admin_logo', 'seller_login_logo', 'seller_logo', 'stores_login_logo', 'stores_logo');
                if ($code == 'shop_logo') {
                    include_once('includes/lib_template.php');
                    $info = get_template_info($_CFG['template']);

                    $file_name = str_replace('{$template}', $_CFG['template'], $file_var_list[$code]['store_dir']) . $info['logo'];
                } elseif($code == 'business_logo') {
                    include_once('includes/lib_template.php');
                    $info = get_template_info($_CFG['template']);

                    $file_name = str_replace('{$template}', $_CFG['template'], $file_var_list[$code]['store_dir']) . $info['business_logo'];
                } elseif ($code == 'watermark') {

                    $ext = !empty($file['name']) ? explode('.', $file['name']) : '';
                    $ext = !empty($ext) ? array_pop($ext) : '';

                    $file_name = $file_var_list[$code]['store_dir'] . 'watermark.' . $ext;
                    if (file_exists($file_var_list[$code]['value'])) {
                        @unlink($file_var_list[$code]['value']);
                    }
                } elseif ($code == 'wap_logo') {

                    $ext = !empty($file['name']) ? explode('.', $file['name']) : '';
                    $ext = !empty($ext) ? array_pop($ext) : '';

                    $file_name = $file_var_list[$code]['store_dir'] . 'wap_logo.' . $ext;
                    if (file_exists($file_var_list[$code]['value'])) {
                        @unlink($file_var_list[$code]['value']);
                    }
                } elseif ($code == 'two_code_logo') {

                    $ext = !empty($file['name']) ? explode('.', $file['name']) : '';
                    $ext = !empty($ext) ? array_pop($ext) : '';

                    $file_name = $file_var_list[$code]['store_dir'] . 'weixin_logo.' . $ext;
                    if (file_exists($file_var_list[$code]['value'])) {
                        @unlink($file_var_list[$code]['value']);
                    }
                } elseif (in_array($code, $code_store_dir)) {

                    $ext = !empty($file['name']) ? explode('.', $file['name']) : '';
                    $ext = !empty($ext) ? array_pop($ext) : '';

                    $file_name = ROOT_PATH . $file_var_list[$code]['store_dir'] . $code . "." . $ext;
                    if (file_exists($file_var_list[$code]['value'])) {
                        @unlink(ROOT_PATH . $file_var_list[$code]['value']);
                    }
                } else {
                    $file_name = $file_var_list[$code]['store_dir'] . $file['name'];
                }
                /* 判断是否上传成功 */
                if (move_upload_file($file['tmp_name'], $file_name))
                {
                    //去除站点目录路径
                    if(strpos($file_name, ROOT_PATH) !== false){
                        $file_name = str_replace(ROOT_PATH, '', $file_name);
                    }
                    $sql = "SELECT value FROM".$ecs->table("shop_config")." WHERE code = '$code'";
                    $olde_value = $db->getOne($sql);
                    if($file_name){
                        $oss_file_name = str_replace(array('../'), '', $file_name);
                        if($olde_value != $file_name && $olde_value != "../images/errorImg.png" && $olde_value != '' && strpos($olde_value, 'http://') === false && strpos($olde_value, 'https://') === false){
                            $oss_olde_file = str_replace(array('../'), '', $olde_value);
                            get_oss_del_file(array($oss_olde_file));
                            //做判断，判断文件是否存在，如果存在则删除
                            dsc_unlink($oss_olde_file);
                        }
                        get_oss_add_file(array($oss_file_name));
                    }
                    
                    $sql = "UPDATE " . $ecs->table('shop_config') . " SET value = '$file_name' WHERE code = '$code'";
                    $db->query($sql);
                }
                else
                {
                    sys_msg(sprintf($_LANG['msg_upload_failed'], $file['name'], $file_var_list[$code]['store_dir']));
                }
            }
        }
    }
    
    $invoice_list = get_post_invoice($_POST['invoice_type'], $_POST['invoice_rate']);
    
    /* 处理发票类型及税率 */
    if (!empty($invoice_list['type']))
    {
        
        $invoice = array(
            'type' => $invoice_list['type'],
            'rate' => $invoice_list['rate']
        );
        $sql = "UPDATE " . $ecs->table('shop_config') . " SET value = '" . serialize($invoice) . "' WHERE code = 'invoice_type'";
        $db->query($sql);
    }

    /* 记录日志 */
    admin_log('', 'edit', 'shop_config');
    
    /*手机缓存*/
    if(file_exists(ROOT_PATH . 'mobile/api/script/clear_cache.php')){
        require_once(ROOT_PATH . 'mobile/api/script/clear_cache.php');
    }
    
    /* 清除缓存 */
    clear_all_files();

    $_CFG = load_config();
    
    $shop_url = urlencode($ecs->url());

    $sql = 'SELECT id, code, value FROM ' . $ecs->table('shop_config') . " WHERE code IN('shop_name','shop_title','shop_desc','shop_keywords','shop_address','qq','ww','service_phone','msn','service_email','sms_shop_mobile','icp_number','lang', 'certi')";
    $row = $db->getAll($sql);
    $row = get_cfg_val($row);

    $shop_info = get_shop_info_content(0);
    if($shop_info['country'] && $shop_info['province'] && $shop_info['city']){
        $shop_country   = $shop_info['country'];
        $shop_province  = $shop_info['province'];
        $shop_city      = $shop_info['city'];
        $shop_address   = $shop_info['shop_address'];
    }else{
        $shop_country   = $row['shop_country'];
        $shop_province  = $row['shop_province'];
        $shop_city      = $row['shop_city'];
        $shop_address   = $row['shop_address'];
    }

    $row['qq'] = !empty($row['qq']) ? $row['qq'] : $shop_info['kf_qq'];
    $row['ww'] = !empty($row['ww']) ? $row['ww'] : $shop_info['kf_ww'];
    $row['service_email'] = !empty($row['service_email']) ? $row['service_email'] : $shop_info['seller_email'];
    $row['service_phone'] = !empty($row['service_phone']) ? $row['service_phone'] : $shop_info['kf_tel'];

    $shop_country   = $db->getOne("SELECT region_name FROM ".$ecs->table('region')." WHERE region_id='$shop_country'");
    $shop_province  = $db->getOne("SELECT region_name FROM ".$ecs->table('region')." WHERE region_id='$shop_province'");
    $shop_city      = $db->getOne("SELECT region_name FROM ".$ecs->table('region')." WHERE region_id='$shop_city'");

    $httpData = array(
                'domain'            =>  $ecs->get_domain(), //当前域名
                'url'               =>  urldecode($shop_url), //当前url
                'shop_name'         =>  $row['shop_name'],
                'shop_title'        =>  $row['shop_title'],
                'shop_desc'         =>  $row['shop_desc'],
                'shop_keywords'     =>  $row['shop_keywords'],
                'country'           =>  $shop_country,
                'province'          =>  $shop_province,
                'city'              =>  $shop_city,
                'address'           =>  $shop_address,
                'qq'                =>  $row['qq'],
                'ww'                =>  $row['ww'],
                'ym'                =>  $row['service_phone'], //客服电话
                'msn'               =>  $row['msn'],
                'email'             =>  $row['service_email'],
                'phone'             =>  $row['sms_shop_mobile'], //手机号
                'icp'               =>  $row['icp_number'],
                'version'           =>  VERSION,
                'release'           =>  RELEASE,
                'language'          =>  $_CFG['lang'],
                'php_ver'           =>  PHP_VERSION,
                'mysql_ver'         =>  $db->version(),
                'charset'           =>  EC_CHARSET
        );

    $Http = new Http();
    $Http->doPost($row['certi'], $httpData);
    write_static_cache('seller_goods_str', $httpData);
    
    $back_array = array('mail_setting', 'seller_setup', 'report_conf', 'complaint_conf', 'sms_setup', 'goods_setup');
    if (in_array($type, $back_array)) {
        
        /* 邮箱设置 */
        if ($type == 'mail_setting') 
        {
            
            $back = $_LANG['back_mail_settings'];
            $href = 'shop_config.php?act=mail_settings';
            $sys_msg = $_LANG['mail_save_success'];
        } 
        
        /* 店铺设置 */
        elseif ($type == 'seller_setup') 
        {
            
            $back = $_LANG['back_seller_settings'];
            $href = 'merchants_steps.php?act=step_up';
            $sys_msg = $_LANG['seller_save_success'];
        }
        
        /* 短信设置 */
        elseif ($type == 'sms_setup') 
        {
            
            $back = $_LANG['back_sms_settings'];
            $href = 'sms_setting.php?act=step_up';
            $sys_msg = $_LANG['sms_success'];
        } 
        
        /* 举报设置 */
        elseif ($type == 'report_conf') 
        {
            
            $back = $_LANG['report_conf'];
            $href = 'goods_report.php?act=report_conf';
            $sys_msg = $_LANG['report_conf_success'];
        } 
        
        /* 投诉设置 */
        elseif ($type == 'complaint_conf') 
        {
            
            $back = $_LANG['complain_conf'];
            $href = 'complaint.php?act=complaint_conf';
            $sys_msg = $_LANG['complain_conf_success'];
        }
		
        /* 商品设置 */
        elseif ($type == 'goods_setup') 
        {
            
            $back = $_LANG['goods_setup'];
            $href = 'goods.php?act=step_up';
            $sys_msg = $_LANG['goods_setup_success'];
        }		
    } else {
        
        $back = $_LANG['back_shop_config'];
        $href = 'shop_config.php?act=list_edit';
        $sys_msg = $_LANG['save_success'];
    }
    
    get_back_settings($back, $href, $sys_msg);
}

/*------------------------------------------------------ */
//-- 发送测试邮件
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'send_test_email')
{
    /* 检查权限 */
    check_authz_json('shop_config');

    /* 取得参数 */
    $email          = trim($_POST['email']);

    /* 更新配置 */
    $_CFG['mail_service'] = intval($_POST['mail_service']);
    $_CFG['smtp_host']    = trim($_POST['smtp_host']);
    $_CFG['smtp_port']    = trim($_POST['smtp_port']);
    $_CFG['smtp_user']    = json_str_iconv(trim($_POST['smtp_user']));
    $_CFG['smtp_pass']    = trim($_POST['smtp_pass']);
    $_CFG['smtp_mail']    = trim($_POST['reply_email']);
    $_CFG['mail_charset'] = trim($_POST['mail_charset']);

    if (send_mail('', $email, $_LANG['test_mail_title'], $_LANG['cfg_name']['email_content'], 0))
    {
        make_json_result('', $_LANG['sendemail_success'] . $email);
    }
    else
    {
        make_json_error(join("\n", $err->_message));
    }
}

/*------------------------------------------------------ */
//-- 删除上传文件
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'del')
{
    /* 检查权限 */
    check_authz_json('shop_config');

    /* 取得参数 */
    $code          = trim($_GET['code']);

    $filename = $_CFG[$code];
    
    if(isset($filename) && !empty($filename)){
        $oss_file_name = str_replace(array('../'), '', $filename);
        get_oss_del_file(array($oss_file_name));
    }
    
    //删除文件
    @unlink($filename);
    
    $sql = "UPDATE " .$GLOBALS['ecs']->table('shop_config'). " SET value = '' WHERE code = '$code'";
    $GLOBALS['db']->query($sql);

    //更新设置
    update_configure($code, '');

    /* 记录日志 */
    admin_log('', 'edit', 'shop_config');

    /* 清除缓存 */
    clear_all_files();
	
	//跳转链接
	$shop_group = get_table_date('shop_config', "code='$code'", array('shop_group'), 2);
	switch($shop_group){
		case 'goods':
			$text = $_LANG['goods_setup'];
			$href = 'goods.php?act=step_up';
			$sys_msg = $_LANG['goods_setup_success'];
			break;
		default :
			$text = $_LANG['back_shop_config'];
			$href = 'shop_config.php?act=list_edit';
			$sys_msg = $_LANG['save_success'];
	}

    $links[] = array('text' => $text, 'href' => $href);
    sys_msg($sys_msg, 0, $links);
}

/**
 * 设置系统设置
 *
 * @param   string  $key
 * @param   string  $val
 *
 * @return  boolean
 */
function update_configure($key, $val='')
{
    if (!empty($key))
    {
        $sql = "UPDATE " . $GLOBALS['ecs']->table('shop_config') . " SET value='$val' WHERE code='$key'";

        return $GLOBALS['db']->query($sql);
    }

    return true;
}

/**
 * 获得设置信息
 *
 * @param   array   $groups     需要获得的设置组
 * @param   array   $excludes   不需要获得的设置组
 *
 * @return  array
 */
function get_settings($groups = null, $excludes = null) {
    global $db, $ecs, $_LANG;

    $config_groups = '';
    $excludes_groups = '';

    if (!empty($groups)) {
        foreach ($groups AS $key => $val) {
            $config_groups .= " AND (id='$val' OR parent_id='$val')";
        }
    }

    if (!empty($excludes)) {
        foreach ($excludes AS $key => $val) {
            $excludes_groups .= " AND (parent_id<>'$val' AND id<>'$val')";
        }
    }
    
    /**
     * 不显示的内容
     */
    $shop_group = array('seller', 'complaint_conf', 'report_conf', 'sms', 'goods');
    $shop_group = db_create_in($shop_group, 'shop_group', 'not');
    $where = " AND $shop_group";
    
    /* 取出全部数据：分组和变量 */
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('shop_config') .
            " WHERE type<>'hidden' $config_groups $excludes_groups $where ORDER BY parent_id, sort_order, id";
    $item_list = $GLOBALS['db']->getAll($sql);

    /* 整理数据 */
    $filter_item = array('sms', 'hidden', 'goods');
    $group_list = array();
    $code_arr = array('shop_logo', 'no_picture', 'watermark', 'shop_slagon', 'wap_logo', 'two_code_logo', 'ectouch_qrcode', 'ecjia_qrcode', 'index_down_logo', 'site_commitment', 'user_login_logo', 'login_logo_pic', 'business_logo', 'admin_login_logo', 'admin_logo', 'seller_login_logo', 'seller_logo', 'stores_login_logo', 'stores_logo');
    foreach ($item_list AS $key => $item) {
        
        if (!in_array($item['code'], $filter_item)) {
            $pid = $item['parent_id'];
            $item['name'] = isset($_LANG['cfg_name'][$item['code']]) ? $_LANG['cfg_name'][$item['code']] : $item['code'];
            $item['desc'] = isset($_LANG['cfg_desc'][$item['code']]) ? $_LANG['cfg_desc'][$item['code']] : '';

            if ($item['code'] == 'sms_shop_mobile') {
                $item['url'] = 1;
            }
            if ($pid == 0) {
                /* 分组 */
                if ($item['type'] == 'group') {
                    $group_list[$item['id']] = $item;
                }
            } else {
                /* 变量 */
                if (isset($group_list[$pid])) {
                    if ($item['store_range']) {
                        $item['store_options'] = explode(',', $item['store_range']);

                        foreach ($item['store_options'] AS $k => $v) {
                            $item['display_options'][$k] = isset($_LANG['cfg_range'][$item['code']][$v]) ?
                                    $_LANG['cfg_range'][$item['code']][$v] : $v;
                        }
                    }

                    if ($item) {
                        if ($item['type'] == 'file' && in_array($item['code'], $code_arr) && $item['value']) {
                            $item['del_img'] = 1;

                            if (strpos($item['value'], '../') === false) {
                                $item['value'] = "../" . $item['value'];
                            }
                        } else {
                            $item['del_img'] = 0;
                        }
                    }

                    $group_list[$pid]['vars'][] = $item;
                }
            }
        }
    }

    return $group_list;
}

//过滤提交票税信息
function get_post_invoice($type, $rate){
    
    if($type){
        for($i=0; $i<count($type); $i++){
            if(empty($type[$i]) && empty($rate[$i])){
                unset($type[$i]);
                unset($rate[$i]);
            }else{
                $rate[$i] = round(floatval($rate[$i]), 2);
            }
        }
    }else{
        $type = array();
        $rate = array();
    }
    
    $arr = array('type' => $type, 'rate' => $rate);
    return $arr;
}

/* 基本信息设置返回 */
function get_back_settings($text, $href, $sys_msg){ 
    $links[] = array('text' => $text, 'href' => $href);
    sys_msg($sys_msg, 0, $links);
}

?>