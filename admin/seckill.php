<?php

/**
 * DSC 秒杀活动的处理
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
require(ROOT_PATH . '/includes/cls_json.php');

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

/* 初始化$exc对象 */
$exc = new exchange($ecs->table('seckill'), $db, 'sec_id', 'acti_title');
$exc_tb = new exchange($ecs->table('seckill_time_bucket'), $db, 'id', 'title');
$exc_sg = new exchange($ecs->table('seckill_goods'), $db, 'id', 'sec_id');

$adminru = get_admin_ru_id();
//ecmoban模板堂 --zhuo start
if($adminru['ru_id'] == 0){
        $smarty->assign('priv_ru',   1);
}else{
        $smarty->assign('priv_ru',   0);
}
//ecmoban模板堂 --zhuo end
/*------------------------------------------------------ */
//-- 秒杀活动列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    admin_priv('seckill_manage');
    
    $smarty->assign('ur_here',     $_LANG['seckill_list']);
    $smarty->assign('action_link' , array('text' => $_LANG['seckill_add'], 'href' => 'seckill.php?act=add'));
	$smarty->assign('action_link2', array('text' => $_LANG['seckill_time_bucket'], 'href' => 'seckill.php?act=time_bucket'));
    $smarty->assign('full_page',   1);

    $list = get_seckill_list();

    $smarty->assign('seckill_list',    $list['seckill']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);

    $sort_flag  = sort_flag($list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    assign_query_info();
    $smarty->display('seckill_list.dwt');
}

/*------------------------------------------------------ */
//-- 秒杀时间段设置
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'time_bucket')
{
    admin_priv('seckill_manage');
    
    $smarty->assign('ur_here',     $_LANG['seckill_time_bucket']);
    $smarty->assign('action_link' , array('text' => $_LANG['time_bucket_add'], 'href' => 'seckill.php?act=time_add'));
	$smarty->assign('action_link2' , array('text' => $_LANG['seckill_list'], 'href' => 'seckill.php?act=list'));

    $list = get_time_bucket_list();

    $smarty->assign('time_bucket',    $list);
	$smarty->assign('full_page',	  1);
	assign_query_info();
    $smarty->display('seckill_time_bucket.dwt');
}

/*------------------------------------------------------ */
//-- 翻页、排序
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'query')
{
	$list = get_seckill_list();
    $smarty->assign('seckill_list',    $list['seckill']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);

    $sort_flag  = sort_flag($list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('seckill_list.dwt'), '',
        array('filter' => $list['filter'], 'page_count' => $list['page_count']));
}

/*------------------------------------------------------ */
//-- 秒杀时段 翻页、排序
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'tb_query')
{
	$list = get_time_bucket_list();
    $smarty->assign('time_bucket',    $list);

	make_json_result($smarty->fetch('seckill_time_bucket.dwt'), '', array());

}

/*------------------------------------------------------ */
//-- 秒杀商品 翻页、排序
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'sg_query')
{
	require_once('includes/lib_goods.php');
	$sec_id = empty($_REQUEST['sec_id']) ? 0 : intval($_REQUEST['sec_id']);
	$tb_id  = empty($_REQUEST['tb_id'])  ? 0 : intval($_REQUEST['tb_id']);
	$list = get_add_seckill_goods($sec_id,$tb_id);
	$smarty->assign('seckill_goods',    $list['seckill_goods']);
    $smarty->assign('filter',       	$list['filter']);
    $smarty->assign('record_count', 	$list['record_count']);
    $smarty->assign('page_count',   	$list['page_count']);

	make_json_result($smarty->fetch('seckill_set_goods_info.dwt'), '', array('filter' => $list['filter'], 'page_count' => $list['page_count']));

}

/*------------------------------------------------------ */
//-- 秒杀活动添加页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add' || $_REQUEST['act'] == 'edit')
{
    admin_priv('seckill_manage');

    $smarty->assign('lang',         $_LANG);
    $smarty->assign('action_link',  array('href' => 'seckill.php?act=list', 'text' => $_LANG['seckill_list']));
    $smarty->assign('cfg_lang',     $_CFG['lang']);
    $sec_id = !empty($_GET['sec_id']) ? intval($_GET['sec_id']) : 1;

	if($_REQUEST['act'] == 'add'){
		$smarty->assign('ur_here',      $_LANG['seckill_add']);
		$smarty->assign('form_act',     'insert');
		$tomorrow = local_strtotime('+1 days');
		$next_week = local_strtotime('+8 days');
		$seckill_arr['begin_time']   = local_date('Y-m-d', $tomorrow);
		$seckill_arr['acti_time']   = local_date('Y-m-d', $next_week);
	
	}else{
		$smarty->assign('ur_here',		$_LANG['seckill_edit']);	
		$smarty->assign('form_act',     'update');		
		$seckill_arr = get_seckill_info();
	}
	
	$smarty->assign('sec',     $seckill_arr);		
    assign_query_info();
    $smarty->display('seckill_info.dwt');
}

