<?php
/**
 * 取得某页的批发商品
 * @param   int $size 每页记录数
 * @param   int $page 当前页
 * @param   string $where 查询条件
 * @return  array
 */
function wholesale_list($size, $page, $where, $where_sort, $countSql = '', $sort, $order)
{
    $list = [];
    $sql = "SELECT w.*, g.goods_thumb, g.goods_name as goods_name " . $countSql .
        " FROM " . $GLOBALS['ecs']->table('wholesale') . " AS w, " .
        $GLOBALS['ecs']->table('goods') . " AS g " . $where .
        " AND w.goods_id = g.goods_id AND w.review_status = 3 " . $where_sort;
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);
    foreach ($res as $row) {
        //获取商品原价
        $sql = "SELECT shop_price FROM " . $GLOBALS['ecs']->table('goods') . ' WHERE goods_id = ' . $row['goods_id'];
        $res = $GLOBALS['db']->getRow($sql);
        $row['format_shop_price'] = price_format($res['shop_price']);
        //
        if (empty($row['goods_thumb'])) {
            $row['goods_thumb'] = C('no_picture');
        } else {
            $row['goods_thumb'] = get_image_path($row['goods_thumb']);
        }
        $row['goods_url'] = url('detail', ['id' => $row['act_id']]);

        $properties = get_goods_properties($row['goods_id']);
        $row['goods_attr'] = $properties['pro'];

        $price_ladder = get_price_ladder($row['goods_id']);
        $temp = '';
        foreach ($price_ladder as $k => $v) {
            foreach ($v['qp_list'] as $qk => $qv) {
                if ($temp == '' || $temp > (int)$qv) {
                    $temp = $qv;
                }
            }
        }
        $tap = array_values($price_ladder['0']['qp_list']);
        $row['price_ladder'] = $price_ladder;
        $row['qp_list_min'] = $tap['0'];

        $list[] = $row;
    }

    if ($order == "prices") {
        if ($sort == "DESC") {
            array_multisort(array_column($list, 'qp_list_min'), SORT_DESC, $list);
        } else {
            array_multisort(array_column($list, 'qp_list_min'), SORT_ASC, $list);
        }
    }
    foreach ($list as $key => $val) {
        $list[$key]['qp_list_min'] = price_format($val['qp_list_min']);
    }
    return $list;
}

/**
 * 商品价格阶梯
 * @param   int $goods_id 商品ID
 * @return  array
 */
function get_price_ladder($goods_id)
{
    /* 显示商品规格 */
    $goods_attr_list = array_values(get_goods_attr($goods_id));
    $sql = "SELECT prices FROM " . $GLOBALS['ecs']->table('wholesale') .
        "WHERE review_status = 3 and goods_id = " . $goods_id;
    $row = $GLOBALS['db']->getRow($sql);

    $arr = [];
    $_arr = unserialize($row['prices']);
    if (is_array($_arr)) {
        foreach (unserialize($row['prices']) as $key => $val) {
            // 显示属性
            if (!empty($val['attr'])) {
                foreach ($val['attr'] as $attr_key => $attr_val) {
                    // 获取当前属性 $attr_key 的信息
                    $goods_attr = [];
                    foreach ($goods_attr_list as $goods_attr_val) {
                        if ($goods_attr_val['attr_id'] == $attr_key) {
                            $goods_attr = $goods_attr_val;
                            break;
                        }
                    }

                    // 重写商品规格的价格阶梯信息
                    if (!empty($goods_attr)) {
                        $arr[$key]['attr'][] = [
                            'attr_id' => $goods_attr['attr_id'],
                            'attr_name' => $goods_attr['attr_name'],
                            'attr_val' => (isset($goods_attr['goods_attr_list'][$attr_val]) ? $goods_attr['goods_attr_list'][$attr_val] : ''),
                            'attr_val_id' => $attr_val
                        ];
                    }
                }
            }

            // 显示数量与价格
            foreach ($val['qp_list'] as $index => $qp) {
                $arr[$key]['qp_list'][$qp['quantity']] = $qp['price'];
            }
        }
    }
    return $arr;
}


/**
 * 商品属性是否匹配
 * @param   array $goods_list 用户选择的商品
 * @param   array $reference 参照的商品属性
 * @return  bool
 */
