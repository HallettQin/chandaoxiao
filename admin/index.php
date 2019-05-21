<?php

/**
 * ECSHOP 控制台首页
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
require_once(ROOT_PATH . '/includes/lib_order.php');

include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);

$adminru = get_admin_ru_id();
$smarty->assign('ru_id', $adminru['ru_id']);

if($adminru['ru_id'] == 0){
        $smarty->assign('priv_ru',   1);
}else{
        $smarty->assign('priv_ru',   0);
}

//商家单个权限 begin
$user_action_list = get_user_action_list($_SESSION['admin_id']);

$index_sales_volume = get_merchants_permissions($user_action_list, 'index_sales_volume');
$smarty->assign('index_sales_volume', $index_sales_volume); //今日销量

$index_today_order = get_merchants_permissions($user_action_list, 'index_today_order');
$smarty->assign('index_today_order', $index_today_order); //今日订单数

$index_today_comment = get_merchants_permissions($user_action_list, 'index_today_comment');
$smarty->assign('index_today_comment', $index_today_comment); //今日评论

$index_seller_num = get_merchants_permissions($user_action_list, 'index_seller_num');
$smarty->assign('index_seller_num', $index_seller_num); //店铺销量

$index_order_status = get_merchants_permissions($user_action_list, 'index_order_status');
$smarty->assign('index_order_status', $index_order_status); //订单状态

$index_order_stats = get_merchants_permissions($user_action_list, 'index_order_stats');
$smarty->assign('index_order_stats', $index_order_stats); //订单统计

$index_sales_stats = get_merchants_permissions($user_action_list, 'index_sales_stats');
$smarty->assign('index_sales_stats', $index_sales_stats); //销量统计

$index_member_info = get_merchants_permissions($user_action_list, 'index_member_info');
$smarty->assign('index_member_info', $index_member_info); //会员信息

$index_goods_view = get_merchants_permissions($user_action_list, 'index_goods_view');
$smarty->assign('index_goods_view', $index_goods_view); //商品一览

$index_control_panel = get_merchants_permissions($user_action_list, 'index_control_panel');
$smarty->assign('index_control_panel', $index_control_panel); //控制面板

$index_system_info = get_merchants_permissions($user_action_list, 'index_system_info');
$smarty->assign('index_system_info', $index_system_info); //系统信息
//商家单个权限 end

$data = read_static_cache('main_user_str');
if ($data === false) {
    $smarty->assign('is_false',    '1');
}else{
    $smarty->assign('is_false',    '0');
}

$data = read_static_cache('seller_goods_str');
if ($data === false) {
    $smarty->assign('goods_false',    '1');
}else{
    $smarty->assign('goods_false',    '0');
}

/* ------------------------------------------------------ */
//-- 框架
/* ------------------------------------------------------ */
if ($_REQUEST['act'] == '') {
    //ecmoban模板堂 --zhuo satrt
    include_once('includes/inc_menu.php');

    // 权限对照表
    include_once('includes/inc_priv.php');

    foreach ($modules AS $key => $value) {
        ksort($modules[$key]);
    }
    ksort($modules);
    foreach ($menu_top as $mkey => $mval) {
        $menus = array();
        $menu_type = '';
        $nav_top[$mkey]['label'] = $_LANG[$mkey];
        $nav_top[$mkey]['type'] = $mkey;
        if (!empty($mval)) {
            $menu_type = explode(',', $mval);
            foreach ($modules AS $key => $val) {
                if (in_array($key, $menu_type)) {

                    $menus[$key]['menuleft'] = $mkey;
                    $menus[$key]['label'] = $_LANG[$key];
                    if ($menus[$key]['menuleft'] == $mkey) {
                        if (is_array($val)) {
                            foreach ($val AS $k => $v) {
                                if (isset($purview[$k])) {
                                    if (is_array($purview[$k])) {
                                        $boole = false;
                                        foreach ($purview[$k] as $action) {
                                            $boole = $boole || admin_priv($action, '', false);
                                        }
                                        if (!$boole) {
                                            continue;
                                        }
                                    } else {
                                        if (!admin_priv($purview[$k], '', false)) {
                                            continue;
                                        }
                                    }
                                }
                                if ($k == 'ucenter_setup' && $_CFG['integrate_code'] != 'ucenter') {
                                    continue;
                                }
                                $menus[$key]['children'][$k]['label'] = $_LANG[$k];
                                $menus[$key]['children'][$k]['action'] = $v;
                            }
                        } else {
                            $menus[$key]['action'] = $val;
                        }
                    }

                    // 如果children的子元素长度为0则删除该组
                    if (empty($menus[$key]['children'])) {
                        unset($menus[$key]);
                    }
                    $nav_top[$mkey]['children'] = $menus;
                }
            }
        }
    }
    if ($adminru['ru_id'] == 0) {
        $smarty->assign('priv_ru', 1);
    } else {
        $shop_name = get_shop_name($adminru['ru_id'], 1);
        $smarty->assign('shop_name', $shop_name);

        $smarty->assign('priv_ru', 0);
    }

    $smarty->assign('nav_top', $nav_top);

    /*  @author-bylu 获取管理员信息 start  */
    $admin_id = intval($_SESSION['admin_id']);
    $sql = "SELECT u.user_name,u.last_login,u.last_ip,u.admin_user_img,r.role_name FROM " . $ecs->table('admin_user') . " u
            LEFT JOIN " . $ecs->table('role') . " r ON u.role_id = r.role_id WHERE u.user_id = '" . $admin_id . "'";
    $admin_info = $db->getRow($sql);
    $admin_info['last_login'] = local_date('Y-m-d H:i:s', $admin_info['last_login']);
    $smarty->assign('admin_info', $admin_info);

    //快捷菜单
    $auth_menu = substr($_COOKIE['auth_menu'], 0, -1);
    $auth_menu = array_filter(explode(',', $auth_menu));
    foreach ($auth_menu as $k => $v) {
        $auth_menu[$k] = explode('|', $v);
    }

	//logo设置
	$sql = "SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'admin_logo'";
	$admin_logo = strstr($GLOBALS['db']->getOne($sql),"images");
	$smarty->assign('admin_logo', $admin_logo);

    $smarty->assign('auth_menu', $auth_menu);
    /*  @author-bylu  end  */

    $smarty->assign('shop_url', urlencode($ecs->url()));
    $smarty->display('index.dwt');
}
/* ------------------------------------------------------ */
//-- 顶部框架的内容
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'top') {
    // 获得管理员设置的菜单
    $lst = array();
    $nav = $db->GetOne('SELECT nav_list FROM ' . $ecs->table('admin_user') . " WHERE user_id = '" . $_SESSION['admin_id'] . "'");

    if (!empty($nav)) {
        $arr = explode(',', $nav);

        foreach ($arr AS $val) {
            $tmp = explode('|', $val);
            $lst[$tmp[1]] = $tmp[0];
        }
    }

    // 获得管理员设置的菜单
    // 获得管理员ID
    $smarty->assign('send_mail_on', $_CFG['send_mail_on']);
    $smarty->assign('nav_list', $lst);
    $smarty->assign('admin_id', $_SESSION['admin_id']);
    $smarty->assign('certi', $_CFG['certi']);

    $smarty->display('top.dwt');
}

/* ------------------------------------------------------ */
//-- 计算器
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'calculator') {
    $smarty->display('calculator.dwt');
}

/* ------------------------------------------------------ */
//-- 左边的框架
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'menu') {
    include_once('includes/inc_menu.php');

// 权限对照表
    include_once('includes/inc_priv.php');

    foreach ($modules AS $key => $value) {
        ksort($modules[$key]);
    }
    ksort($modules);
   foreach($menu_top as $mkey =>$mval){
    $menus = array();
    $menu_type = '';
    $nav_top[$mkey]['label'] = $_LANG[$mkey];
    $nav_top[$mkey]['type'] = $mkey;
    if(!empty($mval)){
        $menu_type = explode(',', $mval);
        foreach ($modules AS $key => $val) {
        if(in_array($key, $menu_type)){

                    $menus[$key]['menuleft'] = $mkey;
                    $menus[$key]['label'] = $_LANG[$key];
                    if($menus[$key]['menuleft'] == $mkey){
                        if (is_array($val)) {
                            foreach ($val AS $k => $v) {
                                if (isset($purview[$k])) {
                                    if (is_array($purview[$k])) {
                                        $boole = false;
                                        foreach ($purview[$k] as $action) {
                                            $boole = $boole || admin_priv($action, '', false);
                                        }
                                        if (!$boole) {
                                            continue;
                                        }
                                    } else {
                                        if (!admin_priv($purview[$k], '', false)) {
                                            continue;
                                        }
                                    }
                                }
                                if ($k == 'ucenter_setup' && $_CFG['integrate_code'] != 'ucenter') {
                                    continue;
                                }
                                $menus[$key]['children'][$k]['label'] = $_LANG[$k];
                                $menus[$key]['children'][$k]['action'] = $v;
                            }
                        } else {
                            $menus[$key]['action'] = $val;
                        }
                    }

                    // 如果children的子元素长度为0则删除该组
                    if (empty($menus[$key]['children'])) {
                        unset($menus[$key]);
                    }
                    $nav_top[$mkey]['children'] = $menus;

                }
        }
    }

}
    $smarty->assign('nav_top', $nav_top);
//    $smarty->assign('menus', $menus);
    $smarty->assign('no_help', $_LANG['no_help']);
    $smarty->assign('help_lang', $_CFG['lang']);
    $smarty->assign('charset', EC_CHARSET);
    $smarty->assign('admin_id', $_SESSION['admin_id']);
    $smarty->display('menu.dwt');
}


/* ------------------------------------------------------ */
//-- 清除缓存
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'clear_cache') {

    if(file_exists(ROOT_PATH . 'mobile/storage/clean.php')){
        require_once(ROOT_PATH . 'mobile/storage/clean.php');
    }

    $smarty->assign('ur_here', $_LANG['09_clear_cache']);
    $smarty->assign('form_act','set_clear_cache');
    $smarty->display('clear_cache.dwt');
}

elseif($_REQUEST['act'] == 'set_clear_cache')
{

    $sql = "UPDATE " . $GLOBALS['ecs']->table('shop_config') . " SET value = 0 WHERE code = 'is_downconfig'";
    $GLOBALS['db']->query($sql);

    $chkGroup = isset($_REQUEST['chkGroup']) ? addslashes($_REQUEST['chkGroup']) : '';
    $sessGroup = isset($_REQUEST['sessGroup']) ? addslashes($_REQUEST['sessGroup']) : '';
    $action_code = !empty($_REQUEST['action_code']) ? $_REQUEST['action_code'] : '';
    if($chkGroup == 'all' || $sessGroup == 'all')
    {
        if($chkGroup == 'all'){
            clear_all_files();
            clear_all_files('',SELLER_PATH);
            clear_all_files('',STORES_PATH);
        }

        if($sessGroup == 'all'){
            clear_sessions();
        }
        if(file_exists(ROOT_PATH . 'mobile/storage/clean.php')){
            require_once(ROOT_PATH . 'mobile/storage/clean.php');
        }
        sys_msg($_LANG['caches_cleared']);
    }
    elseif($action_code)
    {
        foreach($action_code as $k=>$v)
        {
            $arr = array();
            //商城配置
            if($v == 'shop_config'){
                dsc_unlink(ROOT_PATH . "temp/static_caches/shop_config.php");
            }
            if($v == 'category'){
                $arr = array('category_tree_child', 'category_tree_brands','category_topic','cat_top_cache','cat_parent_grade','parent_style_brands','art_cat_pid_releate');
                $dirName = ROOT_PATH ."temp/static_caches";
                set_clear_cache($dirName,$arr);
            }
            if($v == 'floor'){
                $arr = array('index_goods_cat', 'index_goods_cat_cache','floor_cat_conten');
                $dirName = ROOT_PATH ."temp/static_caches";
                set_clear_cache($dirName,$arr);
            }
            if($v == 'platform_temp'){
                clear_all_files();
            }
            if($v == 'seller_temp'){
                clear_all_files('',SELLER_PATH);
            }
            if($v == 'stores_temp'){
                clear_all_files('',STORES_PATH);
            }
            if($v == 'reception'){
                $dirName = ROOT_PATH . "temp/compiled";
                set_clear_cache($dirName);
            }
            if($v == 'sessions'){
                clear_sessions(1);
            }
            if($v == 'stats'){
                clear_sessions(2);
            }
            if($v == 'other'){
                $arr = array('shop_config','category_tree_child', 'category_tree_brands','category_topic','cat_top_cache','cat_parent_grade','parent_style_brands','art_cat_pid_releate','index_goods_cat', 'index_goods_cat_cache','floor_cat_conten');
                $dirName = ROOT_PATH ."temp/static_caches";
                set_clear_cache($dirName,$arr,1);
            }
        }
        sys_msg($_LANG['caches_cleared']);
    }
    else
    {
        sys_msg('请选择清除目标');
    }
}

