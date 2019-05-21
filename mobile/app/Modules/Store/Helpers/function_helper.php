<?php

//查询所有商家的顶级分类
function get_cat_store_list($cat_id)
{
    $sql = "SELECT user_shopMain_category AS user_cat, user_id FROM {pre}merchants_shop_information  WHERE 1 AND user_shopMain_category <> '' AND merchants_audit = 1";
    $res = $GLOBALS['db']->query($sql);
    $user_id = '';
    $arr = [];
    foreach ($res as $key => $row) {
        $row['cat_str'] = '';
        $row['user_cat'] = explode('-', $row['user_cat']);

        foreach ($row['user_cat'] as $uck => $ucrow) {
            if ($ucrow) {
                $row['user_cat'][$uck] = explode(':', $ucrow);
                if (!empty($row['user_cat'][$uck][0])) {
                    $row['cat_str'] .= $row['user_cat'][$uck][0] . ",";
                }
            }
        }

        if ($row['cat_str']) {
            $row['cat_str'] = substr($row['cat_str'], 0, -1);
            $row['cat_str'] = explode(',', $row['cat_str']);

            if (in_array($cat_id, $row['cat_str'])) {
                $user_id .= $row['user_id'] . ",";
            }
        }

        $arr[] = $row;
    }

    if ($user_id) {
        $user_id = substr($user_id, 0, -1);
    }

    return $user_id;
}



/**
 * 获得分类下的商品
 *
 * @access  public
 * @param   string $children
 * @return  array
 */
