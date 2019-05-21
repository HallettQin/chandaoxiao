<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/30
 * Time: 1:33
 */
//by zxk

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

//检查采购商权限
//priv();

require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo
require(ROOT_PATH . '/includes/lib_order.php');  //ecmoban模板堂 --zhuo

/*------------------------------------------------------ */
//-- INPUT
/*------------------------------------------------------ */

$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

$uachar = "/(nokia|sony|ericsson|mot|samsung|sgh|lg|philips|panasonic|alcatel|lenovo|cldc|midp|mobile)/i";
$categorys = get_category_list(0);
$smarty->assign('categorys', $categorys);
if ($_REQUEST['act'] == 'new'){
    //检查采购商权限
    priv();

    $smarty->assign('page_title', '每日上新');
    //新品排行
    $mode     = (isset($_REQUEST['mode'])) ? trim($_REQUEST['mode']) : 'group_buy';
    $smarty->assign('mode', $mode);
    $smarty->display('topnew.dwt');
} elseif ($_REQUEST['act'] == 'topsale') {
    //检查采购商权限
    priv();

    $smarty->assign('page_title', '销售排行');

    //销售排行
    $mode     = (isset($_REQUEST['mode'])) ? trim($_REQUEST['mode']) : 'group_buy';
    $smarty->assign('mode', $mode);

    $smarty->display('topsale.dwt');
} elseif ($_REQUEST['act'] == 'topstore') {
    $smarty->assign('page_title', '优质商家');

    //优质商家
    $smarty->display('topstore.dwt');
} elseif ($_REQUEST['act'] == 'ajaxnew') {
    if (IS_AJAX || true) {
        $cat_id = intval($_GET['id']);
        if (empty($cat_id)) {
            exit(json_encode(['code' => 1, 'message' => '请选择分类']));
        }

        $mode = trim($_REQUEST['mode']);
        if (!in_array($mode, ['group_buy', 'presale', 'wholesale', 'sample'])) {
            exit(json_encode(['code' => 1, 'message' => '请选择商品模式']));
        }

        $goods = get_new10($cat_id, $mode);
        foreach ($goods as $k => $good) {
            $mode = $mode == 'wholesale' ? 'wholesale_goods' : $mode;
            $goods[$k]['url'] = build_uri($mode, ['acid' => $good['act_id'], 'aid'=>$good['act_id'], 'gbid' => $good['act_id'],'id'=>$good['act_id'], 'presaleid'=>$good['act_id'], 'act'=>'view']);;
        }
        $smarty->assign('lists', $goods);
        $val = $smarty->fetch('library/ranknew.lbi');
        die($val);
    }
} elseif ($_REQUEST['act'] == 'ajaxsale') {
    if (IS_AJAX) {
        $cat_id = intval($_GET['id']);
        if (empty($cat_id)) {
            exit(json_encode(['code' => 1, 'message' => '请选择分类']));
        }

        $mode = trim($_REQUEST['mode']);
        if (!in_array($mode, ['group_buy', 'presale', 'wholesale', 'sample'])) {
            exit(json_encode(['code' => 1, 'message' => '请选择商品模式']));
        }

        $goods = get_top10($cat_id, $mode);
        foreach ($goods as $k => $good) {
            $mode = $mode == 'wholesale' ? 'wholesale_goods' : $mode;
            $goods[$k]['url'] = build_uri($mode, ['acid'=>$row['act_id'], 'aid'=>$row['act_id'], 'gbid' => $row['act_id'],'id'=>$row['act_id'], 'presaleid'=>$row['act_id'], 'act'=>'view']);;
        }
        $smarty->assign('lists', $goods);
        $val = $smarty->fetch('library/ranknew.lbi');
        die($val);
    }
} elseif ($_REQUEST['act'] == 'ajaxstore') {
    if (IS_AJAX || true) {
        $cat_id = intval($_GET['id']);

        if (empty($cat_id)) {
            exit(json_encode(['code' => 1, 'message' => '请选择分类']));
        }

        $store_shop_list = get_store_list_top10($cat_id, 'sales_volume');
        $smarty->assign('lists', $store_shop_list['shop_list']);
        $val = $smarty->fetch('library/rankstore.lbi');
        die($val);
    }
}


