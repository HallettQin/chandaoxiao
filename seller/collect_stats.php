<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-1-23
 * Time: 2:26
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'includes/lib_order.php');
require_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/' .ADMIN_PATH. '/statistic.php');
$smarty->assign('menus',$_SESSION['menus']);
$smarty->assign('lang', $_LANG);

$adminru = get_admin_ru_id();
$smarty->assign('ru_id', $adminru['ru_id']);
/* 时间参数 */
if (isset($_POST['start_date']) && !empty($_POST['end_date']))
{
    $start_date = local_strtotime($_POST['start_date']);
    $end_date = local_strtotime($_POST['end_date']);
    if ($start_date == $end_date)
    {
        $end_date   =   $start_date + 86400;
    }
}
else
{
    $today      = strtotime(local_date('Y-m-d'));   //本地时间
    $start_date = $today - 86400 * 6;
    $end_date   = $today + 86400;               //至明天零时
}
$smarty->assign('primary_cat',     $_LANG['06_stats']);

/*------------------------------------------------------ */
//--订单统计
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    admin_priv('sale_order_stats');
    $smarty->assign('current','order_stats_list');
    $smarty->assign('ur_here', $_LANG['collect_stats']);

    $data = collect_list();
    $smarty->assign('data_list', $data['goods']);
    $smarty->assign('filter', $data['filter']);
    $smarty->assign('record_count', $data['record_count']);
    $smarty->assign('page_count', $data['page_count']);
    $page_count_arr = array();
    $page_count_arr = seller_page($data, $_REQUEST['page']);
    $smarty->assign('page_count_arr', $page_count_arr);
    $smarty->assign('full_page', 1);
    $smarty->display('collect_stats.dwt');
} elseif ($_REQUEST['act'] == 'query') {
    $data = collect_list();
    $smarty->assign('data_list', $data['goods']);
    $smarty->assign('filter', $data['filter']);
    $smarty->assign('record_count', $data['record_count']);
    $smarty->assign('page_count', $data['page_count']);
    $page_count_arr = array();
    $page_count_arr = seller_page($data, $_REQUEST['page']);
    $smarty->assign('page_count_arr', $page_count_arr);

    make_json_result($smarty->fetch('collect_stats.dwt'), '',
        array('filter' => $data['filter'], 'page_count' => $data['page_count']));
    exit;
}


function collect_list() {
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    $ruCat = '';
    if($adminru['ru_id'] > 0){
        $ruCat = " and g.user_id = '" .$adminru['ru_id']. "' ";
    }
    //ecmoban模板堂 --zhuo end

    /* 过滤条件 */

    $result = get_filter();

    if ($result === false) {
        $where = 1;
        $where .= $ruCat;
        
        $filter['keyword'] = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        if (!empty($filter['keyword'])) {
            $where .= ' AND (g.goods_sn LIKE \'%' . mysql_like_quote($filter['keyword']) . '%\' OR g.goods_name LIKE \'%' . mysql_like_quote($filter['keyword']) . '%\'' . ')';
        }

        /* 记录总数 */
        $sql = "SELECT g.goods_id" . " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " . " WHERE $where GROUP BY g.goods_id";

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        } else {
            $filter['page_size'] = 15;
        }

        $filter['record_count'] = count($GLOBALS['db']->getAll($sql));
        $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 分页大小 */
        $filter = page_and_size($filter);

        $sql = 'SELECT g.goods_id, g.goods_name, count(cg.rec_id) collect FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g left join ' .$GLOBALS['ecs']->table('collect_goods') . 'as cg ON cg.goods_id = g.goods_id' . ' WHERE '. $where . ' GROUP BY g.goods_id'  . ' order by collect desc  LIMIT ' .  ($filter['page'] - 1) * $filter['page_size'] . "," . $filter['page_size'];
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $GLOBALS['db']->getAll($sql);
    $count = count($row);

    for ($i = 0; $i < $count; $i++) {
        $row[$i]['collect'] = get_collect_goods_user_count($row[$i]['goods_id']);
    }


    return array('goods' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}