function store_get_goods($children, $brand, $min, $max, $ext, $size, $page, $sort, $order, $merchant_id, $warehouse_id = 0, $area_id = 0, $keyword, $type)
{
    //ecmoban模板堂 --zhuo start
    if ($children == '') {
        $cat_where = " AND g.user_id = '$merchant_id' ";
    } else {
        $cat_where = " AND $children ";
    }

    $display = $GLOBALS['display'];
    $where = "g.is_on_sale = 1 AND g.is_alone_sale = 1 AND " . "g.is_delete = 0 $cat_where";

    if ($brand > 0) {
        $where .= "AND g.brand_id=$brand ";
    }

    //ecmoban模板堂 --zhuo start
    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end

    if ($min > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }

    $where .= " AND g.user_id = '$merchant_id'";

    if (!empty($keyword)) {
        $where .= " AND g.goods_name LIKE '%" . mysql_like_quote($keyword) . "%'";
    }

    if ($sort == 'last_update') {
        $sort = 'g.last_update';
    }

    if ($type) {
        $turetype .= "$type = 1 AND";
    } else {
        $turetype = '';
    }

    //ecmoban模板堂 --zhuo start
    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end

    /* 计算总数*/
    if ($size == 0 && $page == 0) {
        $sql = 'SELECT count(*), ' .
            ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, g.is_promote, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, g.goods_type, g.goods_number " .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' . $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE $turetype $where $ext  ORDER BY $sort $order";
        $res = $GLOBALS['db']->getRow($sql);

        return $res['count(*)'];
    } else {
        /* 获得商品列表 */
        $sql = 'SELECT g.goods_id, g.goods_name,g.model_attr, g.goods_number, ' . $shop_price . ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot, ' .
            ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, g.is_promote, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, g.goods_type, " .
            'g.promote_start_date, g.promote_end_date, g.goods_brief,g.product_price,g.product_promote_price, g.goods_thumb , g.goods_img ' .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' . $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE $turetype $where $ext  group by g.goods_id  ORDER BY $sort $order";

        $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);
    }

    $arr = [];
    $idx = 0;
    foreach ($res as $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        /**
         * 重定义商品价格
         * 商品价格 + 属性价格
         * start
         */

        $price_info = get_goods_one_attr_price($row, $warehouse_id, $area_id, $promote_price);
        $row = !empty($row) ? array_merge($row, $price_info) : $row;
        $promote_price = $row['promote_price'];
        /**
         * 重定义商品价格
         * end
         */

        /* 处理商品水印图片 */
        $watermark_img = '';

        if ($promote_price != 0) {
            $watermark_img = "watermark_promote_small";
        } elseif ($row['is_new'] != 0) {
            $watermark_img = "watermark_new_small";
        } elseif ($row['is_best'] != 0) {
            $watermark_img = "watermark_best_small";
        } elseif ($row['is_hot'] != 0) {
            $watermark_img = 'watermark_hot_small';
        }

        if ($watermark_img != '') {
            $arr[$idx]['watermark_img'] = $watermark_img;
        }

        $arr[$idx]['goods_id'] = $row['goods_id'];
        if ($display == 'grid') {
            $arr[$idx]['goods_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        } else {
            $arr[$idx]['goods_name'] = $row['goods_name'];
        }
        $arr[$idx]['name'] = $row['goods_name'];
        $arr[$idx]['goods_brief'] = $row['goods_brief'];
        $arr[$idx]['sales_volume'] = $row['sales_volume'];
        $arr[$idx]['comments_number'] = $row['comments_number'];
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $arr[$idx]['zhekou'] = $discount_arr['discount'];  //zhekou
        $arr[$idx]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $arr[$idx]['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);
        $goods_id = $row['goods_id'];

        $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where comment_type=0 and id_value ='$goods_id'");

        $arr[$idx]['review_count'] = $count;

        $arr[$idx]['market_price'] = price_format($row['market_price']);
        $arr[$idx]['shop_price'] = price_format($row['shop_price']);
        $arr[$idx]['type'] = $row['goods_type'];
        $arr[$idx]['is_promote'] = $row['is_promote'];
        $arr[$idx]['goods_number'] = $row['goods_number'];
        $arr[$idx]['promote_price'] = price_format($promote_price);
        $arr[$idx]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $arr[$idx]['goods_img'] = get_image_path($row['goods_thumb']);
        $arr[$idx]['goods_url'] = build_uri('goods', ['gid' => $row['goods_id']], $row['goods_name']);

        $arr[$idx]['count'] = selled_count($row['goods_id']);

        $arr[$idx]['pictures'] = get_goods_gallery($row['goods_id']);// 商品相册
        $attr = get_goods_properties($row['goods_id'], $warehouse_id, $area_id);
        $arr[$idx]['spe'] = $attr['spe'];
        $idx++;
    }

    return $arr;
}

/*
 * 取得活动列表
 * @return   array
 */
function activity_list($cat_id,  $mode = '', $merchant_id = 0, $sort = 'act_id', $order = 'DESC', $page = 1, $size = 10, $keyword) {
    $where = '';
    $fields = '';
    $join = '';
    $time = gmtime();
    if ($cat_id > 0) {
        $children = get_children($cat_id, 0, 0, 'merchants_category', "g.user_cat");
        $where .= ' AND '.$children;
    }

    if (!empty($keyword)) {
        $where .= " AND g.goods_name LIKE '%" . mysql_like_quote($keyword) . "%'";
    }

    if ($merchant_id) {
        $where .= ' AND g.user_id = '.$merchant_id;
    }

    if ($mode == 'presale') {
        //预售
        $table = $GLOBALS['ecs']->table('presale_activity');
        $where .= " AND a.end_time > " . $time;

    } elseif ($mode == 'groupbuy') {
        //团购
        $where .= " AND a.act_type = " . GAT_GROUP_BUY;
        $where .= " AND a.end_time > " . $time;
        $table = $GLOBALS['ecs']->table('goods_activity');
    } elseif ($mode == 'sample') {
        //样品
        $table = $GLOBALS['ecs']->table('sample_activity');
    } elseif ($mode == 'wholesale') {
        //团购
        $table = $GLOBALS['ecs']->table('wholesale');

    }

    $sql = "SELECT COUNT(*) FROM (SELECT g.goods_id as total FROM " .
        $table . " AS a " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON a.goods_id = g.goods_id " .
        " WHERE g.goods_id > 0  $where AND a.review_status > 2 group by a.goods_id order by act_id desc limit 0, 1000000) as ag ";
    $total = $GLOBALS['db']->getOne($sql);
    $total ? $total : 0;

    $sql = "SELECT * FROM (SELECT a.*, g.goods_thumb, g.goods_img, g.shop_price, g.market_price, g.sales_volume $fields FROM " .
        $table . " AS a " .
        " inner JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON a.goods_id = g.goods_id " .
        " WHERE g.goods_id > 0 $where AND a.review_status > 2 ORDER BY $sort $order limit 0, 1000000) AS ag GROUP BY ag.goods_id LIMIT " . ($page - 1) * $size . ",  $size";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $key => $row) {
        $res[$key]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $res[$key]['goods_img'] = get_image_path($row['goods_img']);

        if (in_array($mode, ['groupbuy', 'presale', 'sample'])) {
            $ext_info = unserialize($row['ext_info']);
            $row = array_merge($row, $ext_info);

            $res[$key]['volume_price'] = price_format($row['price_ladder'][0]['price']);
        }

        if ($mode == 'wholesale') {
            //批发
            if ($row['price_model']) {
                $sql = "SELECT MIN(volume_price) AS volume_price FROM ". $GLOBALS['ecs']->table('wholesale_volume_price') . "WHERE goods_id = ".$row['goods_id'];
                $volume_price = $GLOBALS['db']->getOne($sql);
                if ($volume_price) {
                    $res[$key]['volume_price'] = price_format($volume_price);
                }
            } else {
                $res[$key]['volume_price'] = price_format($row['goods_price']);
            }
        }

        $res[$key]['shop_price'] = price_format($row['shop_price']);

        $stat = activity_stat($row['act_id'], $mode);
        $res[$key]['cur_amount'] = $stat['total_goods'];
        $res[$key]['mode'] = $mode;

        $res[$key]['url'] = build_uri($mode, ['r' => 'index/detail', 'id' => $row['act_id'], 'gbid' => $row['act_id']]);
    }
    return ['total' => $total, 'list' => $res];
}