function is_attr_matching(&$goods_list, $reference)
{
    foreach ($goods_list as $key => $goods) {
        // 需要相同的元素个数
        if (count($goods['goods_attr']) != count($reference)) {
            break;
        }

        // 判断用户提交与批发属性是否相同
        $is_check = true;
        if (is_array($goods['goods_attr'])) {
            foreach ($goods['goods_attr'] as $attr) {
                if (!(array_key_exists($attr['attr_id'], $reference) && $attr['attr_val_id'] == $reference[$attr['attr_id']])) {
                    $is_check = false;
                    break;
                }
            }
        }
        if ($is_check) {
            return $key;
            break;
        }
    }

    return false;
}


function get_wholesale_goods_properties($goods_id, $warehouse_id = 0, $area_id = 0, $goods_attr_id = '', $attr_type = 0)
{
    $attr_array = array();
    if (!(empty($goods_attr_id)))
    {
        $attr_array = explode(',', $goods_attr_id);
    }
    $sql = 'SELECT attr_group ' . 'FROM ' . $GLOBALS['ecs']->table('goods_type') . ' AS gt, ' . $GLOBALS['ecs']->table('wholesale') . ' AS g ' . 'WHERE g.goods_id=\'' . $goods_id . '\' AND gt.cat_id=g.goods_type';
    $grp = $GLOBALS['db']->getOne($sql);
    if (!(empty($grp)))
    {
        $groups = explode("\n", strtr($grp, "\r", ''));
    }
    $model_attr = get_table_date('goods', 'goods_id = \'' . $goods_id . '\'', array('model_attr'), 2);
    $leftJoin = '';
    $select = '';
    $goodsAttr = '';
    if (($attr_type == 1) && !(empty($goods_attr_id)))
    {
        $goodsAttr = ' and g.goods_attr_id in(' . $goods_attr_id . ') ';
    }
    $where = '';
    $goods_type = get_table_date('wholesale', 'goods_id=\'' . $goods_id . '\'', array('goods_type'), 2);
    $where .= ' AND a.cat_id = \'' . $goods_type . '\' ';
    $sql = 'SELECT a.attr_id, a.attr_name, a.attr_group, a.is_linked, a.attr_type, ' . $select . 'g.goods_attr_id, g.attr_value, g.attr_price, g.attr_img_flie, g.attr_img_site, g.attr_checked, g.attr_sort ' . 'FROM ' . $GLOBALS['ecs']->table('wholesale_goods_attr') . ' AS g ' . 'LEFT JOIN ' . $GLOBALS['ecs']->table('attribute') . ' AS a ON a.attr_id = g.attr_id ' . $leftJoin . 'WHERE g.goods_id = \'' . $goods_id . '\' ' . $goodsAttr . $where . ' AND a.attr_type <> 2 ' . 'ORDER BY a.sort_order, a.attr_id, g.goods_attr_id';
    $res = $GLOBALS['db']->getAll($sql);
    $arr['pro'] = array();
    $arr['spe'] = array();
    $arr['lnk'] = array();
    foreach ($res as $row )
    {
        $row['attr_value'] = str_replace("\n", '<br />', $row['attr_value']);
        if ($row['attr_type'] == 0)
        {
            $group = (isset($groups[$row['attr_group']]) ? $groups[$row['attr_group']] : $GLOBALS['_LANG']['goods_attr']);
            $arr['pro'][$group][$row['attr_id']]['name'] = $row['attr_name'];
            $arr['pro'][$group][$row['attr_id']]['value'] = $row['attr_value'];
        }
        else
        {
            if ($model_attr == 1)
            {
                $attr_price = $row['warehouse_attr_price'];
            }
            else if ($model_attr == 2)
            {
                $attr_price = $row['area_attr_price'];
            }
            else
            {
                $attr_price = $row['attr_price'];
            }
            $img_site = array('attr_img_flie' => $row['attr_img_flie'], 'attr_img_site' => $row['attr_img_site']);
            $attr_info = get_has_attr_info($row['attr_id'], $row['attr_value'], $img_site);
            $row['img_flie'] = (!(empty($attr_info['attr_img'])) ? get_image_path($row['attr_id'], $attr_info['attr_img'], true) : '');
            $row['img_site'] = $attr_info['attr_site'];
            $arr['spe'][$row['attr_id']]['attr_type'] = $row['attr_type'];
            $arr['spe'][$row['attr_id']]['name'] = $row['attr_name'];
            $arr['spe'][$row['attr_id']]['values'][] = array('label' => $row['attr_value'], 'img_flie' => $row['img_flie'], 'img_site' => $row['img_site'], 'checked' => $row['attr_checked'], 'attr_sort' => $row['attr_sort'], 'combo_checked' => get_combo_godos_attr($attr_array, $row['goods_attr_id']), 'price' => $attr_price, 'format_price' => price_format(abs($attr_price), false), 'id' => $row['goods_attr_id']);
        }
        if ($row['is_linked'] == 1)
        {
            $arr['lnk'][$row['attr_id']]['name'] = $row['attr_name'];
            $arr['lnk'][$row['attr_id']]['value'] = $row['attr_value'];
        }

        //属性不是规格不能添加
        if ($row['attr_type'] > 0) {
            $arr['spe'][$row['attr_id']]['values'] = get_array_sort($arr['spe'][$row['attr_id']]['values'], 'attr_sort');
            $arr['spe'][$row['attr_id']]['is_checked'] = get_attr_values($arr['spe'][$row['attr_id']]['values']);
        }
    }
    return $arr;
}

