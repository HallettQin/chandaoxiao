<?php

/**
 * 取得返回信息地址
 * @param   string $code 支付方式代码
 */
function return_url($code)
{
    return __URL__ . '/respond.php?code=' . $code;
}

/**
 * 取得返回信息地址
 * @param   string $code 支付方式代码
 */
function notify_url($code)
{
    return __URL__ . '/public/notify/' . $code . '.php';
}

/**
 *  取得某支付方式信息
 * @param  string $code 支付方式代码
 */
function get_payment($code)
{
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('payment') .
        " WHERE pay_code = '$code' AND enabled = '1'";
    $payment = $GLOBALS['db']->getRow($sql);

    if ($payment) {
        $config_list = unserialize($payment['pay_config']);

        foreach ($config_list as $config) {
            $payment[$config['name']] = $config['value'];
        }
    }

    return $payment;
}


/**
 *  通过订单sn取得订单ID
 * @param  string $order_sn 订单sn
 * @param  blob $voucher 是否为会员充值
 */
function get_order_id_by_sn($order_sn, $voucher = 'false')
{
    if ($voucher == 'true') {
        if (is_numeric($order_sn)) {
            return $GLOBALS['db']->getOne("SELECT log_id FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE order_id=" . $order_sn . ' AND order_type=1');
        } else {
            return "";
        }
    } else {
        if (is_numeric($order_sn)) {
            $sql = 'SELECT order_id FROM ' . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn = '$order_sn'";
            $order_id = $GLOBALS['db']->getOne($sql);
        }
        if (!empty($order_id)) {
            $pay_log_id = $GLOBALS['db']->getOne("SELECT log_id FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE order_id='" . $order_id . "'");
            return $pay_log_id;
        } else {
            return "";
        }
    }
}

/**
 *  通过订单ID取得订单商品名称
 * @param  string $order_id 订单ID
 */
function get_goods_name_by_id($order_id)
{
    $sql = 'SELECT goods_name FROM ' . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id'";
    $goods_name = $GLOBALS['db']->getCol($sql);
    return implode(',', $goods_name);
}

/**
 * 检查支付的金额是否与订单相符
 *
 * @access  public
 * @param   string $log_id 支付编号
 * @param   float $money 支付接口返回的金额
 * @return  true
 */
function check_money($log_id, $money)
{
    if (is_numeric($log_id)) {
        $sql = 'SELECT order_amount FROM ' . $GLOBALS['ecs']->table('pay_log') .
            " WHERE log_id = '$log_id'";
        $amount = $GLOBALS['db']->getOne($sql);
    } else {
        return false;
    }
    if ($money == $amount) {
        return true;
    } else {
        return false;
    }
}

/**
 * 修改订单的支付状态
 *
 * @access  public
 * @param   string $log_id 支付编号
 * @param   integer $pay_status 状态
 * @param   string $note 备注
 * @return  void
 */
