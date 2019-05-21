<?php
/*
 * 取得秒杀活动的时间表
 */
function get_seckill_time()
{
    $now = gmtime();
    $day = 24 * 60 * 60;
    $date_begin = local_strtotime(local_date('Ymd'));
    $date_next = local_strtotime(local_date('Ymd')) + $day;
    $sql = "SELECT id,title, begin_time, end_time FROM {pre}seckill_time_bucket ORDER BY begin_time ASC ";
    $stb = $GLOBALS['db']->getAll($sql);
    $arr = [];
    if ($stb) {
        foreach ($stb as $k => $v) {
            $v['local_end_time'] = local_strtotime($v['end_time']);
            if ($v['local_end_time'] > $now) {
                $arr[$k]['id'] = $v['id'];
                $arr[$k]['title'] = $v['title'];
                $arr[$k]['status'] = false;
                $arr[$k]['is_end'] = false;
                $arr[$k]['soon'] = false;
                $arr[$k]['begin_time'] = $begin_time = local_strtotime($v['begin_time']);
                $arr[$k]['end_time'] = $end_time = local_strtotime($v['end_time']);
                $arr[$k]['frist_end_time'] = local_date('Y-m-d H:i:s', $v['end_time']);
                if ($begin_time < $now && $end_time > $now) {
                    $arr[$k]['status'] = true;
                }
                if ($end_time < $now) {
                    $arr[$k]['is_end'] = true;
                }
                if ($begin_time > $now) {
                    $arr[$k]['soon'] = true;
                }
            }
        }
        if (count($arr) > 4) {
            $arr = array_slice($arr, 0, 4);
        }
        if (count($arr) < 4) {
            if (count($arr) == 0) {
                $stb = array_slice($stb, 0, 4);
            }
            if (count($arr) == 1) {
                $stb = array_slice($stb, 0, 3);
            }
            if (count($arr) == 2) {
                $stb = array_slice($stb, 0, 2);
            }
            if (count($arr) == 3) {
                $stb = array_slice($stb, 0, 1);
            }
            foreach ($stb as $k => $v) {
                $arr['tmr' . $k]['id'] = $v['id'];
                $arr['tmr' . $k]['title'] = $v['title'];
                $arr['tmr' . $k]['status'] = false;
                $arr['tmr' . $k]['is_end'] = false;
                $arr['tmr' . $k]['soon'] = true;
                $arr['tmr' . $k]['begin_time'] = local_strtotime($v['begin_time']) + $day;
                $arr['tmr' . $k]['end_time'] = local_strtotime($v['end_time']) + $day;
                $arr['tmr' . $k]['frist_end_time'] = date('Y-m-d H:i:s', $v['end_time'] + $day);
                $arr['tmr' . $k]['tomorrow'] = 1;
            }
        }
    }

    return $arr;
}