function get_wholesale_main_attr_list($goods_id = 0, $attr = array())
{
    $goods_type = get_table_date('wholesale', 'goods_id=\'' . $goods_id . '\'', array('goods_type'), 2);
    $sql = ' SELECT DISTINCT attr_id FROM ' . $GLOBALS['ecs']->table('wholesale_goods_attr') . ' WHERE goods_id = \'' . $goods_id . '\' ';
    $attr_ids = $GLOBALS['db']->getCol($sql);
    if (!(empty($attr_ids)))
    {
        $attr_ids = implode(',', $attr_ids);
        $sort_order = ' ORDER BY sort_order DESC, attr_id DESC ';
        $sql = ' SELECT attr_id FROM ' . $GLOBALS['ecs']->table('attribute') . ' WHERE attr_type > 0 AND cat_id = \'' . $goods_type . '\' AND attr_id IN (' . $attr_ids . ') ' . $sort_order . ' LIMIT 1 ';
        $attr_id = $GLOBALS['db']->getOne($sql);
        $sql = ' SELECT goods_attr_id, attr_value FROM ' . $GLOBALS['ecs']->table('wholesale_goods_attr') . ' WHERE goods_id = \'' . $goods_id . '\' AND attr_id = \'' . $attr_id . '\' ORDER BY goods_attr_id ';
        $data = $GLOBALS['db']->getAll($sql);
        if ($data)
        {
            foreach ($data as $key => $val )
            {
                $new_arr = array_merge($attr, array($val['goods_attr_id']));
                $data[$key]['attr_group'] = implode(',', $new_arr);
                $set = get_find_in_set($new_arr);
                $product_info = get_table_date('wholesale_products', 'goods_id=\'' . $goods_id . '\' ' . $set, array('product_number'));
                $data[$key] = array_merge($data[$key], $product_info);
            }
            return $data;
        }
    }
    return false;
}


function get_wholesale_select_record_data($goods_id = 0, $attr_num_array = array())
{
    $new_array = array();
    foreach ($attr_num_array as $key => $val )
    {
        $arr = explode(',', $val['attr']);
        $end_attr = end($arr);
        array_pop($arr);
        $attr_key = implode(',', $arr);
        $new_array[$attr_key][$end_attr] = $val['num'];
    }
    $record_data = array();
    foreach ($new_array as $key => $val )
    {
        $data = array();
        $data['main_attr'] = get_wholesale_goods_attr_array($key);
        foreach ($val as $k => $v )
        {
            $a = array();
            $a['attr_num'] = $v;
            $b = get_wholesale_goods_attr_array($k);
            $c = $b[0];
            $a = array_merge($a, $c);
            $data['end_attr'][] = $a;
        }
        $record_data[$key] = $data;
    }
    return $record_data;
}