/*------------------------------------------------------ */
//-- 秒杀活动添加/编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert' || $_REQUEST['act'] == 'update')
{
    /* 获得日期信息 */
	$sec_id = empty($_REQUEST['sec_id']) ? '' : intval($_REQUEST['sec_id']);
	$acti_title = $_REQUEST['acti_title'] ? trim($_REQUEST['acti_title']) : '';
	$begin_time = local_strtotime($_REQUEST['begin_time']);
    $acti_time = local_strtotime($_REQUEST['acti_time']);
	$is_putaway = empty($_REQUEST['is_putaway']) ? 0 : intval($_REQUEST['is_putaway']);
    $add_time = gmtime();//添加时间;
    $ru_id = $adminru['ru_id'];
    $review_status = isset($_REQUEST['review_status']) ? intval($_REQUEST['review_status']) : 3;

	if($_REQUEST['act'] == 'insert'){
		/*检查名称是否重复*/
		$is_only = $exc->is_only('acti_title', $_REQUEST['acti_title'],0);
		if (!$is_only)
		{
			sys_msg(sprintf($_LANG['title_exist'], stripslashes($_REQUEST['acti_title'])), 1);
		}	
		/* 插入数据库。 */
		$sql = "INSERT INTO ".$ecs->table('seckill')." (ru_id, acti_title, begin_time, acti_time, is_putaway, add_time, review_status)
		VALUES ('$ru_id', '$acti_title', '$begin_time', '$acti_time', '$is_putaway', '$add_time', '$review_status')";

		if($db->query($sql)){
			/* 提示信息 */
			$link[0]['text'] = $_LANG['back_list'];
			$link[0]['href'] = 'seckill.php?act=list';

			sys_msg($_LANG['add'] . "&nbsp;" .$_POST['acti_title'] . "&nbsp;" . $_LANG['attradd_succed'],0, $link);		
		}else{
			sys_msg($_LANG['add'] . "&nbsp;" .$_POST['acti_title'] . "&nbsp;" . $_LANG['attradd_failed'],1);
		}		
	}else{
		/*检查名称是否重复*/
		$is_only = $exc->is_only('acti_title', $_POST['acti_title'],0,"sec_id != '$sec_id'");
		if (!$is_only)
		{
			sys_msg(sprintf($_LANG['title_exist'], stripslashes($_REQUEST['acti_title'])), 1);
		}
		
		/* 修改入库。 */
		$sql = "UPDATE " .$ecs->table('seckill'). " SET ".
			" acti_title       = '$acti_title', ".
			" begin_time       = '$begin_time', ".
			" acti_time        = '$acti_time', ".
			" is_putaway       = '$is_putaway', ".
            " review_status    = '$review_status' ".
			" WHERE sec_id     = '$sec_id'";
		
		$db->query($sql);	
		
		/* 清除缓存 */
		clear_cache_files();

		/* 提示信息 */
		$link[0]['text'] = $_LANG['back_list'];
		$link[0]['href'] = 'seckill.php?act=list';

		sys_msg($_LANG['edit'] . "&nbsp;" .$_POST['acti_title'] . "&nbsp;" . $_LANG['attradd_succed'],0, $link);
	}
}

