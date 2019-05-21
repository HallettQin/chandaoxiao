<?php

/**
 * ECSHOP 广告管理程序
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
include_once(ROOT_PATH . 'includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$exc   = new exchange($ecs->table("ad"), $db, 'ad_id', 'ad_name');

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

//ecmoban模板堂 --zhuo start
$adminru = get_admin_ru_id();
$ruCat = '';
if($adminru['ru_id'] == 0){
        $smarty->assign('priv_ru',   1);
}else{
        $smarty->assign('priv_ru',   0);
}
//ecmoban模板堂 --zhuo end

/*------------------------------------------------------ */
//-- 广告列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
	$urlPid=empty($_REQUEST['pid'])? '':'&pid='.trim($_REQUEST['pid']); //广告位 by wu
	
    $smarty->assign('ur_here',     $_LANG['ad_list']);
	if(!empty($urlPid)){
   		$smarty->assign('action_link', array('text' => $_LANG['ads_add'], 'href' => 'ads.php?act=add'.$urlPid));
	}
    $smarty->assign('full_page',  1);
	
    //获取位置列表 by wu start
    $where = " WHERE 1 ";
    if ($adminru['ru_id'] > 0) {
        $where .= " AND is_public = 1 ";
    }
    
    $where .= " AND theme = '" .$GLOBALS['_CFG']['template']. "'";
    
    $sql = 'SELECT position_id, position_name, ad_width, ad_height ' .
            'FROM ' . $GLOBALS['ecs']->table('ad_position') . $where; // 改判断条件 商家广告位置由admin设置公共属性 liu
    $position_list = $GLOBALS['db']->getAll($sql);
    $smarty->assign('position_list', $position_list);
    //获取位置列表 by wu end
	
    $ads_list = get_adslist($adminru['ru_id']);

    $smarty->assign('ads_list',     $ads_list['ads']);
    $smarty->assign('filter',       $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count',   $ads_list['page_count']);
    $smarty->assign('pid',         $ads_list['filter']['pid']);
    
    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    $sort_flag  = sort_flag($ads_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    assign_query_info();
    $smarty->display('ads_list.dwt');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    $ads_list = get_adslist($adminru['ru_id']);

    $smarty->assign('ads_list',     $ads_list['ads']);
    $smarty->assign('filter',       $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count',   $ads_list['page_count']);
    
    $store_list = get_common_store_list();
    $smarty->assign('store_list',        $store_list);

    $sort_flag  = sort_flag($ads_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('ads_list.dwt'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加新广告页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add') 
{
    admin_priv('ad_manage');

    //广告位 by wu start
    //$pid=empty($_REQUEST['pid'])? (empty($_SESSION['pid'])? '':$_SESSION['pid']):trim($_REQUEST['pid']);
    $pid = empty($_REQUEST['pid']) ? '' : trim($_REQUEST['pid']);

    if (!empty($pid)) {
        $_SESSION['pid'] = $pid;

        $catFirst = getCatList();
        $smarty->assign('catFirst', $catFirst);

        $rec = get_ad_model($pid);

        /* 需要添加第二张图片或标题的广告位传值 */
        //取消recommend_category 第二章图
        $another_pic = in_array($rec['ad_model'], array('recommend_merchants', 'expert_field_ad', 'category_top_default_brand'));

//        $another_pic = in_array($rec['ad_model'], array('recommend_category', 'recommend_merchants', 'expert_field_ad', 'category_top_default_brand'));
        $title = in_array($rec['ad_model'], array('recommend_category', 'expert_field_ad', 'merchants_index_case_ad', 'cat_goods_ad_left', 'cat_goods_ad_right', 'recommend_merchants', 'category_top_default_brand'));
        $smarty->assign('another_pic', $another_pic);
        $smarty->assign('title', $title);

        $smarty->assign('recommend_merchant', $recommend_merchant);
        $smarty->assign('is_recommend', $is_recommend);
        $smarty->assign('expert_field', $expert_field);
        $smarty->assign('cat_goods_ad', $cat_goods_ad);
        $smarty->assign('merchants_index_case_ad', $merchants_index_case_ad);

        $ad_model = json_encode($rec);
        $smarty->assign('ad_model', $ad_model);
    }
    //广告位 by wu end

    $ad_link = empty($_GET['ad_link']) ? '' : trim($_GET['ad_link']);
    $ad_name = empty($_GET['ad_name']) ? '' : trim($_GET['ad_name']);

    $start_time = local_date($GLOBALS['_CFG']['time_format']);
    $end_time = local_date($GLOBALS['_CFG']['time_format'], gmtime() + 3600 * 24 * 30);  // 默认结束时间为1个月以后

    $smarty->assign('ads', array('ad_link' => $ad_link, 'ad_name' => $ad_name, 'start_time' => $start_time,
        'end_time' => $end_time, 'enabled' => 1, 'position_id' => $pid));

    $smarty->assign('ur_here', $_LANG['ads_add']);
    //$smarty->assign('action_link',   array('href' => 'ads.php?act=list', 'text' => $_LANG['ad_list']));
    $smarty->assign('action_link', array('href' => 'ads.php?act=list' . '&pid=' . $pid, 'text' => $_LANG['ad_list']));
    $smarty->assign('position_list', get_position_list());

    $smarty->assign('form_act', 'insert');
    $smarty->assign('action', 'add');
    $smarty->assign('cfg_lang', $_CFG['lang']);

    set_default_filter(); //by wu

    assign_query_info();
    $smarty->display('ads_info.dwt');
}

/*------------------------------------------------------ */
//-- 新广告的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert')
{
    admin_priv('ad_manage');

    /* 初始化变量 */
    $id      = !empty($_POST['id'])      ? intval($_POST['id'])    : 0;
    $type    = !empty($_POST['type'])    ? intval($_POST['type'])  : 0;
    $ad_name = !empty($_POST['ad_name']) ? trim($_POST['ad_name']) : '';
    $link_color = !empty($_POST['link_color']) ? trim($_POST['link_color']) : '';
    $b_title = !empty($_POST['b_title']) ? trim($_POST['b_title']) : '';
    $s_title = !empty($_POST['s_title']) ? trim($_POST['s_title']) : '';

    //ecmoban模板堂 --zhuo start
    $is_new = !empty($_POST['is_new']) ? intval($_POST['is_new']) : 0;
    $is_hot = !empty($_POST['is_hot']) ? intval($_POST['is_hot']) : 0;
    $is_best = !empty($_POST['is_best']) ? intval($_POST['is_best']) : 0;
    
    $ad_type = !empty($_POST['ad_type']) ? intval($_POST['ad_type']) : 0;
    $goods_name = !empty($_POST['goods_name']) ? trim($_POST['goods_name']) : 0;
    //ecmoban模板堂 --zhuo end

    if ($_POST['media_type'] == '0')
    {
        $ad_link = !empty($_POST['ad_link']) ? trim($_POST['ad_link']) : '';
    }
    else
    {
        $ad_link = !empty($_POST['ad_link2']) ? trim($_POST['ad_link2']) : '';
    }

    /* 获得广告的开始时期与结束日期 */
    $start_time = local_strtotime($_POST['start_time']);
    $end_time   = local_strtotime($_POST['end_time']);

    $template = $GLOBALS['_CFG']['template'];
    
    /* 查看广告名称是否有重复 */
    $sql = "SELECT COUNT(*) FROM " .$ecs->table('ad')." AS a, ". 
            $ecs->table('ad_position') ." AS p ".
            " WHERE a.ad_name = '$ad_name' AND a.position_id = p.position_id AND p.theme = '$template'";
    if ($db->getOne($sql) > 0)
    {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg($_LANG['ad_name_exist'], 0, $link);
    }
    /* 添加图片类型的广告 */
    if ($_POST['media_type'] == '0')
    {
        if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name'] ) &&$_FILES['ad_img']['tmp_name'] != 'none'))
        {
            $ad_code = basename($image->upload_image($_FILES['ad_img'], 'afficheimg'));
        }
        if (!empty($_POST['img_url']))
        {
            //$ad_code = $_POST['img_url'];
			$image_url = $_POST['img_url'];
            if($image_url){
                if (!empty($image_url) && ($image_url != $GLOBALS['_LANG']['img_file']) && ($image_url != 'http://') && copy(trim($image_url), ROOT_PATH . 'data/afficheimg/' . basename($image_url))) {
                    $image_url = trim($image_url);
                    //定义原图路径
                    $ad_code = basename($image_url);
                }
            }
        }
        if (((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] > 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name']) && $_FILES['ad_img']['tmp_name'] == 'none')) && empty($_POST['img_url']))
        {
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['js_languages']['ad_photo_empty'], 0, $link);
        }
		
		//推荐广告背景图片 liu
        if ((isset($_FILES['ad_bg_img']['error']) && $_FILES['ad_bg_img']['error'] == 0) || (!isset($_FILES['ad_bg_img']['error']) && isset($_FILES['ad_bg_img']['tmp_name'] ) &&$_FILES['ad_bg_img']['tmp_name'] != 'none'))
        {
            $ad_bg_code = basename($image->upload_image($_FILES['ad_bg_img'], 'afficheimg'));
        }
        if (((isset($_FILES['ad_bg_img']['error']) && $_FILES['ad_bg_img']['error'] > 0) || (!isset($_FILES['ad_bg_img']['error']) && isset($_FILES['ad_bg_img']['tmp_name']) && $_FILES['ad_bg_img']['tmp_name'] == 'none')) && empty($_POST['img_url']))
        {
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['js_languages']['ad_photo_empty'], 0, $link);
        }
    }

    /* 如果添加的广告是Flash广告 */
    elseif ($_POST['media_type'] == '1')
    {
        if ((isset($_FILES['upfile_flash']['error']) && $_FILES['upfile_flash']['error'] == 0) || (!isset($_FILES['upfile_flash']['error']) && isset($_FILES['ad_img']['tmp_name']) && $_FILES['upfile_flash']['tmp_name'] != 'none'))
        {
            /* 检查文件类型 */
            if ($_FILES['upfile_flash']['type'] != "application/x-shockwave-flash")
            {
                $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
                sys_msg($_LANG['upfile_flash_type'], 0, $link);
            }

            /* 生成文件名 */
            $urlstr = date('Ymd');
            for ($i = 0; $i < 6; $i++)
            {
                $urlstr .= chr(mt_rand(97, 122));
            }

            $source_file = $_FILES['upfile_flash']['tmp_name'];
            $target      = ROOT_PATH . DATA_DIR . '/afficheimg/';
            $file_name   = $urlstr .'.swf';

            if (!move_upload_file($source_file, $target.$file_name))
            {
                $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
                sys_msg($_LANG['upfile_error'], 0, $link);
            }
            else
            {
                $ad_code = $file_name;
            }
        }
        elseif (!empty($_POST['flash_url']))
        {
            if (substr(strtolower($_POST['flash_url']), strlen($_POST['flash_url']) - 4) != '.swf')
            {
                $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
                sys_msg($_LANG['upfile_flash_type'], 0, $link);
            }
            $ad_code = $_POST['flash_url'];
        }

        if (((isset($_FILES['upfile_flash']['error']) && $_FILES['upfile_flash']['error'] > 0) || (!isset($_FILES['upfile_flash']['error']) && isset($_FILES['upfile_flash']['tmp_name']) && $_FILES['upfile_flash']['tmp_name'] == 'none')) && empty($_POST['flash_url']))
        {
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['js_languages']['ad_flash_empty'], 0, $link);
        }
    }
    /* 如果广告类型为代码广告 */
    elseif ($_POST['media_type'] == '2')
    {
        if (!empty($_POST['ad_code']))
        {
            $ad_code = $_POST['ad_code'];
        }
        else
        {
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['js_languages']['ad_code_empty'], 0, $link);
        }
    }

    /* 广告类型为文本广告 */
    elseif ($_POST['media_type'] == '3')
    {
        if (!empty($_POST['ad_text']))
        {
            $ad_code = $_POST['ad_text'];
        }
        else
        {
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['js_languages']['ad_text_empty'], 0, $link);
        }
    }
    $add_file = array();

    if ($ad_code) {
        $add_file[] = DATA_DIR . '/afficheimg/' . $ad_code;
    }

    if ($ad_bg_code) {
        $add_file[] = DATA_DIR . '/afficheimg/' . $ad_bg_code;
    }
    get_oss_add_file($add_file);

    $public_ruid = $adminru['ru_id'];	//ecmoban模板堂 --zhuo 
    /* 插入数据 */
    $sql = "INSERT INTO ".$ecs->table('ad'). " (position_id,media_type,ad_name,is_new,is_hot,is_best,public_ruid,ad_link,ad_code,ad_bg_code,start_time,end_time,link_man,link_email,link_phone,click_count,enabled, link_color, b_title, s_title, ad_type, goods_name)
    VALUES ('$_POST[position_id]',
            '$_POST[media_type]',
            '$ad_name',
            '$is_new',
            '$is_hot',
            '$is_best',
            '$public_ruid',
            '$ad_link',
            '$ad_code',
			'$ad_bg_code',
            '$start_time',
            '$end_time',
            '$_POST[link_man]',
            '$_POST[link_email]',
            '$_POST[link_phone]',
            '0',
            '1',
            '$link_color',
			'$b_title',
			'$s_title',
            '$ad_type',
            '$goods_name')";

    $db->query($sql);
    /* 记录管理员操作 */
    admin_log($_POST['ad_name'], 'add', 'ads');

    clear_cache_files(); // 清除缓存文件

    /* 提示信息 */

    $link[0]['text'] = $_LANG['back_ads_list'];
    $link[0]['href'] = 'ads.php?act=list' . '&pid=' . $_POST['position_id'];

    $link[1]['text'] = $_LANG['continue_add_ad'];
    $link[1]['href'] = 'ads.php?act=add' . '&pid=' . $_POST['position_id'];
    sys_msg($_LANG['add'] . "&nbsp;" . $_POST['ad_name'] . "&nbsp;" . $_LANG['attradd_succed'], 0, $link);
}

/*------------------------------------------------------ */
//-- 广告编辑页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit')
{
    admin_priv('ad_manage');

    /* 获取广告数据 */
    $sql = "SELECT * FROM " .$ecs->table('ad'). " WHERE ad_id='".intval($_REQUEST['id'])."'";
    $ads_arr = $db->getRow($sql);
	
    //广告位 by wu start
    $pid = empty($ads_arr['position_id']) ? '' : trim($ads_arr['position_id']);

    if (!empty($pid)) {

        $catFirst = getCatList();
        $smarty->assign('catFirst', $catFirst);

        $rec = get_ad_model($pid);

        /* 需要添加第二张图片或标题的广告位传值 */
        $another_pic = in_array($rec['ad_model'], array('recommend_category', 'recommend_merchants', 'expert_field_ad', 'category_top_default_brand'));
        $title = in_array($rec['ad_model'], array('recommend_category', 'expert_field_ad', 'merchants_index_case_ad', 'cat_goods_ad_left', 'cat_goods_ad_right', 'recommend_merchants', 'category_top_default_brand'));
        $smarty->assign('another_pic', $another_pic);
        $smarty->assign('title', $title);

        $ad_model = json_encode($rec);
        $smarty->assign('ad_model', $ad_model);
    }
    //广告位 by wu end	

    $ads_arr['ad_name'] = htmlspecialchars($ads_arr['ad_name']);
    /* 格式化广告的有效日期 */
    $ads_arr['start_time']  = local_date($GLOBALS['_CFG']['time_format'], $ads_arr['start_time']);
    $ads_arr['end_time']    = local_date($GLOBALS['_CFG']['time_format'], $ads_arr['end_time']);
       
    if ($ads_arr['media_type'] == '0')
    {
        if (strpos($ads_arr['ad_code'], 'http://') === false && strpos($ads_arr['ad_code'], 'https://') === false)
        {
            $src = DATA_DIR . '/afficheimg/'. $ads_arr['ad_code'];
            $src = get_image_path(0, $src);
            $smarty->assign('img_src', $src);
        }
        else
        {
            $src = $ads_arr['ad_code'];
            $src = str_replace('../', '', $src);
            $src = get_image_path(0, $src);
            $smarty->assign('url_src', $src);
        }
    }
    if ($ads_arr['media_type'] == '1')
    {
        if (strpos($ads_arr['ad_code'], 'http://') === false && strpos($ads_arr['ad_code'], 'https://') === false)
        {
            $src =  DATA_DIR . '/afficheimg/'. $ads_arr['ad_code'];
            $src = get_image_path(0, $src);
            $smarty->assign('flash_url', $src);
        }
        else
        {
            $src = $ads_arr['ad_code'];
            $src = str_replace('../', '', $src);
            $src = get_image_path(0, $src);
            $smarty->assign('flash_url', $src);
        }
        $smarty->assign('src', $src);
    }
    if ($ads_arr['media_type'] == 0)
    {
        $smarty->assign('media_type', $_LANG['ad_img']);
    }
    elseif ($ads_arr['media_type'] == 1)
    {
        $smarty->assign('media_type', $_LANG['ad_flash']);
    }
    elseif ($ads_arr['media_type'] == 2)
    {
        $smarty->assign('media_type', $_LANG['ad_html']);
    }
    elseif ($ads_arr['media_type'] == 3)
    {
        $smarty->assign('media_type', $_LANG['ad_text']);
    }

    //出来广告图片链接
    if($ads_arr['ad_code']){
        if (strpos($ads_arr['ad_code'], 'http://') === false && strpos($ads_arr['ad_code'], 'https://') === false)
        {
            $src = DATA_DIR . '/afficheimg/'. $ads_arr['ad_code'];
            $src = get_image_path(0, $src);
            $ads_arr['ad_code'] = $src;
        }
        else
        {
            $src = $ads_arr['ad_code'];
            $src = str_replace('../', '', $src);
            $src = get_image_path(0, $src);
           $ads_arr['ad_code'] = $src;
        }
    }
    //出来广告背景链接
    if($ads_arr['ad_bg_code']){
        if (strpos($ads_arr['ad_bg_code'], 'http://') === false && strpos($ads_arr['ad_bg_code'], 'https://') === false)
        {
            $src = DATA_DIR . '/afficheimg/'. $ads_arr['ad_bg_code'];
            $src = get_image_path(0, $src);
            $ads_arr['ad_bg_code'] = $src;
        }
        else
        {
            $src = $ads_arr['ad_bg_code'];
            $src = str_replace('../', '', $src);
            $src = get_image_path(0, $src);
           $ads_arr['ad_bg_code'] = $src;
        }
    }
    $smarty->assign('ur_here',       $_LANG['ads_edit']);
    $smarty->assign('action_link',   array('href' => 'ads.php?act=list'.'&pid='.$pid, 'text' => $_LANG['ad_list']));
    $smarty->assign('form_act',      'update');
    $smarty->assign('action',        'edit');
    $smarty->assign('position_list', get_position_list());
    $smarty->assign('ads',           $ads_arr);

	set_default_filter(); //by wu

    assign_query_info();
    $smarty->display('ads_info.dwt');
}
/*------------------------------------------------------ */
//-- 编辑广告名称
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_ad_name')
{
    check_authz_json('ad_manage');

    $id      = intval($_POST['id']);
    $ad_name = json_str_iconv(trim($_POST['val']));
    
    $template = $GLOBALS['_CFG']['template'];
    
    /* 查看广告名称是否有重复 ecmoban模板堂 --zhuo */
    $sql = "SELECT COUNT(*) FROM " .$ecs->table('ad')." AS a, ". 
            $ecs->table('ad_position') ." AS p ".
            " WHERE a.ad_id <> '$id' AND a.ad_name ='$ad_name' AND a.position_id = p.position_id AND p.theme = '$template'";
    if ($db->getOne($sql) > 0)
    {
        $res = 1;
    }else{
        $res = 0;
    }

    /* 检查广告名称是否重复 */
    if ($res)
    {
        make_json_error(sprintf($_LANG['ad_name_exist'], $ad_name));
    }
    else
    {
        if ($exc->edit("ad_name = '$ad_name'", $id))
        {
            admin_log($ad_name,'edit','ads');
            make_json_result(stripslashes($ad_name));
        }
        else
        {
            make_json_error($db->error());
        }
    }
}

/*------------------------------------------------------ */
//-- 广告编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update')
{
    admin_priv('ad_manage');

    /* 初始化变量 */
    $id   = !empty($_POST['id'])   ? intval($_POST['id'])   : 0;
    $type = !empty($_POST['media_type']) ? intval($_POST['media_type']) : 0;
	
    //ecmoban模板堂 --zhuo start
    $is_new = !empty($_POST['is_new']) ? intval($_POST['is_new']) : 0;
    $is_hot = !empty($_POST['is_hot']) ? intval($_POST['is_hot']) : 0;
    $is_best = !empty($_POST['is_best']) ? intval($_POST['is_best']) : 0;
    
    $_POST['ad_name'] = !empty($_POST['ad_name']) ? trim($_POST['ad_name']) : '';
    $link_color = !empty($_POST['link_color']) ? trim($_POST['link_color']) : '';
    $b_title = !empty($_POST['b_title']) ? trim($_POST['b_title']) : '';
    $s_title = !empty($_POST['s_title']) ? trim($_POST['s_title']) : '';
    
    $ad_type = !empty($_POST['ad_type']) ? intval($_POST['ad_type']) : 0;
    $goods_name = !empty($_POST['goods_name']) ? trim($_POST['goods_name']) : 0;
    //ecmoban模板堂 --zhuo end

    if ($_POST['media_type'] == '0')
    {
        $ad_link = !empty($_POST['ad_link']) ? trim($_POST['ad_link']) : '';
    }
    else
    {
        $ad_link = !empty($_POST['ad_link2']) ? trim($_POST['ad_link2']) : '';
    }

    /* 获得广告的开始时期与结束日期 */
    $start_time = local_strtotime($_POST['start_time']);
    $end_time   = local_strtotime($_POST['end_time']);

    /* 编辑图片类型的广告 */
    if ($type == 0)
    {
        if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name']) && $_FILES['ad_img']['tmp_name'] != 'none'))
        {

            $img_up_info = basename($image->upload_image($_FILES['ad_img'], 'afficheimg'));
            $ad_code = "ad_code = '".$img_up_info."'".',';
            $ad_images = $img_up_info;
			
            $sql = " SELECT ad_code FROM " . $ecs->table('ad') . " WHERE ad_id = '$id' ";
            $code = $db->getOne($sql);
            if ($code && $code != $img_up_info) {
                @unlink('../' . DATA_DIR . '/afficheimg/' . $code);
            }
        }
        else
        {
            $ad_code = '';
            $ad_images = '';
        }
        if (!empty($_POST['img_url'])) {
            $image_url = $_POST['img_url'];
            if ($image_url) {
                if (!empty($image_url) && ($image_url != $GLOBALS['_LANG']['img_file']) && ($image_url != 'http://') && copy(trim($image_url), ROOT_PATH . 'data/afficheimg/' . basename($image_url))) {
                    $image_url = trim($image_url);
                    //定义原图路径
                    $ad_code = "ad_code = '" . basename($image_url) . "', ";
                }
            }
            $ad_images = basename($image_url);
        }

        //推荐广告背景图片 liu
        if ((isset($_FILES['ad_bg_img']['error']) && $_FILES['ad_bg_img']['error'] == 0) || (!isset($_FILES['ad_bg_img']['error']) && isset($_FILES['ad_bg_img']['tmp_name']) && $_FILES['ad_bg_img']['tmp_name'] != 'none'))
        {
            
            $bg_img_up_info = basename($image->upload_image($_FILES['ad_bg_img'], 'afficheimg'));
            $ad_bg_code = "ad_bg_code = '".$bg_img_up_info."'".',';
            $ad_bg_images = $bg_img_up_info;
			
            $sql = " SELECT ad_bg_code FROM " . $ecs->table('ad') . " WHERE ad_id = '$id' ";
            $bg_code = $db->getOne($sql);
            if ($bg_code && $bg_code != $bg_img_up_info) {
                @unlink('../' . DATA_DIR . '/afficheimg/' . $bg_code);
            }
        }
        else
        {
            $ad_bg_code = '';
            $ad_bg_images = '';
        }	
    }

    /* 如果是编辑Flash广告 */
    elseif ($type == 1)
    {
        if ((isset($_FILES['upfile_flash']['error']) && $_FILES['upfile_flash']['error'] == 0) || (!isset($_FILES['upfile_flash']['error']) && isset($_FILES['upfile_flash']['tmp_name']) && $_FILES['upfile_flash']['tmp_name'] != 'none'))
        {
            /* 检查文件类型 */
            if ($_FILES['upfile_flash']['type'] != "application/x-shockwave-flash")
            {
                $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
                sys_msg($_LANG['upfile_flash_type'], 0, $link);
            }
            /* 生成文件名 */
            $urlstr = date('Ymd');
            for ($i = 0; $i < 6; $i++)
            {
                $urlstr .= chr(mt_rand(97, 122));
            }

            $source_file = $_FILES['upfile_flash']['tmp_name'];
            $target      = ROOT_PATH . DATA_DIR . '/afficheimg/';
            $file_name   = $urlstr .'.swf';

            if (!move_upload_file($source_file, $target.$file_name))
            {
                $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
                sys_msg($_LANG['upfile_error'], 0, $link);
            }
            else
            {
                $ad_code = "ad_code = '$file_name', ";
                $ad_images = $file_name;
            }
        }
        elseif (!empty($_POST['flash_url']))
        {
            if (substr(strtolower($_POST['flash_url']), strlen($_POST['flash_url']) - 4) != '.swf')
            {
                $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
                sys_msg($_LANG['upfile_flash_type'], 0, $link);
            }
            $ad_code = "ad_code = '".$_POST['flash_url']."', ";
            $ad_images = $_POST['flash_url'];
        }
        else
        {
            $ad_code = '';
            $ad_images = '';
        }

    }

    /* 编辑代码类型的广告 */
    elseif ($type == 2)
    {
        $ad_code = "ad_code = '$_POST[ad_code]', ";
    }

    /* 编辑文本类型的广告 */
    if ($type == 3)
    {
        $ad_code = "ad_code = '$_POST[ad_text]', ";
    }
    
    $template = $GLOBALS['_CFG']['template'];
    
    /* 查看广告名称是否有重复 ecmoban模板堂 --zhuo */
    $sql = "SELECT COUNT(*) FROM " .$ecs->table('ad')." AS a, ". 
            $ecs->table('ad_position') ." AS p ".
            " WHERE a.ad_id <> '$id' AND a.ad_name ='$_POST[ad_name]' AND a.position_id = p.position_id AND p.theme = '$template'";
    if ($db->getOne($sql) > 0)
    {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'ads.php?act=edit&id='.$id);
        sys_msg($_LANG['ad_name_exist'], 1, $link);
        exit;
    }
    $add_file = array();

    if ($ad_images) {
        $add_file[] = DATA_DIR . '/afficheimg/' . $ad_images;
    }

    if ($ad_bg_images) {
        $add_file[] = DATA_DIR . '/afficheimg/' . $ad_bg_images;
    }
    get_oss_add_file($add_file);
    
    $ad_code = str_replace('../' . DATA_DIR . '/afficheimg/', '', $ad_code);
    /* 更新信息 */
    $sql = "UPDATE " .$ecs->table('ad'). " SET ".
            "position_id = '$_POST[position_id]', ".
            "ad_name     = '$_POST[ad_name]', ".
            "ad_link     = '$ad_link', ".
            "link_color  = '$link_color', ".
            "b_title  = '$b_title', ".
            "s_title  = '$s_title', ".
            "is_new     = '$is_new', ".
            "is_hot     = '$is_hot', ".
            "is_best     = '$is_best', ".
            $ad_code.
            $ad_bg_code.//liu 
            "start_time  = '$start_time', ".
            "end_time    = '$end_time', ".
            "link_man    = '$_POST[link_man]', ".
            "link_email  = '$_POST[link_email]', ".
            "link_phone  = '$_POST[link_phone]', ".
            "enabled     = '$_POST[enabled]', ".
            "ad_type  = '$ad_type', ".
            "goods_name  = '$goods_name' ".
            "WHERE ad_id = '$id'";
    $db->query($sql);

   /* 记录管理员操作 */
   admin_log($_POST['ad_name'], 'edit', 'ads');

   clear_cache_files(); // 清除模版缓存

   /* 提示信息 */
   $href[] = array('text' => $_LANG['back_ads_list'], 'href' => 'ads.php?act=list'.'&pid='.$_POST['position_id']);
   sys_msg($_LANG['edit'] .' '.$_POST['ad_name'].' '. $_LANG['attradd_succed'], 0, $href);

}

/*------------------------------------------------------ */
//--生成广告的JS代码
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add_js')
{
    admin_priv('ad_manage');

    /* 编码 */
    $lang_list = array(
        'UTF8'   => $_LANG['charset']['utf8'],
        'GB2312' => $_LANG['charset']['zh_cn'],
        'BIG5'   => $_LANG['charset']['zh_tw'],
    );

    $js_code  = "<script type=".'"'."text/javascript".'"';
    $js_code .= ' src='.'"'.$ecs->url().'affiche.php?act=js&type='.$_REQUEST['type'].'&ad_id='.intval($_REQUEST['id']).'"'.'></script>';

    $site_url = $ecs->url().'affiche.php?act=js&type='.$_REQUEST['type'].'&ad_id='.intval($_REQUEST['id']);

    $smarty->assign('ur_here',     $_LANG['add_js_code']);
    $smarty->assign('action_link', array('href' => 'ads.php?act=list', 'text' => $_LANG['ad_list']));
    $smarty->assign('url',         $site_url);
    $smarty->assign('js_code',     $js_code);
    $smarty->assign('lang_list',   $lang_list);

    assign_query_info();
    $smarty->display('ads_js.htm');
}

/*------------------------------------------------------ */
//-- 删除广告位置
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('ad_manage');

    $id = intval($_GET['id']);
    $img = $exc->get_name($id, 'ad_code');

    $exc->drop($id);

    if ((strpos($img, 'http://') === false) && (strpos($img, 'https://') === false))
    {
        $img_name = basename($img);
        
        get_oss_del_file(array(DATA_DIR . '/afficheimg/'.$img_name));
        @unlink(ROOT_PATH. DATA_DIR . '/afficheimg/'.$img_name);
    }

    admin_log('', 'remove', 'ads');

    $url = 'ads.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);

    ecs_header("Location: $url\n");
    exit;
}

