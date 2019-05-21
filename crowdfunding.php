<?php

/**
 * DSC 众筹商品
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

if ((DEBUG_MODE & 2) != 2) {
    $smarty->caching = true;
}

$affiliate = unserialize($GLOBALS['_CFG']['affiliate']);
$smarty->assign('affiliate', $affiliate);

$zcgoods_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

//输出页面操作 by wu
$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'default';
$smarty->assign('action', $action);

/* 页面补充信息 */
require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo
//ecmoban模板堂 --zhuo start
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);

if (isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])) {
    $region_id = $_COOKIE['region_id'];
}
//ecmoban模板堂 --zhuo end
$smarty->assign('now_time', gmtime());           // 当前系统时间

assign_template();

$position = assign_ur_here(0, $_LANG['page_title']);
$smarty->assign('page_title',      $position['title']);    // 页面标题

if (!defined('THEME_EXTENSION')) {
    $categories_pro = get_category_tree_leve_one();
    $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
}

$smarty->assign('helps', get_shop_help());       // 网店帮助
$smarty->assign('feed_url', ($_CFG['rewrite'] == 1) ? "feed-typesnatch.xml" : 'feed.php?type=snatch'); // RSS URL
/* 页面补充信息 */

$gmtime = gmtime();

/* 过滤 XSS 攻击和SQL注入 */
get_request_filter();

/* ------------------------------------------------------ */
//-- PROCESSOR
/* ------------------------------------------------------ */
if (empty($_REQUEST['act'])) {
    $_REQUEST['act'] = 'list';
}

if ($_REQUEST['act'] == 'list') {
    $smarty->assign('zc_title', $_LANG['crowdfunding']);
    $sql = "SELECT * FROM" . $ecs->table('zc_category') . "WHERE parent_id = 0";
    $cate_one = $db->getAll($sql);

    $cate_two = array();
    foreach ($cate_one as $c_val) {
        $sql = "SELECT * FROM" . $ecs->table('zc_category') . "WHERE parent_id = '$c_val[cat_id]'";
        $cate_two[$c_val['cat_id']] = $db->getAll($sql);
    }
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . "ORDER BY id desc";
    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 5) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }
    $smarty->assign('gengduo', count($zc_arr));
    $smarty->assign('sp_zc_list', get_special_zc_list(1));
    $smarty->assign('cate_one', $cate_one);
    $smarty->assign('cate_two', $cate_two);
    $smarty->assign('zc_arr', $new_zc_arr);

    //添加广告
    for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
        $zc_index_banner .= "'zc_index_banner" . $i . ",";
    }
    $smarty->assign('zc_index_banner', $zc_index_banner);

    $smarty->display('crowdfunding.dwt');
}