/*------------------------------------------------------ */
//-- 秒杀时段添加页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'time_add' || $_REQUEST['act'] == 'time_edit')
{
    admin_priv('seckill_manage');

    $smarty->assign('lang',         $_LANG);
    $smarty->assign('action_link',  array('href' => 'seckill.php?act=time_bucket', 'text' => $_LANG['seckill_time_bucket']));
    $smarty->assign('cfg_lang',     $_CFG['lang']);
    $tb_id = !empty($_GET['tb_id']) ? intval($_GET['tb_id']) : 0;
	if($_REQUEST['act'] == 'time_add'){
		$smarty->assign('ur_here',      $_LANG['time_bucket_add']);
		$smarty->assign('form_act',     'time_insert');
		$sql = " SELECT MAX(end_time) FROM ".$ecs->table('seckill_time_bucket');
		$tb_arr['begin_time '] = $begin_time 	= $db->getOne($sql);
		$tb_arr['begin_time '] = explode(':',$begin_time);

		if($begin_time){
			$tb_arr['begin_hour'] 		= $tb_arr['begin_time '][0];
			$tb_arr['begin_minute'] 	= $tb_arr['begin_time '][1];
			$tb_arr['begin_second'] 	= $tb_arr['begin_time '][2]+1;			
		}
	}else{
		$smarty->assign('ur_here',		$_LANG['seckill_edit']);	
		$smarty->assign('form_act',     'time_update');		
		$tb_arr = get_time_bucket_info($tb_id);
	}

	$smarty->assign('tb',     $tb_arr);		
    assign_query_info();
    $smarty->display('seckill_time_bucket_info.dwt');
}

/*------------------------------------------------------ */
//-- 秒杀时段添加/编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'time_insert' || $_REQUEST['act'] == 'time_update')
{
    /* 获得日期信息 */
	$tb_id = empty($_REQUEST['tb_id']) ? '' : intval($_REQUEST['tb_id']);
	$title = $_REQUEST['title'] ? trim($_REQUEST['title']) : '';
    $begin_hour   = $_REQUEST['begin_hour'] > 0 &&  $_REQUEST['begin_hour'] < 24	?	intval($_REQUEST['begin_hour'])		:	0;
    $begin_minute = $_REQUEST['begin_minute'] > 0 && $_REQUEST['begin_minute'] < 60	?	intval($_REQUEST['begin_minute'])	:	0;
    $begin_second = $_REQUEST['begin_second'] > 0 && $_REQUEST['begin_second'] < 60	?	intval($_REQUEST['begin_second'])	:	0;
    $end_hour 	  = $_REQUEST['end_hour'] > 0 && $_REQUEST['end_hour'] < 24			?	intval($_REQUEST['end_hour'])		:	0;
    $end_minute   = $_REQUEST['end_minute'] > 0 && $_REQUEST['end_minute'] < 60		?	intval($_REQUEST['end_minute'])		:	0;
    $end_second   = $_REQUEST['end_second'] > 0 && $_REQUEST['end_second'] < 60		?	intval($_REQUEST['end_second'])		:	0;

	$begin_time = $begin_hour.':'.$begin_minute.':'.$begin_second;
	$end_time 	= $end_hour.':'.$end_minute.':'.$end_second;

	if(!contrast_time($begin_time,$end_time)){
		sys_msg($_LANG['end_lt_begin'],1);
	}
	
	if($_REQUEST['act'] == 'time_insert'){
		/*检查名称是否重复*/
		$is_only = $exc_tb->is_only('title', $title, 0);
		if (!$is_only)
		{
			sys_msg(sprintf($_LANG['title_exist'], stripslashes($title)), 1);
		}	
		/* 插入数据库。 */
		$sql = "INSERT INTO ".$ecs->table('seckill_time_bucket')." (title, begin_time, end_time)
		VALUES ('$title', '$begin_time', '$end_time')";

		if($db->query($sql)){
			/* 提示信息 */
			$link[0]['text'] = $_LANG['back_list'];
			$link[0]['href'] = 'seckill.php?act=time_bucket';

			sys_msg($_LANG['add'] . "&nbsp;" .$title . "&nbsp;" . $_LANG['attradd_succed'],0, $link);		
		}else{
			sys_msg($_LANG['add'] . "&nbsp;" .$title . "&nbsp;" . $_LANG['attradd_failed'],1);
		}		
	}else{
		/*检查名称是否重复*/
		$is_only = $exc_tb->is_only('title', $title, 0, "id != '$tb_id'");
		if (!$is_only)
		{
			sys_msg(sprintf($_LANG['title_exist'], stripslashes($title)), 1);
		}
		
		/* 判断当前编辑的结束时间是否规范（必须大于当前时段开始时间且小于下一时间段结束时间） */
		$row = edit_end_time($tb_id,$end_time);
		if(!$row){
			sys_msg($_LANG['end_lt_next_end'],1);
		}
		
		/* 修改入库 */		
		$sql = "UPDATE " .$ecs->table('seckill_time_bucket'). " SET ".
			" title       = '$title', ".
			" begin_time       = '$begin_time', ".
			" end_time       = '$end_time' ".
			" WHERE id   = '$tb_id'";	
		$db->query($sql);		
		
		/* 清除缓存 */
		clear_cache_files();

		/* 提示信息 */
		$link[0]['text'] = $_LANG['back_list'];
		$link[0]['href'] = 'seckill.php?act=time_bucket';

		sys_msg($_LANG['edit'] . "&nbsp;" . $title . "&nbsp;" . $_LANG['attradd_succed'],0, $link);
	}
}

