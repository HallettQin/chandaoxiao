<?php

/**
 * 获取未付款订单的数量
 */
function not_pay($user_id)
{
    $where = 'and pay_status = ' . PS_UNPAYED . ' and order_status not in(' . OS_CANCELED . ',' . OS_INVALID . ',' . OS_RETURNED . ')';
    $sql = "SELECT count(*) as num FROM {pre}order_info WHERE user_id = '$user_id' " . $where;
    $res = $GLOBALS['db']->getRow($sql);
    return $res['num'];
}

/**
 * 获取未收货订单的数量
 */
function not_shouhuo($user_id)
{
    $sql = "SELECT count(*) as num FROM {pre}order_info WHERE user_id = '$user_id' and shipping_status = 1 ";
    $res = $GLOBALS['db']->getRow($sql);
    return $res['num'];
}

/**
 * 获取未评价订单的数量
 * 未评价订单条件：订单全部完成
 */
function not_comments($user_id)
{
    $sql = "select count(b.goods_id) from {pre}order_info as o  LEFT JOIN {pre}order_goods  as b on o.order_id=b.order_id  where user_id='$user_id' " .
        " AND o.order_status " . db_create_in([OS_CONFIRMED, OS_SPLITED]) .
        " AND o.shipping_status " . db_create_in([SS_SHIPPED, SS_RECEIVED]) .
        " AND o.pay_status " . db_create_in([PS_PAYED, PS_PAYING]) .
        " AND b.goods_id not in(select id_value from {pre}comment where user_id='$user_id')";

    $res = $GLOBALS['db']->getRow($sql);
    $row = $res['count(b.goods_id)'];
    return $row;
}


/**
 * 拼团中数量
 */
function team_ongoing($user_id)
{
    $where = " and t.status < 1 and '" . gmtime() . "'< (t.start_time+(tg.validity_time*3600)) and tg.is_team = 1 ";//拼团中
    $sql = "select count(o.order_id) as num from {pre}order_info as o left join {pre}team_log as t on o.team_id = t.team_id left join {pre}team_goods as tg on tg.id = t.t_id " . " where o.user_id =$user_id and o.extension_code ='team_buy' and order_status !=2 $where ";
    $res = $GLOBALS['db']->getRow($sql);
    return $res['num'];
}

/**
 * 获取用户红包数量
 */
function my_bonus($user_id)
{
    $time = gmtime();
    $sql = 'select count(u.bonus_id) from {pre}user_bonus as u left join {pre}bonus_type as b on u.bonus_type_id=b.type_id' .
        " where u.user_id='$user_id' and b.use_end_date>$time and u.order_id=0 ";
    $res = $GLOBALS['db']->getRow($sql);
    $count = $res['count(u.bonus_id)'];
    return $count;
}

/**
 * 获取用户积分余额
 */
function pay_money($user_id)
{
    $sql = "SELECT user_money , pay_points FROM {pre}users WHERE user_id = '$user_id'";
    $res = $GLOBALS['db']->getRow($sql);
    return $res;
}

/**
 * 获取未读取的消息数量
 */
function msg_lists($user_id)
{
    $sql = "select msg_id from {pre}feedback where  user_id= '" . $user_id . "'";
    $ress = $GLOBALS['db']->getAll($sql);
    $str = '';
    if ($ress) {
        foreach ($ress as $k) {
            $str .= $k['msg_id'] . ',';
        }
    }
    $reb = substr($str, 0, -1);
    if (!$reb) {
        $reb = 0;
    }
    $sql = "select parent_id from {pre}feedback where parent_id in ($reb)";
    $res = $GLOBALS['db']->getAll($sql);
    if ($res) {
        $strs = '';
        foreach ($res as $k) {
            $strs .= $k['parent_id'] . ',';
        }
    }
    $rebs = substr($strs, 0, -1);
    if (!$rebs) {
        $rebs = 0;
    }
    //		$sql = "select count(*) as num from {pre}feedback where msg_id in ($rebs) and msg_read =0 ";
//		$resb = $GLOBALS['db']->getRow($sql);
//		return $resb['num'];
}

/**
 * 获取收藏商品的数量
 */
function num_collection_goods($user_id)
{
    $sql = "SELECT count(*) as num FROM {pre}collect_goods WHERE user_id = '$user_id'  ";
    $res = $GLOBALS['db']->getRow($sql);
    return $res;
}

/**
 * 获取关注店铺的数量
 */
function num_collection_store($user_id)
{
    $sql = "SELECT count(*) as num FROM {pre}collect_store WHERE user_id = '$user_id'  ";
    $res = $GLOBALS['db']->getRow($sql);
    return $res;
}

/**
 * 调用浏览历史
 *
 * @access  public
 * @return  string
 */
function historys($record_count = 0, $limit = '')
{
    $str = '';
    $history = [];

    if (!empty($_COOKIE['ECS']['history_goods'])) {
        $where = db_create_in($_COOKIE['ECS']['history_goods'], 'goods_id');
        $sql = 'SELECT goods_id, good_mode, goods_name, goods_thumb, shop_price FROM {pre}goods' .
            " WHERE $where AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0" . $limit;
        $query = $GLOBALS['db']->getAll($sql);
        $res = [];
        foreach ($query as $key => $row) {
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

            // by zxk
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
                $goods['volume_price'] = $price_ladder[0]['formated_price'];

                if (1 == $row['good_mode']) {
                    $goods['url'] = build_uri('groupbuy', ['id' => $activity['act_id']]);
                } else if (2 == $row['good_mode']) {
                    $goods['url'] = build_uri('presale', ['id' => $activity['act_id']]);
                }  else if (3 == $row['good_mode']) {
                    $goods['url'] = build_uri('sample', ['id' => $activity['act_id']]);
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
                        $goods['volume_price'] = price_format($volume_price);
                    }
                } else {
                    $goods['volume_price'] = price_format($activity['goods_price']);
                }

                $goods['url'] = build_uri('wholesale', ['id' => $activity['act_id']]);
            }


            $goods['goods_id'] = $row['goods_id'];
            $goods['goods_name'] = $row['goods_name'];
            $goods['short_name'] = C('shop.goods_name_length') > 0 ? sub_str($row['goods_name'], C('shop.goods_name_length')) : $row['goods_name'];
            $goods['goods_thumb'] = get_image_path($row['goods_thumb']);
            $goods['shop_price'] = price_format($row['shop_price']);
//            $goods['url'] = url('goods/index/index', ['id' => $row['goods_id']]);
            $history[] = $goods;
        }
    }
    rsort($history);
    $arr = ['goods_list' => $history, 'record_count' => $record_count, 'paper' => $paper, 'size' => $size];
    return $arr;
}

/**
 * 验证用户信息
 */
function check_user_info($data, $type = 'mobile')
{
    if ($type == 'mobile') {
        $where = " user_name='" . $data['user_name'] . "' and mobile_phone='" . $data['mobile_phone'] . "'";
    } else {
        $where = " user_name='" . $data['user_name'] . "' and email='" . $data['email'] . "'";
    }
    $sql = "SELECT mobile_phone, email FROM {pre}users WHERE " . $where;
    $query = $GLOBALS['db']->getRow($sql);
    if (!empty($query)) {
        return true;
    } else {
        return false;
    }
}

/**
 * 获取管理员留言数量
 */
function get_admin_feedback($user_id)
{
    if (!empty($user_id)) {
        $sql = "SELECT COUNT(*) AS admin_count FROM {pre}feedback AS a WHERE a.parent_id IN " .
            " (SELECT msg_id FROM {pre}feedback AS b WHERE b.user_id = '" . $user_id . "')";
        $query = $GLOBALS['db']->getRow($sql);
    }
    return $query['admin_count'];
}

/*
 * 判断预售商品是否处在尾款结算状态
 */

function presale_settle_status($extension_id)
{
    $now = gmtime();
    $sql = " SELECT pay_start_time, pay_end_time FROM " . $GLOBALS['ecs']->table('presale_activity') .
        " WHERE act_id = '$extension_id' ";
    $row = $GLOBALS['db']->getRow($sql);
    $result = [];
    if ($row['pay_start_time'] <= $now && $row['pay_end_time'] >= $now) {
        $result['start_time'] = local_date('Y-m-d', $row['pay_start_time']);
        $result['end_time'] = local_date('Y-m-d', $row['pay_end_time']);
        $result['settle_status'] = 1; //在支付尾款时间段内
        return $result;
    } elseif ($row['pay_end_time'] < $now) {
        $result['start_time'] = local_date('Y-m-d ', $row['pay_start_time']);
        $result['end_time'] = local_date('Y-m-d', $row['pay_end_time']);
        $result['settle_status'] = -1; //超出支付尾款时间
        return $result;
    } else {
        $result['start_time'] = local_date('Y-m-d', $row['pay_start_time']);
        $result['end_time'] = local_date('Y-m-d', $row['pay_end_time']);
        $result['settle_status'] = 0; //未到付款时间
        return $result;
    }
}