/* ------------------------------------------------------ */
//-- 设置主页面统计图表
/* ------------------------------------------------------ */
 elseif ($_REQUEST['act'] == 'set_statistical_chart') {
    $type = empty($_REQUEST['type']) ? '' : trim($_REQUEST['type']);
    $date = empty($_REQUEST['date']) ? '' : trim($_REQUEST['date']);

    //格林威治时间与本地时间差
    $timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : $GLOBALS['_CFG']['timezone'];
    $time_diff = $timezone * 3600;

    //初始数据
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $data = array();

    if ($date == 'week') {
        $day_num = 7;
    }
    if ($date == 'month') {
        $day_num = 30;
    }
    if ($date == 'year') {
        $day_num = 180;
    }

    $date_end = local_mktime(0, 0, 0, local_date('m'), local_date('d') + 1, local_date('Y')) - 1;
    $date_start = $date_end - 3600 * 24 * $day_num;

    //获取系统数据 start
    $no_main_order = " AND (SELECT count(*) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi2 WHERE oi2.main_order_id = oi.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示    if ($adminru['ru_id'] > 0) {
    if ($adminru['ru_id'] > 0) {
        $where_date .= " AND (SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og WHERE oi.order_id = og.order_id LIMIT 1) = '" . $adminru['ru_id'] . "'";  //主订单下有子订单时，则主订单不显示
    }
    $sql = 'SELECT DATE_FORMAT(FROM_UNIXTIME(oi.add_time + '.$time_diff.'),"%y-%m-%d") AS day,COUNT(*) AS count,SUM(oi.money_paid) AS money, SUM(oi.money_paid)+SUM(oi.surplus) AS superman FROM ' . $ecs->table('order_info') . " AS oi" . ' WHERE oi.add_time BETWEEN ' . $date_start . ' AND ' . $date_end . $no_main_order . $where_date . ' AND oi.supplier_id = 0 GROUP BY day ORDER BY day ASC ';
    $result = $db->query($sql);

    while ($row = $GLOBALS['db']->fetchRow($result)) {
        $orders_series_data[$row['day']] = intval($row['count']);
        $sales_series_data[$row['day']] = floatval($row['money']);
        $sales_series_data[$row['day']] = floatval($row['superman']);
    }

    for ($i = 1; $i <= $day_num; $i++) {
        $day = local_date("y-m-d", local_strtotime(" - " . ($day_num - $i) . " days"));
        if (empty($orders_series_data[$day])) {
            $orders_series_data[$day] = 0;
            $sales_series_data[$day] = 0;
        }
        //输出时间
        $day = local_date("m-d", local_strtotime($day));
        $orders_xAxis_data[] = $day;
        $sales_xAxis_data[] = $day;
    }

    //获取系统数据 end
    //图表公共数据 start
    $toolbox = array(
        'show' => true,
        'orient' => 'vertical',
        'x' => 'right',
        'y' => '60',
        'feature' => array(
            'magicType' => array(
                'show' => true,
                'type' => array('line', 'bar')
            ),
            'saveAsImage' => array(
                'show' => true
            )
        )
    );
    $tooltip = array('trigger' => 'axis',
        'axisPointer' => array(
            'lineStyle' => array(
                'color' => '#6cbd40'
            )
        )
    );
    $xAxis = array(
        'type' => 'category',
        'boundaryGap' => false,
        'axisLine' => array(
            'lineStyle' => array(
                'color' => '#ccc',
                'width' => 0
            )
        ),
        'data' => array());
    $yAxis = array(
        'type' => 'value',
        'axisLine' => array(
            'lineStyle' => array(
                'color' => '#ccc',
                'width' => 0
            )
        ),
        'axisLabel' => array(
            'formatter' => ''));
    $series = array(
        array(
            'name' => '',
            'type' => 'line',
            'itemStyle' => array(
                'normal' => array(
                    'color' => '#6cbd40',
                    'lineStyle' => array(
                        'color' => '#6cbd40'
                    )
                )
            ),
            'data' => array(),
            'markPoint' => array(
                'itemStyle' => array(
                    'normal' => array(
                        'color' => '#6cbd40'
                    )
                ),
                'data' => array(
                    array(
                        'type' => 'max',
                        'name' => '最大值'),
                    array(
                        'type' => 'min',
                        'name' => '最小值')
				)
			)
		),
		array(
			'type' => 'force',
			'name' => '',
			'draggable' => false,
			'nodes' => array(
				'draggable' => false
			)
		)
	);
    $calculable = true;
    $legend = array('data' => array());
    //图表公共数据 end
    //订单统计
    if ($type == 'order') {
        $xAxis['data'] = $orders_xAxis_data;
        $yAxis['formatter'] = '{value}个';
        ksort($orders_series_data);
        $series[0]['name'] = '订单个数';
        $series[0]['data'] = array_values($orders_series_data);
        $data['series'] = $series;
    }

    //销售统计
    if ($type == 'sale') {
        $xAxis['data'] = $sales_xAxis_data;
        $yAxis['formatter'] = '{value}元';
        ksort($sales_series_data);
        $series[0]['name'] = '销售额';
        $series[0]['data'] = array_values($sales_series_data);
        $data['series'] = $series;
    }

    //整理数据
    $data['tooltip'] = $tooltip;
    $data['legend'] = $legend;
    $data['toolbox'] = $toolbox;
    $data['calculable'] = $calculable;
    $data['xAxis'] = $xAxis;
    $data['yAxis'] = $yAxis;

    $data['xy_file'] = get_dir_file_list();

    //输出数据
    die(json_encode($data));
}

/* ------------------------------------------------------ */
//-- 主窗口，起始页
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'main') {

    //开店向导第一步
    if (isset($_SESSION['shop_guide']) && $_SESSION['shop_guide'] === true) {
        unset($_SESSION['shop_guide']); //销毁session

        ecs_header("Location: ./index.php?act=first\n");

        exit();
    }

    $gd = gd_version();

    /* 检查文件目录属性 */
    $warning = array();

    if ($_CFG['shop_closed']) {
        $warning[] = $_LANG['shop_closed_tips'];
    }

    if (file_exists('../install')) {
        $warning[] = $_LANG['remove_install'];
    }

    if (file_exists('../upgrade')) {
        $warning[] = $_LANG['remove_upgrade'];
    }

    if (file_exists('../demo')) {
        $warning[] = $_LANG['remove_demo'];
    }

    $open_basedir = ini_get('open_basedir');
    if (!empty($open_basedir)) {
        /* 如果 open_basedir 不为空，则检查是否包含了 upload_tmp_dir  */
        $open_basedir = str_replace(array("\\", "\\\\"), array("/", "/"), $open_basedir);
        $upload_tmp_dir = ini_get('upload_tmp_dir');

        if (empty($upload_tmp_dir)) {
            if (stristr(PHP_OS, 'win')) {
                $upload_tmp_dir = getenv('TEMP') ? getenv('TEMP') : getenv('TMP');
                $upload_tmp_dir = str_replace(array("\\", "\\\\"), array("/", "/"), $upload_tmp_dir);
            } else {
                $upload_tmp_dir = getenv('TMPDIR') === false ? '/tmp' : getenv('TMPDIR');
            }
        }

        if (!stristr($open_basedir, $upload_tmp_dir)) {
            $warning[] = sprintf($_LANG['temp_dir_cannt_read'], $upload_tmp_dir);
        }
    }

    $result = file_mode_info('../cert');
    if ($result < 2) {
        $warning[] = sprintf($_LANG['not_writable'], 'cert', $_LANG['cert_cannt_write']);
    }

    $result = file_mode_info('../' . DATA_DIR);
    if ($result < 2) {
        $warning[] = sprintf($_LANG['not_writable'], 'data', $_LANG['data_cannt_write']);
    } else {
        $result = file_mode_info('../' . DATA_DIR . '/afficheimg');
        if ($result < 2) {
            $warning[] = sprintf($_LANG['not_writable'], DATA_DIR . '/afficheimg', $_LANG['afficheimg_cannt_write']);
        }

        $result = file_mode_info('../' . DATA_DIR . '/brandlogo');
        if ($result < 2) {
            $warning[] = sprintf($_LANG['not_writable'], DATA_DIR . '/brandlogo', $_LANG['brandlogo_cannt_write']);
        }

        $result = file_mode_info('../' . DATA_DIR . '/cardimg');
        if ($result < 2) {
            $warning[] = sprintf($_LANG['not_writable'], DATA_DIR . '/cardimg', $_LANG['cardimg_cannt_write']);
        }

        $result = file_mode_info('../' . DATA_DIR . '/feedbackimg');
        if ($result < 2) {
            $warning[] = sprintf($_LANG['not_writable'], DATA_DIR . '/feedbackimg', $_LANG['feedbackimg_cannt_write']);
        }

        $result = file_mode_info('../' . DATA_DIR . '/packimg');
        if ($result < 2) {
            $warning[] = sprintf($_LANG['not_writable'], DATA_DIR . '/packimg', $_LANG['packimg_cannt_write']);
        }
    }

    $result = file_mode_info('../images');
    if ($result < 2) {
        $warning[] = sprintf($_LANG['not_writable'], 'images', $_LANG['images_cannt_write']);
    } else {
        $result = file_mode_info('../' . IMAGE_DIR . '/upload');
        if ($result < 2) {
            $warning[] = sprintf($_LANG['not_writable'], IMAGE_DIR . '/upload', $_LANG['imagesupload_cannt_write']);
        }
    }

    $result = file_mode_info('../temp');
    if ($result < 2) {
        $warning[] = sprintf($_LANG['not_writable'], 'images', $_LANG['tpl_cannt_write']);
    }

    $result = file_mode_info('../temp/backup');
    if ($result < 2) {
        $warning[] = sprintf($_LANG['not_writable'], 'images', $_LANG['tpl_backup_cannt_write']);
    }

    if (!is_writeable('../' . DATA_DIR . '/order_print.html')) {
        $warning[] = $_LANG['order_print_canntwrite'];
    }
    clearstatcache();

    $smarty->assign('warning_arr', $warning);


    /* 管理员留言信息 */
    $sql = 'SELECT message_id, sender_id, receiver_id, sent_time, readed, deleted, title, message, user_name ' .
            'FROM ' . $ecs->table('admin_message') . ' AS a, ' . $ecs->table('admin_user') . ' AS b ' .
            "WHERE a.sender_id = b.user_id AND a.receiver_id = '$_SESSION[admin_id]' AND " .
            "a.readed = 0 AND deleted = 0 ORDER BY a.sent_time DESC";
    $admin_msg = $db->GetAll($sql);

    $smarty->assign('admin_msg', $admin_msg);

    /* 取得支持货到付款和不支持货到付款的支付方式 */
    $ids = get_pay_ids();
    /* ecmoban start zhuo */
    $today_start = local_mktime(0,0,0,date('m'),date('d'),date('Y'));
    $today_end = local_mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
    $month_start = local_mktime(0,0,0,date('m'),1,date('Y'));
    $month_end = local_mktime(23,59,59,date('m'),date('t'),date('Y'));
    $today = array();

    $where_date = '';
    $where_og = '';
    $where_og .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = oi.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

    //ecmoban模板堂 --zhuo start
    if ($adminru['ru_id'] > 0) {
	$where_date .= " AND (SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " AS og WHERE oi.order_id = og.order_id LIMIT 1) = '" .$adminru['ru_id']. "'";  //主订单下有子订单时，则主订单不显示
    }
    //ecmoban模板堂 --zhuo end

    //今日销售总额
    //付款金额
    $sql = 'SELECT  SUM(oi.money_paid) AS sales FROM ' .$ecs->table('order_info') ." AS oi". ' WHERE oi.add_time BETWEEN '.$today_start.' AND '.$today_end.'  AND oi.supplier_id=0  '.order_query_sql('queren', "oi.") . $where_date . $where_og;
    $today['money_paid_money'] = $db->GetOne($sql);
    //余额金额
    $sql = 'SELECT  SUM(surplus) AS sales FROM ' .$ecs->table('order_info') ." AS oi". ' WHERE oi.add_time BETWEEN '.$today_start.' AND '.$today_end.'  AND oi.supplier_id=0 '.order_query_sql('queren', "oi.") . $where_date . $where_og;
    $today['surplus_money'] = $db->GetOne($sql);
	//退款金额
	$sql = " SELECT SUM(actual_return) AS sales FROM ". $ecs->table('order_return') ." WHERE return_time BETWEEN ".$today_start." AND ".$today_end." AND refound_status = 1 ";
	$today['return_money'] = $db->GetOne($sql);
    //总金额
    $today['formatted_money'] = price_format($today['money_paid_money']+$today['surplus_money']-$today['return_money']);
    $today['formatted_money'] = str_replace("￥", "", $today['formatted_money']);

    $sql = " SELECT IFNULL(SUM(" . order_commission_field('oi.') . "),0) money_total FROM " .
            $GLOBALS['ecs']->table('order_info') . "oi " .
            ' WHERE oi.add_time BETWEEN '.$today_start.' AND '.$today_end.'  AND oi.supplier_id=0 AND oi.pay_status = 2 '.order_query_sql('queren', "oi.") . $where_date . $where_og ;  //主订单下有子订单时，则主订单不显示
	$today['formatted_money'] = price_format($db->GetOne($sql));
    //今日订单数
    $today['order'] = $db->GetOne('SELECT COUNT(*) FROM ' .$ecs->table('order_info') ." AS oi". ' WHERE oi.add_time BETWEEN '.$today_start.' AND '.$today_end.' AND oi.supplier_id=0'. $where_date . $where_og);
    //今日注册会员
    $sql = "SELECT COUNT(*) FROM " .$ecs->table('users'). " WHERE `reg_time` BETWEEN ".$today_start.' AND '.$today_end;
    $today['user'] = $db->GetOne($sql);
    //当前月份
    $thismonth=date('m');
    $smarty->assign('thismonth',$thismonth);
    $smarty->assign('today',$today);
		/* ecmoban zhou */

    //ecmoban模板堂 --zhuo start
    $where_goods = '';
    $where_cmt = '';

    if ($adminru['ru_id'] > 0) {

        $where_og .= " AND (SELECT og.ru_id FROM " .$GLOBALS['ecs']->table('order_goods') ." AS og WHERE oi.order_id = og.order_id LIMIT 1". ") = " . $adminru['ru_id'];

        $where_goods = " and user_id = " . $adminru['ru_id'];
        $where_cmt = " and ru_id = " . $adminru['ru_id'];
    }
    //ecmoban模板堂 --zhuo end

    /* 已完成的订单 */
    $order['finished'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " AS oi " .
            " WHERE 1 " . order_query_sql('finished') . $where_og);
    $status['finished'] = CS_FINISHED;

    /* 待发货的订单： */
    $order['await_ship'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " AS oi " .
            " WHERE 1 " . order_query_sql('await_ship') . $where_og);
    $status['await_ship'] = CS_AWAIT_SHIP;

    /* 待付款的订单： */
    $order['await_pay'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " AS oi " .
            " WHERE 1 " . order_query_sql('await_pay') . $where_og);
    $status['await_pay'] = CS_AWAIT_PAY;

    /* “未确认”的订单 */
    $order['unconfirmed'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " AS oi " .
            " WHERE 1 " . order_query_sql('unconfirmed') . $where_og);
    $status['unconfirmed'] = OS_UNCONFIRMED;

    /* “部分发货”的订单 */
    $order['shipped_part'] = $db->getOne('SELECT count(*) FROM ' . $ecs->table('order_info') . " AS oi " .
            " WHERE  shipping_status=" . SS_SHIPPED_PART . $where_og);
    $status['shipped_part'] = OS_SHIPPED_PART;