//秒杀日期内的商品
function seckill_goods_results($id = '', $page = 1, $offset = 5, $tomorrow)
{
    $date_begin = local_strtotime(local_date('Ymd')) + $day;
    $sql = "SELECT GROUP_CONCAT(s2.sec_id) AS sec_id FROM " . $GLOBALS[ecs]->table('seckill') . " as s2 WHERE s2.begin_time <= '$date_begin' AND s2.acti_time >= '$date_begin' AND s2.review_status = 3 ORDER BY s2.acti_time ASC LIMIT 1";

    $seckill = $GLOBALS['db']->getRow($sql);

    if ($seckill['sec_id']) {
        $where .= " AND s.sec_id IN(" . $seckill['sec_id'] . ")";
    } else {
        $where .= " AND s.sec_id IN(0)";
    }

    $offset = 5;
    $limit = ' limit ' . (($page - 1) * $offset) . ',' . $offset;
    $sql = " SELECT g.goods_id, g.goods_thumb, g.shop_price, g.market_price, g.goods_name, sg.id, sg.sec_price, sg.sec_num, sg.sec_limit, stb.begin_time, stb.end_time, s.sec_id, s.acti_title, s.acti_time FROM " . $GLOBALS['ecs']->table('seckill_goods') .
        " AS sg LEFT JOIN " . $GLOBALS['ecs']->table('seckill_time_bucket') . " AS stb ON sg.tb_id = stb.id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('seckill') . " AS s ON s.sec_id = sg.sec_id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON sg.goods_id = g.goods_id " .
        " WHERE s.is_putaway = 1 AND s.review_status = 3 AND s.begin_time <= '$date_begin' AND stb.id = '$id' " . $where . " ORDER BY stb.begin_time ASC ";
    $counts = $GLOBALS['db']->getAll($sql);
    $counts = is_array($counts) ? count($counts) : 0;
    $sql = " SELECT g.goods_id, g.goods_thumb, g.shop_price, g.market_price, g.goods_name, sg.id, sg.sec_price, sg.sec_num, sg.sec_limit, stb.begin_time, stb.end_time, s.sec_id, s.acti_title, s.acti_time FROM " . $GLOBALS['ecs']->table('seckill_goods') .
        " AS sg LEFT JOIN " . $GLOBALS['ecs']->table('seckill_time_bucket') . " AS stb ON sg.tb_id = stb.id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('seckill') . " AS s ON s.sec_id = sg.sec_id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON sg.goods_id = g.goods_id " .
        $left_join .
        " WHERE s.is_putaway = 1 AND s.review_status = 3 AND s.begin_time <= '$date_begin' AND stb.id = '$id' " . $where . " ORDER BY stb.begin_time ASC " . $limit;
    $res = $GLOBALS['db']->getAll($sql);

    $now = gmtime();
    $tmr = 86400;
    if ($res) {
        foreach ($res as $k => $v) {
            if ($tomorrow == 1) {
                $res[$k]['begin_time'] = local_strtotime($v['begin_time']) + $tmr;
                $res[$k]['end_time'] = local_strtotime($v['end_time']) + $tmr;
            } else {
                $res[$k]['begin_time'] = local_strtotime($v['begin_time']);
                $res[$k]['end_time'] = local_strtotime($v['end_time']);
            }
            if ($res[$k]['begin_time'] < $now && $res[$k]['end_time'] > $now) {
                $res[$k]['status'] = true;
            }
            if ($res[$k]['end_time'] < $now) {
                $res[$k]['is_end'] = true;
            }
            if ($res[$k]['begin_time'] > $now) {
                $res[$k]['soon'] = true;
            }
            $res[$k]['data_end_time'] = local_date('H:i:s', $res[$k]['begin_time']);
            $res[$k]['sec_price_formated'] = price_format($v['sec_price']);
            $res[$k]['market_price_formated'] = price_format($v['market_price']);
            $res[$k]['sales_volume'] = sec_goods_stats($v['id']);
            $res[$k]['valid_goods'] = $res[$k]['sales_volume']['valid_goods'];
            $res[$k]['percent'] = ceil($res[$k]['sales_volume']['valid_goods'] / ($v['sec_num'] + $res[$k]['sales_volume']['valid_goods']) * 100);
            $res[$k]['goods_thumb'] = get_image_path($v['goods_thumb']);
            $res[$k]['url'] = url('seckill/index/detail', ['id' => $v[id], 'tmr' => $tomorrow]);
        }
    }
    $result = ['list' => $res, 'totalPage' => ceil($counts / $offset)];
    return $result;
}

/**
 * 取得秒杀活动的状态
 * @param   int $seckill_id 秒杀活动id
 * @param   int $current_num 本次购买数量（计算当前价时要加上的数量）
 * @return  array
 *                  status          状态：
 */
function seckill_status($begin_time, $end_time)
{
    $now = gmtime();
    $arr = [];
    $arr['status'] = false;
    $arr['is_end'] = false;
    $arr['soon'] = false;
    if ($begin_time < $now && $end_time > $now) {
        $arr['status'] = true;
    }
    if ($end_time < $now) {
        $arr['is_end'] = true;
    }
    if ($end_time > $now) {
        $arr['soon'] = true;
    }

    return $arr;
}

/**
 * 取得秒杀活动信息 取得秒杀活动商品详情
 * @param   int $seckill_id 秒杀活动id
 * @param   int $current_num 本次购买数量（计算当前价时要加上的数量）
 * @return  array
 *                  status          状态：
 */
