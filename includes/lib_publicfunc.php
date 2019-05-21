<?php

/**
 * 公用函数库
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Hallett
 */
if (!defined('IN_ECS')) {
    die('Hacking attempt');
}

//插入仓库/地区属性价格数据
function get_warehouse_area_attr_price_insert($warehouse_area, $goods_id, $goods_attr_id, $table) {

    $arr = array();

    if (is_array($warehouse_area)) {
        for ($i = 0; $i < count($warehouse_area); $i++) {

            if (!empty($warehouse_area[$i])) {

                $parent = array(
                    'goods_id' => $goods_id,
                    'goods_attr_id' => $goods_attr_id
                );

                if ($table == 'warehouse_attr') {
                    $where = " AND warehouse_id = '" . $warehouse_area[$i] . "'";
                    $parent['warehouse_id'] = $warehouse_area[$i];
                    $parent['attr_price'] = $_POST['attr_price_' . $warehouse_area[$i]];
                } elseif ($table == 'warehouse_area_attr') {
                    $where = " AND area_id = '" . $warehouse_area[$i] . "'";
                    $parent['area_id'] = $warehouse_area[$i];
                    $parent['attr_price'] = $_POST['attrPrice_' . $warehouse_area[$i]];
                }

                if($goods_id){
                    $admin_id = get_admin_id();
                    $where .= " AND admin_id = '$admin_id'";
                }

                $sql = "SELECT id FROM " . $GLOBALS['ecs']->table($table) . " WHERE goods_id = '$goods_id' AND goods_attr_id = '$goods_attr_id' $where";
                $id = $GLOBALS['db']->getOne($sql);

                if ($id > 0) {
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table($table), $parent, 'UPDATE', "goods_id = '$goods_id' and goods_attr_id = '$goods_attr_id' $where");
                } else {

                    $parent['admin_id'] = $admin_id;
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table($table), $parent, 'INSERT');
                }
            }
        }
    } else if (is_array($goods_attr_id)) {
        for ($i = 0; $i < count($goods_attr_id); $i++) {

            if (!empty($goods_attr_id[$i])) {

                $parent = array(
                    'goods_id' => $goods_id,
                    'goods_attr_id' => $goods_attr_id[$i]
                );

                if ($table == 'warehouse_attr') {
                    $where = " AND warehouse_id = '$warehouse_area'";
                    $parent['warehouse_id'] = $warehouse_area;
                    $parent['attr_price'] = $_POST['attr_price_' . $goods_attr_id[$i]];
                } elseif ($table == 'warehouse_area_attr') {
                    $where = " AND area_id = '$warehouse_area'";
                    $parent['area_id'] = $warehouse_area;
                    $parent['attr_price'] = $_POST['attrPrice_' . $goods_attr_id[$i]];
                }

                if($goods_id){
                    $admin_id = get_admin_id();
                    $where .= " AND admin_id = '$admin_id'";
                }

                $sql = "SELECT id FROM " . $GLOBALS['ecs']->table($table) . " WHERE goods_id = '$goods_id' AND goods_attr_id = '" . $goods_attr_id[$i] . "' $where";
                $id = $GLOBALS['db']->getOne($sql);

                if ($id > 0) {
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table($table), $parent, 'UPDATE', "goods_id = '$goods_id' and goods_attr_id = '" . $goods_attr_id[$i] . "' $where");
                } else {

                    $parent['admin_id'] = $admin_id;
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table($table), $parent, 'INSERT');
                }
            }
        }
    }
}

/* 获取当前商家的等级 */

function get_seller_grade_rank($ru_id) {
    $sql = "SELECT sg.goods_sun, sg.seller_temp, sg.favorable_rate, sg.give_integral, sg.rank_integral, sg.pay_integral FROM " . $GLOBALS['ecs']->table('merchants_grade') . " AS mg, " .
            $GLOBALS['ecs']->table('seller_grade') . " AS sg " .
            " WHERE mg.grade_id = sg.id AND ru_id = '$ru_id' LIMIT 1";

    $res = $GLOBALS['db']->getRow($sql);

    $res['favorable_rate'] = !empty($res['favorable_rate']) ? $res['favorable_rate'] / 100 : 1;
    $res['give_integral'] = !empty($res['give_integral']) ? $res['give_integral'] / 100 : 1;
    $res['rank_integral'] = !empty($res['rank_integral']) ? $res['rank_integral'] / 100 : 1;
    $res['pay_integral'] = !empty($res['pay_integral']) ? $res['pay_integral'] / 100 : 1;

    return $res;
}

/**
 * 申请日志列表
 */