//    $today_start = mktime(0,0,0,date('m'),date('d'),date('Y'));
    $order['stats'] = $db->getRow('SELECT COUNT(*) AS oCount, IFNULL(SUM(oi.order_amount), 0) AS oAmount' .
            ' FROM ' . $ecs->table('order_info') . " as oi" . " WHERE 1 " . $where_og);

    //退换货
    $where_return = '';
    if ($adminru['ru_id'] > 0) {
        $where_return = " and og.ru_id = '" . $adminru['ru_id'] . "'";
    }

    $sql = "SELECT count(*) FROM " . $ecs->table('order_info') . " AS o LEFT JOIN " . $ecs->table('order_goods') . " AS og ON og.order_id = o.order_id " .
            " LEFT JOIN " . $ecs->table('users') . " AS u ON u.user_id = o.user_id " .
            " RIGHT JOIN " . $ecs->table('order_return') . " AS r ON r.order_id = o.order_id WHERE 1" . $where_return;
    $order['return_number'] = $db->getOne($sql);

    $smarty->assign('order', $order);
    $smarty->assign('status', $status);

    /* 商品信息 */
    /*$goods_total_where = " is_delete = 0 AND is_alone_sale = 1 AND is_real = 1 " . $where_goods;
    $goods['total'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$goods_total_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $virtual_card_total_where = " is_delete = 0 AND is_alone_sale = 1 AND is_real=0 AND extension_code = 'virtual_card' " . $where_goods;
    $virtual_card['total'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$virtual_card_total_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $goods_new_where = " is_delete = 0 AND is_new = 1 AND is_real = 1 " . $where_goods;
    $goods['new'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$goods_new_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $virtual_card_new_where = " is_delete = 0 AND is_new = 1 AND is_real = 0 AND extension_code = 'virtual_card' " . $where_goods;
    $virtual_card['new'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$virtual_card_new_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $goods_best_where = " is_delete = 0 AND is_best = 1 AND is_real = 1 " . $where_goods;
    $goods['best'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$goods_best_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $virtual_card_best_where = " is_delete = 0 AND is_best = 1 AND is_real = 0 AND extension_code = 'virtual_card' " . $where_goods;
    $virtual_card['best'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$virtual_card_best_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $goods_hot_where = " is_delete = 0 AND is_hot = 1 AND is_real = 1 " . $where_goods;
    $goods['hot'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$goods_hot_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $virtual_card_hot_where = " is_delete = 0 AND is_hot = 1 AND is_real = 0 AND extension_code = 'virtual_card' " . $where_goods;
    $virtual_card['hot'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$virtual_card_hot_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $time = gmtime();
    $goods_promote_where = " is_delete = 0 AND promote_price > 0 AND promote_start_date <= '$time' AND promote_end_date >= '$time' AND is_real = 1 " . $where_goods;
    $goods['promote'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$goods_promote_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');

    $virtual_card_promote_where = " is_delete = 0 AND promote_price > 0 AND promote_start_date <= '$time' AND promote_end_date >= '$time' AND is_real=0 AND extension_code='virtual_card' " . $where_goods;
    $virtual_card['promote'] = $db->GetOne('SELECT SUM(CASE WHEN ' .$virtual_card_promote_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') .
            ' WHERE 1');*/

    /* 缺货商品 */
    /*if ($_CFG['use_storage']) {
        $goods_warn_where = " is_delete = 0 AND goods_number <= warn_number AND is_real = 1 " . $where_goods;
        $sql = 'SELECT SUM(CASE WHEN ' .$goods_warn_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') . ' WHERE 1';
        $goods['warn'] = $db->GetOne($sql);

        $virtual_card_warn_where = " is_delete = 0 AND goods_number <= warn_number AND is_real=0 AND extension_code = 'virtual_card' " . $where_goods;
        $sql = 'SELECT SUM(CASE WHEN ' .$virtual_card_warn_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') . ' WHERE 1';
        $virtual_card['warn'] = $db->GetOne($sql);
    } else {
        $goods['warn'] = 0;
        $virtual_card['warn'] = 0;
    }
    $smarty->assign('goods', $goods);
    $smarty->assign('virtual_card', $virtual_card);*/

    /* 访问统计信息 */
    $today = local_getdate();
    $today_visit_where = " access_time > " . (mktime(0, 0, 0, $today['mon'], $today['mday'], $today['year']) - date('Z'));
    $sql = 'SELECT SUM(CASE WHEN ' .$today_visit_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('stats') .' WHERE  1';
    $today_visit = $db->GetOne($sql);
    $smarty->assign('today_visit', $today_visit);

    $today_visit = $db->GetOne($sql);
    $smarty->assign('today_visit', $today_visit);

    $online_users = $sess->get_users_count();
    $smarty->assign('online_users', $online_users);

    /* 最近反馈 */
    $sql = "SELECT COUNT(f.msg_id) " .
            "FROM " . $ecs->table('feedback') . " AS f " .
            "LEFT JOIN " . $ecs->table('feedback') . " AS r ON r.parent_id=f.msg_id " .
            'WHERE f.parent_id=0 AND ISNULL(r.msg_id) ';
    $smarty->assign('feedback_number', $db->GetOne($sql));

	/*基本信息（短信，邮件，支付方式，OSS）是否配置 start*/
	//短信是否配置
	$phone_num = $db->getOne("SELECT value FROM " . $ecs->table('shop_config') . " WHERE code = 'sms_shop_mobile'");
	$user_name = $db->getOne("SELECT value FROM " . $ecs->table('shop_config') . " WHERE code = 'sms_ecmoban_user'");
	$smarty->assign('phone_num', $phone_num);
	$smarty->assign('user_name', $user_name);

	//邮箱是否设置
	$email = $db->getOne("SELECT value FROM " . $ecs->table('shop_config') . " WHERE code = 'smtp_user'");
	$smarty->assign('email', $email);

	//支付方式是否设置
	$pay = $db->getOne("SELECT pay_name FROM " . $ecs->table('payment') . " WHERE enabled = '1'");
	$smarty->assign('pay', $pay);

	//OSS是否设置
	$oss = $db->getOne("SELECT bucket FROM " . $ecs->table('oss_configure') . " WHERE is_use = '1'");
	$smarty->assign('oss', $oss);
	/*基本信息（短信，邮件，支付方式，OSS）是否配置 end*/

    /* 未审核评论 */
    $comment_number_where = " status = 0 AND parent_id = 0 " . $where_cmt;
    $comment_number = $db->getOne('SELECT SUM(CASE WHEN ' .$comment_number_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('comment') .  ' WHERE 1');
    $smarty->assign('comment_number', $comment_number);

    /* 首页统计信息 by wu start */

    // 今日评论数
    $today_comment_number_where = " parent_id = 0" . $where_cmt . " AND add_time BETWEEN " . $today_start . ' AND ' . $today_end;
    $today_comment_number = $db->getOne('SELECT SUM(CASE WHEN ' .$today_comment_number_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('comment') .' WHERE 1');
    $smarty->assign('today_comment_number', $today_comment_number);

    // 自营实体商品数量
    $platform_real_where = " is_delete= 0 AND user_id = 0 AND is_real = 1 ";
    $platform_real_goods_number = $db->getOne('SELECT SUM(CASE WHEN ' .$platform_real_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') . ' WHERE 1');
    $smarty->assign('platform_real_goods_number', $platform_real_goods_number);

    // 自营虚拟商品数量
    $platform_virtual_where = " is_delete= 0 AND user_id = 0 AND is_real = 0 ";
    $platform_virtual_goods_number = $db->getOne('SELECT SUM(CASE WHEN ' .$platform_virtual_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') . ' WHERE 1');
    $smarty->assign('platform_virtual_goods_number', $platform_virtual_goods_number);

    // 商家实体商品数量
    $merchants_real_where = " is_delete= 0 AND user_id > 0 AND is_real = 1 ";
    $merchants_real_goods_number = $db->getOne('SELECT SUM(CASE WHEN ' .$merchants_real_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') . ' WHERE 1');
    $smarty->assign('merchants_real_goods_number', $merchants_real_goods_number);

    // 商家虚拟商品数量
    $merchants_virtual_where = " is_delete= 0 AND user_id > 0 AND is_real = 0 ";
    $merchants_virtual_goods_number = $db->getOne('SELECT SUM(CASE WHEN ' .$merchants_virtual_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('goods') . ' WHERE 1');
    $smarty->assign('merchants_virtual_goods_number', $merchants_virtual_goods_number);

    // 今日注册会员数量
    $today_user_number_where = " reg_time BETWEEN '" .$today_start. "' AND '" . $today_end ."'";
    $today_user_number = $db->getOne('SELECT SUM(CASE WHEN ' .$today_user_number_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('users') . ' WHERE 1');
    $smarty->assign('today_user_number', $today_user_number);

    // 昨日注册会员数量
    $yesterday_user_number_where = " reg_time BETWEEN " . ($today_start - 3600 * 24) . " AND " . ($today_end - 3600 * 24);
    $yesterday_user_number = $db->getOne('SELECT SUM(CASE WHEN ' .$yesterday_user_number_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('users') . ' WHERE 1');
    $smarty->assign('yesterday_user_number', $yesterday_user_number);

    // 本月注册会员数量
    $month_user_number_where = " reg_time BETWEEN " . $month_start  . " AND " . $month_end;
    $month_user_number = $db->getOne('SELECT SUM(CASE WHEN ' .$month_user_number_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('users') . ' WHERE 1');
    $smarty->assign('month_user_number', $month_user_number);

    // 所有会员数量
    $smarty->assign('user_number', $db->getOne('SELECT COUNT(*) FROM ' . $ecs->table('users')));

    // 已审核店铺数量
    $smarty->assign('seller_num', $db->getOne('SELECT COUNT(*) FROM ' . $ecs->table('merchants_shop_information') . ' WHERE merchants_audit = 1'));
    /* 首页统计信息 by wu end */

    $mysql_ver = $db->version();   // 获得 MySQL 版本

    /* 系统信息 */
    $sys_info['os'] = PHP_OS;
    $sys_info['ip'] = $_SERVER['SERVER_ADDR'];
    $sys_info['web_server'] = $_SERVER['SERVER_SOFTWARE'];
    $sys_info['php_ver'] = PHP_VERSION;
    $sys_info['mysql_ver'] = $mysql_ver;
    $sys_info['zlib'] = function_exists('gzclose') ? $_LANG['yes'] : $_LANG['no'];
    $sys_info['safe_mode'] = (boolean) ini_get('safe_mode') ? $_LANG['yes'] : $_LANG['no'];
    $sys_info['safe_mode_gid'] = (boolean) ini_get('safe_mode_gid') ? $_LANG['yes'] : $_LANG['no'];
    $sys_info['timezone'] = function_exists("date_default_timezone_get") ? date_default_timezone_get() : $_LANG['no_timezone'];
    $sys_info['socket'] = function_exists('fsockopen') ? $_LANG['yes'] : $_LANG['no'];

    if ($gd == 0) {
        $sys_info['gd'] = 'N/A';
    } else {
        if ($gd == 1) {
            $sys_info['gd'] = 'GD1';
        } else {
            $sys_info['gd'] = 'GD2';
        }

        $sys_info['gd'] .= ' (';

        /* 检查系统支持的图片类型 */
        if ($gd && (imagetypes() & IMG_JPG) > 0) {
            $sys_info['gd'] .= ' JPEG';
        }

        if ($gd && (imagetypes() & IMG_GIF) > 0) {
            $sys_info['gd'] .= ' GIF';
        }

        if ($gd && (imagetypes() & IMG_PNG) > 0) {
            $sys_info['gd'] .= ' PNG';
        }

        $sys_info['gd'] .= ')';
    }

    /* IP库版本 */
    $sys_info['ip_version'] = ecs_geoip('255.255.255.0');

    /* 允许上传的最大文件大小 */
    $sys_info['max_filesize'] = ini_get('upload_max_filesize');

    $smarty->assign('sys_info', $sys_info);

    /* 缺货登记 */

    $booking_goods_where = " is_dispose = 0 ";
    $sql = 'SELECT SUM(CASE WHEN ' .$booking_goods_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('booking_goods') . ' WHERE 1';
    $booking_goods = $db->getOne($sql);

    $smarty->assign('booking_goods', $booking_goods);

    /* 退款申请 */
    $new_repay_where = " process_type = '" .SURPLUS_RETURN. "' AND is_paid = 0 ";
    $new_repay = $db->getOne('SELECT SUM(CASE WHEN ' .$new_repay_where. ' THEN 1 ELSE 0 END) FROM ' . $ecs->table('user_account') . ' WHERE 1');
    $smarty->assign('new_repay', $new_repay);

    /* 每月数据统计 ecmoban start zhou */
    $froms_tooltip = array(
                        'trigger'=>'item',
                        'formatter'=>'{a} <br/>{b} : {c} ({d}%)');
    $froms_legend = array(
                        'orient'=>'vertical',
                        'x'=>'left',
                        'y'=>'20',
                        'data'=>array());
    $froms_toolbox = array(
        'show'=>true,
        'feature'=>array(
            'magicType'=>array(
                'show'=>true,
                'type'=>array('pie','funnel')
            ),
            'restore'=>array('show'=>true),
            'saveAsImage'=>array('show'=>true)
        )
    );

    $froms_calculable = true;
    $froms_series = array(
        array(
            'type'=>'pie',
            'radius'=>'55%',
            'center'=>array('50%','60%')));
    $froms_data = array();
    $froms_options = array();

    $no_main_order = " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = oi.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

    $sql = 'SELECT oi.froms, count(*) AS `count` FROM '.$ecs->table('order_info') ." AS oi ".' WHERE oi.add_time BETWEEN '.$month_start.' AND '.$month_end .$no_main_order . $where_date . ' AND oi.supplier_id = 0 GROUP BY oi.froms ORDER BY `count` DESC';
    $result = $db->query($sql);
    while($row = $GLOBALS['db']->fetchRow($result))
    {
        $froms_data[] = array('value'=>$row['count'],'name'=>$row['froms']);
        $froms_legend_data[]=$row['froms'];
    }
    $froms_legend['data'] = $froms_legend_data;
    $froms_series[0]['data'] = $froms_data;
    $froms_options['tooltip'] = $froms_tooltip;
    $froms_options['legend'] = $froms_legend;
    $froms_options['toolbox'] = $froms_toolbox;
    $froms_options['calculabe'] = $froms_calculable;
    $froms_options['series'] = $froms_series;
    $smarty->assign('froms_option',json_encode($froms_options));
    //当月每日订单数统计
    $orders_tooltip = array('trigger'=>'axis');
    $orders_legend = array('data'=>array());
    $orders_toolbox = array(
                        'show'=>true,
                        'x'=>'right',
                        'feature'=>array(
                                    'magicType'=>array(
                                                    'show'=>true,
                                                    'type'=>array('line','bar')),
                                    'restore'=>array(
                                                    'show'=>true)
                                    ));
    $orders_calculable = true;
    $orders_xAxis = array(
                        'type'=>'category',
                        'boundryGap'=>false,
                        'data'=>array());
    $orders_yAxis = array(
                        'type'=>'value',
                        'axisLabel'=>array(
                                        'formatter'=>'{value}个'));
    $orders_series = array(
                        array(
                            'name'=>'订单个数',
                            'type'=>'line',
                            'data'=>array(),
                            'markPoint'=>array(
                                            'data'=>array(
                                                        array(
                                                            'type'=>'max',
                                                            'name'=>'最大值'),
                                                        array(
                                                            'type'=>'min',
                                                            'name'=>'最小值')))));

    $sql = 'SELECT DATE_FORMAT(FROM_UNIXTIME(oi.add_time),"%d") AS day,COUNT(*) AS count,SUM(oi.money_paid) AS money, SUM(oi.money_paid)+SUM(oi.surplus) AS superman FROM '.$ecs->table('order_info') ." AS oi".' WHERE oi.add_time BETWEEN '.$month_start.' AND '.$month_end .$no_main_order .$where_date. ' AND oi.supplier_id = 0 GROUP BY day ORDER BY day ASC ';
    $result = $db->query($sql);

    while($row = $GLOBALS['db']->fetchRow($result))
    {
        $orders_series_data[intval($row['day'])] = intval($row['count']);
        $sales_series_data[intval($row['day'])] = floatval($row['money']);
        $sales_series_data[intval($row['day'])] = floatval($row['superman']);
    }
    for($i = 1;$i<=date('d');$i++)
    {
        if(empty($orders_series_data[$i]))
        {
            $orders_series_data[$i] = 0;
            $sales_series_data[$i] = 0;
        }
        $orders_xAxis_data[] = $i;
        $sales_xAxis_data[] = $i;
    }
    $orders_xAxis['data'] = $orders_xAxis_data;
    ksort($orders_series_data);

    $orders_series[0]['data'] = array_values($orders_series_data);
    $orders_option['tooltip'] = $orders_tooltip;
    $orders_option['legend'] = $orders_legend;
    $orders_option['toolbox'] = $orders_toolbox;
    $orders_option['calculable'] = $orders_calculable;
    $orders_option['xAxis'] = $orders_xAxis;
    $orders_option['yAxis'] = $orders_yAxis;
    $orders_option['series'] = $orders_series;
    $smarty->assign('orders_option',json_encode($orders_option));

    //当月每日销售额统计
    $sales_tooltip = array('trigger'=>'axis');
    $sales_legend = array('data'=>array());
    $sales_toolbox = array(
                        'show'=>true,
                        'x'=>'right',
                        'feature'=>array(
                                    'magicType'=>array(
                                                    'show'=>true,
                                                    'type'=>array('line','bar')),
                                    'restore'=>array(
                                                    'show'=>true)
                                    ));
    $sales_calculable = true;
    $sales_xAxis = array(
                        'type'=>'category',
                        'boundryGap'=>false,
                        'data'=>array());
    $sales_yAxis = array(
                        'type'=>'value',
                        'axisLabel'=>array(
                                        'formatter'=>'{value}元'));
    $sales_series = array(
                        array(
                            'name'=>'销售额',
                            'type'=>'line',
                            'data'=>array(),
                            'markPoint'=>array(
                                            'data'=>array(
                                                        array(
                                                            'type'=>'max',
                                                            'name'=>'最大值'),
                                                        array(
                                                            'type'=>'min',
                                                            'name'=>'最小值')))));
    $sales_xAxis['data'] = $sales_xAxis_data;
    ksort($sales_series_data);
    $sales_series[0]['data'] = array_values($sales_series_data);
    $sales_option['tooltip'] = $sales_tooltip;
    $sales_option['toolbox'] = $sales_toolbox;
    $sales_option['calculable'] = $sales_calculable;
    $sales_option['xAxis'] = $sales_xAxis;
    $sales_option['yAxis'] = $sales_yAxis;
    $sales_option['series'] = $sales_series;
    $smarty->assign('sales_option',json_encode($sales_option));
    /* ecmoban end */

    assign_query_info();
    $smarty->assign('ecs_url', $ecs->url());
    $smarty->assign('ecs_version', VERSION);
    $smarty->assign('ecs_release', RELEASE);
    $smarty->assign('ecs_lang', $_CFG['lang']);
    $smarty->assign('ecs_charset', strtoupper(EC_CHARSET));
    $smarty->assign('install_date', local_date($_CFG['date_format'], $_CFG['install_date']));
    $smarty->display('start.dwt');
}
//wang 商家入驻 店铺头部装修
elseif($_REQUEST['act'] == 'shop_top')
{
	$smarty->assign('menu_select',array('action' => '19_merchants_store', 'current' => '03_merchants_shop_top'));
    admin_priv('seller_store_other');//by kong
	$smarty->assign('ur_here','店铺头部装修');
	//获取入驻商家店铺信息 wang 商家入驻
	$sql="select id,seller_theme,shop_color from ".$ecs->table('seller_shopinfo')." where ru_id='".$adminru['ru_id']."'";
	$seller_shop_info=$db->getRow($sql);
	if($seller_shop_info['id']>0)
	{
		//店铺头部
		$header_sql="select content, headtype, headbg_img, shop_color from ".$GLOBALS['ecs']->table('seller_shopheader')." where seller_theme='".$seller_shop_info['seller_theme']."' and ru_id = '".$adminru['ru_id']."'";
		$shopheader_info = $GLOBALS['db']->getRow($header_sql);

                $header_content = $shopheader_info['content'];

		/* 创建 百度编辑器 wang 商家入驻 */
                create_ueditor_editor('shop_header',$header_content,586);

		$smarty->assign('form_action', 'shop_top_edit');
		$smarty->assign('shop_info',$seller_shop_info);
                $smarty->assign('shopheader_info', $shopheader_info);
	}
	else
	{
		$lnk[] = array('text' => '设置店铺信息','href'=>'index.php?act=first');
		sys_msg('请先设置店铺基本信息', 0, $lnk);
	}
	$smarty->display('seller_shop_header.dwt');
}
elseif($_REQUEST['act'] == 'shop_top_edit')
{
    //正则去掉js代码
    $preg = "/<script[\s\S]*?<\/script>/i";

    $shop_header = !empty($_REQUEST['shop_header']) ? preg_replace($preg, "", stripslashes($_REQUEST['shop_header'])) : '';
    $seller_theme = !empty($_REQUEST['seller_theme']) ? preg_replace($preg, "", stripslashes($_REQUEST['seller_theme'])) : '';
    $shop_color = !empty($_REQUEST['shop_color']) ? $_REQUEST['shop_color'] : '';
    $headtype = isset($_REQUEST['headtype']) ? intval($_REQUEST['headtype']) : 0;

    $img_url = '';
    if ($headtype == 0) {
        /* 处理图片 */
        /* 允许上传的文件类型 */
        $allow_file_types = '|GIF|JPG|PNG|BMP|';

        if ($_FILES['img_url']) {
            $file = $_FILES['img_url'];
            /* 判断用户是否选择了文件 */
            if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none')) {
                /* 检查上传的文件类型是否合法 */
                if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types)) {
                    sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
                } else {

                    $ext = array_pop(explode('.', $file['name']));
                    $file_dir = '../seller_imgs/seller_header_img/seller_' . $adminru['ru_id'];
                    if (!is_dir($file_dir)) {
                        mkdir($file_dir);
                    }
                    $file_name = $file_dir . "/slide_" . gmtime() . '.' . $ext;
                    /* 判断是否上传成功 */
                    if (move_upload_file($file['tmp_name'], $file_name)) {
                        $img_url = $file_name;

                        $oss_img_url = str_replace("../", "", $img_url);
                        get_oss_add_file(array($oss_img_url));
                    } else {
                        sys_msg('图片上传失败');
                    }
                }
            }
        } else {
            sys_msg('必须上传图片');
        }
    }

    $sql = "SELECT headbg_img FROM " . $ecs->table('seller_shopheader') . " WHERE ru_id='" . $adminru['ru_id'] . "' and seller_theme='" . $seller_theme . "'";
    $shopheader_info = $db->getRow($sql);

    if (empty($img_url)) {
        $img_url = $shopheader_info['headbg_img'];
    }

    //跟新店铺头部
    $sql = "update " . $ecs->table('seller_shopheader') . " set content='$shop_header', shop_color='$shop_color', headbg_img='$img_url', headtype='$headtype' where ru_id='" . $adminru['ru_id'] . "' and seller_theme='" . $seller_theme . "'";
    $db->query($sql);

    $lnk[] = array('text' => '返回上一步', 'href' => 'index.php?act=shop_top');

    sys_msg('店铺头部装修成功', 0, $lnk);
}
elseif ($_REQUEST['act'] == 'main_api') {
    require_once(ROOT_PATH . '/includes/lib_base.php');
    $data = read_static_cache('api_str');

    if ($data === false || API_TIME < date('Y-m-d H:i:s', time() - 43200)) {
        include_once(ROOT_PATH . 'includes/cls_transport.php');

        $ecs_version = VERSION;
        $ecs_lang = $_CFG['lang'];
        $ecs_release = RELEASE;
        $php_ver = PHP_VERSION;
        $mysql_ver = $db->version();
        $ecs_charset = strtoupper(EC_CHARSET);

        $no_main_order = " WHERE 1 AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
        $sql = 'SELECT COUNT(*) AS oCount, IFNULL(SUM(order_amount), 0) AS oAmount FROM ' . $ecs->table('order_info') ." AS o ". $no_main_order;
        $order['stats'] = $db->getRow($sql);
        $ocount = $order['stats']['oCount']; //订单数量
        $oamount = $order['stats']['oAmount']; //总销售金额

        $goods['total'] = $db->GetOne('SELECT COUNT(*) FROM ' . $ecs->table('goods') .
                ' WHERE is_delete = 0 AND is_alone_sale = 1 AND is_real = 1');
        $gcount = $goods['total']; //商品数量
        $ecs_user = $db->getOne('SELECT COUNT(*) FROM ' . $ecs->table('users')); //会员数量

        $ecs_template = $db->getOne('SELECT value FROM ' . $ecs->table('shop_config') . ' WHERE code = \'template\''); //当前使用模板
        $style = $db->getOne('SELECT value FROM ' . $ecs->table('shop_config') . ' WHERE code = \'stylename\'');  //当前模板样式
        if ($style == '') {
            $style = '0';
        }
        $ecs_style = $style;
        $shop_url = urlencode($ecs->url()); //当前url

        $httpData = array(
            'domain'            =>  $ecs->get_domain(), //当前域名
            'url'               =>  urldecode($shop_url), //当前url
            'ver'           	=>  $ecs_version,
            'lang'          	=>  $ecs_lang,
            'release'           =>  $ecs_release,
            'php_ver'           =>  $php_ver,
            'mysql_ver'         =>  $mysql_ver,
            'ocount'            =>  $ocount,
            'oamount'           =>  $oamount,
            'gcount'            =>  $gcount,
            'charset'           =>  $ecs_charset,
            'usecount'          =>  $ecs_user,
            'template'          =>  $ecs_template,
            'style'             =>  $ecs_style
        );

        $Http = new Http();
        $Http->doPost('http://ecshop.ecmoban.com/dsc_checkver.php', $httpData);

        $f = ROOT_PATH . 'data/config.php';
        write_static_file_cache('config', str_replace("'API_TIME', '" . API_TIME . "'", "'API_TIME', '" . date('Y-m-d H:i:s', time()) . "'", file_get_contents($f)), 'php', ROOT_PATH . 'data/');

        write_static_cache('api_str', $httpData);
    }
}


