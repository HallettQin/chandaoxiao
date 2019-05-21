<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/19
 * Time: 23:41
 */
define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
require_once ROOT_PATH . 'includes/lib_goods.php';
require_once ROOT_PATH . 'includes/lib_order.php';
require_once ROOT_PATH . '/' . ADMIN_PATH . '/includes/lib_goods.php';
require(ROOT_PATH . '/includes/cls_json.php');
admin_priv('presale');
$adminru = get_admin_ru_id();

if ($adminru['ru_id'] == 0) {
    $smarty->assign('priv_ru', 1);
}
else {
    $smarty->assign('priv_ru', 0);
}

if (empty($_REQUEST['act'])) {
    $_REQUEST['act'] = 'list';
}
else {
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

if ($_REQUEST['act'] == 'list') {
    $smarty->assign('menu_select', array('action' => '02_cat_and_goods', 'current' => '01_goods_list'));

    $smarty->assign('full_page', 1);
    $smarty->assign('ur_here', $_LANG['sample_list']);
    $smarty->assign('action_link', array('href' => 'presale.php?act=add', 'text' => $_LANG['add_presale']));

    //样品列表
    $list = sample_list($adminru['ru_id']);
    $smarty->assign('list', $list['item']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->display('sample_list.dwt');
} elseif ($_REQUEST['act'] == 'update_review_status') {
    //审核
    $json = new JSON;
    $result = array('error' => 0, 'message' => '','content' => '');

    $ids = isset($_REQUEST['ids']) ? $_REQUEST['ids'] : 0;
    $other  = [];
    $other['review_status'] = isset($_REQUEST['review_status']) ? intval($_REQUEST['review_status']) : 2;
    $other['review_content'] = !empty($_REQUEST['review_content']) ? addslashes(trim($_REQUEST['review_content'])) : '';
    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('sample_activity'), $other, "UPDATE", "act_id in (".$ids.")");

    $result['type'] = $type;
    admin_log([$ids], 'audit', 'sample');
    die($json->encode($result));

} elseif ($_REQUEST['act'] == 'review_status') {
    $smarty->assign('menu_select', array('action' => '02_cat_and_goods', 'current' => '01_review_status'));
    $smarty->assign('full_page', 1);
    $smarty->assign('ur_here', $_LANG['sample_list']);

    $type = isset($_REQUEST['type']) && !empty($_REQUEST['type']) ? addslashes($_REQUEST['type']) : 'not_audit';
    if($type == 'not_pass'){
        $status = 2;
        $_REQUEST['review_status'] = 2;
        $smarty->assign('ur_here', $_LANG['lab_review_not_pass']);
    }else{
        $status = 1;
        $_REQUEST['review_status'] = 1;
        $smarty->assign('ur_here', $_LANG['lab_review_not_audit']);
    }

    $list = sample_list($adminru['ru_id']);
    $smarty->assign('list', $list['item']);

    $goods_list_type = get_sample_type_number($adminru['ru_id']);
    $smarty->assign('goods_list_type', $goods_list_type);

    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);
    $smarty->assign('filter',       $list['filter']);

    //审核页面
    $smarty->display('sample_review_list.dwt');
    exit;
} elseif ($_REQUEST['act'] == 'query') {
    $list = sample_list($adminru['ru_id']);
    $smarty->assign('list', $list['item']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count', $list['page_count']);

    $tpl = $list['filter']['review_status'] ? 'sample_review_list.dwt' : 'sample_list.dwt';
    make_json_result($smarty->fetch($tpl), '', array('filter' => $list['filter'], 'page_count' => $list['page_count']));

} else {
    if (($_REQUEST['act'] == 'add') || ($_REQUEST['act'] == 'edit')) {
        if ($_REQUEST['act'] == 'add') {
            $sample =  array(
                'act_desc' => ''
            );
            $smarty->assign('ur_here', $_LANG['add_sample']);
            $smarty->assign('form_action', 'insert');
        } else {
            $sample_id = intval($_REQUEST['id']);
            if ($sample_id <= 0) {
                exit('invalid param');
            }
            $smarty->assign('ur_here', $_LANG['edit_sample']);

            $sample = sample_info($sample_id, 0, 0, 'seller');
            $smarty->assign('form_action', 'update');
        }
    } elseif ($_REQUEST['act'] == 'insert_update') {
        $sample_id = intval($_POST['act_id']);

        $goods_id = intval($_POST['goods_id']);
        $info = good_sample($goods_id);
        if ($info && ($info['act_id'] != $sample_id)) {
            //是否已经存在活动
            sys_msg($_LANG['error_goods_exist']);
        }

        //生产周期
        $pruduct_cycle = intval($_POST['production_cycle']);
        if ($production_cycle < 1) {
            $production_cycle = 1;
        }

        //价格阶梯
        $price_ladder = array();
        $count = count($_POST['ladder_amount']);

        for ($i = $count - 1; 0 <= $i; $i--) {
            $amount = intval($_POST['ladder_amount'][$i]);

            if ($amount <= 0) {
                continue;
            }

            $price = round(floatval($_POST['ladder_price'][$i]), 2);

            if ($price <= 0) {
                continue;
            }

            $price_ladder[$amount] = array('amount' => $amount, 'price' => $price);
        }

        $amount_list = array_keys($price_ladder);

        if ((0 < $restrict_amount) && ($restrict_amount < max($amount_list))) {
            sys_msg($_LANG['error_restrict_amount']);
        }

        ksort($price_ladder);
        $price_ladder = array_values($price_ladder);

        //获取商品名
        $goods_name = $db->getOne('SELECT goods_name FROM ' . $ecs->table('goods') . ' WHERE goods_id = \'' . $goods_id . '\'');
        //活动名称为商品名
        $act_name = $goods_name;
        $sample = array('act_name' => $act_name, 'act_desc' => $_POST['act_desc'], 'goods_id' => $goods_id, 'goods_name' => $goods_name, 'ext_info' => serialize(array('price_ladder' => $price_ladder)), 'production_cycle'=>$pruduct_cycle);
        clear_cache_files();
        if (0 < $sample_id) {
            if (isset($_POST['review_status'])) {
                $review_status = (!empty($_POST['review_status']) ? intval($_POST['review_status']) : 1);
                $review_content = (!empty($_POST['review_content']) ? addslashes(trim($_POST['review_content'])) : '');
                $sample['review_status'] = $review_status;
                $sample['review_content'] = $review_content;
            }


            $db->autoExecute($ecs->table('sample_activity'), $sample, 'UPDATE', 'act_id = \'' . $sample_id . '\'');
            admin_log(addslashes($goods_name) . '[' . $sample_id . ']', 'edit', 'sample');
            $links = array(
                array('href' => 'sample.php?act=list&' . list_link_postfix(), 'text' => $_LANG['back_list'])
            );
            sys_msg($_LANG['edit_success'], 0, $links);
        } else {
            $sample['review_status'] = 3;
            $sample['user_id'] = $adminru['ru_id'];
            $db->autoExecute($ecs->table('sample_activity'), $sample, 'INSERT');
            admin_log(addslashes($goods_name), 'add', 'sample');
            $links = array(
                array('href' => 'sample.php?act=add', 'text' => $_LANG['continue_add']),
                array('href' => 'sample.php?act=list', 'text' => $_LANG['back_list'])
            );
            sys_msg($_LANG['add_success'], 0, $links);
        }

    } else if ($_REQUEST['act'] == 'search_goods')
    {
        check_authz_json('whole_sale');
        include_once ROOT_PATH . 'includes/cls_json.php';
        $json = new JSON();
        $filter = $json->decode($_GET['JSON']);
        //添加查询筛选
        $filter->good_mode = 4;
        $arr = get_goods_list($filter);
        if (empty($arr))
        {
            $arr[0] = array('goods_id' => 0, 'goods_name' => $_LANG['search_result_empty']);
        }
        make_json_result($arr);
    } else if ($_REQUEST['act'] == 'batch_drop') {
        if (isset($_POST['checkboxes'])) {
            $del_count = 0;

            foreach ($_POST['checkboxes'] as $key => $id) {
                $sample = sample_info($id, 0, 0, 'seller');

                if ($sample['valid_order'] <= 0) {
                    $sql = 'DELETE FROM ' . $GLOBALS['ecs']->table('sample_activity') . ' WHERE act_id = \'' . $id . '\' LIMIT 1';
                    $GLOBALS['db']->query($sql, 'SILENT');
                    admin_log(addslashes($sample['goods_name']) . '[' . $id . ']', 'remove', 'sample');
                    $del_count++;
                }
            }

            if (0 < $del_count) {
                clear_cache_files();
            }

            $links[] = array('text' => $_LANG['back_list'], 'href' => 'sample.php?act=list');
            sys_msg(sprintf($_LANG['batch_drop_success'], $del_count), 0, $links);
        }
        else {
            $links[] = array('text' => $_LANG['back_list'], 'href' => 'sample.php?act=list');
            sys_msg($_LANG['no_select_sample'], 0, $links);
        }
    } else if ($_REQUEST['act'] == 'batch_audited') {

    }
    $smarty->assign('info', $sample);
    $smarty->display('sample_info.dwt');
}

function good_sample($goods_id){
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('sample_activity') . ' WHERE goods_id = \'' . $goods_id . '\' ' . ' LIMIT 1';
    return $GLOBALS['db']->getRow($sql);
}

function sample_list($ru_id) {
    $result = get_filter();
    $where = '';
    if ($result === false) {
        $filter['keyword'] = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        if (isset($_REQUEST['is_ajax']) && ($_REQUEST['is_ajax'] == 1)) {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'ga.act_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $where = (!empty($filter['keyword']) ? ' AND (ga.goods_name LIKE \'%' . mysql_like_quote($filter['keyword']) . '%\')' : '');

        $filter['review_status'] = empty($_REQUEST['review_status']) ? 0 : intval($_REQUEST['review_status']);
        if ($filter['review_status']) {
            $where .= ' AND ga.review_status = \'' . $filter['review_status'] . '\' ';
        }

        $filter['store_search'] = !isset($_REQUEST['store_search']) ? -1 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';
        $store_where = '';
        $store_search_where = '';


        if (-1 < $filter['store_search']) {
            if ($ru_id == 0) {
                if (0 < $filter['store_search']) {
                    if ($_REQUEST['store_type']) {
                        $store_search_where = 'AND msi.shopNameSuffix = \'' . $_REQUEST['store_type'] . '\'';
                    }

                    if ($filter['store_search'] == 1) {
                        $where .= ' AND ga.user_id = \'' . $filter['merchant_id'] . '\' ';
                    }
                    else if ($filter['store_search'] == 2) {
                        $store_where .= ' AND msi.rz_shopName LIKE \'%' . mysql_like_quote($filter['store_keyword']) . '%\'';
                    }
                    else if ($filter['store_search'] == 3) {
                        $store_where .= ' AND msi.shoprz_brandName LIKE \'%' . mysql_like_quote($filter['store_keyword']) . '%\' ' . $store_search_where;
                    }

                    if (1 < $filter['store_search']) {
                        $where .= ' AND (SELECT msi.user_id FROM ' . $GLOBALS['ecs']->table('merchants_shop_information') . ' as msi ' . ' WHERE msi.user_id = ga.user_id ' . $store_where . ') > 0 ';
                    }
                }
                else {
                    $where .= ' AND ga.user_id = 0';
                }
            }
        }
        $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('sample_activity') . ' AS ga ' . ' WHERE 1 ' . $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        $filter = page_and_size($filter);

        $sql = 'SELECT ga.* ' . 'FROM ' . $GLOBALS['ecs']->table('sample_activity') . ' AS ga ' . ' WHERE 1 ' . $where . ' ' . ' ORDER BY ' . $filter['sort_by'] . ' ' . $filter['sort_order'] . ' ' . ' LIMIT ' . $filter['start'] . ', ' . $filter['page_size'];
        $filter['keyword'] = stripslashes($filter['keyword']);
        set_filter($filter, $sql);
    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->query($sql);
    $list = array();

    while ($row = $GLOBALS['db']->fetchRow($res)) {
//        $stat = presale_stat($row['act_id'], $row['deposit']);
        $arr = array_merge($row, []);
//        $status = presale_status($arr);
//        $arr['start_time'] = local_date($GLOBALS['_CFG']['date_format'], $arr['start_time']);
//        $arr['end_time'] = local_date($GLOBALS['_CFG']['date_format'], $arr['end_time']);
//        $arr['pay_start_time'] = local_date($GLOBALS['_CFG']['date_format'], $arr['pay_start_time']);
//        $arr['pay_end_time'] = local_date($GLOBALS['_CFG']['date_format'], $arr['pay_end_time']);
//        $arr['cur_status'] = $GLOBALS['_LANG']['gbs'][$status];
        $arr['shop_name'] = get_shop_name($row['user_id'], 1);
        $list[] = $arr;
    }

    $arr = array('item' => $list, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
}

function get_sample_type_number($ru_id) {
    $result = get_filter();
    $where = '';
    if ($result === false) {
        $filter['keyword'] = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        if (isset($_REQUEST['is_ajax']) && ($_REQUEST['is_ajax'] == 1)) {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'ga.act_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $where = (!empty($filter['keyword']) ? ' AND (ga.goods_name LIKE \'%' . mysql_like_quote($filter['keyword']) . '%\')' : '');


        $filter['store_search'] = !isset($_REQUEST['store_search']) ? -1 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';
        $store_where = '';
        $store_search_where = '';

        if (-1 < $filter['store_search']) {
            if ($ru_id == 0) {
                if (0 < $filter['store_search']) {
                    if ($_REQUEST['store_type']) {
                        $store_search_where = 'AND msi.shopNameSuffix = \'' . $_REQUEST['store_type'] . '\'';
                    }

                    if ($filter['store_search'] == 1) {
                        $where .= ' AND ga.user_id = \'' . $filter['merchant_id'] . '\' ';
                    } else if ($filter['store_search'] == 2) {
                        $store_where .= ' AND msi.rz_shopName LIKE \'%' . mysql_like_quote($filter['store_keyword']) . '%\'';
                    } else if ($filter['store_search'] == 3) {
                        $store_where .= ' AND msi.shoprz_brandName LIKE \'%' . mysql_like_quote($filter['store_keyword']) . '%\' ' . $store_search_where;
                    }

                    if (1 < $filter['store_search']) {
                        $where .= ' AND (SELECT msi.user_id FROM ' . $GLOBALS['ecs']->table('merchants_shop_information') . ' as msi ' . ' WHERE msi.user_id = ga.user_id ' . $store_where . ') > 0 ';
                    }
                } else {
                    $where .= ' AND ga.user_id = 0';
                }
            }
        }
    }


        $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('sample_activity') . ' AS ga ' . ' WHERE 1 ' . $where;
    $arr['not_status'] = $GLOBALS['db']->getOne($sql . ' AND ga.review_status = 1');
    $arr['not_pass'] = $GLOBALS['db']->getOne($sql . ' AND ga.review_status = 2');


    return $arr;
}
