<?php

/**
 *  获取指定用户的收藏商品列表
 *
 * @access  public
 * @param   int $user_id 用户ID
 * @param   int $num 列表最大数量
 * @param   int $start 列表其实位置
 *
 * @return  array   $arr
 */
function get_collection_goods($user_id, $record_count, $limit = '')
{
    if (!isset($_COOKIE['province'])) {
        $area_array = get_ip_area_name();

        if ($area_array['county_level'] == 2) {
            $date = ['region_id', 'parent_id', 'region_name'];
            $ip_where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
            $city_info = get_table_date('region', $ip_where, $date, 1);

            $date = ['region_id', 'region_name'];
            $ip_where = "region_id = '" . $city_info[0]['parent_id'] . "'";
            $province_info = get_table_date('region', $ip_where, $date);

            $ip_where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
            $district_info = get_table_date('region', $ip_where, $date, 1);
        } elseif ($area_array['county_level'] == 1) {
            $area_name = $area_array['area_name'];

            $date = ['region_id', 'region_name'];
            $ip_where = "region_name = '$area_name'";
            $province_info = get_table_date('region', $ip_where, $date);

            $ip_where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
            $city_info = get_table_date('region', $ip_where, $date, 1);

            $ip_where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
            $district_info = get_table_date('region', $ip_where, $date, 1);
        }
    }

    $province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $province_info['region_id'];
    $city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $city_info[0]['region_id'];
    $district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $district_info[0]['region_id'];

    cookie('province', $province_id);
    cookie('city', $city_id);
    cookie('district', $district_id);

    $area_info = get_area_info($province_id);
    $area_id = $area_info['region_id'];

    $region_where = "regionId = '$province_id'";
    $date = ['parent_id'];
    $region_id = get_table_date('region_warehouse', $region_where, $date, 2);

    $leftJoin = '';

    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$region_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end
    $sql = 'SELECT g.goods_thumb, g.good_mode, g.goods_id, g.goods_name, g.market_price, g.shop_price AS org_price, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
            'g.promote_start_date,g.promote_end_date, c.rec_id, c.is_attention, c.add_time' .
            ' FROM ' . $GLOBALS['ecs']->table('collect_goods') . ' AS c' .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g " .
            "ON g.goods_id = c.goods_id " .
            $leftJoin .
            " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            " WHERE g.goods_id = c.goods_id AND c.user_id = '$user_id' ORDER BY c.rec_id DESC " . $limit;
    $res = $GLOBALS['db']->getAll($sql);


    $goods_list = [];
    foreach ($res as $key => $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }
        $sql = 'SELECT sales_volume, goods_id, goods_name, goods_number, promote_start_date, promote_end_date, is_promote, market_price, promote_price, shop_price, goods_thumb, market_price
                FROM {pre}goods WHERE goods_id=' . $row['goods_id'];
        $get = $GLOBALS['db']->getRow($sql);

        //团购类型
        if (1 == $row['good_mode']) {
            //团购
            $table = $GLOBALS['ecs']->table('goods_activity');
        }
        if (2 == $row['good_mode']) {
            //预定
            $table = $GLOBALS['ecs']->table('presale_activity');
        }
        if (3 == $row['good_mode']) {
            //样品
            $table = $GLOBALS['ecs']->table('sample_activity');
        }
        if (4 == $row['good_mode']) {
            //批发
            $table = $GLOBALS['ecs']->table('wholesale');
        }

        if ($row['good_mode'] != 4) {
            $sql = "SELECT act_id, ext_info FROM $table WHERE goods_id = ".$row['goods_id'] ." AND  review_status = 3  order by act_id desc";
            $activity = $GLOBALS['db']->getRow($sql);
            if (!$activity) {
                continue;
            }

            //预览扩展
            $ext_info = unserialize($activity['ext_info']);
            $activity = array_merge($activity, $ext_info);

            $price_ladder = $activity['price_ladder'];
            if (!is_array($price_ladder) || empty($price_ladder))
            {
                $price_ladder = array(array('amount' => 0, 'price' => 0));
            }
            else
            {
                foreach ($price_ladder as $key => $amount_price)
                {
                    $price_ladder[$key]['formated_price'] = price_format($amount_price['price'], false);
                }
            }
            $goods_list[$row['goods_id']]['volume_price'] = $price_ladder[0]['formated_price'];

            if (1 == $row['good_mode']) {
                $goods_list[$row['goods_id']]['url'] = build_uri('groupbuy', ['id' => $activity['act_id']]);
            } else if (2 == $row['good_mode']) {
                $goods_list[$row['goods_id']]['url'] = build_uri('presale', ['id' => $activity['act_id']]);
            }  else if (3 == $row['good_mode']) {
                $goods_list[$row['goods_id']]['url'] = build_uri('sample', ['id' => $activity['act_id']]);
            }

        } else {
            $sql = "SELECT act_id, price_model FROM $table WHERE goods_id = ".$row['goods_id'] ." AND  review_status = 3  order by act_id desc";
            $activity = $GLOBALS['db']->getRow($sql);
            if (!$activity) {
                continue;
            }

            if ($activity['price_model']) {
                $sql = "SELECT MIN(volume_price) AS volume_price FROM ". $GLOBALS['ecs']->table('wholesale_volume_price') . "WHERE goods_id = ".$row['goods_id'];
                $volume_price = $GLOBALS['db']->getOne($sql);
                if ($volume_price) {
                    $goods_list[$row['goods_id']]['volume_price'] = price_format($volume_price);
                }
            } else {
                $goods_list[$row['goods_id']]['volume_price'] = price_format($activity['goods_price']);
            }

            $goods_list[$row['goods_id']]['url'] = build_uri('wholesale', ['id' => $activity['act_id']]);
        }

        $goods_list[$row['goods_id']]['good_mode'] = $row['good_mode'];
        $goods_list[$row['goods_id']]['goods_number'] = $get['goods_number'];
        $goods_list[$row['goods_id']]['rec_id'] = $row['rec_id'];
        $goods_list[$row['goods_id']]['is_attention'] = $row['is_attention'];
        $goods_list[$row['goods_id']]['goods_id'] = $row['goods_id'];
        $goods_list[$row['goods_id']]['goods_name'] = $row['goods_name'];
        $goods_list[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $goods_list[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
        $goods_list[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
        $goods_list[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $goods_list[$row['goods_id']]['add_time'] = local_date("Y-m-d H:i:s", $row['add_time']);
        $goods_list[$row['goods_id']]['del'] = url('user/index/delcollection', ['rec_id' => $row['rec_id']]);
        $mc_all = ments_count_all($row['goods_id']);       //总条数
        $mc_one = ments_count_rank_num($row['goods_id'], 1);  //一颗星
        $mc_two = ments_count_rank_num($row['goods_id'], 2);     //两颗星
        $mc_three = ments_count_rank_num($row['goods_id'], 3);    //三颗星
        $mc_four = ments_count_rank_num($row['goods_id'], 4);  //四颗星
        $mc_five = ments_count_rank_num($row['goods_id'], 5);  //五颗星
        $goods_list[$row['goods_id']]['zconments'] = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);
    }

    $arr = ['goods_list' => $goods_list, 'record_count' => $record_count, 'paper' => $paper, 'size' => $size];
    return $arr;
}


/**
 *  获取指定用户的收藏店铺列表
 *
 * @access  public
 * @param   int     $user_id        用户ID
 * @param   int     $num            列表最大数量
 *
 *
 * @return  array   $arr
 */
function get_collection_store_list($user_id, $record_count, $limit = '')
{
    if (!isset($_COOKIE['province'])) {
        $area_array = get_ip_area_name();
        if ($area_array['county_level'] == 2) {
            $date = ['region_id', 'parent_id', 'region_name'];
            $where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
            $city_info = get_table_date('region', $where, $date, 1);
            $date = ['region_id', 'region_name'];
            $where = "region_id = '" . $city_info[0]['parent_id'] . "'";
            $province_info = get_table_date('region', $where, $date);

            $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
            $district_info = get_table_date('region', $where, $date, 1);
        } elseif ($area_array['county_level'] == 1) {
            $area_name = $area_array['area_name'];

            $date = ['region_id', 'region_name'];
            $where = "region_name = '$area_name'";
            $province_info = get_table_date('region', $where, $date);

            $where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
            $city_info = get_table_date('region', $where, $date, 1);

            $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
            $district_info = get_table_date('region', $where, $date, 1);
        }
    }

    $province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $province_info['region_id'];
    $city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $city_info[0]['region_id'];
    $district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $district_info[0]['region_id'];

    cookie('province', $province_id);
    cookie('city', $city_id);
    cookie('district', $district_id);

    $area_info = get_area_info($province_id);
    $area_id = $area_info['region_id'];

    $region_where = "regionId = '$province_id'";
    $date = ['parent_id'];
    $region_id = get_table_date('region_warehouse', $region_where, $date, 2);

    $leftJoin = '';

    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$region_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end

    $sql = "SELECT m.shoprz_brandName, m.shopNameSuffix, m.shop_id, s.shop_logo,c.rec_id, c.ru_id, c.add_time, s.kf_type, s.kf_ww, s.kf_qq, brand_thumb  FROM " . $GLOBALS['ecs']->table('collect_store') . " as c, " . $GLOBALS['ecs']->table('seller_shopinfo') . " as s, " .
            $GLOBALS['ecs']->table('merchants_shop_information') . " as m " .
            " WHERE c.ru_id = s.ru_id AND s.ru_id = m.user_id AND c.user_id = '$user_id' order by m.shop_id DESC " .
            $limit;
    $res = $GLOBALS['db']->getAll($sql);


    $store_list = [];
    foreach ($res as $key => $row) {
        $sql = "SELECT count(user_id) as a FROM {pre}collect_store WHERE ru_id=" . $row['ru_id'] . " ";
        $gaze = $GLOBALS['db']->getOne($sql);
        $store_list[$key]['collect_number'] = $gaze;
        $store_list[$key]['goods'] = $goods;
        $store_list[$key]['rec_id'] = $row['rec_id'];
        $store_list[$key]['del'] = url('user/index/delstore', ['rec_id' => $row['rec_id']]);
        $store_list[$key]['shop_id'] = $row['ru_id'];
        $store_list[$key]['store_name'] = get_shop_name($row['ru_id'], 1); //店铺名称
        $store_list[$key]['shop_logo'] = get_image_path($row['shop_logo']);
        $store_list[$key]['count_store'] = $GLOBALS['db']->getOne("SELECT count(*) FROM " . $GLOBALS['ecs']->table('collect_store') . " WHERE ru_id = '" . $row['ru_id'] . "'");
        $store_list[$key]['add_time'] = local_date("Y-m-d", $row['add_time']);
        $store_list[$key]['kf_type'] = $row['kf_type'];
        $store_list[$key]['kf_ww'] = $row['kf_ww'];
        $store_list[$key]['kf_qq'] = $row['kf_qq'];
        $store_list[$key]['ru_id'] = $row['ru_id'];
        $store_list[$key]['brand_thumb'] = get_image_path($row['brand_thumb']);
        // $store_list[$key]['url'] = build_uri('store', array('cid' => 0, 'urid' => $row['ru_id']), $store_list[$key]['store_name']);
        $store_list[$key]['url'] = url('store/index/shop_info', ['id' => $row['ru_id']]);
        $store_list[$key]['merch_cmt'] = get_merchants_goods_comment($row['ru_id']); //商家所有商品评分类型汇总
        $store_list[$key]['commentrank'] = $store_list[$key]['merch_cmt']['cmt']['commentRank']['zconments']['score']; //商品评分
        $store_list[$key]['commentServer'] = $store_list[$key]['merch_cmt']['cmt']['commentServer']['zconments']['score']; //服务评分
        $store_list[$key]['commentdelivery'] = $store_list[$key]['merch_cmt']['cmt']['commentDelivery']['zconments']['score']; //时效评分
        //商品评分
        if ($store_list[$key]['commentrank'] >= 4) {
            $store_list[$key]['rankgoodReview'] = '高';
        } elseif ($store_list[$key]['commentrank'] > 3) {
            $store_list[$key]['rankgoodReview'] = '中';
        } else {
            $store_list[$key]['rankgoodReview'] = '低';
        }
        //服务评分
        if ($store_list[$key]['commentServer'] >= 4) {
            $store_list[$key]['ServergoodReview'] = '高';
        } elseif ($store_list[$key]['commentServer'] > 3) {
            $store_list[$key]['ServergoodReview'] = '中';
        } else {
            $store_list[$key]['ServergoodReview'] = '低';
        }
        //时效评分
        if ($store_list[$key]['commentdelivery'] >= 4) {
            $store_list[$key]['deliverygoodReview'] = '高';
        } elseif ($store_list[$key]['commentdelivery'] > 3) {
            $store_list[$key]['deliverygoodReview'] = '中';
        } else {
            $store_list[$key]['deliverygoodReview'] = '低';
        }
        //        $store_list[$key]['rankgoodReview'] = $store_list[$key]['merch_cmt']['cmt']['commentRank']['zconments']['goodReview']; //商品评分
        //        $store_list[$key]['ServergoodReview'] = $store_list[$key]['merch_cmt']['cmt']['commentServer']['zconments']['goodReview']; //服务评分
        //        $store_list[$key]['deliverygoodReview'] = $store_list[$key]['merch_cmt']['cmt']['commentDelivery']['zconments']['goodReview'];//时效评分

        $store_list[$key]['hot_goods'] = get_user_store_goods_list($row['ru_id'], $region_id, $area_id, 'store_hot');
        $store_list[$key]['new_goods'] = get_user_store_goods_list($row['ru_id'], $region_id, $area_id, 'store_new');
    }


    $arr = ['store_list' => $store_list, 'record_count' => $record_count, 'paper' => $paper, 'size' => $size];

    return $arr;
}

/**
 *  获取指定用户的收藏店铺列表
 *
 * @access  public
 * @param   int     $user_id        用户ID
 * @param   int     $num            列表最大数量
 * @param   int     $start          列表其实位置
 *
 * @return  array   $arr
 */
function get_collection_store($user_id, $record_count, $page, $pageFunc, $size = 5)
{
    //ecmoban模板堂 --zhuo start
    if (!isset($_COOKIE['province'])) {
        $area_array = get_ip_area_name();

        if ($area_array['county_level'] == 2) {
            $date = ['region_id', 'parent_id', 'region_name'];
            $where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
            $city_info = get_table_date('region', $where, $date, 1);

            $date = ['region_id', 'region_name'];
            $where = "region_id = '" . $city_info[0]['parent_id'] . "'";
            $province_info = get_table_date('region', $where, $date);

            $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
            $district_info = get_table_date('region', $where, $date, 1);
        } elseif ($area_array['county_level'] == 1) {
            $area_name = $area_array['area_name'];

            $date = ['region_id', 'region_name'];
            $where = "region_name = '$area_name'";
            $province_info = get_table_date('region', $where, $date);

            $where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
            $city_info = get_table_date('region', $where, $date, 1);

            $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
            $district_info = get_table_date('region', $where, $date, 1);
        }
    }

    $province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $province_info['region_id'];
    $city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $city_info[0]['region_id'];
    $district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $district_info[0]['region_id'];

    cookie('province', $province_id);
    cookie('city', $city_id);
    cookie('district', $district_id);

    $area_info = get_area_info($province_id);
    $area_id = $area_info['region_id'];

    $region_where = "regionId = '$province_id'";
    $date = ['parent_id'];
    $region_id = get_table_date('region_warehouse', $region_where, $date, 2);

    $leftJoin = '';

    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$region_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end

    $collection = new \App\Libraries\Page($record_count, $size, '', 0, 0, $page, $pageFunc, 1);
    $limit = $collection->limit;
    $paper = $collection->fpage([0, 4, 5, 6, 9]);

    $sql = "SELECT m.shoprz_brandName, m.shopNameSuffix, m.shop_id, s.shop_logo, c.ru_id, c.add_time, s.kf_type, s.kf_ww, s.kf_qq, brand_thumb  FROM " . $GLOBALS['ecs']->table('collect_store') . " as c, " . $GLOBALS['ecs']->table('seller_shopinfo') . " as s, " .
            $GLOBALS['ecs']->table('merchants_shop_information') . " as m " .
            " WHERE c.ru_id = s.ru_id AND s.ru_id = m.user_id AND c.user_id = '$user_id' order by m.shop_id DESC " .
            $limit;
    $res = $GLOBALS['db']->getAll($sql);

    $store_list = [];
    foreach ($res as $key => $row) {
        $store_list[$key]['shop_id'] = $row['shop_id'];
        $store_list[$key]['store_name'] = get_shop_name($row['ru_id'], 1); //店铺名称
        $store_list[$key]['shop_logo'] = get_image_path($row['shop_logo']);
        $store_list[$key]['count_store'] = $GLOBALS['db']->getOne("SELECT count(*) FROM " . $GLOBALS['ecs']->table('collect_store') . " WHERE ru_id = '" . $row['ru_id'] . "'");
        $store_list[$key]['add_time'] = local_date("Y-m-d", $row['add_time']);
        $store_list[$key]['kf_type'] = $row['kf_type'];
        $store_list[$key]['kf_ww'] = $row['kf_ww'];
        $store_list[$key]['kf_qq'] = $row['kf_qq'];
        $store_list[$key]['ru_id'] = $row['ru_id'];
        $store_list[$key]['brand_thumb'] = $row['brand_thumb'];
        $store_list[$key]['url'] = build_uri('merchants_store', ['cid' => 0, 'urid' => $row['ru_id']], $store_list[$key]['store_name']);
        $store_list[$key]['merch_cmt'] = get_merchants_goods_comment($row['ru_id']); //商家所有商品评分类型汇总

        $store_list[$key]['hot_goods'] = get_user_store_goods_list($row['ru_id'], $region_id, $area_id, 'store_hot');
        $store_list[$key]['new_goods'] = get_user_store_goods_list($row['ru_id'], $region_id, $area_id, 'store_new');
    }

    $arr = ['store_list' => $store_list, 'record_count' => $record_count, 'paper' => $paper, 'size' => $size];

    return $arr;
}

function get_user_store_goods_list($user_id, $region_id, $area_id, $type = '', $sort = 'last_update', $order = 'DESC', $limit = 'LIMIT 0,10')
{
    $leftJoin = '';
    $where = '';

    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$region_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }

    $sql = 'SELECT g.goods_thumb, g.goods_id, g.goods_name, g.market_price, g.shop_price AS org_price, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
            "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
            'g.promote_start_date,g.promote_end_date' .
            ' FROM ' . $GLOBALS['ecs']->table('goods') . " AS g " .
            $leftJoin .
            " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            " WHERE g.user_id = '$user_id' AND " . $type . " = 1 $where ORDER BY g." . $sort . " $order " . $limit;
    $res = $GLOBALS['db']->getAll($sql);

    $goods_list = [];
    foreach ($res as $key => $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        $goods_list[$row['goods_id']]['goods_id'] = $row['goods_id'];
        $goods_list[$row['goods_id']]['goods_name'] = $row['goods_name'];
        $goods_list[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $goods_list[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
        $goods_list[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
        $goods_list[$row['goods_id']]['url'] = build_uri('goods', ['gid' => $row['goods_id']], $row['goods_name']);
        $goods_list[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_thumb']);

        $mc_all = ments_count_all($row['goods_id']);       //总条数
        $mc_one = ments_count_rank_num($row['goods_id'], 1);  //一颗星
        $mc_two = ments_count_rank_num($row['goods_id'], 2);     //两颗星
        $mc_three = ments_count_rank_num($row['goods_id'], 3);    //三颗星
        $mc_four = ments_count_rank_num($row['goods_id'], 4);  //四颗星
        $mc_five = ments_count_rank_num($row['goods_id'], 5);  //五颗星
        $goods_list[$row['goods_id']]['zconments'] = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);
    }

    return $goods_list;
}

/**
 *  查看此商品是否已进行过缺货登记
 *
 * @access  public
 * @param   int $user_id 用户ID
 * @param   int $goods_id 商品ID
 *
 * @return  int
 */
function get_booking_rec($user_id, $goods_id)
{
    $sql = 'SELECT COUNT(*) ' .
            'FROM ' . $GLOBALS['ecs']->table('booking_goods') .
            "WHERE user_id = '$user_id' AND goods_id = '$goods_id' AND is_dispose = 0";

    return $GLOBALS['db']->getOne($sql);
}

/**
 *  获取指定用户的留言
 *
 * @access  public
 * @param   int $user_id 用户ID
 * @param   int $user_name 用户名
 * @param   int $num 列表最大数量
 * @param   int $start 列表其实位置
 * @return  array   $msg            留言及回复列表
 * @return  string  $order_id       订单ID
 */
function get_message_list($user_id, $user_name, $num, $start, $order_id = 0)
{
    /* 获取留言数据 */
    $msg = [];
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('feedback');
    if ($order_id) {
        $sql .= " WHERE parent_id = 0 AND order_id = '$order_id' AND user_id = '$user_id' ORDER BY msg_time DESC";
    } else {
        $sql .= " WHERE parent_id = 0 AND user_id = '$user_id' AND user_name = '" . $_SESSION['user_name'] . "' AND order_id=0 ORDER BY msg_time DESC";
    }

    $res = $GLOBALS['db']->SelectLimit($sql, $num, $start);
    //$rows = $GLOBALS['db']->getRow($res
    foreach ($res as $rows) {
        /* 取得留言的回复 */
        //if (empty($order_id))
        //{
        $reply = [];
        $sql = "SELECT user_name, user_email, msg_time, msg_content" .
                " FROM " . $GLOBALS['ecs']->table('feedback') .
                " WHERE parent_id = '" . $rows['msg_id'] . "'";
        $reply = $GLOBALS['db']->getRow($sql);

        if ($reply) {
            $msg[$rows['msg_id']]['re_user_name'] = $reply['user_name'];
            $msg[$rows['msg_id']]['re_user_email'] = $reply['user_email'];
            $msg[$rows['msg_id']]['re_msg_time'] = local_date($GLOBALS['_CFG']['time_format'], $reply['msg_time']);
            $msg[$rows['msg_id']]['re_msg_content'] = nl2br(htmlspecialchars($reply['msg_content']));
        }
        //}

        $msg[$rows['msg_id']]['msg_content'] = nl2br(htmlspecialchars($rows['msg_content']));
        $msg[$rows['msg_id']]['msg_time'] = local_date($GLOBALS['_CFG']['time_format'], $rows['msg_time']);
        $msg[$rows['msg_id']]['msg_type'] = $order_id ? $rows['user_name'] : $GLOBALS['_LANG']['type'][$rows['msg_type']];
        $msg[$rows['msg_id']]['msg_title'] = nl2br(htmlspecialchars($rows['msg_title']));
        $msg[$rows['msg_id']]['message_img'] = $rows['message_img'];
        $msg[$rows['msg_id']]['order_id'] = $rows['order_id'];
    }

    return $msg;
}

/**
 *  添加留言
 *
 * @access  public
 * @param   array $message
 *
 * @return  boolen      $bool
 */
function addmg($message)
{
    $res = $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('feedback'), $message, 'INSERT');
    return true;
}

/**
 *  添加留言函数
 *
 * @access  public
 * @param   array $message
 *
 * @return  boolen      $bool
 */
function add_message($message)
{
    $upload_size_limit = $GLOBALS['_CFG']['upload_size_limit'] == '-1' ? ini_get('upload_max_filesize') : $GLOBALS['_CFG']['upload_size_limit'];
    $status = 1 - $GLOBALS['_CFG']['message_check'];

    $last_char = strtolower($upload_size_limit{strlen($upload_size_limit) - 1});

    switch ($last_char) {
        case 'm':
            $upload_size_limit *= 1024 * 1024;
            break;
        case 'k':
            $upload_size_limit *= 1024;
            break;
    }

    if ($message['upload']) {
        if ($_FILES['message_img']['size'] / 1024 > $upload_size_limit) {
            $GLOBALS['err']->add(sprintf($GLOBALS['_LANG']['upload_file_limit'], $upload_size_limit));
            return false;
        }
        $img_name = upload_file($_FILES['message_img'], 'feedbackimg');

        if ($img_name === false) {
            return false;
        }
    } else {
        $img_name = '';
    }

    if (empty($message['msg_title'])) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['msg_title_empty']);

        return false;
    }

    $message['msg_area'] = isset($message['msg_area']) ? intval($message['msg_area']) : 0;
    $sql = "INSERT INTO " . $GLOBALS['ecs']->table('feedback') .
            " (msg_id, parent_id, user_id, user_name, user_email, msg_title, msg_type, msg_status,  msg_content, msg_time, message_img, order_id, msg_area)" .
            " VALUES (NULL, 0, '$message[user_id]', '$message[user_name]', '$message[user_email]', " .
            " '$message[msg_title]', '$message[msg_type]', '$status', '$message[msg_content]', '" . gmtime() . "', '$img_name', '$message[order_id]', '$message[msg_area]')";
    $GLOBALS['db']->query($sql);

    return true;
}

/**
 *  获取用户的tags
 *
 * @access  public
 * @param   int $user_id 用户ID
 *
 * @return array        $arr            tags列表
 */
function get_user_tags($user_id = 0)
{
    if (empty($user_id)) {
        $GLOBALS['error_no'] = 1;

        return false;
    }

    $tags = get_tags(0, $user_id);

    if (!empty($tags)) {
        color_tag($tags);
    }

    return $tags;
}

/**
 *  验证性的删除某个tag
 *
 * @access  public
 * @param   int $tag_words tag的ID
 * @param   int $user_id 用户的ID
 *
 * @return  boolen      bool
 */
function delete_tag($tag_words, $user_id)
{
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('tag') .
            " WHERE tag_words = '$tag_words' AND user_id = '$user_id'";

    return $GLOBALS['db']->query($sql);
}

/**
 *  获取某用户的缺货登记列表
 *
 * @access  public
 * @param   int $user_id 用户ID
 * @param   int $num 列表最大数量
 * @param   int $start 列表其实位置
 *
 * @return  array   $booking
 */
function get_booking_list($user_id, $num, $start)
{
    $booking = [];
    $sql = "SELECT bg.rec_id, bg.goods_id, bg.goods_number, bg.booking_time, bg.dispose_note, g.goods_name, g.goods_thumb " .
            "FROM " . $GLOBALS['ecs']->table('booking_goods') . " AS bg , " . $GLOBALS['ecs']->table('goods') . " AS g" . " WHERE bg.goods_id = g.goods_id AND bg.user_id = '$user_id' ORDER BY bg.booking_time DESC";
    $res = $GLOBALS['db']->SelectLimit($sql, $num, $start);

    foreach ($res as $row) {
        if (empty($row['dispose_note'])) {
            $row['dispose_note'] = 'N/A';
        }
        $booking[] = ['rec_id' => $row['rec_id'],
            'goods_name' => $row['goods_name'],
            'goods_number' => $row['goods_number'],
            'goods_thumb' => $row['goods_thumb'],
            'booking_time' => local_date($GLOBALS['_CFG']['date_format'], $row['booking_time']),
            'dispose_note' => $row['dispose_note'],
            'url' => build_uri('goods', ['gid' => $row['goods_id']], $row['goods_name'])];
    }

    return $booking;
}

/**
 *  获取某用户的缺货登记列表
 *
 * @access  public
 * @param   int $goods_id 商品ID
 *
 * @return  array   $info
 */
function get_goodsinfo($goods_id)
{
    $info = [];
    $sql = "SELECT goods_name FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id'";

    $info['goods_name'] = $GLOBALS['db']->getOne($sql);
    $info['goods_number'] = 1;
    $info['id'] = $goods_id;

    if (!empty($_SESSION['user_id'])) {
        $row = [];
        $sql = "SELECT ua.consignee, ua.email, ua.tel, ua.mobile " .
                "FROM " . $GLOBALS['ecs']->table('user_address') . " AS ua, " . $GLOBALS['ecs']->table('users') . " AS u" .
                " WHERE u.address_id = ua.address_id AND u.user_id = '$_SESSION[user_id]'";
        $row = $GLOBALS['db']->getRow($sql);
        $info['consignee'] = empty($row['consignee']) ? '' : $row['consignee'];
        $info['email'] = empty($row['email']) ? '' : $row['email'];
        $info['tel'] = empty($row['mobile']) ? (empty($row['tel']) ? '' : $row['tel']) : $row['mobile'];
    }

    return $info;
}

/**
 *  验证删除某个收藏商品
 *
 * @access  public
 * @param   int $booking_id 缺货登记的ID
 * @param   int $user_id 会员的ID
 * @return  boolen      $bool
 */
function delete_booking($booking_id, $user_id)
{
    $sql = 'DELETE FROM ' . $GLOBALS['ecs']->table('booking_goods') .
            " WHERE rec_id = '$booking_id' AND user_id = '$user_id'";

    return $GLOBALS['db']->query($sql);
}

/**
 * 添加缺货登记记录到数据表
 * @access  public
 * @param   array $booking
 *
 * @return void
 */
function add_booking($booking)
{
    $sql = "INSERT INTO " . $GLOBALS['ecs']->table('booking_goods') .
            " (user_id, email, link_man, tel, goods_id, goods_desc, goods_number, booking_time, is_dispose, dispose_user, dispose_time, dispose_note)" .
            " VALUES ('$_SESSION[user_id]', '$booking[email]', '$booking[linkman]', " .
            "'$booking[tel]', '$booking[goods_id]', '$booking[desc]', " .
            "'$booking[goods_amount]', '" . gmtime() . "', 0, '', 0, '')";
    return $GLOBALS['db']->query($sql);
}
/**
 * 插入会员账目明细
 *
 * @access  public
 * @param   array $surplus 会员余额信息
 * @param   string $amount 余额
 *
 * @return  int
 */
function insert_user_account($surplus, $amount)
{
    $data['user_id'] = $surplus['user_id'];
    $data['admin_user'] = '';
    $data['amount'] = $amount;
    $data['add_time'] = gmtime();
    $data['paid_time'] = 0;
    $data['admin_note'] = '';
    $data['user_note'] = $surplus['user_note'];
    $data['process_type'] = $surplus['process_type'];
    $data['payment'] = $surplus['payment'];
    $data['is_paid'] = 0;
    $insert_id = $GLOBALS['db']->table('user_account')->data($data)->add();

    return $insert_id;
}

/**
 * 插入会员账目明细扩展字段by wang
 *
 * @access  public
 * @param   array     $user_account_fields  扩展字段数组
 * @return  int
 */
function insert_user_account_fields($user_account_fields)
{
    $sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('user_account_fields') .
            ' (user_id, account_id,bank_number, real_name)' .
            " VALUES ('$user_account_fields[user_id]','$user_account_fields[account_id]', '$user_account_fields[bank_number]','$user_account_fields[real_name]')";

    $GLOBALS['db']->query($sql);
}

/**
 * 更新会员账目明细
 *
 * @access  public
 * @param   array $surplus 会员余额信息
 *
 * @return  int
 */
function update_user_account($surplus)
{
    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('user_account') . ' SET ' .
            "amount     = '$surplus[amount]', " .
            "user_note  = '$surplus[user_note]', " .
            "payment    = '$surplus[payment]' " .
            "WHERE id   = '$surplus[rec_id]'";
    $GLOBALS['db']->query($sql);

    return $surplus['rec_id'];
}

/**
 * 将支付LOG插入数据表
 *
 * @access  public
 * @param   integer $id 订单编号
 * @param   float $amount 订单金额
 * @param   integer $type 支付类型
 * @param   integer $is_paid 是否已支付
 *
 * @return  int
 */
function insert_pay_log($id, $amount, $type = PAY_SURPLUS, $is_paid = 0)
{
    /* $sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('pay_log') . " (order_id, order_amount, order_type, is_paid)" .
      " VALUES  ('$id', '$amount', '$type', '$is_paid')"; */
    $data['order_id'] = $id;
    $data['order_amount'] = $amount;
    $data['order_type'] = $type;
    $data['is_paid'] = $is_paid;
    $insert_id = $GLOBALS['db']->table('pay_log')->data($data)->add();

    return $insert_id;
}

/**
 * 取得上次未支付的pay_lig_id
 *
 * @access  public
 * @param   array $surplus_id 余额记录的ID
 * @param   array $pay_type 支付的类型：预付款/订单支付
 *
 * @return  int
 */
function get_paylog_id($surplus_id, $pay_type = PAY_SURPLUS)
{
    $sql = 'SELECT log_id FROM' . $GLOBALS['ecs']->table('pay_log') .
            " WHERE order_id = '$surplus_id' AND order_type = '$pay_type' AND is_paid = 0";

    return $GLOBALS['db']->getOne($sql);
}

/**
 * 根据ID获取当前余额操作信息
 *
 * @access  public
 * @param   int $surplus_id 会员余额的ID
 *
 * @return  int
 */
function get_surplus_info($surplus_id)
{
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('user_account') .
            " WHERE id = '$surplus_id'";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 取得已安装的支付方式(其中不包括线下支付的)
 * @param   bool $include_balance 是否包含余额支付（冲值时不应包括）
 * @return  array   已安装的配送方式列表
 */
function get_online_payment_list($include_balance = true)
{
    $sql = 'SELECT pay_id, pay_code, pay_name, pay_fee, pay_desc ' .
            'FROM ' . $GLOBALS['ecs']->table('payment') .
            " WHERE enabled = 1 AND is_cod <> 1";
    if (!$include_balance) {
        $sql .= " AND pay_code <> 'balance' ";
    }

    $modules = $GLOBALS['db']->getAll($sql);
    foreach ($modules as $k => $v) {
        $res = $v['pay_code'];
    }

    include_once(BASE_PATH . 'Helpers/compositor.php');

    //ecmoban模板堂 --zhuo
    $arr = [];
    foreach ($modules as $key => $row) {
        $pay_code = substr($row['pay_code'], 0, 4);
        if ($pay_code != 'pay_') {
            $arr[$key]['pay_id'] = $row['pay_id'];
            $arr[$key]['pay_code'] = $row['pay_code'];
            $arr[$key]['pay_name'] = $row['pay_name'];
            $arr[$key]['pay_fee'] = $row['pay_fee'];
            $arr[$key]['pay_desc'] = $row['pay_desc'];
        }
    }

    return $arr;
}

/**
 * 查询会员余额的操作记录
 *
 * @access  public
 * @param   int $user_id 会员ID
 * @param   int $num 每页显示数量
 * @param   int $start 开始显示的条数
 * @return  array
 */
function get_account_log($user_id, $num, $start, $id, $count, $limit)
{
    $account_log = [];
    if (!empty($id)) {
        $sql = "SELECT ua.*, uaf.bank_number FROM " . $GLOBALS['ecs']->table('user_account') . " AS ua" .
                " LEFT JOIN " . $GLOBALS['ecs']->table('user_account_fields') . " AS uaf " .
                " ON ua.id = uaf.account_id " .
                " WHERE ua.user_id = '$user_id' AND ua.id='$id'";
        $sql="select * from " . $GLOBALS['ecs']->table('user_account') . "   WHERE user_id = '$user_id' AND id='$id' ";
        $res = $GLOBALS['db']->getAll($sql);
    } else {
        $sql = 'SELECT * FROM ' .$GLOBALS['ecs']->table('user_account').
           " WHERE user_id = '$user_id'" .
           " AND process_type " . db_create_in([SURPLUS_SAVE, SURPLUS_RETURN]) .
           " ORDER BY add_time DESC".$limit;
        $res = $GLOBALS['db']->getAll($sql);
    }
    if ($res) {
        foreach ($res as $rows) {
            $rows['add_time'] = local_date($GLOBALS['_CFG']['date_format'], $rows['add_time']);
            $rows['admin_note'] = nl2br(htmlspecialchars($rows['admin_note']));
            $rows['short_admin_note'] = ($rows['admin_note'] > '') ? sub_str($rows['admin_note'], 30) : 'N/A';
            $rows['user_note'] = nl2br(htmlspecialchars($rows['user_note']));
            $rows['short_user_note'] = ($rows['user_note'] > '') ? sub_str($rows['user_note'], 30) : 'N/A';
            $rows['pay_status'] = ($rows['is_paid'] == 0) ? L('un_confirm') : L('is_confirm');
            $rows['amount'] = price_format(abs($rows['amount']), false);
            $rows['url'] = url('user/account/accountdetail', ['id'=> $rows['id']]);

            /* 会员的操作类型： 冲值，提现 */
            if ($rows['process_type'] == 0) {
                $rows['type'] = L('surplus_type_0');
            } else {
                $rows['type'] = L('surplus_type_1');
            }
            /* 支付方式的ID */
            $sql = 'SELECT pay_id  FROM ' . $GLOBALS['ecs']->table('payment') .
                    " WHERE pay_name = '$rows[payment]' AND enabled = 1";
            $pid = $GLOBALS['db']->getOne($sql);
            $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('payment') . " WHERE pay_id='$pid' ";
            $ress = $GLOBALS['db']->getRow($sql);
            $rows['pay_fee'] = $ress['pay_fee'];
            $rows['pay_desc'] = $ress['pay_desc'];

            /* 如果是预付款而且还没有付款, 允许付款 */
            if (($rows['is_paid'] == 0) && ($rows['process_type'] == 0)) {
                $rows['handle'] = '<a class="btn-submit box-flex" href="' . url('user/account/pay', ['id' => $rows['id'], 'pid' => $pid]) . '">' . L('pay') . '</a>';
            }

            $account_log[] = $rows;
        }
        $arr = ['log_list' =>$account_log, 'count' => $count];
        return $arr;
    } else {
        return false;
    }
}

/**
 *  删除未确认的会员帐目信息
 *
 * @access  public
 * @param   int $rec_id 会员余额记录的ID
 * @param   int $user_id 会员的ID
 * @return  boolen
 */
function del_user_account($id, $user_id)
{
    $sql = 'DELETE FROM ' . $GLOBALS['ecs']->table('user_account') .
            " WHERE is_paid = 0 AND id = '$id' AND user_id = '$user_id'";

    return $GLOBALS['db']->query($sql);
}

/**
 *  删除未确认的会员帐目的扩展信息
 *
 * @access  public
 * @param   int         $acount_id     会员余额记录的ID
 * @param   int         $user_id    会员的ID
 * @return  boolen
 */
function del_user_account_fields($acount_id, $user_id)
{
    $sql = 'DELETE FROM ' . $GLOBALS['ecs']->table('user_account_fields') .
            " WHERE account_id = '$acount_id' AND user_id = '$user_id'";

    return $GLOBALS['db']->query($sql);
}

/**
 * 查询会员余额的数量
 * @access  public
 * @param   int $user_id 会员ID
 * @return  int
 */
function get_user_surplus($user_id)
{
    $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('account_log') .
            " WHERE user_id = '$user_id'";
    $count = $GLOBALS['db']->getOne($sql);


    $sql = "SELECT user_money FROM " . $GLOBALS['ecs']->table('users') .
            " WHERE user_id = '$user_id'";
    $res = $GLOBALS['db']->getOne($sql);


    return $res;
}

//查询会员冻结资金
function get_user_frozen($user_id)
{
    $sql = "SELECT frozen_money FROM " . $GLOBALS['ecs']->table('users') .
            " WHERE user_id = '$user_id'";
    $res = $GLOBALS['db']->getOne($sql);


    return $res;
}

/**
 * 添加商品标签
 *
 * @access  public
 * @param   integer $id
 * @param   string $tag
 * @return  void
 */
function add_tag($id, $tag)
{
    if (empty($tag)) {
        return;
    }

    $arr = explode(',', $tag);

    foreach ($arr as $val) {
        /* 检查是否重复 */
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table("tag") .
                " WHERE user_id = '" . $_SESSION['user_id'] . "' AND goods_id = '$id' AND tag_words = '$val'";

        if ($GLOBALS['db']->getOne($sql) == 0) {
            $sql = "INSERT INTO " . $GLOBALS['ecs']->table("tag") . " (user_id, goods_id, tag_words) " .
                    "VALUES ('" . $_SESSION['user_id'] . "', '$id', '$val')";
            $GLOBALS['db']->query($sql);
        }
    }
}

/**
 * 标签着色
 *
 * @access   public
 * @param    array
 * @author   Xuan Yan
 *
 * @return   none
 */
function color_tag(&$tags)
{
    $tagmark = [
        ['color' => '#666666', 'size' => '0.8em', 'ifbold' => 1],
        ['color' => '#333333', 'size' => '0.9em', 'ifbold' => 0],
        ['color' => '#006699', 'size' => '1.0em', 'ifbold' => 1],
        ['color' => '#CC9900', 'size' => '1.1em', 'ifbold' => 0],
        ['color' => '#666633', 'size' => '1.2em', 'ifbold' => 1],
        ['color' => '#993300', 'size' => '1.3em', 'ifbold' => 0],
        ['color' => '#669933', 'size' => '1.4em', 'ifbold' => 1],
        ['color' => '#3366FF', 'size' => '1.5em', 'ifbold' => 0],
        ['color' => '#197B30', 'size' => '1.6em', 'ifbold' => 1],
    ];

    $maxlevel = count($tagmark);
    $tcount = $scount = [];

    foreach ($tags as $val) {
        $tcount[] = $val['tag_count']; // 获得tag个数数组
    }
    $tcount = array_unique($tcount); // 去除相同个数的tag

    sort($tcount); // 从小到大排序

    $tempcount = count($tcount); // 真正的tag级数
    $per = $maxlevel >= $tempcount ? 1 : $maxlevel / ($tempcount - 1);

    foreach ($tcount as $key => $val) {
        $lvl = floor($per * $key);
        $scount[$val] = $lvl; // 计算不同个数的tag相对应的着色数组key
    }

    $rewrite = intval($GLOBALS['_CFG']['rewrite']) > 0;

    /* 遍历所有标签，根据引用次数设定字体大小 */
    foreach ($tags as $key => $val) {
        $lvl = $scount[$val['tag_count']]; // 着色数组key

        $tags[$key]['color'] = $tagmark[$lvl]['color'];
        $tags[$key]['size'] = $tagmark[$lvl]['size'];
        $tags[$key]['bold'] = $tagmark[$lvl]['ifbold'];
        if ($rewrite) {
            if (strtolower(CHARSET) !== 'utf-8') {
                $tags[$key]['url'] = 'tag-' . urlencode(urlencode($val['tag_words'])) . '.html';
            } else {
                $tags[$key]['url'] = 'tag-' . urlencode($val['tag_words']) . '.html';
            }
        } else {
            $tags[$key]['url'] = 'search.php?keywords=' . urlencode($val['tag_words']);
        }
    }
    shuffle($tags);
}

/**
 *  获取用户参与活动信息
 *
 * @access  public
 * @param   int $user_id 用户id
 *
 * @return  array
 */
function get_user_prompt($user_id)
{
    $prompt = [];
    $now = gmtime();
    /* 夺宝奇兵 */
    $sql = "SELECT act_id, goods_name, end_time " .
            "FROM " . $GLOBALS['ecs']->table('goods_activity') .
            " WHERE act_type = '" . GAT_SNATCH . "'" .
            " AND (is_finished = 1 OR (is_finished = 0 AND end_time <= '$now')) AND review_status = 3";
    $res = $GLOBALS['db']->query($sql);
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        $act_id = $row['act_id'];
        $result = get_snatch_result($act_id);
        if (isset($result['order_count']) && $result['order_count'] == 0 && $result['user_id'] == $user_id) {
            $prompt[] = [
                'text' => sprintf($GLOBALS['_LANG']['your_snatch'], $row['goods_name'], $row['act_id']),
                'add_time' => $row['end_time']
            ];
        }
        if (isset($auction['last_bid']) && $auction['last_bid']['bid_user'] == $user_id && $auction['order_count'] == 0) {
            $prompt[] = [
                'text' => sprintf($GLOBALS['_LANG']['your_auction'], $row['goods_name'], $row['act_id']),
                'add_time' => $row['end_time']
            ];
        }
    }


    /* 竞拍 */

    $sql = "SELECT act_id, goods_name, end_time " .
            "FROM " . $GLOBALS['ecs']->table('goods_activity') .
            " WHERE act_type = '" . GAT_AUCTION . "'" .
            " AND (is_finished = 1 OR (is_finished = 0 AND end_time <= '$now')) AND review_status = 3";
    $res = $GLOBALS['db']->query($sql);
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        $act_id = $row['act_id'];
        $auction = auction_info($act_id);
        if (isset($auction['last_bid']) && $auction['last_bid']['bid_user'] == $user_id && $auction['order_count'] == 0) {
            $prompt[] = [
                'text' => sprintf($GLOBALS['_LANG']['your_auction'], $row['goods_name'], $row['act_id']),
                'add_time' => $row['end_time']
            ];
        }
    }

    /* 排序 */
    $cmp = create_function('$a, $b', 'if($a["add_time"] == $b["add_time"]){return 0;};return $a["add_time"] < $b["add_time"] ? 1 : -1;');
    usort($prompt, $cmp);

    /* 格式化时间 */
    foreach ($prompt as $key => $val) {
        $prompt[$key]['formated_time'] = local_date($GLOBALS['_CFG']['time_format'], $val['add_time']);
    }

    return $prompt;
}