/*
 * 取得某活动统计信息
 */
function activity_stat($act_id, $mode = '') {
    $act_id = intval($act_id);
    if (!$mode) {
        return [];
    }

    $where = '';
    switch($mode) {
        case 'groupbuy':
            $table = $GLOBALS['ecs']->table('goods_activity');
            $where .=  " AND act_type = '" . GAT_GROUP_BUY . "'";
            break;
        case 'presale':
            $table = $GLOBALS['ecs']->table('presale_activity');
            break;
        case 'sample':
            $table = $GLOBALS['ecs']->table('sample_activity');
            break;
        case 'wholesale':
            $table = $GLOBALS['ecs']->table('wholesale');
            break;
    }

    /* 取得团购活动商品ID */
    $sql = "SELECT goods_id " .
        "FROM " . $table.
        "WHERE review_status = 3 AND act_id = '$act_id' " .
        $where;
    $goods_id = $GLOBALS['db']->getOne($sql);

    if ($mode == 'groupbuy') {
        $mode = 'group_buy';
    }

    $sql = "SELECT COUNT(*) AS total_order, SUM(g.goods_number) AS total_goods " .
        "FROM " . $GLOBALS['ecs']->table('order_info') . " AS o, " .
        $GLOBALS['ecs']->table('order_goods') . " AS g " .
        " WHERE o.order_id = g.order_id " .
        " AND o.extension_code = '".$mode."'" .
        " AND o.extension_id = " .$act_id .
        " AND g.goods_id = '$goods_id' " .
        " AND (order_status = '" . OS_CONFIRMED . "' OR order_status = '" . OS_UNCONFIRMED . "')";
    $stat = $GLOBALS['db']->getRow($sql);

    if ($stat['total_order'] == 0)
    {
        $stat['total_goods'] = 0;
    }

    return $stat;
}

