<?php

/**
 * 获得分类的信息
 *
 * @param integer $cat_id
 *
 * @return void
 */
function get_cat_info($cat_id)
{
    return $GLOBALS['db']->getRow('SELECT cat_name, keywords, cat_desc, style, grade, filter_attr, parent_id, cat_icon FROM {pre}category as g WHERE cat_id = ' . $cat_id);
}

/**
 * 获得分类下的商品
 *
 * @access public
 * @param string $children
 * @return array
 */
function get_cagtegory_goods($keywords, $cat_id, $sort = 'g.last_update', $order = 'DESC', $limit = '10')
{
    if ($cat_id == 0) {
        $where = '';
    } else {
        $where = " AND " . get_children($cat_id);
    }
    if ($keywords) {
        $where .= " AND ( 1 " . $keywords . " ) ";
    }
    $sql = "SELECT g.goods_id, g.goods_name,g.goods_img,g.shop_price FROM {pre}goods AS g  WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND g.review_status > 2 " . $where . " ORDER BY " . $sort . " " . $order . " LIMIT $limit";
    $goods_list = $GLOBALS['db']->query($sql);
    foreach ($goods_list as $key => $val) {
        $goods_list[$key]['goods_img'] = get_image_path($val['goods_img']);
        $sql = "SELECT (AVG(comment_rank) * 20) AS rank,COUNT(*) AS count FROM {pre}comment WHERE comment_type = 0 AND id_value = " . $val['goods_id'];
        $rank = $GLOBALS['db']->getRow($sql);
        $goods_list[$key]['rank'] = substr($rank['rank'], 0, 2) ? substr($rank['rank'], 0, 2) : 0;
        $goods_list[$key]['count'] = $rank['count'];
    }
    return $goods_list;
}

/**
 * 取得最近的上级分类的grade值
 *
 * @access  public
 * @param   int $cat_id //当前的cat_id
 *
 * @return int
 */
function get_parent_grade($cat_id)
{
    static $res = null;

    if ($res === null) {
        $data = read_static_cache('cat_parent_grade');
        if ($data === false) {
            $sql = "SELECT parent_id, cat_id, grade " .
                " FROM " . $GLOBALS['ecs']->table('category');
            $res = $GLOBALS['db']->getAll($sql);
            write_static_cache('cat_parent_grade', $res);
        } else {
            $res = $data;
        }
    }

    if (!$res) {
        return 0;
    }

    $parent_arr = [];
    $grade_arr = [];

    foreach ($res as $val) {
        $parent_arr[$val['cat_id']] = $val['parent_id'];
        $grade_arr[$val['cat_id']] = $val['grade'];
    }

    while ($parent_arr[$cat_id] > 0 && $grade_arr[$cat_id] == 0) {
        $cat_id = $parent_arr[$cat_id];
    }

    return $grade_arr[$cat_id];
}

/**
 * 获得分类下的商品
 * @param $keywords 关键词查询条件
 * @param $children
 * @param $brand
 * @param $min
 * @param $max
 * @param $ext
 * @param $size
 * @param $page
 * @param $sort
 * @param $order
 * @param int $warehouse_id
 * @param int $area_id
 * @param $ubrand
 * @param $hasgoods
 * @param $promotion
 * @return array
 */
function category_get_goods($keywords = '', $children, $intro = '', $brand, $min, $max, $ext, $size, $page, $sort, $order, $warehouse_id = 0, $area_id = 0, $ubrand = 0, $hasgoods = 0, $promotion = 0, $cou_id = 0, $mode='')
{
    $display = isset($_COOKIE['ECS']['display']) ? $_COOKIE['ECS']['display'] : 'grid';
    $where = "g.is_delete = 0 ";
    if ($keywords) {
        $where .= " AND (( 1 " . $keywords . " ) ) ";
    } else {
        $get_extension_goods = get_extension_goods($children);
        if (!empty($get_extension_goods) && !empty($children)) {
            $where .= " AND ($children OR " . $get_extension_goods . ')';
        }
    }
    if ($hasgoods) {
        $where .= " AND g.goods_number > 0";
    }
    if ($promotion) {
        $intro = 'promotion';
    }
    if ($intro) {
        switch ($intro) {
            case 'best':
                $where .= ' AND g.is_best = 1 ';
                break;
            case 'new':
                $where .= ' AND g.is_new = 1 ';
                break;
            case 'hot':
                $where .= ' AND g.is_hot = 1 ';
                break;
            case 'promotion':
                $time = gmtime();
                $where .= " AND g.promote_price > 0 AND g.promote_start_date <= '$time' AND g.promote_end_date >= '$time' ";
                break;
            default:
                $where .= '';
        }
    }

    $leftJoin = '';
    if ($brand) {
        $where .= " AND g.brand_id IN(" . $brand . ")";
    }

    $now = gmtime();

    //ecmoban模板堂 --zhuo start
    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('merchants_shop_information') . " as msi on msi.user_id = g.user_id ";

    if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
        $where .= " AND lag.region_id = '$area_id' ";
    }
    //ecmoban模板堂 --zhuo end

    if ($min > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }

    if ($sort == 'last_update') {
        $sort = 'g.last_update';
    }

    //ecmoban模板堂 --zhuo start