/**
 * 众筹类库
 */

/**
 * 推荐项目
 */
function zc_best_list()
{
    $now = gmtime();
    $sql = "SELECT `id`,`cat_id`,`title`,`start_time`,`end_time`,`amount`,`join_money`,`join_num`,`title_img`,`describe`,(end_time-unix_timestamp(now())) as shenyu_time FROM " . $GLOBALS['ecs']->table('zc_project') . " where start_time <= '$now' AND end_time > '$now' and is_best = 1 ORDER BY id DESC ";
    $zc_arr = $GLOBALS['db']->query($sql);

    foreach ($zc_arr as $k => $z_val) {
        $zc_arr[$k]['star_time'] = date('Y-m-d', $z_val['start_time']);
        $zc_arr[$k]['end_time'] = date('Y-m-d', $z_val['end_time']);
        $zc_arr[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_arr[$k]['title_img'] = get_zc_image_path($z_val['title_img']);
        $zc_arr[$k]['url'] = url('crowd_funding/index/info', ['id' => $z_val['id']]);
        $zc_arr[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 2) * 100;
        $zc_arr[$k]['min_price'] = plan_min_price($z_val['id']); //获取方案最低价格
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_arr[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
    }

    return $zc_arr;
}

/**
 * 用户关注的众筹项目列表
 */
function zc_focus_list($user_id = 0, $type = 1)
{
    $now = gmtime();
    switch ($type) {
        case 1:
            $where = " ";                  //全部
            break;
        case 2:
            $where = " AND $now > zp.start_time and $now < zp.end_time";//进行中
            break;
        case 3:
            $where = " AND $now > zp.end_time and zp.join_money >= zp.amount";  //已成功
            break;
        case 4:

            break;
    }

    $sql = "select zp.*,(end_time-unix_timestamp(now())) as shenyu_time,sum(zg.backer_num) as zhichi_num from " . $GLOBALS['ecs']->table('zc_focus') . "zf
			left join" . $GLOBALS['ecs']->table('zc_project') . "zp on zf.pid=zp.id
			left join " . $GLOBALS['ecs']->table('zc_goods') . "zg on zp.id=zg.pid
			where zf.user_id='$user_id' $where group by zp.id";
    $zc_focus_list = $GLOBALS['db']->getAll($sql);
    foreach ($zc_focus_list as $k => $z_val) {
        $zc_focus_list[$k]['start_time'] = local_date($GLOBALS['_CFG']['time_format'], $z_val['start_time']);
        $zc_focus_list[$k]['end_time'] = local_date($GLOBALS['_CFG']['time_format'], $z_val['end_time']);
        $zc_focus_list[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $zc_focus_list[$k]['title_img'] = get_zc_image_path($z_val['title_img']);
        $zc_focus_list[$k]['url'] = url('crowd_funding/index/info', ['id' => $z_val['id']]);
        $zc_focus_list[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 2) * 100;
        $zc_focus_list[$k]['min_price'] = plan_min_price($z_val['id']); //获取方案最低价格
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $zc_focus_list[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $zc_focus_list[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        //众筹活动状态 start
        $time = gmtime();

        if ($time > $z_val['start_time'] && $time < $z_val['end_time']) {
            $zc_focus_list[$k]['zc_status'] = '活动进行中';
        } elseif ($time > $z_val['end_time'] && $z_val['join_money'] >= $z_val['amount']) {
            $zc_focus_list[$k]['zc_status'] = '活动结束';
            $zc_focus_list[$k]['shenyu_time'] = 0;
        } else {
            $zc_focus_list[$k]['zc_status'] = '活动失败';
            $zc_focus_list[$k]['shenyu_time'] = 0;
        }
        //众筹活动状态 end
    }


    return $zc_focus_list;
}

/**
 * 用户支持的众筹项目列表
 */
function crowd_buy_list($user_id = 0, $size = 10, $page = 1, $type = 1)
{
    $now = gmtime();
    switch ($type) {
        case 1:
            $where = " ";                  //全部
            break;
        case 2:
            $where = " AND $now > zp.start_time and $now < zp.end_time";//进行中
            break;
        case 3:
            $where = " AND $now > zp.end_time and zp.join_money >= zp.amount";  //已成功
            break;
        case 4:

            break;
    }

    $sql = "select zp.id, zp.title,zp.start_time,zp.end_time,zp.amount,zp.join_money,zp.describe,zp.title_img,(end_time-unix_timestamp(now())) as shenyu_time,oi.order_id,oi.pay_status,shipping_status,zp.join_num as zhichi_num from " . $GLOBALS['ecs']->table('zc_goods') . " as zg left join " . $GLOBALS['ecs']->table('zc_project') . " as zp on zg.pid=zp.id
			left join " . $GLOBALS['ecs']->table('order_info') . "as oi on zg.id=oi.zc_goods_id where oi.user_id='$user_id' $where and oi.is_zc_order=1  GROUP BY zp.id order by oi.order_id desc  ";

    $buy_list = $GLOBALS['db']->getAll($sql);
    $total = is_array($buy_list) ? count($buy_list) : 0;
    $crowd_buy_list = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    foreach ($crowd_buy_list as $k => $z_val) {
        $crowd_buy_list[$k]['start_time'] = local_date($GLOBALS['_CFG']['time_format'], $z_val['start_time']);
        $crowd_buy_list[$k]['end_time'] = local_date($GLOBALS['_CFG']['time_format'], $z_val['end_time']);
        $crowd_buy_list[$k]['shenyu_time'] = ceil($z_val['shenyu_time'] / 3600 / 24);
        $crowd_buy_list[$k]['title_img'] = get_zc_image_path($z_val['title_img']);
        $crowd_buy_list[$k]['url'] = url('crowd_funding/index/info', ['id' => $z_val['id']]);
        $crowd_buy_list[$k]['baifen_bi'] = round($z_val['join_money'] / $z_val['amount'], 2) * 100;
        $crowd_buy_list[$k]['min_price'] = plan_min_price($z_val['id']); //获取方案最低价格
        if (mb_strlen($z_val['describe'], 'utf-8') > 50) {
            $crowd_buy_list[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8') . '...';
        } else {
            $crowd_buy_list[$k]['duan_des'] = mb_substr($z_val['describe'], 0, 50, 'utf-8');
        }
        //众筹活动状态 start
        $time = gmtime();

        if ($time > $z_val['start_time'] && $time < $z_val['end_time']) {
            $crowd_buy_list[$k]['zc_status'] = '活动进行中';
        } elseif ($time > $z_val['end_time'] && $z_val['join_money'] >= $z_val['amount']) {
            $crowd_buy_list[$k]['zc_status'] = '活动结束';
            $crowd_buy_list[$k]['shenyu_time'] = 0;
        } else {
            $crowd_buy_list[$k]['zc_status'] = '活动失败';
            $crowd_buy_list[$k]['shenyu_time'] = 0;
        }
        //众筹活动状态 end
    }
    return ['list' => array_values($crowd_buy_list), 'totalpage' => ceil($total / $size)];
}

/**
 *  获取用户指定范围的订单列表
 *
 * @access  public
 * @param   int $user_id 用户ID号
 * @param   int $size 列表最大数量
 * @param   int $start 列表起始位置
 * @return  array       $order_list     订单列表
 */
function zc_get_user_orders($user_id, $size = 10, $page = 1, $status = 0)
{
    $where = "";

    if ($status == 1) {
        // 全部订单
        $where = '';
    } elseif ($status == 2) {
        // 未付款 但不包含已取消、无效、退货订单的订单
        $where = 'and oi.pay_status = ' . PS_UNPAYED . ' and oi.order_status not in(' . OS_CANCELED . ',' . OS_INVALID . ',' . OS_RETURNED . ')';
    } elseif ($status == 3) {
        // //待发货
        $where = 'and oi.pay_status = ' . PS_PAYED . ' and oi.shipping_status =' . SS_UNSHIPPED;
    } elseif ($status == 4) {
        // //待收货
        $where = 'and oi.pay_status = ' . PS_PAYED . ' and oi.shipping_status =' . SS_SHIPPED;
    } else {
        // 已完结
        $where = 'and oi.pay_status = ' . PS_PAYED . ' and oi.shipping_status =' . SS_RECEIVED;
    }


    /* 取得订单列表 */
    $arr = [];
    $now = time();

    $sql = "select zp.id,zp.title,zp.start_time,zp.end_time,zp.amount,zp.join_money,zp.title_img,zg.content,zg.price,oi.consignee,oi.pay_name, oi.order_id, oi.order_sn, oi.order_status, oi.shipping_status, oi.pay_status, oi.add_time, oi.shipping_time, oi.auto_delivery_time, oi.sign_time,(oi.goods_amount + oi.shipping_fee + oi.insure_fee + oi.pay_fee + oi.pack_fee + oi.card_fee + oi.tax - oi.discount) AS total_fee,oi.invoice_no, oi.shipping_name, oi.tel, oi.email, oi.address, oi.province, oi.city, oi.district  from " . $GLOBALS['ecs']->table('zc_goods') . " as zg left join " . $GLOBALS['ecs']->table('zc_project') . " as zp on zg.pid=zp.id
			left join " . $GLOBALS['ecs']->table('order_info') . "as oi on zg.id=oi.zc_goods_id where oi.user_id='$user_id' $where and oi.is_zc_order=1 and oi.is_delete = '0' order by oi.order_id desc";
    $orderlist = $GLOBALS['db']->getAll($sql);
    $total = is_array($orderlist) ? count($orderlist) : 0;
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    foreach ($res as $key => $row) {
        $os = L('os');
        $ps = L('ps');
        $ss = L('ss');
        if ($row['order_status'] == OS_UNCONFIRMED) {
            $row['handler'] = "<a class=\"btn-default\" href=\"" . url('user/crowd/cancel', ['order_id' => $row['order_id']]) . "\" onclick=\"if (!confirm('" . L('confirm_cancel') . "')) return false;\">" . L('cancel') . "</a>";
        } elseif ($row['order_status'] == OS_SPLITED) {
            /* 对配送状态的处理 */
            if ($row['shipping_status'] == SS_SHIPPED) {
                @$row['handler'] = "<a class=\"btn-default\" href=\"" . url('user/crowd/affirmreceived', ['order_id' => $row['order_id']]) . "\" onclick=\"if (!confirm('" . L('confirm_received') . "')) return false;\">" . L('received') . "</a>";
            } elseif ($row['shipping_status'] == SS_RECEIVED) {
                @$row['handler'] = '<a class="btn-default">' . L('ss_received') . '</a>';
            } else {
                if ($row['pay_status'] == PS_UNPAYED) {
                    @$row['handler'] = "<a class=\"btn-default\" href=\"" . url('user/crowd/detail', ['order_id' => $row['order_id']]) . '" >' . L('pay_money') . '</a>';
                } else {
                    @$row['handler'] = "<a  class=\"btn-default\" href=\"" . url('user/crowd/detail', ['order_id' => $row['order_id']]) . '">' . L('view_order') . '</a>';
                }
            }
        } else {
            $row['handler'] = '<a class="btn-default">' . $os[$row['order_status']] . '</a>';
        }
        //ecmoban模板堂 --zhuo start
        $row['user_order'] = $row['order_status'];
        $row['user_shipping'] = $row['shipping_status'];
        $row['user_pay'] = $row['pay_status'];
        //订单删除
        if ($row[order_status] == 2 || ($row[order_status] == 5 && $row[shipping_status] == 2 && $row[pay_status] == 2)) {
            $row['order_del'] = 1;
        }

        if ($row['user_order'] == OS_SPLITED && $row['user_shipping'] == SS_RECEIVED && $row['user_pay'] == PS_PAYED) {
            $row['delete_yes'] = 1;
        } elseif (($row['user_order'] == OS_CONFIRMED || $row['user_order'] == OS_UNCONFIRMED || $row['user_order'] == OS_CANCELED) && $row['user_shipping'] == SS_UNSHIPPED && $row['user_pay'] == PS_UNPAYED) {
            $row['delete_yes'] = 1;
        } elseif ($row['user_order'] == OS_INVALID && $row['user_pay'] == PS_PAYED_PART && $row['user_shipping'] == SS_UNSHIPPED) {
            $row['delete_yes'] = 1;
        } else {
            $row['delete_yes'] = 0;
        }

        //判断是否已评论或晒单 end
        $row['shipping_status'] = ($row['shipping_status'] == SS_SHIPPED_ING) ? SS_PREPARING : $row['shipping_status'];

        //订单状态
        $row['order_status'] = $os[$row[order_status]] . ',' . $ps[$row[pay_status]] . ',' . $ss[$row['shipping_status']];

        $br = '';
        $order_over = 0;

        if ($row['user_order'] == OS_CANCELED && $row['user_shipping'] == SS_UNSHIPPED && $row['user_pay'] == PS_UNPAYED) {
            $order_over = 1; //订单已取消
            //$row['order_status'] = $GLOBALS['_LANG']['os'][OS_CANCELED];
            $row['handler'] = '';
        } elseif ($row['user_order'] == OS_SPLITED && $row['user_shipping'] == SS_SHIPPED && $row['user_pay'] == PS_PAYED) { //确认收货
            $row['handler'] = $row['handler'];
            $br = "<br/>";
        } else {
            if (!($row['user_order'] == OS_UNCONFIRMED && $row['user_shipping'] == SS_UNSHIPPED && $row['user_pay'] == PS_UNPAYED)) {
                $row['handler'] = '';
            } else {
                $br = "<br/>";
            }
        }

        $sql = "select invoice_no, shipping_name, update_time from " . $GLOBALS['ecs']->table('delivery_order') . " where order_id = '" . $row['order_id'] . "'";
        $delivery = $GLOBALS['db']->getRow($sql);

        $delivery['delivery_time'] = local_date($GLOBALS['_CFG']['time_format'], $delivery['update_time']);

        //众筹活动状态 start
        $time = gmtime();

        if ($time > $row['start_time'] && $time < $row['end_time']) {
            $zc_status = '项目进行中';
        } elseif ($time > $row['end_time'] && $row['join_money'] >= $row['amount']) {
            $zc_status = '项目成功';
        } else {
            $zc_status = '项目失败';
        }
        //众筹活动状态 end
        $arr[] = ['order_id' => $row['order_id'],
            'order_sn' => $row['order_sn'],
            'add_time' => local_date($GLOBALS['_CFG']['time_format'], $row['add_time']),
            'order_status' => $row['order_status'],
            'order_del' => $row['order_del'],
            'consignee' => $row['consignee'],
            'title' => $row['title'], //项目名称
            'title_img' => get_zc_image_path($row['title_img']), //项目图片
            'content' => $row['content'], //项目商品名称
            'price' => $row['price'], //项目商品价格
            'zc_status' => $zc_status, //众筹活动状态
            'no_picture' => $GLOBALS['_CFG']['no_picture'],
            'delete_yes' => $row['delete_yes'],
            'invoice_no' => $row['invoice_no'],
            'shipping_name' => $row['shipping_name'],
            'email' => $row['email'],
            'tel' => $row['tel'],
            'delivery_time' => $delivery['delivery_time'],
            'total_fee' => price_format($row['total_fee'], false),//总额
            'handler_return' => $row['handler_return'],
            'pay_status' => $row['pay_status'],
            'handler' => $row['handler'],
            'order_url' => url('user/crowd/detail', ['order_id' => $row['order_id']])];
    }

    return ['list' => array_values($arr), 'totalpage' => ceil($total / $size)];
}

/**
 * 取消一个用户订单
 *
 * @access  public
 * @param   int $order_id 订单ID
 * @param   int $user_id 用户ID
 *
 * @return void
 */
function zc_cancel_order($order_id, $user_id = 0)
{
    /* 查询订单信息，检查状态 */
    $sql = "SELECT user_id, order_id, order_sn , surplus , integral , bonus_id, order_status, shipping_status, pay_status FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    if (empty($order)) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['order_exist']);
        return false;
    }

    // 如果用户ID大于0，检查订单是否属于该用户
    if ($user_id > 0 && $order['user_id'] != $user_id) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['no_priv']);

        return false;
    }

    // 订单状态只能是“未确认”或“已确认”
    if ($order['order_status'] != OS_UNCONFIRMED && $order['order_status'] != OS_CONFIRMED) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['current_os_not_unconfirmed']);

        return false;
    }

    //订单一旦确认，不允许用户取消
    if ($order['order_status'] == OS_CONFIRMED) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['current_os_already_confirmed']);

        return false;
    }

    // 发货状态只能是“未发货”
    if ($order['shipping_status'] != SS_UNSHIPPED) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['current_ss_not_cancel']);

        return false;
    }

    // 如果付款状态是“已付款”、“付款中”，不允许取消，要取消和商家联系
    if ($order['pay_status'] != PS_UNPAYED) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['current_ps_not_cancel']);

        return false;
    }

    //将用户订单设置为取消
    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') . " SET order_status = '" . OS_CANCELED . "' WHERE order_id = '$order_id'";
    if ($GLOBALS['db']->query($sql)) {
        /* 记录log */
        order_action($order['order_sn'], OS_CANCELED, $order['shipping_status'], PS_UNPAYED, L('buyer_cancel'), 'buyer');
        /* 退货用户余额、积分、红包 */
        if ($order['user_id'] > 0 && $order['surplus'] > 0) {
            $change_desc = sprintf(L('return_surplus_on_cancel'), $order['order_sn']);
            log_account_change($order['user_id'], $order['surplus'], 0, 0, 0, $change_desc);
        }
        if ($order['user_id'] > 0 && $order['integral'] > 0) {
            $change_desc = sprintf(L('return_integral_on_cancel'), $order['order_sn']);
            log_account_change($order['user_id'], 0, 0, 0, $order['integral'], $change_desc);
        }
        if ($order['user_id'] > 0 && $order['bonus_id'] > 0) {
            change_user_bonus($order['bonus_id'], $order['order_id'], false);
        }

        /* 如果使用库存，且下订单时减库存，则增加库存 */
        /*  if ($GLOBALS['_CFG']['use_storage'] == '1' && $GLOBALS['_CFG']['stock_dec_time'] == SDT_PLACE) {
          change_order_goods_storage($order['order_id'], false, 1);
          } */

        /* 修改订单 */
        $arr = [
            'bonus_id' => 0,
            'bonus' => 0,
            'integral' => 0,
            'integral_money' => 0,
            'surplus' => 0
        ];
        //update_order($order['order_id'], $arr);

        return true;
    } else {
        die($GLOBALS['db']->errorMsg());
    }
}

