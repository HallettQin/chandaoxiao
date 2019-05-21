<?php

/**
 * DSC 会员中心
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
require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo

/* 过滤 XSS 攻击和SQL注入 */
get_request_filter();

$user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$action  = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'default';


//交易快照
if ($action == 'trade') {
    assign_template();
    $tradeId = isset($_REQUEST['tradeId']) ? intval($_REQUEST['tradeId']) : 0;
    $snapshot = isset($_REQUEST['snapshot']) ? true : false;
    $sql = " SELECT * FROM " . $ecs->table('trade_snapshot') . " WHERE trade_id = '$tradeId' ";
    $row = $db->getRow($sql);
    //格式化时间戳
    $row['snapshot_time'] = local_date('Y-m-d H:i:s', $row['snapshot_time']);

    if ($row['ru_id'] > 0) {
        $merchants_goods_comment = get_merchants_goods_comment($row['ru_id']); //商家所有商品评分类型汇总
        $smarty->assign('merch_cmt', $merchants_goods_comment);
    }

    // 判断当前商家是否允许"在线客服" start
    $shop_information = get_shop_name($row['ru_id']);
    $shop_information['kf_tel'] = $db->getOne("SELECT kf_tel FROM " . $ecs->table('seller_shopinfo') . "WHERE ru_id = '" . $row['ru_id'] . "'");
    //判断当前商家是平台,还是入驻商家
    if ($row['ru_id'] == 0) {
        //判断平台是否开启了IM在线客服
        if ($db->getOne("SELECT kf_im_switch FROM " . $ecs->table('seller_shopinfo') . "WHERE ru_id = 0")) {
            $shop_information['is_dsc'] = true;
        } else {
            $shop_information['is_dsc'] = false;
        }
    } else {
        $shop_information['is_dsc'] = false;
    }
    $smarty->assign('shop_information', $shop_information);

    $smarty->assign('page_title', $row['goods_name']);  // 页面标题
    $smarty->assign('helps', get_shop_help());     // 网店帮助
    $smarty->assign('snapshot', $snapshot);
    $smarty->assign('goods', $row);
    $smarty->display('trade_snapshot.dwt');
}

?>