/**
 *  获取用户评论
 *
 * @access  public
 * @param   int $user_id 用户id
 * @param   int $page_size 列表最大数量
 * @param   int $start 列表起始页
 * @return  array
 */
function get_comment_list($user_id, $page_size, $start)
{
    $sql = "SELECT c.*, g.goods_name AS cmt_name, r.content AS reply_content, r.add_time AS reply_time " .
            " FROM " . $GLOBALS['ecs']->table('comment') . " AS c " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('comment') . " AS r " .
            " ON r.parent_id = c.comment_id AND r.parent_id > 0 AND r.single_id = 0 AND r.dis_id = 0 " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g " .
            " ON c.comment_type=0 AND c.id_value = g.goods_id " .
            " WHERE c.user_id='$user_id'";
    $res = $GLOBALS['db']->SelectLimit($sql, $page_size, $start);

    $comments = [];
    $to_article = [];
    foreach ($res as $row) {
        $row['formated_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
        if ($row['reply_time']) {
            $row['formated_reply_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['reply_time']);
        }
        if ($row['comment_type'] == 1) {
            $to_article[] = $row["id_value"];
        }

        $row['goods_url'] = build_uri('goods', ['gid' => $row['id_value']], $row['goods_name']);
        $comments[] = $row;
    }

    if ($to_article) {
        $sql = "SELECT article_id , title FROM " . $GLOBALS['ecs']->table('article') . " WHERE " . db_create_in($to_article, 'article_id');
        $arr = $GLOBALS['db']->getAll($sql);
        $to_cmt_name = [];
        foreach ($arr as $row) {
            $to_cmt_name[$row['article_id']] = $row['title'];
        }

        foreach ($comments as $key => $row) {
            if ($row['comment_type'] == 1) {
                $comments[$key]['cmt_name'] = isset($to_cmt_name[$row['id_value']]) ? $to_cmt_name[$row['id_value']] : '';
            }
        }
    }

    return $comments;
}

/**
 * 评论晒单
 * @param type $user_id
 * @param type $type count,list标识
 * @param type $sign 0：带评论 1：追加图片 2:已评论
 * @param type $size
 * @param type $start
 * @return type
 */
function get_user_order_comment_list($user_id, $type = 0, $sign = 0, $order_id = 0, $size = 0, $start = 0)
{
    $where = " AND (select count(*) from " . $GLOBALS['ecs']->table('order_info') . " as oi_2 where oi_2.main_order_id = oi.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
    $where .= " AND oi.order_status " . db_create_in([OS_CONFIRMED, OS_SPLITED]) . "  AND oi.shipping_status = '" . SS_RECEIVED . "' AND oi.pay_status " . db_create_in([PS_PAYED, PS_PAYING]);

    if ($order_id > 0) {
        $where = " AND og.order_id = $order_id ";
    } else {
        $where .= " AND og.order_id = oi.order_id ";
    }

    if ($sign == 0) {
        $where .= " AND (SELECT count(*) FROM " . $GLOBALS['ecs']->table('comment') . " AS c WHERE c.comment_type = 0 AND c.id_value = g.goods_id AND c.order_id = og.order_id AND c.parent_id = 0 AND c.user_id = '$user_id') = 0 ";
    } elseif ($sign == 1) {
        $where .= " AND (SELECT count(*) FROM " . $GLOBALS['ecs']->table('comment') . " AS c WHERE c.comment_type = 0 AND c.id_value = g.goods_id AND c.order_id = og.order_id AND c.parent_id = 0 AND c.user_id = '$user_id') > 0 ";
        $where .= " AND (SELECT count(*) FROM " . $GLOBALS['ecs']->table('comment_img') . " AS ci, " . $GLOBALS['ecs']->table('comment') . " AS c" . " WHERE c.comment_type = 0 AND c.id_value = g.goods_id AND ci.order_id = og.order_id AND c.parent_id = 0 AND c.user_id = '$user_id' AND ci.comment_id = c.comment_id ) = 0 ";
    } elseif ($sign == 2) {
        $where .= " AND (SELECT count(*) FROM " . $GLOBALS['ecs']->table('comment') . " AS c WHERE c.comment_type = 0 AND c.id_value = g.goods_id AND c.rec_id = og.rec_id AND c.parent_id = 0 AND c.user_id = '$user_id') > 0 ";
        $where .= " AND (SELECT count(*) FROM " . $GLOBALS['ecs']->table('comment_img') . " AS ci, " . $GLOBALS['ecs']->table('comment') . " AS c" . " WHERE c.comment_type = 0 AND c.id_value = g.goods_id AND ci.order_id = og.order_id AND c.parent_id = 0 AND c.user_id = '$user_id' AND ci.comment_id = c.comment_id ) > 0 ";
    }

    if ($type == 1) {
        $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . " AS oi ON og.order_id = oi.order_id " .
            "LEFT JOIN  " . $GLOBALS['ecs']->table('goods') . " AS g ON og.goods_id = g.goods_id " .
            "WHERE og.goods_id = g.goods_id AND oi.user_id = '$user_id' $where GROUP BY oi.order_id ORDER BY oi.add_time DESC";
        $arr = $GLOBALS['db']->getOne($sql);
    } else {
        $sql = "SELECT og.rec_id, oi.order_sn, og.order_id, og.goods_id, og.goods_name, oi.add_time,oi.extension_code, oi.extension_id, g.goods_thumb, g.goods_product_tag, og.ru_id FROM " .
            $GLOBALS['ecs']->table('order_goods') . " AS og " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . " AS oi ON og.order_id = oi.order_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON og.goods_id = g.goods_id " .
            "WHERE og.goods_id = g.goods_id AND oi.user_id = '$user_id' $where GROUP BY oi.order_id ORDER BY oi.add_time DESC";

        if ($size > 0) {
            $res = $GLOBALS['db']->SelectLimit($sql, $size, $start);
        } else {
            $res = $GLOBALS['db']->query($sql);
        }

        $arr = [];
        foreach ($res as $row) {
            $row['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            $row['goods_thumb'] = get_image_path($row['goods_thumb']);
            $row['impression_list'] = !empty($row['goods_product_tag']) ? explode(',', $row['goods_product_tag']) : [];

            $mode = $row['extension_code'];
            if ($mode == 'group_buy') {
                $mode = 'groupbuy';
            }

            $row['goods_url']       = build_uri($mode, array('id'=>$row['extension_id'],'cid'=>$row['extension_id'], 'gbid'=>$row['extension_id'], 'presaleid'=>$row['extension_id']));

            //订单商品评论信息
            $row['comment'] = get_order_goods_comment($row['goods_id'], $row['rec_id'], $user_id);
            $arr[] = $row;
        }
    }

    //get_print_r($arr);
    return $arr;
}

function get_order_goods_comment($goods_id, $rec_id, $user_id)
{
    $sql = "SELECT c.comment_id, c.comment_rank, c.content, c.id_value, c.order_id, c.user_id, c.goods_tag FROM " . $GLOBALS['ecs']->table('comment') .
            " AS c WHERE c.comment_type = 0 AND c.id_value = '$goods_id' AND c.rec_id = '$rec_id' AND c.parent_id = 0 AND c.user_id = '$user_id'";
    $res = $GLOBALS['db']->getRow($sql);

    $res['content'] = nl2br(str_replace('\n', '<br />', htmlspecialchars($res['content'])));

    if ($res['goods_tag']) {
    }
    $res['goods_tag'] = !empty($res['goods_tag']) ? explode(',', $res['goods_tag']) : [];
    $img_list = get_img_list($goods_id, $res['comment_id']);
    $res['img_list'] = $img_list;

    return $res;
}


/**  已绑定储值卡列表
 *
 * @access  public
 * @param   int         $user_id         用户ID
 * @param   int         $num             列表显示条数
 * @param   int         $start           显示起始位置
 *
 * @return  array       $arr             储值卡列表
 */
function get_user_bind_vc_list($user_id = 0, $page = 1, $type = 0, $pageFunc = '', $amount = 0, $offset = 4)
{
    $limit = ' limit ' . (($page - 1) * $offset) . ',' . $offset;
    $sql = "SELECT t.name, t.use_condition, v.vc_value, t.is_rec, v.vid, v.value_card_sn, v.card_money, v.bind_time,v.end_time FROM " . $GLOBALS['ecs']->table('value_card') . " AS v " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('value_card_type') . " AS t ON v.tid = t.id " .
            " WHERE v.user_id = '$user_id' order by v.vid DESC ";
    $counts = $GLOBALS['db']->getAll($sql);
    $counts = is_array($counts) ? count($counts) : 0;

    $sql = "SELECT t.name, t.use_condition, v.vc_value, t.is_rec, v.vid, v.value_card_sn, v.card_money, v.bind_time,v.end_time FROM " . $GLOBALS['ecs']->table('value_card') . " AS v " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('value_card_type') . " AS t ON v.tid = t.id " .
            " WHERE v.user_id = '$user_id' order by v.vid DESC ".$limit;
    $res = $GLOBALS['db']->getAll($sql);

    $now = gmtime();
    foreach ($res as $key => $row) {
        if ($now > $row['end_time']) {
            $res[$key]['status'] = false;
        } else {
            $res[$key]['status'] = true;
        }
        /* 先判断是否被使用，然后判断是否开始或过期 */
        $res[$key]['name'] = $row['name'];
        $res[$key]['vid'] = $row['vid'];
        $res[$key]['value_card_sn'] = $row['value_card_sn'];
        $res[$key]['vc_value'] = price_format($row['vc_value']);
        $res[$key]['use_condition'] = condition_format($row['use_condition']);
        $res[$key]['is_rec'] = $row['is_rec'];
        $res[$key]['card_money'] = price_format($row['card_money']);
        $res[$key]['bind_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['bind_time']);
        $res[$key]['end_time'] = local_date('Y-m-d H:i:s', $row['end_time']);
        $res[$key]['detail_url'] = url('user/account/value_card_info', ['vid'=>$row['vid']]);
        $res[$key]['pay_url'] = url('user/account/pay_value_card', ['vid'=>$row['vid']]);
    }
    $result = ['list' => $res, 'totalPage' => ceil($counts / $offset)];
    return $result;
}

/* 使用限制条件格式化 */
function condition_format($conditon)
{
    switch ($conditon) {
        case 1:
        return '指定分类';
        break;
        case 2:
        return '指定商品';
        break;
        case 0:
        return '所有商品';
        default:
        return 'N/A';
        break;
    }
}

/* 取得储值卡使用限制说明 */
function get_explain($vid)
{
    $sql = " SELECT use_condition, use_merchants, spec_goods, spec_cat FROM ".$GLOBALS['ecs']->table('value_card_type')." AS t LEFT JOIN ".$GLOBALS['ecs']->table('value_card').
           " AS v ON v.tid = t.id WHERE vid = '$vid' ";
    $row = $GLOBALS['db']->getRow($sql);
    if ($row['use_condition'] == 0) {
        $explain = $GLOBALS['_LANG']['all_goods_explain'];
    } elseif ($row['use_condition'] == 1) {
        $sql = " SELECT cat_name,cat_id FROM ".$GLOBALS['ecs']->table('category')." WHERE cat_id IN($row[spec_cat]) ";
        $res = $GLOBALS['db']->getAll($sql);

        $explain = str_replace('%', cat_format($res), $GLOBALS['_LANG']['spec_cat_explain']);
    } elseif ($row['use_condition'] == 2) {
        $explain['explain'] = str_replace('%', $row['spec_goods'], $GLOBALS['_LANG']['spec_goods_explain']);
        $explain['goods_ids'] = $row['spec_goods'];
    } else {
        $explain = '';
    }
    $other_explain = '';
    if ($row['use_merchants'] == 'all') {
        $other_explain = ' | '.$GLOBALS['_LANG']['all_merchants'];
    } elseif ($row['use_merchants'] == 'self') {
        $other_explain = ' | '.$GLOBALS['_LANG']['self_merchants'];
    } elseif (!empty($row['use_merchants'])) {
        $other_explain = ' | '.$GLOBALS['_LANG']['assign_merchants'];
    }
    if ($other_explain) {
        return $explain.$other_explain;
    } else {
        return $explain;
    }
}

/**  指定储值卡使用详情
 *
 * @access  public
 * @param   int     $vid   储值卡编号
 * @return  array   $arr   储值卡使用详情列表
 */
function value_card_use_info($vc_id = 0, $page, $offset=5)
{
    $limit = ' limit ' . (($page - 1) * $offset) . ',' . $offset;
    $sql =  "SELECT o.order_sn, r.rid, r.use_val, r.add_val, r.record_time FROM " .$GLOBALS['ecs']->table('value_card_record'). " AS r ".
            " LEFT JOIN ". $GLOBALS['ecs']->table('order_info') ." AS o ON r.order_id = o.order_id ".
            " WHERE r.vc_id = '$vc_id' order by r.rid DESC ";
    $counts = $GLOBALS['db']->getAll($sql);
    $counts = is_array($counts) ? count($counts) : 0;
    $sql =  "SELECT o.order_sn, r.rid, r.use_val, r.add_val, r.record_time FROM " .$GLOBALS['ecs']->table('value_card_record'). " AS r ".
            " LEFT JOIN ". $GLOBALS['ecs']->table('order_info') ." AS o ON r.order_id = o.order_id ".
            " WHERE r.vc_id = '$vc_id' order by r.rid DESC".$limit;

    $res = $GLOBALS['db']->getAll($sql);
    foreach ($res as $key=>$row) {
        $res[$key]['rid']    = $row['rid'];
        $res[$key]['order_sn']    = $row['order_sn'];
        $res[$key]['use_val']    = price_format($row['use_val']);
        $res[$key]['add_val']    = price_format($row['add_val']);
        $res[$key]['record_time']     = local_date($GLOBALS['_CFG']['time_format'], $row['record_time']);
    }

    $result = ['list' => $res, 'totalPage' => ceil($counts / $offset)];
    return $result;
}

/**
 *  给指定用户添加一张储值卡
 *
 * @access  public
 * @param   int         $user_id        用户ID
 * @param   string      $value_card       储值卡序列号
 *
 * @return  boolen      $result
 */
function add_value_card($user_id, $value_card, $password)
{
    /* 查询储值卡序列号是否已经存在 */
    $sql = "SELECT vid, tid, value_card_sn, user_id, end_time FROM " . $GLOBALS['ecs']->table('value_card') .
            " WHERE value_card_sn = '$value_card' AND value_card_password = '$password'";

    $row = $GLOBALS['db']->getRow($sql);

    if ($row) {
        if ($row['user_id'] == 0) {
            //储值卡未被绑定
            $sql = "SELECT vc_indate, vc_limit " .
                    " FROM " . $GLOBALS['ecs']->table('value_card_type') .
                    " WHERE id = '" . $row['tid'] . "'";

            $vc_type = $GLOBALS['db']->getRow($sql);

            if ($row['end_time']) {
                if (gmtime() > $row['end_time']) {
                    $GLOBALS['err']->add($GLOBALS['_LANG']['vc_use_expire']);
                    return 1;
                }
            } else {
                $end_time = " , end_time = '" . local_strtotime("+" . $vc_type['vc_indate'] . " months ") . "' ";
            }

            $sql = " SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('value_card') . " WHERE user_id = '$user_id' AND tid = '$row[tid]' ";
            $limit = $GLOBALS['db']->getOne($sql);
            if ($limit >= $vc_type['vc_limit']) {
                $GLOBALS['err']->add($GLOBALS['_LANG']['vc_limit_expire']);
                return 5;
            }

            $sql = "UPDATE " . $GLOBALS['ecs']->table('value_card') . " SET user_id = '$user_id', bind_time = '" . gmtime() . "'" . $end_time .
                    " WHERE vid = '$row[vid]' ";
            $result = $GLOBALS['db']->query($sql);

            if ($result) {
                return 0;
            } else {
                return $GLOBALS['db']->errorMsg();
            }
        } else {
            if ($row['user_id'] == $user_id) {
                //储值卡已添加。
                $GLOBALS['err']->add($GLOBALS['_LANG']['vc_is_used']);
                return 2;
            } else {
                //储值卡已被绑定。
                $GLOBALS['err']->add($GLOBALS['_LANG']['vc_is_used_by_other']);
                return 3;
            }
        }
    } else {
        //储值卡不存在
        return 4;
    }
}

/**
 *  使用一张充值卡
 *
 * @access  public
 * @param   int         $user_id        用户ID
 * @param   string      $value_card       储值卡序列号
 *
 * @return  boolen      $result
 */
function use_pay_card($user_id, $vid, $pay_card, $password)
{
    /* 查询储值卡序列号是否已经存在 */
    $sql = "SELECT p.id, p.c_id, p.card_number, p.user_id, pt.type_money FROM " . $GLOBALS['ecs']->table('pay_card') . " AS p LEFT JOIN " .
            $GLOBALS['ecs']->table('pay_card_type') . " AS pt ON pt.type_id = p.c_id " .
            " WHERE p.card_number = '$pay_card' AND p.card_psd = '$password'";
    $row = $GLOBALS['db']->getRow($sql);

    $sql = " SELECT t.is_rec FROM " . $GLOBALS['ecs']->table('value_card_type') . " AS t " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('value_card') . " AS v ON v.tid = t.id " .
            " WHERE v.vid = '$vid'  ";
    $is_rec = $GLOBALS['db']->getOne($sql);

    if ($row) {
        if ($row['user_id'] == 0 && $is_rec) {
            //储值卡未被绑定
            $sql = "SELECT use_end_date " .
                    " FROM " . $GLOBALS['ecs']->table('pay_card_type') .
                    " WHERE type_id = '" . $row['c_id'] . "'";

            $pc_type = $GLOBALS['db']->getRow($sql);

            $now = gmtime();
            if ($now > $pc_type['use_end_date']) {
                return 3;
            }

            $sql = "UPDATE " . $GLOBALS['ecs']->table('pay_card') . " SET user_id = '$user_id', used_time = '" . gmtime() . "' " .
                    "WHERE id = '$row[id]'";
            $result = $GLOBALS['db']->query($sql);

            if ($result) {
                $sql = " UPDATE " . $GLOBALS['ecs']->table('value_card') . " SET card_money = card_money + " . $row['type_money'] .
                        " WHERE vid = '$vid' ";

                $res = $GLOBALS['db']->query($sql);

                if ($res) {
                    $sql = " INSERT INTO " . $GLOBALS['ecs']->table('value_card_record') . " (vc_id, add_val, record_time) VALUE ('$vid', '$row[type_money]', '" . gmtime() . "' ) ";
                    $GLOBALS['db']->query($sql);
                    return 0;
                } else {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('pay_card') . " SET user_id = 0, used_time = '' " .
                            "WHERE id = '$row[id]'";
                    $GLOBALS['db']->query($sql);

                    return $GLOBALS['db']->errorMsg();
                }
            } else {
                return $GLOBALS['db']->errorMsg();
            }
        } else {
            //充值卡已使用或改储值卡无法被充值
            return 2;
        }
    } else {
        //储值卡不存在
        return 1;
    }
}

/**
 *  储值卡的信息
 */
function value_cart_info($vcid, $user_id)
{
    $sql = "SELECT t.name, t.use_condition,v.user_id,   v.vc_value, t.is_rec, v.vid, v.value_card_sn, v.card_money, v.bind_time,v.end_time FROM " . $GLOBALS['ecs']->table('value_card') . " AS v " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('value_card_type') . " AS t ON v.tid = t.id " .
            " WHERE v.vid = '$vcid'";
    $info=$GLOBALS['db']->getRow($sql);
    return $info;
}

function get_seller_domain_url($ru_id = 0, $build_uri = [])
{
    $build_uri['cid'] = isset($build_uri['cid']) ? $build_uri['cid'] : 0;
    $build_uri['urid'] = isset($build_uri['urid']) ? $build_uri['urid'] : 0;
    $append = isset($build_uri['append']) ? $build_uri['append'] : '';
    unset($build_uri['append']);

    $res = get_seller_domain_info($ru_id);

    $res['seller_url'] = $res['domain_name'];

    if ($res['domain_name'] && $res['is_enable']) {
        if ($build_uri['cid']) {
            $build_uri['domain_name'] = $res['domain_name'];
            $res['domain_name'] = get_return_store_url($build_uri, $append);
        } else {
            $res['domain_name'] = $res['domain_name'];
        }

        $res['domain_name'] = $res['domain_name'];
    } else {
        $res['domain_name'] = get_return_store_url($build_uri, $append);
    }

    return $res;
}
