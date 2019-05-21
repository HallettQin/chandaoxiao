<?php

/* 格式化优惠券数据(注册送、购物送除外)
 * @param $cou_data
 * @return mixed
 */
function fromat_coupons($cou_data)
{

    //当前时间;
    $time = gmtime();

    //优化数据;
    foreach ($cou_data as $k => $v) {

        //优惠券剩余量
        if (!isset($v['cou_surplus'])) {
            $cou_data[$k]['cou_surplus'] = 100;
        }

        //可使用优惠券的商品; bylu
        if (!empty($v['cou_goods'])) {
            $cou_data[$k]['cou_goods_name'] = $GLOBALS['db']->getAll("SELECT goods_id,goods_name,goods_thumb FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id IN(" . $v['cou_goods'] . ")");
        }

        //可领券的会员等级;
        if (!empty($v['cou_ok_user'])) {
            $cou_data[$k]['cou_ok_user_name'] = $GLOBALS['db']->getOne("SELECT group_concat(rank_name)  FROM " . $GLOBALS['ecs']->table('user_rank') . " WHERE rank_id IN(" . $v['cou_ok_user'] . ")");
        }

        //可使用的店铺;
        $cou_data[$k]['store_name'] = sprintf(L('use_limit'), get_shop_name($v['ru_id'], 1));


        //时间戳转时间;
        $cou_data[$k]['cou_start_time_format'] = local_date('Y/m/d', $v['cou_start_time']);
        $cou_data[$k]['cou_end_time_format'] = local_date('Y/m/d', $v['cou_end_time']);

        //判断是否已过期;
        if ($v['cou_end_time'] < $time) {
            $cou_data[$k]['is_overdue'] = 1;
        } else {
            $cou_data[$k]['is_overdue'] = 0;
        }

        //优惠券种类;
        $cou_data[$k]['cou_type_name'] = $v['cou_type'] == 3 ? L('vouchers_all') : ($v['cou_type'] == 4 ? L('vouchers_user') : ($v['cou_type'] == 5 ? L('vouchers_shipping') : L('unknown')));

        //是否已经领取过了
        if ($_SESSION['user_id']) {
            $r = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('coupons_user') . " WHERE cou_id='" . $v['cou_id'] . "' AND user_id ='" . $_SESSION['user_id'] . "'");
            if($v['cou_user_num'] <= $r){
                $cou_data[$k]['cou_is_receive'] = 1;
            }else{
                $cou_data[$k]['cou_is_receive'] = 0;
            }
        }
    }

    return $cou_data;
}

/**
 * 优惠券列表（分页） 全场券、会员券、免邮券
 * @param int $num
 * @param int $page
 * @param int $status
 * @return
 */
function get_coupons_list($num = 10, $page = 1, $status = 0)
{
    $time = gmtime();
    $where = '1';
    if ($status == 0) {
        $where .= " AND c.cou_type = 3 "; // 全场券
    } elseif ($status == 1) {
        $where .= " AND c.cou_type = 4 "; // 会员券
    }
     elseif ($status == 2) {
        $where .= " AND c.cou_type = 5 "; // 免邮券
    }

    //优惠券总数;
    $sql = "SELECT COUNT(c.cou_id) FROM ".$GLOBALS['ecs']->table('coupons')." c WHERE c.review_status = 3 AND c.cou_type NOT IN(1,2) AND c.cou_end_time > $time AND $time > c.cou_start_time AND " . $where;
    $total = $GLOBALS['db']->getOne($sql);

    $start = ($page - 1) * $num;
    //取出所有优惠券(注册送、购物送除外)
    $sql = "SELECT c.*,cu.user_id,cu.is_use FROM ".$GLOBALS['ecs']->table('coupons')." c LEFT JOIN ".$GLOBALS['ecs']->table('coupons_user')." cu ON c.cou_id=cu.cou_id WHERE c.review_status = 3 AND c.cou_type  NOT IN(1,2) AND c.cou_end_time > $time AND $where GROUP BY c.cou_id  ORDER BY c.cou_id DESC limit ".$start." , ".$num."";
    $cou_data = $GLOBALS['db']->getAll($sql);

    foreach ($cou_data as $k => $v) {
        //可使用的店铺;
        if ($v['ru_id']) {
            $cou_data[$k]['store_name'] = sprintf(L('use_limit'), get_shop_name($v['ru_id'], 1));
        } else {
            $cou_data[$k]['store_name'] = L('platform_use');
        }
        //判断是否已过期;
        if ($v['cou_end_time'] < $time) {
            $cou_data[$k]['is_overdue'] = 1;
        } else {
            $cou_data[$k]['is_overdue'] = 0;
        }
        //优惠券种类;
        $cou_data[$k]['cou_type_name'] = $v['cou_type'] == 3 ? L('vouchers_all') : ($v['cou_type'] == 4 ? L('vouchers_user') : ($v['cou_type'] == 5 ? L('vouchers_shipping') : L('unknown')));

        if ($_SESSION['user_id'] > 0) {
            $is_use = dao('coupons_user')->where(['cou_id' => $v['cou_id'], 'user_id' => $_SESSION['user_id']])->getField('is_use');
            $cou_data[$k]['is_use'] = empty($is_use) ? 0 : $is_use; //好券集市(用户登入了的话,重新获取用户优惠券的使用情况)
        }
        $cou_data[$k]['begintime'] = local_date("Y/m/d", $v['cou_start_time']);
        $cou_data[$k]['endtime'] = local_date("Y/m/d", $v['cou_end_time']);
        $cou_data[$k]['img'] = "images/coupons_default.png";

        //是否已经领取过了
        if ($_SESSION['user_id']) {
            $r = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('coupons_user') . " WHERE cou_id='" . $v['cou_id'] . "' AND user_id ='" . $_SESSION['user_id'] . "'");
            if($v['cou_user_num'] <= $r){
                $cou_data[$k]['cou_is_receive'] = 1;
            }else{
                $cou_data[$k]['cou_is_receive'] = 0;
            }
        }
    }

    return ['tab' => $cou_data, 'totalpage' => ceil($total / $num)];
}