/* ------------------------------------------------------ */
//-- 开店向导第一步
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'first') {
    $smarty->assign('countries', get_regions());
    $smarty->assign('provinces', get_regions(1, 1));
    $smarty->assign('cities', get_regions(2, 2));

    $sql = 'SELECT value from ' . $ecs->table('shop_config') . " WHERE code='shop_name'";
    $shop_name = $db->getOne($sql);

    $smarty->assign('shop_name', $shop_name);

    $sql = 'SELECT value from ' . $ecs->table('shop_config') . " WHERE code='shop_title'";
    $shop_title = $db->getOne($sql);

    $smarty->assign('shop_title', $shop_title);

    //获取配送方式
//    $modules = read_modules('../includes/modules/shipping');
    $directory = ROOT_PATH . 'includes/modules/shipping';
    $dir = @opendir($directory);
    $set_modules = true;
    $modules = array();

    while (false !== ($file = @readdir($dir))) {
        if (preg_match("/^.*?\.php$/", $file)) {
            if ($file != 'express.php') {
                include_once($directory . '/' . $file);
            }
        }
    }
    @closedir($dir);
    unset($set_modules);

    foreach ($modules AS $key => $value) {
        ksort($modules[$key]);
    }
    ksort($modules);

    for ($i = 0; $i < count($modules); $i++) {
        $lang_file = ROOT_PATH . 'languages/' . $_CFG['lang'] . '/shipping/' . $modules[$i]['code'] . '.php';

        if (file_exists($lang_file)) {
            include_once($lang_file);
        }

        $modules[$i]['name'] = $_LANG[$modules[$i]['code']];
        $modules[$i]['desc'] = $_LANG[$modules[$i]['desc']];
        $modules[$i]['insure_fee'] = empty($modules[$i]['insure']) ? 0 : $modules[$i]['insure'];
        $modules[$i]['cod'] = $modules[$i]['cod'];
        $modules[$i]['install'] = 0;
    }
    $smarty->assign('modules', $modules);

    unset($modules);

    //获取支付方式
    $modules = read_modules('../includes/modules/payment');

    for ($i = 0; $i < count($modules); $i++) {
        $code = $modules[$i]['code'];
        $modules[$i]['name'] = $_LANG[$modules[$i]['code']];
        if (!isset($modules[$i]['pay_fee'])) {
            $modules[$i]['pay_fee'] = 0;
        }
        $modules[$i]['desc'] = $_LANG[$modules[$i]['desc']];
    }
    //        $modules[$i]['install'] = '0';
    $smarty->assign('modules_payment', $modules);

    assign_query_info();

    $smarty->assign('ur_here', $_LANG['ur_config']);
    $smarty->display('setting_first.dwt');
}

/* ------------------------------------------------------ */
//-- 开店向导第二步
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'second') {
    admin_priv('shop_config');

    $shop_name = empty($_POST['shop_name']) ? '' : $_POST['shop_name'];
    $shop_title = empty($_POST['shop_title']) ? '' : $_POST['shop_title'];
    $shop_country = empty($_POST['shop_country']) ? '' : intval($_POST['shop_country']);
    $shop_province = empty($_POST['shop_province']) ? '' : intval($_POST['shop_province']);
    $shop_city = empty($_POST['shop_city']) ? '' : intval($_POST['shop_city']);
    $shop_address = empty($_POST['shop_address']) ? '' : $_POST['shop_address'];
    $shipping = empty($_POST['shipping']) ? '' : $_POST['shipping'];
    $payment = empty($_POST['payment']) ? '' : $_POST['payment'];

    if (!empty($shop_name)) {
        $sql = 'UPDATE ' . $ecs->table('shop_config') . " SET value = '$shop_name' WHERE code = 'shop_name'";
        $db->query($sql);
    }

    if (!empty($shop_title)) {
        $sql = 'UPDATE ' . $ecs->table('shop_config') . " SET value = '$shop_title' WHERE code = 'shop_title'";
        $db->query($sql);
    }

    if (!empty($shop_address)) {
        $sql = 'UPDATE ' . $ecs->table('shop_config') . " SET value = '$shop_address' WHERE code = 'shop_address'";
        $db->query($sql);
    }

    if (!empty($shop_country)) {
        $sql = 'UPDATE ' . $ecs->table('shop_config') . "SET value = '$shop_country' WHERE code='shop_country'";
        $db->query($sql);
    }

    if (!empty($shop_province)) {
        $sql = 'UPDATE ' . $ecs->table('shop_config') . "SET value = '$shop_province' WHERE code='shop_province'";
        $db->query($sql);
    }

    if (!empty($shop_city)) {
        $sql = 'UPDATE ' . $ecs->table('shop_config') . "SET value = '$shop_city' WHERE code='shop_city'";
        $db->query($sql);
    }

    //设置配送方式
    if (!empty($shipping)) {
        $shop_add = read_modules('../includes/modules/shipping');

        foreach ($shop_add as $val) {
            $mod_shop[] = $val['code'];
        }
        $mod_shop = implode(',', $mod_shop);

        $set_modules = true;
        if (strpos($mod_shop, $shipping) === false) {
            exit;
        } else {
            include_once(ROOT_PATH . 'includes/modules/shipping/' . $shipping . '.php');
        }
        $sql = "SELECT shipping_id FROM " . $ecs->table('shipping') . " WHERE shipping_code = '$shipping'";
        $shipping_id = $db->GetOne($sql);

        if ($shipping_id <= 0) {
            $insure = empty($modules[0]['insure']) ? 0 : $modules[0]['insure'];
            $sql = "INSERT INTO " . $ecs->table('shipping') . " (" .
                    "shipping_code, shipping_name, shipping_desc, insure, support_cod, enabled" .
                    ") VALUES (" .
                    "'" . addslashes($modules[0]['code']) . "', '" . addslashes($_LANG[$modules[0]['code']]) . "', '" .
                    addslashes($_LANG[$modules[0]['desc']]) . "', '$insure', '" . intval($modules[0]['cod']) . "', 1)";
            $db->query($sql);
            $shipping_id = $db->insert_Id();
        }

        //设置配送区域
        $area_name = empty($_POST['area_name']) ? '' : $_POST['area_name'];
        if (!empty($area_name)) {
            $sql = "SELECT shipping_area_id FROM " . $ecs->table("shipping_area") .
                    " WHERE shipping_id='$shipping_id' AND shipping_area_name='$area_name'";
            $area_id = $db->getOne($sql);

            if ($area_id <= 0) {
                $config = array();
                if(!empty($modules[0]['configure'])){
                    foreach ($modules[0]['configure'] AS $key => $val) {
                        $config[$key]['name'] = $val['name'];
                        $config[$key]['value'] = $val['value'];
                    }
                }

                $count = count($config);
                $config[$count]['name'] = 'free_money';
                $config[$count]['value'] = 0;

                /* 如果支持货到付款，则允许设置货到付款支付费用 */
                if ($modules[0]['cod']) {
                    $count++;
                    $config[$count]['name'] = 'pay_fee';
                    $config[$count]['value'] = make_semiangle(0);
                }

                $sql = "INSERT INTO " . $ecs->table('shipping_area') .
                        " (shipping_area_name, shipping_id, configure) " .
                        "VALUES" . " ('$area_name', '$shipping_id', '" . serialize($config) . "')";
                $db->query($sql);
                $area_id = $db->insert_Id();
            }

            $region_id = empty($_POST['shipping_country']) ? 1 : intval($_POST['shipping_country']);
            $region_id = empty($_POST['shipping_province']) ? $region_id : intval($_POST['shipping_province']);
            $region_id = empty($_POST['shipping_city']) ? $region_id : intval($_POST['shipping_city']);
            $region_id = empty($_POST['shipping_district']) ? $region_id : intval($_POST['shipping_district']);

            /* 添加选定的城市和地区 */
            $sql = "REPLACE INTO " . $ecs->table('area_region') . " (shipping_area_id, region_id) VALUES ('$area_id', '$region_id')";
            $db->query($sql);
        }
    }

    unset($modules);

    if (!empty($payment)) {
        /* 取相应插件信息 */
        $set_modules = true;
        include_once(ROOT_PATH . 'includes/modules/payment/' . $payment . '.php');

        $pay_config = array();
        if (isset($_REQUEST['cfg_value']) && is_array($_REQUEST['cfg_value'])) {
            for ($i = 0; $i < count($_POST['cfg_value']); $i++) {
                $pay_config[] = array('name' => trim($_POST['cfg_name'][$i]),
                    'type' => trim($_POST['cfg_type'][$i]),
                    'value' => trim($_POST['cfg_value'][$i])
                );
            }
        }

        $pay_config = serialize($pay_config);
        /* 安装，检查该支付方式是否曾经安装过 */
        $sql = "SELECT COUNT(*) FROM " . $ecs->table('payment') . " WHERE pay_code = '$payment'";
        if ($db->GetOne($sql) > 0) {
            $sql = "UPDATE " . $ecs->table('payment') .
                    " SET pay_config = '$pay_config'," .
                    " enabled = '1' " .
                    "WHERE pay_code = '$payment' LIMIT 1";
            $db->query($sql);
        } else {
//            $modules = read_modules('../includes/modules/payment');

            $payment_info = array();
            $payment_info['name'] = $_LANG[$modules[0]['code']];
            $payment_info['pay_fee'] = empty($modules[0]['pay_fee']) ? 0 : $modules[0]['pay_fee'];
            $payment_info['desc'] = $_LANG[$modules[0]['desc']];

            $sql = "INSERT INTO " . $ecs->table('payment') . " (pay_code, pay_name, pay_desc, pay_config, is_cod, pay_fee, enabled, is_online)" .
                    "VALUES ('$payment', '$payment_info[name]', '$payment_info[desc]', '$pay_config', '0', '$payment_info[pay_fee]', '1', '1')";
            $db->query($sql);
        }
    }

    clear_all_files();

    assign_query_info();

    $smarty->assign('ur_here', $_LANG['ur_add']);
    $smarty->display('setting_second.dwt');
}

