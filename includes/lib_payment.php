<?php

/**
 * ECSHOP 支付接口函数库
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Hallett
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

/**
 * 取得返回信息地址
 * @param   string  $code   支付方式代码
 */
function return_url($code) {
    $url = $GLOBALS['ecs']->url();

    $self = explode("/", substr(PHP_SELF, 1));
    $count = count($self);
    if ($count > 1) {
        $real_path = $self[$count - 2];
        if ($real_path == SELLER_PATH) {
            $str_len = -(str_len(SELLER_PATH) + 1);
            $url = substr($GLOBALS['ecs']->url(), 0, $str_len);
        }
    }

    return $url . 'respond.php?code=' . $code;
}

/**
 * 取得异步通知返回信息地址
 * @param   string  $code   支付方式代码
 */
function notify_url($code) {
    $url = $GLOBALS['ecs']->url();

    $self = explode("/", substr(PHP_SELF, 1));
    $count = count($self);
    if ($count > 1) {
        $real_path = $self[$count - 2];
        if ($real_path == SELLER_PATH) {
            $str_len = -(str_len(SELLER_PATH) + 1);
            $url = substr($GLOBALS['ecs']->url(), 0, $str_len);
        }
    }

    return $url . 'api/notify/' . $code . '.php';
}

/**
 *  取得某支付方式信息
 *  @param  string  $field   支付方式代码/支付方式ID
 */
function get_payment($field, $type = 0)
{
    if($type == 1){
        $where = " AND pay_id = '$field'";
    }else{
        $where = " AND pay_code = '$field'";
    }

    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('payment').
           " WHERE enabled = 1 $where";
    $payment = $GLOBALS['db']->getRow($sql);

    if ($payment)
    {
        $config_list = unserialize($payment['pay_config']);

        foreach ($config_list AS $config)
        {
            $payment[$config['name']] = $config['value'];
        }
    }

    return $payment;
}

/**
 *  通过订单sn取得订单ID
 *  @param  string  $order_sn   订单sn
 *  @param  blob    $voucher    是否为会员充值
 */
function get_order_id_by_sn($order_sn, $voucher = 'false')
{
    if ($voucher == 'true')
    {
        if(is_numeric($order_sn))
        {
              return $GLOBALS['db']->getOne("SELECT log_id FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE order_id=" . $order_sn . ' AND order_type=1');
        }
        else
        {
            return "";
        }
    }
    else
    {
        if(is_numeric($order_sn))
        {
            $sql = 'SELECT order_id FROM ' . $GLOBALS['ecs']->table('order_info'). " WHERE order_sn = '$order_sn'";
            $order_id = $GLOBALS['db']->getOne($sql);
        }
        if (!empty($order_id))
        {
            $pay_log_id = $GLOBALS['db']->getOne("SELECT log_id FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE order_id='" . $order_id . "'");
            return $pay_log_id;
        }
        else
        {
            return "";
        }
    }
}

/**
 *  通过订单ID取得订单商品名称
 *  @param  string  $order_id   订单ID
 */
function get_goods_name_by_id($order_id)
{
    $sql = 'SELECT goods_name FROM ' . $GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order_id'";
    $goods_name = $GLOBALS['db']->getCol($sql);
    return implode(',', $goods_name);
}

/**
 * 检查支付的金额是否与订单相符
 *
 * @access  public
 * @param   string   $log_id      支付编号
 * @param   float    $money       支付接口返回的金额
 * @return  true
 */
function check_money($log_id, $money)
{
    if(is_numeric($log_id))
    {
        $sql = 'SELECT order_id, order_amount FROM ' . $GLOBALS['ecs']->table('pay_log') .
              " WHERE log_id = '$log_id' LIMIT 1";
        $pay = $GLOBALS['db']->getRow($sql);

        $pay['order_id'] = isset($pay['order_id']) ? $pay['order_id'] : 0;
        $pay['order_amount'] = isset($pay['order_amount']) ? $pay['order_amount'] : 0;

        $sql = 'SELECT order_id, order_amount, surplus FROM ' . $GLOBALS['ecs']->table('order_info') .
              " WHERE order_id = '" .$pay['order_id']. "' LIMIT 1";
        $order = $GLOBALS['db']->getRow($sql);
        $order['order_amount'] = isset($order['order_amount']) ? $order['order_amount'] : 0;
        $order['surplus'] = isset($order['surplus']) ? $order['surplus'] : 0;

        if($order['surplus'] > 0){
            $amount = $order['order_amount'];
        }else{
            $amount = $pay['order_amount'];
        }
    }
    else
    {
        return false;
    }

    if ($money == $amount)
    {
        return true;
    }
    else
    {
        return false;
    }
}