function get_account_log_list($ru_id, $type = 0) {
    require_once(ROOT_PATH . 'includes/lib_order.php');

    $result = get_filter();
    if ($result === false) {
        /* 过滤条件 */
        $filter['keywords'] = !isset($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1) {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }

        $filter['order_sn'] = !isset($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
        $filter['out_up'] = !isset($_REQUEST['out_up']) ? 0 : intval($_REQUEST['out_up']);
        $filter['log_type'] = !isset($_REQUEST['log_type']) ? 0 : intval($_REQUEST['log_type']);
        $filter['handler'] = !isset($_REQUEST['handler']) ? 0 : intval($_REQUEST['handler']);
        $filter['rawals'] = !isset($_REQUEST['rawals']) ? 0 : intval($_REQUEST['rawals']);

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'sal.log_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $filter['act_type'] = !isset($_REQUEST['act_type']) ? 'detail' : $_REQUEST['act_type'];
        $filter['ru_id'] = !isset($_REQUEST['ru_id']) ? $ru_id : intval($_REQUEST['ru_id']);

        $ex_where = ' WHERE 1 ';

        //订单编号
        if ($filter['order_sn']) {
            $ex_where .= " AND (sal.apply_sn = '" . $filter['order_sn'] . "'";
            $ex_where .= " OR ";
            $ex_where .= " (SELECT order_sn FROM " . $GLOBALS['ecs']->table("order_info") . " AS oi WHERE sal.order_id = oi.order_id LIMIT 1) = '" . $filter['order_sn'] . "')";
        }

        //收入/支出
        if ($filter['out_up']) {
            if ($filter['out_up'] != 4) {
                if ($filter['out_up'] == 3) {
                    $ex_where .= " AND sal.log_type = '" . $filter['out_up'] . "'";
                }
                $ex_where .= " AND (sal.log_type > '" . $filter['out_up'] . "' OR sal.log_type =  '" . $filter['out_up'] . "')";
            } else {
                $ex_where .= " AND sal.log_type = '" . $filter['out_up'] . "'";
            }
        }
        if($filter['rawals'] == 1){
            $type = array(1);
        }
        //待处理
        if ($filter['handler']) {
            if ($filter['handler'] == 1) {
                $ex_where .= " AND sal.is_paid = 1";
            } else {
                $ex_where .= " AND sal.is_paid = 0";
            }
        }

        //类型
        if ($filter['log_type']) {
            $ex_where .= " AND sal.log_type = '" . $filter['log_type'] . "'";
        }

        //管理员查询的权限 -- 店铺查询 start
        $filter['store_search'] = empty($_REQUEST['store_search']) ? 0 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';

        $store_where = '';
        $store_search_where = '';
        if ($filter['store_search'] != 0) {
            if ($ru_id == 0) {

                if ($_REQUEST['store_type']) {
                    $store_search_where = "AND mis.shopNameSuffix = '" . $_REQUEST['store_type'] . "'";
                }

                if ($filter['store_search'] == 1) {
                    $ex_where .= " AND mis.user_id = '" . $filter['merchant_id'] . "' ";
                } elseif ($filter['store_search'] == 2) {
                    $store_where .= " AND mis.rz_shopName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%'";
                } elseif ($filter['store_search'] == 3) {
                    $store_where .= " AND mis.shoprz_brandName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%' " . $store_search_where;
                }

                if ($filter['store_search'] > 1) {
                    $ex_where .= " AND mis.user_id > 0 $store_where ";
                }
            }
        }
        //管理员查询的权限 -- 店铺查询 end

        $type = implode(',', $type);

        if ($filter['ru_id']) {
            $ex_where .= " AND sal.ru_id = '" . $filter['ru_id'] . "'";
        }

        $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('seller_account_log') . " AS sal " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS mis ON sal.ru_id = mis.user_id " .
                " $ex_where AND sal.log_type IN($type)";
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        /* 分页大小 */
        $filter = page_and_size($filter);

        $sql = "SELECT sal.* FROM " . $GLOBALS['ecs']->table('seller_account_log') . " AS sal " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS mis ON sal.ru_id = mis.user_id " .
                " $ex_where AND sal.log_type IN($type)" .
                " ORDER BY " . $filter['sort_by'] . ' ' . $filter['sort_order'] .
                " LIMIT " . $filter['start'] . ',' . $filter['page_size'];

        $filter['keywords'] = stripslashes($filter['keywords']);
        set_filter($filter, $sql);
    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    for ($i = 0; $i < count($res); $i++) {
        $res[$i]['shop_name'] = get_shop_name($res[$i]['ru_id'], 1);
        $order = order_info($res[$i]['order_id']);
        $res[$i]['order_sn'] = !empty($order['order_sn']) ? "【订单】" . $order['order_sn'] : $res[$i]['apply_sn'];
        $res[$i]['amount'] = price_format($res[$i]['amount'], false);
        $res[$i]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $res[$i]['add_time']);
        $res[$i]['payment_info'] = payment_info($res[$i]['pay_id']);
        $res[$i]['apply_sn'] = sprintf($_LANG['01_apply_sn'], $res[$i]['apply_sn']);
    }

    $arr = array('log_list' => $res, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

/**
 * 申请日志详细信息
 */
function get_account_log_info($log_id) {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('seller_account_log') . " WHERE log_id = '$log_id' LIMIT 1";
    $res = $GLOBALS['db']->getRow($sql);

    if ($res) {
        $res['shop_name'] = get_shop_name($res['ru_id'], 1);
        $res['payment_info'] = payment_info($res['pay_id']);

        /* 商家资金 start */
        $info = get_seller_shopinfo($res['ru_id'], array('seller_money', 'frozen_money'));
        $res['seller_money'] = $info['seller_money']; //商家可提现金额
        $res['seller_frozen'] = $info['frozen_money']; //商家冻结金额
        /* 商家资金 end */
    }

    return $res;
}

/**
 * 查询所有商家分类
 */
function get_seller_category() {
    $sql = "SELECT c.*, (SELECT c2.cat_name FROM " . $GLOBALS['ecs']->table("category") . " AS c2 WHERE c2.cat_id = c.parent_id LIMIT 1) AS parent_name " .
            " FROM " . $GLOBALS['ecs']->table("merchants_category") . " AS mc," .
            $GLOBALS['ecs']->table("category") . " AS c " .
            " WHERE 1 AND mc.cat_id = c.cat_id";
    $res = $GLOBALS['db']->getAll($sql);

    $chid_level = 0;
    $level = 1;

    $arr = array();
    if ($res) {
        foreach ($res as $key => $row) {
            $arr[$key]['cat_id'] = $row['cat_id'];
            $arr[$key]['cat_name'] = $row['cat_name'];
            $arr[$key]['parent_id'] = $row['parent_id'];

            $arr[$key]['keywords'] = $row['keywords'];
            $arr[$key]['cat_desc'] = $row['cat_desc'];
            $arr[$key]['sort_order'] = $row['sort_order'];
            $arr[$key]['measure_unit'] = $row['measure_unit'];
            $arr[$key]['show_in_nav'] = $row['show_in_nav'];
            $arr[$key]['style'] = $row['style'];
            $arr[$key]['grade'] = $row['grade'];
            $arr[$key]['filter_attr'] = $row['filter_attr'];
            $arr[$key]['is_top_style'] = $row['is_top_style'];
            $arr[$key]['top_style_tpl'] = $row['top_style_tpl'];
            $arr[$key]['cat_icon'] = $row['cat_icon'];
            $arr[$key]['is_top_show'] = $row['is_top_show'];
            $arr[$key]['category_links'] = $row['category_links'];
            $arr[$key]['category_topic'] = $row['category_topic'];
            $arr[$key]['pinyin_keyword'] = $row['pinyin_keyword'];
            $arr[$key]['cat_alias_name'] = $row['cat_alias_name'];
            $arr[$key]['template_file'] = $row['template_file'];

            $arr[$key]['parent_name'] = $row['parent_name'];

            $sql = "SELECT * FROM " . $GLOBALS['ecs']->table("merchants_category") . " WHERE cat_id = '" . $row['parent_id'] . "'";
            if ($GLOBALS['db']->getOne($sql, true)) {
                $cat_level = get_seller_cat_level($row['parent_id']);
                if ($cat_level['parent_id'] != 0) {
                    $chid = get_seller_cat_level($cat_level['parent_id']);
                    if ($chid) {
                        $chid_level += 1;
                    }
                }

                $arr[$key]['level'] = $level + $chid_level;
            } else {
                $arr[$key]['level'] = 0;
            }

            $cat_level = array('一', '二', '三', '四', '五', '六', '气', '八', '九', '十');
            $arr[$key]['belongs'] = $cat_level[$arr[$key]['level']] . "级";

            if ($arr[$key]['level'] == 0) {
                $row['parent_id'] = 0;
            }
        }
    }

    return $arr;
}

/**
 * 查询商家分类是否存在上一级
 */
function get_seller_cat_level($parent_id = 0, $level = 1) {
    $sql = "SELECT c.cat_id, c.cat_name, c.parent_id FROM " . $GLOBALS['ecs']->table("merchants_category") . " AS mc," .
            $GLOBALS['ecs']->table("category") . " AS c" .
            " WHERE mc.cat_id = c.cat_id AND c.cat_id = '$parent_id' LIMIT 1";
    $row = $GLOBALS['db']->getRow($sql);

    return $row;
}

/*
 * 商家分类
 * 获取上下级分类列表 by wu
 * $cat_id      分类id
 * $relation    关系 0:自己 1:上级 2:下级
 * $self        是否包含自己 true:包含 false:不包含
 */

function get_seller_select_category($cat_id = 0, $relation = 0, $self = true, $user_id = 0) {
    //静态数组
    static $cat_list = array();
    $cat_list[] = intval($cat_id);

    if ($user_id) {
        $where = " AND user_id = '$user_id'";
    }

    if ($relation == 0) {
        return $cat_list;
    } elseif ($relation == 1) {
        $sql = " select parent_id from " . $GLOBALS['ecs']->table('merchants_category') . " where cat_id='" . $cat_id . "' $where";
        $parent_id = $GLOBALS['db']->getOne($sql);
        if (!empty($parent_id)) {
            get_seller_select_category($parent_id, $relation, $self, $user_id);
        }
        //删除自己
        if ($self == false) {
            unset($cat_list[0]);
        }
        $cat_list[] = 0;
        //去掉重复，主要是0
        return array_reverse(array_unique($cat_list));
    } elseif ($relation == 2) {
        $sql = " select cat_id from " . $GLOBALS['ecs']->table('merchants_category') . " where parent_id='" . $cat_id . "' $where";
        $child_id = $GLOBALS['db']->getCol($sql);
        if (!empty($child_id)) {
            foreach ($child_id as $key => $val) {
                get_seller_select_category($val, $relation, $self, $user_id);
            }
        }
        //删除自己
        if ($self == false) {
            unset($cat_list[0]);
        }
        return $cat_list;
    }
}

/*
 * 平台分类
 * 获取当级分类列表 by wu
 * $cat_id      分类id
 * $relation    关系 0:自己 1:上级 2:下级
 */

function get_seller_category_list($cat_id = 0, $relation = 0, $user_id = 0) {

    $where = '';
    if ($user_id) {
        $where .= " AND user_id = '$user_id'";
    }

    if ($relation == 0) {
        $parent_id = $GLOBALS['db']->getOne(" SELECT parent_id FROM " . $GLOBALS['ecs']->table('merchants_category') . " WHERE cat_id = '$cat_id' $where");
    } elseif ($relation == 1) {
        $parent_id = $GLOBALS['db']->getOne(" SELECT parent_id FROM " . $GLOBALS['ecs']->table('merchants_category') . " WHERE cat_id = '$cat_id' $where");
    } elseif ($relation == 2) {
        $parent_id = $cat_id;
    }

    $parent_id = empty($parent_id) ? 0 : $parent_id;
    $category_list = $GLOBALS['db']->getAll(" SELECT cat_id, cat_name FROM " . $GLOBALS['ecs']->table('merchants_category') . " WHERE parent_id = '$parent_id' $where");
    foreach ($category_list as $key => $val) {
        if ($cat_id == $val['cat_id']) {
            $is_selected = 1;
        } else {
            $is_selected = 0;
        }
        $category_list[$key]['is_selected'] = $is_selected;
    }
    return $category_list;
}

//设置默认筛选 by wu
function set_default_filter($goods_id = 0, $cat_id = 0, $user_id = 0, $cat_type_show = 0, $table = 'category') {
    //分类导航
    if ($cat_id) {
        $parent_cat_list = get_select_category($cat_id, 1, true, $user_id, $table);
        $filter_category_navigation = get_array_category_info($parent_cat_list, $table);
        $GLOBALS['smarty']->assign('filter_category_navigation', $filter_category_navigation);
    }

    if ($user_id) {
        $seller_shop_cat = seller_shop_cat($user_id);
    } else {
        $seller_shop_cat = array();
    }

    $GLOBALS['smarty']->assign('filter_category_list', get_category_list($cat_id, 0, $seller_shop_cat, $user_id, 2, $table)); //分类列表
    $GLOBALS['smarty']->assign('filter_brand_list', search_brand_list($goods_id, $user_id)); //品牌列表
    $GLOBALS['smarty']->assign('cat_type_show', $cat_type_show); //平台分类

    return true;
}

function set_seller_default_filter($goods_id = 0, $cat_id = 0, $user_id = 0) {
    //分类导航
    if ($cat_id > 0) {
        $seller_parent_cat_list = get_seller_select_category($cat_id, 1, true, $user_id);
        $seller_filter_category_navigation = get_seller_array_category_info($seller_parent_cat_list);

        $GLOBALS['smarty']->assign('seller_filter_category_navigation', $seller_filter_category_navigation);
    }

    $GLOBALS['smarty']->assign('seller_filter_category_list', get_seller_category_list($cat_id, 0, $user_id)); //分类列表
    $GLOBALS['smarty']->assign('seller_cat_type_show', 1); //商家分类

    return true;
}

//给出cat_id,返回逐级分类 by wu
function get_seller_every_category($cat_id = 0) {
    $parent_cat_list = get_seller_category_array($cat_id, 1, true);
    $filter_category_navigation = get_seller_array_category_info($parent_cat_list);
    $cat_nav = "";
    if ($filter_category_navigation) {
        foreach ($filter_category_navigation as $key => $val) {
            if ($key == 0) {
                $cat_nav .= $val['cat_name'];
            } elseif ($key > 0) {
                $cat_nav .= " > " . $val['cat_name'];
            }
        }
    }

    return $cat_nav;
}

//通过分类id，获取一个数组包含所有父级元素 by wu
function get_seller_category_array($cat_id = 0, $relation = 0, $self = true) {
    $cat_list[] = intval($cat_id);

    if ($relation == 0) {
        return $cat_list;
    } elseif ($relation == 1) {
        do {
            $sql = " select parent_id from " . $GLOBALS['ecs']->table('merchants_category') . " where cat_id='" . $cat_id . "' ";
            $parent_id = $GLOBALS['db']->getOne($sql);
            if (!empty($parent_id)) {
                $cat_list[] = $parent_id;
                $cat_id = $parent_id;
            }
        } while (!empty($parent_id));

        //删除自己
        if ($self == false) {
            unset($cat_list[0]);
        }
        $cat_list[] = 0;
        //去掉重复，主要是0
        return array_reverse(array_unique($cat_list));
    } elseif ($relation == 2) {

    }
}

//获取数组中分类信息 by wu
function get_seller_array_category_info($arr = array()) {
    if ($arr) {
        $sql = " SELECT cat_id, cat_name FROM " . $GLOBALS['ecs']->table('merchants_category') . " WHERE cat_id " . db_create_in($arr);
        return $GLOBALS['db']->getAll($sql);
    } else {
        return false;
    }
}

/**
 * 商家入驻分类
 */
function seller_shop_cat($user_id = 0) {
    $seller_shop_cat = '';
    if ($user_id) {
        $sql = "SELECT user_shopMain_category FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " WHERE user_id = '$user_id'";
        $seller_shop_cat = $GLOBALS['db']->getOne($sql, true);
    }

    $arr = array();
    $arr['parent'] = '';
    if ($seller_shop_cat) {
        $seller_shop_cat = explode("-", $seller_shop_cat);

        foreach ($seller_shop_cat as $key => $row) {
            if ($row) {
                $cat = explode(":", $row);
                $arr[$key]['cat_id'] = $cat[0];
                $arr[$key]['cat_tree'] = $cat[1];

                $arr['parent'] .= $cat[0] . ",";

                if ($cat[1]) {
                    $arr['parent'] .= $cat[1] . ",";
                }
            }
        }
    }

    $arr['parent'] = substr($arr['parent'], 0, -1);

    return $arr;
}

/**
 * 获得商品分类的所有信息
 *
 * @param   integer     $cat_id     指定的分类ID
 *
 * @return  mix
 */
function get_seller_cat_info($cat_id) {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('merchants_category') . " WHERE cat_id = '$cat_id' LIMIT 1";
    $row = $GLOBALS['db']->getRow($sql);

    if ($row) {
        $row['is_show_merchants'] = $row['is_show'];
    }

    return $row;
}

/**
 * 获取商品信息
 */
function get_admin_goods_info($goods_id = 0, $select = array()) {

    if ($select && is_array($select)) {
        $select = implode(",", $select);
    } else {
        $select = "*";
    }

    $sql = "SELECT $select FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id' LIMIT 1";
    $row = $GLOBALS['db']->getRow($sql);

    if ($row) {
        if (isset($row['user_cat']) && !empty($row['user_cat'])) {
            $cat_info = get_seller_cat_info($row['user_cat']);
            $row['user_cat_name'] = $cat_info['cat_name'];
        }

        if(isset($row['user_id'])){
            $row['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称
        }
    }

    return $row;
}

//给出cat_id,返回逐级分类 by wu
function get_every_category($cat_id = 0, $table = 'category') {
    $parent_cat_list = get_category_array($cat_id, 1, true, $table);
    $filter_category_navigation = get_array_category_info($parent_cat_list, $table);
    $cat_nav = "";
    if ($filter_category_navigation) {
        foreach ($filter_category_navigation as $key => $val) {
            if ($key == 0) {
                $cat_nav .= $val['cat_name'];
            } elseif ($key > 0) {
                $cat_nav .= " > " . $val['cat_name'];
            }
        }
    }

    return $cat_nav;
}

//通过分类id，获取一个数组包含所有父级元素 by wu
function get_category_array($cat_id = 0, $relation = 0, $self = true, $table = 'category') {
    $cat_list[] = intval($cat_id);

    if ($relation == 0) {
        return $cat_list;
    } elseif ($relation == 1) {
        do {
            $sql = " SELECT parent_id FROM " . $GLOBALS['ecs']->table($table) . " WHERE cat_id='$cat_id' ";
            $parent_id = $GLOBALS['db']->getOne($sql);
            if (!empty($parent_id)) {
                $cat_list[] = $parent_id;
                $cat_id = $parent_id;
            }
        } while (!empty($parent_id));

        //删除自己
        if ($self == false) {
            unset($cat_list[0]);
        }
        $cat_list[] = 0;
        //去掉重复，主要是0
        return array_reverse(array_unique($cat_list));
    } elseif ($relation == 2) {

    }
}

//获取数组中分类信息 by wu
function get_array_category_info($arr = array(), $table = 'category') {
    if ($arr) {
        $arr = get_del_str_comma($arr);
        $sql = " SELECT cat_id, cat_name FROM " . $GLOBALS['ecs']->table($table) . " WHERE cat_id " . db_create_in($arr);
        $category_list = $GLOBALS['db']->getAll($sql);

        foreach ($category_list as $key => $val) {
            $category_list[$key]['url'] = build_uri($table, array('cid' => $val['cat_id']), $val['cat_name']);
        }
        return $category_list;
    } else {
        return false;
    }
}

/**
 * 商品详情分类
 */
function get_add_edit_goods_cat_list($goods_id = 0, $cat_id = 0, $table = 'category', $sin_prefix = '', $user_id = 0, $seller_shop_cat = array()) {

    //关联商品
    if (empty($sin_prefix)) {
        $select_category_rel = '';
        $select_category_rel .= insert_select_category(0, 0, 0, 'cat_id1', 1, $table, $seller_shop_cat);
        $GLOBALS['smarty']->assign($sin_prefix . 'select_category_rel', $select_category_rel);
    }

    //配件
    if (empty($sin_prefix)) {
        $select_category_pak = '';
        $select_category_pak .= insert_select_category(0, 0, 0, 'cat_id2', 1, $table, $seller_shop_cat);
        $GLOBALS['smarty']->assign($sin_prefix . 'select_category_pak', $select_category_pak);
    }

    /**
     * 商品分类
     * 添加商品
     */
    if ($_REQUEST['act'] == 'add') {
        $select_category_html = '';

        if ($sin_prefix) {
            $select_category_html .= insert_seller_select_category(0, 0, 0, 'user_cat', 0, $table, array(), $user_id);
        } else {
            $select_category_html .= insert_select_category(0, 0, 0, 'cat_id', 0, $table, $seller_shop_cat);
        }

        $GLOBALS['smarty']->assign($sin_prefix . 'select_category_html', $select_category_html);
    }

    /**
     * 编辑商品
     */ else if ($_REQUEST['act'] == 'edit' || $_REQUEST['act'] == 'copy') {
        $goods = get_admin_goods_info($goods_id, array('cat_id', 'user_cat'));

        $select_category_html = '';

        if ($sin_prefix) {
            $parent_cat_list = get_seller_select_category($cat_id, 1, true, $user_id);
            $cat_id = $goods['user_cat'];
        } else {
            $parent_cat_list = get_select_category($cat_id, 1, true);
            $cat_id = $goods['cat_id'];
        }

        for ($i = 0; $i < count($parent_cat_list); $i++) {

            if ($sin_prefix) {
                $select_category_html .= insert_seller_select_category(pos($parent_cat_list), next($parent_cat_list), $i, 'user_cat', 0, $table, array(), $user_id);
            } else {
                $select_category_html .= insert_select_category(pos($parent_cat_list), next($parent_cat_list), $i, 'cat_id', 0, $table, $seller_shop_cat);
            }
        }
        $GLOBALS['smarty']->assign($sin_prefix . 'select_category_html', $select_category_html);
        $parent_and_rank = empty($cat_id) ? '0_0' : $cat_id . '_' . (count($parent_cat_list) - 2);
        $GLOBALS['smarty']->assign($sin_prefix . 'parent_and_rank', $parent_and_rank);
    }
}

/**
 * 会员信息
 */
function get_admin_user_info($id = 0) {
    $sql = 'SELECT u.user_id, u.email, u.user_name, u.user_money, u.mobile_phone, u.pay_points, nick_name' .
            ' FROM ' . $GLOBALS['ecs']->table('users') . ' AS u ' .
            " WHERE u.user_id = '$id'";
    return $GLOBALS['db']->getRow($sql);
}

/**
 * 查询当前商品属性
 */
function get_dialog_goods_attr_type($attr_id = 0, $goods_id = 0) {
    $sql = "SELECT goods_attr_id, attr_id, attr_value FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE attr_id = '$attr_id' AND goods_id = '$goods_id' ORDER BY attr_sort";
    $res = $GLOBALS['db']->getAll($sql);

    if ($res) {
        foreach ($res as $key => $row) {
            if ($goods_id) {
                $res[$key]['is_selected'] = 1;
            } else {
                $res[$key]['is_selected'] = 0;
            }
        }
    }

    return $res;
}

/**
 * 商家评分列表
 */
function seller_grade_list() {
    $sql = "SELECT user_id FROM " . $GLOBALS['ecs']->table('merchants_shop_information') . " WHERE merchants_audit = 1 ORDER BY user_id ASC";
    return $GLOBALS['db']->getAll($sql);
}

/**
 * 重新组合首字地区列表
 */
function get_pin_regions() {
    $arr = array();

    $letters = range('A', 'Z');
    $pin_regions = read_static_cache('pin_regions', '/data/sc_file/');

    if ($pin_regions !== false) {
        foreach ($letters as $key => $row) {
            foreach ($pin_regions as $pk => $prow) {
                if ($row == $prow['initial']) {
                    $arr[$row][$pk] = $prow;
                }
            }

            if ($arr[$row]) {
                $arr[$row] = get_array_sort($arr[$row], 'region_id');
            }
        }
    }

    ksort($arr);
    return $arr;
}

/**
 * 更新、删除商品属性
 */
function get_updel_goods_attr($goods_id = 0) {

    $admin_id = get_admin_id();

    if ($admin_id) {
        if ($goods_id) {
            $sql = "UPDATE " . $GLOBALS['ecs']->table('goods_attr') . " SET goods_id = '$goods_id' WHERE admin_id = '$admin_id' AND goods_id = 0";
        } else {
            $sql = "DELETE FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE admin_id = '$admin_id' AND goods_id = 0";
        }

        $GLOBALS['db']->query($sql);
    }
}

//获取商品的属性ID
function get_goods_attr_nameId($goods_id = 0, $attr_id = 0, $attr_value = '', $select = 'goods_attr_id', $type = 0) {

    if ($type == 1) {
        $sql = "SELECT $select FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE goods_id = '$goods_id' AND goods_attr_id = '$attr_id'";
    } else {
        $sql = "SELECT $select FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE goods_id = '$goods_id' AND attr_id = '$attr_id' " .
                " AND attr_value = '$attr_value'";
    }

    return $GLOBALS['db']->getOne($sql);
}

/* 商家地区 */

function get_seller_region($region = array(), $ru_id = 0) {

    /* 取得区域名 */
    if ($region) {
        $sql = "SELECT concat(IFNULL(p.region_name, ''), '', IFNULL(t.region_name, ''), '', IFNULL(d.region_name, '')) AS region " .
                "FROM " . $GLOBALS['ecs']->table('region') . " AS p, " .
                $GLOBALS['ecs']->table('region') . " AS t, " .
                $GLOBALS['ecs']->table('region') . " AS d " .
                "WHERE p.region_id = '" . $region['province'] . "' AND t.region_id = '" . $region['city'] . "' AND d.region_id = '" . $region['district'] . "'";
    } else {
        $sql = "SELECT concat(IFNULL(p.region_name, ''), '', IFNULL(t.region_name, ''), '', IFNULL(d.region_name, ''), '', IFNULL(s.region_name, '')) AS region " .
                "FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " AS ss " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS p ON ss.province = p.region_id " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS t ON ss.city = t.region_id " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS d ON ss.district = d.region_id " .
                "WHERE ss.ru_id = '$ru_id'";
    }

    return $GLOBALS['db']->getOne($sql);
}

/**
 * 删除商品复选属性ID
 */
function get_goods_unset_attr($goods_id = 0, $attr_arr = array()) {

    $arr = array();

    if ($attr_arr) {

        $where_select = array();

        if (empty($goods_id)) {
            $admin_id = get_admin_id();
            $where_select['admin_id'] = $admin_id;
        }

        $where_select['goods_id'] = $goods_id;

        foreach ($attr_arr AS $key => $row) {
            if ($row) {
                $where_select['attr_value'] = $row[0];
                $attr_info = get_goods_attr_id($where_select, array('ga.goods_id', 'ga.attr_value', 'a.attr_id', 'a.attr_type'), 2, 1);
                if ($attr_info && $row[0] == $attr_info['attr_value']) {
                    unset($row);
                } else {
                    $arr[$key] = $row;
                }
            }
        }
    }

    return $arr;
}

/**
 * 获取商品运费模板
 */
function get_goods_transport_info($tid, $table = 'goods_transport') {
    //获得商品的扩展信息by wang
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table) . " WHERE tid = '$tid'";
    return $GLOBALS['db']->getRow($sql);
}

/**
 * 获取商品扩展信息
 */
function get_goods_extend($goods_id) {
    //获得商品的扩展信息by wang
    $extend_sql = "SELECT * FROM " . $GLOBALS['ecs']->table('goods_extend') . " WHERE goods_id = '$goods_id'";
    return $GLOBALS['db']->getRow($extend_sql);
}

/**
 * 图库管理
 */
function get_goods_gallery_album($type = 0, $id = 0, $select = array(), $id_name = 'album_id', $order = '') {

    $where = 1;
    if ($id) {
        $where .= " AND $id_name = '$id'";
    }

    if ($select && is_array($select)) {
        $select = implode(",", $select);
    } else {
        $select = '*';
    }

    if ($type == 2) {
        $where .= " LIMIT 1";
    }

    $sql = "SELECT $select FROM" . $GLOBALS['ecs']->table('gallery_album') . " WHERE $where $order";

    if ($type == 1) {
        $album_list = $GLOBALS['db']->getAll($sql);

        if ($album_list) {
            foreach ($album_list as $key => $row) {
                $album_list[$key]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            }
        }
    } elseif ($type == 2) {
        $album_list = $GLOBALS['db']->getRow($sql);
    } else {
        $album_list = $GLOBALS['db']->getOne($sql, true);
    }

    return $album_list;
}

/**
 * 图库管理图片信息
 */
function gallery_pic_album($type = 0, $id = 0, $select = array(), $id_name = 'pic_id', $order = '') {
    $where = 1;
    if ($id) {
        $where .= " AND $id_name = '$id'";
    }

    if ($select && is_array($select)) {
        $select = implode(",", $select);
    } else {
        $select = '*';
    }

    if ($type == 2) {
        $where .= " LIMIT 1";
    }

    $sql = "SELECT $select FROM" . $GLOBALS['ecs']->table('pic_album') . " WHERE $where $order";

    if ($type == 1) {
        $pic_list = $GLOBALS['db']->getAll($sql);

        if ($pic_list) {
            foreach ($pic_list as $key => $row) {
                $pic_list[$key]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            }
        }
    } elseif ($type == 2) {
        $pic_list = $GLOBALS['db']->getRow($sql);
    } else {
        $pic_list = $GLOBALS['db']->getOne($sql, true);
    }

    return $pic_list;
}

/*
 * 处理商家后台的分页效果
 * param    array     $list   产品列表
 * param    string    $nowpage   当前页数
 * param    number    $show   显示分页数量 默认超过10页则显示前后共10页
 * return   array     $arr    分页数量
 */

function seller_page($list, $nowpage, $show = '10') {

    $arr = array();

    if ($show > $list['page_count']) {
        $show = $list['page_count']; //如显示分页条数大于总条数，则等于总条数
    }

    if ($show % 2 == 0) { //判断奇偶数
        $begin = $nowpage - ceil($show / 2);
        $end = $nowpage + floor($show / 2);
    } else {
        $begin = $nowpage - floor($show / 2);
        $end = $nowpage + ceil($show / 2);
    }

    if ($show > 1) {//显示分页数量大于1时做处理
        if ($nowpage > (ceil($show / 2) + 1) && $nowpage <= ($list['page_count'] - ceil($show / 2))) {
            for ($i = $begin; $i < $end; $i++) {
                $arr[$i] = $i;
            }
        } else {
            if ($nowpage > (ceil($show / 2) + 1) && $nowpage > ($list['page_count'] - ($show - 1))) {
                for ($i = $list['page_count'] - ($show - 1); $i <= $list['page_count']; $i++) {

                    $arr[$i] = $i;
                }
            } else {
                for ($i = 1; $i <= $show; $i++) {

                    $arr[$i] = $i;
                }
            }
        }
    } else {
        $arr[1] = 1;
    }

    return $arr;
}


/*
 * 获取选中的分类
 */
function get_choose_cat($ids) {
    $sql = " SELECT cat_id, cat_name FROM " . $GLOBALS['ecs']->table('category') . " WHERE cat_id " . db_create_in($ids);
    return $GLOBALS['db']->getAll($sql);
}

/*
 * 获取选中的商品
 */
function get_choose_goods($ids) {
    $sql = " SELECT goods_id, goods_name FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id " . db_create_in($ids);
    return $GLOBALS['db']->getAll($sql);
}

/**
 * 获取地区列表
 */
function get_areaRegion_info_list($ra_id) {

    if ($ra_id > 0) {
        $where_raId = " AND mr.ra_id = '$ra_id'";
    }

    $sql = "SELECT rw.region_id, rw.region_name FROM " . $GLOBALS['ecs']->table('merchants_region_info') . " AS mr " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS r ON mr.region_id = r.region_id" .
            " LEFT JOIN " . $GLOBALS['ecs']->table('region_warehouse') . " AS rw ON r.region_id = rw.regionId" .
            " where 1" . $where_raId;
    return $GLOBALS['db']->getAll($sql);
}

/*
 * 获取单条信息函数
 */
function get_table_info($table = '', $where = 1, $select = array()){

    $res = array();
    if($table){
        if($select && is_array($select)){
            $select = implode(",", $select);
        }else{
            $select = empty($select) ? "*" : $select;
        }

        $sql = "SELECT $select FROM " .$GLOBALS['ecs']->table($table). " WHERE $where LIMIT 1";
        $res = $GLOBALS['db']->getRow($sql);
    }

    return $res;
}

/* 微分销佣金金额 */
function get_order_drp_money($total_fee = 0, $ru_id = 0, $order_id = 0, $order = array()) {

    $should_amount = 0;
    $where = "";
    if ($order_id) {
        $where .= " AND oi.order_id = '$order_id'";
    }else{
        $where .= order_query_sql('confirm_take', 'oi.');
        $where .= " AND og.ru_id = '$ru_id'";
        $where .= " AND (SELECT count(*) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi2 WHERE oi2.main_order_id = oi.order_id LIMIT 1) = 0";  //主订单下有子订单时，则主订单不显示;
    }

    $res = array();
    $sql = "SELECT SUM(og.drp_money) AS drp_money FROM " . $GLOBALS['ecs']->table('order_info') . " as oi " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS og ON og.order_id = oi.order_id " .
            " WHERE 1 " . $where;

    $order_drp = $GLOBALS['db']->getRow($sql);

    if ($order_id) {
        /* 商品佣金比例 start */
        $goods_rate = get_alone_goods_rate($order_id, 0, $order);

        if ($goods_rate['rate_activity']) {
            $value['total_fee'] = $value['total_fee'] + $goods_rate['rate_activity'];
        }

        $res['rate_activity'] = $goods_rate['rate_activity'];

        /**
         * 扣除单独设置商品佣金比例的商品总金额
         */
        if ($goods_rate && $goods_rate['total_fee']) {
            $total_fee = $value['total_fee'] - $goods_rate['total_fee'];
        }

        $should_amount = $goods_rate['should_amount'];
        /* 商品佣金比例 end */
    }

    if($total_fee > 0){
        $res['total_fee'] = $total_fee - $order_drp['drp_money'];
    }else{
        $res['total_fee'] = 0;
    }

    $res['drp_money'] = $order_drp['drp_money'];
    $res['should_amount'] = $should_amount;

    return $res;
}
/**
 * 资金管理日志
 */
function get_seller_account_log() {

    $result = get_filter();
    if ($result === false) {
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'sal.log_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $filter['user_id'] = empty($_REQUEST['ru_id']) ? 0 : intval($_REQUEST['ru_id']);
        $adminru = get_admin_ru_id();
        $ex_where = ' WHERE mis.merchants_audit = 1';
        if($adminru['ru_id'] > 0){
            $ex_where .= ' AND sal.user_id="'.$adminru['ru_id'].'" ';
        }elseif($filter['user_id'] > 0){
            $ex_where .= ' AND sal.user_id="'.$filter['user_id'].'" ';
        }elseif($filter['keywords']){
            $sql = "SELECT user_id FROM".$GLOBALS['ecs']->table('users')." WHERE(user_name LIKE '%" . mysql_like_quote($filter['keywords']) ."%' OR nick_name LIKE '%" . mysql_like_quote($filter['keywords']) ."%') ";
            $user_id = $GLOBALS['db']->getOne($sql);
            $ex_where .= ' AND sal.user_id="'.$user_id.'" ';
        }
        //管理员查询的权限 -- 店铺查询 start
        $filter['store_search'] = empty($_REQUEST['store_search']) ? 0 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';

        $store_where = '';
        $store_search_where = '';
        if ($filter['store_search'] != 0) {
            if ($ru_id == 0) {

                if ($_REQUEST['store_type']) {
                    $store_search_where = "AND mis.shopNameSuffix = '" . $_REQUEST['store_type'] . "'";
                }

                if ($filter['store_search'] == 1) {
                    $ex_where .= " AND mis.user_id = '" . $filter['merchant_id'] . "' ";
                } elseif ($filter['store_search'] == 2) {
                    $store_where .= " AND mis.rz_shopName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%'";
                } elseif ($filter['store_search'] == 3) {
                    $store_where .= " AND mis.shoprz_brandName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%' " . $store_search_where;
                }

                if ($filter['store_search'] > 1) {
                    $ex_where .= " AND mis.user_id > 0 $store_where ";
                }
            }
        }
        //管理员查询的权限 -- 店铺查询 end

        $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('merchants_account_log') . " AS sal " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS mis ON sal.user_id = mis.user_id " .
                " $ex_where";

        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        /* 分页大小 */
        $filter = page_and_size($filter);

        $sql = "SELECT sal.* FROM " . $GLOBALS['ecs']->table('merchants_account_log') . " AS sal " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " AS mis ON sal.user_id = mis.user_id " .
                " $ex_where " .
                " ORDER BY " . $filter['sort_by'] . ' ' . $filter['sort_order'] .
                " LIMIT " . $filter['start'] . ',' . $filter['page_size'];

        $filter['keywords'] = stripslashes($filter['keywords']);
        set_filter($filter, $sql);
    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    for ($i = 0; $i < count($res); $i++) {
        $res[$i]['shop_name'] = get_shop_name($res[$i]['user_id'], 1);
        $res[$i]['change_time'] = local_date($GLOBALS['_CFG']['time_format'], $res[$i]['change_time']);
    }

    $arr = array('log_list' => $res, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

/**
 * 商品信息
 * 进入添加或编辑商品时
 * 删除goods_id为0的商品相关表信息
 * admin_id 管理员ID
 * $type 0 删除 1 更新
 */
function get_del_update_goods_null($goods_id = 0, $type = 0){

    $admin_id = get_admin_id();
    $table_list = array(
        'products', //商品货品表
        'products_warehouse', //商品仓库货品表
        'products_area', //商品地区货品表
        'goods_attr', //商品属性表
        'warehouse_attr', //商品仓库属性表
        'warehouse_area_attr', //商品地区属性表
    );

    foreach($table_list as $key=>$table){

        if($type){
            $other['goods_id'] = $goods_id;
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table($table), $other, 'UPDATE', "goods_id = 0 AND admin_id = '$admin_id'");
        }else{
            $sql = "DELETE FROM " . $GLOBALS['ecs']->table($table) . " WHERE goods_id = 0 AND admin_id = '$admin_id'";
            $GLOBALS['db']->query($sql);
        }
    }
}

/**
 * 编辑商品
 * 删除添加商品的图片
 */
function get_del_edit_goods_img($goods_id) {
    /* 如果有上传图片，删除原来的商品图 */
    $sql = "SELECT goods_thumb, goods_img, original_img " .
            " FROM " . $GLOBALS['ecs']->table('goods') .
            " WHERE goods_id = '$goods_id' LIMIT 1";

    $row = $GLOBALS['db']->getRow($sql);

    if ($row) {
        if ($result['data']['goods_thumb'] && $row['goods_thumb'] != $result['data']['goods_thumb'] && strpos($row['goods_thumb'], "data/gallery_album") === false) {
            dsc_unlink(ROOT_PATH . $row['goods_thumb']);
            $arr_img[] = $row['goods_thumb'];
        }

        if ($result['data']['goods_img'] && $row['goods_img'] != $result['data']['goods_img'] && strpos($row['goods_img'], "data/gallery_album") === false) {
            dsc_unlink(ROOT_PATH . $row['goods_img']);
            $arr_img[] = $row['goods_img'];
        }

        if ($result['data']['original_img'] && $row['original_img'] != $result['data']['original_img'] && strpos($k['original_img'], "data/gallery_album") === false) {
            dsc_unlink(ROOT_PATH . $row['original_img']);
            $arr_img[] = $row['original_img'];
        }

        get_oss_del_file($arr_img);
    }
}

/**
 * 删除添加商品的图片
 * 商品并没有保存
 */
function get_del_goodsimg_null() {

    $admin_id = get_admin_id();

    if (isset($_SESSION['goods'][$admin_id])) {

        foreach ($_SESSION['goods'][$admin_id] as $key => $row) {

            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods') .
                    " WHERE original_img = '" . $row['original_img'] . "' OR goods_img = '" . $row['goods_img'] . "' OR goods_thumb = '" . $row['goods_thumb'] . "'";
            $count = $GLOBALS['db']->getOne($sql);

            if ($key == 0 && !$count) {
                if ($row['original_img'] && strpos($row['original_img'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $row['original_img']);
                    $arr_img[] = $row['original_img'];
                }
                if ($row['goods_img'] && strpos($row['goods_img'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $row['goods_img']);
                    $arr_img[] = $row['goods_img'];
                }
                if ($row['goods_thumb'] && strpos($row['goods_thumb'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $row['goods_thumb']);
                    $arr_img[] = $row['goods_thumb'];
                }

                get_oss_del_file($arr_img);
            }
        }

        unset($_SESSION['goods'][$admin_id]);
    } else {
        if (isset($_SESSION['goods']) && empty($_SESSION['goods'])) {
            unset($_SESSION['goods']);
        }
    }
}

/**
 * 删除添加商品的相册图片
 * 商品并没有保存
 */
function get_del_goods_gallery(){

    $admin_id = get_admin_id();

    if (isset($_SESSION['thumb_img_id' . $admin_id]) && $_SESSION['thumb_img_id' . $admin_id]) {
        /* 删除未绑定图片 */
        $res = $GLOBALS['db']->getAll(" SELECT img_url,thumb_url,img_original FROM" . $GLOBALS['ecs']->table('goods_gallery') . " WHERE goods_id = 0 AND img_id" . db_create_in($_SESSION['thumb_img_id' . $admin_id]));

        //删除图片
        if (!empty($res)) {
            foreach ($res as $k) {
                if ($k['img_url'] && strpos($k['img_url'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $k['img_url']);
                    $arr_img[] =  $row['img_url'];
                }
                if ($k['thumb_url'] && strpos($k['thumb_url'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $k['thumb_url']);
                    $arr_img[] =  $row['thumb_url'];
                }
                if ($k['img_original'] && strpos($k['img_original'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $k['img_original']);
                    $arr_img[] =  $row['img_original'];
                }

                get_oss_del_file($arr_img);
            }
        }

        $GLOBALS['db']->query("DELETE FROM" . $GLOBALS['ecs']->table("goods_gallery") . " WHERE goods_id = 0 AND img_id" . db_create_in($_SESSION['thumb_img_id' . $admin_id]));
        unset($_SESSION['thumb_img_id' . $admin_id]); //清楚临时$_COOKIE
    }
}

/**
 * 检测商家或会员
 * 可用金额
 * 冻结金额
 * 消费积分
 * 等级积分
 * 是否满足条件
 */
function get_return_money($left_money = 0, $rigth_money = 0) {
    $money = $left_money;
    if ($left_money <= 0) {
        if (!(strpos($left_money, "-") === false)) {
            $new_frozen_money = substr($left_money, 1);
            if ($rigth_money) {
                if ($rigth_money <= 0 || $new_frozen_money > $rigth_money) {
                    $money = 0;
                }
            }
        }
    }

    return $money;
}

/**
 * 获得设置信息
 *
 * @param   array   $groups     需要获得的设置组
 *
 * @return  array
 */
function get_up_settings($groups = '') {
    global $db, $ecs, $_LANG;

    $where = " AND parent_id > 0";

    if(!empty($groups)){
        $where .= " AND shop_group = '$groups'";
    }

    /* 取出全部数据：分组和变量 */
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('shop_config') .
            " WHERE type<>'hidden' $where ORDER BY parent_id, sort_order, id";
    $item_list = $GLOBALS['db']->getAll($sql);

    /* 整理数据 */
    $code_arr = array('shop_logo', 'no_picture', 'watermark', 'shop_slagon', 'wap_logo', 'two_code_logo', 'ectouch_qrcode', 'ecjia_qrcode', 'index_down_logo', 'site_commitment', 'user_login_logo', 'login_logo_pic', 'business_logo');
    foreach ($item_list AS $key => $item) {

        $pid = $item['parent_id'];

        $item['name'] = isset($_LANG['cfg_name'][$item['code']]) ? $_LANG['cfg_name'][$item['code']] : $item['code'];
        $item['desc'] = isset($_LANG['cfg_desc'][$item['code']]) ? $_LANG['cfg_desc'][$item['code']] : '';

        if ($item['code'] == 'sms_shop_mobile') {
            $item['url'] = 1;
        }

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

        $group_list[] = $item;
    }

    return $group_list;
}
//获取指定数组的下级分类
function get_type_cat_arr($cat_id = 0, $type = 0, $arr = 0, $ru_id = ''){

    $adminru = get_admin_ru_id();
    $where = '';

    if ($cat_id == 0) {
        if (is_numeric($ru_id) && $GLOBALS['_CFG']['attr_set_up'] == 1) {
            $where = " AND user_id = '" . $ru_id . "'";
        } elseif ($ru_id == '' && $GLOBALS['_CFG']['attr_set_up'] == 1) {
            $where = " AND user_id = '" . $adminru['ru_id'] . "' ";
        } elseif ($GLOBALS['_CFG']['attr_set_up'] == 0) {
            $where = " AND user_id = 0 ";
        }
    }

    if($type == 2){
        $sql = "SELECT level,cat_id,parent_id FROM".$GLOBALS['ecs']->table('goods_type_cat')."WHERE cat_id = '$cat_id' $where LIMIT 1";
        $cat_list = $GLOBALS[db]->getRow($sql);
    }else{
        $sql = "SELECT cat_id ,cat_name ,level FROM".$GLOBALS['ecs']->table('goods_type_cat')."WHERE parent_id = '$cat_id' $where";
        $cat_list = $GLOBALS[db]->getAll($sql);
    }

    if($type == 1)
    {
        $cat_string = $cat_id.',';
        if(!empty($cat_list))
        {
            foreach($cat_list as $k=>$v){
                $cat_string .= get_type_cat_arr($v['cat_id'],1);
            }
        }

        if($arr == 1){
            $cat_string = substr($cat_string,0,strlen($cat_string)-1);

        }
        return $cat_string;
    }
    elseif($type == 2)
    {
        if($cat_list['parent_id'] > 0){
            $sql = "SELECT cat_id,parent_id,cat_name,sort_order,level FROM".$GLOBALS['ecs']->table('goods_type_cat')."WHERE parent_id = '".$cat_list['parent_id']."' $where";
            $cat_tree = $GLOBALS['db']->getAll($sql);
            return array('checked_id'=>$cat_list['parent_id'],'arr'=>$cat_tree);
        }else{
            return array('checked_id'=>$cat_id,'arr'=>'');
        }
    }
    else
    {
        return $cat_list;
    }
}
function getCatNun($cat_keys = array()){
    $adminru = get_admin_ru_id();
    $where = '';
    if($adminru['ru_id'] > 0){
        $where = " AND user_id = '".$adminru['ru_id']."'";
    }
    if(!empty($cat_keys))
    {
        $sql = "SELECT COUNT(*) FROM".$GLOBALS['ecs']->table('goods_type')."WHERE c_id in($cat_keys) $where";
        $count = $GLOBALS[db]->getOne($sql);
    }
    else
    {
        $count = 0;
    }
    return $count;
}
/**
 * 获得所有商品类型
 *
 * @access  public
 * @return  array
 */
function get_typecat($level = 1)
{
    $adminru = get_admin_ru_id();
    $result = get_filter();
    if ($result === false)
    {
        $filter['parent_id'] = empty($_REQUEST['parent_id']) ? 0 : intval($_REQUEST['parent_id']);
        $filter['level'] = $level;
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $where = " WHERE level =  '" .$filter['level']. "'";

		if($adminru['ru_id'] > 0 && $GLOBALS['_CFG']['attr_set_up'] == 1){
			$where .= " AND user_id = '".$adminru['ru_id']."'";
		}elseif($GLOBALS['_CFG']['attr_set_up'] == 0){
			$where .= " AND user_id = 0 ";
		}

        if ($filter['keywords'])
        {
            $where .= " AND cat_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' ";
        }
       if($filter['parent_id'] > 0)
       {
           $where .= " AND parent_id = '" .$filter['parent_id']. "' ";
       }
        /* 记录总数以及页数 */
        $sql = "SELECT COUNT(*) FROM".$GLOBALS['ecs']->table('goods_type_cat').$where." GROUP BY cat_id";
        $filter['record_count'] = count($GLOBALS['db']->getAll($sql));

        $filter = page_and_size($filter);

        /* 查询记录 */
        $sql = "SELECT cat_id,parent_id,cat_name,sort_order,user_id,level FROM".$GLOBALS['ecs']->table('goods_type_cat').$where." GROUP BY cat_id".
               ' LIMIT ' . $filter['start'] . ',' . $filter['page_size'];
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $all = $GLOBALS['db']->getAll($sql);

    foreach ($all AS $key=>$val)
    {
        $all[$key]['shop_name'] = get_shop_name($val['user_id'], 1);
        $all[$key]['parent_name'] = $GLOBALS[db]->getOne("SELECT cat_name FROM".$GLOBALS[ecs]->table('goods_type_cat')."WHERE cat_id = '" .$val['parent_id']. "'");//获取父级分类
        //获取分类下的所有子分类
        $cat_keys = get_type_cat_arr($val['cat_id'],1,1);
        $all[$key]['type_num'] = getCatNun($cat_keys);
    }
    return array('type' => $all, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

/**
 * 获得设置分组
 */
function get_setting_groups($groups = '') {
    global $_LANG;
    $group_list = array();
    $list = get_up_settings($groups);

    //将设置规组
    if ($list) {
        foreach ($list as $key => $val) {
            $group_list[$val['parent_id']]['vars'][] = $val;
        }
    }

    //补全组信息
    if ($group_list) {
        foreach ($group_list as $key => $val) {
            $data = get_table_date('shop_config', "id='$key'", array('*'));
            //处理数据
            $data['name'] = isset($_LANG['cfg_name'][$data['code']]) ? $_LANG['cfg_name'][$data['code']] : $data['code'];
            $data['desc'] = isset($_LANG['cfg_desc'][$data['code']]) ? $_LANG['cfg_desc'][$data['code']] : '';
            //合并数据
            $data = array_merge($data, $val);
            $group_list[$key] = $data;
        }
    }

    return $group_list;
}

/**
 * 获得店铺信息修改记录
 */
function get_seller_shopinfo_changelog($ru_id = 0){
	$diff_data = array();
	$changelog = get_table_date('seller_shopinfo_changelog', "ru_id='$ru_id'", array('data_key', 'data_value'), 1);
	foreach($changelog as $key=>$val){
		$diff_data[$val['data_key']] = $val['data_value'];
	}
	return $diff_data;
}
/**
 * wang 商家入驻 生成百度编辑器
 * @param   string  input_name  输入框名称
 * @param   string  input_value 输入框值
 * @param   string  $input_height 输入大小
 * @param   string  $type 输出方式  0、assign输出，1、return输出
 */
function create_ueditor_editor($input_name, $input_value = '',$input_height = 486 ,$type = 0)
{
    global $smarty;

    $FCKeditor = '<input type="hidden" id="'.$input_name.'" name="'.$input_name.'" value="'.htmlspecialchars($input_value).'" /><iframe id="'.$input_name.'_frame" src="../plugins/seller_ueditor/ecmobanEditor.php?item='.$input_name.'" width="100%" height="'.$input_height.'" frameborder="0" scrolling="no"></iframe>';

    if($type == 1){
        return $FCKeditor;
    }else{
        $smarty->assign('FCKeditor', $FCKeditor);
    }
}
/**
 * 获取商品仓库价格
 */
function get_goods_warehouse_area_list($goods_id = 0, $model = 0, $warehouse_id = 0){
    $result = get_filter();
    if ($result === false)
    {
        /* 过滤条件 */
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }

        $filter['goods_id'] = !isset($_REQUEST['goods_id']) ? $goods_id : intval($_REQUEST['goods_id']);
        $filter['warehouse_id'] = !isset($_REQUEST['warehouse_id']) ? $warehouse_id : intval($_REQUEST['warehouse_id']);
        $filter['model'] = !isset($_REQUEST['model']) ? $model : intval($_REQUEST['model']);
        $filter['region_sn'] = !isset($_REQUEST['region_sn']) ? '' : addslashes(trim($_REQUEST['region_sn']));

        $filter['sort_by']    = empty($_REQUEST['sort_by'])    ? ' rw.region_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC'     : trim($_REQUEST['sort_order']);

        $where = " 1 ";
        $sql_where = '';
        $select = "";
        $leftJion = "";
        if($filter['model'] == 1){
            $where .= " AND rw.region_type = 0";

            $leftJion = ", " . $GLOBALS['ecs']->table('warehouse_goods') . " AS wg ";
            $sql_where .= " AND rw.region_id = wg.region_id AND wg.goods_id = '" .$filter['goods_id']. "'";

            $select .= ", wg.w_id, wg.region_sn, wg.region_number, wg.warehouse_price, wg.warehouse_promote_price";

            if($filter['region_sn']){
                $where .= " AND wg.region_sn = '" .$filter['region_sn']. "'";
            }

        }elseif($filter['model'] == 2){

            if($filter['warehouse_id']){
                $where .= " AND rw.parent_id = '" .$filter['warehouse_id']. "'";
            }

            $where .= " AND rw.region_type = 1";
            $select .= ", (SELECT rw2.region_name FROM " .$GLOBALS['ecs']->table('region_warehouse'). " AS rw2 WHERE rw2.region_id = rw.parent_id) AS warehouse_name ";

            $leftJion = ", " . $GLOBALS['ecs']->table('warehouse_area_goods') . " AS wag ";
            $sql_where .= " AND rw.region_id = wag.region_id AND wag.goods_id = '" .$filter['goods_id']. "'";

            $select .= ", wag.a_id, wag.region_sn, wag.region_number, wag.region_price, wag.region_promote_price";

            if($filter['region_sn']){
                $where .= " AND wag.region_sn = '" .$filter['region_sn']. "'";
            }
        }

        $sql = "SELECT count(*) FROM " .$GLOBALS['ecs']->table('region_warehouse'). " AS rw " .
               $leftJion .
               " WHERE $where $sql_where";
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        /* 分页大小 */
        $filter = page_and_size($filter, 1);

        $sql = "SELECT rw.* $select FROM " .$GLOBALS['ecs']->table('region_warehouse') . " AS rw " .
               $leftJion .
               " WHERE $where $sql_where ORDER BY " . $filter['sort_by'] . ' ' . $filter['sort_order'] .
               " LIMIT " . $filter['start'] . ',' . $filter['page_size'];

        $filter['keywords'] = stripslashes($filter['keywords']);
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array('list' => $res, 'filter' => $filter,
        'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'], 'query' => "goods_wa_query");

    return $arr;
}

/**
 * 获取商品模式
 * model
 */
function get_goods_model($goods_id){
    $sql = "SELECT goods_id, goods_sn, model_attr, user_id FROM " .$GLOBALS['ecs']->table('goods'). " WHERE goods_id = '$goods_id' LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}
/**
 * 获取商品货品SKU列表
 */
function get_goods_product_list($goods_id = 0, $model = 0, $warehouse_id = 0, $area_id = 0, $is_pagging = true){
    $result = get_filter();
    if ($result === false)
    {
        /* 过滤条件 */
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }

        $filter['product_sn'] = !isset($_REQUEST['product_sn']) ? '' : addslashes(trim($_REQUEST['product_sn']));
        $filter['goods_id'] = !isset($_REQUEST['goods_id']) ? $goods_id : intval($_REQUEST['goods_id']);
        $filter['model'] = !isset($_REQUEST['model']) ? $model : intval($_REQUEST['model']);

        $filter['sort_by']    = empty($_REQUEST['sort_by'])    ? 'product_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC'     : trim($_REQUEST['sort_order']);

        $where = "";
        if($filter['model'] == 1){
            $filter['warehouse_id'] = !isset($_REQUEST['warehouse_id']) ? $warehouse_id : intval($_REQUEST['warehouse_id']);
            $table = "products_warehouse";
            $where .= " AND warehouse_id = '" .$filter['warehouse_id']. "'";
        }elseif($filter['model'] == 2){
            $filter['area_id'] = !isset($_REQUEST['area_id']) ? $area_id : intval($_REQUEST['area_id']);
            $table = "products_area";
            $where .= " AND area_id = '" .$filter['area_id']. "'";
        }else{
            $table = "products";
        }

        if($filter['product_sn']){
            $where .= " AND product_sn = '" .$filter['product_sn']. "'";
        }

        $sql = "SELECT count(*) FROM " .$GLOBALS['ecs']->table($table). " WHERE goods_id = '" .$filter['goods_id']. "' $where";
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        /* 分页大小 */
        $filter = page_and_size($filter, 1);

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table). " WHERE goods_id = '" .$filter['goods_id']. "' $where ORDER BY " . $filter['sort_by'] . ' ' . $filter['sort_order'] ;
        if($is_pagging){
			$sql .= " LIMIT " . $filter['start'] . ',' . $filter['page_size'];
		}

        $filter['keywords'] = stripslashes($filter['keywords']);
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->getAll($sql);

    for($i=0; $i<count($res); $i++){
        $goods_attr_id = str_replace('|', ',', $res[$i]['goods_attr']);
        $sql = "SELECT attr_value FROM " .$GLOBALS['ecs']->table('goods_attr') . " WHERE goods_id = '" .$res[$i]['goods_id']. "' AND goods_attr_id IN($goods_attr_id)";
        $attr_value = $GLOBALS['db']->getAll($sql);
        $res[$i]['attr_value'] = get_goods_attr_value($attr_value);
    }

    if($is_pagging){
		$arr = array('product_list' => $res, 'filter' => $filter,
			'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'], 'query' => "sku_query");
	}else{
		return $res;
	}

    return $arr;
}
function get_goods_attr_value($attr_value){

    $str = "";
    if($attr_value){
        foreach($attr_value as $key=>$val){
            $str .= "【" . $val['attr_value'] . "】";
        }
    }

    return $str;
}
/**
 * 检查图片网址是否合法
 *
 * @param string $url 网址
 *
 * @return boolean
 */
function goods_parse_url($url) {
    $parse_url = @parse_url($url);
    return (!empty($parse_url['scheme']) && !empty($parse_url['host']));
}

/* 获取区域商品 */

function get_area_goods($goods_id) {
    $sql = "select rw.region_id, rw.region_name from " . $GLOBALS['ecs']->table('link_area_goods') . " as lag" .
            " left join " . $GLOBALS['ecs']->table('region_warehouse') . " as rw on lag.region_id = rw.region_id" .
            " where lag.goods_id = '$goods_id'";

    return $GLOBALS['db']->getAll($sql);
}
/**
 * 数组合并
 *
 * @param array $array1 数组1
 * @param array $array2 数组2
 *
 * @return array
 */
function my_array_merge($array1, $array2)
{
    $new_array = $array1;
    foreach ($array2 as $key => $val)
    {
        $new_array[$key] = $val;
    }
    return $new_array;
}
/* 获取管理员操作记录 */
function get_goods_change_logs($goods_id)
{
    include_once(ROOT_PATH . 'includes/lib_order.php');

     /* 过滤条件 */
    $result = get_filter();

    if ($result === false)
    {
        $filter['keyword']          = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }

        $filter['start_time']      = empty($_REQUEST['start_time']) ? '' : trim($_REQUEST['start_time']);
        $filter['end_time']      = empty($_REQUEST['end_time']) ? '' : trim($_REQUEST['end_time']);

        $filter['sort_by']      = empty($_REQUEST['sort_by']) ? 'gcl.log_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order']   = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
		$filter['goodsId']   = empty($_REQUEST['goodsId']) ? 0 : intval($_REQUEST['goodsId']);

        $filter['operation_type'] = !isset($_REQUEST['operation_type']) ? -1 : intval($_REQUEST['operation_type']);

        //查询条件
        $where = " WHERE 1 ";

        /* 商品ID */
        if (!empty($goods_id))
        {
            $where .= " AND gcl.goods_id = $goods_id ";
		}
		else
		{
			return false;
		}

        /* 关键字 */
        if (!empty($filter['keyword']))
        {
            $where .= " AND g.goods_name LIKE '%" . mysql_like_quote($filter['keyword']) . "%'";
		}

        /* 操作时间 */
        if (!empty($filter['start_time']) || !empty($filter['end_time']))
        {
            $filter['start_time'] = local_strtotime($filter['start_time']);
            $filter['end_time'] = local_strtotime($filter['end_time']);
            $where .= " AND gcl.handle_time > '" .$filter['start_time']. "' AND gcl.handle_time < '" .$filter['end_time']. "'";
	}

        /* 获得总记录数据 */
        $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('goods_change_log') ." as gcl " .
                " LEFT JOIN " .$GLOBALS['ecs']->table('goods') ." as g ON gcl.goods_id = g.goods_id".
                $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $filter = page_and_size($filter);

        /* 获取管理员日志记录 */
        $list = array();
        $sql  = 'SELECT gcl.*, au.user_name AS admin_name FROM ' .$GLOBALS['ecs']->table('goods_change_log') . " as gcl ".
                " LEFT JOIN " . $GLOBALS['ecs']->table('goods') ." as g ON gcl.goods_id = g.goods_id".
				" LEFT JOIN " . $GLOBALS['ecs']->table('admin_user') ." as au ON gcl.user_id = au.user_id ".
                $where . ' GROUP BY gcl.log_id ORDER by '.$filter['sort_by'].' '.$filter['sort_order'];
        $res  = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

        $filter['keyword'] = stripslashes($filter['keyword']);
        set_filter($filter, $sql, $param_str);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
        $rows['shop_price'] = price_format($rows['shop_price']);
        $rows['shipping_fee'] = price_format($rows['shipping_fee']);
		$rows['handle_time'] = local_date($GLOBALS['_CFG']['time_format'], $rows['handle_time']);

        $list[] = $rows;
    }
    return array('list' => $list, 'filter' => $filter, 'page_count' =>  $filter['page_count'], 'record_count' => $filter['record_count']);
}

function is_distribution($ru_id) {

    $field = get_table_file_name($GLOBALS['ecs']->table('merchants_steps_fields'), 'is_distribution');
    if ($field['bool']) {
        $sql = " SELECT is_distribution FROM " . $GLOBALS['ecs']->table('merchants_steps_fields') . " WHERE user_id = '$ru_id' ";
        $one = $GLOBALS['db']->getOne($sql);
        if ($one == '是') {
            return true;
        } else {
            return false;
        }
    }else{
        return false;
    }
}
//ecmoban模板堂 --zhuo end

function is_mer($goods_id) {
    $sql = " SELECT user_id FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id' ";
    $one = $GLOBALS['db']->getOne($sql, true);
    if ($one == 0) {
        return false;
    } else {
        return $one;
    }
}

//ecmoban模板堂 --zhuo start 区域地区
function get_areaRegion_list(){
	$sql = "select ra_id, ra_name from " .$GLOBALS['ecs']->table('merchants_region_area'). " where 1 order by ra_sort asc";
	$res = $GLOBALS['db']->getAll($sql);

	$arr = array();
	foreach($res as $key=>$row){
		$arr[$key]['ra_id'] = $row['ra_id'];
		$arr[$key]['ra_name'] = $row['ra_name'];
		$arr[$key]['area'] = get_areaRegion_info_list($row['ra_id']);
	}

	return $arr;
}
/**
 * 修改商品库存
 * @param   string  $goods_id   商品编号，可以为多个，用 ',' 隔开
 * @param   string  $value      字段值
 * @return  bool
 */
function update_goods_stock($goods_id, $value, $warehouse_id = 0)
{
    if ($goods_id)
    {
        /* $res = $goods_number - $old_product_number + $product_number; */
        $sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') . "
                SET region_number = region_number + $value,
                    last_update = '". gmtime() ."'
                WHERE goods_id = '$goods_id' and region_id = '$warehouse_id'";
        $result = $GLOBALS['db']->query($sql);

        /* 清除缓存 */
        clear_cache_files();

        return $result;
    }
    else
    {
        return false;
    }
}
/**
 * 保存某商品的优惠价格
 * @param   int     $goods_id    商品编号
 * @param   array   $number_list 优惠数量列表
 * @param   array   $price_list  价格列表
 * @return  void
 */
function handle_volume_price($goods_id, $is_volume, $number_list, $price_list, $id_list) {
    if ($is_volume) {
        /* 循环处理每个优惠价格 */
        foreach ($price_list AS $key => $price) {
            /* 价格对应的数量上下限 */
            $volume_number = $number_list[$key];
            $volume_id = isset($id_list[$key]) && !empty($id_list[$key]) ? $id_list[$key] : 0;

            if (!empty($price)) {
                if ($volume_id) {
                    $sql = "SELECT id FROM " . $GLOBALS['ecs']->table('volume_price') . " WHERE goods_id = '$goods_id' AND (volume_price = '$price' OR volume_number = '$volume_number')";
                    if ($GLOBALS['db']->getOne($sql)) {
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('volume_price') . " SET volume_number = '$volume_number', volume_price = '$price' WHERE id = '$volume_id'";
                        $GLOBALS['db']->query($sql);
                    }
                } else {

                    $sql = "SELECT id FROM " . $GLOBALS['ecs']->table('volume_price') . " WHERE goods_id = '$goods_id' AND (volume_price = '$price' OR volume_number = '$volume_number')";
                    if (!$GLOBALS['db']->getOne($sql)) {
                        $sql = "INSERT INTO " . $GLOBALS['ecs']->table('volume_price') .
                                " (price_type, goods_id, volume_number, volume_price) " .
                                "VALUES ('1', '$goods_id', '$volume_number', '$price')";
                        $GLOBALS['db']->query($sql);
                    }
                }
            }
        }
    } else {
        $sql = "DELETE FROM " . $GLOBALS['ecs']->table('volume_price') .
                " WHERE price_type = '1' AND goods_id = '$goods_id'";
        $GLOBALS['db']->query($sql);
    }
}

/*获取订单store_id*/
function get_store_id($order_id=0){
    $sql = "SELECT store_id FROM".$GLOBALS['ecs']->table("store_order")." WHERE order_id = '".$order_id."'";
    return $GLOBALS['db']->getOne($sql);
}
// 用户退货时  退回赠送积分  by kong  start
function return_integral_rank($ret_id=0,$user_id=0,$order_sn=0,$rec_id=0,$refound_pay_points=0){
    $sql=" SELECT IF(g.give_integral != -1,g.give_integral*o.return_number,org.goods_price*o.return_number) as give_integral , IF(g.rank_integral != -1,g.rank_integral*o.return_number,org.goods_price*o.return_number) as rank_integral FROM ".$GLOBALS['ecs']->table("goods")." AS g "
            . "LEFT JOIN ".$GLOBALS['ecs']->table('order_return')." AS ord ON ord.goods_id = g.goods_id "
             . "LEFT JOIN ".$GLOBALS['ecs']->table('order_goods')." AS org ON org.rec_id = '$rec_id' "
            . "LEFT JOIN ".$GLOBALS['ecs']->table("order_return_extend")." AS o ON o.ret_id = ord.ret_id  WHERE o.ret_id = '$ret_id'" ;
    $return_integral=$GLOBALS['db']->getRow($sql);
//    $gave_custom_points=$return_integral['give_integral']-$refound_pay_points;// by kong 计算赠送积分
//    if($gave_custom_points < 0){
//        $gave_custom_points=0;
//    }
    $gave_custom_points = $return_integral['give_integral'];
    if(!empty($return_integral)){
        log_account_change($user_id, 0, 0, "-".$return_integral['rank_integral'], "-".$gave_custom_points, sprintf($GLOBALS['_LANG']['return_order_gift_integral'], $order_sn), ACT_OTHER, 1);
        return;
    }
}
// 用户退货时  退回赠送积分  by kong  end

function get_warehouse_area_goods($warehouse_id, $goods_id, $table){
    $sql = "SELECT region_number FROM " .$GLOBALS['ecs']->table($table). " WHERE region_id = '$warehouse_id' AND goods_id = '$goods_id'";
    return $GLOBALS['db']->getOne($sql);
}

/**
 * 编辑商品
 * 删除添加商品的图片
 */
function lib_get_del_edit_goods_img($goods_id) {
    /* 如果有上传图片，删除原来的商品图 */
    $sql = "SELECT goods_thumb, goods_img, original_img " .
            " FROM " . $GLOBALS['ecs']->table('goods_lib') .
            " WHERE goods_id = '$goods_id' LIMIT 1";

    $row = $GLOBALS['db']->getRow($sql);

    if ($row) {
        if ($result['data']['goods_thumb'] && $row['goods_thumb'] != $result['data']['goods_thumb'] && strpos($row['goods_thumb'], "data/gallery_album") === false) {
            dsc_unlink(ROOT_PATH . $row['goods_thumb']);
            $arr_img[] = $row['goods_thumb'];
        }

        if ($result['data']['goods_img'] && $row['goods_img'] != $result['data']['goods_img'] && strpos($row['goods_img'], "data/gallery_album") === false) {
            dsc_unlink(ROOT_PATH . $row['goods_img']);
            $arr_img[] = $row['goods_img'];
        }

        if ($result['data']['original_img'] && $row['original_img'] != $result['data']['original_img'] && strpos($k['original_img'], "data/gallery_album") === false) {
            dsc_unlink(ROOT_PATH . $row['original_img']);
            $arr_img[] = $row['original_img'];
        }

        get_oss_del_file($arr_img);
    }
}

/**
 * 删除添加商品的图片
 * 商品并没有保存
 */
function lib_get_del_goodsimg_null() {

    $admin_id = get_admin_id();

    if (isset($_SESSION['goods_lib'][$admin_id])) {

        foreach ($_SESSION['goods_lib'][$admin_id] as $key => $row) {

            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods_lib') .
                    " WHERE original_img = '" . $row['original_img'] . "' OR goods_img = '" . $row['goods_img'] . "' OR goods_thumb = '" . $row['goods_thumb'] . "'";
            $count = $GLOBALS['db']->getOne($sql);

            if ($key == 0 && !$count) {
                if ($row['original_img'] && strpos($row['original_img'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $row['original_img']);
                    $arr_img[] = $row['original_img'];
                }
                if ($row['goods_img'] && strpos($row['goods_img'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $row['goods_img']);
                    $arr_img[] = $row['goods_img'];
                }
                if ($row['goods_thumb'] && strpos($row['goods_thumb'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $row['goods_thumb']);
                    $arr_img[] = $row['goods_thumb'];
                }

                get_oss_del_file($arr_img);
            }
        }

        unset($_SESSION['goods_lib'][$admin_id]);
    } else {
        if (isset($_SESSION['goods_lib']) && empty($_SESSION['goods_lib'])) {
            unset($_SESSION['goods_lib']);
        }
    }
}

/**
 * 删除添加商品的相册图片
 * 商品并没有保存
 */
function lib_get_del_goods_gallery(){

    $admin_id = get_admin_id();

    if (isset($_SESSION['thumb_img_id' . $_SESSION['admin_id']]) && $_SESSION['thumb_img_id' . $admin_id]) {
        /* 删除未绑定图片 */
        $res = $GLOBALS['db']->getAll(" SELECT img_url,thumb_url,img_original FROM" . $GLOBALS['ecs']->table('goods_lib_gallery') . " WHERE goods_id = 0 AND img_id" . db_create_in($_SESSION['thumb_img_id' . $admin_id]));

        //删除图片
        if (!empty($res)) {
            foreach ($res as $k) {
                if ($k['img_url'] && strpos($k['img_url'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $k['img_url']);
                    $arr_img[] =  $row['img_url'];
                }
                if ($k['thumb_url'] && strpos($k['thumb_url'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $k['thumb_url']);
                    $arr_img[] =  $row['thumb_url'];
                }
                if ($k['img_original'] && strpos($k['img_original'], "data/gallery_album") === false) {
                    dsc_unlink(ROOT_PATH . $k['img_original']);
                    $arr_img[] =  $row['img_original'];
                }

                get_oss_del_file($arr_img);
            }
        }

        $GLOBALS['db']->query("DELETE FROM" . $GLOBALS['ecs']->table("goods_lib_gallery") . " WHERE goods_id = 0 AND img_id" . db_create_in($_SESSION['thumb_img_id' . $admin_id]));
        unset($_SESSION['thumb_img_id' . $admin_id]); //清楚临时$_COOKIE
    }
}

/**
 * 获得指定相册下的子相册的数组
 *
 * @access  public
 * @param   int     $album_id     分类的ID
 * @param   int     $selected   当前选中分类的ID
 * @param   boolean $re_type    返回的类型: 值为真时返回下拉列表,否则返回数组
 * @param   int     $level      限定返回的级数。为0时返回所有级数
 * @param   int     $is_show_all 如果为true显示所有分类，如果为false隐藏不可见分类。
 * @return  mix
 */
function gallery_cat_list($album_id = 0, $selected = 0, $re_type = true, $level = 0, $is_show_all = true,$seller_id = 0) {

    static $res = NULL;
    $adminru = get_admin_ru_id();
    $where = "WHERE 1 ";

    if ($res === NULL) {
        if($adminru['ru_id'] > 0){
            $seller_id = $adminru['ru_id'];
        }
        $where .= " AND g.ru_id='".$seller_id."'";
		$sql = "SELECT g.album_id, g.album_mame, g.parent_album_id, g.sort_order, COUNT(a.parent_album_id) AS has_children " .
				'FROM ' . $GLOBALS['ecs']->table('gallery_album') . " AS g " .
				"LEFT JOIN " . $GLOBALS['ecs']->table('gallery_album') . " AS a ON a.parent_album_id = g.album_id " .
                                 $where.
				"GROUP BY g.album_id " .
				'ORDER BY g.parent_album_id, g.sort_order ASC';
		$res = $GLOBALS['db']->getAll($sql);
    }
    if (empty($res) == true) {
        return $re_type ? '' : array();
    }

    $options = gallery_cat_options($album_id, $res); // 获得指定分类下的子分类的数组

    $children_level = 99999; //大于这个分类的将被删除
    if ($is_show_all == false) {
        foreach ($options as $key => $val) {
            if ($val['level'] > $children_level) {
                unset($options[$key]);
            } else {

                $children_level = 99999; //恢复初始值

            }
        }
    }

    /* 截取到指定的缩减级别 */
    if ($level > 0) {
        if ($album_id == 0) {
            $end_level = $level;
        } else {
            $first_item = reset($options); // 获取第一个元素
            $end_level = $first_item['level'] + $level;
        }

        /* 保留level小于end_level的部分 */
        foreach ($options AS $key => $val) {
            if ($val['level'] >= $end_level) {
                unset($options[$key]);
            }
        }
    }

    if ($re_type == true) {
        $select = '';
        foreach ($options AS $var) {
            $select .= '<option value="' . $var['album_id'] . '" ';
            $select .= ($selected == $var['album_id']) ? "selected='ture'" : '';
            $select .= '>';
            if ($var['level'] > 0) {
                $select .= str_repeat('&nbsp;', $var['level'] * 4);
            }
            $select .= htmlspecialchars(addslashes($var['cat_name']), ENT_QUOTES) . '</option>';
        }

        return $select;
    } else {
        return $options;
    }
}

/**
 * 过滤和排序所有相册，返回一个带有缩进级别的数组
 *
 * @access  private
 * @param   int     $spec_cat_id     上级分类ID
 * @param   array   $arr        含有所有分类的数组
 * @param   int     $level      级别
 * @return  void
 */
function gallery_cat_options($spec_cat_id, $arr) {
    static $cat_options = array();

    if (isset($cat_options[$spec_cat_id])) {
        return $cat_options[$spec_cat_id];
    }
    $i = 0;

    if (!isset($cat_options[0])) {
        $level = $last_cat_id = 0;
        $options = $cat_id_array = $level_array = array();
            while (!empty($arr)) {

                foreach ($arr AS $key => $value) {
                    $album_id = $value['album_id'];
                    if ($level == 0 && $last_cat_id == 0) {
                        if ($value['parent_album_id'] > 0) {
                            break;
                        }

                        $options[$album_id] = $value;
                        $options[$album_id]['level'] = $level;
                        $options[$album_id]['id'] = $album_id;
                        $options[$album_id]['name'] = $value['album_mame'];
                        unset($arr[$key]);

                        if ($value['has_children'] == 0) {
                            continue;
                        }
                        $last_cat_id = $album_id;
                        $cat_id_array = array($album_id);
                        $level_array[$last_cat_id] = ++$level;
                        continue;
                    }

                    if ($value['parent_album_id'] == $last_cat_id) {
                        $options[$album_id] = $value;
                        $options[$album_id]['level'] = $level;
                        $options[$album_id]['id'] = $album_id;
                        $options[$album_id]['name'] = $value['album_mame'];
                        unset($arr[$key]);

                        if ($value['has_children'] > 0) {
                            if (end($cat_id_array) != $last_cat_id) {
                                $cat_id_array[] = $last_cat_id;
                            }
                            $last_cat_id = $album_id;
                            $cat_id_array[] = $album_id;
                            $level_array[$last_cat_id] = ++$level;
                        }
                    } elseif ($value['parent_album_id'] > $last_cat_id) {
                        break;
                    }
                }

                $count = count($cat_id_array);
                if ($count > 1) {
                    $last_cat_id = array_pop($cat_id_array);
                } elseif ($count == 1) {
                    if ($last_cat_id != end($cat_id_array)) {
                        $last_cat_id = end($cat_id_array);
                    } else {
                        $level = 0;
                        $last_cat_id = 0;
                        $cat_id_array = array();
                        continue;
                    }
                }

                if ($last_cat_id && isset($level_array[$last_cat_id])) {
                    $level = $level_array[$last_cat_id];
                } else {
                    $level = 0;
                }

            }
        $cat_options[0] = $options;
    } else {
        $options = $cat_options[0];
    }

    if (!$spec_cat_id) {
        return $options;
    } else {
        if (empty($options[$spec_cat_id])) {
            return array();
        }

        $spec_cat_id_level = $options[$spec_cat_id]['level'];

        foreach ($options AS $key => $value) {
            if ($key != $spec_cat_id) {
                unset($options[$key]);
            } else {
                break;
            }
        }

        $spec_cat_id_array = array();
        foreach ($options AS $key => $value) {
            if (($spec_cat_id_level == $value['level'] && $value['cat_id'] != $spec_cat_id) ||
                    ($spec_cat_id_level > $value['level'])) {
                continue;
            } else {
                $spec_cat_id_array[$key] = $value;
            }
        }
        $cat_options[$spec_cat_id] = $spec_cat_id_array;
        return $spec_cat_id_array;
    }
}

//获取子相册
function gallery_child_cat_list($parent_id) {
    $adminru = get_admin_ru_id();
    $where = "";
    if ($adminru['ru_id'] > 0) {
        $where = " AND ru_id = '" . $adminru['ru_id'] . "' ";
    }
    $sql = "SELECT album_id, album_mame, parent_album_id, sort_order, album_cover, album_desc, add_time FROM" .
            $GLOBALS['ecs']->table('gallery_album') .
            " WHERE parent_album_id = '$parent_id' " . $where .
            "ORDER BY sort_order ASC";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $k => $v) {
        if (isset($v['album_cover']) && $v['album_cover']) {
            $res[$k]['album_cover'] = get_image_path($v['album_id'], $v['album_cover']);
        }

        if (!empty($res[$k]['album_cover']) && (strpos($res[$k]['album_cover'], 'http://') === false && strpos($res[$k]['album_cover'], 'https://') === false)) {
            $res[$k]['album_cover'] = $GLOBALS['ecs']->url() . $res[$k]['album_cover'];
        }
        $res[$k]['album_mame'] = $v['album_mame'];
        $res[$k]['album_id'] = $v['album_id'];
    }
    return $res;
}

//获取相册数量
function gallery_child_cat_num($parent_id) {
    $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('gallery_album') ." WHERE parent_album_id = '$parent_id'";
    return $GLOBALS['db']->getOne($sql);
}

/**
 * 导出csv文件
 * $data         array     欲导出数组
 * $filename     string    文件名
 * $prev         array     文档头部信息
 * $next         array     文档底部信息
 *
 */
function export_csv_pro($data = array(), $filename = "", $prev = array(), $next = array()) {
    if (empty($filename)) {
        $filename = date("YmdHis") . ".csv";
    } else {
        $filename = $filename . ".csv";
    }
    if (!empty($prev)) {
        $prev = implode(",", $prev) . "\n";
    } else {
        $prev = "";
    }
    if (!empty($next)) {
        $next = implode(",", $next) . "\n";
    } else {
        $next = "";
    }
    //头部设置
    header("Content-type:text/csv");
    header("Content-Disposition:attachment;filename=" . $filename);
    header("Cache-Control:must-revalidate,post-check=0,pre-check=0");
    header("Expires:0");
    header("Pragma:public");
    //处理数组数据
    if (empty($data)) {
        echo idel("没有符合您要求的数据！^_^");
    } else {
        $result = "";
        foreach ($data as $key => $value) {
            //处理数组内的','
            foreach ($value as $abc => $cba) {
                $value[$abc] = idel(str_replace(",", "", $cba));
            }
            $result.=implode(",", $value) . "\n";
        }
        echo $prev . $result . $next;
    }
}

//过滤字符串相关标签
function idel($strInput) {
    $strInput = strip_tags($strInput);
    return $strInput;
}

//快递鸟、电子面单：打印订单信息
function get_print_order_info($order_id = 0,$order_type='order') {
    require_once(ROOT_PATH . 'includes/lib_order.php');
    require_once(ROOT_PATH . 'includes/lib_goods.php');
	require_once(ROOT_PATH . 'includes/lib_wholesale.php');
	if($order_type == 'order'){
		//订单信息
		$order_info = order_info($order_id);
		//商品数据
		$goods_list = order_goods($order_id);
	}else{
		//订单信息
		$order_info = wholesale_order_info($order_id);
		//商品数据
		$goods_list = get_wholesale_order_goods_toInfo($order_id);
	}


    if (isset($order_info['order_id'])) {
        /* 收货地址 */
		if($order_type == 'order'){
			$order_info['complete_address'] = get_goods_region_name($order_info['province']) . '-' .
			get_goods_region_name($order_info['city']) . '-' .
			get_goods_region_name($order_info['district']) . ' ' .
			$order_info['address'];
		}else{
			$order_info['complete_address'] = $order_info['address'];
		}


        $number_amount = 0;
        if ($goods_list) {
            foreach ($goods_list as $key => $val) {
                $goods_list[$key]['format_goods_price'] = price_format($val['goods_price']);
                $goods_list[$key]['format_goods_amount'] = price_format($val['goods_price'] * $val['goods_number']);

                $products = get_warehouse_id_attr_number($val['goods_id'], $val['goods_attr_id'], $val['ru_id'], $val['warehouse_id'], $val['area_id'], $val['model_attr']);

                if($products && $products['bar_code']){
                    $bar_code = $products['bar_code'];
                }else{
                    $bar_code = get_table_date('goods', "goods_id = '" .$val['goods_id']. "'", array('bar_code'), 2);
                }

                $goods_list[$key]['bar_code'] = $bar_code;
                $number_amount += $val['goods_number'];
            }
        }
        $order_info['number_amount'] = $number_amount;
        $order_info['goods_list'] = $goods_list;
    }

    return $order_info;
}

//快递鸟、电子面单：获取打印内容
function get_kdniao_print_content($order_id = 0, $shipping_spec = array(), $shipping_info = array()) {
    $order = order_info($order_id);
    $shopinfo = get_table_date('seller_shopinfo', "ru_id='{$admin['ru_id']}'", array('*'));

    //补充数据
    $Goodsquantity = 0;
    $GoodsWeight = 0;
    $order_goods = order_goods($order_id);
    foreach ($order_goods as $goods) {
        $Goodsquantity += $goods['goods_number'];
        $GoodsWeight += $goods['goodsweight'];
    }

    //构造电子面单提交信息
    $eorder = [];
    $eorder["ShipperCode"] = isset($shipping_spec['kdniao_code']) ? $shipping_spec['kdniao_code'] : '';
    $eorder["OrderCode"] = $order['order_sn'];
    $eorder["PayType"] = 1;
    $eorder["ExpType"] = 1;
    $eorder["IsReturnPrintTemplate"] = 1;
    $eorder["CustomerName"] = isset($shipping_info['customer_name']) ? $shipping_info['customer_name'] : ''; //帐号
    $eorder["CustomerPwd"] = isset($shipping_info['customer_pwd']) ? $shipping_info['customer_pwd'] : ''; //密码
    $eorder["MonthCode"] = isset($shipping_info['month_code']) ? $shipping_info['month_code'] : ''; //月结编号
    $eorder["SendSite"] = isset($shipping_info['send_site']) ? $shipping_info['send_site'] : ''; //收件网点标识
    //$eorder["LogisticCode"] = isset($shipping_info['logistic_code']) ? $shipping_info['logistic_code'] : ''; //快递单号

    //寄方
    $sender = [];
    $sender["Name"] = $shopinfo['shop_name'];
    $sender["Mobile"] = $shopinfo['mobile'];
    $sender["ProvinceName"] = get_goods_region_name($shopinfo['province']);
    $sender["CityName"] = get_goods_region_name($shopinfo['city']);
    $sender["ExpAreaName"] = get_goods_region_name($shopinfo['district']);
    $sender["Address"] = $shopinfo['shop_address'];

    //收方
    $receiver = [];
    $receiver["Name"] = $order['consignee'];
    $receiver["Mobile"] = $order['mobile'];
    $receiver["ProvinceName"] = get_goods_region_name($order['province']);
    $receiver["CityName"] = get_goods_region_name($order['city']);
    $receiver["ExpAreaName"] = get_goods_region_name($order['district']);
    $receiver["Address"] = $order['address'];

    //其他
    $commodityOne = [];
    $commodityOne["GoodsName"] = "其他";
    $commodityOne["Goodsquantity"] = empty($Goodsquantity) ? 1 : $Goodsquantity;
    $commodityOne["GoodsWeight"] = empty($GoodsWeight) ? 1 : $GoodsWeight;
    $commodity = [];
    $commodity[] = $commodityOne;

    //整合数据
    $eorder["Sender"] = $sender;
    $eorder["Receiver"] = $receiver;
    $eorder["Commodity"] = $commodity;

    //调用电子面单
    $jsonParam = json_encode($eorder, JSON_UNESCAPED_UNICODE);
    //$jsonParam = JSON($eorder);//兼容php5.2（含）以下
    $jsonResult = submitEOrder($jsonParam);

    //解析电子面单返回结果
    $result = json_decode($jsonResult, true);

    return $result;
}

//快递鸟、电子面单：获取打印方式
function get_print_type($ru_id = 0) {
    $print_type = get_table_date('seller_shopinfo', "ru_id='$ru_id'", array('print_type'), 2);
    return $print_type;
}

/**
 * 获取商家品牌
 * 后台异步操作需要
 */
function get_seller_brand(){
    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('merchants_shop_brand'). " WHERE 1";
    $res = $GLOBALS['db']->getAll($sql);

    if ($res) {
        foreach ($res as $key => $row) {

            $res[$key]['seller_name'] = get_shop_name($row['user_id'], 1);

            $sql = "SELECT b.brand_id, b.brand_name FROM " . $GLOBALS['ecs']->table('brand') ." AS b," .$GLOBALS['ecs']->table('link_brand') ." AS lb".
                    " WHERE b.brand_id = lb.brand_id AND lb.bid = '" .$row['bid']. "' LIMIT 1";
            $brand = $GLOBALS['db']->getRow($sql);

            if ($brand) {
                $res[$key]['brand_id'] = $brand['brand_id'];
                $res[$key]['brand_name'] = $brand['brand_name'];

                $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods') . " WHERE brand_id = '" . $row['bid'] . "' AND user_id = '" . $row['user_id'] . "'";
                $res[$key]['goods_count'] = $GLOBALS['db']->getOne($sql);
            } else {
                unset($res[$key]);
            }
        }
    }

    return $res;
}

//获取统计数据
function get_statistical_data($start_date = 0, $end_date = 0, $type = 'order'){
	$data = array();
	$adminru = get_admin_ru_id();

	//格林威治时间与本地时间差
	$timezone = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : $GLOBALS['_CFG']['timezone'];
	$time_diff = $timezone * 3600;
	$date_start = $start_date;
	$date_end = $end_date;
	$day_num = ceil($date_end - $date_start)/86400;

	//获取系统数据 start
	$no_main_order = " AND (SELECT count(*) FROM " . $GLOBALS['ecs']->table('order_info') . " AS oi2 WHERE oi2.main_order_id = oi.order_id) = 0 "; //主订单下有子订单时，则主订单不显示
	$where_date .= " AND (SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og WHERE oi.order_id = og.order_id LIMIT 1) = '" . $adminru['ru_id'] . "'"; //商家订单
	$sql = 'SELECT DATE_FORMAT(FROM_UNIXTIME(oi.add_time + '.$time_diff.'),"%y-%m-%d") AS day,COUNT(*) AS count,SUM(oi.money_paid) AS money, SUM(oi.money_paid)+SUM(oi.surplus) AS superman FROM ' . $GLOBALS['ecs']->table('order_info') . " AS oi" . ' WHERE oi.add_time BETWEEN ' . $date_start . ' AND ' . $date_end . $no_main_order . $where_date . ' AND oi.supplier_id = 0 GROUP BY day ORDER BY day ASC ';
	$result = $GLOBALS['db']->query($sql);

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
	$title = array(
		'text' => '',
		'subtext' => ''
	);

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
		$title['text'] = '订单数量';
		$xAxis['data'] = $orders_xAxis_data;
		$yAxis['formatter'] = '{value}个';
		ksort($orders_series_data);
		$series[0]['name'] = '订单个数';
		$series[0]['data'] = array_values($orders_series_data);
	}

	//销售统计
	if ($type == 'sale') {
		$title['text'] = '销售额';
		$xAxis['data'] = $sales_xAxis_data;
		$yAxis['formatter'] = '{value}元';
		ksort($sales_series_data);
		$series[0]['name'] = '销售额';
		$series[0]['data'] = array_values($sales_series_data);
	}

	//整理数据
	$data['title'] = $title;
	$data['series'] = $series;
	$data['tooltip'] = $tooltip;
	$data['legend'] = $legend;
	$data['toolbox'] = $toolbox;
	$data['calculable'] = $calculable;
	$data['xAxis'] = $xAxis;
	$data['yAxis'] = $yAxis;

	$data['xy_file'] = get_dir_file_list();
	return $data;
}

//图片库图片引用判断函数
//$file  图片链接
function verific_pic($file = '',$ru_id = 0) {
    $return = false;
    if ($file) {
        $where = " WHERE 1";
        $where .= " AND (g.goods_desc LIKE '%" . $file . "%' OR g.desc_mobile LIKE '%" .$file . "%' OR ga.img_url LIKE '%" . $file . "%' OR ga.thumb_url LIKE '%" . $file . "%' OR ga.img_original LIKE '%" . $file . "%')";
        $sql = "SELECT COUNT(*) FROM" . $GLOBALS['ecs']->table('goods') . "AS g LEFT JOIN " . $GLOBALS['ecs']->table('goods_gallery') . "AS ga ON ga.goods_id = g.goods_id " . $where;

        if($GLOBALS['db']->getOne($sql) > 0){
            $return = true;
        }
    }
    return $return;
}

/**
 * 运费模板
 * 获取全国地区
 *
 */
function get_the_national($region_name = '中国'){
    $sql = "SELECT r2.region_id, r2.region_name, r2.parent_id FROM " .$GLOBALS['ecs']->table("region"). " AS r1".
            " LEFT JOIN " . $GLOBALS['ecs']->table("region"). " AS r2 ON r2.parent_id = r1.region_id AND r2.region_type = 1".
            " WHERE r1.region_type = 0 AND r1.region_name = '$region_name'";
    $regions = $GLOBALS['db']->getAll($sql);

    return $regions;
}

//改变订单编号
function correct_order_sn($order_sn = '') {
    $new_order_sn = get_order_sn();//生成新的订单号
    //修改订单表订单号
    $sql = "UPDATE" . $GLOBALS['ecs']->table('order_info') . "SET order_sn = '$new_order_sn' WHERE order_sn = '" . $order_sn . "'";
    $GLOBALS['db']->query($sql);
    //修改分期表订单号
    $sql = "UPDATE" . $GLOBALS['ecs']->table('stages') . "SET order_sn = '$new_order_sn' WHERE order_sn = '" . $order_sn . "'";
    $GLOBALS['db']->query($sql);
    //修改交易快照订单号
    $sql = "UPDATE" . $GLOBALS['ecs']->table('trade_snapshot') . "SET order_sn = '$new_order_sn' WHERE order_sn = '" . $order_sn . "'";
    $GLOBALS['db']->query($sql);
    //修改虚拟卡卡号库表订单号
    $sql = "UPDATE" . $GLOBALS['ecs']->table('virtual_card') . "SET order_sn = '$new_order_sn' WHERE order_sn = '" . $order_sn . "'";
    $GLOBALS['db']->query($sql);
    //修改交易纠纷表订单号
    $sql = "UPDATE" . $GLOBALS['ecs']->table('complaint') . "SET order_sn = '$new_order_sn' WHERE order_sn = '" . $order_sn . "'";
    $GLOBALS['db']->query($sql);

    //修改账户日志订单号
    $new_order_sn_msg = "，订单号已修改为：$new_order_sn";
    $sql = "UPDATE" . $GLOBALS['ecs']->table('account_log') . "SET change_desc = concat(change_desc,'$new_order_sn_msg') WHERE change_desc LIKE '%" . $order_sn. "%'";
    $GLOBALS['db']->query($sql);
    //修改管理员日志订单号
    $new_order_sn_msg = "，订单号已修改为：$new_order_sn";
    $sql = "UPDATE" . $GLOBALS['ecs']->table('admin_log') . "SET log_info = concat(log_info,'$new_order_sn_msg') WHERE log_info LIKE '%" . $order_sn . "%'";
    $GLOBALS['db']->query($sql);
    return $new_order_sn;
}

/**
 * 运费模板
 * 快递模板 - 快递列表
 */
function get_transport_shipping_list($tid = 0, $ru_id = 0){
    $sql = "SELECT g.tid, g.shipping_id, s.shipping_name FROM " . $GLOBALS['ecs']->table('shipping') . " AS s " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods_transport_tpl') . " as g ON g.shipping_id = s.shipping_id" .
            " WHERE user_id = '$ru_id' AND tid = '$tid' GROUP BY g.shipping_id";
    $shipping_tpl = $GLOBALS['db']->getAll($sql);

    if($shipping_tpl){
        foreach($shipping_tpl as $k=>$v){
            if($v['shipping_id']){
                $shipping_tpl[$k]['area_list'] = get_transport_shipping_area_list($v['tid'], $v['shipping_id']);
                $shipping_tpl[$k]['area_count'] = count($shipping_tpl[$k]['area_list']);
            }
        }
    }

    return $shipping_tpl;
}

/**
 * 运费模板
 * 快递模板 - 快递地区列表
 */
function get_transport_shipping_area_list($tid = 0, $shipping_id = 0){
    $sql = "SELECT g.* FROM " .$GLOBALS['ecs']->table('goods_transport_tpl'). " AS g " .
        "WHERE g.tid = '$tid' AND g.shipping_id = '$shipping_id' ORDER BY g.id";
    $area_list = $GLOBALS['db']->getAll($sql);

    if($area_list){
        foreach ($area_list as $k => $v) {
            if ($v['region_id']) {
                $area_list[$k]['region_list'] = get_area_list($v['region_id']);
            }
        }
    }

    return $area_list;
}


/**
 * 统一商品详情
 * 列表
 */
function get_link_goods_desc_list($ru_id = 0){


    $result = get_filter();
    if ($result === false)
    {

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $filter['type'] = isset($_REQUEST['type']) && !empty($_REQUEST['type']) ? dsc_addslashes($_REQUEST['type']) : '';

        $filter['ru_id'] = $ru_id;

        if($filter['type']){
            $where = "AND ru_id > 0";
        }else{
            $where = "AND ru_id = '" .$filter['ru_id']. "'";
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
        if($page > 0){
            $filter['page'] = $page;
        }
        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 15;
        }

        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('link_goods_desc') . " WHERE 1 $where";
        $record_count = $GLOBALS['db']->getOne($sql);

        $filter['record_count']   = $record_count;
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('link_goods_desc') . " WHERE 1 $where" .
                " ORDER BY $filter[sort_by] $filter[sort_order] " .
                " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array('desc_list' => $res, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
}

?>
