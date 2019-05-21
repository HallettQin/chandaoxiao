<?php

/**
 * ECSHOP 商品分类管理程序
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
admin_priv('oss_configure');

$smarty->assign('menu_select',array('action' => '01_system', 'current' => 'oss_configure'));
/*------------------------------------------------------ */
//-- OSS Bucket列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    
    $smarty->assign('action_link',  array('text' => $_LANG['02_oss_add'], 'href'=>'oss_configure.php?act=add'));
    
    /* 模板赋值 */
    $smarty->assign('ur_here',      $_LANG['oss_configure']);
    $smarty->assign('form_act',    'insert');
    
    $bucket_list = bucket_list();

    $smarty->assign('bucket_list',    $bucket_list['bucket_list']);
    $smarty->assign('filter',       $bucket_list['filter']);
    $smarty->assign('record_count', $bucket_list['record_count']);
    $smarty->assign('page_count',   $bucket_list['page_count']);
    $smarty->assign('full_page',    1);

    /* 列表页面 */
    assign_query_info();
    $smarty->display('oss_configure_list.dwt');
}

/*------------------------------------------------------ */
//-- ajax返回Bucket列表
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    $bucket_list = bucket_list();

    $smarty->assign('bucket_list',    $bucket_list['bucket_list']);
    $smarty->assign('filter',       $bucket_list['filter']);
    $smarty->assign('record_count', $bucket_list['record_count']);
    $smarty->assign('page_count',   $bucket_list['page_count']);
    
    $sort_flag  = sort_flag($bucket_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('oss_configure_list.dwt'), '', array('filter' => $bucket_list['filter'], 'page_count' => $bucket_list['page_count']));
}

/*------------------------------------------------------ */
//-- OSS 添加Bucket
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'add')
{
    $smarty->assign('action_link',  array('text' => $_LANG['01_oss_list'], 'href'=>'oss_configure.php?act=list'));
    
    $bucket['regional'] = 'shanghai';
    $smarty->assign('bucket',    $bucket);
    
    /* 模板赋值 */
    $smarty->assign('ur_here',      $_LANG['oss_configure']);
    $smarty->assign('form_act',    'insert');

    /* 列表页面 */
    assign_query_info();
    $smarty->display('oss_configure_info.dwt');
}

/*------------------------------------------------------ */
//-- OSS 编辑Bucket
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'edit')
{
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    
    $smarty->assign('action_link',  array('text' => $_LANG['01_oss_list'], 'href'=>'oss_configure.php?act=list'));
    
    $date = array('*');
    $where = "id = '$id'";
    $bucket_info = get_table_date('oss_configure', $where, $date);
    $smarty->assign('bucket',    $bucket_info);
    
    /* 模板赋值 */
    $smarty->assign('ur_here',      $_LANG['oss_configure']);
    $smarty->assign('form_act',    'update');

    /* 列表页面 */
    assign_query_info();
    $smarty->display('oss_configure_info.dwt');
}

/*------------------------------------------------------ */
//-- OSS 添加Bucket
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert' || $_REQUEST['act'] == 'update')
{
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    
    
    $other['bucket'] = empty($_POST['bucket']) ? '' : trim($_POST['bucket']);
    $other['keyid'] = empty($_POST['keyid']) ? '' : trim($_POST['keyid']);
    $other['keysecret'] = empty($_POST['keysecret']) ? '' : trim($_POST['keysecret']);
    $other['is_cname'] = empty($_POST['is_cname']) ? '' : intval($_POST['is_cname']);
    $other['endpoint'] = empty($_POST['endpoint']) ? '' : trim($_POST['endpoint']);
    $other['regional'] = empty($_POST['regional']) ? '' : trim($_POST['regional']);
    $other['is_use'] = empty($_POST['is_use']) ? '' : intval($_POST['is_use']);
    
    $date = array('bucket');
    $where = "bucket = '" .$other['bucket']. "'";
    $where .= !empty($id) ? " AND id <> '$id'" : '';
    $bucket_info = get_table_date('oss_configure', $where, $date);
    
    if($bucket_info){
        sys_msg($_LANG['add_failure'], 1);
    }
    
    if($other['is_use'] == 1){
        $sql = "UPDATE " .$GLOBALS['ecs']->table('oss_configure'). " SET is_use = 0 WHERE 1";
        $GLOBALS['db']->query($sql);
    }
    
    if($id){
        $db->autoExecute($ecs->table('oss_configure'), $other, "UPDATE", "id = '$id'");
        $href = 'oss_configure.php?act=edit&id=' . $id;
        
        $lang_name = $_LANG['edit_success'];
    }else{
        $db->autoExecute($ecs->table('oss_configure'), $other);
        $href = 'oss_configure.php?act=list';
        $lang_name = $_LANG['add_success'];
    }
    
    /* 提示信息 */
    $link[] = array('text' => $_LANG['go_back'], 'href'=>$href);
    sys_msg(sprintf($lang_name, htmlspecialchars(stripslashes($other['bucket']))), 0, $link);
}