/*------------------------------------------------------ */
//-- 活动上下线
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'toggle_putaway') {

    $id = intval($_REQUEST['id']);
	$val = intval($_REQUEST['val']);

    /* 修改 */
    $sql = "UPDATE " . $ecs->table('seckill') . " SET is_putaway = '$val' WHERE sec_id = '$id'";
    $result = $db->query($sql);
    if ($result) {
        clear_cache_files();
        make_json_result($val);
    }
}

/*------------------------------------------------------ */
//-- 设置秒杀商品列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'set_goods')
{
    admin_priv('seckill_manage');
    $sec_id = !empty($_GET['sec_id']) ? intval($_GET['sec_id']) : 0;
    $smarty->assign('ur_here',     $_LANG['set_seckill_goods']);
    $smarty->assign('action_link' , array('text' => $_LANG['seckill_list'], 'href' => 'seckill.php?act=list'));

    $list = get_time_bucket_list();
	$smarty->assign('sec_id',		$sec_id);
    $smarty->assign('time_bucket',  $list);
	$smarty->assign('full_page',	1);
	assign_query_info();
    $smarty->display('seckill_set_goods.dwt');
}

/*------------------------------------------------------ */
//-- 设置秒杀商品
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'add_goods')
{
    admin_priv('seckill_manage');
	require_once('includes/lib_goods.php');
	
	$sec_id = !empty($_GET['sec_id']) ? intval($_GET['sec_id']) : 0;
	$tb_id  = !empty($_GET['tb_id'])  ? intval($_GET['tb_id'])  : 0;
	set_default_filter(); //设置默认筛选	
	assign_query_info();   
	
	$list = get_add_seckill_goods($sec_id,$tb_id);
	$smarty->assign('seckill_goods',    $list['seckill_goods']);
    $smarty->assign('filter',       	$list['filter']);
    $smarty->assign('record_count', 	$list['record_count']);
    $smarty->assign('page_count',   	$list['page_count']);
    $smarty->assign('ur_here',     $_LANG['seckill_goods_info']);
    $smarty->assign('action_link', array('text' => $_LANG['set_seckill_goods'], 'href' => "seckill.php?act=set_goods&sec_id=$sec_id"));
	$smarty->assign('sec_id',      $sec_id);
	$smarty->assign('tb_id',       $tb_id);
	$smarty->assign('full_page',   1);
    $smarty->display('seckill_set_goods_info.dwt');
}

/*------------------------------------------------------ */
//-- 删除秒杀商品
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'sg_remove')
{
    $id = intval($_REQUEST['id']);
	$sql = " SELECT sec_id, tb_id FROM ". $ecs->table("seckill_goods") ." WHERE id = '$id' ";
	$res = $db->getRow($sql);
	$sec_id = $res['sec_id'];
	$tb_id = $res['tb_id'];
    if($id){
        $res=$exc_sg->drop($id);
    }
	$url = 'seckill.php?act=sg_query&sec_id='. $sec_id . '&tb_id='. $tb_id . str_replace('act=sg_remove', '', $_SERVER['QUERY_STRING']);
	ecs_header("Location: $url\n");
	exit;
}

/*------------------------------------------------------ */
//-- 删除秒杀活动
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    $sec_id = intval($_REQUEST['id']);
    if($sec_id){
        $res=$exc->drop($sec_id);
        if($res){
			$db->query(" DELETE FROM ".$ecs->table('seckill_goods')." WHERE sec_id='$sec_id' ");
		}
    }
	$url = 'seckill.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);
	ecs_header("Location: $url\n");
	exit;
}

/*------------------------------------------------------ */
//-- 删除秒杀时段
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'tb_remove')
{
    $tb_id = intval($_REQUEST['id']);
    if($tb_id){
        $res=$exc_tb->drop($tb_id);
		$sql = " DELETE FROM ".$ecs->table('seckill_goods')." WHERE tb_id = '$tb_id' ";
		$db->query($sql);
    }
	$url = 'seckill.php?act=tb_query&' . str_replace('act=tb_remove', '', $_SERVER['QUERY_STRING']);
	ecs_header("Location: $url\n");
	exit;
}