/* ------------------------------------------------------ */
//-- 开店向导第三步
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'third') {
    admin_priv('goods_manage');

    $good_name = empty($_POST['good_name']) ? '' : $_POST['good_name'];
    $good_number = empty($_POST['good_number']) ? '' : $_POST['good_number'];
    $good_category = empty($_POST['good_category']) ? '' : $_POST['good_category'];
    $good_brand = empty($_POST['good_brand']) ? '' : $_POST['good_brand'];
    $good_price = empty($_POST['good_price']) ? 0 : $_POST['good_price'];
    $good_name = empty($_POST['good_name']) ? '' : $_POST['good_name'];
    $is_best = empty($_POST['is_best']) ? 0 : 1;
    $is_new = empty($_POST['is_new']) ? 0 : 1;
    $is_hot = empty($_POST['is_hot']) ? 0 : 1;
    $good_brief = empty($_POST['good_brief']) ? '' : $_POST['good_brief'];
    $market_price = $good_price * 1.2;

    if (!empty($good_category)) {
        if (cat_exists($good_category, 0)) {
            /* 同级别下不能有重复的分类名称 */
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['catname_exist'], 0, $link);
        }
    }

    if (!empty($good_brand)) {
        if (brand_exists($good_brand)) {
            /* 同级别下不能有重复的品牌名称 */
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['brand_name_exist'], 0, $link);
        }
    }

    $brand_id = 0;
    if (!empty($good_brand)) {
        $sql = 'INSERT INTO ' . $ecs->table('brand') . " (brand_name, is_show)" .
                " values('" . $good_brand . "', '1')";
        $db->query($sql);

        $brand_id = $db->insert_Id();
    }

    if (!empty($good_category)) {
        $sql = 'INSERT INTO ' . $ecs->table('category') . " (cat_name, parent_id, is_show)" .
                " values('" . $good_category . "', '0', '1')";
        $db->query($sql);

        $cat_id = $db->insert_Id();

        //货号
        require_once(ROOT_PATH . ADMIN_PATH . '/includes/lib_goods.php');
        $max_id = $db->getOne("SELECT MAX(goods_id) + 1 FROM " . $ecs->table('goods'));
        $goods_sn = generate_goods_sn($max_id);

        include_once(ROOT_PATH . 'includes/cls_image.php');
        $image = new cls_image($_CFG['bgcolor']);

        if (!empty($good_name)) {
            /* 检查图片：如果有错误，检查尺寸是否超过最大值；否则，检查文件类型 */
            if (isset($_FILES['goods_img']['error'])) { // php 4.2 版本才支持 error
                // 最大上传文件大小
                $php_maxsize = ini_get('upload_max_filesize');
                $htm_maxsize = '2M';

                // 商品图片
                if ($_FILES['goods_img']['error'] == 0) {
                    if (!$image->check_img_type($_FILES['goods_img']['type'])) {
                        sys_msg($_LANG['invalid_goods_img'], 1, array(), false);
                    }
                } elseif ($_FILES['goods_img']['error'] == 1) {
                    sys_msg(sprintf($_LANG['goods_img_too_big'], $php_maxsize), 1, array(), false);
                } elseif ($_FILES['goods_img']['error'] == 2) {
                    sys_msg(sprintf($_LANG['goods_img_too_big'], $htm_maxsize), 1, array(), false);
                }
            }
            /* 4。1版本 */ else {
                // 商品图片
                if ($_FILES['goods_img']['tmp_name'] != 'none') {
                    if (!$image->check_img_type($_FILES['goods_img']['type'])) {
                        sys_msg($_LANG['invalid_goods_img'], 1, array(), false);
                    }
                }
            }
            $goods_img = '';  // 初始化商品图片
            $goods_thumb = '';  // 初始化商品缩略图
            $original_img = '';  // 初始化原始图片
            $old_original_img = '';  // 初始化原始图片旧图
            // 如果上传了商品图片，相应处理
            if ($_FILES['goods_img']['tmp_name'] != '' && $_FILES['goods_img']['tmp_name'] != 'none') {

                $original_img = $image->upload_image($_FILES['goods_img']); // 原始图片
                if ($original_img === false) {
                    sys_msg($image->error_msg(), 1, array(), false);
                }
                $goods_img = $original_img;   // 商品图片

                /* 复制一份相册图片 */
                $img = $original_img;   // 相册图片
                $pos = strpos(basename($img), '.');
                $newname = dirname($img) . '/' . $image->random_filename() . substr(basename($img), $pos);
                if (!copy('../' . $img, '../' . $newname)) {
                    sys_msg('fail to copy file: ' . realpath('../' . $img), 1, array(), false);
                }
                $img = $newname;

                $gallery_img = $img;
                $gallery_thumb = $img;

                // 如果系统支持GD，缩放商品图片，且给商品图片和相册图片加水印
                if ($image->gd_version() > 0 && $image->check_img_function($_FILES['goods_img']['type'])) {
                    // 如果设置大小不为0，缩放图片
                    if ($_CFG['image_width'] != 0 || $_CFG['image_height'] != 0) {
                        $goods_img = $image->make_thumb('../' . $goods_img, $GLOBALS['_CFG']['image_width'], $GLOBALS['_CFG']['image_height']);
                        if ($goods_img === false) {
                            sys_msg($image->error_msg(), 1, array(), false);
                        }
                    }

                    $newname = dirname($img) . '/' . $image->random_filename() . substr(basename($img), $pos);
                    if (!copy('../' . $img, '../' . $newname)) {
                        sys_msg('fail to copy file: ' . realpath('../' . $img), 1, array(), false);
                    }
                    $gallery_img = $newname;

                    // 加水印
                    if (intval($_CFG['watermark_place']) > 0 && !empty($GLOBALS['_CFG']['watermark'])) {
                        if ($image->add_watermark('../' . $goods_img, '', $GLOBALS['_CFG']['watermark'], $GLOBALS['_CFG']['watermark_place'], $GLOBALS['_CFG']['watermark_alpha']) === false) {
                            sys_msg($image->error_msg(), 1, array(), false);
                        }

                        if ($image->add_watermark('../' . $gallery_img, '', $GLOBALS['_CFG']['watermark'], $GLOBALS['_CFG']['watermark_place'], $GLOBALS['_CFG']['watermark_alpha']) === false) {
                            sys_msg($image->error_msg(), 1, array(), false);
                        }
                    }

                    // 相册缩略图
                    if ($_CFG['thumb_width'] != 0 || $_CFG['thumb_height'] != 0) {
                        $gallery_thumb = $image->make_thumb('../' . $img, $GLOBALS['_CFG']['thumb_width'], $GLOBALS['_CFG']['thumb_height']);
                        if ($gallery_thumb === false) {
                            sys_msg($image->error_msg(), 1, array(), false);
                        }
                    }
                } else {
                    /* 复制一份原图 */
                    $pos = strpos(basename($img), '.');
                    $gallery_img = dirname($img) . '/' . $image->random_filename() . substr(basename($img), $pos);
                    if (!copy('../' . $img, '../' . $gallery_img)) {
                        sys_msg('fail to copy file: ' . realpath('../' . $img), 1, array(), false);
                    }
                    $gallery_thumb = '';
                }
            }
            // 未上传，如果自动选择生成，且上传了商品图片，生成所略图
            if (!empty($original_img)) {
                // 如果设置缩略图大小不为0，生成缩略图
                if ($_CFG['thumb_width'] != 0 || $_CFG['thumb_height'] != 0) {
                    $goods_thumb = $image->make_thumb('../' . $original_img, $GLOBALS['_CFG']['thumb_width'], $GLOBALS['_CFG']['thumb_height']);
                    if ($goods_thumb === false) {
                        sys_msg($image->error_msg(), 1, array(), false);
                    }
                } else {
                    $goods_thumb = $original_img;
                }
            }


            $sql = 'INSERT INTO ' . $ecs->table('goods') . "(goods_name, goods_sn, goods_number, cat_id, brand_id, goods_brief, shop_price, market_price, goods_img, goods_thumb, original_img,add_time, last_update,
                   is_best, is_new, is_hot)" .
                    "VALUES('$good_name', '$goods_sn', '$good_number', '$cat_id', '$brand_id', '$good_brief', '$good_price'," .
                    " '$market_price', '$goods_img', '$goods_thumb', '$original_img','" . gmtime() . "', '" . gmtime() . "', '$is_best', '$is_new', '$is_hot')";

            $db->query($sql);
            $good_id = $db->insert_id();
            /* 如果有图片，把商品图片加入图片相册 */
            if (isset($img)) {
                $sql = "INSERT INTO " . $ecs->table('goods_gallery') . " (goods_id, img_url, img_desc, thumb_url, img_original) " .
                        "VALUES ('$good_id', '$gallery_img', '', '$gallery_thumb', '$img')";
                $db->query($sql);
            }
        }
    }

    assign_query_info();
    //    $smarty->assign('ur_here', '开店向导－添加商品');
    $smarty->display('setting_third.dwt');
}


/*------------------------------------------------------ */
//-- 商家开店向导第一步
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'merchants_first')
{
    admin_priv('seller_store_informa');//by kong
    $smarty->assign('countries',    get_regions());
    $smarty->assign('provinces',    get_regions(1, 1));

    //获取入驻商家店铺信息 wang 商家入驻
    $sql="SELECT ss.*,sq.* FROM ".$ecs->table('seller_shopinfo')." AS ss ".
	" LEFT JOIN ".$ecs->table('seller_qrcode')." AS sq ON sq.ru_id = ss.ru_id ".
	" WHERE ss.ru_id = '".$adminru['ru_id']."' LIMIT 1"; //by wu

    $seller_shop_info = $db->getRow($sql);

    $action='add';
    if($seller_shop_info)
    {
            $action='update';
    }

    $smarty->assign('seller_notice',  $seller_shop_info['notice']);

    $shipping_list = warehouse_shipping_list();
    $smarty->assign('shipping_list',  $shipping_list);
    //获取店铺二级域名 by kong
    $domain_name=$db->getOne(" SELECT domain_name FROM".$ecs->table("seller_domain")." WHERE ru_id='".$adminru['ru_id']."'");
    $seller_shop_info['domain_name']=$domain_name;//by kong

    $smarty->assign('shop_info',$seller_shop_info);

    $shop_information = get_shop_name($adminru['ru_id']);
    $adminru['ru_id'] == 0 ? $shop_information['is_dsc'] = true : $shop_information['is_dsc'] = false;//判断当前商家是平台,还是入驻商家 bylu
    $smarty->assign('shop_information',$shop_information);

    $smarty->assign('cities',    get_regions(2, $seller_shop_info['province']));
    $smarty->assign('districts',    get_regions(3, $seller_shop_info['city']));

    $smarty->assign('data_op',$action);
    assign_query_info();

    $smarty->assign('ur_here', $_LANG['04_self_basic_info']);
    $smarty->display('seller_shop_first.dwt');
}