/**
 * 修改订单的支付状态
 *
 * @access  public
 * @param   string  $log_id     支付编号
 * @param   integer $pay_status 状态
 * @param   string  $note       备注
 * @return  void
 */
function order_paid($log_id, $pay_status = PS_PAYED, $note = '', $order_sn = '')
{
    $pay_order = array();
    if (!empty($order_sn)) {
        $sql = "SELECT ifnull(bai.is_stages, 0) is_stages, o.order_id, order_sn FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('baitiao_log') . " AS bai ON o.order_id = bai.order_id " .
                "WHERE order_sn = '$order_sn' LIMIT  1";
        $pay_order = $GLOBALS['db']->getRow($sql);
    }

    if (!empty($order_sn) && $pay_order && $pay_order['is_stages'] == 1) {
        /**
         * 白条订单
         */
        $bt_time = gmtime();

        $where_other = array(
            'id' => $log_id
        );

        $log_info = get_baitiao_pay_log_info($where_other);

        $sql = "UPDATE " . $GLOBALS['ecs']->table('baitiao_pay_log') . " SET is_pay = 1, pay_time = '$bt_time' " .
                " WHERE id = '$log_id'";
        $GLOBALS['db']->query($sql);

        $sql = "UPDATE " . $GLOBALS['ecs']->table('stages') . " SET yes_num = yes_num + 1, repay_date = '$bt_time' WHERE order_sn = '$order_sn'";
        $GLOBALS['db']->query($sql);

        $sql = "UPDATE " . $GLOBALS['ecs']->table('baitiao_log') . " SET yes_num = yes_num + 1, repayed_date = '$bt_time' WHERE log_id = '" . $log_info['log_id'] . "'";
        $GLOBALS['db']->query($sql);

        $baitiao_log_other = array(
            'log_id' => $log_info['log_id']
        );
        $baitiao_log_info = get_baitiao_log_info($baitiao_log_other);
        if ($baitiao_log_info['stages_total'] == $baitiao_log_info['yes_num'] && $baitiao_log_info['is_repay'] == 0) {
            //已还清,更新白条状态为已还清;
            $GLOBALS['db']->query("UPDATE " . $GLOBALS['ecs']->table('baitiao_log') . " SET is_repay = 1 WHERE log_id = '" . $log_info['log_id'] . "'");
        }
    } else {

        /**
         * 普通订单
         */

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
                    $sql = 'SELECT main_order_id, order_id, user_id, order_sn, consignee, address, tel, mobile, shipping_id, pay_status, extension_code, extension_id, goods_amount, ' .
                            'shipping_fee, insure_fee, pay_fee, tax, pack_fee, card_fee, surplus, money_paid, integral, integral_money, bonus, order_amount, discount, pay_id, pay_name, shipping_status ' .
                            'FROM ' . $GLOBALS['ecs']->table('order_info') .
                            " WHERE order_id = '$pay_log[order_id]'";
                    $order = $GLOBALS['db']->getRow($sql);
                    $main_order_id = $order['main_order_id'];
                    $order_id = $order['order_id'];
                    $order_sn = $order['order_sn'];

                    $pay_fee = order_pay_fee($order['pay_id'], $pay_log['order_amount']);

                    /* 众筹状态的更改 by wu */
                    update_zc_project($order_id);

                    //预售首先支付定金--无需分单
                    if ($order['extension_code'] == 'presale') {
                        $money_paid = $order['money_paid'] + $order['order_amount'];

                        if ($order['pay_status'] == 0) {
                            /* 修改订单状态为已部分付款 */
                            $order_amount = $order['goods_amount'] + $order['shipping_fee'] + $order['insure_fee'] + $order['pay_fee'] + $order['tax'] - $order['money_paid'] - $order['order_amount'];
                            $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                                    " SET order_status = '" . OS_CONFIRMED . "', " .
                                    " confirm_time = '" . gmtime() . "', " .
                                    " pay_status = '" . PS_PAYED_PART . "', " .
                                    " pay_time = '" . gmtime() . "', " .
                                    " pay_fee = '$pay_fee', " .
                                    " money_paid = '$money_paid'," .
                                    " order_amount = '$order_amount' " .
                                    "WHERE order_id = '$order_id'";
                            $GLOBALS['db']->query($sql);

                            /* 记录订单操作记录 */
                            order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, PS_PAYED_PART, $note, $GLOBALS['_LANG']['buyer']);
                            //更新pay_log
                            update_pay_log($order_id);
                        } else {
                            $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                                    " SET pay_status = '" . PS_PAYED . "', " .
                                    " pay_time = '" . gmtime() . "', " .
                                    " pay_fee = '$pay_fee', " .
                                    " money_paid = '$money_paid'," .
                                    " order_amount = 0 " .
                                    "WHERE order_id = '$order_id'";
                            $GLOBALS['db']->query($sql);

                            /* 记录订单操作记录 */
                            order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, PS_PAYED, $note, $GLOBALS['_LANG']['buyer']);

                            //付款成功后增加预售人数
                            get_presale_num($order_id);
                        }
                    } else {
                        /* 修改订单状态为已付款 */
                        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                                " SET order_status = '" . OS_CONFIRMED . "', " .
                                " confirm_time = '" . gmtime() . "', " .
                                " pay_status = '$pay_status', " .
                                " pay_fee = '$pay_fee', " .
                                " pay_time = '" . gmtime() . "', " .
                                " money_paid = money_paid + order_amount," .
                                " order_amount = 0 " .
                                "WHERE order_id = '$order_id'";
                        $GLOBALS['db']->query($sql);

                        //付款成功创建快照
                        create_snapshot($order_id);

                        //检查/改变主订单状态
                        check_main_order_status($order_id);

                        /* 记录订单操作记录 */
                        order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, $pay_status, $note, $GLOBALS['_LANG']['buyer']);

                        $order_sale = array(
                            'order_id' => $order_id,
                            'pay_status' => $pay_status,
                            'shipping_status' => $order['shipping_status']
                        );

                        get_goods_sale($order_id, $order_sale);
                    }

                    /* 修改子订单状态为已付款 by wanganlin */
                    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE main_order_id = '$order_id'";
                    $child_num = $GLOBALS['db']->getOne($sql);

                    if ($main_order_id == 0 && $child_num > 0) {
                        $sql = 'SELECT order_id, order_sn, pay_id, order_amount ' . 'FROM ' . $GLOBALS['ecs']->table('order_info') .
                                " WHERE main_order_id = '$order_id'";
                        $order_res = $GLOBALS['db']->getAll($sql);

                        foreach ($order_res AS $row) {
                            $child_pay_fee = order_pay_fee($row['pay_id'], $row['order_amount']);

                            $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                                    " SET order_status = '" . OS_CONFIRMED . "', " .
                                    " confirm_time = '" . gmtime() . "', " .
                                    " pay_status = '$pay_status', " .
                                    " pay_time = '" . gmtime() . "', " .
                                    " pay_id = '" .$order['pay_id']. "', " . //by 修改支付方式
                                    " pay_name = '" .$order['pay_name']. "', " .
                                    " pay_fee = '$child_pay_fee', " .
                                    " money_paid = order_amount," .
                                    " order_amount = 0 " .
                                    "WHERE order_id = '" . $row['order_id'] . "'";

                            $GLOBALS['db']->query($sql);



                            /* 记录订单操作记录 */
                            order_action($row['order_sn'], OS_CONFIRMED, SS_UNSHIPPED, $pay_status, $note, $GLOBALS['_LANG']['buyer']);
                        }
                    }

                    /* jisuan by zxk  */
                    /* jisuan by zxk  */

                    /* 如果需要，发短信 */
                    $sql = "SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id'";
                    $ru_id = $GLOBALS['db']->getOne($sql, true);

                    $shop_name = get_shop_name($ru_id, 1);

                    if ($ru_id == 0) {
                        $sms_shop_mobile = $GLOBALS['_CFG']['sms_shop_mobile'];
                    } else {
                        $sql = "SELECT mobile FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = '$ru_id'";
                        $sms_shop_mobile = $GLOBALS['db']->getOne($sql, true);
                    }

                    $order_result = array();
                    if ($GLOBALS['_CFG']['sms_order_payed'] == '1' && $sms_shop_mobile != '') {
                        $order_region = get_flow_user_region($order_id);
                        //阿里大鱼短信接口参数
                        $smsParams = array(
                            'shop_name' => $shop_name,
                            'shopname' => $shop_name,
                            'order_sn' => $order_sn,
                            'ordersn' => $order_sn,
                            'consignee' => $order['consignee'],
                            'order_region' => $order_region,
                            'orderregion' => $order_region,
                            'address' => $order['address'],
                            'order_mobile' => $order['mobile'],
                            'ordermobile' => $order['mobile'],
                            'mobile_phone' => $sms_shop_mobile,
                            'mobilephone' => $sms_shop_mobile
                        );

                        if ($GLOBALS['_CFG']['sms_type'] == 0) {
                            huyi_sms($smsParams, 'sms_order_payed');
                        } elseif ($GLOBALS['_CFG']['sms_type'] >= 1) {
                            $order_result = sms_ali($smsParams, 'sms_order_payed'); //阿里大鱼短信变量传值，发送时机传值
                        }
                    }

                    //门店处理
                    $sql = 'SELECT id, store_id, order_id FROM ' . $GLOBALS['ecs']->table("store_order") . " WHERE order_id = '$order_id' LIMIT 1";
                    $stores_order = $GLOBALS['db']->getRow($sql);

                    $store_result = array();
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
                            $db->query($sql);

                            //门店短信处理
                            $sql = "SELECT id, country, province, city, district, stores_address, stores_name, stores_tel FROM " . $GLOBALS['ecs']->table('offline_store') . " WHERE id = '" . $stores_order['store_id'] . "' LIMIT 1";
                            $stores_info = $GLOBALS['db']->getRow($sql);
                            $store_address = get_area_region_info($stores_info) . $stores_info['stores_address'];
                            $user_name = isset($_SESSION['user_name']) && !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
                            //门店订单->短信接口参数
                            $store_smsParams = array(
                                'user_name' => $user_name,
                                'username' => $user_name,
                                'order_sn' => $order_sn,
                                'ordersn' => $order_sn,
                                'code' => $pick_code,
                                'store_address' => $store_address,
                                'storeaddress' => $store_address,
                                'mobile_phone' => $user_mobile_phone,
                                'mobilephone' => $user_mobile_phone
                            );
                            if ($GLOBALS['_CFG']['sms_type'] == 0) {
                                if ($stores_order['store_id'] > 0 && !empty($store_smsParams)) {
                                    huyi_sms($store_smsParams, 'store_order_code');
                                }
                            } elseif ($GLOBALS['_CFG']['sms_type'] >= 1) {
                                if ($stores_order['store_id'] > 0 && !empty($store_smsParams)) {
                                    $store_result = sms_ali($store_smsParams, 'store_order_code'); //阿里大鱼短信变量传值，发送时机传值
                                }
                            }
                        }
                    }

                    if ($GLOBALS['_CFG']['sms_type'] >= 1) {

                        $sms_send = array($store_result, $order_result);
                        $resp = $GLOBALS['ecs']->ali_yu($sms_send, 1);
                    }

                    /* 取得未发货虚拟商品 */
                    $virtual_goods = get_virtual_goods($order_id);

                    if (!empty($virtual_goods)) {

                        if ($virtual_goods) {
                            /* 虚拟卡发货 */
                            if (virtual_goods_ship($virtual_goods, $msg, $order['order_sn'], true)) {

                                if ($order['shipping_id'] == -1 || empty($order['shipping_id'])) {
                                    /* 如果没有实体商品，修改发货状态，送积分和红包 */
                                    $sql = "SELECT COUNT(*)" .
                                            " FROM " . $GLOBALS['ecs']->table('order_goods') .
                                            " WHERE order_id = '$order_id' " .
                                            " AND is_real = 1";
                                    if ($GLOBALS['db']->getOne($sql) <= 0) {
                                        /* 修改订单状态 */
                                        update_order($order_id, array('shipping_status' => SS_SHIPPED, 'shipping_time' => gmtime()));

                                        /* 记录订单操作记录 */
                                        order_action($order_sn, OS_CONFIRMED, SS_SHIPPED, $pay_status, $note, $GLOBALS['_LANG']['buyer']);

                                        /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                                        if ($order['user_id'] > 0) {

                                            /* 计算并发放积分 */
                                            $integral = integral_to_give($order);
                                            $gave_custom_points = integral_of_value($integral['custom_points']) - $order['integral']; // by kong 计算赠送积分
                                            if ($gave_custom_points < 0) {
                                                $gave_custom_points = 0;
                                            }
                                            log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($GLOBALS['_LANG']['order_gift_integral'], $order['order_sn']));

                                            /* 发放红包 */
                                            send_order_bonus($order_id);

                                            /* 发放优惠券 bylu */
                                            send_order_coupons($order_id);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } elseif ($pay_log['order_type'] == PAY_SURPLUS) {
                    /* 取得添加预付款的用户以及金额 */
                    $sql = 'SELECT id, user_id, amount, is_paid FROM ' . $GLOBALS['ecs']->table('user_account') . " WHERE `id` = '" . $pay_log['order_id'] . "' LIMIT 1";
                    $user_account = $GLOBALS['db']->getRow($sql);
                    if ($user_account) {
                        if ($user_account['is_paid'] == 0) {
                            /* 更新会员预付款的到款状态 */
                            $sql = 'UPDATE ' . $GLOBALS['ecs']->table('user_account') .
                                    " SET paid_time = '" . gmtime() . "', is_paid = 1" .
                                    " WHERE id = '" . $pay_log['order_id'] . "'";
                            $GLOBALS['db']->query($sql);

                            /* 修改会员帐户金额 */
                            include_once(ROOT_PATH . 'languages/' . $GLOBALS['_CFG']['lang'] . '/user.php');
                            log_account_change($user_account['user_id'], $user_account['amount'], 0, 0, 0, $GLOBALS['_LANG']['surplus_type_0'], ACT_SAVING);
                        }
                    }
                } elseif ($pay_log['order_type'] == PAY_APPLYTEMP) {
                    require_once(ROOT_PATH . 'includes/lib_visual.php');

                    //获取订单信息
                    $sql = "SELECT ru_id,temp_id,temp_code,apply_sn FROM" . $GLOBALS['ecs']->table("seller_template_apply") . "WHERE apply_id = '" . $pay_log['order_id'] . "'";
                    $seller_template_apply = $GLOBALS['db']->getRow($sql);

                    //导入已付款的模板
                    $new_suffix = get_new_dirName($seller_template_apply['ru_id']); //获取新的模板
                    Import_temp($seller_template_apply['temp_code'], $new_suffix, $seller_template_apply['ru_id']);

                    //更新模板使用数量
                    $sql = "UPDATE" . $GLOBALS['ecs']->table('template_mall') . "SET sales_volume = sales_volume+1 WHERE temp_id = '" . $seller_template_apply['temp_id'] . "'";
                    $GLOBALS['db']->query($sql);

                    /* 修改申请的支付状态 */
                    $sql = " UPDATE " . $GLOBALS['ecs']->table('seller_template_apply') . " SET pay_status = 1 ,pay_time = '" . gmtime() . "' , apply_status = 1 WHERE apply_id= '" . $pay_log['order_id'] . "'";
                    $GLOBALS['db']->query($sql);
                } elseif ($pay_log['order_type'] == PAY_APPLYGRADE) {

                    /* 修改申请的支付状态 by kong grade */
                    $sql = " UPDATE " . $GLOBALS['ecs']->table('seller_apply_info') . " SET is_paid = 1 ,pay_time = '" . gmtime() . "' ,pay_status = 1 WHERE apply_id= '" . $pay_log['order_id'] . "'";
                    $GLOBALS['db']->query($sql);
                } elseif ($pay_log['order_type'] == PAY_TOPUP) {

                    $sql = "SELECT ru_id FROM " . $GLOBALS['ecs']->table('seller_account_log') . " WHERE log_id = '" . $pay_log['order_id'] . "' LIMIT 1";
                    $account_log = $GLOBALS['db']->getRow($sql);

                    /* 修改商家充值的支付状态 */
                    $sql = " UPDATE " . $GLOBALS['ecs']->table('seller_account_log') . " SET is_paid = 1 WHERE log_id = '" . $pay_log['order_id'] . "'";
                    $GLOBALS['db']->query($sql);

                    /* 改变商家金额 */
                    $sql = " UPDATE " . $GLOBALS['ecs']->table('seller_shopinfo') . " SET seller_money = seller_money + " . $pay_log['order_amount'] . " WHERE ru_id = '" . $account_log['ru_id'] . "'";
                    $GLOBALS['db']->query($sql);

                    $change_desc = "商家充值，操作员：商家本人线上支付";
                    $log = array(
                        'user_id' => $account_log['ru_id'],
                        'user_money' => $pay_log['order_amount'],
                        'change_time' => gmtime(),
                        'change_desc' => $change_desc,
                        'change_type' => 2
                    );
                    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('merchants_account_log'), $log, 'INSERT');
                }
            } else {
                $order_id = $pay_log['order_id'];

                $sql = 'SELECT main_order_id, order_id, user_id, order_sn, consignee, address, tel, mobile, shipping_id, pay_status, extension_code, extension_id, goods_amount, ' .
                        'shipping_fee, insure_fee, pay_fee, tax, pack_fee, card_fee, surplus, money_paid, integral, integral_money, bonus, order_amount, discount, pay_id, shipping_status ' .
                        'FROM ' . $GLOBALS['ecs']->table('order_info') .
                        " WHERE order_id = '$pay_log[order_id]'";
                $order = $GLOBALS['db']->getRow($sql);

                /* 取得未发货虚拟商品 */
                $virtual_goods = get_virtual_goods($order_id);

                if (!empty($virtual_goods)) {

                    if ($virtual_goods) {
                        /* 虚拟卡发货 */
                        if (virtual_goods_ship($virtual_goods, $msg, $order['order_sn'], true)) {
                            if ($order['shipping_id'] == -1 || empty($order['shipping_id'])) {
                                /* 如果没有实体商品，修改发货状态，送积分和红包 */
                                $sql = "SELECT COUNT(*)" .
                                        " FROM " . $GLOBALS['ecs']->table('order_goods') .
                                        " WHERE order_id = '$order_id' " .
                                        " AND is_real = 1";
                                if ($GLOBALS['db']->getOne($sql) <= 0) {
                                    /* 修改订单状态 */
                                    update_order($order_id, array('shipping_status' => SS_SHIPPED, 'shipping_time' => gmtime()));

                                    /* 记录订单操作记录 */
                                    order_action($order_sn, OS_CONFIRMED, SS_SHIPPED, $order['pay_status'], $note, $GLOBALS['_LANG']['buyer']);

                                    /* 如果订单用户不为空，计算积分，并发给用户；发红包 */
                                    if ($order['user_id'] > 0) {
                                        /* 取得用户信息 */
                                        $user = user_info($order['user_id']);

                                        /* 计算并发放积分 */
                                        $integral = integral_to_give($order);
                                        $gave_custom_points = integral_of_value($integral['custom_points']) - $order['integral']; // by kong 计算赠送积分
                                        if ($gave_custom_points < 0) {
                                            $gave_custom_points = 0;
                                        }
                                        log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($GLOBALS['_LANG']['order_gift_integral'], $order['order_sn']));

                                        /* 发放红包 */
                                        send_order_bonus($order_id);

                                        /* 发放优惠券 bylu */
                                        send_order_coupons($order_id);
                                    }
                                }
                            }
                        }
                    }
                }

                $is_number = order_virtual_card_count($order_id);
                if ($is_number == 1) {
                    $GLOBALS['_LANG']['pay_success'] .= '<br />' . $GLOBALS['_LANG']['virtual_goods_ship_fail'];
                }
            }
        }
    }
}

/**
 * 取得支付方式信息
 * @param   int     $pay_id     支付方式id
 * @return  array   支付方式信息
 */
function order_payment_info($pay_id)
{
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('payment') .
            " WHERE pay_id = '$pay_id' AND enabled = 1";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 获得订单需要支付的支付费用
 *
 * @access  public
 * @param   integer $payment_id
 * @param   float   $order_amount
 * @param   mix     $cod_fee
 * @return  float
 */
function order_pay_fee($payment_id, $order_amount, $cod_fee=null)
{
    $pay_fee = 0;
    $payment = order_payment_info($payment_id);
    $rate    = ($payment['is_cod'] && !is_null($cod_fee)) ? $cod_fee : $payment['pay_fee'];

    if (strpos($rate, '%') !== false)
    {
        /* 支付费用是一个比例 */
        $val     = floatval($rate) / 100;
        $pay_fee = $val > 0 ? $order_amount * $val /(1- $val) : 0;
    }
    else
    {
        $pay_fee = floatval($rate);
    }

    return round($pay_fee, 2);
}

?>