/*------------------------------------------------------ */
//-- 获取分类列表 by wu
/*------------------------------------------------------ */
elseif($_REQUEST['act'] == 'getCatList')
{
	$catId=empty($_REQUEST['catId'])? '':trim($_REQUEST['catId']);
	$catList=getCatList($catId);
	die(json_encode($catList));
}

//获取广告位结构
elseif ($_REQUEST['act'] == 'get_position'){
	$position_id = !empty($_GET['position_id']) ? intval($_GET['position_id']) : 0;
	$reg='/\D+/';
	$sql = "SELECT COUNT(*) AS position_num,ad_name FROM " . $GLOBALS['ecs']->table('ad') . " WHERE position_id='$position_id' ORDER BY ad_id DESC";
	$position_model = $db->getRow($sql);
	preg_match_all($reg,$position_model['ad_name'],$res); 
	$position_model['ad_name'] = $res[0][0];
	die(json_encode($position_model));
}

/* 获取广告数据列表 */
function get_adslist($ru_id)
{	
    /* 过滤查询 */
    $filter = array();
	
    //ecmoban模板堂 --zhuo start
    $filter['keyword'] = !empty($_REQUEST['keyword']) ? trim($_REQUEST['keyword']) : '';
    if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
    {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
    }
    //ecmoban模板堂 --zhuo end
    
    $filter['sort_by']    = empty($_REQUEST['sort_by']) ? 'ad.ad_id' : trim($_REQUEST['sort_by']);
    $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
    $filter['pid']    = empty($_REQUEST['pid']) ? 0 : intval($_REQUEST['pid']);

    $where = 'WHERE 1 ';
    
    /* 关键字 */
    if (!empty($filter['keyword']))
    {
        $where .= " AND (ad.ad_name LIKE '%" . mysql_like_quote($filter['keyword']) . "%'" . " OR p.position_name LIKE '%" . mysql_like_quote($filter['keyword']) . "%'" . ")";  
    }
    
    if (!empty($filter['pid']))
    {
        $where .= " AND ad.position_id = '" .$filter['pid']. "' ";
    }

    //ecmoban模板堂 --zhuo start
    if($ru_id > 0){
        $where .= " and (p.user_id = '$ru_id' or (is_public = 1 and ad.public_ruid = '$ru_id')) ";
    }
    //ecmoban模板堂 --zhuo end
    
    //模板类型
    $where .= " AND p.theme = '" .$GLOBALS['_CFG']['template']. "'";
    
    //管理员查询的权限 -- 店铺查询 start
    $filter['store_search'] = empty($_REQUEST['store_search']) ? 0 : intval($_REQUEST['store_search']);
    $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
    $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';
	
    $time = gmtime();
    $filter['advance_date'] = isset($_REQUEST['advance_date']) ? intval($_REQUEST['advance_date']) : 0;
    if ($filter['advance_date']) {
        $end_where = " AND " . $time . " BETWEEN (end_time - 3600*24*3) AND end_time";
    } else {
        $end_where = '';
    }

    $store_where = '';
    $store_search_where = '';
    if($filter['store_search'] !=0){
       if($ru_id == 0){ 

           if($_REQUEST['store_type']){
                $store_search_where = "AND msi.shopNameSuffix = '" .$_REQUEST['store_type']. "'";
            }

            if($filter['store_search'] == 1){
                $where .= " AND p.user_id = '" .$filter['merchant_id']. "' ";
            }elseif($filter['store_search'] == 2){
                $store_where .= " AND msi.rz_shopName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%'";
            }elseif($filter['store_search'] == 3){
                $store_where .= " AND msi.shoprz_brandName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%' " . $store_search_where;
            }

            if($filter['store_search'] > 1){
                $where .= " AND (SELECT msi.user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') .' as msi ' .  
                          " WHERE msi.user_id = p.user_id $store_where) > 0 ";
            }
       }
    }
    //管理员查询的权限 -- 店铺查询 end

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('ad'). ' AS ad ' . 'LEFT JOIN ' . $GLOBALS['ecs']->table('ad_position'). ' AS p ON p.position_id = ad.position_id ' . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.*, COUNT(o.order_id) AS ad_stats, p.position_name, p.user_id '.
            'FROM ' .$GLOBALS['ecs']->table('ad'). 'AS ad ' .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('ad_position'). ' AS p ON p.position_id = ad.position_id '.
            'LEFT JOIN ' . $GLOBALS['ecs']->table('order_info'). " AS o ON o.from_ad = ad.ad_id $where $end_where " .
            'GROUP BY ad.ad_id '.
            'ORDER by '.$filter['sort_by'].' '.$filter['sort_order'];

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    $idx = 0;
    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
         /* 广告类型的名称 */
         $rows['type']  = ($rows['media_type'] == 0) ? $GLOBALS['_LANG']['ad_img']   : '';
         $rows['type'] .= ($rows['media_type'] == 1) ? $GLOBALS['_LANG']['ad_flash'] : '';
         $rows['type'] .= ($rows['media_type'] == 2) ? $GLOBALS['_LANG']['ad_html']  : '';
         $rows['type'] .= ($rows['media_type'] == 3) ? $GLOBALS['_LANG']['ad_text']  : '';

         /* 格式化日期 */
        $rows['start_date'] = local_date($GLOBALS['_CFG']['time_format'], $rows['start_time']);
        $rows['end_date'] = local_date($GLOBALS['_CFG']['time_format'], $rows['end_time']);

        if ($time > ($rows['end_time'] - 24 * 3600 * 3) && $time < $rows['end_time']) {
            $rows['advance_date'] = 1;
        }

        if($rows['public_ruid'] == 0){
             $user_id = $rows['user_id'];
         }else{
             $user_id = $rows['public_ruid'];
         }
         
        $rows['user_name'] = get_shop_name($user_id, 1); //ecmoban模板堂 --zhuo
		 
        $arr[$idx] = $rows;

        $idx++;
    }

    return array('ads' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

//获取广告位模型信息 by wu
function get_ad_model($pid)
{
	//初始数组
	$ad_arr=array(
		'ad_type'=>0,
		'ad_model_init'=>'',
		'ad_model'=>'',
		'ad_model_structure'=>'',
		'cat_id'=>''
	);	
	
	//模型片段
	$init_model=array('[num_id]','[cat_id]');
	
	//广告位信息
	$sql=" select * from ".$GLOBALS['ecs']->table('ad_position')." where position_id='".$pid."' limit 1";
	$position_info=$GLOBALS['db']->getRow($sql);

	if(!empty($position_info['position_model']))
	{
		//$ad_arr['ad_type']=1;
		
		//初始广告位模型($ad_model)和模型结构($ad_model_structure)
		$ad_model=$position_info['position_model'];
		$ad_model_structure=array();
		$i=0;
		foreach($init_model as $model)
		{
			if(strpos($ad_model,$model))
			{
				if($model=='[num_id]')
				{
					$ad_arr['ad_type']=1;
				}
				if($model=='[cat_id]')
				{
					$ad_arr['ad_type']=2;
				}
				//去除[]符号
				$ad_model_structure[$i]=str_replace(array('[',']'),array('',''),$model);
				$i++;
				$ad_model=str_replace(array('_'.$model.'_','_'.$model,$model.'_',$model),array('','','',''),$ad_model);
			}
		}

		if($ad_arr['ad_type']>0)
		{
			//赋值数组
			$ad_arr['ad_model_init']=$position_info['position_model'];
			$ad_arr['ad_model']=$ad_model;
			$ad_arr['ad_model_structure']=$init_model;
		}
		
		if(in_array('cat_id',$ad_model_structure)&&in_array('num_id',$ad_model_structure))
		{
			$ad_arr['ad_type']=3;
			
			//搜索已添加广告
			$sql=" select ad_name from ".$GLOBALS['ecs']->table('ad')." where ad_name like '%".$ad_model."%'";
			$ad_exist=$GLOBALS['db']->getAll($sql);
			
			if(!empty($ad_exist))
			{
				$ad_arr['ad_type']=4;
				
				//处理已存在广告(模型片段)
				$ad_all=array();
				foreach($ad_exist as $key=>$val)
				{
					$ad_deal=explode('_',str_replace($ad_model,'',$val['ad_name']));
					for($j=0;$j<count($ad_model_structure);$j++)
					{
						$ad_all[$key][$ad_model_structure[$j]]=$ad_deal[$j];
					}
				}
				
				//合并分类下的广告
				foreach($ad_all as $key=>$val)
				{
					$ad_arr['cat_id'][$val['cat_id']]['num_id'][]=$val['num_id'];		
				}
				foreach($ad_arr['cat_id'] as $key=>$val)
				{
					//获取下一个即将添加的num_id
					$ad_arr['cat_id'][$key]['next']=null;
					for($p=1;$p<9999;$p++)
					{
						if(!in_array($p,$val['num_id']))
						{
							$ad_arr['cat_id'][$key]['next']=$p;
							break;
						}
					}
				}			
			}			
		}			
	}
	//print_r($ad_arr);	
	return $ad_arr;		
}

function getCatList($catId=0)
{	
	$catList=array();
	
	$where=' where 1 ';

	if(empty($catId))
	{
		$where.=' and parent_id=0 ';		
	}
	else
	{
		$where.=' and parent_id= '.$catId;
	}

	$sql=" select cat_id,cat_name from ".$GLOBALS['ecs']->table('category').$where;
	$catList=$GLOBALS['db']->getAll($sql);
	
	return $catList;
}

?>