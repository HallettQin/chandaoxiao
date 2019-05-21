<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/8
 * Time: 7:11
 */
function get_good_comment($id, $rank = NULL, $hasgoods = 0, $start = 0, $size = 10)
{
    if (empty($id)) {
        return false;
    }

    $where = '';
    $rank = (empty($rank) && ($rank !== 0) ? '' : intval($rank));

    if ($rank == 4) {
        $where = ' AND  comment_rank in (4, 5)';
    }
    else if ($rank == 2) {
        $where = ' AND  comment_rank in (2, 3)';
    }
    else if ($rank === 0) {
        $where = ' AND  comment_rank in (0, 1)';
    }
    else if ($rank == 1) {
        $where = ' AND  comment_rank in (0, 1)';
    }
    else if ($rank == 5) {
        $where = ' AND  comment_rank in (0, 1, 2, 3, 4,5)';
    }

    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') . ' WHERE id_value = \'' . $id . '\' and comment_type = 0 and status = 1 and parent_id = 0 ' . $where . ' ORDER BY comment_id DESC LIMIT ' . $start . ', ' . $size;
    $comment = $GLOBALS['db']->getAll($sql);
    $sql = ' SELECT value FROM ' . $GLOBALS['ecs']->table('shop_config') . ' WHERE code = \'goods_attr_price\' ';
    $config = $GLOBALS['db']->getone($sql);
    $arr = array();

    if ($comment) {
        $ids = '';

        foreach ($comment as $key => $row) {
            $ids .= ($ids ? ',' . $row['comment_id'] : $row['comment_id']);
            $arr[$row['comment_id']]['id'] = $row['comment_id'];
            $arr[$row['comment_id']]['email'] = $row['email'];
            $sql = 'SELECT user_picture, nick_name FROM {pre}users WHERE user_name = \'' . $row['user_name'] . '\'';
            $one = $GLOBALS['db']->getAll($sql);
            $arr[$row['comment_id']]['username'] = encrypt_username($one[0]['nick_name']);
            $arr[$row['comment_id']]['content'] = str_replace('\\r\\n', '<br />', $row['content']);
            $arr[$row['comment_id']]['content'] = nl2br(str_replace('\\n', '<br />', $arr[$row['comment_id']]['content']));
            $arr[$row['comment_id']]['rank'] = $row['comment_rank'];
            $arr[$row['comment_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            if ($row['order_id'] && $hasgoods) {
                $sql = 'SELECT o.goods_id, o.goods_name, o.goods_attr, g.goods_img FROM ' . $GLOBALS['ecs']->table('order_goods') . ' o LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' g ON o.goods_id = g.goods_id WHERE o.order_id = \'' . $row['order_id'] . '\' ORDER BY rec_id DESC';
                $goods = $GLOBALS['db']->getAll($sql);

                if ($goods) {
                    foreach ($goods as $k => $v) {
                        $goods[$k]['goods_img'] = get_image_path($v['goods_img']);
                        $goods[$k]['goods_attr'] = str_replace('\\r\\n', '<br />', $v['goods_attr']);
                        if (($config == 0) || ($config == 1)) {
                            $ping = strstr($v['goods_attr'], '[', true);
                            $goods[$k]['goods_attr'] = str_replace('\\r\\n', '<br />', $ping);

                            if ($ping === false) {
                                $$v['goods_attr'] = $$v['goods_attr'];
                                $goods[$k]['goods_attr'] = str_replace('\\r\\n', '<br />', $v['goods_attr']);
                            }
                        }
                    }
                }

                $arr[$row['comment_id']]['goods'] = $goods;
            }

            $sql = 'SELECT img_thumb FROM {pre}comment_img WHERE comment_id = ' . $row['comment_id'];
            $comment_thumb = $GLOBALS['db']->getCol($sql);

            if (0 < count($comment_thumb)) {
                foreach ($comment_thumb as $k => $v) {
                    $comment_thumb[$k] = get_image_path($v);
                }

                $arr[$row['comment_id']]['thumb'] = $comment_thumb;
            }
            else {
                $arr[$row['comment_id']]['thumb'] = 0;
            }
        }

        if ($ids) {
            $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') . ' WHERE parent_id IN( ' . $ids . ' )';
            $res = $GLOBALS['db']->query($sql);

            foreach ($res as $row) {
                $arr[$row['parent_id']]['re_content'] = nl2br(str_replace('\\n', '<br />', htmlspecialchars($row['content'])));
                $arr[$row['parent_id']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
                $arr[$row['parent_id']]['re_email'] = $row['email'];
                $arr[$row['parent_id']]['re_username'] = $row['user_name'];
            }
        }

        $arr = array_values($arr);
    }

    return $arr;
}

function clear_cart($type = CART_GENERAL_GOODS, $cart_value = '')
{
    if (!empty($_SESSION['user_id'])) {
        $sess_id = ' user_id = \'' . $_SESSION['user_id'] . '\' ';
    }
    else {
        $sess_id = ' session_id = \'' . real_cart_mac_ip() . '\' ';
    }

    $goodsIn = '';

    if (!empty($cart_value)) {
        $goodsIn = ' and rec_id in(' . $cart_value . ')';
    }

    $sql = 'DELETE FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND rec_type = \'' . $type . '\'' . $goodsIn;
    $GLOBALS['db']->query($sql);

    if (!empty($_SESSION['user_id'])) {
        $sess_id = ' user_id = \'' . $_SESSION['user_id'] . '\' ';
    }
    else {
        $sess_id = ' user_id = \'' . real_cart_mac_ip() . '\' ';
    }

    $sql = 'DELETE FROM ' . $GLOBALS['ecs']->table('cart_user_info') . ' WHERE ' . $sess_id;
    $GLOBALS['db']->query($sql);
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
    $sql = "SELECT count( DISTINCT b.comment_id) as num FROM {pre}comment as a LEFT JOIN {pre}comment_img as b ON a.id_value=b.goods_id WHERE a.id_value =" . $id . " and a.comment_type = 0 and a.status = 1 and a.parent_id = 0 and b.comment_img != ''";
    $arr['img_comment'] = $GLOBALS['db']->getOne($sql);
    foreach ($arr as $key => $val) {
        $arr[$key] = empty($val) ? 0 : $arr[$key];
    }
    return $arr;
}

/**
 * 商铺其他预定
 * @param $presale_id
 * @return mixed
 */
function get_merchant_group_goods($sample_id)
{
    $ru_id = $GLOBALS['db']->getOne("SELECT user_id FROM " . $GLOBALS['ecs']->table('sample_activity') . " WHERE act_id = '$sample_id'");
    $sql = "SELECT ga.goods_id, ga.act_id, ga.goods_id, ga.ext_info, ga.act_name, g.goods_thumb, g.sales_volume FROM ( SELECT * FROM " . $GLOBALS['ecs']->table('sample_activity') . " order by act_id desc) ga"
        . " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " g ON ga.goods_id = g.goods_id "
        . " WHERE ga.user_id = '$ru_id' AND ga.review_status = 3 group by ga.goods_id order by ga.act_id desc  LIMIT 4 ";
    $merchant_group = $GLOBALS['db']->getAll($sql);

    foreach ($merchant_group as $key => $row) {
        $ext_info = unserialize($row['ext_info']);
        $row = array_merge($row, $ext_info);
        $merchant_group[$key]['cur_price'] = $row['ext_info']['cur_price'];

        /* 处理价格阶梯 */
        $price_ladder = $row['price_ladder'];
        if (!is_array($price_ladder) || empty($price_ladder)) {
            $price_ladder = [['amount' => 0, 'price' => 0]];
        } else {
            foreach ($price_ladder as $k => $amount_price) {
                $price_ladder[$k]['formated_price'] = price_format($amount_price['price'], false);
            }
        }

        $merchant_group[$key]['shop_price'] = $price_ladder[0]['formated_price'];
        $merchant_group[$key]['goods_thumb'] = get_image_path($row['goods_thumb']);
    }

    return $merchant_group;
}