function get_wholesale_goods_attr_array($goods_attr_id = '')
{
    if (empty($goods_attr_id))
    {
        return false;
    }
    $sort_order = ' ORDER BY a.sort_order ASC, a.attr_id ASC ';
    $sql = ' SELECT a.attr_name, ga.attr_value FROM ' . $GLOBALS['ecs']->table('wholesale_goods_attr') . ' AS ga ' . ' LEFT JOIN ' . $GLOBALS['ecs']->table('attribute') . ' AS a ON a.attr_id = ga.attr_id ' . ' WHERE ga.goods_attr_id IN (' . $goods_attr_id . ') ' . $sort_order;
    $res = $GLOBALS['db']->getAll($sql);
    return $res;
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

    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_user_info') . " WHERE " . $sess_id;
    $GLOBALS['db']->query($sql);
}

/*
 * 取得批发活动统计信息
 * @param   int     $group_buy_id   预售活动id
 * @return  array   统计信息
 *                  total_order     总订单数
 *                  valid_order     有效订单数
 */
function wholesale_stat($wholesale_id) {
    $wholesale_id = intval($wholesale_id);

    /* 取得预售活动商品ID */
    $sql = "SELECT goods_id " .
        "FROM " . $GLOBALS['ecs']->table('wholesale') .
        "WHERE act_id = '$wholesale_id' AND review_status = 3 ";
    $goods_id = $GLOBALS['db']->getOne($sql);

    $where = "";
    $where .= "AND o.extension_code = 'wholesale' " .
        "AND o.extension_id = '$wholesale_id' ";

    $sql = "SELECT COUNT(*) AS total_order FROM " . $GLOBALS['ecs']->table('order_info') . " AS o".
        " WHERE 1 " . $where;

    $stat = $GLOBALS['db']->getRow($sql);

    if ($stat['total_order'] == 0)
    {
        $stat['total_goods'] = 0;
    } else {
        $sql = "SELECT SUM(g.goods_number) AS total_goods " .
            "FROM " . $GLOBALS['ecs']->table('order_info') . " AS o, " .
            $GLOBALS['ecs']->table('order_goods') . " AS g " .
            " WHERE o.order_id = g.order_id " .
            "AND g.goods_id = '$goods_id' " .
            $where;
        $total_goods = $GLOBALS['db']->getOne($sql);
        $stat['total_goods'] = $total_goods;
    }

    $stat['valid_order'] = $stat['total_order'];
    $stat['valid_goods'] = $stat['total_goods'];

    return $stat;
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
 * 商铺其他批发
 * @param $presale_id
 * @return mixed
 */
function get_merchant_group_goods($wholesale_id)
{
    $ru_id = $GLOBALS['db']->getOne("SELECT user_id FROM " . $GLOBALS['ecs']->table('wholesale') . " WHERE act_id = '$wholesale_id'");
    $sql = "SELECT ga.goods_id, ga.act_id, ga.goods_id, ga.price_model, ga.goods_price, ga.goods_name act_name, g.goods_thumb, g.sales_volume FROM ( SELECT * FROM " . $GLOBALS['ecs']->table('wholesale') . " order by act_id desc) ga"
        . " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " g ON ga.goods_id = g.goods_id "
        . " WHERE ga.user_id = '$ru_id' AND ga.review_status = 3 group by ga.goods_id order by ga.act_id desc  LIMIT 4 ";
    $merchant_group = $GLOBALS['db']->getAll($sql);

    foreach ($merchant_group as $key => $row) {
        if ($row['price_model']) {
            $sql = "SELECT MIN(volume_price) AS volume_price FROM ". $GLOBALS['ecs']->table('wholesale_volume_price') . "WHERE goods_id = ".$row['goods_id'];
            $volume_price = $GLOBALS['db']->getOne($sql);
            if ($volume_price) {
                $row['volume_price'] = price_format($volume_price);
            }
        } else {
            $row['volume_price'] = price_format($row['goods_price']);
        }

        $merchant_group[$key]['shop_price'] =  $row['volume_price'];
        $merchant_group[$key]['goods_thumb'] = get_image_path($row['goods_thumb']);
    }

    return $merchant_group;
}
?>