function seckill_info($seckill_id, $current_num = 0, $path = '', $tomorrow)
{
    $where = '';
    if (empty($path)) {
        $where = " AND b.review_status = 3 ";
    }
    $seckill_id = intval($seckill_id);
    $sql = " SELECT g.*, sg.*, s.*, stb.begin_time, stb.end_time FROM " . $GLOBALS['ecs']->table('seckill_goods') . " AS sg " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.goods_id = sg.goods_id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('seckill_time_bucket') . " AS stb ON sg.tb_id = stb.id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('seckill') . " AS s ON sg.sec_id = s.sec_id " .
        " WHERE sg.id = $seckill_id AND s.is_putaway = 1 AND s.review_status = 3  ";
    $seckill = $GLOBALS['db']->getRow($sql);
    /* 如果为空，返回空数组 */
    if (empty($seckill)) {
        return [];
    }
    $now = gmtime();
    $tmr = 0;
    if ($tomorrow == 1) {
        $tmr = 86400;
    }
    $begin_time = local_strtotime($seckill['begin_time']) + $tmr;
    $end_time = local_strtotime($seckill['end_time']) + $tmr;


    $now = gmtime();
    if ($begin_time < $now && $end_time > $now) {
        $seckill['status'] = true;
    } else {
        $seckill['status'] = false;
    }
    $seckill['is_end'] = $now > $end_time ? 1 : 0;
    $stat = sec_goods_stats($seckill_id);
    $seckill = array_merge($seckill, $stat);
    $seckill['rz_shopName'] = get_shop_name($seckill['user_id'], 1); //店铺名称
    $seckill['goods_thumb'] = get_image_path($seckill['goods_thumb']);
    $seckill['goods_img'] = get_image_path($seckill['goods_img']);
    $build_uri = [
        'urid' => $seckill['user_id'],
        'append' => $seckill['rz_shopName']
    ];
    /* 格式化时间 如果活动没有开始那么计算的时间是按照开始时间来计算
     */
    if (!$seckill['is_end'] && !$seckill['status']) {
        $end_time = $begin_time;
    }
    $seckill['formated_start_date'] = local_date('Y-m-d H:i:s', $begin_time);
    $seckill['formated_end_date'] = local_date('Y-m-d H:i:s', $end_time);

    //    $domain_url = get_seller_domain_url($seckill['user_id'], $build_uri);
    $seckill['store_url'] = $domain_url['domain_name'];

    $seckill['shopinfo'] = get_shop_name($seckill['user_id'], 2);
    $seckill['shopinfo']['brand_thumb'] = str_replace(['../'], '', $seckill['shopinfo']['brand_thumb']);
    if ($seckill['user_id'] == 0) {
        //        $seckill['brand'] = get_brand_url($seckill['brand_id']);
    }

    //OSS文件存储ecmoban模板堂 --zhuo start
    if ($GLOBALS['_CFG']['open_oss'] == 1) {
        $bucket_info = get_bucket_info();
        if ($seckill['goods_desc']) {
            $desc_preg = get_goods_desc_images_preg($bucket_info['endpoint'], $seckill['goods_desc']);
            $seckill['goods_desc'] = $desc_preg['goods_desc'];
        }
    }
    //OSS文件存储ecmoban模板堂 --zhuo end
    return $seckill;
}

/*
 * 取得秒杀活动商品统计信息
 * @param   int     $sec_id   秒杀活动ID
 * @return  array   统计信息
 *                  total_order     总订单数
 *                  total_goods     总商品数
 *                  valid_order     有效订单数
 *                  valid_goods     有效商品数
 */