function order_paid($log_id, $pay_status = PS_PAYED, $note = '', $module_name = '')
{
    // 模块订单处理接口
    $module_path = BASE_PATH . 'Modules/' . $module_name;
    if (!empty($module_name) && is_dir($module_path)) {
        $handler = 'order_paid_' . $module_name;
        if (require_cache($module_path . '/Helpers/' . $handler . '.php')) {
            return $handler($log_id, $pay_status = PS_PAYED, $note = '');
        }
    }
    /* 取得支付编号 */
    $log_id = intval($log_id);
    if ($log_id > 0) {
        /* 取得要修改的支付记录信息 */
        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('pay_log') .
            " WHERE log_id = '$log_id'";
        $pay_log = $GLOBALS['db']->getRow($sql);
        if ($pay_log && $pay_log['is_paid'] == 0) {
            /* 修改此次支付操作的状态为已付款 */
            $sql = 'UPDATE ' . $GLOBALS['ecs']->table('pay_log') .
                " SET is_paid = '1' WHERE log_id = '$log_id'";
            $GLOBALS['db']->query($sql);

            /* 根据记录类型做相应处理 */
            if ($pay_log['order_type'] == PAY_ORDER) {
                /* 取得订单信息 */
                if (is_dir(APP_TEAM_PATH)) {
                    $sql = 'SELECT main_order_id, order_id, user_id, order_sn, consignee, address, tel, mobile, shipping_id, pay_status, extension_code, extension_id, goods_amount, is_zc_order, zc_goods_id,team_id,team_parent_id,team_user_id, ' .
                        'shipping_fee, insure_fee, pay_fee, tax, pack_fee, card_fee, surplus, money_paid, integral_money, bonus, order_amount, discount '.
                        'FROM ' . $GLOBALS['ecs']->table('order_info') .
                       " WHERE order_id = '$pay_log[order_id]'";
                } else {
                    $sql = 'SELECT main_order_id, order_id, user_id, order_sn, consignee, address, tel, mobile, shipping_id, pay_status, extension_code, extension_id, goods_amount, is_zc_order, zc_goods_id, ' .
                        'shipping_fee, insure_fee, pay_fee, tax, pack_fee, card_fee, surplus, money_paid, integral_money, bonus, order_amount, discount '.
                        'FROM ' . $GLOBALS['ecs']->table('order_info') .
                       " WHERE order_id = '$pay_log[order_id]'";
                }

                $order = $GLOBALS['db']->getRow($sql);
                $main_order_id = $order['main_order_id'];
                $order_id = $order['order_id'];
                $order_sn = $order['order_sn'];

                /* 众筹状态的更改 */
                $is_zc_order = $order['is_zc_order'];
                $zc_goods_id = $order['zc_goods_id'];
                $user_id = $order['user_id'];
                if ($is_zc_order == 1 && $user_id > 0) {
                    //获取众筹商品信息
                    $sql = " select * from ".$GLOBALS['ecs']->table('zc_goods')." where id = '$zc_goods_id' ";
                    $zc_goods_info = $GLOBALS['db']->getRow($sql);
                    $pid = $zc_goods_info['pid'];
                    $goods_price = $zc_goods_info['price'];

                    //增加众筹商品支持的用户数量
                    $sql = " UPDATE ".$GLOBALS['ecs']->table('zc_goods')." SET backer_num = backer_num+1 WHERE id = '$zc_goods_id' ";
                    $GLOBALS['db']->query($sql);

                    //增加众筹商品支持的用户id
                    $sql = "SELECT backer_list FROM " . $GLOBALS['ecs']->table('zc_goods') . " WHERE id = '$zc_goods_id'";
                    $backer_list = $GLOBALS['db']->getOne($sql);
                    if (empty($backer_list)) {
                        $backer_list = $user_id;
                    } else {
                        $backer_list = $backer_list . ',' . $user_id;
                    }
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('zc_goods') . " SET backer_list='$backer_list' WHERE id = '$zc_goods_id'";
                    $GLOBALS['db']->query($sql);

                    //增加众筹项目的支持用户总数量、增加众筹项目总金额
                    $sql = "UPDATE ".$GLOBALS['ecs']->table('zc_project')." SET join_num=join_num+1, join_money=join_money+$goods_price WHERE id = '$pid'";
                    $GLOBALS['db']->query($sql);
                }
                /* 众筹状态的更改 */

                //预售首先支付定金--无需分单
                if ($order['extension_code'] == 'presale') {
                    $money_paid = $order['money_paid'] + $order['order_amount'];

                    if ($order['pay_status'] == 0) {
                        /* 修改订单状态为已部分付款 */
                        $order_amount= $order['goods_amount']
                            + $order['shipping_fee']
                            + $order['insure_fee']
                            + $order['pay_fee']
                            + $order['tax']
                            - $order['money_paid']
                            - $order['order_amount'];
                        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                            " SET order_status = '" . OS_CONFIRMED . "', " .
                            " confirm_time = '" . gmtime() . "', " .
                            " pay_status = '". PS_PAYED_PART ."', " .
                            " pay_time = '".gmtime()."', " .
                            " money_paid = '$money_paid'," .
                            " order_amount = '$order_amount' ".
                            "WHERE order_id = '$order_id'";
                        $GLOBALS['db']->query($sql);
                        /* 更新商品销量 */
                        $is_update_sale = is_update_sale($order['order_id']);
                        if (C('shop.sales_volume_time') == SALES_PAY && $is_update_sale == 0) {
                            get_goods_sale($order['order_id']);
                        }
                        /* 记录订单操作记录 */
                        order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, PS_PAYED_PART, $note, L('buyer'));
                        //更新pay_log
                        update_pay_log($order_id);
                    } else {
                        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                            " SET pay_status = '". PS_PAYED ."', " .
                            " pay_time = '".gmtime()."', " .
                            " money_paid = '$money_paid'," .
                            " order_amount = 0 ".
                            "WHERE order_id = '$order_id'";
                        $GLOBALS['db']->query($sql);

                        /* 记录订单操作记录 */
                        order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, PS_PAYED, $note, L('buyer'));
                    }
                } else {
                    //
                    /* 修改订单状态为已付款 */
                    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                        " SET order_status = '" . OS_CONFIRMED . "', " .
                        " confirm_time = '" . gmtime() . "', " .
                        " pay_status = '$pay_status', " .
                        " pay_time = '" . gmtime() . "', " .
                        " money_paid = order_amount," .
                        " order_amount = 0 " .
                        "WHERE order_id = '$order_id'";
                    $GLOBALS['db']->query($sql);
                    /* 更新商品销量 */
                    $is_update_sale = is_update_sale($order['order_id']);
                    if (C('shop.sales_volume_time') == SALES_PAY && $is_update_sale == 0) {
                        get_goods_sale($order['order_id']);
                    }
                    //付款成功创建快照
                    create_snapshot($order_id);
                    /* 记录订单操作记录 */
                    order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, $pay_status, $note, L('buyer'));
                }
                /* 修改子订单状态为已付款 by wanganlin */
                $sql = "SELECT order_id FROM ". $GLOBALS['ecs']->table('order_info') ." WHERE main_order_id = '$order_id'";
                $child_order_id_arr = $GLOBALS['db']->getAll($sql);
                if ($main_order_id == 0 && count($child_order_id_arr) > 0) {
                    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                    " SET order_status = '" . OS_CONFIRMED . "', " .
                    " confirm_time = '" . gmtime() . "', " .
                    " pay_status = '$pay_status', " .
                    " pay_time = '".gmtime()."', " .
                    " money_paid = order_amount," .
                    " order_amount = 0 ".
                    "WHERE main_order_id = '$order_id'";
                    $GLOBALS['db']->query($sql);

                    $sql = 'SELECT order_sn ' . 'FROM ' . $GLOBALS['ecs']->table('order_info') .
                        " WHERE main_order_id = '$order_id'";
                    $order_res = $GLOBALS['db']->getAll($sql);
                    foreach ($order_res as $row) {
                        /* 记录订单操作记录 */
                        order_action($row['order_sn'], OS_CONFIRMED, SS_UNSHIPPED, $pay_status, $note, L('buyer'));
                    }
                }

                /* 如果需要，发短信 */
                $sql = "SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id' LIMIT 1";
                $ru_id = $GLOBALS['db']->getOne($sql);
                if ($ru_id == 0) {
                    $sms_shop_mobile = $GLOBALS['_CFG']['sms_shop_mobile'];
                } else {
                    $sql = "SELECT mobile FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = '$ru_id'";
                    $sms_shop_mobile = $GLOBALS['db']->getOne($sql);
                }

                if ($GLOBALS['_CFG']['sms_order_payed'] == '1' && $sms_shop_mobile != '') {
                    // 组装数据
                    $message = [
                        'consignee' => $order['consignee'],
                        'order_mobile' => $order['mobile'],
                        'ordermobile' => $order['mobile'],
                    ];
                    send_sms($sms_shop_mobile, 'sms_order_payed', $message);
                }

                //门店处理
                $sql = "SELECT id, store_id, order_id FROM " . $GLOBALS['ecs']->table("store_order")." WHERE order_id = '$order_id' LIMIT 1";
                $stores_order = $GLOBALS['db']->getRow($sql);
                if ($stores_order && $stores_order['store_id'] > 0) {
                    if ($order['mobile']) {
                        $user_mobile_phone = $order['mobile'];
                    } else {
                        $sql = "SELECT mobile_phone FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id = '" . $_SESSION['user_id'] . "'";
                        $user_mobile_phone = $GLOBALS['db']->getOne($sql, true);
                    }
                    if (!empty($user_mobile_phone)) {
                        $pick_code = substr($order['order_sn'], -3) . rand(0, 9) . rand(0, 9) . rand(0, 9);
                        $sql = "UPDATE " . $GLOBALS['ecs']->table('store_order') . " SET pick_code = '$pick_code' WHERE id = '" . $stores_order['id'] . "'";
                        $GLOBALS['db']->query($sql);

                        //门店短信处理
                        // $sql = "SELECT id, country, province, city, district, stores_address, stores_name, stores_tel FROM " . $GLOBALS['ecs']->table('offline_store') . " WHERE id = '" . $stores_order['store_id'] . "' LIMIT 1";
                        // $stores_info = $GLOBALS['db']->getRow($sql);
                        // $store_address = get_area_region_info($stores_info) . $stores_info['stores_address'];
                        // $user_name = isset($_SESSION['user_name']) && !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
                        // //门店订单->短信接口参数
                        // $store_smsParams = array(
                        //     'user_name' => $user_name,
                        //     'username' => $user_name,
                        //     'order_sn' => $order_sn,
                        //     'ordersn' => $order_sn,
                        //     'code' => $pick_code,
                        //     'store_address' => $store_address,
                        //     'storeaddress' => $store_address,
                        //     'mobile_phone' => $user_mobile_phone
                        //     'mobilephone' => $user_mobile_phone
                        // );
                        // send_sms($user_mobile_phone, 'store_order_code', $store_smsParams);
                    }
                }

                /* 如果安装微信通,订单支付成功消息提醒 */
                if (is_dir(APP_WECHAT_PATH)) {
                    $pushData = [
                        'keyword1' => ['value' => $order_sn, 'color' => '#173177'],
                        'keyword2' => ['value' => '已付款', 'color' => '#173177'],
                        'keyword3' => ['value' => date('Y-m-d', gmtime()), 'color' => '#173177'],
                        'keyword4' => ['value' => $GLOBALS['_CFG']['shop_name'], 'color' => '#173177'],
                        'keyword5' => ['value' => number_format($pay_log['order_amount'], 2, '.', ''), 'color' => '#173177']
                    ];
                    $order_url = __HOST__ . url('user/order/detail', ['order_id'=> $order_id]);
                    $replace_host = [
                        'public/notify/wxpay.php',
                        'public/notify/alipay.php'
                    ];
                    $url = str_replace($replace_host, 'index.php', $order_url);
                    push_template('OPENTM204987032', $pushData, $url, $order['user_id']);
                }

                /* 拼团状态的更改 sty */
                $team_id = $order['team_id'];
                if ($team_id > 0) {
                    $sql ="select g.id,g.goods_id,g.team_price,g.limit_num, g.team_num,g.validity_time,og.goods_name from " . $GLOBALS['ecs']->table('team_log') . " as tl LEFT JOIN " . $GLOBALS['ecs']->table('team_goods') ." as g ON tl.t_id = g.id LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " as og ON tl.goods_id = og.goods_id  where tl.team_id =$team_id ";
                    $res = $GLOBALS['db']->getRow($sql);
                    //验证拼团是否成功
                    $sql = "SELECT count(order_id) as num  FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE team_id = '" . $team_id . "' AND extension_code = 'team_buy'  and pay_status = '" . PS_PAYED . "' ";
                    $team_count = $GLOBALS['db']->getRow($sql);
                    if ($team_count['num']>=$res['team_num']) {
                        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('team_log') .
                                        " SET status = '1' " .
                                        " WHERE team_id = '".$team_id."' ";
                        $GLOBALS['db']->query($sql);

                        /*拼团成功提示会员等待发货 sty*/
                        if (is_dir(APP_WECHAT_PATH)) {
                            $sql = 'SELECT order_sn,user_id FROM ' . $GLOBALS['ecs']->table('order_info') . " WHERE team_id = '" . $team_id . "' AND extension_code = 'team_buy'  and pay_status = '" . PS_PAYED . "' ";
                            $team_order = $GLOBALS['db']->query($sql);
                            foreach ($team_order as $key => $vo) {
                                $pushData = [
                                    'keyword1' => ['value' => $vo['order_sn'], 'color' => '#173177'],
                                    'keyword2' => ['value' => $res['goods_name'], 'color' => '#173177']
                                ];
                                $order_url = __HOST__ . url('team/goods/teamwait', ['team_id'=>$team_id]);
                                $replace_host = [
                                    'public/notify/wxpay.php',
                                    'public/notify/alipay.php'
                                ];
                                $url = str_replace($replace_host, 'index.php', $order_url);
                                push_template('OPENTM407456411', $pushData, $url, $vo['user_id']);
                            }
                        }
                    }
                    //统计拼团人数
                    $limit_num = $res['limit_num']+1;
                    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('team_goods') .
                                        " SET limit_num = '$limit_num' " .
                                        " WHERE goods_id = '".$res['goods_id']."' and id = '".$res['id']."' ";
                    $GLOBALS['db']->query($sql);

                    /*开团成功提醒*/
                    if (is_dir(APP_WECHAT_PATH)) {
                        if ($order['team_parent_id'] > 0) {
                            $pushData = [
                                'keyword1' => ['value' => $res['goods_name'], 'color' => '#173177'],
                                'keyword2' => ['value' => $res['team_price'].'元', 'color' => '#173177'],
                                'keyword3' => ['value' => $res['team_num'], 'color' => '#173177'],
                                'keyword4' => ['value' => '普通', 'color' => '#173177'],
                                'keyword5' => ['value' => $res['validity_time'].'小时' , 'color' => '#173177']
                            ];
                            $order_url = __HOST__ . url('team/goods/teamwait', ['team_id'=>$team_id]);
                            $replace_host = [
                                'public/notify/wxpay.php',
                                'public/notify/alipay.php'
                            ];
                            $url = str_replace($replace_host, 'index.php', $order_url);
                            push_template('OPENTM407307456', $pushData, $url, $order['user_id']);
                        } else {//参团成功通知
                            $pushData = [
                                'first' => ['value' => '您好，您已参团成功'],
                                'keyword1' => ['value' => $res['goods_name'], 'color' => '#173177'],
                                'keyword2' => ['value' => $res['team_price'].'元', 'color' => '#173177'],
                                'keyword3' => ['value' => $res['validity_time'].'小时' , 'color' => '#173177']
                            ];
                            $order_url = __HOST__ . url('team/goods/teamwait', ['team_id'=>$team_id]);
                            $replace_host = [
                                'public/notify/wxpay.php',
                                'public/notify/alipay.php'
                            ];
                            $url = str_replace($replace_host, 'index.php', $order_url);
                            push_template('OPENTM400048581', $pushData, $url, $order['user_id']);
                        }
                    }
                }
                /* 拼团状态的更改 end */

                /* 对虚拟商品的支持 */
                $virtual_goods = get_virtual_goods($order_id);
                if (!empty($virtual_goods)) {
                    $msg = '';
                    if (!virtual_goods_ship($virtual_goods, $msg, $order_sn, true)) {
                        $pay_success = L('pay_success');
                        $pay_success .= '<div style="color:red;">' . $msg . '</div>' . L('virtual_goods_ship_fail');
                    }

                    /* 如果订单没有配送方式，自动完成发货操作 */
                    if ($order['shipping_id'] == -1 || empty($order['shipping_id'])) {
                        /* 如果没有实体商品，修改发货状态，送积分和红包 */
                        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_goods') .
                            " WHERE order_id = '$order_id' AND is_real = 1";
                        if ($GLOBALS['db']->getOne($sql) <= 0) {
                            /* 修改订单状态 */
                            dao('order_info')->where(['order_id' => $order_id])
                                ->save([
                                    'order_status' => OS_SPLITED,
                                    'shipping_status' => SS_SHIPPED,
                                    'shipping_time' => gmtime()
                                ]);

                            /* 记录订单操作记录 */
                            order_action($order_sn, OS_CONFIRMED, SS_SHIPPED, $pay_status, $note, L('buyer'));

                            /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                            if ($order['user_id'] > 0) {
                                /* 计算并发放积分 */
                                $integral = integral_to_give($order);
                                $gave_custom_points = integral_of_value($integral['custom_points']) - $order['integral']; // by kong 计算赠送积分
                                if ($gave_custom_points < 0) {
                                    $gave_custom_points = 0;
                                }
                                log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf(L('order_gift_integral'), $order['order_sn']));

                                /* 发放红包 */
                                send_order_bonus($order_id);

                                /* 发放优惠券 */
                                // send_order_coupons($order_id); TODO
                            }
                        }
                    }
                }
            } elseif ($pay_log['order_type'] == PAY_SURPLUS) {
                $sql = 'SELECT `id` FROM ' . $GLOBALS['ecs']->table('user_account') . " WHERE `id` = '$pay_log[order_id]' AND `is_paid` = 1  LIMIT 1";
                $res_id = $GLOBALS['db']->getOne($sql);
                if (empty($res_id)) {
                    /* 更新会员预付款的到款状态 */
                    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('user_account') .
                        " SET paid_time = '" . gmtime() . "', is_paid = 1" .
                        " WHERE id = '$pay_log[order_id]' LIMIT 1";
                    $GLOBALS['db']->query($sql);

                    /* 取得添加预付款的用户以及金额 */
                    $sql = "SELECT user_id, amount FROM " . $GLOBALS['ecs']->table('user_account') .
                        " WHERE id = '$pay_log[order_id]'";
                    $arr = $GLOBALS['db']->getRow($sql);

                    /* 修改会员帐户金额 */
                    $_LANG = [];
                    include_once(LANG_PATH  . $GLOBALS['_CFG']['lang'] . '/user.php');
                    log_account_change($arr['user_id'], $arr['amount'], 0, 0, 0, $_LANG['surplus_type_0'], ACT_SAVING);
                }
            }
            //支付类型为购买成为分销商start
            elseif ($pay_log['order_type'] == PAY_REGISTERED) {
                $sql = "SELECT id FROM ".$GLOBALS['ecs']->table('drp_shop')." WHERE user_id='$pay_log[order_id]' AND `isbuy` = 1  LIMIT 1";
                $res_id = $GLOBALS['db']->getOne($sql);
                $sql = "SELECT value FROM ".$GLOBALS['ecs']->table('drp_config')." WHERE code='ischeck'";
                $ischeck = $GLOBALS['db']->getOne($sql);
                if ($ischeck == 1) {
                    $audit = 0;
                } else {
                    $audit = 1;
                }
                if (empty($res_id)) {
                    $time = gmtime();
                    $sql = "INSERT INTO ". $GLOBALS['ecs']->table('drp_shop') ."(user_id,create_time, isbuy, audit ,status) VALUES "."('".$pay_log[order_id]."','".$time."', '1', '".$audit."', '1')";
                    $GLOBALS['db']->query($sql);
                }
            }
            //支付类型为购买成为分销商end
        } else {
            /* 取得已发货的虚拟商品信息 */
            $post_virtual_goods = get_virtual_goods($pay_log['order_id'], true);

            /* 有已发货的虚拟商品 */
            if (!empty($post_virtual_goods)) {
                $msg = '';
                /* 检查两次刷新时间有无超过12小时 */
                $sql = 'SELECT pay_time, order_sn FROM ' . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$pay_log[order_id]'";
                $row = $GLOBALS['db']->getRow($sql);
                $intval_time = gmtime() - $row['pay_time'];
                if ($intval_time >= 0 && $intval_time < 3600 * 12) {
                    $virtual_card = [];
                    foreach ($post_virtual_goods as $code => $goods_list) {
                        /* 只处理虚拟卡 */
                        if ($code == 'virtual_card') {
                            foreach ($goods_list as $goods) {
                                if ($info = virtual_card_result($row['order_sn'], $goods)) {
                                    $virtual_card[] = ['goods_id' => $goods['goods_id'], 'goods_name' => $goods['goods_name'], 'info' => $info];
                                }
                            }

                            $GLOBALS['smarty']->assign('virtual_card', $virtual_card);
                        }
                    }
                } else {
                    $msg = '<div>' . L('please_view_order_detail') . '</div>';
                }
                $pay_success = L('pay_success');
                $pay_success .= $msg;
            }

            /* 取得未发货虚拟商品 */
            $virtual_goods = get_virtual_goods($pay_log['order_id'], false);
            if (!empty($virtual_goods)) {
                $pay_success = L('pay_success');
                $pay_success .= '<br />' . L('virtual_goods_ship_fail');
            }
        }
    }
}