//获取最新商品
function get_new10($cat_id = '', $mode) {
    $now = gmtime();

    /* 查询条件： */
    $children = get_children($cat_id);
    $where = '1';

    if($children){
        $where .= " AND ($children OR " . get_extension_goods($children) . ")";
    }

    $where .= " AND g.is_delete = 0 ";

    switch($mode) {
        case 'group_buy':
            $table = $GLOBALS['ecs']->table('goods_activity');
            $where .= ' AND b.act_type = '.GAT_GROUP_BUY;
            $where .= ' AND b.start_time <= ' . $now;
            $where .= ' AND b.is_finished < 3';
            break;
        case 'presale':
            $table = $GLOBALS['ecs']->table('presale_activity');
            $where .= ' AND b.is_finished < 3';
            break;
        case 'sample':
            $table = $GLOBALS['ecs']->table('sample_activity');
            break;
        case 'wholesale':
            $table = $GLOBALS['ecs']->table('wholesale');
            break;
    }


    $sql = 'select * from (SELECT b.*, IFNULL(g.goods_thumb, \'\') AS goods_thumb , g.market_price'  . ' FROM ' . $table . ' AS b ' . 'LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' AS g ON b.goods_id = g.goods_id ' . 'WHERE ' . $where  . ' AND b.review_status = 3 ORDER BY b.act_id desc limit 0,100) as bb GROUP BY bb.goods_id ';
    $res = $GLOBALS['db']->selectLimit($sql, 10);

    $list = [];
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $row['url'] = build_uri($mode, array('gbid' => $row['act_id']));
        $list[] = $row;
    }

    return $list;
}

//获取店铺排名 by zxk
function get_store_list_top10($cat_id) {
    $whereShop = " 1 ";

    $where_table = '';
    $select = '';


    $select .= ", (SELECT SUM(og.goods_number) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi, " . $GLOBALS['ecs']->table('order_goods') . " AS og " .
        " WHERE oi.order_id = og.order_id AND og.ru_id = msi.user_id " .
        " AND (oi.order_status = '" . OS_CONFIRMED . "' OR  oi.order_status = '" . OS_SPLITED . "' OR oi.order_status = '" . OS_SPLITING_PART . "') " .
        " AND (oi.pay_status  = '" . PS_PAYING . "' OR  oi.pay_status  = '" . PS_PAYED . "')) AS sales_volume ";

    $select .= ", ((SELECT SUM(g.goods_number) FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
        " WHERE g.user_id = msi.user_id AND g.review_status > 2)) AS goods_number ";

    if ($cat_id) {
        $whereShop .= " AND msi.shop_categoryMain = ".$cat_id;
    }

    $sql = "SELECT msi.shop_id, msi.user_id, msi.shoprz_brandName, msi.shopNameSuffix $select FROM " .
        $GLOBALS['ecs']->table('merchants_shop_information') . " as msi LEFT JOIN " . $GLOBALS['ecs']->table('merchants_grade') . " AS mg ON mg.ru_id = msi.user_id " . $where_table . " where $whereShop" .
        " AND msi.merchants_audit = 1 ORDER BY sales_volume desc, goods_number desc";

    $res = $GLOBALS['db']->selectLimit($sql, 10, 0);

    $arr = [];
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        $_arr['shop_id'] = $row['shop_id'];
        $_arr['shop_id'] = $row['shop_id'];
        $_arr['shopNameSuffix'] = $row['shopNameSuffix'];
        $_arr['shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
        $_arr['sales_volume'] = !empty($row['sales_volume']) ? $row['sales_volume'] : 0;

        $shop_info = get_shop_info_content($row['user_id']);
        $_arr['shop_logo'] = str_replace('../', '', $shop_info['shop_logo']); //商家logo
        $_arr['logo_thumb'] = str_replace('../', '', $shop_info['logo_thumb']); //商家缩略图
        $_arr['street_thumb'] = get_image_path(ltrim($shop_info['street_thumb'], '../')); //店铺街封面图

        //店铺地址
        $_arr['shop_url'] = build_uri('merchants_store', ['urid' => $row['user_id']]);
        $arr[] = $_arr;
    }

    $result = ['shop_list' => $arr];
    return $result;

}