/**
 *  获取指订单的详情
 *
 * @access  public
 * @param   int $order_id 订单ID
 * @param   int $user_id 用户ID
 *
 * @return   arr        $order          订单所有信息的数组
 */
function zc_get_order_detail($order_id, $user_id = 0)
{
    include_once(BASE_PATH . 'Helpers/order_helper.php');

    $order_id = intval($order_id);
    if ($order_id <= 0) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['invalid_order_id']);

        return false;
    }
    $order = zc_order_info($order_id);

    //检查订单是否属于该用户
    if ($user_id > 0 && $user_id != $order['user_id']) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['no_priv']);

        return false;
    }
    /* 对发货号处理 */
    if (!empty($order['invoice_no'])) {
        $shipping_code = $GLOBALS['db']->GetOne("SELECT shipping_code FROM " . $GLOBALS['ecs']->table('shipping') . " WHERE shipping_id = '$order[shipping_id]'");
        $plugin = ADDONS_PATH . 'shipping/' . $shipping_code . '.php';
        if (file_exists($plugin)) {
            include_once($plugin);
            $shipping = new $shipping_code;
            $order['invoice_no'] = $shipping->query($order['invoice_no']);
        }
    }

    /* 只有未确认才允许用户修改订单地址 */
    if ($order['order_status'] == OS_UNCONFIRMED) {
        $order['allow_update_address'] = 1; //允许修改收货地址
    } else {
        $order['allow_update_address'] = 0;
    }

    /* 获取订单中实体商品数量 */
    $order['exist_real_goods'] = exist_real_goods($order_id);

    /* 如果是未付款状态，生成支付按钮 */
    if ($order['pay_status'] == PS_UNPAYED || $order['pay_status'] == PS_PAYED_PART &&
        ($order['order_status'] == OS_UNCONFIRMED ||
            $order['order_status'] == OS_CONFIRMED)
    ) {
        /*
         * 在线支付按钮
         */
        //支付方式信息
        $payment_info = [];
        $payment_info = payment_info($order['pay_id']);

        //无效支付方式
        if ($payment_info === false) {
            $order['pay_online'] = '';
        } else {
            //ecmoban模板堂 --will改 start
            //pc端如果使用的是app的支付方式，也不生成支付按钮
            if (substr($payment_info['pay_code'], 0, 4) == 'pay_') {
                $order['pay_online'] = '';
            } else {
                //取得支付信息，生成支付代码
                $payment = unserialize_config($payment_info['pay_config']);

                //获取需要支付的log_id
                $order['log_id'] = get_paylog_id($order['order_id'], $pay_type = PAY_ORDER);
                $order['user_name'] = $_SESSION['user_name'];
                $order['pay_desc'] = $payment_info['pay_desc'];
                $order['pay_online'] = '';
                /* 调用相应的支付方式文件 */
                if (file_exists(ADDONS_PATH . 'payment/' . $payment_info['pay_code'] . '.php')) {
                    include_once(ADDONS_PATH . 'payment/' . $payment_info['pay_code'] . '.php');

                    /* 取得在线支付方式的支付按钮u */
                    $pay_obj = new $payment_info['pay_code'];
                    if (OS_RETURNED != $order['order_status']) {
                        // 取消 退货统一订单bug
                        $order['pay_online'] = $pay_obj->get_code($order, $payment);
                    }
                }
            }
            //ecmoban模板堂 --will end
        }
    } else {
        $order['pay_online'] = '';
    }

    /* 无配送时的处理 */
    $order['shipping_id'] == -1 and $order['shipping_name'] = $GLOBALS['_LANG']['shipping_not_need'];

    /* 其他信息初始化 */
    $order['how_oos_name'] = $order['how_oos'];
    $order['how_surplus_name'] = $order['how_surplus'];

    /* 虚拟商品付款后处理 */
    if ($order['pay_status'] != PS_UNPAYED) {
        /* 取得已发货的虚拟商品信息 */
        $virtual_goods = get_virtual_goods($order_id, true);
        $virtual_card = [];
        foreach ($virtual_goods as $code => $goods_list) {
            /* 只处理虚拟卡 */
            if ($code == 'virtual_card') {
                foreach ($goods_list as $goods) {
                    if ($info = virtual_card_result($order['order_sn'], $goods)) {
                        $virtual_card[] = ['goods_id' => $goods['goods_id'], 'goods_name' => $goods['goods_name'], 'info' => $info];
                    }
                }
            }
            /* 处理超值礼包里面的虚拟卡 */
            if ($code == 'package_buy') {
                foreach ($goods_list as $goods) {
                    $sql = 'SELECT g.goods_id FROM ' . $GLOBALS['ecs']->table('package_goods') . ' AS pg, ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
                        "WHERE pg.goods_id = g.goods_id AND pg.package_id = '" . $goods['goods_id'] . "' AND extension_code = 'virtual_card'";
                    $vcard_arr = $GLOBALS['db']->getAll($sql);

                    foreach ($vcard_arr as $val) {
                        if ($info = virtual_card_result($order['order_sn'], $val)) {
                            $virtual_card[] = ['goods_id' => $goods['goods_id'], 'goods_name' => $goods['goods_name'], 'info' => $info];
                        }
                    }
                }
            }
        }
        $var_card = deleteRepeat($virtual_card);
        $GLOBALS['smarty']->assign('virtual_card', $var_card);
    }


    /* 确认时间 支付时间 发货时间 */
    if ($order['confirm_time'] > 0 && ($order['order_status'] == OS_CONFIRMED || $order['order_status'] == OS_SPLITED || $order['order_status'] == OS_SPLITING_PART)) {
        $order['confirm_time'] = sprintf($GLOBALS['_LANG']['confirm_time'], local_date($GLOBALS['_CFG']['time_format'], $order['confirm_time']));
    } else {
        $order['confirm_time'] = '';
    }

    if ($order['pay_time'] > 0) {
        $order['pay_time'] = $order['pay_time'];
    } else {
        $order['pay_time'] = '';
    }
    if ($order['shipping_time'] > 0 && in_array($order['shipping_status'], [SS_SHIPPED, SS_RECEIVED])) {
        $order['shipping_time'] = sprintf($GLOBALS['_LANG']['shipping_time'], local_date($GLOBALS['_CFG']['time_format'], $order['shipping_time']));
    } else {
        $order['shipping_time'] = '';
    }
    return $order;
}