function sec_goods_stats($sec_id)
{
    $sec_id = intval($sec_id);
    /* 取得秒杀活动商品ID */
    $sql = "SELECT goods_id " .
        "FROM " . $GLOBALS['ecs']->table('seckill_goods') .
        "WHERE id = '$sec_id' ";
    $sec_goods_id = $GLOBALS['db']->getOne($sql);

    /* 取得总订单数和总商品数 */
    $sql = "SELECT COUNT(*) AS total_order, SUM(g.goods_number) AS total_goods " .
        "FROM " . $GLOBALS['ecs']->table('order_info') . " AS o, " .
        $GLOBALS['ecs']->table('order_goods') . " AS g " .
        " WHERE o.order_id = g.order_id " .
        " AND g.extension_code = 'seckill" . $sec_id . "' " .
        " AND g.goods_id = '$sec_goods_id' " .
        " AND (order_status = '" . OS_CONFIRMED . "' OR order_status = '" . OS_UNCONFIRMED . "')";

    $stat = $GLOBALS['db']->getRow($sql);
    if ($stat['total_order'] == 0) {
        $stat['total_goods'] = 0;
    }
    $stat['valid_order'] = $stat['total_order'];
    $stat['valid_goods'] = $stat['total_goods'];

    return $stat;
}

//获取首页秒杀活动商品
function get_seckill_goods()
{
    $now = gmtime();
    $date_begin = local_strtotime(local_date('Ymd'));
    $soon = [];
    $sql = " SELECT g.goods_id, g.goods_thumb, g.shop_price, g.market_price, g.goods_name, sg.id, sg.sec_price, sg.sec_num, sg.sec_limit, stb.begin_time, stb.end_time, s.sec_id, s.acti_title, s.acti_time FROM " . $GLOBALS['ecs']->table('seckill_goods') .
        " AS sg LEFT JOIN " . $GLOBALS['ecs']->table('seckill_time_bucket') . " AS stb ON sg.tb_id = stb.id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('seckill') . " AS s ON s.sec_id = sg.sec_id " .
        " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON sg.goods_id = g.goods_id " .
        " WHERE s.is_putaway = 1 AND s.review_status = 3  AND s.acti_time >= '$date_begin' ORDER BY stb.begin_time ASC ";
    $res = $GLOBALS['db']->getAll($sql);
    $sql = " SELECT MIN(begin_time), MAX(end_time) FROM " . $GLOBALS['ecs']->table('seckill_time_bucket');
    $time = $GLOBALS['db']->getRow($sql);
    $min_time = $time['begin_time'];
    $max_time = $time['end_time'];

    if ($res) {
        foreach ($res as $k => $v) {
            $begin_time = local_strtotime($v['begin_time']);
            $end_time = local_strtotime($v['end_time']);
            if (($begin_time > $now) || ($end_time < $now)) {
                if ($v['begin_time'] == $min_time && $max_time > $now) {
                    $soon[$k] = $res[$k];
                    $begin_time_format = local_date("Y-m-d H:i:s", $begin_time);
                }
                unset($res[$k]);
            } else {
                $end_time_format = local_date("Y-m-d H:i:s", $end_time);
            }
        }
    }
    if (empty($end_time_format)) {
        $GLOBALS['smarty']->assign('sec_begin_time', $begin_time_format);
    } else {
        $GLOBALS['smarty']->assign('sec_end_time', $end_time_format);
    }

    if ($res) {
        foreach ($res as $k => $v) {
            $res[$k]['sec_price'] = price_format($v['sec_price']);
            $res[$k]['market_price'] = price_format($v['market_price']);
            $res[$k]['url'] = build_uri('seckill', ['act' => "view", 'secid' => $v['id']], $v['goods_name']);
            $res[$k]['list_url'] = build_uri('seckill', ['act' => "list", 'secid' => $v['id']], $v['goods_name']);
        }
        return $res;
    } else {
        if ($soon) {
            foreach ($soon as $k => $v) {
                $soon[$k]['sec_price'] = price_format($v['sec_price']);
                $soon[$k]['market_price'] = price_format($v['market_price']);
                $soon[$k]['url'] = build_uri('seckill', ['act' => "view", 'secid' => $v['id']], $v['goods_name']);
                $res[$k]['list_url'] = build_uri('seckill', ['act' => "list", 'secid' => $v['id']], $v['goods_name']);
            }
        }
        return $soon;
    }
}

/*
 * 取得商品评论条数
 */