/**
 * 优惠券列表（分页） 购物券
 * @param int $num
 * @param int $page
 * @return
 */
function get_coupons_goods_list($num = 10, $page = 1)
{
    $time = gmtime();

    // 购物优惠券总数;
    $sql = "SELECT COUNT(c.cou_id) FROM ".$GLOBALS['ecs']->table('coupons')." c WHERE c.review_status = 3 AND c.cou_type = 2 AND c.cou_end_time > $time AND $time > c.cou_start_time" ;

    $total = $GLOBALS['db']->getOne($sql);

    $start = ($page - 1) * $num;
    //取出所有购物优惠券
    $sql = "SELECT * FROM ".$GLOBALS['ecs']->table('coupons')." c  WHERE c.review_status = 3 AND c.cou_type = 2 AND c.cou_end_time > $time  GROUP BY c.cou_id  ORDER BY c.cou_id DESC limit ".$start." , ".$num."";
    $cou_goods = $GLOBALS['db']->getAll($sql);

    foreach ($cou_goods as $k => $v) {
        //可使用的店铺;
        if ($v['ru_id']) {
            $cou_goods[$k]['store_name'] = sprintf(L('use_limit'), get_shop_name($v['ru_id'], 1));
        } else {
            $cou_goods[$k]['store_name'] = L('platform_use');
        }
        //判断是否已过期;
        if ($v['cou_end_time'] < $time) {
            $cou_goods[$k]['is_overdue'] = 1;
        } else {
            $cou_goods[$k]['is_overdue'] = 0;
        }
         //商品图片(没有指定商品时为默认图片)
        if ($v['cou_ok_goods']) {
            $cou_goods[$k]['cou_ok_goods_name'] = $db->getAll("SELECT goods_id,goods_name,goods_thumb FROM ".$ecs->table('goods')." WHERE goods_id IN(".$v['cou_ok_goods'].")");
        } else {
            $cou_goods[$k]['cou_ok_goods_name'][0]['goods_thumb'] = "images/coupons_default.png";
        }

        $cou_goods[$k]['begintime'] = local_date("Y/m/d", $v['cou_start_time']);
        $cou_goods[$k]['endtime'] = local_date("Y/m/d", $v['cou_end_time']);

        //是否已经领取过了
        if ($_SESSION['user_id']) {
            $r = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('coupons_user') . " WHERE cou_id='" . $v['cou_id'] . "' AND user_id ='" . $_SESSION['user_id'] . "'");
            if($v['cou_user_num'] <= $r){
                $cou_goods[$k]['cou_is_receive'] = 1;
            }else{
                $cou_goods[$k]['cou_is_receive'] = 0;
            }
        }
    }

    return ['tab' => $cou_goods, 'totalpage' => ceil($total / $num)];
}