/*--------------------------------------------------------*/
//商品模块弹窗
/*--------------------------------------------------------*/
else if($_REQUEST['act'] == 'goods_info')
{
	$json = new JSON;
	$result = array('content' => '','mode'=>'');
	/*处理数组*/
	$cat_id = !empty($_REQUEST['cat_id']) ?  intval($_REQUEST['cat_id']) : 0;
	$goods_type = isset($_REQUEST['goods_type']) ? intval($_REQUEST['goods_type']) : 0;
	$_REQUEST['spec_attr']=strip_tags(urldecode($_REQUEST['spec_attr']));
	$_REQUEST['spec_attr'] = json_str_iconv($_REQUEST['spec_attr']);
	$_REQUEST['spec_attr'] = !empty($_REQUEST['spec_attr']) ? stripslashes($_REQUEST['spec_attr']) : '';
	if(!empty($_REQUEST['spec_attr'])){
		$spec_attr = $json->decode(stripslashes($_REQUEST['spec_attr']));
		$spec_attr = sec_object_to_array($spec_attr);
	}
	$spec_attr['is_title'] = isset($spec_attr['is_title']) ? $spec_attr['is_title'] : 0;
	$spec_attr['itemsLayout'] = isset($spec_attr['itemsLayout']) ? $spec_attr['itemsLayout'] : 'row4';
	$result['mode'] = isset($_REQUEST['mode'])  ? addslashes($_REQUEST['mode'])  : '';
	$result['diff'] = isset($_REQUEST['diff'])  ?  intval($_REQUEST['diff'])  : 0;
	$lift = isset($_REQUEST['lift']) ? trim($_REQUEST['lift']) : '';
	//取得商品列表
	if($spec_attr['goods_ids'])
	{
		$goods_info = explode(',', $spec_attr['goods_ids']);
		foreach( $goods_info as $k=>$v)
		{  
			if( !$v )
			{
				unset( $goods_info[$k] );  
			}
		}   
		if(!empty($goods_info))
	   {
		   $where = " WHERE g.is_on_sale=1 AND g.is_delete=0 AND g.goods_id".db_create_in($goods_info);
		   
		   //ecmoban模板堂 --zhuo start
			if($GLOBALS['_CFG']['review_goods'] == 1){
					$where .= ' AND g.review_status > 2 ';
			}
			//ecmoban模板堂 --zhuo end

		   $sql = "SELECT g.goods_name,g.goods_id,g.goods_thumb,g.original_img,g.shop_price FROM " . $ecs->table('goods') . " AS g " . $where ;
		   $goods_list = $db->getAll($sql);
		   
		   foreach($goods_list as $k=>$v){
				$goods_list[$k]['shop_price'] = price_format($v['shop_price']);
			}
	
		   $smarty->assign('goods_list', $goods_list);
		   $smarty->assign('goods_count',     count($goods_list));
	   }
	}
	/* 取得分类列表 */
	//获取下拉列表 by wu start
	set_default_filter(0, $cat_id); //设置默认筛选
	$smarty->assign('parent_category', get_every_category($cat_id)); //上级分类导航
	$smarty->assign('select_category_html', $select_category_html);
	$smarty->assign('brand_list',   get_brand_list());
	$smarty->assign('arr',   $spec_attr);
	$smarty->assign("goods_type",$goods_type);
	$smarty->assign("mode",$result['mode']);
	$smarty->assign("cat_id",$cat_id);
	$smarty->assign("lift",$lift);
	$result['content'] = $GLOBALS['smarty']->fetch('library/add_seckill_goods.lbi');
	die($json->encode($result));
}