/**
 * 取得订单信息
 * @param   int $order_id 订单id（如果order_id > 0 就按id查，否则按sn查）
 * @param   string $order_sn 订单号
 * @return  array   订单信息（金额都有相应格式化的字段，前缀是formated_）
 */
function zc_order_info($order_id, $order_sn = '')
{
    $order_id = intval($order_id);
    $now = gmtime();
    $sql = "select zp.id,zp.title,zp.title_img,zp.amount,zp.join_money,zp.join_num,(zp.end_time-unix_timestamp(now())) as shenyu_time,zg.content,zg.price,oi.consignee,oi.pay_name,oi.* ,(oi.goods_amount + oi.shipping_fee + oi.insure_fee + oi.pay_fee + oi.pack_fee + oi.card_fee + oi.tax - oi.discount) AS total_fee  from " . $GLOBALS['ecs']->table('zc_goods') . " as zg left join " . $GLOBALS['ecs']->table('zc_project') . " as zp on zg.pid=zp.id
			left join " . $GLOBALS['ecs']->table('order_info') . "as oi on zg.id=oi.zc_goods_id where oi.order_id='$order_id'  and oi.is_zc_order=1 ";

    $order = $GLOBALS['db']->getRow($sql);
    $order['shenyu_time'] = ceil($order['shenyu_time'] / 3600 / 24);
    $order['baifen_bi'] = round($order['join_money'] / $order['amount'], 2) * 100;
    $order['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['add_time']);
    $order['title_img'] = get_zc_image_path($order['title_img']);
    $order['url'] = url('crowd_funding/index/info', ['id' => $order['id']]);
    if ($order['pay_time'] > 0) {
        $order['pay_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['pay_time']);
    }

    $os = L('os');


    /* 格式化金额字段 */
    if ($order) {
        $order['formated_goods_amount'] = price_format($order['goods_amount'], false);
        $order['formated_discount'] = price_format($order['discount'], false);
        $order['formated_tax'] = price_format($order['tax'], false);
        $order['formated_shipping_fee'] = price_format($order['shipping_fee'], false);
        $order['formated_insure_fee'] = price_format($order['insure_fee'], false);
        $order['formated_pay_fee'] = price_format($order['pay_fee'], false);
        $order['formated_pack_fee'] = price_format($order['pack_fee'], false);
        $order['formated_card_fee'] = price_format($order['card_fee'], false);
        $order['formated_total_fee'] = price_format($order['total_fee'], false);
        $order['formated_money_paid'] = price_format($order['money_paid'], false);
        $order['formated_bonus'] = price_format($order['bonus'], false);
        $order['formated_integral_money'] = price_format($order['integral_money'], false);
        $order['formated_surplus'] = price_format($order['surplus'], false);
        $order['formated_order_amount'] = price_format(abs($order['order_amount']), false);
        $order['formated_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['add_time']);
    }
    return $order;
}

/**
 * 获取方案最低价格     zc_project
 */
function plan_min_price($pid = 0)
{
    $sql = 'SELECT min(price) as price ' . 'FROM '
        . $GLOBALS['ecs']->table('zc_goods') . "WHERE  pid = '$pid'  ";
    $res = $GLOBALS['db']->getRow($sql);
    return $res['price'];
}

/**
 * 个人中心优惠券列表
 * @param type $num 每页显示记录条数
 * @param type $page 页数
 * @param type $status 状态
 * @return type
 */
function get_coupons_lists($num = 2, $page = 1, $status = 0)
{
    $time = gmtime();//当前时间
    $uid = $_SESSION['user_id'];
    if ($status == 0) {
        //领取的优惠券未使用
        $where = "where cu.is_use = 0  and cu.user_id = '$uid' and c.cou_end_time>'$time' ";
    } elseif ($status == 1) {
        //已使用的
        $where = "where cu.is_use = 1  and cu.user_id = '$uid' ";
    } elseif ($status == 2) {
        //过期
        $where = "where  '$time' > c.cou_end_time and  cu.is_use = 0  and cu.user_id = '$uid'";
    }
    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('coupons_user') . " AS cu LEFT JOIN  " . $GLOBALS['ecs']->table('coupons') . " AS c ON c.cou_id = cu.cou_id  " . $where . "AND c.review_status = 3" ;
    //总条数
    $total = $GLOBALS['db']->getOne($sql);

    $start = ($page - 1) * $num;

    $left_join = " LEFT JOIN " . $GLOBALS['ecs']->table('order_info') ." AS o ON cu.order_id = o.order_id ";
    $sql = "SELECT c.*, cu.*, o.order_sn, o.add_time FROM " . $GLOBALS['ecs']->table('coupons_user') . " AS cu LEFT JOIN  " . $GLOBALS['ecs']->table('coupons') . " AS c ON c.cou_id = cu.cou_id " .
        $left_join .
        $where . " AND c.review_status = 3 limit $start,$num ";
    $tab = $GLOBALS['db']->getAll($sql);

    foreach ($tab as &$v) {
        $v['begintime'] = local_date("Y-m-d", $v['cou_start_time']);
        $v['endtime'] = local_date("Y-m-d", $v['cou_end_time']);
        $v['img'] = "images/coupons_default.png";

        $v['add_time'] = local_date('Y-m-d', $v['add_time']); //订单生成时间即算优惠券使用时间

        //如果指定了使用的优惠券的商品,取出允许使用优惠券的商品
        if (!empty($v['cou_goods'])) {
            $v['goods_list'] = $GLOBALS['db']->getAll("SELECT goods_name FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id IN (" . $v['cou_goods'] . ")");
        }

        //获取店铺名称区分平台和店铺(平台发的全平台用,商家发的商家店铺用)
        if ($v['ru_id']) {
            $v['store_name'] = sprintf(L('use_limit'), get_shop_name($v['ru_id'], 1));
        } else {
            $v['store_name'] = L('platform_use');
        }

        //格式化类型名称
        $v['cou_type_name'] = $v['cou_type'] == 1 ? L('vouchers_login') : ($v['cou_type'] == 2 ? L('vouchers_shoping') :($v['cou_type'] == 3 ? L('vouchers_all') : ($v['cou_type'] == 4 ? L('vouchers_user') : ($v['cou_type'] == 5 ? L('vouchers_shipping') : L('unknown')))));

        // 是否过期
        $v['is_overdue'] = $v['cou_end_time'] < gmtime() ? 1 : 0;
        // 立即使用
        $v['to_use_url'] = $v['is_use'] == 0 ? url('category/index/products', ['id' => 0, 'intro' => '', 'cou_id' => $v['cou_id']]) : '';
    }

    $result = ['tab' => $tab, 'totalpage' => ceil($total / $num)];
    return $result;
}

/**
 *  获得退换货订单信息
 * @param type $order_id
 * @param type $user_id
 * @return boolean by leah
 */
function get_return_detail($ret_id, $user_id = 0)
{
    $ret_id = intval($ret_id);
    if ($ret_id <= 0) {
        $GLOBALS['err']->add($GLOBALS['_LANG']['invalid_order_id']);

        return false;
    }
    $order = return_order_info($ret_id);

    return $order;
}

/**
 * 取消一个退换单
 * @access  public
 * @param   int $order_id 订单ID
 * @param   int $user_id 用户ID
 *
 * @return void
 */
function cancel_return($ret_id, $user_id = 0)
{
    /* 查询订单信息，检查状态 */
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('order_return') . " WHERE ret_id = '$ret_id'";
    $order = $GLOBALS['db']->GetRow($sql);

    if (empty($order)) {
        $GLOBALS['err']->add(L('return_exist'));
        return false;
    }

    // 如果用户ID大于0，检查订单是否属于该用户
    if ($user_id > 0 && $order['user_id'] != $user_id) {
        $GLOBALS['err']->add(L('no_priv'));

        return false;
    }

    // 订单状态只能是用户寄回和未退款状态
    if ($order['return_status'] != RF_APPLICATION && $order['refound_status'] != FF_NOREFOUND) {
        $GLOBALS['err']->add(L('return_not_unconfirmed'));

        return false;
    }

    //一旦由商家收到退换货商品，不允许用户取消
    if ($order['return_status'] == RF_RECEIVE) {
        $GLOBALS['err']->add(L('current_os_already_receive'));

        return false;
    }

    // 商家已发送退换货商品
    if ($order['return_status'] == RF_SWAPPED_OUT_SINGLE || $order['return_status'] == RF_SWAPPED_OUT) {
        $GLOBALS['err']->add(L('already_out_goods'));

        return false;
    }

    // 如果付款状态是“已付款”、“付款中”，不允许取消，要取消和商家联系
    if ($order['refound_status'] == FF_REFOUND) {
        $GLOBALS['err']->add(L('have_refound'));

        return false;
    }

    //将用户订单设置为取消
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('order_return') . " WHERE ret_id =" . $ret_id;
    if ($GLOBALS['db']->query($sql)) {
        $sql = "DELETE FROM " . $GLOBALS['ecs']->table('return_goods') . " WHERE rec_id =" . $order['rec_id'];
        $GLOBALS['db']->query($sql);

        $sql = "select img_file from " . $GLOBALS['ecs']->table('return_images') . " where user_id = '" . $_SESSION['user_id'] . "' and rec_id = '" . $order['rec_id'] . "'";
        $img_list = $GLOBALS['db']->getAll($sql);

        if ($img_list) {
            foreach ($img_list as $key => $row) {
                @unlink(ROOT_PATH . $row['img_file']);
            }

            $sql = "delete from " . $GLOBALS['ecs']->table('return_images') . " where user_id = '" . $_SESSION['user_id'] . "' and rec_id = '" . $order['rec_id'] . "'";
            $GLOBALS['db']->query($sql);
        }

        /* 删除扩展记录  by kong */
        $sql = "delete from " . $GLOBALS['ecs']->table('order_return_extend') . " where ret_id = '" . $ret_id . "' ";
        $GLOBALS['db']->query($sql);

        /* 记录log */
        return_action($ret_id, '取消', '', '', '买家', '');

        return true;
    } else {
        die($GLOBALS['db']->errorMsg());
    }
}

/**
 * 统计退货单记录
 * 退换货状态 not in 1 收到退换货,  4 完成退换货
 */
function get_count_return()
{
    $sql = "SELECT count(*) as num FROM " . $GLOBALS['ecs']->table('order_return') . " WHERE return_status NOT IN (" . RF_RECEIVE . "," . RF_COMPLETE . " )  AND user_id = '" . $_SESSION['user_id'] . "' ";
    $count = $GLOBALS['db']->getRow($sql);

    return $count['num'];
}

/**
 * 获取售后服务订单
 */
function get_all_return_order($order_id = 0)
{
    if (!empty($order_id) && !is_int($order_id)) {
        die(json_encode(['error' => 1, 'content' => '订单号不存在']));
    }

    $where = '';
    if ($order_id > 0) {
        $where = ' AND o.order_id = ' . $order_id;
    }

    $sign_time = C('shop.sign');       //发货日期起可退换货时间
    $time = gmtime();  //现在时间
    $log_time = $time - $sign_time * 24 * 3600; // log时间内的订单
    //一天内退款
    $p_time = $time - 1 * 24 * 3600;

    $sql = "SELECT o.order_id, o.order_sn, o.add_time, o.extension_id FROM " . $GLOBALS['ecs']->table('order_info') . " AS o"
        . " WHERE o.user_id = '" . $_SESSION['user_id'] . "' AND o.order_status  IN ('1','5', '6')  AND o.pay_status  IN ('2','3') "
        . " AND o.is_delete = 0 "
        . " AND ((o.shipping_time > " . $log_time . " AND o.shipping_time < " . $time . ") OR (o.pay_time > " . $p_time. " AND o.shipping_status IN ('0', '3', '5')))"  . $where
        . " group by o.order_id ORDER BY o.add_time DESC";

    $order = $GLOBALS['db']->query($sql);

    foreach ($order as $key => $val) {
        $order[$key]["add_time"] = local_date($GLOBALS['_CFG']['time_format'], $val["add_time"]);

        $sql = "SELECT og.order_id, og.goods_name, og.goods_number, og.rec_id, g.goods_id, IF(og.extension_code = 'package_buy', (SELECT activity_thumb FROM " . $GLOBALS['ecs']->table('goods_activity') . " WHERE act_id = g.goods_id),g.goods_thumb) as goods_thumb
			FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og"
            . " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON  og.goods_id = g.goods_id"
            . " WHERE og.order_id = " . $val['order_id'];

        $goods = $GLOBALS['db']->query($sql);
        foreach ($goods as $gkey => $gval) {
            $goods[$gkey]['goods_thumb'] = get_image_path($gval['goods_thumb']);
            $goods[$gkey]['is_refound'] = get_is_refound($gval['rec_id']); //检测是否存在退货记录s
            if ($goods[$gkey]['is_refound'] == 0) {
                $goods[$gkey]['apply_return_url'] = url('user/refound/apply_return', ['order_goods_id' => $gval['rec_id'], 'order_id' => $gval['order_id']]);
            }
        }
        $order[$key]['goods_list'] = $goods;
        $order[$key]['order_url'] = url('user/order/detail', ['order_id' => $val['order_id']]);
    }

    return $order;
}

/**
 * 获取订单商品信息
 */
function get_order_goods_info($id)
{
    $sql = "SELECT og.goods_name, og.goods_number, g.goods_thumb, g.user_id, og.goods_attr, g.goods_cause, og.goods_price, IF(og.extension_code = 'package_buy', (SELECT activity_thumb FROM " . $GLOBALS['ecs']->table('goods_activity') . " WHERE act_id = g.goods_id),g.goods_thumb) as goods_thumb
			FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og"
        . " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON  og.goods_id = g.goods_id"
        . " WHERE og.rec_id = " . $id;

    $goods = $GLOBALS['db']->getRow($sql);
    $goods['goods_thumb'] = get_image_path($goods['goods_thumb']);
    $goods['user_name'] = get_shop_name($goods['user_id'], 1);
    return $goods;
}

/**
 * 判断是否授权登录用户
 * @param  integer $user_id
 * @return boolean
 */
function is_connect_user($user_id)
{
    $is_connect_user = dao('connect_user')->where(['user_id' => $user_id])->count();
    return $is_connect_user;
}

/**
 * 查询会员信息
 * @param  integer $user_id
 * @param  integer $wechat_id
 * @return array
 */
function get_users($user_id)
{
    $result = dao('users')->field('user_name, user_id, mobile_phone, email, user_picture')->where(['user_id' => $user_id])->find();
    return $result;
}

/**
 *  获取订单投诉列表
 *
 * @access  public
 * @param   int $num 列表最大数量
 * @param   int $start 列表起始位置
 * @return  array       $order_list     列表
 */
function get_complaint_list($num = 10, $page = 1, $where = '', $is_complaint = 0)
{
    $start = ($page - 1) * $num;
    $sql = "SELECT IFNULL(bai.complaint_id,0) AS is_complaint,bai.complaint_state,bai.complaint_active,og.ru_id, oi.order_id, oi.order_sn, oi.add_time, oi.shipping_time, " .
        "(oi.goods_amount + oi.shipping_fee + oi.insure_fee + oi.pay_fee + oi.pack_fee + oi.card_fee + oi.tax - oi.discount) AS total_fee, og.goods_id, " .
        " oi.shipping_name, oi.tel " .
        " FROM " . $GLOBALS['ecs']->table('order_info') . " as oi" .
        " left join " . $GLOBALS['ecs']->table('order_goods') . " as og on oi.order_id = og.order_id" .
        " left join " . $GLOBALS['ecs']->table('complaint') . " as bai on oi.order_id = bai.order_id" . //查询是否已经投诉
        $left_join .
        " WHERE oi.user_id = '" . $_SESSION['user_id'] . "' and oi.is_delete =0 " . $where .
        " and (select count(*) from " . $GLOBALS['ecs']->table('order_info') . " as oi2 where oi2.main_order_id = oi.order_id) = 0 " . //主订单下有子订单时，则主订单不显示
        " group by oi.order_id ORDER BY oi.add_time DESC";
    $counts = $GLOBALS['db']->getAll($sql);
    $counts = is_array($counts) ? count($counts) : 0;
    $sql = "SELECT IFNULL(bai.complaint_id,0) AS is_complaint,bai.complaint_state,bai.complaint_active,og.ru_id, oi.order_id, oi.order_sn, oi.add_time, oi.shipping_time, " .
        "(oi.goods_amount + oi.shipping_fee + oi.insure_fee + oi.pay_fee + oi.pack_fee + oi.card_fee + oi.tax - oi.discount) AS total_fee, og.goods_id, " .
        " oi.shipping_name, oi.tel " .
        " FROM " . $GLOBALS['ecs']->table('order_info') . " as oi" .
        " left join " . $GLOBALS['ecs']->table('order_goods') . " as og on oi.order_id = og.order_id" .
        " left join " . $GLOBALS['ecs']->table('complaint') . " as bai on oi.order_id = bai.order_id" . //查询是否已经投诉
        $left_join .
        " WHERE oi.user_id = '" . $_SESSION['user_id'] . "' and oi.is_delete =0 " . $where .
        " and (select count(*) from " . $GLOBALS['ecs']->table('order_info') . " as oi2 where oi2.main_order_id = oi.order_id) = 0 " . //主订单下有子订单时，则主订单不显示
        " group by oi.order_id ORDER BY oi.add_time DESC LIMIT  $start,$num";
    $res = $GLOBALS['db']->getAll($sql);
    foreach ($res as $key => $row) {
        if ($is_complaint == 0) {
            if ($row['is_complaint'] > 0) {
                continue;
            }
        } else {
            if ($row['is_complaint'] == 0) {
                continue;
            }
        }
        $noTime = gmtime();
        $ru_id = $row['ru_id'];
        $row['order_goods'] = get_order_goods_toInfo($row['order_id']);
        $order_id = $row['order_id'];
        $sql = "select kf_type, kf_ww, kf_qq  from " . $GLOBALS['ecs']->table('seller_shopinfo') . " where ru_id='$ru_id'";
        $basic_info = $GLOBALS['db']->getRow($sql);
        $row['shop_name'] = get_shop_name($ru_id, 1);
        $row['shop_ru_id'] = $ru_id;
        $build_uri = [
            'urid' => $ru_id,
            'append' => $row['shop_name']
        ];
        //
        //        $domain_url = get_seller_domain_url($ru_id, $build_uri);
        //        $row['shop_url'] = $domain_url['domain_name'];

        /* 处理客服QQ数组 by kong */
        if ($basic_info['kf_qq']) {
            $kf_qq = array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
            $kf_qq = explode("|", $kf_qq[0]);
            if (!empty($kf_qq[1])) {
                $kf_qq_one = $kf_qq[1];
            } else {
                $kf_qq_one = "";
            }
        } else {
            $kf_qq_one = "";
        }
        /* 处理客服旺旺数组 by kong */
        if ($basic_info['kf_ww']) {
            $kf_ww = array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
            $kf_ww = explode("|", $kf_ww[0]);
            if (!empty($kf_ww[1])) {
                $kf_ww_one = $kf_ww[1];
            } else {
                $kf_ww_one = "";
            }
        } else {
            $kf_ww_one = "";
        }

        /*  @author-bylu 判断当前商家是否允许"在线客服" start */
        $shop_information = get_shop_name($ru_id); //通过ru_id获取到店铺信息;
        //判断当前商家是平台,还是入驻商家 bylu
        if ($ru_id == 0) {
            //判断平台是否开启了IM在线客服
            if ($GLOBALS['db']->getOne("SELECT kf_im_switch FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = 0", true)) {
                $row['is_dsc'] = true;
            } else {
                $row['is_dsc'] = false;
            }
        } else {
            $row['is_dsc'] = false;
        }
        $row['has_talk'] = 0;
        //获取是否存在未读信息
        if ($row['complaint_state'] > 1) {
            $sql = "SELECT view_state FROM" . $GLOBALS['ecs']->table('complaint_talk') . "WHERE complaint_id='" . $row['is_complaint'] . "' ORDER BY talk_time DESC";
            $talk_list = $GLOBALS['db']->getAll($sql);
            if ($talk_list) {
                foreach ($talk_list as $k => $v) {
                    if ($v['view_state']) {
                        $view_state = explode(',', $v['view_state']);
                        if (!in_array('seller', $view_state)) {
                            $row['has_talk'] = 1;
                            break;
                        }
                    }
                }
            }
        }
        /*  @author-bylu  end */
        $arr[] = ['order_id' => $row['order_id'],
            'order_sn' => $row['order_sn'],
            'order_time' => local_date($GLOBALS['_CFG']['time_format'], $row['add_time']),
            'sign' => $shop_information['is_IM'], //平台是否允许商家使用"在线客服";
            'is_dsc' => $row['is_dsc'],
            'shop_name' => $row['shop_name'], //店铺名称	,
            'shop_url' => $row['shop_url'], //店铺名称	,
            'order_goods' => $row['order_goods'],
            'no_picture' => $GLOBALS['_CFG']['no_picture'],
            'kf_type' => $basic_info['kf_type'],
            'kf_ww' => $kf_ww_one,
            'kf_qq' => $kf_qq_one,
            'total_fee' => price_format($row['total_fee'], false),
            'is_complaint' => $row['is_complaint'],
            'complaint_state' => $row['complaint_state'],
            'complaint_active' => $row['complaint_active'],
            'has_talk' => $row['has_talk'],
            'order_goods_num' => count($row['order_goods']),
            'url' => url('user/order/complaint_apply', ['order_id' => $row['order_id'], 'complaint_id' => $row['is_complaint']])
        ];
    }
    $result = ['list' => $arr, 'totalPage' => ceil($counts / $num)];
    return $result;
}


function get_complaint_title()
{
    $sql = "SELECT title_id , title_name , title_desc FROM " . $GLOBALS['ecs']->table("complain_title") . "WHERE is_show=1";
    $report_type = $GLOBALS['db']->getAll($sql);
    return $report_type;
}

//获取交易纠纷详情
function get_complaint_info($complaint_id = 0)
{
    //获取投诉详情
    $sql = "SELECT complaint_id,order_id,order_sn,user_id,user_name,ru_id,shop_name,title_id,complaint_content,add_time,complaint_handle_time,"
        . "admin_id,appeal_messg,appeal_time,end_handle_time,end_admin_id,complaint_state,complaint_active,end_handle_messg FROM" . $GLOBALS['ecs']->table('complaint')
        . " WHERE complaint_id = '$complaint_id' LIMIT 1";
    $complaint_info = $GLOBALS['db']->getRow($sql);
    $complaint_info['title_name'] = $GLOBALS['db']->getOne("SELECT title_name FROM" . $GLOBALS["ecs"]->table('complain_title') . "WHERE title_id = '" . $complaint_info['title_id'] . "'");

    //获取举报图片列表
    $sql = "SELECT img_file ,img_id FROM " . $GLOBALS["ecs"]->table('complaint_img') . " WHERE complaint_id = '" . $complaint_info['complaint_id'] . "' ORDER BY  img_id DESC";
    $img_list = $GLOBALS['db']->getAll($sql);
    if (!empty($img_list)) {
        foreach ($img_list as $k => $v) {
            $img_list[$k]['img_file'] = get_image_path($v['img_id'], $v['img_file']);
        }
    }
    $complaint_info['img_list'] = $img_list;

    //申诉图片列表
    $sql = "SELECT img_file ,img_id FROM " . $GLOBALS["ecs"]->table('appeal_img') . " WHERE complaint_id = '" . $complaint_info['complaint_id'] . "' ORDER BY  img_id DESC";
    $appeal_img = $GLOBALS['db']->getAll($sql);
    if (!empty($appeal_img)) {
        foreach ($appeal_img as $k => $v) {
            $appeal_img[$k]['img_file'] = get_image_path($v['img_id'], $v['img_file']);
        }
    }
    $complaint_info['appeal_img'] = $appeal_img;
    //获取操作人
    $complaint_info['end_handle_user'] = $GLOBALS['db']->getOne("SELECT user_name FROM" . $GLOBALS["ecs"]->table('admin_user') . "WHERE user_id = '" . $complaint_info['end_admin_id'] . "'");
    $complaint_info['handle_user'] = $GLOBALS['db']->getOne("SELECT user_name FROM" . $GLOBALS["ecs"]->table('admin_user') . "WHERE user_id = '" . $complaint_info['admin_id'] . "'");

    $complaint_info['add_time'] = local_date('Y-m-d H:i:s', $complaint_info['add_time']);
    $complaint_info['appeal_time'] = local_date('Y-m-d H:i:s', $complaint_info['appeal_time']);
    $complaint_info['end_handle_time'] = local_date('Y-m-d H:i:s', $complaint_info['end_handle_time']);
    $complaint_info['complaint_handle_time'] = local_date('Y-m-d H:i:s', $complaint_info['complaint_handle_time']);

    return $complaint_info;
}

//获取谈话
//$type查看聊天人类型   0平台，1商家，2会员
function checkTalkView($complaint_id = 0, $type = 'admin')
{
    $sql = "SELECT talk_id,talk_member_name,talk_member_type,talk_content,talk_state,talk_time,view_state FROM" . $GLOBALS['ecs']->table('complaint_talk') . "WHERE complaint_id='$complaint_id' ORDER BY talk_time DESC";
    $talk_list = $GLOBALS['db']->getAll($sql);
    foreach ($talk_list as $k => $v) {
        $talk_list[$k]['talk_time'] = local_date('Y-m-d H:i:s', $v['talk_time']);
        if ($v['view_state']) {
            $view_state = explode(',', $v['view_state']);
            if (!in_array($type, $view_state)) {
                $view_state_new = $v['view_state'] . "," . $type;
                $sql = "UPDATE" . $GLOBALS['ecs']->table('complaint_talk') . " SET view_state = '$view_state_new' WHERE talk_id = '" . $v['talk_id'] . "'";
                $GLOBALS['db']->query($sql);
            }
        }
    }
    return $talk_list;
}

//删除举报相关图片
function del_complaint_img($complaint_id = 0, $table = 'complaint_img')
{
    $sql = "SELECT img_file ,img_id FROM " . $GLOBALS["ecs"]->table('complaint_img') . " WHERE complaint_id = '" . $complaint_id . "' ORDER BY  img_id DESC";
    $img_list = $GLOBALS['db']->getAll($sql);
    if (!empty($img_list)) {
        foreach ($img_list as $k => $v) {
            if ($v['img_file']) {
                $sql = "DELETE FROM " . $GLOBALS["ecs"]->table($table) . " WHERE img_id = '" . $v['img_id'] . "'";
                $GLOBALS['db']->query($sql);
                get_oss_del_file([$v['img_file']]);
                @unlink(ROOT_PATH . $v['img_file']);
            }
        }
    }
    return '';
}

//删除谈话
function del_complaint_talk($complaint_id = 0)
{
    $sql = "DELETE FROM" . $GLOBALS['ecs']->table('complaint_talk') . "WHERE complaint_id = '$complaint_id'";

    return $GLOBALS['db']->query($sql);
}


//获取投诉类型
function get_goods_report_type()
{
    $sql = "SELECT type_id , type_name , type_desc FROM " . $GLOBALS['ecs']->table("goods_report_type") . " WHERE is_show = 1";
    $report_type = $GLOBALS['db']->getAll($sql);
    return $report_type;
}

//获取投诉主题
function get_goods_report_title($type_id = 0)
{
    $where = 'WHERE 1 AND is_show = 1';
    if ($type_id > 0) {
        $where .= " AND type_id = '$type_id'";
    }
    $sql = "SELECT title_id , type_id , title_name FROM " . $GLOBALS['ecs']->table("goods_report_title") . $where;
    $report_title = $GLOBALS['db']->getAll($sql);
    if ($report_title) {
        foreach ($report_title as $k => $v) {
            if ($v['type_id'] > 0) {
                $sql = "SELECT type_name FROM " . $GLOBALS['ecs']->table("goods_report_type") . "WHERE type_id = '" . $v['type_id'] . "'";
                $report_title[$k]['type_name'] = $GLOBALS['db']->getOne($sql);
            }
        }
    }
    return $report_title;
}

/**
 *  获取举报列表
 *
 * @access  public
 * @param   int $num 列表最大数量
 * @param   int $start 列表起始位置
 * @return  array       $order_list     列表
 */
function get_goods_report_list($num = 10, $page = 1)
{
    $start = ($page - 1) * $num;
    $sql = "SELECT report_id,goods_image,goods_name,goods_id,title_id,type_id,add_time,report_state,handle_type FROM" . $GLOBALS['ecs']->table('goods_report')
        . "WHERE user_id = '" . $_SESSION['user_id'] . "' AND report_state < 3  ORDER BY add_time DESC";
    $counts = $GLOBALS['db']->getAll($sql);
    $counts = is_array($counts) ? count($counts) : 0;

    $sql = "SELECT report_id,goods_image,goods_name,goods_id,title_id,type_id,add_time,report_state,handle_type FROM" . $GLOBALS['ecs']->table('goods_report')
        . "WHERE user_id = '" . $_SESSION['user_id'] . "' AND report_state < 3  ORDER BY add_time DESC limit $start,$num";
    $row = $GLOBALS['db']->getAll($sql);
    foreach ($row as $k => $v) {
        if ($v['title_id'] > 0) {
            $sql_title = "SELECT title_name FROM " . $GLOBALS['ecs']->table("goods_report_title") . "WHERE title_id = '" . $v['title_id'] . "'";
            $row[$k]['title_name'] = $GLOBALS['db']->getOne($sql_title);
        }
        if ($v['type_id'] > 0) {
            $sql_type = "SELECT type_name FROM " . $GLOBALS['ecs']->table("goods_report_type") . "WHERE type_id = '" . $v['type_id'] . "'";
            $row[$k]['type_name'] = $GLOBALS['db']->getOne($sql_type);
        }
        if ($v['add_time'] > 0) {
            $row[$k]['add_time'] = local_date('Y-m-d H:i:s', $v['add_time']);
        }
        $row[$k]['url'] = build_uri('goods', ['gid' => $v['goods_id']], $v['goods_name']);
        $sql = "SELECT user_id FROM" . $GLOBALS['ecs']->table('goods') . "WHERE goods_id = '" . $v['goods_id'] . "' LIMIT 1";
        $basic_info = get_seller_shopinfo($GLOBALS['db']->getOne($sql));
        $row[$k]['shop_name'] = $basic_info['shop_name'];
        $row[$k]['goods_image'] = get_image_path($v['goods_image']);
        $row[$k]['goods_url'] = url('goods/index/index', ['id' => $v['goods_id']]);
        $row[$k]['order_url'] = url('user/index/goods_report', ['report_id' => $v['report_id']]);
    }
    $result = ['list' => $row, 'totalPage' => ceil($counts / $num)];
    return $result;
}

/**
 * 匹配中文字符，数字，字母，"-"，"_" 4-15位, 一个中文算两个字符 例如: "你好1" 算5个字符长度
 * @param  $string
 * @return
 */
function new_strlen($string)
{
    $reg = '/^[\x{4e00}-\x{9fa5}a-zA-Z0-9-_]+$/u'; //支持中文字符，数字，字母, "-"，"_" 的正则表达式
    if (preg_match($reg, $string)) {
        $length = (strlen($string) + mb_strlen($string, 'utf-8')) / 2;
        return $length;
    } else {
        return false;
    }
}

/**
 * 过滤
 * 商品退换货标识
 */
function get_goods_cause($goods_cause, $chargeoff_status = -1, $is_settlement = 0)
{
    $arr = [];
    $lang = L('order_return_type');
    if ($goods_cause) {
        $goods_cause = explode(',', $goods_cause);
        foreach ($goods_cause as $key => $row) {
            if ($chargeoff_status > 0 || $is_settlement == 1) {
                $in_arr = [3];
                if ($is_settlement == 1) {
                    $in_arr = [1, 3];
                }
                if (!in_array($row, $in_arr)) {
                    $arr[$key]['cause'] = $row;
                    $arr[$key]['lang'] = $lang[$row];

                    if ($key == 0) {
                        $arr[$key]['is_checked'] = 1;
                    } else {
                        $arr[$key]['is_checked'] = 0;
                    }
                }
            } else {
                $arr[$key]['cause'] = $row;
                $arr[$key]['lang'] = $lang[$row];

                if ($key == 0) {
                    $arr[$key]['is_checked'] = 1;
                } else {
                    $arr[$key]['is_checked'] = 0;
                }
            }
        }
    }
    return $arr;
}

//获取余额记录总数
function get_user_accountlog_count($user_id = 0, $account_type = '', $page = 1, $size = 10)
{

    $limit = ' limit ' . (($page - 1) * $size) . ',' . $size;
    /* 获取记录条数 */
    $sql = "SELECT COUNT(*) FROM {pre}account_log WHERE user_id = " . $user_id . " AND $account_type <> 0 ORDER BY log_id DESC ";
    $record_count = $GLOBALS['db']->getOne($sql);

    $sql = "SELECT * FROM {pre}account_log WHERE user_id = " . $user_id . " AND $account_type <> 0 ORDER BY log_id DESC" . $limit;
    $res = $GLOBALS['db']->getAll($sql);
    foreach ($res as $k => $row) {
        $row['change_time'] = local_date($GLOBALS['_CFG']['date_format'], $row['change_time']);
        $row['type'] = $row[$account_type] > 0 ? '+' : '';
        $row['short_change_desc'] = sub_str($row['change_desc'], 60);
        $temp = explode(',', $row['short_change_desc']);
        if (count($temp) == 2) {
            $row['short_change_desc_part1'] = $temp[0];
            $row['short_change_desc_part2'] = $temp[1];
        }
        $row['amount'] = $row[$account_type];
        $account_log[] = $row;
    }
    $result = ['list' => $account_log, 'totalPage' => ceil($record_count / $size)];
    return $result;
}


/**
 * 我参与的砍价活动
 */
function bargain_buy_list($user_id = 0, $size = 10, $page = 1)
{

    $arr = [];
    $sql = 'SELECT bg.id,bg.bargain_name,bg.start_time,bg.end_time,bg.target_price,bg.total_num,g.goods_id, g.goods_name, g.shop_price, g.market_price, g.goods_thumb , g.goods_img FROM ' .  $GLOBALS['ecs']->table('bargain_statistics_log') . ' AS bsl LEFT JOIN ' .  $GLOBALS['ecs']->table('bargain_goods') . ' AS bg ON bsl.bargain_id = bg.id LEFT JOIN ' .  $GLOBALS['ecs']->table('goods') . ' AS g ON bg.goods_id = g.goods_id ' .
        "WHERE bsl.user_id = $user_id order by bsl.add_time desc ";

    $buy_list = $GLOBALS['db']->getAll($sql);
    $total = is_array($buy_list) ? count($buy_list) : 0;
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    //return array('list' => array_values($crowd_buy_list), 'totalpage' => ceil($total / $size));
    foreach ($res as $key => $val) {
        $arr[$key]['goods_id'] = $val['goods_id'];
        $arr[$key]['bargain_id'] = $val['id'];
        $arr[$key]['goods_name'] = $val['goods_name'];
        $arr[$key]['bargain_name'] = $val['bargain_name'];
        $arr[$key]['shop_price'] = price_format($val['shop_price']);
        $target_price = get_bargain_target_price($val['id']);
        if($target_price){
            $arr[$key]['target_price'] = price_format($target_price);
        }else{
            $arr[$key]['target_price'] = price_format($val['target_price']);
        }
        $arr[$key]['goods_img'] = get_image_path($val['goods_img']);
        $arr[$key]['goods_thumb'] = get_image_path($val['goods_thumb']);
        $arr[$key]['total_num'] = $val['total_num'];
        $arr[$key]['url'] = url('bargain/goods/index', ['id' => $val['id']]);
    }
    return ['list' => array_values($arr), 'totalpage' => ceil($total / $size)];
}

/**
 * //获取砍价商品属性最低价格
 */
function get_bargain_target_price($bargain_id = 0)
{
    $sql = 'SELECT min(target_price) as target_price FROM ' . $GLOBALS['ecs']->table('activity_goods_attr') . " WHERE bargain_id = $bargain_id ";
    $bargain = $GLOBALS['db']->getOne($sql);
    return $bargain;
}

//定时24小时未支付订单
function timing_cancel_order() {
    $t = gmtime() - 24 * 3600;

    $where = " AND oi.order_status = 0 AND oi.pay_status =0  AND oi.shipping_status = 0 AND oi.pay_time = 0 AND oi.add_time < ".$t;
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT oi.order_id FROM " .$GLOBALS['ecs']->table('order_info') ." as oi".
        " WHERE oi.user_id = '$user_id' and oi.is_delete = 0 " .
        " and (select count(*) from " .$GLOBALS['ecs']->table('order_info'). " as oi_2 where oi_2.main_order_id = oi.order_id) = 0 " .  //主订单下有子订单时，则主订单不显示
        $where . " group by oi.order_id";
    $record_count = $GLOBALS['db']->getAll($sql);
    $order_ids = array_column($record_count, 'order_id');

    if ($order_ids) {
        foreach ($order_ids as $order_id) {
            cancel_order($order_id, $user_id);
        }
    }
}