//    if ($GLOBALS['_CFG']['review_goods'] == 1) {
//        $where .= ' AND g.review_status > 2 ';
//    }
    //ecmoban模板堂 --zhuo end

    // 优惠券商品条件
    if ($cou_id > 0) {
        $cou_data = dao('coupons')->field('cou_type, ru_id, cou_goods, spec_cat, cou_ok_goods, cou_ok_cat')->where(['cou_id' => $cou_id])->find();
        //如果指定了使用的商品
        $where .= get_con_where($cou_data['ru_id'], $cou_data['cou_goods'], $cou_data['spec_cat']);
    }

    /* 获得商品列表 */

    $leftJoin1 = " inner JOIN " . $GLOBALS['ecs']->table('goods_activity') . " AS pa ON pa.goods_id = g.goods_id ".$leftJoin;
    $leftJoin2 = " inner JOIN " . $GLOBALS['ecs']->table('presale_activity') . " AS pa ON pa.goods_id = g.goods_id ".$leftJoin;
    $leftJoin3 = " inner JOIN " . $GLOBALS['ecs']->table('sample_activity') . " AS pa ON pa.goods_id = g.goods_id ".$leftJoin;
    $leftJoin4 = " inner JOIN " . $GLOBALS['ecs']->table('wholesale') . " AS pa ON pa.goods_id = g.goods_id ".$leftJoin;


    $where .= ' AND pa.review_status > 2 ';

    //团购
    $sql1 = 'SELECT  pa.act_id,pa.ext_info, "price_model", g.goods_id, g.last_update,  g.good_mode, g.user_id, g.goods_name, ' . $shop_price . ' "goods_price", ' . ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot,g.model_attr,' .
        ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, g.model_price, ' .
        "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
        "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, g.goods_type, " .
        'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img, msi.self_run, g.product_price, g.product_promote_price  ' .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin1 .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        "WHERE $where $ext AND pa.act_type = '" . GAT_GROUP_BUY . "' AND pa.start_time < ".$now ." AND pa.end_time > ".$now;

    $sql2 = 'SELECT  pa.act_id,pa.ext_info,"price_model",g.goods_id, g.last_update,  g.good_mode, g.user_id, g.goods_name, ' . $shop_price . ' "goods_price", ' . ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot,g.model_attr,' .
        ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, g.model_price, ' .
        "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
        "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, g.goods_type, " .
        'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img, msi.self_run, g.product_price, g.product_promote_price  ' .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin2 .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        "WHERE $where $ext " ." AND pa.start_time < ".$now ." AND pa.end_time > ".$now;

    $sql3 = 'SELECT  pa.act_id,pa.ext_info,"price_model",g.goods_id,  g.last_update, g.good_mode, g.user_id, g.goods_name, ' . $shop_price . ' "goods_price", ' . ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot,g.model_attr,' .
        ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, g.model_price, ' .
        "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
        "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, g.goods_type, " .
        'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img, msi.self_run, g.product_price, g.product_promote_price  ' .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin3 .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        "WHERE $where $ext ";

    $sql4 = 'SELECT  pa.act_id, "", pa.price_model,g.goods_id, g.last_update, g.good_mode, g.user_id, g.goods_name, ' . $shop_price . ' pa.goods_price, ' . ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot,g.model_attr,' .
        ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, g.model_price, ' .
        "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
        "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, g.goods_type, " .
        'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img, msi.self_run, g.product_price, g.product_promote_price  ' .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin4 .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        "WHERE $where $ext ";

    if ($mode && in_array($mode, ['groupbuy', 'presale', 'sample', 'wholesale'])) {
        switch($mode) {
            case 'groupbuy':
                $sql = "SELECT * FROM ( " .$sql1 . " ) AS g ORDER BY $sort $order";
                break;
            case 'presale':
                $sql = "SELECT * FROM ( " .$sql2 . " ) AS g ORDER BY $sort $order";
                break;
            case 'sample':
                $sql = "SELECT * FROM ( " .$sql3 . " ) AS g ORDER BY $sort $order";
                break;
            case 'wholesale':
                $sql = "SELECT * FROM ( " .$sql4 . " ) AS g ORDER BY $sort $order";
                break;
        }
    } else {
        $sql = "SELECT * FROM ( " .$sql1 .' UNION ALL '.$sql2 . ' UNION ALL '.$sql3  .' UNION ALL '.$sql4 ." ) AS g ORDER BY $sort $order";
    }


    $total_query = $GLOBALS['db']->query($sql);
    $total = is_array($total_query) ? count($total_query) : 0;
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);


    $arr = [];
    foreach ($res as $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        //冲定义价格
        if (in_array($row['good_mode'], [1, 2, 3])) {
            $ext_info = unserialize($row['ext_info']);
            $row = array_merge($row, $ext_info);

            /* 处理价格阶梯 */
            $price_ladder = $row['price_ladder'];
            if (!is_array($price_ladder) || empty($price_ladder))
            {
                $price_ladder = array(array('amount' => 0, 'price' => 0));
            }
            else
            {
                foreach ($price_ladder as $key => $amount_price)
                {
                    $price_ladder[$key]['formated_price'] = price_format($amount_price['price']);
                }
            }

            $row['price_ladder'] = $price_ladder;

            $arr[$row['goods_id']]['volume_price'] = price_format($price_ladder[0]['price']);
        } else {
            if ($row['price_model']) {
                $sql = "SELECT MIN(volume_price) AS volume_price FROM ". $GLOBALS['ecs']->table('wholesale_volume_price') . "WHERE goods_id = ".$row['goods_id'];
                $volume_price = $GLOBALS['db']->getOne($sql);
                if ($volume_price) {
                    $arr[$row['goods_id']]['volume_price'] = price_format($volume_price);
                }
            } else {
                $arr[$row['goods_id']]['volume_price'] = price_format($row['goods_price']);
            }
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

        $arr[$row['goods_id']]['org_price'] = $row['org_price'];
        $arr[$row['goods_id']]['model_price'] = $row['model_price'];

        $arr[$row['goods_id']]['warehouse_price'] = $row['warehouse_price'];
        $arr[$row['goods_id']]['warehouse_promote_price'] = $row['warehouse_promote_price'];
        $arr[$row['goods_id']]['region_price'] = $row['region_price'];
        $arr[$row['goods_id']]['region_promote_price'] = $row['region_promote_price'];

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
            $arr[$row['goods_id']]['watermark_img'] = $watermark_img;
        }

        $arr[$row['goods_id']]['goods_id'] = $row['goods_id'];
        if ($display == 'grid') {
            $arr[$row['goods_id']]['goods_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        } else {
            $arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
        }
        $arr[$row['goods_id']]['name'] = $row['goods_name'];
        $arr[$row['goods_id']]['goods_brief'] = $row['goods_brief'];
        $arr[$row['goods_id']]['sales_volume'] = $row['sales_volume'];
        $arr[$row['goods_id']]['comments_number'] = $row['comments_number'];
        $arr[$row['goods_id']]['is_promote'] = $row['is_promote'];
        $arr[$row['goods_id']]['promote_start_date'] = $row['promote_start_date'];
        $arr[$row['goods_id']]['promote_end_date'] = $row['promote_end_date'];
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $arr[$row['goods_id']]['zhekou'] = $discount_arr['discount'];  //zhekou
        $arr[$row['goods_id']]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $arr[$row['goods_id']]['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);

        $goods_id = $row['goods_id'];
        $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='$goods_id' AND status = 1 AND parent_id = 0");
        $arr[$row['goods_id']]['review_count'] = $count;

        $arr[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
        $arr[$row['goods_id']]['type'] = $row['goods_type'];
        $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format(min($promote_price, $row['shop_price'])) : '';
        $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $arr[$row['goods_id']]['goods_img'] = get_image_path($row['goods_img']);
        if (1 == $row['good_mode']) {
            //拼单
            $arr[$row['goods_id']]['url'] = url('groupbuy/index/detail', ['id'=>$row['act_id']]);
        } else if (2 == $row['good_mode']) {
            //预定
            $arr[$row['goods_id']]['url'] = url('presale/index/detail', ['id'=>$row['act_id']]);
        } else if (3 == $row['good_mode']) {
            //样品
            $arr[$row['goods_id']]['url'] = url('sample/index/detail', ['id'=>$row['act_id']]);
        } else if (4 == $row['good_mode']) {
            //批发
            $arr[$row['goods_id']]['url'] = url('wholesale/index/detail', ['id'=>$row['act_id']]);
        } else {
            $arr[$row['goods_id']]['url'] = build_uri('goods', ['gid' => $row['goods_id']], $row['goods_name']);
        }
        $arr[$row['goods_id']]['is_hot'] = $row['is_hot'];
        $arr[$row['goods_id']]['is_best'] = $row['is_best'];
        $arr[$row['goods_id']]['is_new'] = $row['is_new'];
        $arr[$row['goods_id']]['self_run'] = $row['self_run'];
        $arr[$row['goods_id']]['is_shipping'] = $row['is_shipping'];

        //ecmoban模板堂 --zhuo start
        if ($row['model_attr'] == 1) {
            $table_products = "products_warehouse";
            $type_files = " and warehouse_id = '$warehouse_id'";
        } elseif ($row['model_attr'] == 2) {
            $table_products = "products_area";
            $type_files = " and area_id = '$area_id'";
        } else {
            $table_products = "products";
            $type_files = "";
        }

        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $row['goods_id'] . "'" . $type_files . " LIMIT 0, 1";
        $arr[$row['goods_id']]['prod'] = $GLOBALS['db']->getRow($sql);

        if (empty($prod)) { //当商品没有属性库存时
            $arr[$row['goods_id']]['prod'] = 1;
        } else {
            $arr[$row['goods_id']]['prod'] = 0;
        }

        $arr[$row['goods_id']]['goods_number'] = $row['goods_number'];

        $sql = "select * from " . $GLOBALS['ecs']->table('seller_shopinfo') . " where ru_id='" . $row['user_id'] . "'";
        $basic_info = $GLOBALS['db']->getRow($sql);
        $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];
        $arr[$row['goods_id']]['kf_ww'] = $basic_info['kf_ww'];
        $arr[$row['goods_id']]['kf_qq'] = $basic_info['kf_qq'];
        $arr[$row['goods_id']]['rz_shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
        $arr[$row['goods_id']]['user_id'] = $row['user_id'];
        $arr[$row['goods_id']]['store_url'] = build_uri('merchants_store', ['urid' => $row['user_id']], $arr[$row['goods_id']]['rz_shopName']);

        $arr[$row['goods_id']]['count'] = selled_count($row['goods_id']);
        $mc_all = ments_count_all($row['goods_id']);       //总条数
        $mc_one = ments_count_rank_num($row['goods_id'], 1);        //一颗星
        $mc_two = ments_count_rank_num($row['goods_id'], 2);        //两颗星
        $mc_three = ments_count_rank_num($row['goods_id'], 3);    //三颗星
        $mc_four = ments_count_rank_num($row['goods_id'], 4);        //四颗星
        $mc_five = ments_count_rank_num($row['goods_id'], 5);        //五颗星
        $arr[$row['goods_id']]['zconments'] = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);
        //ecmoban模板堂 --zhuo end
    }

    return ['list' => array_values($arr), 'totalpage' => ceil($total / $size)];
}


/**
 * 匹配商品名关键词高亮显示
 * @param   string $goods_name 商品名称
 * @param   string $keyword 关键词
 * @return  string
 */
function add_highlight($goods_name, $keyword = '')
{
    if (!empty($keyword)) {
        $string = '<font style=color:red; font-size:inherit;><b>' . $keyword . '</b></font>';
        $goods_name = str_replace($keyword, $string, $goods_name); //加粗加颜色;
        // $goods_name = preg_replace("/($keyword)/i", "<b style=\"color:red\">\\1</b>", $goods_name);
    }
    return $goods_name;
}


/* 优惠券使用条件 */
function get_con_where($ru_id, $cou_goods, $spec_cat){
    //如果指定了使用的商品
    $cou_where = " AND g.user_id = '$ru_id' ";
    if ($cou_goods) {
        $cou_where .= " AND g.goods_id IN (" . $cou_goods . ") ";
    } elseif ($spec_cat) {
        $cou_children = get_cou_children($spec_cat);
        if ($cou_children) {
            $cou_where .= " AND g.cat_id IN (" . $cou_children . ") ";
        }
    }

    return $cou_where;
}