/*------------------------------------------------------ */
//-- 商家开店向导第二步
/*------------------------------------------------------ */

 elseif ($_REQUEST['act'] == 'merchants_second')
{
    $shop_name = empty($_POST['shop_name']) ? '' : htmlspecialchars(trim($_POST['shop_name']));
    $shop_title = empty($_POST['shop_title']) ? '' : htmlspecialchars(trim($_POST['shop_title']));
    $shop_keyword = empty($_POST['shop_keyword']) ? '' : htmlspecialchars(trim($_POST['shop_keyword']));
    $shop_country = empty($_POST['shop_country']) ? '' : intval($_POST['shop_country']);
    $shop_province = empty($_POST['shop_province']) ? '' : intval($_POST['shop_province']);
    $shop_city = empty($_POST['shop_city']) ? '' : intval($_POST['shop_city']);
    $shop_district = empty($_POST['shop_district']) ? '' : intval($_POST['shop_district']);
    $shipping_id = empty($_POST['shipping_id']) ? '' : intval($_POST['shipping_id']);
    $shop_address = empty($_POST['shop_address']) ? '' : htmlspecialchars(trim($_POST['shop_address']));
    $mobile = empty($_POST['mobile']) ? '' : trim($_POST['mobile']); //by wu
    $seller_email = empty($_POST['seller_email']) ? '' : htmlspecialchars(trim($_POST['seller_email']));
    $street_desc = empty($_POST['street_desc']) ? '' : htmlspecialchars(trim($_POST['street_desc']));
    $kf_qq = empty($_POST['kf_qq']) ? '' : $_POST['kf_qq'];
    $kf_ww = empty($_POST['kf_ww']) ? '' : $_POST['kf_ww'];
    $kf_im_switch = empty($_POST['kf_im_switch']) ? 0 : $_POST['kf_im_switch']; //IM在线客服开关 bylu
    $kf_touid = empty($_POST['kf_touid']) ? '' : $_POST['kf_touid']; //客服账号 bylu
    $kf_appkey = empty($_POST['kf_appkey']) ? 0 : $_POST['kf_appkey']; //appkey bylu
    $kf_secretkey = empty($_POST['kf_secretkey']) ? 0 : $_POST['kf_secretkey']; //secretkey bylu
    $kf_logo = empty($_POST['kf_logo']) ? 'http://' : $_POST['kf_logo']; //头像 bylu
    $kf_welcomeMsg = empty($_POST['kf_welcomeMsg']) ? '' : $_POST['kf_welcomeMsg']; //欢迎语 bylu
    $meiqia = empty($_POST['meiqia']) ? '' : $_POST['meiqia']; //美洽客服
    $kf_type = empty($_POST['kf_type']) ? '' : intval($_POST['kf_type']);
    $kf_tel = empty($_POST['kf_tel']) ? '' : $_POST['kf_tel'];
    $notice = empty($_POST['notice']) ? '' : $_POST['notice'];
    $data_op = empty($_POST['data_op']) ? '' : $_POST['data_op'];
    $check_sellername = empty($_POST['check_sellername']) ? 0 : intval($_POST['check_sellername']);
    $shop_style = intval($_POST['shop_style']);
    $domain_name = empty($_POST['domain_name']) ? '' : $_POST['domain_name'];
    $js_appkey = empty($_POST['js_appkey']) ? '' : $_POST['js_appkey']; //扫码appkey
    $js_appsecret = empty($_POST['js_appsecret']) ? '' : $_POST['js_appsecret']; //扫码appsecret
    $print_type = empty($_POST['print_type']) ? 0 : intval($_POST['print_type']); //打印方式
	$kdniao_printer = empty($_POST['kdniao_printer']) ? '' : $_POST['kdniao_printer']; //打印机

    $region_info = get_region_info($shop_city);
    if($region_info && $shop_province != $region_info['parent_id']){
        $shop_city = 0;
        $shop_district = 0;
    }

    //判断域名是否存在  by kong
    if (!empty($domain_name)) {
        $sql = " SELECT count(id) FROM " . $ecs->table("seller_domain") . " WHERE domain_name = '" . $domain_name . "' AND ru_id !='" . $adminru['ru_id'] . "'";
        if ($db->getOne($sql) > 0) {
            $lnk[] = array('text' => '返回首页', 'href' => 'index.php?act=main');
            sys_msg('域名已存在', 0, $lnk);
        }
    }

    $seller_domain = array(
        'ru_id' => $adminru['ru_id'],
        'domain_name' => $domain_name,
    );

    //平台同步设置手机，邮箱 by wu start  by kong 改
    if ($adminru['ru_id'] == 0) {
        $update_arr = array(
            'sms_shop_mobile' => $mobile, //手机
            'service_email' => $seller_email, //邮箱
            'qq' => $kf_qq, //QQ
            'ww' => $kf_ww, //旺旺
            'shop_title' => $shop_title, //商店标题
            'shop_keywords' => $shop_keyword, //商店关键字
            'shop_country' => $shop_country, //国家
            'shop_province' => $shop_province, //省份
            'shop_city' => $shop_city, //城市
            'shop_address' => $shop_address, //地址
            'service_phone' => $kf_tel, //客服电话
            'shop_notice' => $notice //店铺公告
        );
        foreach ($update_arr as $key => $val) {
            $sql = "UPDATE " . $GLOBALS['ecs']->table('shop_config') . " SET value = '" . $val . "' WHERE code='" . $key . "'";
            $GLOBALS['db']->query($sql);
        }
    }

    clear_all_files('', ADMIN_PATH);
    //平台同步设置手机，邮箱 by wu end

    $shop_info = array(
        'ru_id' => $adminru['ru_id'],
        'shop_name' => $shop_name,
        'shop_title' => $shop_title,
        'shop_keyword' => $shop_keyword,
        'country' => $shop_country,
        'province' => $shop_province,
        'city' => $shop_city,
        'district' => $shop_district,
        'shipping_id' => $shipping_id,
        'shop_address' => $shop_address,
        'mobile' => $mobile,
        'seller_email' => $seller_email,
        'kf_qq' => $kf_qq,
        'kf_ww' => $kf_ww,
        'kf_appkey' => $kf_appkey, // bylu
        'kf_secretkey' => $kf_secretkey, // bylu
        'kf_touid' => $kf_touid, // bylu
        'kf_logo' => $kf_logo, // bylu
        'kf_welcomeMsg' => $kf_welcomeMsg, // bylu
        'kf_im_switch' => $kf_im_switch, // IM在线客服开关 bylu
        'meiqia' => $meiqia,
        'kf_type' => $kf_type,
        'kf_tel' => $kf_tel,
        'notice' => $notice,
        'street_desc' => $street_desc,
        'shop_style' => $shop_style,
        'check_sellername' => $check_sellername,
        'js_appkey' => $js_appkey, //扫码appkey
        'js_appsecret' => $js_appsecret, //扫码appsecret
        'print_type' => $print_type,
        'kdniao_printer' => $kdniao_printer
    );

    $sql = "SELECT ss.shop_logo, ss.logo_thumb, ss.street_thumb, ss.brand_thumb, sq.qrcode_thumb FROM " . $ecs->table('seller_shopinfo') . " as ss " .
            " left join " . $ecs->table('seller_qrcode') . " as sq on sq.ru_id=ss.ru_id " .
            " WHERE ss.ru_id='" . $adminru['ru_id'] . "'"; //by wu
    $store = $db->getRow($sql);

    /* 允许上传的文件类型 */
    $allow_file_types = '|GIF|JPG|PNG|BMP|';

    if ($_FILES['shop_logo']) {
        $file = $_FILES['shop_logo'];
        /* 判断用户是否选择了文件 */
        if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none')) {
            /* 检查上传的文件类型是否合法 */
            if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types)) {
                sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
            } else {

                if ($file['name']) {
                    $ext = explode('.', $file['name']);
                    $ext = array_pop($ext);
                } else {
                    $ext = "";
                }

                $file_name = '../seller_imgs/seller_logo/seller_logo' . $adminru['ru_id'] . '.' . $ext;
                /* 判断是否上传成功 */
                if (move_upload_file($file['tmp_name'], $file_name)) {
                    $shop_info['shop_logo'] = $file_name;
                } else {
                    sys_msg(sprintf($_LANG['msg_upload_failed'], $file['name'], '../seller_imgs/seller_' . $adminru['ru_id']));
                }
            }
        }
    }

    $del_logo_thumb = '';
    if ($_FILES['logo_thumb']) {
        $file = $_FILES['logo_thumb'];
        /* 判断用户是否选择了文件 */
        if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none')) {
            /* 检查上传的文件类型是否合法 */
            if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types)) {
                sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
            } else {

                if ($file['name']) {
                    $ext = explode('.', $file['name']);
                    $ext = array_pop($ext);
                } else {
                    $ext = "";
                }

                $file_name = '../seller_imgs/seller_logo/logo_thumb/logo_thumb' . $adminru['ru_id'] . '.' . $ext;
                /* 判断是否上传成功 */
                if (move_upload_file($file['tmp_name'], $file_name)) {
                    include_once(ROOT_PATH . '/includes/cls_image.php');
                    $image = new cls_image($_CFG['bgcolor']);

                    $goods_thumb = $image->make_thumb($file_name, 120, 120, "../seller_imgs/seller_logo/logo_thumb/");
                    $shop_info['logo_thumb'] = $goods_thumb;

                    if (!empty($goods_thumb)) {
                        if ($store['logo_thumb']) {
                            $store['logo_thumb'] = str_replace('../', '', $store['logo_thumb']);
                            $del_logo_thumb = $store['logo_thumb'];
                        }
                        @unlink(ROOT_PATH . $del_logo_thumb);
                    }
                } else {
                    sys_msg(sprintf($_LANG['msg_upload_failed'], $file['name'], 'seller_imgs/logo_thumb_' . $adminru['ru_id']));
                }
            }
        }
    }

    $street_thumb = $image->upload_image($_FILES['street_thumb'], 'store_street/street_thumb');  //图片存放地址 -- data/septs_Image
    $brand_thumb = $image->upload_image($_FILES['brand_thumb'], 'store_street/brand_thumb');  //图片存放地址 -- data/septs_Image

    $domain_id = $db->getOne("SELECT id FROM " . $ecs->table('seller_domain') . " WHERE ru_id ='" . $adminru['ru_id'] . "'"); //by kong
    /* 二级域名绑定  by kong  satrt */
    if ($domain_id > 0) {
        $db->autoExecute($ecs->table('seller_domain'), $seller_domain, 'UPDATE', "ru_id='" . $adminru['ru_id'] . "'");
    } else {
        $db->autoExecute($ecs->table('seller_domain'), $seller_domain, 'INSERT');
    }
    /* 二级域名绑定  by kong  end */
    //二维码中间logo by wu start
    if ($_FILES['qrcode_thumb']) {
        $file = $_FILES['qrcode_thumb'];
        /* 判断用户是否选择了文件 */
        if ((isset($file['error']) && $file['error'] == 0) || (!isset($file['error']) && $file['tmp_name'] != 'none')) {
            /* 检查上传的文件类型是否合法 */
            if (!check_file_type($file['tmp_name'], $file['name'], $allow_file_types)) {
                sys_msg(sprintf($_LANG['msg_invalid_file'], $file['name']));
            } else {
                $ext = array_pop(explode('.', $file['name']));
                $file_name = '../seller_imgs/seller_qrcode/qrcode_thumb/qrcode_thumb' . $adminru['ru_id'] . '.' . $ext;
                /* 判断是否上传成功 */
                if (move_upload_file($file['tmp_name'], $file_name)) {
                    include_once(ROOT_PATH . '/includes/cls_image.php');
                    $image = new cls_image($_CFG['bgcolor']);

                    $qrcode_thumb = $image->make_thumb($file_name, 120, 120, "../seller_imgs/seller_qrcode/qrcode_thumb/");
                    //$shop_info['qrcode_thumb']=$qrcode_thumb;

                    if (!empty($qrcode_thumb)) {
                        if ($store['qrcode_thumb']) {
                            $store['qrcode_thumb'] = str_replace('../', '', $store['qrcode_thumb']);
                            $del_logo_thumb = $store['qrcode_thumb'];
                        }
                        @unlink(ROOT_PATH . $del_logo_thumb);
                    }
                    /* 保存 */
                    $sql = " select * from " . $GLOBALS['ecs']->table('seller_qrcode') . " where ru_id='" . $adminru['ru_id'] . "' limit 1";
                    $qrinfo = $GLOBALS['db']->getRow($sql);
                    if (empty($qrinfo)) {
                        $sql = " insert into " . $GLOBALS['ecs']->table('seller_qrcode') . " (ru_id,qrcode_thumb) " .
                                " values " .
                                "('" . $adminru['ru_id'] . "','" . $qrcode_thumb . "')";
                        $GLOBALS['db']->query($sql);
                    } else {
                        $sql = " update " . $GLOBALS['ecs']->table('seller_qrcode') . " set ru_id='" . $adminru['ru_id'] . "', " .
                                " qrcode_thumb='" . $qrcode_thumb . "' ";
                        $GLOBALS['db']->query($sql);
                    }
                } else {
                    sys_msg(sprintf($_LANG['msg_upload_failed'], $file['name'], 'seller_imgs/qrcode_thumb_' . $adminru['ru_id']));
                }
            }
        }
    }
    //二维码中间logo by wu end

    $shop_logo = '';
    if ($shop_info['shop_logo']) {
        $shop_logo = str_replace('../', '', $shop_info['shop_logo']);
    }

    $add_logo_thumb = '';
    if ($shop_info['logo_thumb']) {
        $add_logo_thumb = str_replace('../', '', $shop_info['logo_thumb']);
    }

    get_oss_add_file(array($street_thumb, $brand_thumb, $shop_logo, $add_logo_thumb));

    if ($data_op == 'add') {
        $shop_info['street_thumb'] = $street_thumb;
        $shop_info['brand_thumb'] = $brand_thumb;

        if (!$store) {

            $db->autoExecute($ecs->table('seller_shopinfo'), $shop_info, 'INSERT');
        }

        $lnk[] = array('text' => '返回上一步', 'href' => 'index.php?act=merchants_first');
        sys_msg('添加店铺信息成功', 0, $lnk);
    } else {
        $sql = "select check_sellername from " . $ecs->table('seller_shopinfo') . " where ru_id = '" . $adminru['ru_id'] . "'";
        $seller_shop_info = $db->getRow($sql);

        if ($adminru['ru_id'] > 0 && $seller_shop_info['check_sellername'] != $check_sellername) {
            $shop_info['shopname_audit'] = 0;
        }

        $oss_street_thumb = '';
        if (!empty($street_thumb)) {
            $oss_street_thumb = $store['street_thumb'];
            $shop_info['street_thumb'] = $street_thumb;
            @unlink(ROOT_PATH . $oss_street_thumb);
        }

        $oss_brand_thumb = '';
        if (!empty($brand_thumb)) {
            $oss_brand_thumb = $store['brand_thumb'];
            $shop_info['brand_thumb'] = $brand_thumb;
            @unlink(ROOT_PATH . $oss_brand_thumb);
        }

        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $bucket_info = get_bucket_info();
            $urlip = get_ip_url($GLOBALS['ecs']->url());
            $url = $urlip . "oss.php?act=del_file";
            $Http = new Http();
            $post_data = array(
                'bucket' => $bucket_info['bucket'],
                'keyid' => $bucket_info['keyid'],
                'keysecret' => $bucket_info['keysecret'],
                'is_cname' => $bucket_info['is_cname'],
                'endpoint' => $bucket_info['outside_site'],
                'object' => array(
                    $oss_street_thumb,
                    $oss_brand_thumb,
                    $del_logo_thumb
                )
            );

            $Http->doPost($url, $post_data);
        }
        //OSS文件存储ecmoban模板堂 --zhuo end

        $db->autoExecute($ecs->table('seller_shopinfo'), $shop_info, 'UPDATE', "ru_id='" . $adminru['ru_id'] . "'");
        $lnk[] = array('text' => '返回上一步', 'href' => 'index.php?act=merchants_first');
        sys_msg('更新店铺信息成功', 0, $lnk);
    }
}

/* ------------------------------------------------------ */
//-- 关于 DSC
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'about_us') {
    assign_query_info();
    $smarty->display('about_us.dwt');
}

/* ------------------------------------------------------ */
//-- 拖动的帧
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'drag') {
    $smarty->display('drag.dwt');
    ;
}