function commentCol($id)
{
    if (empty($id)) {
        return false;
    }
    $sql = "SELECT count(comment_id) as num FROM {pre}comment WHERE id_value =" . $id . ' and comment_type = 0 and status = 1 and parent_id = 0';
    $arr['all_comment'] = $GLOBALS['db']->getOne($sql);
    $sql = "SELECT count(comment_id) as num FROM {pre}comment WHERE id_value =" . $id . ' AND  comment_rank in (4, 5) and comment_type = 0 and status = 1 and parent_id = 0 ';
    $arr['good_comment'] = $GLOBALS['db']->getOne($sql);
    $sql = "SELECT count(comment_id) as num FROM {pre}comment WHERE id_value =" . $id . ' AND  comment_rank in (2, 3) and comment_type = 0 and status = 1 and parent_id = 0 ';
    $arr['in_comment'] = $GLOBALS['db']->getOne($sql);
    $sql = "SELECT count(comment_id) as num FROM {pre}comment WHERE id_value =" . $id . ' AND  comment_rank in (0, 1) and comment_type = 0 and status = 1 and parent_id = 0 ';
    $arr['rotten_comment'] = $GLOBALS['db']->getOne($sql);
    $sql = "SELECT count( DISTINCT b.comment_id) as num FROM {pre}comment as a LEFT JOIN {pre}comment_img as b ON a.id_value=b.goods_id WHERE a.id_value =" . $id . " and a.comment_type = 0 and a.status = 1 and a.parent_id = 0 and b.img_thumb != ''";
    $arr['img_comment'] = $GLOBALS['db']->getOne($sql);
    foreach ($arr as $key => $val) {
        $arr[$key] = empty($val) ? 0 : $arr[$key];
    }
    return $arr;
}

/**
 * 商品详情页查询商品评论
 * @param $goods_id
 * @param string $rank
 * @param int $hasgoods
 * @param int $start
 * @param int $size
 * @return array
 */
