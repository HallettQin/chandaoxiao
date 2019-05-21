<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/3
 * Time: 4:03
 */
/*
 * 取得活动列表
 * @return   array
 */
function activity_list($cat_id,  $mode = '', $sort = 'act_id', $order = 'DESC', $page = 1, $size = 10) {
    $now = gmtime();

    $where = '';
    if ($cat_id > 0) {
        $children = get_children($cat_id);
        $where .= ' AND '.$children;
    }


    if ($mode == 'presale') {
        //预售
        $table = $GLOBALS['ecs']->table('presale_activity');
        $where .= ' AND a.start_time < '.$now .' AND a.end_time > '.$now;
    } elseif ($mode == 'groupbuy') {
        //团购
        $where .= " AND a.act_type = " . GAT_GROUP_BUY;
        $table = $GLOBALS['ecs']->table('goods_activity');
        $where .= ' AND a.start_time < '.$now .' AND a.end_time > '.$now;
    } elseif ($mode == 'sample') {
        //样品
        $table = $GLOBALS['ecs']->table('sample_activity');
    } elseif ($mode == 'wholesale') {
        //批发
        $table = $GLOBALS['ecs']->table('wholesale');
    }

    //by zxk 添加排序
    $case = '';
    if (in_array($mode, ['groupbuy', 'presale'])) {
        $case = '(case when end_time >= '.$now.' then 0 else 1 end) is_end,';
        $sort = 'is_end';
    }

    $sql = "SELECT COUNT(*) FROM (SELECT g.goods_id as total FROM " .
        $table . " AS a " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON a.goods_id = g.goods_id " .
        " WHERE g.goods_id > 0  $where AND a.review_status > 2 group by a.goods_id order by act_id desc limit 0, 1000000) as ag ";

    $total = $GLOBALS['db']->getOne($sql);
    $total ? $total : 0;


    $sql = "SELECT * FROM (SELECT a.*, $case g.goods_thumb, g.goods_img, g.shop_price, g.market_price, g.sales_volume FROM " .
        $table . " AS a " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON a.goods_id = g.goods_id " .
        " WHERE g.goods_id > 0 $where AND a.review_status > 2 ORDER BY $sort $order limit 0, 1000000) AS ag GROUP BY ag.goods_id LIMIT " . ($page - 1) * $size . ",  $size";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $key => $row) {
        $res[$key]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $res[$key]['goods_img'] = get_image_path($row['goods_img']);

        $res[$key]['url'] = build_uri($mode, ['r' => 'index/detail', 'id' => $row['act_id'], 'gbid' => $row['act_id']]);
        if ($mode != 'wholesale') {
            $ext_info = unserialize($row['ext_info']);
            $res[$key] = array_merge($res[$key], $ext_info);

            $nowprice = $ext_info['price_ladder'][0]['price']; //现价
            $nowprice = price_format($nowprice);
        } else {
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

            $nowprice = $res[$key]['volume_price'];
        }



        $stat = activity_stat($row['act_id'], $mode);
        $res[$key]['cur_amount'] = $stat['total_goods'];         // 当前数量

        $res[$key]['price'] = $nowprice;

        if (in_array($mode, ['groupbuy', 'presale'])) {
            $res[$key]['formated_end_date'] = activitydate($row['end_time']);
            //是否结束
            $res[$key]['is_end'] = $now > $row['end_time'] ? 1 : 0;
        }
        /* 格式化时间 */
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

    if ($mode == 'groupbuy') {
        $mode = 'group_buy';
    }

    /* 取得团购活动商品ID */
    $sql = "SELECT goods_id " .
        "FROM " . $table.
        "WHERE review_status = 3 AND act_id = '$act_id' " .
        $where;
    $goods_id = $GLOBALS['db']->getOne($sql);


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

function activitydate($time = null)
{
    $text = '';
    $t = $time - gmtime(); //时间差 （秒）
    if ($t <= 0) {
        return 1;
    }
    $y = date('Y', $time) - date('Y', gmtime());//是否跨年
    switch ($t) {
        case $t == 0:
            $text = '刚刚';
            break;
        case $t < 60:
            $text = $t . '秒'; // 一分钟内
            break;
        case $t < 60 * 60:
            $text = floor($t / 60) . '分'; //一小时内
            break;
        case $t < 60 * 60 * 24:
            $text = floor($t / (60 * 60)) . '时'; // 一天内
            break;
        default:
            $text = floor($t / (60 * 60 * 24)) . '天'; //一年以前
            break;
    }

    return $text;
}