if ($_REQUEST['act'] == 'quanbu') {
    
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "WHERE title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " $where_wenzi ORDER BY id desc";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 5) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $smarty->assign('gengduo', $gengduo);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_filter.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'cate') {
    $code = intval($_POST['code']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    $cate_two = array();
    $str_id = $code . ',';
    $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = '$code'";
    $cate_two = $db->getAll($sql);
    foreach ($cate_two as $c_val) {
        $str_id .= $c_val['cat_id'] . ',';
        $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = " . $c_val['cat_id'] . " ";
        $cate_three = $db->getAll($sql);
        foreach ($cate_three as $ct_val) {
            $str_id .= $ct_val['cat_id'] . ',';
        }
    }
    $str_id = rtrim($str_id, ',');
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id in($str_id) $where_wenzi ORDER BY id desc";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 5) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $smarty->assign('gengduo', $gengduo);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_filter.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'cate_child') {
    $code = intval($_POST['code']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id='$code' $where_wenzi ORDER BY id desc";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 5) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $smarty->assign('gengduo', $gengduo);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_filter.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'gengduo_pid_zero') {
    $len = $_POST['len'];
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "WHERE title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " $where_wenzi ORDER BY id desc";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $zx_tig = $gengduo - ($len + 3);

    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= ($len + 3)) {
            break;
        }
        //每次点击，增加3个
        if ($i >= $len && $i < ($len + 3)) {
            $new_zc_arr[] = $value;
        }
        $i++;
    }

    $smarty->assign('zx_tig', $zx_tig);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_more.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'gengduo_pid') {
    $pid = intval($_POST['id']);
    $len = intval($_POST['len']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    $cate_two = array();
    if ($pid) {
        $str_id = $pid . ',';
    } else {
        $str_id = '';
    }
    $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = '$pid'";
    $cate_two = $db->getAll($sql);
    foreach ($cate_two as $c_val) {
        if ($c_val['cat_id']) {
            $str_id .= $c_val['cat_id'] . ',';
        }
        $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = " . $c_val['cat_id'] . " ";
        $cate_three = $db->getAll($sql);
        foreach ($cate_three as $ct_val) {
            $str_id .= $ct_val['cat_id'] . ',';
        }
    }
    $str_id = rtrim($str_id, ',');
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . "WHERE cat_id in($str_id) $where_wenzi ORDER BY id desc";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $zx_tig = $gengduo - ($len + 3);

    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= ($len + 3)) {
            break;
        }
        //每次点击，增加3个
        if ($i >= $len && $i < ($len + 3)) {
            $new_zc_arr[] = $value;
        }
        $i++;
    }

    $smarty->assign('zx_tig', $zx_tig);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_more.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'gengduo_tid') {
    $tid = intval($_POST['id']);
    $len = intval($_POST['len']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . "WHERE cat_id='$tid' $where_wenzi ORDER BY id desc";
    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $zx_tig = $gengduo - ($len + 3);

    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= ($len + 3)) {
            break;
        }
        //每次点击，增加3个
        if ($i >= $len && $i < ($len + 3)) {
            $new_zc_arr[] = $value;
        }
        $i++;
    }

    $smarty->assign('zx_tig', $zx_tig);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_more.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'paixu_pid_zero') {
    $pid = intval($_POST['id']);
    $len = intval($_POST['len']);
    $sig = addslashes($_POST['sig']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "WHERE title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY `id` desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY `join_money` desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY `join_num` desc';
            break;
    }
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM " . $ecs->table('zc_project') . " $where_wenzi $where_tj";
		
    $zc_arr = $db->getAll($sql);
    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr) - $len + 5;
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= $len) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $smarty->assign('gengduo', $gengduo);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_filter.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'paixu_pid') {
    $pid = intval($_POST['id']);
    $len = intval($_POST['len']);
    $sig = addslashes($_POST['sig']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY `join_money` desc,id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY `id` desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY `join_money` desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY `join_num` desc';
            break;
    }

    $cate_two = array();
    $str_id = $code . ',';
    $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = '$pid'";
    $cate_two = $db->getAll($sql);
    foreach ($cate_two as $c_val) {
        $str_id .= $c_val['cat_id'] . ',';
        $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = " . $c_val['cat_id'] . " ";
        $cate_three = $db->getAll($sql);
        foreach ($cate_three as $ct_val) {
            $str_id .= $ct_val['cat_id'] . ',';
        }
    }
    $str_id = trim($str_id, ',');
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id in($str_id) $where_wenzi $where_tj ";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr) - $len + 5;
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= $len) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $smarty->assign('gengduo', $gengduo);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_filter.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'paixu_tid') {
    $tid = intval($_POST['id']);
    $len = intval($_POST['len']);
    $sig = addslashes($_POST['sig']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY `join_money` desc,id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY `id` desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY `join_money` desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY `join_num` desc';
            break;
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id='$tid' $where_wenzi $where_tj ";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr) - $len + 5;
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= $len) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $smarty->assign('gengduo', $gengduo);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch("library/zc_filter.lbi");
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'detail') {
    $cid = empty($_GET['id']) ? 0 : intval($_GET['id']);
    $init = get_initiator_info($cid);
    $smarty->assign('init', $init); //发起人信息
    $sql = " SELECT * FROM " . $ecs->table('zc_project') . " WHERE id = '$cid' ";
	$smarty->assign('id',$cid);
    $zhongchou = $db->getRow($sql);

    //如果没有数据，自动跳转到第一条 by wu
    if (empty($zhongchou)) {
        $sql = " SELECT id FROM " . $ecs->table('zc_project');
        $first_id = $db->getOne($sql, true);
		
        header('location:crowdfunding.php?act=detail&id=' . $first_id);
    }

    //如果关注量和赞大于三位数，则替代为千、万... by wu
    $zhongchou['focus_num'] = setNumberFormat($zhongchou['focus_num'], 3, 0, false);
    $zhongchou['prais_num'] = setNumberFormat($zhongchou['prais_num'], 3, 0, false);

    /* 浏览历史 */
    $history = zc_cate_history();
    $smarty->assign('history', $history);

    //项目状态 by wu
    if ($gmtime < $zhongchou['start_time']) {
        $zhongchou['zc_status'] = 0;
    } elseif ($gmtime > $zhongchou['end_time']) {
        $zhongchou['zc_status'] = 2;
    } else {
        $zhongchou['zc_status'] = 1;
    }

    //项目成功与否 by wu
    if ($zhongchou['amount'] > $zhongchou['join_money'] && $zhongchou['zc_status'] == 2) {
        $zhongchou['result'] = 1;
    } elseif ($zhongchou['amount'] < $zhongchou['join_money'] && $zhongchou['zc_status'] == 2) {
        $zhongchou['result'] = 2;
    } else {
        $zhongchou['result'] = 0;
    }

    //百分比
    $zhongchou['baifen_bi'] = round($zhongchou['join_money'] / $zhongchou['amount'], 2) * 100;

    $zhongchou['shenyu_time'] = ceil(($zhongchou['end_time'] - $gmtime) / 3600 / 24);
    $zhongchou['zw_end_time'] = date($GLOBALS['_LANG']['data'], $zhongchou['end_time']);
    $zhongchou['star_time'] = date('Y/m/d', $zhongchou['start_time']);
    $zhongchou['end_time'] = date('Y/m/d', $zhongchou['end_time']);

    $sql = " SELECT `id`,`pid`,`limit`,`backer_num`,`price`,`shipping_fee`,`content`,`img`,`return_time`,`backer_list`,(`limit`-`backer_num`) as shenyu_ren FROM " . $ecs->table('zc_goods') . " WHERE pid = '$cid' ";
    $goods_arr = $db->getAll($sql);

    $sql = " SELECT sum(backer_num) as zong_zhichi FROM " . $ecs->table('zc_goods') . " WHERE pid = '$cid' ";
    $zong_zhichi = $db->getOne($sql);
    if ($zong_zhichi == '') {
        $zong_zhichi = 0;
    }

    if (!empty($zhongchou['img'])) {
        $zhongchou['img'] = unserialize($zhongchou['img']);
    }

    $smarty->assign('zhongchou', $zhongchou);
    $smarty->assign('goods_arr', $goods_arr);
    $smarty->assign('zong_zhichi', $zong_zhichi);

    //补充验证关注点赞状态 by wu start [1526]
    if ($_SESSION['user_id'] > 0) {
        $sql = "SELECT rec_id FROM " . $GLOBALS['ecs']->table('zc_focus') . " WHERE pid = '$cid' AND user_id = '" . intval($_SESSION['user_id']) . "'";
        $focus_status = $GLOBALS['db']->getOne($sql);
    }
    $smarty->assign('user_id', $_SESSION['user_id']);
    $focus_status = empty($focus_status) ? 0 : 1;
    $smarty->assign('focus_status', $focus_status);
    $prais_status = empty($_SESSION['REMOTE_ADDR']) ? 0 : 1;
    $smarty->assign('prais_status', $prais_status);
    //补充验证关注点赞状态 by wu end
    //输出页面分享信息 by wu start [1526]
    $base_url = 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"];
    $page_url = $base_url . "?" . $_SERVER["QUERY_STRING"];
    $img_url = str_replace(basename($base_url), '', $base_url) . $zhongchou['title_img'];
    $smarty->assign('share_title', $zhongchou['title']);
    $smarty->assign('share_url', $page_url);
    $smarty->assign('share_img', $img_url);
    //输出页面分享信息 by wu end
    //分享二维码 by wu start [1526]
    include_once(dirname(__FILE__) . '/includes/phpqrcode/phpqrcode.php');
    $size = '200x200';
	$url = $ecs->url();
	$data = $url . 'mobile/index.php?m=crowd_funding&a=info&id=' . $cid;
    //$data = $page_url;
    $errorCorrectionLevel = 'M'; // 纠错级别：L、M、Q、H
    $matrixPointSize = 3; // 点的大小：1到10
    if (!file_exists("images/weixin_zc")) {
        mkdir("images/weixin_zc");
    }
    $filename = "images/weixin_zc/zc_" . $cid . ".png";
    QRcode::png($data, $filename, $errorCorrectionLevel, $matrixPointSize);
    $QR = imagecreatefrompng($filename);
    imagepng($QR, $filename);
    imagedestroy($QR);
    $smarty->assign('weixin_img_url', $filename);
    $smarty->assign('weixin_img_text', $zhongchou['title']);
    //分享二维码 by wu end
    //众筹支持者 by wu start [1526]
    $smarty->assign('backer_num', get_backer_num($cid));
    $smarty->assign('backer_list', get_backer_list($cid, 1));
    //众筹支持者 by wu end
    //众筹话题 by wu start [1526]
    $smarty->assign('topic_num', get_topic_num($cid));
    $smarty->assign('topic_list', get_topic_list($cid, 1));
    //众筹话题 by wu end

    /*  @author-bylu 项目进度 start  */
    $zc_evolve_list = $db->getAll("select * from " .$ecs->table('zc_progress'). " where pid = '$cid' order by add_time DESC ");
    foreach ($zc_evolve_list as $k => &$v) {
        $v['pro-day'] = floor(($gmtime - $v['add_time']) / 86400);
        $v['img'] = unserialize($v['img']);
        if (!empty($v['img'])) {
            foreach ($v['img'] as $k2 => $v2) {
                $v['img'][$k2] = './' . $v2;
            }
        }
    }

    $smarty->assign('zc_evolve_list_num', count($zc_evolve_list));
    $smarty->assign('zc_evolve_list', $zc_evolve_list);
    /*  @author-bylu 项目进度 end  */

    /*  @author-bylu 判断当前商家是否允许"在线客服" start  */
    //判断平台是否开启了IM在线客服
    if ($db->getOne("SELECT kf_im_switch FROM " . $ecs->table('seller_shopinfo') . "WHERE ru_id = 0")) {
        $shop_information['is_dsc'] = true;
    } else {
        $shop_information['is_dsc'] = false;
    }
    $smarty->assign('shop_information', $shop_information);
    /*  @author-bylu  end  */

    /* 记录浏览历史 ecmoban模板堂 --zhuo start 浏览列表插件 */
    if (!empty($_COOKIE['ECS']['zc_history'])) {
        $zc_history = explode(',', $_COOKIE['ECS']['zc_history']);

        array_unshift($zc_history, $zcgoods_id);
        $zc_history = array_unique($zc_history);

        while (count($zc_history) > 100000) {
            array_pop($zc_history);
        }

        setcookie('ECS[zc_history]', implode(',', $zc_history), gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    } else {
        setcookie('ECS[zc_history]', $zcgoods_id, gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }
    /* 记录浏览历史 ecmoban模板堂 --zhuo end 浏览列表插件 */

    $smarty->assign('zc_title', $zhongchou['title']);
    $smarty->display('crowdfunding.dwt');
}

//ajax获取支持者列表 by wu
if ($_REQUEST['act'] == 'get_backer_list') {
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $zcid = empty($_REQUEST['zcid']) ? 0 : intval($_REQUEST['zcid']);
    $page = empty($_REQUEST['page']) ? 1 : intval($_REQUEST['page']);
    $result['content'] = get_backer_list($zcid, $page);
    die(json_encode($result));
}

//ajax获取话题列表 by wu
if ($_REQUEST['act'] == 'get_topic_list') {
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $zcid = empty($_REQUEST['zcid']) ? 0 : intval($_REQUEST['zcid']);
    $page = empty($_REQUEST['page']) ? 1 : intval($_REQUEST['page']);
    $result['content'] = get_topic_list($zcid, $page);
    die(json_encode($result));
}

//ajax发布评论回复 by wu
if ($_REQUEST['act'] == 'post_topic') {
    $result = array('error' => 0, 'message' => '', 'content' => '');

    $topic_id = empty($_REQUEST['topic_id']) ? 0 : intval($_REQUEST['topic_id']);
    $type = empty($_REQUEST['type']) ? 0 : intval($_REQUEST['type']);
    $parent_id = empty($_REQUEST['parent_id']) ? 0 : intval($_REQUEST['parent_id']);
    $topic_content = empty($_REQUEST['topic_content']) ? '' : strip_tags($_REQUEST['topic_content']);
    if ($topic_id > 0) {
        $sql = " select pid from " . $GLOBALS['ecs']->table('zc_topic') . " where topic_id= " . $topic_id;
        $zcid = $GLOBALS['db']->getOne($sql);
    }

    if ($type != 2) {
        $parent_id = 0;
    }

    if ($_SESSION['user_id'] > 0) {
        if (!empty($topic_content)) {
            $sql = " insert into " . $GLOBALS['ecs']->table('zc_topic') .
                    " (topic_id, parent_topic_id, reply_topic_id, topic_status, topic_content, user_id, pid, add_time) " .
                    " VALUES " .
                    " (NULL, '" . $topic_id . "', '" . $parent_id . "', 1, '" . $topic_content . "', '" . $_SESSION['user_id'] . "', '" . $zcid . "', '" . $gmtime . "') ";
            if ($GLOBALS['db']->query($sql)) {
                $result['error'] = 1;
                $result['message'] = $_LANG['lang_crowd_art_succeed'];
            }
        }
    } else {
        $result['error'] = 9;
        $result['message'] = $_LANG['lang_crowd_login'];
    }

    die(json_encode($result));
}

//ajax发布话题 by wu
if ($_REQUEST['act'] == 'submit_topic') {
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $zcid = empty($_REQUEST['zcid']) ? 0 : intval($_REQUEST['zcid']);
    $topic_content = empty($_REQUEST['topic_content']) ? '' : strip_tags($_REQUEST['topic_content']);
    if ($_SESSION['user_id'] > 0) {
        if (!empty($topic_content)) {
            /* 判断当前会员是否重复发布话题 */
            $sql = "SELECT user_id,pid FROM " . $GLOBALS['ecs']->table('zc_topic') . " WHERE user_id=" . $_SESSION['user_id'] . " AND pid=" . $zcid;
            $res = $GLOBALS['db']->fetch_array($GLOBALS['db']->query($sql));
            if ($res === false) { 
                $sql = " insert into " . $GLOBALS['ecs']->table('zc_topic') .
                        " (topic_id, parent_topic_id, topic_status, topic_content, user_id, pid, add_time) " .
                        " VALUES " .
                        " (NULL, 0, 1, '" . $topic_content . "', '" . $_SESSION['user_id'] . "', '" . $zcid . "', '" . $gmtime . "') ";
                if ($GLOBALS['db']->query($sql)) {
					
                    $result['error'] = 1;
                    $result['message'] = $_LANG['lang_crowd_art_succeed'];
                }
            } else {
                $result['error'] = 8;
                $result['message'] = $_LANG['lang_crowd_art_succeed_repeat'];
            }
			$sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('zc_topic') . " WHERE pid=" . $zcid . " AND parent_topic_id = 0";
			$result['content']['zc_topic_num'] = $GLOBALS['db']->getOne($sql);
        }
    } else {
        $result['error'] = 9;
        $result['message'] = $_LANG['lang_crowd_login'];
    }
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'checkout') {
    require(ROOT_PATH . 'includes/lib_order.php');

    $smarty->assign('zc_title', $_LANG['zc_order_info']);
    /*
     * 检查用户是否已经登录
     * 如果用户已经登录了则检查是否有默认的收货地址
     * 如果没有登录则跳转到登录和注册页面
     */
    if (!$_SESSION['user_id'] || $_SESSION['user_id'] == 0) {
        /* 用户没有登录且没有选定匿名购物，转向到登录页面 */
        ecs_header("Location: user.php\n");
        exit;
    }
    //获取收货地址 by wu
    if (isset($_SESSION['address_id']) && !empty($_SESSION['address_id'])) {
        $sql = " SELECT ua.*, " .
                "concat(IFNULL(p.region_name, ''), " .
                "'  ', IFNULL(t.region_name, ''), " .
                "'  ', IFNULL(d.region_name, ''), " .
                " '  ', IFNULL(s.region_name, '')) AS region " .
                " FROM " . $GLOBALS['ecs']->table('user_address') . " AS ua " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS p ON ua.province = p.region_id " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS t ON ua.city = t.region_id " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS d ON ua.district = d.region_id " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS s ON ua.street = s.region_id " .
                " WHERE address_id = '" . $_SESSION['address_id'] . "' LIMIT 1";
        $consignee = $GLOBALS['db']->getRow($sql);
    } else {
        $consignee = get_consignee($_SESSION['user_id']);
    }

    $sql = "SELECT region_name FROM" . $ecs->table('region') . "WHERE region_id = '$consignee[province]'";
    $b['province'] = $db->getOne($sql);

    $sql = "SELECT region_name FROM" . $ecs->table('region') . "WHERE region_id = '$consignee[city]'";
    $b['city'] = $db->getOne($sql);

    $sql = "SELECT region_name FROM" . $ecs->table('region') . "WHERE region_id = '$consignee[district]'";
    $b['district'] = $db->getOne($sql);

    $smarty->assign('b', $b);

    $gid = intval($_REQUEST['gid']);

    $sql = " SELECT zg.*,zp.* FROM " . $GLOBALS['ecs']->table('zc_goods') . " AS zg " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('zc_project') . " AS zp on zp.id = zg.pid " .
            " WHERE zg.id = '$gid' ";
    $goods_arr = $db->getRow($sql);

    $shengyu = $goods_arr['limit'] - $goods_arr['backer_num'];
    if ($shengyu == 0) {
        show_message($GLOBALS['_LANG']['Sold_out'], $_LANG['back_up_page'], 'javascript:history.back(-1)');
    }

    $sql = "SELECT title FROM" . $ecs->table('zc_project') . "WHERE id = '$goods_arr[pid]'";
    $g_title = $db->getOne($sql);

    //$consignee = get_consignee($_SESSION['user_id']);

    $user_address = get_order_user_address_list($_SESSION['user_id']);

    if ($direct_shopping != 1 && !empty($_SESSION['user_id'])) {
        $_SESSION['browse_trace'] = "flow.php";
    } else {
        $_SESSION['browse_trace'] = "flow.php?step=checkout";
    }

    if (!$user_address && $consignee) {
        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['region'] = $consignee['province_name'] . "&nbsp;" . $consignee['city_name'] . "&nbsp;" . $consignee['district_name'];

        $user_address = array($consignee);
    }

    $smarty->assign('user_address', $user_address);

	$inv_content_list = explode("\n", str_replace("\r", '', $_CFG['invoice_content']));
	$smarty->assign('inv_content', $inv_content_list[0]);

    $smarty->assign('goods_arr', $goods_arr);
    $smarty->assign('g_title', $g_title);
    $smarty->assign('consignee', $consignee);
    $smarty->display('crowdfunding.dwt');
}

if ($_REQUEST['act'] == 'consignee') {
    /* ------------------------------------------------------ */
    //-- 收货人信息
    /* ------------------------------------------------------ */
    require_once(ROOT_PATH . 'languages/' . $_CFG['lang'] . '/user.php');
    require_once(ROOT_PATH . 'languages/' . $_CFG['lang'] . '/shopping_flow.php');
    include_once('includes/lib_transaction.php');
    include_once('includes/lib_order.php');
    $smarty->assign('lang', $_LANG);

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        /* 收货人信息填写界面 */
        if (isset($_REQUEST['direct_shopping'])) {
            $_SESSION['direct_shopping'] = 1;
        }

        /* 取得国家列表、商店所在国家、商店所在国家的省列表 */
        $smarty->assign('country_list', get_regions());
        $smarty->assign('shop_country', $_CFG['shop_country']);
        $smarty->assign('shop_province_list', get_regions(1, $_CFG['shop_country']));

        /* 获得用户所有的收货人信息 */
        if ($_SESSION['user_id'] > 0) {
            $consignee_list = get_consignee_list($_SESSION['user_id']);

            if (count($consignee_list) < 5) {
                /* 如果用户收货人信息的总数小于 5 则增加一个新的收货人信息 */
                $consignee_list[] = array('country' => $_CFG['shop_country'], 'email' => isset($_SESSION['email']) ? $_SESSION['email'] : '');
            }
        } else {
            if (isset($_SESSION['flow_consignee'])) {
                $consignee_list = array($_SESSION['flow_consignee']);
            } else {
                $consignee_list[] = array('country' => $_CFG['shop_country']);
            }
        }
        $smarty->assign('name_of_region', array($_CFG['name_of_region_1'], $_CFG['name_of_region_2'], $_CFG['name_of_region_3'], $_CFG['name_of_region_4']));
        $smarty->assign('consignee_list', $consignee_list);

        /* 取得每个收货地址的省市区列表 */
        $province_list = array();
        $city_list = array();
        $district_list = array();
        foreach ($consignee_list as $region_id => $consignee) {
            $consignee['country'] = isset($consignee['country']) ? intval($consignee['country']) : 0;
            $consignee['province'] = isset($consignee['province']) ? intval($consignee['province']) : 0;
            $consignee['city'] = isset($consignee['city']) ? intval($consignee['city']) : 0;

            $province_list[$region_id] = get_regions(1, $consignee['country']);
            $city_list[$region_id] = get_regions(2, $consignee['province']);
            $district_list[$region_id] = get_regions(3, $consignee['city']);
        }
        $smarty->assign('province_list', $province_list);
        $smarty->assign('city_list', $city_list);
        $smarty->assign('district_list', $district_list);

        $smarty->assign('page_title', $_LANG['lang_crowd_page_title']); //页面标题 by wu
        $smarty->assign('step', 'consignee'); //页面步骤 by wu

        /* 返回收货人页面代码 */
        $smarty->display('crowdfunding.dwt');
    } else {
        /* 保存收货人信息 */
        $consignee = array(
            'address_id' => empty($_POST['address_id']) ? 0 : intval($_POST['address_id']),
            'consignee' => empty($_POST['consignee']) ? '' : trim($_POST['consignee']),
            'country' => empty($_POST['country']) ? '' : $_POST['country'],
            'province' => empty($_POST['province']) ? '' : $_POST['province'],
            'city' => empty($_POST['city']) ? '' : $_POST['city'],
            'district' => empty($_POST['district']) ? '' : $_POST['district'],
            'email' => empty($_POST['email']) ? '' : $_POST['email'],
            'address' => empty($_POST['address']) ? '' : $_POST['address'],
            'zipcode' => empty($_POST['zipcode']) ? '' : make_semiangle(trim($_POST['zipcode'])),
            'tel' => empty($_POST['tel']) ? '' : make_semiangle(trim($_POST['tel'])),
            'mobile' => empty($_POST['mobile']) ? '' : make_semiangle(trim($_POST['mobile'])),
            'sign_building' => empty($_POST['sign_building']) ? '' : $_POST['sign_building'],
            'best_time' => empty($_POST['best_time']) ? '' : $_POST['best_time'],
        );

        if ($_SESSION['user_id'] > 0) {
            include_once(ROOT_PATH . 'includes/lib_transaction.php');

            /* 如果用户已经登录，则保存收货人信息 */
            $consignee['user_id'] = $_SESSION['user_id'];

            save_consignee($consignee, true);
        }

        /* 保存到session */
        $_SESSION['flow_consignee'] = stripslashes_deep($consignee);
        
        $gid = intval($_POST['gid']);
        Header("Location:crowdfunding.php?act=checkout&gid=" . $gid);
        exit;
    }
}

if ($_REQUEST['act'] == 'drop_consignee') {
    /* ------------------------------------------------------ */
    //-- 删除收货人信息
    /* ------------------------------------------------------ */
    include_once('includes/lib_transaction.php');

    $consignee_id = intval($_GET['id']);
    $gid = intval($_REQUEST['']);

    if (drop_consignee($consignee_id)) {
        ecs_header("Location: crowdfunding.php?act=consignee&gid=" . $gid . "\n");
        exit;
    } else {
        show_message($_LANG['not_fount_consignee']);
    }
}

//统计关注点赞 by wu
if ($_REQUEST['act'] == 'statistical') {
    $result = array('error' => 0, 'message' => '', 'content' => '');
    $zcid = empty($_REQUEST['zcid']) ? '0' : intval($_REQUEST['zcid']);
    $type = empty($_REQUEST['type']) ? '0' : intval($_REQUEST['type']);
    if ($zcid > 0 && $type > 0) {
        //关注
        if ($type == 1) {
            //只有登陆用户才能关注
            if (empty($_SESSION['user_id'])) {
                $result['error'] = 9;
                $result['message'] = $_LANG['lang_crowd_login_focus'];
            } else {
                $sql = " select rec_id from " . $GLOBALS['ecs']->table('zc_focus') . " where pid= " . $zcid . " and user_id= " . $_SESSION['user_id'];
                $focus_status = $GLOBALS['db']->getOne($sql);
                if (empty($focus_status)) {
                    $sql = " insert into " . $GLOBALS['ecs']->table('zc_focus') . " (rec_id,user_id,pid,add_time) " .
                            " VALUES " .
                            " (NULL, '" . $_SESSION['user_id'] . "', '" . $zcid . "', '" . $gmtime . "') ";
                    if ($GLOBALS['db']->query($sql)) {
                        $sql = " update " . $GLOBALS['ecs']->table('zc_project') . " set focus_num=focus_num+1 where id=" . $zcid;
                        if ($GLOBALS['db']->query($sql)) {
                            $result['error'] = 2;
                            $result['message'] = $_LANG['lang_crowd_focus_succeed'];
                        }
                    }
                } else {
                    $result['error'] = 3;
                    $result['message'] = $_LANG['lang_crowd_focus_repeat'];
                }
            }
        }
        //点赞
        if ($type == 2) {
            if (empty($_SESSION['REMOTE_ADDR'])) {
                $sql = " update " . $GLOBALS['ecs']->table('zc_project') . " set prais_num=prais_num+1 where id=" . $zcid;
                if ($GLOBALS['db']->query($sql)) {
                    $result['error'] = 4;
                    $result['message'] = $_LANG['lang_crowd_like'];
                    $_SESSION['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
                }
            } else {
                $result['error'] = 5;
                $result['message'] = $_LANG['lang_crowd_like_repeat'];
            }
        }
    }
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'confirmAddress') {
    include_once('includes/cls_json.php');
    $json = new JSON;
    $consignee_id = intval($_POST['consignee_id']);

    $gid = intval($_REQUEST['gid']);
    $sql = "SELECT * FROM" . $ecs->table('zc_goods') . "WHERE id = '$gid'";
    $goods_arr = $db->getRow($sql);

    $sql = "SELECT title FROM" . $ecs->table('zc_project') . "WHERE id = '$goods_arr[pid]'";
    $g_title = $db->getOne($sql);

    $confirm_address = get_order_confirm_address($consignee_id);
    if (!$confirm_address && $consignee) {
        $consignee['province_name'] = get_goods_region_name($consignee['province']);
        $consignee['city_name'] = get_goods_region_name($consignee['city']);
        $consignee['district_name'] = get_goods_region_name($consignee['district']);
        $consignee['region'] = $consignee['province_name'] . "&nbsp;" . $consignee['city_name'] . "&nbsp;" . $consignee['district_name'];

        $confirm_address = array($consignee);
    }
    $confirm_address['mobile'] = $confirm_address['mobile'] ? $confirm_address['mobile'] : $confirm_address['tel'];
    $content = '';
    $content = "<span>" . $confirm_address['consignee'] . "</span>" .
            "<span>" . $confirm_address['address'] . "</span>" .
            "<span>" . $confirm_address['mobile'] . "</span>" .
            "<span><a class='f_blue repeat' href='javascript:void(0);' id='editRepeat'>修改地址</a></span>";
    $common = '';
    $common = " <div class='common_button' id='common_button' > " .
            "<form action='crowdfunding.php?act=done' method='post'> " .
            "<input type='hidden' name='country'  value=" . $confirm_address['country'] . ">" .
            "<input type='hidden' name='province' value=" . $confirm_address['province'] . ">" .
            "<input type='hidden' name='city' value=" . $confirm_address['city'] . ">" .
            "<input type='hidden' name='district' value=" . $confirm_address['district'] . ">" .
            "<input type='hidden' name='consignee' value=" . $confirm_address['consignee'] . ">" .
            "<input type='hidden' name='address' value=" . $confirm_address['address'] . ">" .
            "<input type='hidden' name='tel' value=" . $confirm_address['tel'] . ">" .
            "<input type='hidden' name='mobile' value=" . $confirm_address['mobile'] . ">" .
            "<input type='hidden' name='email' value=" . $confirm_address['email'] . ">" .
            "<input type='hidden' name='best_time' value=" . $confirm_address['best_time'] . ">" .
            "<input type='hidden' name='sign_building' value=" . $confirm_address['sign_building'] . ">" .
			"<input type='hidden' id='inv_payee' name='inv_payee' value=''>" .
            "<input type='hidden' id='liuyan' name='postscript' value=''>" .
            "<input type='hidden' name='goods_amount' value=" . $goods_arr['price'] . ">" .
            "<input type='hidden' name='shipping_fee' value=" . $goods_arr['yunfei'] . ">" .
            "<input type='hidden' name='order_amount' value=" . $goods_arr['price'] . ">" .
            "<input type='hidden' name='huibao' value=" . $goods_arr['content'] . ">" .
            "<input type='hidden' name='g_title' value=" . $g_title . ">" .
            "<input type='hidden' name='xm_id' value=" . $goods_arr['goods_id'] . ">" .
            "<input type='hidden' name='gid' value=" . $gid . ">" .
            "<input type='submit' id='btn_sub' value='" . $_LANG['lang_crowd_next_step'] . "'>" .
            "</form>" .
            "</div>";
    $result = array('error' => 0, 'content' => $content, 'common' => $common);
    $_SESSION['address_id'] = $consignee_id;
    die($json->encode($result));
};

if ($_REQUEST['act'] == 'add_Consignee') {
    include('includes/cls_json.php');
    include('includes/lib_order.php');

    $json = new JSON;
    $res = array('message' => '', 'result' => '');
    $address_id = isset($_REQUEST['address_id']) ? intval($_REQUEST['address_id']) : 0;

    if ($address_id == 0) {
        $consignee['country'] = 1;
        $consignee['province'] = 0;
        $consignee['city'] = 0;
    }

    get_goods_flow_type($_SESSION['cart_value']);

    $consignee = get_update_flow_Consignee($address_id);
    $smarty->assign('consignee', $consignee);

    /* 取得国家列表、商店所在国家、商店所在国家的省列表 */
    $smarty->assign('country_list', get_regions());

    $smarty->assign('please_select', $_LANG['please_select']);

    $province_list = get_regions_log(1, $consignee['country']);
    $city_list = get_regions_log(2, $consignee['province']);
    $district_list = get_regions_log(3, $consignee['city']);
    $street_list = get_regions_log(4, $consignee['district']);

    $smarty->assign('province_list', $province_list);
    $smarty->assign('city_list', $city_list);
    $smarty->assign('district_list', $district_list);
    $smarty->assign('street_list', $street_list);

    $smarty->assign('gid', intval($_REQUEST['gid']));


    if ($_SESSION['user_id'] <= 0) {
        $result['error'] = 2;
        $result['message'] = $_LANG['lang_crowd_not_login'];
    } else {
        $result['error'] = 0;
        $result['content'] = $smarty->fetch("library/consignee_zc.lbi");
    }
    die($json->encode($result));
}

if ($_REQUEST['act'] == 'insert_Consignee') {
    include('includes/cls_json.php');
    include('includes/lib_order.php');
    $json = new JSON;
    $result = array('message' => '', 'result' => '', 'error' => 0);

    $_REQUEST['csg'] = isset($_REQUEST['csg']) ? json_str_iconv($_REQUEST['csg']) : '';
    $csg = $json->decode($_REQUEST['csg']);

    $consignee = array(
        'address_id' => empty($csg->address_id) ? 0 : intval($csg->address_id),
        'consignee' => empty($csg->consignee) ? '' : compile_str(trim($csg->consignee)),
        'country' => empty($csg->country) ? 0 : intval($csg->country),
        'province' => empty($csg->province) ? 0 : intval($csg->province),
        'city' => empty($csg->city) ? 0 : intval($csg->city),
        'district' => empty($csg->district) ? 0 : intval($csg->district),
        'address' => empty($csg->address) ? '' : compile_str($csg->address),
        'mobile' => empty($csg->mobile) ? '' : compile_str(make_semiangle(trim($csg->mobile))),
    );

    if ($result['error'] == 0) {

        if ($_SESSION['user_id'] > 0) {
            include_once(ROOT_PATH . 'includes/lib_transaction.php');

            if ($consignee['address_id'] > 0) {
                $addressId = " and address_id <> '" . $consignee['address_id'] . "' ";
            }

            $sql = "SELECT COUNT(*) FROM " . $ecs->table('user_address') . " WHERE consignee = '" . $consignee['consignee'] . "'" .
                    " AND country = '" . $consignee['country'] . "'" .
                    " AND province = '" . $consignee['province'] . "'" .
                    " AND city = '" . $consignee['city'] . "'" .
                    " AND district = '" . $consignee['district'] . "'" .
                    " AND user_id = '" . $_SESSION['user_id'] . "'" . $addressId;
            $row = $db->getOne($sql);

            if ($row > 0) {
                $result['error'] = 4;
                $result['message'] = $GLOBALS['_LANG']['shiping_in'];
            } else {
                $result['error'] = 0;

                /* 如果用户已经登录，则保存收货人信息 */
                $consignee['user_id'] = $_SESSION['user_id'];
                $_SESSION['address_id'] = $consignee['address_id'];
                $saveConsignee = save_consignee($consignee, true);

                $sql = "select address_id from " . $GLOBALS['ecs']->table('users') . " where user_id = '" . $_SESSION['user_id'] . "'";
                $user_address_id = $GLOBALS['db']->getOne($sql);

                if ($user_address_id > 0) {
                    $consignee['address_id'] = $user_address_id;
                }

                $sql = "select count(*) from " . $GLOBALS['ecs']->table('user_address') . " where user_id = '" . $_SESSION['user_id'] . "'";
                $count = $GLOBALS['db']->getOne($sql);

                if ($consignee['address_id'] > 0) {

                    $sql = "UPDATE " . $GLOBALS['ecs']->table('users') . " SET address_id = '" . $consignee['address_id'] . "' " . " WHERE user_id = '" . $consignee['user_id'] . "'";
                    $GLOBALS['db']->query($sql);
                    $_SESSION['flow_consignee'] = $consignee;

                    $result['message'] = $GLOBALS['_LANG']['edit_success'];
                } else {
                    $result['message'] = $GLOBALS['_LANG']['add_success'];
                }
            }

            $user_address = get_order_user_address_list($_SESSION['user_id']);
            $smarty->assign('user_address', $user_address);
            $consignee['province_name'] = get_goods_region_name($consignee['province']);
            $consignee['city_name'] = get_goods_region_name($consignee['city']);
            $consignee['district_name'] = get_goods_region_name($consignee['district']);
            $consignee['consignee_address'] = $consignee['province_name'] . $consignee['city_name'] . $consignee['district_name'] . $consignee['address'];

            $smarty->assign('consignee', $consignee);

            $result['content'] = $smarty->fetch("library/consignee_zcflow.lbi");

            $region_id = get_province_id_warehouse($consignee['province']);
            $area_info = get_area_info($consignee['province']);

            $smarty->assign('warehouse_id', $region_id);
            $smarty->assign('area_id', $area_info['region_id']);
            $sql = " SELECT count(*) FROM " . $ecs->table('user_address') . " WHERE user_id = '" . $_SESSION['user_id'] . "' ";
            $once = $db->getOne($sql);
            if ($once < 2) {
                $result['once'] = true;
                $result['gid'] = intval($_REQUEST['gid']);
            }
        } else {
            $result['error'] = 2;
            $result['message'] = $_LANG['lang_crowd_not_login'];
        }
    }
    die($json->encode($result));
}

if ($_REQUEST['act'] == 'delete_Consignee') {
    include('includes/cls_json.php');
    include('includes/lib_order.php');

    $json = new JSON;
    $res = array('message' => '', 'result' => '', 'qty' => 1);

    $gid = intval($_REQUEST['gid']);
    $result['error'] = 0;

    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $address_id = isset($_REQUEST['address_id']) ? intval($_REQUEST['address_id']) : 0;
    $sql = "delete from " . $ecs->table('user_address') . " where address_id = '$address_id'";
    $db->query($sql);

    $consignee = $_SESSION['flow_consignee'];
    $smarty->assign('consignee', $consignee);

    if ($consignee) {
        setcookie('province', $consignee['province'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('city', $consignee['city'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('district', $consignee['district'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

        $flow_warehouse = get_warehouse_goods_region($consignee['province']);
        setcookie('area_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        setcookie('flow_region', $flow_warehouse['region_id'], gmtime() + 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    }

    $region_id = get_province_id_warehouse($consignee['province']);
    $area_info = get_area_info($consignee['province']);

    $smarty->assign('warehouse_id', $region_id);
    $smarty->assign('area_id', $area_info['region_id']);

    $user_address = get_order_user_address_list($_SESSION['user_id']);
    $smarty->assign('user_address', $user_address);

    if (!$user_address) {
        $consignee = array(
            'province' => 0,
            'city' => 0
        );
        // 取得国家列表、商店所在国家、商店所在国家的省列表
        $smarty->assign('country_list', get_regions());
        $smarty->assign('please_select', $GLOBALS['_LANG']['please_select']);

        $province_list = get_regions_log(1, 1);
        $city_list = get_regions_log(2, $consignee['province']);
        $district_list = get_regions_log(3, $consignee['city']);
        $street_list = get_regions_log(4, $consignee['district']);

        $smarty->assign('province_list', $province_list);
        $smarty->assign('city_list', $city_list);
        $smarty->assign('district_list', $district_list);
        $smarty->assign('street_list', $street_list);
        $smarty->assign('consignee', $consignee);

        $result['error'] = 2;
        $result['gid'] = $gid;
    } else {
        $result['content'] = $smarty->fetch("library/consignee_zcflow.lbi");
    }
    die($json->encode($result));
}

if ($_REQUEST['act'] == 'done') {
    include_once('includes/lib_clips.php');
    include_once('includes/lib_payment.php');
    include_once('includes/lib_order.php');

    $smarty->assign('zc_title', $_LANG['zc_order_submit']);

    //判断是否有收获地址 by wu
    if (empty($_POST['consignee'])) {
        show_message($_LANG['lang_crowd_not_address'], $_LANG['back_up_page'], 'javascript:history.back(-1)');
    }

    //判断是否重复提交订单 by wu start
    $sql = " SELECT COUNT(order_id) FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE user_id = '$_SESSION[user_id]' AND is_zc_order = 1 AND zc_goods_id = '$_POST[gid]' AND pay_status = 0 ";
    $zc_order_num = $GLOBALS['db']->getOne($sql);
    if ($zc_order_num > 0) {
        show_message($_LANG['lang_crowd_not_pay'], $_LANG['back_up_page'], 'user.php?act=crowdfunding');
    }
    //判断是否重复提交订单 by wu end

    $sql = "SELECT * FROM" . $ecs->table('shipping') . "WHERE enabled = 1";
    $arr_shipping = $db->getAll($sql);

    $sql = "SELECT * FROM" . $ecs->table('payment') . "WHERE enabled = 1";
    $arr_payment = $db->getAll($sql);

    $order['country'] = !empty($_POST['country']) ? intval($_POST['country']) : 1;
    $order['province'] = !empty($_POST['province']) ? intval($_POST['province']) : 0;
    $order['city'] = !empty($_POST['city']) ? intval($_POST['city']) : 0;
    $order['district'] = !empty($_POST['district']) ? intval($_POST['district']) : 0;
    $order['consignee'] = !empty($_POST['consignee']) ? trim($_POST['consignee']) : '';
    $order['address'] = !empty($_POST['address']) ? trim($_POST['address']) : '';
    $order['tel'] = !empty($_POST['tel']) ? trim($_POST['tel']) : 0;
    $order['mobile'] = !empty($_POST['mobile']) ? trim($_POST['mobile']) : 0;
    $order['email'] = !empty($_POST['email']) ? trim($_POST['email']) : '';
    $order['best_time'] = !empty($_POST['best_time']) ? trim($_POST['best_time']) : '';
    $order['sign_building'] = !empty($_POST['sign_building']) ? trim($_POST['sign_building']) : '';
    $order['zipcode'] = '';
	$order['inv_payee'] = !empty($_POST['inv_payee']) ? trim($_POST['inv_payee']) : '';
    $order['postscript'] = !empty($_POST['postscript']) ? trim($_POST['postscript']) : '';
    $order['shipping_id'] = $arr_shipping[0]['shipping_id'];
    $order['shipping_name'] = $arr_shipping[0]['shipping_name'];
    $order['pay_id'] = $zhifu['pay_id'];
    $order['pay_name'] = $zhifu['pay_name'];
    $order['how_oos'] = '';
    $order['how_surplus'] = '';
    $order['pack_name'] = '';
    $order['card_name'] = '';
    $order['card_message'] = '';
    $order['inv_content'] = '';
    $order['goods_amount'] = !empty($_POST['goods_amount']) ? trim($_POST['goods_amount']) : 0;
    $order['shipping_fee'] = !empty($_POST['shipping_fee']) ? trim($_POST['shipping_fee']) : 0;
    $order['insure_fee'] = 0;
    $order['pay_fee'] = 0;
    $order['pack_fee'] = 0;
    $order['card_fee'] = 0;
    $order['money_paid'] = 0;
    $order['surplus'] = 0;
    $order['integral'] = 0;
    $order['integral_money'] = 0;
    $order['bonus'] = 0;
    $order['order_amount'] = $order['goods_amount'] + $order['shipping_fee'];
    $order['from_ad'] = 0;
    $order['referer'] = $_LANG['self_site'];
    $order['add_time'] = gmtime();
    $order['confirm_time'] = 0;
    $order['pay_time'] = 0;
    $order['shipping_time'] = 0;
    $order['pack_id'] = 0;
    $order['card_id'] = 0;
    $order['bonus_id'] = 0;
    $order['invoice_no'] = '';
    $order['extension_code'] = '';
    $order['extension_id'] = 0;
    $order['to_buyer'] = '';
    $order['pay_note'] = '';
    $order['agency_id'] = 0;
    $order['inv_type'] = '';
    $order['tax'] = 0;
    $order['is_separate'] = 0;
    $order['parent_id'] = 0;
    $order['discount'] = 0;
    $order['is_zc_order'] = 1;
    $order['zc_goods_id'] = $_POST['gid'];
    $order['user_id'] = $_SESSION['user_id'];
    $order['order_status'] = 0;
    $order['shipping_status'] = 0;
    $order['pay_status'] = 0;
	
	$order['inv_payee'] = isset($_POST['inv_payee']) ? trim($_POST['inv_payee']) : '';
	$order['tax_id'] = isset($_POST['tax_id']) ? trim($_POST['tax_id']) : '';//纳税人识别号
	$order['inv_content'] = isset($_POST['inv_content']) ? trim($_POST['inv_content']) : '';
	$order['inv_content'] = isset($_POST['inv_content']) ? trim($_POST['inv_content']) : '';
	$order['invoice_type'] = isset($_POST['invoice_type']) ? intval($_POST['invoice_type']) : 0;
	
    $order['order_sn'] = get_order_sn(); //获取新订单号

    $db->autoExecute($ecs->table('order_info'), $order, 'INSERT');
    $res_id = $db->insert_id();

    /* 插入支付日志 */
    $order['log_id'] = insert_pay_log($res_id, $order['order_amount'], PAY_ORDER);

    /* 在线支付代码 */
    $payment_list = available_payment_list(0, $cod_fee);
    //取出所有在线支付方法(含按钮);
    foreach ($payment_list as $k => $v) {
        if ($v['is_online'] == 1 || $v['pay_code'] == 'balance') {

            $payment_file = 'includes/modules/payment/' . $v['pay_code'] . '.php';
            if (file_exists($payment_file)) {
                include_once($payment_file);
                $pay_obj = new $v['pay_code'];
                $payment = payment_info($v['pay_id']);
                $pay_online_button[$v['pay_code']] = <<<HTML
<div style='display:inline-block;' >
{$pay_obj->get_code($order, unserialize_config($v['pay_config']))}
</div>
HTML;
                //判断已安装的支付方法中是否有"支付宝网银直连"方法;
                if ($v['pay_code'] == 'alipay_bank') {
                    //重新赋值支付宝网银直连的支付按钮,将支付按钮列表中的删除;
                    $smarty->assign('is_alipay_bank', $pay_online_button['alipay_bank']);
                    unset($pay_online_button['alipay_bank']);
                }
                if ($v['pay_code'] == 'balance') {
                    $pay_online_button['balance'] = <<<HTML
		<a href="flow.php?step=done&act=balance&order_sn={$order['order_sn']}" id="balance" style="float: left;" order_sn="{$order['order_sn']}" flag="balance" >{$GLOBALS['_LANG']['balance_pay']}</a>
HTML;
                }
                //判断当前用户是否拥有白条支付授权(有的话才显示"白条支付按钮");
                if (!empty($user_baitao_amount)) {
                    $smarty->assign('is_chunsejinrong', true);
                    if ($v['pay_code'] == 'chunsejinrong') {
                        $pay_online_button['chunsejinrong'] = <<<HTML
				<a href="flow.php?step=done&act=chunsejinrong&order_sn={$order['order_sn']}" id="chunsejinrong" style="float: left;" order_sn="{$order['order_sn']}" flag="chunsejinrong" >{$GLOBALS['_LANG']['ious_pay']}</a>
HTML;
                    }
                }
            }
        }
    }
    $smarty->assign('pay_online_button', $pay_online_button); //在线支付按钮数组;
    $smarty->assign('is_onlinepay', true); //在线支付标记 by lu;	
    /* 在线支付代码 */

    if (empty($order['address'])) {
        $sql = "SELECT region_name FROM" . $ecs->table('region') . "WHERE region_id = '$order[province]'";
        $b['province'] = $db->getOne($sql);

        $sql = "SELECT region_name FROM" . $ecs->table('region') . "WHERE region_id = '$order[city]'";
        $b['city'] = $db->getOne($sql);

        $sql = "SELECT region_name FROM" . $ecs->table('region') . "WHERE region_id = '$order[district]'";
        $b['district'] = $db->getOne($sql);

        $smarty->assign('b', $b);
    }

    $smarty->assign('xm_id', $_POST['xm_id']);
    $smarty->assign('g_title', $_POST['g_title']);
    $smarty->assign('huibao', $_POST['huibao']);
    $smarty->assign('order', $order);

    $smarty->display('crowdfunding.dwt');
}

if ($_REQUEST['act'] == 'xm') {
    $smarty->assign('zc_title', $_LANG['zc_search']);
    $sql = "SELECT * FROM" . $ecs->table('zc_category') . "WHERE parent_id = 0";
    $cate_one = $db->getAll($sql);

    $cate_two = array();
    foreach ($cate_one as $c_val) {
        $sql = "SELECT * FROM" . $ecs->table('zc_category') . "WHERE parent_id = '$c_val[cat_id]'";
        $cate_two[$c_val['cat_id']] = $db->getAll($sql);
    }
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . "ORDER BY id desc";
    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 12) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('cate_one', $cate_one);
    $smarty->assign('cate_two', $cate_two);
    $smarty->assign('zc_arr', $new_zc_arr);

    $smarty->display('crowdfunding.dwt');
}

if ($_REQUEST['act'] == 'search_quanbu') {
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "WHERE title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " $where_wenzi ORDER BY id desc";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 12) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'search_cate') {
    $code = intval($_POST['code']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    $cate_two = array();
    $str_id = $code . ',';
    $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = '$code'";
    $cate_two = $db->getAll($sql);
    foreach ($cate_two as $c_val) {
        $str_id .= $c_val['cat_id'] . ',';
        $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = " . $c_val['cat_id'] . " ";
        $cate_three = $db->getAll($sql);
        foreach ($cate_three as $ct_val) {
            $str_id .= $ct_val['cat_id'] . ',';
        }
    }
    $str_id = rtrim($str_id, ',');
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id in($str_id) $where_wenzi ORDER BY id desc";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 12) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'search_cate_child') {
    $code = intval($_POST['code']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id='$code' $where_wenzi ORDER BY id desc";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 12) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'search_paixu_tid') {
    $tid = intval($_POST['id']);
    $sig = addslashes($_POST['sig']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY join_money desc,id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY id desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY join_money desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY join_num desc';
            break;
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id='$tid' $where_wenzi $where_tj ";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 12) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'search_paixu_pid_zero') {
    $sig = $_POST['sig'];
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "WHERE title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY join_money desc,id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY id desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY join_money desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY join_num desc';
            break;
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " $where_wenzi $where_tj ";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 12) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'search_paixu_pid') {
    $pid = intval($_POST['id']);
    $sig = $_POST['sig'];
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY join_money desc,id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY id desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY join_money desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY join_num desc';
            break;
    }

    $cate_two = array();
    $str_id = $code . ',';
    $sql = "SELECT id FROM" . $ecs->table('zc_category') . "WHERE parent_id = '$pid'";
    $cate_two = $db->getAll($sql);
    foreach ($cate_two as $c_val) {
        $str_id .= $c_val['cat_id'] . ',';
        $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = " . $c_val['cat_id'] . " ";
        $cate_three = $db->getAll($sql);
        foreach ($cate_three as $ct_val) {
            $str_id .= $ct_val['cat_id'] . ',';
        }
    }
    $str_id = rtrim($str_id, ',');

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . "WHERE cat_id in($str_id) $where_wenzi $where_tj ";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    foreach ($zc_arr as $value) {
        if ($i >= 12) {
            break;
        }
        $new_zc_arr[] = $value;
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'page_tid') {
    $tid = intval($_POST['id']);
    $sig = addslashes($_POST['sig']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';
    $page = intval($_POST['page']);

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY join_money desc,id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY id desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY join_money desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY join_num desc';
            break;
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id='$tid' $where_wenzi $where_tj ";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    $start_i = ($page - 1) * 12;
    $end_i = $start_i + 12;
    foreach ($zc_arr as $value) {
        if ($i >= $end_i) {
            break;
        }

        if ($i >= $start_i) {
            $new_zc_arr[] = $value;
        }
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page', $page);
    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'page_pid_zero') {
    $pid = intval($_POST['id']);
    $sig = addslashes($_POST['sig']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';
    $page = intval($_POST['page']);


    if (!empty($wenzi)) {
        $where_wenzi = "WHERE title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY join_money desc,id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY id desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY join_money desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY join_num desc';
            break;
    }

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " $where_wenzi $where_tj ";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    $start_i = ($page - 1) * 12;
    $end_i = $start_i + 12;
    foreach ($zc_arr as $value) {
        if ($i >= $end_i) {
            break;
        }

        if ($i >= $start_i) {
            $new_zc_arr[] = $value;
        }
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page', $page);
    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'page_pid') {
    $pid = intval($_POST['id']);
    $sig = addslashes($_POST['sig']);
    $wenzi = isset($_POST['wenzi']) && !empty($_POST['wenzi']) ? addslashes($_POST['wenzi']) : '';
    $page = intval($_POST['page']);

    if (!empty($wenzi)) {
        $where_wenzi = "AND title like '%$wenzi%'";
    } else {
        $where_wenzi = "";
    }

    switch ($sig) {
        case 'zhtj':
            $where_tj = 'ORDER BY join_money desc,id desc';
            break;
        case 'zxsx':
            $where_tj = 'ORDER BY id desc';
            break;
        case 'jezg':
            $where_tj = 'ORDER BY join_money desc';
            break;
        case 'zczd':
            $where_tj = 'ORDER BY join_num desc';
            break;
    }

    $cate_two = array();
    $str_id = $code . ',';
    $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = '$pid'";
    $cate_two = $db->getAll($sql);
    foreach ($cate_two as $c_val) {
        $str_id .= $c_val['cat_id'] . ',';
        $sql = "SELECT cat_id FROM" . $ecs->table('zc_category') . "WHERE parent_id = " . $c_val['cat_id'] . " ";
        $cate_three = $db->getAll($sql);
        foreach ($cate_three as $ct_val) {
            $str_id .= $ct_val['cat_id'] . ',';
        }
    }
    $str_id = rtrim($str_id, ',');

    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM" . $ecs->table('zc_project') . " WHERE cat_id in($str_id) $where_wenzi $where_tj ";

    $zc_arr = $db->getAll($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 4) * 100;
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        if ($z_val['start_time'] > $gmtime) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_preheat'];
        } else if ($gmtime >= $z_val['start_time'] && $gmtime <= $z_val['end_time']) {
            $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_of'];
        } else if ($gmtime > $z_val['end_time']) {
            if ($z_val['join_money'] >= $z_val['amount']) {
                $zc_arr[$k]['zc_status'] = $_LANG['lang_crowd_succeed'];
                $zc_arr[$k]['shenyu_time'] = 0;
            } else {
                //删除不成功众筹
                unset($zc_arr[$k]);
            }
        }
    }

    //默认是2个，如果是3就加3
    $gengduo = count($zc_arr);
    $new_zc_arr = array();
    $i = 0;
    $start_i = ($page - 1) * 12;
    $end_i = $start_i + 12;
    foreach ($zc_arr as $value) {
        if ($i >= $end_i) {
            break;
        }

        if ($i >= $start_i) {
            $new_zc_arr[] = $value;
        }
        $i++;
    }

    $zong_page = ceil($gengduo / 12);
    $page_arr = array();
    for ($i = 0; $i < $zong_page; $i++) {
        $page_arr[] = $i + 1;
    }

    $smarty->assign('page', $page);
    $smarty->assign('page_arr', $page_arr);
    $smarty->assign('zc_arr', $new_zc_arr);
    $result = $smarty->fetch('library/zc_search.lbi');
    die(json_encode($result));
}

if ($_REQUEST['act'] == 'rm_focus') {
    $pid = intval($_GET['id']);

    $res = $db->query("DELETE FROM {$ecs->table('zc_focus')} WHERE pid='$pid'");
    $res = $db->query("UPDATE" . $ecs->table('zc_project') . "SET focus_num=focus_num-1 WHERE id='$pid'");
    if ($res) {
        show_message($_LANG['lang_crowd_focus_cancel'], $_LANG['back_up_page'], 'user.php?act=crowdfunding');
    }
}

//删除浏览历史 by wu
if ($_REQUEST['act'] == 'delete_zc_history') {
    $result = array('error' => 0, 'message' => '', 'content' => '');
    setcookie('ECS[zc_history]', $zcgoods_id, gmtime() - 3600 * 24 * 30, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
    $result['error'] = 1;
    die(json_encode($result));
}

//将超过n位的数字化为个、十、百、千、万等 by wu
function setNumberFormat($number = 0, $limit = 0, $point = 0, $round = true) {
    $number = intval($number);
    $result = $number;
    $count = strlen($number);

    switch ($count - 1) {
        case 0 : $dividend = 1;
            $name = $GLOBALS['_LANG']['number'][0];
            break;
        case 1 : $dividend = 10;
            $name = $GLOBALS['_LANG']['number'][1];
            break;
        case 2 : $dividend = 100;
            $name = $GLOBALS['_LANG']['number'][2];
            break;
        case 3 : $dividend = 1000;
            $name = $GLOBALS['_LANG']['number'][3];
            break;
        case 4 : $dividend = 10000;
            $name = $GLOBALS['_LANG']['number'][4];
            break;
        case 5 : $dividend = 100000;
            $name = $GLOBALS['_LANG']['number'][5];
            break;
        case 6 : $dividend = 1000000;
            $name = $GLOBALS['_LANG']['number'][6];
            break;
        case 7 : $dividend = 10000000;
            $name = $GLOBALS['_LANG']['number'][7];
            break;
        case 8 : $dividend = 100000000;
            $name = $GLOBALS['_LANG']['number'][8];
            break;
        default : $dividend = 1;
            $name = $GLOBALS['_LANG']['number'][0];
            break;
    }

    //如果数字长度大于限制长度，则进行处理
    if ($count > $limit) {
        if ($round) {
            $result = round($number / $dividend, $point) . $name;
        } else {
            $symbol = empty($point) ? '' : '.'; //小数点
            $prev = floor($number / $dividend); //小数前
            $next = substr($number - $prev * $dividend, 0, $point); //小数后
            $result = $prev . $symbol . $next . $name;
        }
    }

    return $result;
}

//将时间转化为刚刚、几分钟前等等 by wu
function get_time_past($time = 0, $now = 0) {
    $time_past = "";

    if ($now >= $time) {
        //相差时间
        $diff = $now - $time;

        //一分钟内：刚刚
        if ($diff > 0 && $diff <= 60) {
            $time_past = $GLOBALS['_LANG']['Opportunity'][0];
        }
        //一小时内：n分钟前
        elseif ($diff > 60 && $diff <= 3600) {
            $time_past = floor($diff / 60) . $GLOBALS['_LANG']['Opportunity'][1];
        }
        //一天内：n小时前
        elseif ($diff > 3600 && $diff <= 86400) {
            $time_past = floor($diff / 3600) . $GLOBALS['_LANG']['Opportunity'][2];
        }
        //一月内：n天前
        elseif ($diff > 86400 && $diff <= 2592000) {
            $time_past = floor($diff / 86400) . $GLOBALS['_LANG']['Opportunity'][3];
        }
        //一年内：n月前
        elseif ($diff > 2592000 && $diff <= 31536000) {
            $time_past = floor($diff / 2592000) . $GLOBALS['_LANG']['Opportunity'][4];
        }
        //一年后：n年前
        elseif ($diff > 31536000) {
            $time_past = floor($diff / 31536000) . $GLOBALS['_LANG']['Opportunity'][5];
        }
    } else {
        $time_past = $GLOBALS['_LANG']['Opportunity'][6];
    }

    return $time_past;
}

//获取众筹项目列表
function get_special_zc_list($type = 0, $num = 5, $sort = "DESC") {
    $where = " WHERE 1 ";

    if ($type == 1) {
        $where .= " AND is_best=1 ";
    }

    $now = gmtime();
    $where .= " AND start_time < '$now' AND end_time > '$now' ";

    $sql = " SELECT id, title, title_img FROM " . $GLOBALS['ecs']->table('zc_project') . " $where ORDER BY id $sort LIMIT $num ";
    $zc_list = $GLOBALS['db']->getAll($sql);

    return $zc_list;
}

//获取支持者数量 by wu
function get_backer_num($zcid = 0) {
    $sql = " SELECT join_num from " . $GLOBALS['ecs']->table('zc_project') . " WHERE id = '$zcid'";
    return $GLOBALS['db']->getOne($sql);
}

//获取话题数量 by wu
function get_topic_num($zcid = 0) {
    $sql = " select count(topic_id) from " . $GLOBALS['ecs']->table('zc_topic') . " where pid='$zcid' AND topic_status = 1 AND parent_topic_id = 0 ";
    return $GLOBALS['db']->getOne($sql);
}

//得到所有子分类ID liu
function get_cat_child_id($cat_id = 0) {
    if ($cat_id > 0) {
        $sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('zc_category') . " WHERE parent_id = '$cat_id' AND is_show = 1 ";
        if ($GLOBALS['db']->getOne($sql)) {
            /* 获取当前分类及其子分类 */
            $sql = 'SELECT cat_id ' .
                    'FROM ' . $GLOBALS['ecs']->table('zc_category') .
                    "WHERE parent_id = '$cat_id' ORDER BY sort_order ASC, cat_id ASC";

            $res = $GLOBALS['db']->getAll($sql);

            foreach ($res AS $row) {
                if (isset($row['cat_id']) != NULL) {
                    $cat_arr[$row['cat_id']]['cat_id'] = get_child_tree_id($row['cat_id']);
                }
            }
        }


        if (isset($cat_arr)) {
            return $cat_arr;
        }
    }
}

function get_child_tree_id($tree_id = 0) {
    $three_arr = array();
    $sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('zc_category') . " WHERE parent_id = '$tree_id' AND is_show = 1 ";
    if ($GLOBALS['db']->getOne($sql) || $tree_id == 0) {
        $child_sql = 'SELECT cat_id ' .
                'FROM ' . $GLOBALS['ecs']->table('zc_category') .
                "WHERE parent_id = '$tree_id' ORDER BY sort_order ASC, cat_id ASC";
        $res = $GLOBALS['db']->getAll($child_sql);
        foreach ($res AS $row) {
            if (isset($row['cat_id']) != NULL) {
                $three_arr[$row['cat_id']]['cat_id'] = get_child_tree_id($row['cat_id']);
            }
        }
    }
    return $three_arr;
}

//取得当前项目发起人信息
function get_initiator_info($cid) {
    $id = get_initiator_id($cid);
    $sql = " SELECT * FROM " . $GLOBALS['ecs']->table('zc_initiator') . " WHERE id = '$id' ";
    $row = $GLOBALS['db']->getRow($sql);
    //处理等级标识
    $logo = explode(',', $row['rank']);
    if ($logo) {
        foreach ($logo as $val) {
            $row['logo'][] = get_rank_logo($val);
        }
    }
    $start_sql = " SELECT count(*) FROM " . $GLOBALS['ecs']->table('zc_project') . " WHERE init_id = '$id' ";
    $count = $GLOBALS['db']->getOne($start_sql);
    $row['start_count'] = isset($count) ? $count : 1;
    return $row;
}

//取得等级身份标识
function get_rank_logo($id) {
    $sql = " SELECT logo_name, img FROM " . $GLOBALS['ecs']->table('zc_rank_logo') . " WHERE id = '$id' ";
    $row = $GLOBALS['db']->getRow($sql);
    return $row;
}

//取得发起人ID
function get_initiator_id($cid) {
    $sql = " SELECT init_id FROM " . $GLOBALS['ecs']->table('zc_project') . " WHERE id = '$cid' ";
    $init_id = $GLOBALS['db']->getOne($sql);
    return $init_id;
}

/* 浏览历史 */

function zc_cate_history($limit = '5') {
    $str = '';
    if (!empty($_COOKIE['ECS']['zc_history'])) {
        $string = "'" . $_COOKIE['ECS']['zc_history'] . "'"; //按照浏览时间排序用 liu
        $order = " ORDER BY SUBSTRING_INDEX($string,id,1) "; //排序 liu
        $where = db_create_in($_COOKIE['ECS']['zc_history'], 'id');

        $sql = "SELECT id, title, title_img FROM " . $GLOBALS['ecs']->table('zc_project') .
                " WHERE $where $order";
        $res = $GLOBALS['db']->selectLimit($sql, $limit);

        $arr = array();
        while ($row = $GLOBALS['db']->fetchRow($res)) {
            $arr[$row['id']]['id'] = $row['id'];
            $arr[$row['id']]['title'] = $row['title'];
            $arr[$row['id']]['title_img'] = $row['title_img'];
        }
    }
    return $arr;
}

/**
 * 获得指定国家的所有省份
 *
 * @access      public
 * @param       int     country    国家的编号
 * @return      array
 */
function get_regions_log($type = 0, $parent = 0) {
    $sql = 'SELECT region_id, region_name FROM ' . $GLOBALS['ecs']->table('region') .
            " WHERE region_type = '$type' AND parent_id = '$parent'";

    return $GLOBALS['db']->GetAll($sql);
}

function get_order_confirm_address($address_id) {
    $sql = " SELECT * FROM " . $GLOBALS['ecs']->table('user_address') . " WHERE address_id = '$address_id' ";
    $row = $GLOBALS['db']->getRow($sql);
    return $row;
}

?>