/* ------------------------------------------------------ */
//-- 检查订单
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'check_order') {
    //生产周期快到通知
    order_production_cycle_notification();

    $firstSecToday = local_mktime(0, 0, 0, date("m"), date("d"), date("Y"));
    $lastSecToday = local_mktime(0, 0, 0, date("m"), date("d")+1, date("Y"))-1;
    if (empty($_SESSION['last_check'])) {
        $_SESSION['last_check'] = gmtime();
        make_json_result('', '', array('new_orders' => 0, 'new_paid' => 0));
    }

    //ecmoban模板堂 --zhuo
    $where = "";
    $where = " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id limit 0, 1) = '" .$adminru['ru_id']. "' ";
    $where .= " AND (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 WHERE oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
    $where .= " AND o.shipping_status = " . SS_UNSHIPPED;

    if(admin_priv('order_view', '', false)){
        /* 新订单 */
        $sql = 'SELECT COUNT(*) FROM ' . $ecs->table('order_info') . " as o" .
                " WHERE o.add_time >= " . $firstSecToday . " AND o.add_time <= " .$lastSecToday. $where;
        $arr['new_orders'] = $db->getOne($sql);

        /* 新付款的订单 */
        $sql = 'SELECT COUNT(*) FROM ' . $ecs->table('order_info') . " as o " .
                ' WHERE o.pay_time >= ' . $firstSecToday . " AND o.pay_time <= " .$lastSecToday. $where;
        $arr['new_paid'] = $db->getOne($sql);

        /* 待发货订单 */
        $where_og = "";
        $where_og .= " AND (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = oi.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
        $sql = 'SELECT count(*) FROM ' . $ecs->table('order_info') . " AS oi " .
                " WHERE 1 " . order_query_sql('await_ship') . $where_og;
        $arr['no_paid'] = $db->getOne($sql);
    }

    if(admin_priv('order_back_apply', '', false)){
        /* 待处理退换货订单 */
        $sql = "SELECT count(*) FROM " .
                $GLOBALS['ecs']->table('order_return') . " WHERE is_check = '0' AND agree_apply = '0' AND return_status = '0' AND refound_status = '0' AND return_type NOT IN('0')";
        $arr['no_change'] = $db->getOne($sql);
    }

    if(admin_priv('users_merchants', '', false)){
        /* 最新入驻商家 未审核商家 */
        $sql = 'SELECT count(*) FROM ' .
                $GLOBALS['ecs']->table('merchants_shop_information') . ' WHERE merchants_audit = 0';
        $arr['shop_account'] = $db->getOne($sql);

        /* 未审核店铺信息 */
        $sql = "SELECT COUNT(*) FROM " .
                $GLOBALS['ecs']->table('merchants_shop_information') . " as mis " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('users') . " as u on mis.user_id = u.user_id " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('seller_shopinfo') . " as ss on mis.user_id = ss.ru_id " .
                " WHERE ss.review_status = 1";
        $arr['shopinfo_account'] = $db->getOne($sql);
    }

    if(admin_priv('users_real_manage', '', false)){
        /* 会员实名认证 未审核会员 */
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('users_real') ."AS ur "
                    . " JOIN ". $GLOBALS['ecs']->table('users') ." AS u ON ur.user_id = u.user_id AND review_status = 0 AND user_type = 0";
        $arr['user_account'] = $db->getOne($sql);
        /* 会员实名认证 未审核商家 */
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('users_real') ."AS ur "
                    . " JOIN ". $GLOBALS['ecs']->table('users') ." AS u ON ur.user_id = u.user_id AND review_status = 0 AND user_type = 1";
        $arr['seller_account'] = $db->getOne($sql);
    }

    if(admin_priv('seller_account', '', false)){
        /* 待审核商家提现 */
        $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('seller_account_log') . " AS sal " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS mis ON sal.ru_id = mis.user_id " .
                " WHERE sal.log_type IN(1,4) AND sal.is_paid = 0 ";
        $arr['wait_cash'] = $GLOBALS['db']->getOne($sql);
    }

    if(admin_priv('ad_manage', '', false)){
        /* 广告位到期 */
        $now_time = gmtime();
        $sql = "SELECT count(*) FROM " .
                $GLOBALS['ecs']->table('ad') . " WHERE " . $now_time . " BETWEEN (end_time - 3600 * 24 * 3) AND end_time";
        $arr['advance_date'] = $db->getOne($sql);
    }

    if(admin_priv('goods_report', '', false)){
        //未处理商品投诉
        $sql = " SELECT COUNT(*) FROM".$ecs->table('goods_report')."WHERE report_state = 0";
        $arr['goods_report'] = $db->getOne($sql);
    }

    if(admin_priv('complaint', '', false)){
        //为完成仲裁
        $sql = " SELECT COUNT(*) FROM".$ecs->table('complaint')."WHERE complaint_state != 4";
        $arr['complaint'] = $db->getOne($sql);
    }

    $_SESSION['last_check'] = gmtime();

    $_SESSION['firstSecToday'] = $firstSecToday;
    $_SESSION['lastSecToday'] = $lastSecToday;

    if (!(is_numeric($arr['new_orders']) && is_numeric($arr['new_paid']))) {
        make_json_error($db->error());
    } else {
        make_json_result('', '', $arr);
    }
}