/*------------------------------------------------------ */
//-- 商品模块
/*------------------------------------------------------ */
elseif($_REQUEST['act'] == 'changedgoods'){
    require(ROOT_PATH . '/includes/lib_goods.php');
    $json = new JSON;
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $spec_attr = array();
    $result['lift'] = isset($_REQUEST['lift']) ? trim($_REQUEST['lift']) : '';
    $result['spec_attr'] = !empty($_REQUEST['spec_attr']) ? stripslashes($_REQUEST['spec_attr']) : '';
    if($_REQUEST['spec_attr']){
        $_REQUEST['spec_attr']=strip_tags(urldecode($_REQUEST['spec_attr']));
        $_REQUEST['spec_attr'] = json_str_iconv($_REQUEST['spec_attr']);
        if(!empty($_REQUEST['spec_attr'])){
            $spec_attr = $json->decode($_REQUEST['spec_attr']);
            $spec_attr = object_to_array($spec_attr);
        }
    }
    $sort_order = isset($_REQUEST['sort_order'])  ? $_REQUEST['sort_order']  :  1;
    $cat_id = isset($_REQUEST['cat_id'])  ? explode('_', $_REQUEST['cat_id'])  :  array();
    $brand_id = isset($_REQUEST['brand_id'])  ? intval($_REQUEST['brand_id'])  : 0;
    $keyword = isset($_REQUEST['keyword'])  ? addslashes($_REQUEST['keyword'])  : '';
    $goodsAttr = isset($spec_attr['goods_ids'])  ? explode(',', $spec_attr['goods_ids'])  :  '';
    $goods_ids = isset($_REQUEST['goods_ids'])  ? explode(',', $_REQUEST['goods_ids'])  :  '';
    $result['goods_ids'] = !empty($goodsAttr) ? $goodsAttr : $goods_ids;
    $result['cat_desc'] = isset($spec_attr['cat_desc'])  ? addslashes($spec_attr['cat_desc'])  :  '';
    $result['cat_name'] = isset($spec_attr['cat_name'])  ? addslashes($spec_attr['cat_name'])  :  '';
    $result['align'] = isset($spec_attr['align'])  ? addslashes($spec_attr['align'])  :  '';
    $result['is_title'] = isset($spec_attr['is_title'])  ? intval($spec_attr['is_title'])  : 0;
    $result['itemsLayout'] = isset($spec_attr['itemsLayout'])  ? addslashes($spec_attr['itemsLayout'])  : '';
	$result['diff'] = isset($_REQUEST['diff'])  ?  intval($_REQUEST['diff'])  : 0;
    $type = isset($_REQUEST['type'])  ? intval($_REQUEST['type'])  :  0;
    $temp = isset($_REQUEST['temp'])   ?  $_REQUEST['temp'] : 'goods_list';
    $resetRrl = isset($_REQUEST['resetRrl']) ? intval($_REQUEST['resetRrl']) : 0;
    
    $result['mode'] = isset($_REQUEST['mode'])  ? $_REQUEST['mode']  : '';
    $smarty->assign('temp',$temp);
    $where = "WHERE g.is_on_sale=1 AND g.is_delete=0 ";
    //ecmoban模板堂 --zhuo start
    if($GLOBALS['_CFG']['review_goods'] == 1){
            $where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end
    if($cat_id[0] > 0)
    {
        $where .= " AND ".get_children($cat_id[0]);
    }
    if($brand_id > 0)
    {
        $where .= " AND g.brand_id = '$brand_id'";
    }
    if($keyword)
    {
        $where .= " AND g.goods_name  LIKE '%$keyword%'";
    }
     if($result['goods_ids'] && $type == '0')
    {
        $where .= " AND g.goods_id".db_create_in($result['goods_ids']);
    }
    $sort = '';
    switch ($sort_order) {
		case '1':
			$sort .= " ORDER BY g.add_time ASC";
			break;

		case '2' :
			$sort .= " ORDER BY g.add_time DESC";
			break;

		case '3' :
			$sort .= " ORDER BY g.sort_order ASC";
			break;

		case '4' :
			$sort .= " ORDER BY g.sort_order DESC";
			break;

		case '5' :
			$sort .= " ORDER BY g.goods_name ASC";
			break;

        case '6' :
            $sort .= " ORDER BY g.goods_name DESC";
            break;
    }
    if($type == 1){
        $list = getGoodslist($where,$sort);
        $goods_list = $list['list'];
        $filter = $list['filter'];
        $filter['cat_id'] = $cat_id[0];
        $filter['sort_order'] = $sort_order;
        $filter['keyword'] = $keyword;
        $smarty->assign('filter',     $filter);
        
    }else{
        $sql = "SELECT g.promote_start_date, g.promote_end_date, g.promote_price, g.goods_name, g.goods_id, g.goods_thumb, g.shop_price, g.market_price, g.original_img FROM " . 
            $ecs->table('goods') . " AS g " . $where .$sort;
        $goods_list = $db->getAll($sql);
    }
    if (!empty($goods_list)) {
        foreach ($goods_list as $k => $v) {
            $goods_list[$k]['goods_thumb'] = get_image_path($v['goods_id'], $v['goods_thumb']);
            $goods_list[$k]['original_img'] = get_image_path($v['goods_id'], $v['original_img']);
            $goods_list[$k]['url'] = build_uri('goods', array('gid' => $v['goods_id']), $v['goods_name']);
            $goods_list[$k]['shop_price'] = price_format($v['shop_price']);
            if ($v['promote_price'] > 0) {
                $goods_list[$k]['promote_price'] = bargain_price($v['promote_price'], $v['promote_start_date'], $v['promote_end_date']);
            } else {
                $goods_list[$k]['promote_price'] = 0;
            }
            if ($v['goods_id'] > 0 && in_array($v['goods_id'], $result['goods_ids']) && !empty($result['goods_ids'])) {
                $goods_list[$k]['is_selected'] = 1;
            }
        }
    }
    $smarty->assign("is_title",$result['is_title']);
    $smarty->assign('goods_list', $goods_list);

    $smarty->assign('goods_count',     count($goods_list));
	$smarty->assign('attr',$spec_attr);
    $result['content'] = $GLOBALS['smarty']->fetch('library/seckill_goods_list.lbi');
    die(json_encode($result));
}

/*------------------------------------------------------ */
//-- 修改秒杀商品价格
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_sec_price')
{
    check_authz_json('seckill_manage');

    $id       	= intval($_POST['id']);
    $sec_price  = floatval($_POST['val']);

    if ($exc_sg->edit("sec_price = '$sec_price'", $id))
    {
        clear_cache_files();
        make_json_result($sec_price);
    }
}

/*------------------------------------------------------ */
//-- 修改秒杀商品数量
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_sec_num')
{
    check_authz_json('seckill_manage');

    $id       	= intval($_POST['id']);
    $sec_num  = intval($_POST['val']);

    if ($exc_sg->edit("sec_num = '$sec_num'", $id))
    {
        clear_cache_files();
        make_json_result($sec_num);
    }
}

/*------------------------------------------------------ */
//-- 修改秒杀商品限购数量
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_sec_limit')
{
    check_authz_json('seckill_manage');

    $id       	= intval($_POST['id']);
    $sec_limit  = intval($_POST['val']);

    if ($exc_sg->edit("sec_limit = '$sec_limit'", $id))
    {
        clear_cache_files();
        make_json_result($sec_limit);
    }
}

/*
*  秒杀活动商品列表
*/
function get_seckill_list(){
    $result = get_filter();
    if ($result === false)
    {
        /* 查询条件 */
        $filter['keywords']   = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }
		
        $filter['sort_by']    = empty($_REQUEST['sort_by']) ? 'sec_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
		$where = " WHERE 1 ";
		$where .= empty($_REQUEST['sec_id']) ? '' : " AND sec_id = '". trim($_REQUEST['sec_id']) ."' ";
        $where .= (!empty($filter['keywords'])) ? " AND acti_title like '%". mysql_like_quote($filter['keywords']) ."%'" : '';


        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('seckill') .$where;

        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $filter = page_and_size($filter);

        /* 获活动数据 */
        $sql = "SELECT sec_id, begin_time, acti_title, is_putaway, acti_time, review_status, ru_id ".
               " FROM " . $GLOBALS['ecs']->table('seckill') .
			   $where ." ORDER by $filter[sort_by] $filter[sort_order] LIMIT ". $filter['start'] .", " . $filter['page_size'];
        
        $filter['keywords'] = stripslashes($filter['keywords']);
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $GLOBALS['db']->getAll($sql);

    foreach ($row AS $key => $val)
    {
		$time = time();
		$row[$key]['begin_time'] = local_date("Y-m-d", $val['begin_time']);
        $row[$key]['acti_time'] = local_date("Y-m-d", $val['acti_time']);
		$start_time = local_strtotime($row[$key]['begin_time']);
		$end_time = local_strtotime($row[$key]['acti_time']);
		if($time > $end_time){
			$row[$key]['time'] = "活动结束";
		}elseif($time < $end_time && $time > $start_time){
			$row[$key]['time'] = "活动进行中";
		}else{
			$row[$key]['time'] = "活动未开始";
		}
        $row[$key]['ru_name'] = get_shop_name($val['ru_id'], 1);
    }
	
    $arr = array('seckill' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;	
}

/*
* 秒杀活动商品详情
*/
function get_seckill_info(){
	$sql = " SELECT sec_id, begin_time, acti_title, acti_time, is_putaway, ru_id, review_status FROM ". $GLOBALS['ecs']->table('seckill') .
			" WHERE sec_id = '".intval($_REQUEST['sec_id'])."' ";
	$arr = $GLOBALS['db']->getRow($sql);
	
	$arr['begin_time'] = local_date("Y-m-d", $arr['begin_time']);
	$arr['acti_time'] = local_date("Y-m-d", $arr['acti_time']);
	
	return $arr;
	
}

function get_time_bucket_list(){
	$sql = " SELECT * FROM ".$GLOBALS['ecs']->table('seckill_time_bucket');
	$res = $GLOBALS['db']->getAll($sql);
	return $res;
}

function get_time_bucket_info($id){
	$sql = " SELECT * FROM ".$GLOBALS['ecs']->table('seckill_time_bucket')." WHERE id = '$id' ";
	$row = $GLOBALS['db']->getRow($sql);
	if($row){
		$begin_time 	= explode(':',$row['begin_time']);
		$row['begin_hour'] 	= $begin_time[0];
		$row['begin_minute'] 	= $begin_time[1];
		$row['begin_second'] 	= $begin_time[2];
		$end_time 	= explode(':',$row['end_time']);
		$row['end_hour'] 	= $end_time[0];
		$row['end_minute'] 	= $end_time[1];
		$row['end_second'] 	= $end_time[2];
	}
	return $row;
}

//比对开始结束时间大小
function contrast_time($begin_time,$end_time){
	
	$local_begin_time = local_strtotime($begin_time);
	$local_end_time = local_strtotime($end_time);
	
	if($local_begin_time >= $local_end_time){
		return false;
	}
	return true;
}

//当编辑结束时间时判断是否在可修改范围内
function edit_end_time($tb_id,$end_time){
	$sql = " SELECT begin_time, end_time FROM ".$GLOBALS['ecs']->table('seckill_time_bucket')." WHERE id = '$tb_id' ";
	$row = $GLOBALS['db']->getRow($sql);

	$old_end_time = $row['end_time'];
	$formated_old_end_time = explode(':',$old_end_time);
	$formated_old_end_time[2] = str_pad($formated_old_end_time[2]+1,2,"0",STR_PAD_LEFT);
	$old_end_time = implode(':',$formated_old_end_time);
	
	$formated_next_begin_time = explode(':',$end_time);
	$formated_next_begin_time[2] = str_pad($formated_next_begin_time[2]+1,2,"0",STR_PAD_LEFT);
	$edit_begin_time = implode(':',$formated_next_begin_time);	

	$sql = " SELECT end_time FROM ".$GLOBALS['ecs']->table('seckill_time_bucket')." WHERE begin_time = '$old_end_time' ";	
	$next_end_time = $GLOBALS['db']->getOne($sql);
	if($next_end_time){
		if(contrast_time($end_time, $next_end_time)){
			$sql = "UPDATE " .$GLOBALS['ecs']->table('seckill_time_bucket'). " SET ".
				" begin_time = '$edit_begin_time' ".
				" WHERE begin_time = '$old_end_time'";	
			$GLOBALS['db']->query($sql);

			return true;		
		};		
	}else{
		return true;
	}

	return false;
}

/*对象转数组*/
function sec_object_to_array($obj) {
    $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
    if($_arr){
        foreach ($_arr as $key => $val) {
            $val = (is_array($val)) || is_object($val) ? object_to_array($val) : $val;
            $arr[$key] = $val;
        }
    }else{
        $arr = array();
    }
    
    return $arr;
}

function getGoodslist($where = '',$sort = '',$search = '',$leftjoin = ''){
    
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods') ." AS g ".$leftjoin. $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        $filter = page_and_size($filter);
        $where .= $sort." LIMIT " . $filter['start'].",".$filter['page_size'];
        $sql = "SELECT g.promote_start_date, g.promote_end_date, g.promote_price, g.goods_name, g.goods_id, g.goods_thumb, g.shop_price, g.market_price, g.original_img $search FROM " . 
            $GLOBALS['ecs']->table('goods') . " AS g " .$leftjoin. $where ;
        
        $goods_list = $GLOBALS['db']->getAll($sql);
        $filter['page_arr'] = seller_page($filter,$filter['page']);
        return array('list' => $goods_list, 'filter' => $filter);
}