function get_good_comment_as($goods_id, $rank = '', $hasgoods = 0, $start = 0, $size = 10)
{
    if (empty($goods_id)) {
        return false;
    }

    $rank = !empty($rank) ? $rank : 'all';
    $where = '';
    if ($rank == 'all') {
        $where = ' AND comment_rank in (0, 1, 2, 3, 4, 5) '; // 全部评价
    } elseif ($rank == 'good') {
        $where = ' AND comment_rank in (4, 5) '; //好评
    } elseif ($rank == 'in') {
        $where = ' AND comment_rank in (2, 3) '; //中评
    } elseif ($rank == 'rotten') {
        $where = ' AND comment_rank in (0, 1) '; //差评
    } elseif ($rank == 'img') {
        $where = ' AND comment_rank in (0, 1, 2, 3, 4, 5) '; // 有图评价
    }

    $sql = "SELECT comment_id, content, add_time, email, user_name, comment_rank, status, user_id, order_id FROM " . $GLOBALS['ecs']->table('comment') . " WHERE id_value = '" . $goods_id . "' and comment_type = 0 and status = 1 and parent_id = 0 " . $where . " ORDER BY comment_id DESC LIMIT $start, $size";
    $comment = $GLOBALS['db']->getAll($sql);

    $sql = "SELECT count(*) as num FROM " . $GLOBALS['ecs']->table('comment') . " WHERE id_value = '" . $goods_id . "' and comment_type = 0 and status = 1 and parent_id = 0 " . $where;
    $num = $GLOBALS['db']->getOne($sql);
    $max = ceil($num / $size);

    $arr = [];
    if ($comment) {
        $ids = '';
        foreach ($comment as $key => $row) {
            $ids .= $ids ? ",$row[comment_id]" : $row['comment_id'];
            $arr[$row['comment_id']]['id'] = $row['comment_id'];
            $arr[$row['comment_id']]['email'] = $row['email'];

            $users = get_wechat_user_info($row['user_id']);
            $arr[$row['comment_id']]['username'] = encrypt_username($users['nick_name']);
            $arr[$row['comment_id']]['user_picture'] = get_image_path($users['user_picture']);

            $arr[$row['comment_id']]['content'] = str_replace('\r\n', '<br />', $row['content']);
            $arr[$row['comment_id']]['content'] = nl2br(str_replace('\n', '<br />', $arr[$row['comment_id']]['content']));
            $arr[$row['comment_id']]['rank'] = $row['comment_rank'];
            $arr[$row['comment_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            // 评价商品
            if ($row['order_id'] && $hasgoods) {
                $sql = "SELECT o.goods_id, o.goods_name, o.goods_attr, g.goods_img FROM " . $GLOBALS['ecs']->table('order_goods') . " o LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " g ON o.goods_id = g.goods_id WHERE o.order_id = '" . $row['order_id'] . "' ORDER BY rec_id DESC";
                $goods = $GLOBALS['db']->getAll($sql);
                if ($goods) {
                    foreach ($goods as $k => $v) {
                        $goods[$k]['goods_img'] = get_image_path($v['goods_img']);
                        $goods[$k]['goods_attr'] = str_replace('\r\n', '<br />', $v['goods_attr']);
                        if (C('shop.goods_attr_price') == 0 || C('shop.goods_attr_price') == 1) {
                            $ping = strstr($v['goods_attr'], '[', true);
                            $goods[$k]['goods_attr'] = str_replace('\r\n', '<br />', $ping);
                            if ($ping === false) {
                                $$v['goods_attr'] = $$v['goods_attr'];
                                $goods[$k]['goods_attr'] = str_replace('\r\n', '<br />', $v['goods_attr']);
                            }
                        }
                    }
                    $arr[$row['comment_id']]['goods'] = $goods;
                }
            }
            // 有图评价
            $sql = "SELECT img_thumb FROM {pre}comment_img WHERE comment_id = " . $row['comment_id'];
            $comment_thumb = $GLOBALS['db']->getCol($sql);
            if (count($comment_thumb) > 0) {
                foreach ($comment_thumb as $k => $v) {
                    $comment_thumb[$k] = get_image_path($v);
                }
                $arr[$row['comment_id']]['thumb'] = $comment_thumb;
            } else {
                $arr[$row['comment_id']]['thumb'] = '';
            }
            $img_max = ceil(count($comment_thumb) / $size);
        }

        /* 取得已有回复的评论 */
        if ($ids) {
            $sql = 'SELECT parent_id, content, add_time, email, user_name FROM ' . $GLOBALS['ecs']->table('comment') . " WHERE parent_id IN( $ids )";
            $res = $GLOBALS['db']->query($sql);
            foreach ($res as $row) {
                $arr[$row['parent_id']]['re_content'] = nl2br(str_replace('\n', '<br />', htmlspecialchars($row['content'])));
                $arr[$row['parent_id']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
                $arr[$row['parent_id']]['re_email'] = $row['email'];
                $arr[$row['parent_id']]['re_username'] = $row['user_name'];
            }
        }
        $arr = array_values($arr);
    }

    return ['arr' => $arr, 'max' => $max, 'img_max' => $img_max];
}

/**
 * 查询商品评论
 * @param $id
 * @param string $rank
 * @param int $start
 * @param int $size
 * @return bool
 */
function get_good_comment($id, $rank = null, $hasgoods = 0, $start = 0, $size = 10)
{
    if (empty($id)) {
        return false;
    }
    $where = '';

    $rank = (empty($rank) && $rank !== 0) ? '' : intval($rank);

    if ($rank == 4) {
        //好评
        $where = ' AND  comment_rank in (4, 5)';
    } elseif ($rank == 2) {
        //中评
        $where = ' AND  comment_rank in (2, 3)';
    } elseif ($rank === 0) {
        //差评
        $where = ' AND  comment_rank in (0, 1)';
    } elseif ($rank == 1) {
        //差评
        $where = ' AND  comment_rank in (0, 1)';
    } elseif ($rank == 5) {
        $where = ' AND  comment_rank in (0, 1, 2, 3, 4,5)';
    }

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('comment') . " WHERE id_value = '" . $id . "' and comment_type = 0 and status = 1 and parent_id = 0 " . $where . " ORDER BY comment_id DESC LIMIT $start, $size";

    $comment = $GLOBALS['db']->getAll($sql);

    $sql = " SELECT value FROM " . $GLOBALS['ecs']->table('shop_config') . " WHERE code = 'goods_attr_price' ";
    $config = $GLOBALS['db']->getone($sql);
    $arr = [];
    if ($comment) {
        $ids = '';
        foreach ($comment as $key => $row) {
            $ids .= $ids ? ",$row[comment_id]" : $row['comment_id'];
            $arr[$row['comment_id']]['id'] = $row['comment_id'];
            $arr[$row['comment_id']]['email'] = $row['email'];
            $users = get_wechat_user_info($row['user_id']);
            $arr[$row['comment_id']]['username'] = encrypt_username($users['nick_name']);
            $arr[$row['comment_id']]['user_picture'] = get_image_path($users['user_picture']);
            $arr[$row['comment_id']]['content'] = str_replace('\r\n', '<br />', $row['content']);
            $arr[$row['comment_id']]['content'] = nl2br(str_replace('\n', '<br />', $arr[$row['comment_id']]['content']));
            $arr[$row['comment_id']]['rank'] = $row['comment_rank'];
            $arr[$row['comment_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            if ($row['order_id'] && $hasgoods) {
                $sql = "SELECT o.goods_id, o.goods_name, o.goods_attr, g.goods_img FROM " . $GLOBALS['ecs']->table('order_goods') . " o LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " g ON o.goods_id = g.goods_id WHERE o.order_id = '" . $row['order_id'] . "' ORDER BY rec_id DESC";
                $goods = $GLOBALS['db']->getAll($sql);
                if ($goods) {
                    foreach ($goods as $k => $v) {
                        $goods[$k]['goods_img'] = get_image_path($v['goods_img']);
                        $goods[$k]['goods_attr'] = str_replace('\r\n', '<br />', $v['goods_attr']);
                        if ($config == 0 || $config == 1) {
                            $ping = strstr($v['goods_attr'], '[', true);
                            $goods[$k]['goods_attr'] = str_replace('\r\n', '<br />', $ping);
                            if ($ping === false) {
                                $$v['goods_attr'] = $$v['goods_attr'];
                                $goods[$k]['goods_attr'] = str_replace('\r\n', '<br />', $v['goods_attr']);
                            }
                        }
                    }
                }
                $arr[$row['comment_id']]['goods'] = $goods;
            }
            $sql = "SELECT img_thumb FROM {pre}comment_img WHERE comment_id = " . $row['comment_id'];
            $comment_thumb = $GLOBALS['db']->getCol($sql);
            if (count($comment_thumb) > 0) {
                foreach ($comment_thumb as $k => $v) {
                    $comment_thumb[$k] = get_image_path($v);
                }
                $arr[$row['comment_id']]['thumb'] = $comment_thumb;
            } else {
                $arr[$row['comment_id']]['thumb'] = 0;
            }
        }

        /* 取得已有回复的评论 */
        if ($ids) {
            $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') . " WHERE parent_id IN( $ids )";
            $res = $GLOBALS['db']->query($sql);
            foreach ($res as $row) {
                $arr[$row['parent_id']]['re_content'] = nl2br(str_replace('\n', '<br />', htmlspecialchars($row['content'])));
                $arr[$row['parent_id']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
                $arr[$row['parent_id']]['re_email'] = $row['email'];
                $arr[$row['parent_id']]['re_username'] = $row['user_name'];
            }
        }
        $arr = array_values($arr);
    }
    return $arr;
}

/**
 * 清空进货单
 * @param   int $type 类型：默认普通商品
 */
function clear_cart($type = CART_GENERAL_GOODS, $cart_value = '')
{
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    $goodsIn = '';
    if (!empty($cart_value)) {
        $goodsIn = " and rec_id in($cart_value)";
    }
    //ecmoban模板堂 --zhuo end

    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') .
        " WHERE " . $sess_id . " AND rec_type = '$type'" . $goodsIn;
    $GLOBALS['db']->query($sql);

    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " user_id = '" . real_cart_mac_ip() . "' ";
    }
}