/* ------------------------------------------------------ */
//-- 检查商家账单是否生成
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'check_bill') {
    make_json_result('', '', $result);
    exit;
    /* by zxk jisuan */
    //取消商家账单生成
    /* by zxk jisuan */
    $checkbill_number = isset($GLOBALS['_CFG']['checkbill_number']) && !empty($GLOBALS['_CFG']['checkbill_number']) ? $GLOBALS['_CFG']['checkbill_number'] : 10;

    $day_time = local_date("Y-m-d", gmtime());
    $checkbil_array = array(
        $day_time => array(
            $adminru['ru_id'] => array(
                'checkbill_number' => 1
            )
        )
    );

    $cfg_checkbill = read_static_cache('checkbill_number_' . $adminru['ru_id'], '/data/sc_file/seller_bill/');
    if ($cfg_checkbill === false) {
        write_static_cache('checkbill_number_' . $adminru['ru_id'], $checkbil_array, '/data/sc_file/seller_bill/');
    } else {

        if(count($cfg_checkbill) >= 7){
            dsc_unlink(ROOT_PATH . DATA_DIR . "/sc_file/seller_bill/checkbill_number_" .$adminru['ru_id']. ".php");

            $cfg_checkbill = array(
                $day_time => array(
                    'checkbill_number' => $cfg_checkbill[$day_time][$adminru['ru_id']]['checkbill_number']
                )
            );
        }

        if($cfg_checkbill[$day_time][$adminru['ru_id']]['checkbill_number'] < $checkbill_number){
            $cfg_checkbill[$day_time][$adminru['ru_id']]['checkbill_number'] += 1;

            write_static_cache('checkbill_number_' . $adminru['ru_id'], $cfg_checkbill, '/data/sc_file/seller_bill/');
        }
    }

    /**
     * 判断是否异步加载过规定次数
     * 默认是10次
     */
    if($cfg_checkbill !== false && $cfg_checkbill[$day_time][$adminru['ru_id']]['checkbill_number'] >= $checkbill_number){
        $is_check_bill = 0; //停止加载
    }else{
        $is_check_bill = 1; //启动加载
    }

    if ($is_check_bill) {
        $result = array();
        $seller_id = isset($_REQUEST['seller_id']) && !empty($_REQUEST['seller_id']) ? intval($_REQUEST['seller_id']) : 0;

        if ($seller_id) {
            $sql = "SELECT u.user_id AS seller_id, IFNULL(s.cycle, 0) AS cycle, p.percent_value FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS u " .
                    " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_server') . " AS s ON u.user_id = s.user_id" .
                    " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_percent') . " AS p ON s.suppliers_percent = p.percent_id" .
                    " WHERE u.user_id = '$seller_id'";
            $seller_list = $GLOBALS['db']->getAll($sql);
        } else {
            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " WHERE merchants_audit = 1";
            $count = $GLOBALS['db']->getOne($sql);

            $seller_list = read_static_cache('seller_list', '/data/sc_file/');
            $cache_count = count($seller_list);

            $is_cache = 0;
            if ($count > $cache_count) {
                dsc_unlink('/data/sc_file/seller_list.php');
                $is_cache = 1;
            }

            if ($is_cache == 1 || $seller_list === false) {
                $seller_list = get_cache_seller_list();
            }
        }

        $last_year_start = 0;
        $last_year_end = 0;

        $notime = gmtime();
        $year = local_date("Y", $notime); //当前年份

        $now_date = local_date("Y-m-d", $notime); //当前年月份
        $year_exp = explode("-", $now_date);
        $nowYear = intval($year_exp[0]); //当前年份
        $nowMonth = intval($year_exp[1]); //当前月份
        $nowDay = intval($year_exp[2]); //当前日期

        foreach ($seller_list as $key => $row) {

            $day_array = array();
            $is_charge = 1;

            /* 按天数 */
            if ($row['cycle'] == 7) {

                $sql = "SELECT MAX(end_time) FROM " . $GLOBALS['ecs']->table('seller_commission_bill') . " WHERE seller_id = '" . $row['seller_id'] . "' AND bill_cycle = '" . $row['cycle'] . "' LIMIT 1";
                $end_time = $GLOBALS['db']->getOne($sql);

                if ($end_time) {
                    $row['bill_time'] = $end_time;
                }

                $last_year_start = local_date("Y-m-d 00:00:00", $row['bill_time']);
                $bill_time = $row['bill_time'] + ($row['day_number'] - 1) * 24 * 60 * 60;
                $last_year_end = local_date("Y-m-d 23:59:59", $bill_time);

                $thistime = gmtime();
                $bill_end_time = local_strtotime($last_year_end);

                if ($thistime <= $bill_end_time) {
                    $is_charge = 0;
                }
            }

            /* 按年 */
            elseif ($row['cycle'] == 6) {

                $day_array = get_bill_one_year($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    $last_year_start = ($year - 1) . "-01-01 00:00:00"; //去年开始的第一天
                    $last_year_end = ($year - 1) . "-12-31 23:59:59";   //去年结束的最后一天

                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 6个月 */
            elseif ($row['cycle'] == 5) {

                $day_array = get_bill_half_year($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    /* 判断当前月份是否大于6月份，是否已是七月份 */
                    if ($nowMonth > 6) {

                        $last_year_start = $year . "-01-01 00:00:00"; //当前年份开始的第一天
                        $last_year_end = $year . "-06-30 23:59:59";   //当前年份结束的最后一天
                    } else {

                        /* 获取去年下半年的时间段 */
                        $lastYear = $nowYear - 1;

                        $last_year_start = $lastYear . "-07-01 00:00:00"; //去年后半年开始的第一天
                        $last_year_end = $lastYear . "-12-31 23:59:59";   //后半年结束的最后一天
                    }

                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 1个季度 */
            elseif ($row['cycle'] == 4) {
                $day_array = get_bill_quarter($row['seller_id'], $row['cycle']);
                if (empty($day_array)) {
                    if ($nowMonth > 3 && $nowMonth <= 6) {
                        /* 当前第一季度时间段 */
                        $last_year_start = $nowYear . "-01-01 00:00:00"; //当前第一季度开始的第一天
                        $last_year_end = $nowYear . "-03-31 23:59:59";   //当前第一季度结束的最后一天
                    } elseif ($nowMonth > 6 && $nowMonth <= 9) {
                        /* 当前第二季度时间段 */
                        $last_year_start = $nowYear . "-04-01 00:00:00"; //当前第二季度开始的第一天
                        $last_year_end = $nowYear . "-06-30 23:59:59";   //当前第二季度结束的最后一天
                    } elseif ($nowMonth > 9 && $nowMonth <= 12) {
                        /* 当前第三季度时间段 */
                        $last_year_start = $nowYear . "-07-01 00:00:00"; //当前第三季度开始的第一天
                        $last_year_end = $nowYear . "-09-30 23:59:59";   //当前第三季度结束的最后一天
                    } elseif ($nowMonth <= 3) {
                        /* 当前第四季度时间段 */
                        $last_year_start = $nowYear - 1 . "-10-01 00:00:00"; //当前第四季度开始的第一天
                        $last_year_end = $nowYear - 1 . "-12-31 23:59:59";   //当前第四季度结束的最后一天
                    }

                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 1个月 */
            elseif ($row['cycle'] == 3) {
                $day_array = get_bill_one_month($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {

                    $nowMonth = $nowMonth - 1;

                    /* 获取当月天数 */
                    $days = cal_days_in_month(CAL_GREGORIAN, $nowMonth, $nowYear);

                    if ($nowMonth <= 9) {
                        $nowMonth = "0" . $nowMonth;
                    }

                    $last_year_start = $nowYear . "-" . $nowMonth . "-01 00:00:00"; //上一个月的第一天
                    $last_year_end = $nowYear . "-" . $nowMonth . "-" . $days . " 23:59:59"; //上一个月的最后一天

                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 15天（半个月） */
            elseif ($row['cycle'] == 2) {
                $day_array = get_bill_half_month($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    $lastDay = local_date('Y-m-t');
                    $lastDay = explode("-", $lastDay);
                    $halfDay = intval($lastDay[2] / 2);

                    if ($nowDay > $halfDay) {
                        $last_year_start = $lastDay[0] . "-" . $lastDay[1] . "-01 00:00:00"; //当前月开始的第一天
                        $last_year_end = $lastDay[0] . "-" . $lastDay[1] . "-" . $halfDay . " 23:59:59"; //当前月开始的第一天
                    } else {
                        $lastMonth_firstDay = $nowYear . "-" . $nowMonth . "-01 00:00:00";
                        $lastMonth_lastDay = local_date('Y-m-d', local_strtotime("$lastMonth_firstDay +1 month -1 day")) . " 23:59:59";

                        $lastMonth = local_date('Y-m-d', local_strtotime("$lastMonth_firstDay +1 month -1 day"));
                        $lastMonth = explode("-", $lastMonth);
                        $halfMonth = intval($lastMonth[2] / 2);
                        $middleMonth = $lastMonth[0] . "-" . $lastMonth[1] . "-" . ($halfMonth + 1);

                        $middleMonth_lastDay = $middleMonth . " 23:59:59";
                        $middleMonth_firstDay = $middleMonth . " 00:00:00";
                        $last_year_start = $middleMonth_firstDay;   //当前月月中的天数日期
                        $last_year_end = $lastMonth_lastDay;    //上一个月的最后一天（以当前是5月15号之前运算）
                    }
                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 七天(按一个礼拜) */
            elseif ($row['cycle'] == 1) {
                $day_array = get_bill_seven_day($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    $week = local_date('w'); //当前月的日期本周
                    $thisWeekMon = local_strtotime('+' . 1 - $week . ' days'); //本周礼拜一
                    $lastWeekMon = 7 * 24 * 60 * 60; //上个礼拜一的时间
                    $lastWeeksun = 1 * 24 * 60 * 60; //上个礼拜日的时间

                    $lastWeekMon = $thisWeekMon - $lastWeekMon;
                    $lastWeeksun = $thisWeekMon - $lastWeeksun;

                    $last_year_start = local_date('Y-m-d 00:00:00', $lastWeekMon);
                    $last_year_end = local_date('Y-m-d 23:59:59', $lastWeeksun);
                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            /* 每天 */
            else {
                $day_array = get_bill_per_day($row['seller_id'], $row['cycle']);

                if (empty($day_array)) {
                    $last_year_start = local_date("Y-m-d 00:00:00", local_strtotime("-1 day"));
                    $last_year_end = local_date("Y-m-d 23:59:59", local_strtotime("-1 day"));
                    $day_array[0]['last_year_start'] = $last_year_start;
                    $day_array[0]['last_year_end'] = $last_year_end;
                }
            }

            if ($day_array) {
                foreach ($day_array as $keys => $rows) {
                    $last_year_start = local_strtotime($rows['last_year_start']); //时间戳
                    $last_year_end = local_strtotime($rows['last_year_end']); //时间戳

                    $sql = "SELECT id FROM " . $GLOBALS['ecs']->table('seller_commission_bill') . " WHERE seller_id = '" . $row['seller_id'] . "' AND bill_cycle = '" . $row['cycle'] . "'" .
                            " AND start_time >= '$last_year_start' AND end_time <= '$last_year_end'";
                    $bill_id = $GLOBALS['db']->getOne($sql, true);

                    if (!$bill_id && $is_charge == 1 && ($last_year_start > 0 && $last_year_end > 0 && $last_year_start < $last_year_end)) {

                        $bill_sn = get_order_sn();

                        $other = array(
                            'seller_id' => $row['seller_id'],
                            'bill_sn' => $bill_sn,
                            'proportion' => $row['percent_value'],
                            'start_time' => $last_year_start,
                            'end_time' => $last_year_end,
                            'bill_cycle' => $row['cycle'],
                            'operator' => $_SESSION['admin_name']
                        );

                        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('seller_commission_bill'), $other, 'INSERT');
                    }
                }
            }
        }
    }

    make_json_result('', '', $result);
}

/* ------------------------------------------------------ */
//-- Totolist操作
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'save_todolist') {
    $content = json_str_iconv($_POST["content"]);
    $sql = "UPDATE" . $GLOBALS['ecs']->table('admin_user') . " SET todolist='" . $content . "' WHERE user_id = " . $_SESSION['admin_id'];
    $GLOBALS['db']->query($sql);
} elseif ($_REQUEST['act'] == 'get_todolist') {
    $sql = "SELECT todolist FROM " . $GLOBALS['ecs']->table('admin_user') . " WHERE user_id = " . $_SESSION['admin_id'];
    $content = $GLOBALS['db']->getOne($sql);
    echo $content;
}
// 邮件群发处理
elseif ($_REQUEST['act'] == 'send_mail') {
    if ($_CFG['send_mail_on'] == 'off') {
        make_json_result('', $_LANG['send_mail_off'], 0);
        exit();
    }
    $sql = "SELECT * FROM " . $ecs->table('email_sendlist') . " ORDER BY pri DESC, last_send ASC LIMIT 1";
    $row = $db->getRow($sql);

    //发送列表为空
    if (empty($row['id'])) {
        make_json_result('', $_LANG['mailsend_null'], 0);
    }

    //发送列表不为空，邮件地址为空
    if (!empty($row['id']) && empty($row['email'])) {
        $sql = "DELETE FROM " . $ecs->table('email_sendlist') . " WHERE id = '$row[id]'";
        $db->query($sql);
        $count = $db->getOne("SELECT COUNT(*) FROM " . $ecs->table('email_sendlist'));
        make_json_result('', $_LANG['mailsend_skip'], array('count' => $count, 'goon' => 1));
    }

    //查询相关模板
    $sql = "SELECT * FROM " . $ecs->table('mail_templates') . " WHERE template_id = '$row[template_id]'";
    $rt = $db->getRow($sql);

    //如果是模板，则将已存入email_sendlist的内容作为邮件内容
    //否则即是杂质，将mail_templates调出的内容作为邮件内容
    if ($rt['type'] == 'template') {
        $rt['template_content'] = $row['email_content'];
    }

    if ($rt['template_id'] && $rt['template_content']) {
        if (send_mail('', $row['email'], $rt['template_subject'], $rt['template_content'], $rt['is_html'])) {
            //发送成功
            //从列表中删除
            $sql = "DELETE FROM " . $ecs->table('email_sendlist') . " WHERE id = '$row[id]'";
            $db->query($sql);

            //剩余列表数
            $count = $db->getOne("SELECT COUNT(*) FROM " . $ecs->table('email_sendlist'));

            if ($count > 0) {
                $msg = sprintf($_LANG['mailsend_ok'], $row['email'], $count);
            } else {
                $msg = sprintf($_LANG['mailsend_finished'], $row['email']);
            }
            make_json_result('', $msg, array('count' => $count));
        } else {
            //发送出错

            if ($row['error'] < 3) {
                $time = time();
                $sql = "UPDATE " . $ecs->table('email_sendlist') . " SET error = error + 1, pri = 0, last_send = '$time' WHERE id = '$row[id]'";
            } else {
                //将出错超次的纪录删除
                $sql = "DELETE FROM " . $ecs->table('email_sendlist') . " WHERE id = '$row[id]'";
            }
            $db->query($sql);

            $count = $db->getOne("SELECT COUNT(*) FROM " . $ecs->table('email_sendlist'));
            make_json_result('', sprintf($_LANG['mailsend_fail'], $row['email']), array('count' => $count));
        }
    } else {
        //无效的邮件队列
        $sql = "DELETE FROM " . $ecs->table('email_sendlist') . " WHERE id = '$row[id]'";
        $db->query($sql);
        $count = $db->getOne("SELECT COUNT(*) FROM " . $ecs->table('email_sendlist'));
        make_json_result('', sprintf($_LANG['mailsend_fail'], $row['email']), array('count' => $count));
    }
}

/* ------------------------------------------------------ */
//-- license操作
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'license') {
    $is_ajax = $_GET['is_ajax'];

    if (isset($is_ajax) && $is_ajax) {
        // license 检查
        include_once(ROOT_PATH . 'includes/cls_transport.php');
        include_once(ROOT_PATH . 'includes/cls_json.php');
        include_once(ROOT_PATH . 'includes/lib_main.php');
        include_once(ROOT_PATH . 'includes/lib_license.php');

        $license = license_check();
        switch ($license['flag']) {
            case 'login_succ':
                if (isset($license['request']['info']['service']['ecshop_b2c']['cert_auth']['auth_str'])) {
                    make_json_result(process_login_license($license['request']['info']['service']['ecshop_b2c']['cert_auth']));
                } else {
                    make_json_error(0);
                }
                break;

            case 'login_fail':
            case 'login_ping_fail':
                make_json_error(0);
                break;

            case 'reg_succ':
                $_license = license_check();
                switch ($_license['flag']) {
                    case 'login_succ':
                        if (isset($_license['request']['info']['service']['ecshop_b2c']['cert_auth']['auth_str']) && $_license['request']['info']['service']['ecshop_b2c']['cert_auth']['auth_str'] != '') {
                            make_json_result(process_login_license($license['request']['info']['service']['ecshop_b2c']['cert_auth']));
                        } else {
                            make_json_error(0);
                        }
                        break;

                    case 'login_fail':
                    case 'login_ping_fail':
                        make_json_error(0);
                        break;
                }
                break;

            case 'reg_fail':
            case 'reg_ping_fail':
                make_json_error(0);
                break;
        }
    } else {
        make_json_error(0);
    }
}

/* ------------------------------------------------------ */
//-- 云服务
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'cloud_services') {

    admin_priv('cloud_services');

    $http = $ecs->http();
    if(strpos($http, 'https://') === false){
        $Loaction = "http://www.dscmall.cn/cloud/index.html";
    }else{
        $Loaction = "https://www.dscmall.cn/cloud/index.html";
    }

    ecs_header("Location: $Loaction\n");
    exit;
}
/* ------------------------------------------------------ */
//-- 平台推荐
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'platform_recommend') {

    admin_priv('cloud_services');

    $http = $ecs->http();
    if(strpos($http, 'https://') === false){
        $Loaction = "http://dscmall.cn/cloud/platform_rec.html";
    }else{
        $Loaction = "https://dscmall.cn/cloud/platform_rec.html";
    }

    ecs_header("Location: $Loaction\n");
    exit;
}
/* ------------------------------------------------------ */
//-- 好货推荐
/* ------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'best_recommend') {

    admin_priv('cloud_services');

    $http = $ecs->http();
    if(strpos($http, 'https://') === false){
        $Loaction = "https://dscmall.cn/cloud/best_rec.html";
    }else{
        $Loaction = "https://dscmall.cn/cloud/best_rec.html";
    }

    ecs_header("Location: $Loaction\n");
    exit;
}

/* ------------------------------------------------------ */
//-- 删除配置文件夹  by kong  20160425
/* ------------------------------------------------------ */
elseif($_REQUEST['act'] == 'remove_file'){
$file=!empty($_REQUEST['file'])    ? $_REQUEST['file']:'';
    if(!empty($file)){

        if(deldir(ROOT_PATH.$file) == true){
            $Loaction="index.php?act=main";
            ecs_header("Location: $Loaction\n");//返回首页
        } // 删除的文件夹
    }
}

/* ------------------------------------------------------ */
//-- 管理员头像上传
/* ------------------------------------------------------ */
elseif($_REQUEST['act'] == 'upload_store_img')
{
    $result = array("error"=>0, "message"=>"", "content"=>"");
    include_once(ROOT_PATH . '/includes/cls_image.php');
    $image = new cls_image($_CFG['bgcolor']);

    if($_FILES['img']['name'])
    {
        //$name_arr = explode(".", $_FILES['img']['name']);
        //$file_type = end($name_arr);
        //$img_name = $store_id . "." . $file_type;
        $dir         = 'store_user';

        $img_name = $image->upload_image($_FILES['img'],$dir);

        if($img_name)
        {
            $result['error'] = 1;
            $result['content'] = "../" . $img_name;
            //删除原图片

            $store_user_img = $GLOBALS['db']->getOne(" SELECT admin_user_img FROM ".$GLOBALS['ecs']->table('admin_user')." WHERE user_id = '".$_SESSION['admin_id']."' ");
            @unlink("../" . $store_user_img);
            //插入新图片
            $sql = " UPDATE ".$GLOBALS['ecs']->table('admin_user')." SET admin_user_img = '$img_name' WHERE user_id = '".$_SESSION['admin_id']."' ";
            $GLOBALS['db']->query($sql);
        }
    }
    die(json_encode($result));
}

/* ------------------------------------------------------ */
//-- 处理添加快捷菜单(保存于cookie) bylu
/* ------------------------------------------------------ */
elseif($_REQUEST['act'] == 'auth_menu'){

    $type = isset($_POST['type'])?$_POST['type']:'';
    $auth_name = isset($_POST['auth_name'])?$_POST['auth_name']:'';
    $auth_href = isset($_POST['auth_href'])?$_POST['auth_href']:'';
    $auth_menu = !empty($_COOKIE['auth_menu'])?$_COOKIE['auth_menu']:'';

    if($type == 'add'){
        $auth_menu.=$auth_name.'|'.$auth_href.',';
        setcookie('auth_menu',$auth_menu,time()+3600*24*365);

    }else{
        $auth_menu = str_replace($auth_name.'|'.$auth_href.',','',$auth_menu);
        setcookie('auth_menu',$auth_menu,time()+3600*24*365);
    }
}

/**
 * license check
 * @return  bool
 */
function license_check() {
    // return 返回数组
    $return_array = array();

    // 取出网店 license
    $license = get_shop_license();

    // 检测网店 license
    if (!empty($license['certificate_id']) && !empty($license['token']) && !empty($license['certi'])) {
        // license（登录）
        $return_array = license_login();
    } else {
        // license（注册）
        $return_array = license_reg();
    }

    return $return_array;
}

//ecmoban模板堂 --zhuo start
function get_menu_list($menus, $ru_id = 0) {

    $menus = array_values($menus);
    $arr = array();

    foreach ($menus as $key => $row) {
        $arr[$key] = $row;

        if ($row['label'] == '商品管理') {
            if ($ru_id > 0) {
                $goods_where = " where user_id = '$ru_id' and is_delete = 0";
            } else {
                $goods_where = " where is_delete = 0";
            }

            $sql = "select count(*) from " . $GLOBALS['ecs']->table('goods') . $goods_where;

            $arr[$key]['number'] = $GLOBALS['db']->getOne($sql);

            $arr[$key]['href'] = "goods.php?act=list";
        } elseif ($row['label'] == '库存管理') {
            $arr[$key]['href'] = "goods_inventory_logs.php?act=list&step=put";
        } elseif ($row['label'] == '广告管理') {
            if ($ru_id > 0) {
                $ads_where = " where p.user_id = '$ru_id' or (p.is_public = 1 and a.public_ruid = '$ru_id')";
            }
            $sql = "select count(a.ad_id) from " . $GLOBALS['ecs']->table('ad_position') . " as p " .
                    " left join " . $GLOBALS['ecs']->table('ad') . " as a on p.position_id = a.position_id" .
                    $ads_where;

            $arr[$key]['number'] = $GLOBALS['db']->getOne($sql);

            $arr[$key]['href'] = "ads.php?act=list";
        } elseif ($row['label'] == '订单管理') {

            $number = get_order_count($ru_id);

            $arr[$key]['number'] = $number;

            $arr[$key]['href'] = "order.php?act=list";
        } elseif ($row['label'] == '模式管理') {
            $arr[$key]['href'] = "bonus.php?act=list";
        } elseif ($row['label'] == '报表统计') {
            $arr[$key]['href'] = "order_stats.php?act=list";
        } elseif ($row['label'] == '文章管理') {
            $arr[$key]['href'] = "articlecat.php?act=list";
        } elseif ($row['label'] == '会员管理') {
            $arr[$key]['href'] = "users.php?act=list";
        } elseif ($row['label'] == '权限管理') {
            $arr[$key]['href'] = "privilege.php?act=list";
        } elseif ($row['label'] == '系统设置') {
            if($ru_id > 0){
                    $arr[$key]['href'] = "warehouse.php?act=list";
            }else{
                    $arr[$key]['href'] = "shop_config.php?act=list_edit";
            }
        } elseif ($row['label'] == '模板管理') {
            $arr[$key]['href'] = "template.php?act=list";
        } elseif ($row['label'] == '数据库管理') {
            $arr[$key]['href'] = "sql.php?act=main";
        } elseif ($row['label'] == '短信管理') {
            $arr[$key]['href'] = "sms.php?act=display_send_ui";
        } elseif ($row['label'] == '推荐管理') {
            $arr[$key]['href'] = "affiliate.php?act=list";
        } elseif ($row['label'] == '邮件群发管理') {
            $arr[$key]['href'] = "view_sendlist.php?act=list";
        } elseif ($row['label'] == '商家入驻管理') {
            if($ru_id > 0){
                    $arr[$key]['href'] = "merchants_commission.php?act=list";
            }else{
                    $arr[$key]['href'] = "merchants_users_list.php?act=list";
            }
        } elseif ($row['label'] == '商品批量管理') {
            $arr[$key]['href'] = "goods_warehouse_batch.php?act=add";
        } elseif ($row['label'] == '店铺设置管理') {
            $arr[$key]['href'] = "index.php?act=merchants_first";
        }
    }

    return $arr;
}

//计算订单数量
function get_order_count($ru_id = 0){
	$no_main_order = '';
	$where = 'WHERE 1 ';
	if($ru_id > 0){
		$where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id LIMIT 1) = '$ru_id' ";
		$no_main_order = " and (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi2 where oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
	}

	$sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_info') . " AS o ". $where . $no_main_order;
	$count = $GLOBALS['db']->getOne($sql);

    return $count;
}
/*删除文件夹   by kong 20160425*/
function deldir($dir)
{
   $dh = @opendir($dir);
   while ($file = @readdir($dh))
   {
      if ($file != "." && $file != "..")
      {
         $fullpath = $dir . "/" . $file;
         if (!is_dir($fullpath))
         {
            unlink($fullpath);
         } else
         {
            deldir($fullpath);
         }
      }
   }
   @closedir($dh);
   if (@rmdir($dir))
   {
      return true;
   } else
   {
      return false;
   }
}
function clear_sessions($type = 0){
    $sql1 = "TRUNCATE".$GLOBALS['ecs']->table('sessions');
    $sql2 = "TRUNCATE".$GLOBALS['ecs']->table('sessions_data');
    $sql3 = "TRUNCATE".$GLOBALS['ecs']->table('stats');
    if($type == 0){
        $GLOBALS['db']->query($sql1);
        $GLOBALS['db']->query($sql2);
        $GLOBALS['db']->query($sql3);
    }elseif($type == 1){
        $GLOBALS['db']->query($sql1);
        $GLOBALS['db']->query($sql2);
    }else{
        $GLOBALS['db']->query($sql3);
    }
    return ;
}
?>