/*------------------------------------------------------ */
//-- OSS 批量删除Bucket
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'batch_remove')
{
    if(isset($_REQUEST['checkboxes'])){
        $sql = "DELETE FROM " .$GLOBALS['ecs']->table('oss_configure'). " WHERE id " . db_create_in($_REQUEST['checkboxes']);
        $GLOBALS['db']->query($sql);
        
        /* 提示信息 */
        $link[] = array('text' => $_LANG['go_back'], 'href'=>'oss_configure.php?act=list');
        sys_msg($_LANG['remove_success'], 0, $link);
    }else{
        
        /* 提示信息 */
        $lnk[] = array('text' => $_LANG['go_back'], 'href'=>'oss_configure.php?act=list');
        sys_msg($_LANG['no_select_user'], 0, $lnk);
    }
}

/*------------------------------------------------------ */
//-- OSS 删除Bucket
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    
    $sql = "SELECT bucket FROM " . $GLOBALS['ecs']->table('oss_configure') . " WHERE id = '$id'";
    $bucket = $GLOBALS['db']->getOne($sql);
    
    $sql = "DELETE FROM " .$GLOBALS['ecs']->table('oss_configure'). " WHERE id = '$id'";
    $GLOBALS['db']->query($sql);
    
    /* 提示信息 */
    $link[] = array('text' => $_LANG['go_back'], 'href'=>'oss_configure.php?act=list');
    sys_msg(sprintf($_LANG['remove_success'], $bucket), 0, $link);
}

/**
 *  返回bucket列表数据
 *
 * @access  public
 * @param
 *
 * @return void
 */
function bucket_list()
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
        
        $filter['record_count'] = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('oss_configure') . $where);
        /* 分页大小 */
        $filter = page_and_size($filter);
        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('oss_configure') . $where .
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
    
    $bucket_list = $GLOBALS['db']->getAll($sql);
    $count = count($bucket_list);
    
    for ($i=0; $i<$count; $i++)
    {
        $regional = substr($bucket_list[$i]['regional'], 0, 2);
        
        $http = $GLOBALS['ecs']->http();
        
        if($regional == 'us' || $regional == 'ap'){
            $outside_site = $http . $bucket_list[$i]['bucket'] . ".oss-" .$bucket_list[$i]['regional']. ".aliyuncs.com";
            $inside_site = $http . $bucket_list[$i]['bucket'] . ".oss-" .$bucket_list[$i]['regional']. "-internal.aliyuncs.com";
        }else{
            $outside_site = $http . $bucket_list[$i]['bucket'] . ".oss-cn-" .$bucket_list[$i]['regional']. ".aliyuncs.com";
            $inside_site = $http . $bucket_list[$i]['bucket'] . ".oss-cn-" .$bucket_list[$i]['regional']. "-internal.aliyuncs.com";
        }
        
        $bucket_list[$i]['outside_site'] = $outside_site;
        $bucket_list[$i]['inside_site'] = $inside_site;
        
        if($bucket_list[$i]['regional'] == 'shanghai'){
            $bucket_list[$i]['regional_name'] = '中国（上海）';
        }elseif($bucket_list[$i]['regional'] == 'hangzhou'){
            $bucket_list[$i]['regional_name'] = '中国（杭州）';
        }elseif($bucket_list[$i]['regional'] == 'shenzhen'){
            $bucket_list[$i]['regional_name'] = '中国（深圳）';
        }elseif($bucket_list[$i]['regional'] == 'beijing'){
            $bucket_list[$i]['regional_name'] = '中国（北京）';
        }elseif($bucket_list[$i]['regional'] == 'qingdao'){
            $bucket_list[$i]['regional_name'] = '中国（青岛）';
        }elseif($bucket_list[$i]['regional'] == 'hongkong'){
            $bucket_list[$i]['regional_name'] = '中国（香港）';
        }elseif($bucket_list[$i]['regional'] == 'us-west-1'){
            $bucket_list[$i]['regional_name'] = '美国(加利福尼亚州)';
        }elseif($bucket_list[$i]['regional'] == 'ap-southeast-1'){
            $bucket_list[$i]['regional_name'] = '亚洲(新加坡)';
        }
    }
    
    $arr = array('bucket_list' => $bucket_list, 'filter' => $filter,
        'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}    
?>