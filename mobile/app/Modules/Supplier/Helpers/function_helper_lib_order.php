<?php
/**
 *
 * @param type $c_id
 * @return type
 * by　Leah
 */
function cause_info($c_id) {

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('return_cause') . " WHERE cause_id = " . $c_id;

    $res = $GLOBALS['db']->getRow($sql);

    if ($res) {

        return $res;
    } else {

        return array();
    }
}

/**
 * 取得状态列表
 * @param   string  $type   类型：all | order | shipping | payment
 */
function get_status_list($type = 'all')
{
    global $_LANG;

    $list = array();

    if ($type == 'all' || $type == 'order')
    {
        $pre = $type == 'all' ? 'os_' : '';
        foreach ($_LANG['os'] AS $key => $value)
        {
            $list[$pre . $key] = $value;
        }
    }

    if ($type == 'all' || $type == 'shipping')
    {
        $pre = $type == 'all' ? 'ss_' : '';
        foreach ($_LANG['ss'] AS $key => $value)
        {
            $list[$pre . $key] = $value;
        }
    }

    if ($type == 'all' || $type == 'payment')
    {
        $pre = $type == 'all' ? 'ps_' : '';
        foreach ($_LANG['ps'] AS $key => $value)
        {
            $list[$pre . $key] = $value;
        }
    }
    return $list;
}

/**
 * 退回余额、积分、红包（取消、无效、退货时），把订单使用余额、积分、红包设为0
 * @param   array   $order  订单信息
 */
function return_user_surplus_integral_bonus($order)
{
    /* 处理余额、积分、红包 */
    if ($order['user_id'] > 0 && $order['surplus'] > 0)
    {
        $surplus = $order['money_paid'] < 0 ? $order['surplus'] + $order['money_paid'] : $order['surplus'];
        log_account_change($order['user_id'], $surplus, 0, 0, 0, sprintf($GLOBALS['_LANG']['return_order_surplus'], $order['order_sn']), ACT_OTHER, 1);
        $GLOBALS['db']->query("UPDATE ". $GLOBALS['ecs']->table('order_info') . " SET `order_amount` = '0' WHERE `order_id` =". $order['order_id']);
    }

    if ($order['user_id'] > 0 && $order['integral'] > 0)
    {
        log_account_change($order['user_id'], 0, 0, 0, $order['integral'], sprintf($GLOBALS['_LANG']['return_order_integral'], $order['order_sn']), ACT_OTHER, 1);
    }

    if ($order['bonus_id'] > 0)
    {
        unuse_bonus($order['bonus_id']);
    }

    /* 修改订单 */
    $arr = array(
        'bonus_id'  => 0,
        'bonus'     => 0,
        'integral'  => 0,
        'integral_money'    => 0,
        'surplus'   => 0
    );
    update_order($order['order_id'], $arr);
}

/**
 * 更新订单总金额
 * @param   int     $order_id   订单id
 * @return  bool
 */
function update_order_amount($order_id)
{
    include_once(ROOT_PATH . 'includes/lib_order.php');
    //更新订单总金额
    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') .
        " SET order_amount = " . order_due_field() .
        " WHERE order_id = '$order_id' LIMIT 1";

    return $GLOBALS['db']->query($sql);
}

/**
 * 返回某个订单可执行的操作列表，包括权限判断
 * @param   array   $order      订单信息 order_status, shipping_status, pay_status
 * @param   bool    $is_cod     支付方式是否货到付款
 * @return  array   可执行的操作  confirm, pay, unpay, prepare, ship, unship, receive, cancel, invalid, return, drop
 * 格式 array('confirm' => true, 'pay' => true)
 */
function operable_list($order)
{
    /* 取得订单状态、发货状态、付款状态 */
    $os = $order['order_status'];
    $ss = $order['shipping_status'];
    $ps = $order['pay_status'];

    /* 佣金账单状态 0 未出账 1 出账 2 结账 */
    $chargeoff_status = $order['chargeoff_status'];

    /* 取得订单操作权限 */
    $actions = $_SESSION['seller_action_list'];
    if ($actions == 'all')
    {
        $priv_list  = array('os' => true, 'ss' => true, 'ps' => true, 'edit' => true);
    }
    else
    {
        $actions    = ',' . $actions . ',';
        $priv_list  = array(
            'os'    => strpos($actions, ',order_os_edit,') !== false,
            'ss'    => strpos($actions, ',order_ss_edit,') !== false,
            'ps'    => strpos($actions, ',order_ps_edit,') !== false,
            'edit'  => strpos($actions, ',order_edit,') !== false
        );
    }

    /* 取得订单支付方式是否货到付款 */
    $payment = payment_info($order['pay_id']);
    $is_cod  = $payment['is_cod'] == 1;

    /* 根据状态返回可执行操作 */
    $list = array();
    if (OS_UNCONFIRMED == $os)
    {
        /* 状态：未确认 => 未付款、未发货 */
        if ($priv_list['os'])
        {
            $list['confirm']    = true; // 确认
            $list['invalid']    = true; // 无效
            $list['cancel']     = true; // 取消
            if ($is_cod)
            {
                /* 货到付款 */
                if ($priv_list['ss'])
                {
                    $list['prepare'] = true; // 配货
                    $list['split'] = true; // 分单
                }
            }
            else
            {
                /* 不是货到付款 */
                if ($priv_list['ps'])
                {
                    $list['pay'] = true;  // 付款
                }
            }
        }
    }
    elseif(OS_RETURNED_PART == $os || $chargeoff_status > 0){

        /* 状态：未付款 */
        if ($priv_list['ps'] < 2) {
            $list['pay'] = true; // 付款
        }

        if ($ss != SS_RECEIVED) {
            /* 状态：部分退货 */
            $list['receive'] = true; // 收货确认
        }
    }
    elseif (OS_CONFIRMED == $os || OS_SPLITED == $os || OS_SPLITING_PART == $os)
    {
        /* 状态：已确认 */
        if (PS_UNPAYED == $ps || PS_PAYED_PART == $ps)
        {
            /* 状态：已确认、未付款 */
            if (SS_UNSHIPPED == $ss || SS_PREPARING == $ss)
            {
                /* 状态：已确认、未付款、未发货（或配货中） */
                if ($priv_list['os'])
                {
                    $list['cancel'] = true; // 取消
                    $list['invalid'] = true; // 无效
                }
                if ($is_cod)
                {
                    /* 货到付款 */
                    if ($priv_list['ss'])
                    {
                        if (SS_UNSHIPPED == $ss)
                        {
                            $list['prepare'] = true; // 配货
                        }
                        $list['split'] = true; // 分单
                    }
                }
                else
                {
                    /* 不是货到付款 */
                    if ($priv_list['ps'])
                    {
                        $list['pay'] = true; // 付款
                    }
                }
            }
            /* 状态：已确认、未付款、发货中 */
            elseif (SS_SHIPPED_ING == $ss || SS_SHIPPED_PART == $ss)
            {
                // 部分分单
                if (OS_SPLITING_PART == $os)
                {
                    $list['split'] = true; // 分单
                }
                $list['to_delivery'] = true; // 去发货
            }
            else
            {
                /* 状态：已确认、未付款、已发货或已收货 => 货到付款 */
                if ($priv_list['ps'])
                {
                    $list['pay'] = true; // 付款
                }
                if ($priv_list['ss'])
                {
                    if (SS_SHIPPED == $ss)
                    {
                        $list['receive'] = true; // 收货确认
                    }
                    $list['unship'] = true; // 设为未发货
                    if ($priv_list['os'])
                    {
                        $list['return'] = true; // 退货
                    }
                }
            }
        }
        else
        {
            /* 状态：已确认、已付款和付款中 */
            if (SS_UNSHIPPED == $ss || SS_PREPARING == $ss)
            {
                /* 状态：已确认、已付款和付款中、未发货（配货中） => 不是货到付款 */
                if ($priv_list['ss'])
                {
                    if (SS_UNSHIPPED == $ss)
                    {
                        $list['prepare'] = true; // 配货
                    }
                    $list['split'] = true; // 分单
                }
                if ($priv_list['ps'])
                {
                    $list['unpay'] = true; // 设为未付款
                    if ($priv_list['os'])
                    {
                        //$list['cancel'] = true; // 取消  暂时注释 liu
                    }
                }
            }
            /* 状态：已确认、未付款、发货中 */
            elseif (SS_SHIPPED_ING == $ss || SS_SHIPPED_PART == $ss)
            {
                // 部分分单
                if (OS_SPLITING_PART == $os)
                {
                    $list['split'] = true; // 分单
                }
                $list['to_delivery'] = true; // 去发货
            }
            else
            {
                /* 状态：已确认、已付款和付款中、已发货或已收货 */
                if ($priv_list['ss'])
                {
                    if (SS_SHIPPED == $ss)
                    {
                        $list['receive'] = true; // 收货确认
                    }
                    if (!$is_cod)
                    {
                        $list['unship'] = true; // 设为未发货
                    }
                }
                if ($priv_list['ps'] && $is_cod)
                {
                    $list['unpay']  = true; // 设为未付款
                }
                if ($priv_list['os'] && $priv_list['ss'] && $priv_list['ps'])
                {
                    $list['return'] = true; // 退货（包括退款）
                }
            }
        }
    }
    elseif (OS_CANCELED == $os)
    {
        /* 状态：取消 */
        if ($priv_list['os'])
        {
            // $list['confirm'] = true; 暂时注释 liu
        }
        if ($priv_list['edit'])
        {
            $list['remove'] = true;
        }
    }
    elseif (OS_INVALID == $os)
    {
        /* 状态：无效 */
        if ($priv_list['os'])
        {
            //$list['confirm'] = true; 暂时注释 liu
        }
        if ($priv_list['edit'])
        {
            $list['remove'] = true;
        }
    }
    elseif (OS_RETURNED == $os)
    {
        /* 状态：退货 */
        if ($priv_list['os'])
        {
            $list['confirm'] = true;
        }
    }

    if ((OS_CONFIRMED == $os || OS_SPLITED == $os || OS_SHIPPED_PART == $os) && PS_PAYED == $ps && (SS_UNSHIPPED == $ss || SS_SHIPPED_PART == $ss))
    {
        /* 状态：（已确认、已分单）、已付款和未发货 */
        if ($priv_list['os'] && $priv_list['ss'] && $priv_list['ps']) {
            $list['return'] = true; // 退货（包括退款）
        }
    }

    /* 修正发货操作 */
    if (!empty($list['split']))
    {
        /* 如果是团购活动且未处理成功，不能发货 */
        if ($order['extension_code'] == 'group_buy')
        {
            include_once(ROOT_PATH . 'includes/lib_goods.php');
            $group_buy = group_buy_info(intval($order['extension_id']));
            if ($group_buy['status'] != GBS_SUCCEED)
            {
                unset($list['split']);
                unset($list['to_delivery']);
            }
        }

        /* 如果部分发货 不允许 取消 订单 */
        if (order_deliveryed($order['order_id']))
        {
            $list['return'] = true; // 退货（包括退款）
            unset($list['cancel']); // 取消
        }
    }

    /* 同意申请 */
    /*
     * by Leah
     */
    $list['after_service'] = true;
    $list['receive_goods'] = true;
    $list['agree_apply'] = true;
    $list['refound'] = true;
    $list['swapped_out_single'] = true;
    $list['swapped_out'] = true;
    $list['complete'] = true;
    $list['refuse_apply'] = true;
    /*
     * by Leah
     */

    return $list;
}

/**
 * 处理编辑订单时订单金额变动
 * @param   array   $order  订单信息
 * @param   array   $msgs   提示信息
 * @param   array   $links  链接信息
 */
function handle_order_money_change($order, &$msgs, &$links)
{
    $order_id = $order['order_id'];
    if ($order['pay_status'] == PS_PAYED || $order['pay_status'] == PS_PAYING)
    {
        /* 应付款金额 */
        $money_dues = $order['order_amount'];
        if ($money_dues > 0)
        {
            /* 修改订单为未付款 */
            update_order($order_id, array('pay_status' => PS_UNPAYED, 'pay_time' => 0));
            $msgs[]     = $GLOBALS['_LANG']['amount_increase'];
            $links[]    = array('text' => $GLOBALS['_LANG']['order_info'], 'href' => 'order.php?act=info&order_id=' . $order_id);
        }
        elseif ($money_dues < 0)
        {
            $anonymous  = $order['user_id'] > 0 ? 0 : 1;
            $msgs[]     = $GLOBALS['_LANG']['amount_decrease'];
            $links[]    = array('text' => $GLOBALS['_LANG']['refund'], 'href' => 'order.php?act=process&func=load_refund&anonymous=' .
                $anonymous . '&order_id=' . $order_id . '&refund_amount=' . abs($money_dues));
        }
    }
}

/**
 *  获取订单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function order_list($page = 0)
{
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    $ruCat = '';
    $no_main_order = '';
    $noTime = gmtime();
    //ecmoban模板堂 --zhuo end

    $result = get_filter();
    if ($result === false)
    {
        /* 过滤信息 */
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $filter['order_sn'] = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
        $filter['consignee'] = empty($_REQUEST['consignee']) ? '' : trim($_REQUEST['consignee']);
        $filter['address'] = empty($_REQUEST['address']) ? '' : trim($_REQUEST['address']);
        $filter['shipped_deal'] = empty($_REQUEST['shipped_deal']) ? '' : trim($_REQUEST['shipped_deal']);

        if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
            $filter['order_sn'] = json_str_iconv($filter['order_sn']);
            $filter['consignee'] = json_str_iconv($filter['consignee']);
            $filter['address'] = json_str_iconv($filter['address']);
        }

        $filter['email'] = empty($_REQUEST['email']) ? '' : trim($_REQUEST['email']);
        $filter['zipcode'] = empty($_REQUEST['zipcode']) ? '' : trim($_REQUEST['zipcode']);
        $filter['tel'] = empty($_REQUEST['tel']) ? '' : trim($_REQUEST['tel']);
        $filter['mobile'] = empty($_REQUEST['mobile']) ? 0 : trim($_REQUEST['mobile']);
        $filter['country'] = empty($_REQUEST['order_country']) ? 0 : intval($_REQUEST['order_country']);
        $filter['province'] = empty($_REQUEST['order_province']) ? 0 : intval($_REQUEST['order_province']);
        $filter['city'] = empty($_REQUEST['order_city']) ? 0 : intval($_REQUEST['order_city']);
        $filter['district'] = empty($_REQUEST['order_district']) ? 0 : intval($_REQUEST['order_district']);
        $filter['street'] = empty($_REQUEST['order_street']) ? 0 : intval($_REQUEST['order_street']);
        $filter['shipping_id'] = empty($_REQUEST['shipping_id']) ? 0 : intval($_REQUEST['shipping_id']);
        $filter['pay_id'] = empty($_REQUEST['pay_id']) ? 0 : intval($_REQUEST['pay_id']);
        $filter['order_status'] = isset($_REQUEST['order_status']) ? intval($_REQUEST['order_status']) : -1;
        $filter['shipping_status'] = isset($_REQUEST['shipping_status']) ? intval($_REQUEST['shipping_status']) : -1;
        $filter['pay_status'] = isset($_REQUEST['pay_status']) ? intval($_REQUEST['pay_status']) : -1;
        $filter['user_id'] = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
        $filter['user_name'] = empty($_REQUEST['user_name']) ? '' : trim($_REQUEST['user_name']);
        $filter['composite_status'] = isset($_REQUEST['composite_status']) ? intval($_REQUEST['composite_status']) : -1;
        $filter['group_buy_id'] = isset($_REQUEST['group_buy_id']) ? intval($_REQUEST['group_buy_id']) : 0;
        $filter['presale_id'] = isset($_REQUEST['presale_id']) ? intval($_REQUEST['presale_id']) : 0; // 预售id
        $filter['store_id'] = isset($_REQUEST['store_id']) ? intval($_REQUEST['store_id']) : 0; // 门店id
        $filter['order_cat'] = isset($_REQUEST['order_cat']) ? trim($_REQUEST['order_cat']) : '';

        $filter['source'] = empty($_REQUEST['source']) ? '' : trim($_REQUEST['source']); //来源起始页

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'add_time' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $filter['start_time'] = empty($_REQUEST['start_time']) ? '' : (strpos($_REQUEST['start_time'], '-') > 0 ?  local_strtotime($_REQUEST['start_time']) : $_REQUEST['start_time']);
        $filter['end_time'] = empty($_REQUEST['end_time']) ? '' : (strpos($_REQUEST['end_time'], '-') > 0 ?  local_strtotime($_REQUEST['end_time']) : $_REQUEST['end_time']);

        //确认收货时间 bylu:
        $filter['start_take_time'] = empty($_REQUEST['start_take_time']) ? '' : (strpos($_REQUEST['start_take_time'], '-') > 0 ?  local_strtotime($_REQUEST['start_take_time']) : $_REQUEST['start_take_time']);
        $filter['end_take_time'] = empty($_REQUEST['end_take_time']) ? '' : (strpos($_REQUEST['end_take_time'], '-') > 0 ?  local_strtotime($_REQUEST['end_take_time']) : $_REQUEST['end_take_time']);

        //管理员查询的权限 -- 店铺查询 start
        $filter['store_search'] = !isset($_REQUEST['store_search']) ? -1 : intval($_REQUEST['store_search']);
        $filter['merchant_id'] = isset($_REQUEST['merchant_id']) ? intval($_REQUEST['merchant_id']) : 0;
        $filter['store_keyword'] = isset($_REQUEST['store_keyword']) ? trim($_REQUEST['store_keyword']) : '';
        $filter['store_type'] = isset($_REQUEST['store_type']) ? trim($_REQUEST['store_type']) : '';

        $where = ' WHERE 1 ';

        if($filter['keywords']){
            $where  .= " AND (o.order_sn LIKE '%" .$filter['keywords']. "%'";
            $where  .= " OR (iog.goods_name LIKE '%" .$filter['keywords']. "%' OR iog.goods_sn LIKE '%" .$filter['keywords']. "%'))";
        }

        if($adminru['ru_id'] > 0){
            $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = o.order_id LIMIT 1) = '" .$adminru['ru_id']. "' ";
        }

        $no_main_order = " and (select count(*) from " . $GLOBALS['ecs']->table('order_info') . " as oi2 where oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示

        if($filter['shipped_deal']){
            $where .= " AND o.shipping_status<>" . SS_RECEIVED;
        }
        $leftJoin = '';
        $store_search = -1;
        $store_where = '';
        $store_search_where = '';
        if($filter['store_search'] > -1){
            if($adminru['ru_id'] == 0){
                if($filter['store_search'] > 0){
                    if($filter['store_type']){
                        $store_search_where = "AND msi.shopNameSuffix = '" .$filter['store_type']. "'";
                    }

                    $no_main_order = " and (SELECT count(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 where oi2.main_order_id = o.order_id) = 0 ";  //主订单下有子订单时，则主订单不显示
                    if($filter['store_search'] == 1){
                        $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' AS og' . " WHERE og.order_id = o.order_id LIMIT 1) = '" .$filter['merchant_id']. "' ";
                    }elseif($filter['store_search'] == 2){
                        $store_where .= " AND msi.rz_shopName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%'";
                    }elseif($filter['store_search'] == 3){
                        $store_where .= " AND msi.shoprz_brandName LIKE '%" . mysql_like_quote($filter['store_keyword']) . "%' " . $store_search_where;
                    }

                    if($filter['store_search'] > 1){
                        $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' AS og, ' .
                            $GLOBALS['ecs']->table('merchants_shop_information') .' AS msi ' .
                            " WHERE og.order_id = o.order_id AND msi.user_id = og.ru_id $store_where LIMIT 1) > 0 ";
                    }
                }else{
                    $store_search = 0;
                }
            }
        }

        //管理员查询的权限 -- 店铺查询 end
        //门店订单 by kong 20160727 start
        if($filter['store_id'] > 0){
            $leftJoin .= " LEFT JOIN".$GLOBALS['ecs']->table('store_order')." AS sto ON sto.order_id = o.order_id ";
            $where .= " AND sto.store_id  = '".$filter['store_id']."'";
        }
        //门店订单 by kong 20160727 end
        if ($filter['order_sn'])
        {
            $where .= " AND o.order_sn LIKE '%" . mysql_like_quote($filter['order_sn']) . "%'";
        }
        if ($filter['consignee'])
        {
            $where .= " AND o.consignee LIKE '%" . mysql_like_quote($filter['consignee']) . "%'";
        }
        if ($filter['email'])
        {
            $where .= " AND o.email LIKE '%" . mysql_like_quote($filter['email']) . "%'";
        }
        if ($filter['address'])
        {
            $where .= " AND o.address LIKE '%" . mysql_like_quote($filter['address']) . "%'";
        }
        if ($filter['zipcode'])
        {
            $where .= " AND o.zipcode LIKE '%" . mysql_like_quote($filter['zipcode']) . "%'";
        }
        if ($filter['tel'])
        {
            $where .= " AND o.tel LIKE '%" . mysql_like_quote($filter['tel']) . "%'";
        }
        if ($filter['mobile'])
        {
            $where .= " AND o.mobile LIKE '%" .mysql_like_quote($filter['mobile']) . "%'";
        }
        if ($filter['country'])
        {
            $where .= " AND o.country = '$filter[country]'";
        }
        if ($filter['province'])
        {
            $where .= " AND o.province = '$filter[province]'";
        }
        if ($filter['city'])
        {
            $where .= " AND o.city = '$filter[city]'";
        }
        if ($filter['district'])
        {
            $where .= " AND o.district = '$filter[district]'";
        }
        if ($filter['street'])
        {
            $where .= " AND o.street = '$filter[street]'";
        }
        if ($filter['shipping_id'])
        {
            $where .= " AND o.shipping_id  = '$filter[shipping_id]'";
        }
        if ($filter['pay_id'])
        {
            $where .= " AND o.pay_id  = '$filter[pay_id]'";
        }
        if ($filter['order_status'] != -1)
        {
            $where .= " AND o.order_status  = '$filter[order_status]'";
        }
        if ($filter['shipping_status'] != -1)
        {
            $where .= " AND o.shipping_status = '$filter[shipping_status]'";
        }
        if ($filter['pay_status'] != -1)
        {
            $where .= " AND o.pay_status = '$filter[pay_status]'";
        }
        if ($filter['user_id'])
        {
            $where .= " AND o.user_id = '$filter[user_id]'";
        }
        if ($filter['user_name'])
        {
            $where .= " AND (SELECT u.user_id FROM " .$GLOBALS['ecs']->table('users'). " AS u WHERE u.user_name LIKE '%" . mysql_like_quote($filter['user_name']) . "%' LIMIT 1) = o.user_id";
        }
        if ($filter['start_time'])
        {
            $where .= " AND o.add_time >= '$filter[start_time]'";
        }
        if ($filter['end_time'])
        {
            $where .= " AND o.add_time <= '$filter[end_time]'";
        }

        $alias = "o.";
        //综合状态
        switch ($filter['composite_status']) {
            case CS_AWAIT_PAY :
                $where .= order_query_sql('await_pay', $alias);
                break;

            case CS_AWAIT_SHIP :
                $where .= order_query_sql('await_ship', $alias);
                break;

            case CS_FINISHED :
                $where .= order_query_sql('finished', $alias);
                break;
            //确认收货 bylu;
            case CS_CONFIRM_TAKE :
                $where .= order_query_sql('confirm_take', $alias);
                break;

            case PS_PAYING :
                if ($filter['composite_status'] != -1) {
                    $where .= " AND o.pay_status = '$filter[composite_status]' ";
                }
                break;
            case OS_SHIPPED_PART :
                if ($filter['composite_status'] != -1) {
                    $where .= " AND o.shipping_status  = '$filter[composite_status]'-2 ";
                }
                break;
            default:
                if ($filter['composite_status'] != -1) {
                    $where .= " AND o.order_status = '$filter[composite_status]' ";
                }
        }

        /* 团购订单 */
        if ($filter['group_buy_id'])
        {
            $where .= " AND o.extension_code = 'group_buy' AND o.extension_id = '$filter[group_buy_id]' ";
        }
        /* 预售订单 */
        if ($filter['presale_id'])
        {
            $where .= " AND o.extension_code = 'presale' AND o.extension_id = '$filter[presale_id]' ";
        }

        /* 如果管理员属于某个办事处，只列出这个办事处管辖的订单 */
        $sql = "SELECT agency_id FROM " . $GLOBALS['ecs']->table('admin_user') . " WHERE user_id = '$_SESSION[seller_id]' AND action_list <> 'all'";
        $agency_id = $GLOBALS['db']->getOne($sql);

        if ($agency_id > 0)
        {
            $where .= " AND o.agency_id = '$agency_id' ";
        }

        if ($filter['order_cat']) {
            switch ($filter['order_cat']) {
                case 'stages':
                    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('baitiao_log') . " AS b ON b.order_id = o.order_id ";
                    $where .= " AND b.order_id > 0 ";
                    break;
                case 'zc':
                    $where .= " AND o.is_zc_order = 1 ";
                    break;
                case 'store':
                    $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('store_order') . " AS s ON s.order_id = o.order_id ";
                    $where .= " AND s.order_id > 0 ";
                    break;
                case 'other':
                    $where .= " AND length(o.extension_code) > 0 ";
                    break;
                case 'dbdd':
                    $where .= " AND o.extension_code = 'snatch' ";
                    break;
                case 'msdd':
                    $where .= " AND o.extension_code = 'seckill' ";
                    break;
                case 'tgdd':
                    $where .= " AND o.extension_code = 'group_buy' ";
                    break;
                case 'pmdd':
                    $where .= " AND o.extension_code = 'auction' ";
                    break;
                case 'jfdd':
                    $where .= " AND o.extension_code = 'exchange_goods' ";
                    break;
                case 'ysdd':
                    $where .= " AND o.extension_code = 'presale' ";
                    break;
                default:
            }
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
        if($page > 0){
            $filter['page'] = $page;
        }
        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 15;
        }

        $where_store = '';
        if(empty($filter['start_take_time']) || empty($filter['end_take_time']))
        {
            if($store_search == 0 && $adminru['ru_id'] == 0){
                $where_store = " AND (SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('order_goods') ." AS og ". " WHERE o.order_id = og.order_id AND og.ru_id = 0 LIMIT 1) > 0 ".
                    " AND (SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('order_info'). " AS oi2 WHERE oi2.main_order_id = o.order_id) = 0";
            }
        }

        if(!empty($filter['start_take_time']) || !empty($filter['end_take_time']))
        {
            $where_action = '';
            if ($filter['start_take_time']) {
                $where_action .= " AND oa.log_time >= '$filter[start_take_time]'";
            }
            if ($filter['end_take_time']) {
                $where_action .= " AND oa.log_time <= '$filter[end_take_time]'";
            }

            $where_action .= order_take_query_sql('finished', "oa.");

            $where .= " AND (SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('order_action'). " AS oa WHERE o.order_id = oa.order_id $where_action) > 0";
        }

        $groupBy = "";
        /* 记录总数 */
        if(!empty($filter['keywords']))
        {
            $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS iog ON iog.order_id = o.order_id ";

            $sql = "SELECT o.order_id FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
                $leftJoin .
                $where . $where_store . $no_main_order . " GROUP BY o.order_id";

            $record_count = count($GLOBALS['db']->getAll($sql));

            $groupBy = " GROUP BY o.order_id ";
        }
        else
        {
            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
                $leftJoin .
                $where . $where_store . $no_main_order;

            $record_count = $GLOBALS['db']->getOne($sql);
        }

        $filter['record_count']   = $record_count;
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 分页大小 */
        $filter = page_and_size($filter);


        /* 查询 */
        $sql = "SELECT ifnull(bai.is_stages,0) is_stages, o.order_id, o.main_order_id, o.order_sn, o.add_time, o.order_status, o.shipping_status, o.pay_status, o.order_amount, o.money_paid, o.is_delete," .
            "o.shipping_fee, o.insure_fee, o.pay_fee, o.surplus,o.tax, o.integral_money, o.bonus, o.discount, o.coupons," .
            "o.shipping_time, o.auto_delivery_time, o.consignee, o.address, o.email, o.tel, o.mobile, o.extension_code AS o_extension_code, " .
            "o.extension_id, o.user_id, o.referer, o.froms, o.chargeoff_status, o.pay_id, o.pay_name, o.shipping_id, o.shipping_name, " .
            "(" . order_amount_field('o.') . ") AS total_fee, (o.goods_amount - o.discount + o.tax + o.shipping_fee + o.insure_fee + o.pay_fee + o.pack_fee + o.card_fee) AS total_fee_order, o.pay_id " .
            " FROM " . $GLOBALS['ecs']->table('order_info') . " AS o " .
            " LEFT JOIN " .$GLOBALS['ecs']->table('baitiao_log'). " AS bai ON o.order_id=bai.order_id ".//这里连上白条日志表,查询当前订单是否是白条订单 bylu;
            $leftJoin .
            $where . $where_store . $no_main_order . $groupBy .
            " ORDER BY $filter[sort_by] $filter[sort_order] " .
            " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";

        foreach (array('order_sn', 'consignee', 'email', 'address', 'zipcode', 'tel', 'user_name') AS $val)
        {
            $filter[$val] = stripslashes($filter[$val]);
        }

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $GLOBALS['db']->getAll($sql);

    /* 格式话数据 */
    foreach ($row AS $key => $value)
    {

        /* 处理确认收货时间 start */
        if ($value['shipping_status'] == 2 && empty($value['confirm_take_time'])) {
            $sql = "SELECT MAX(log_time) AS log_time FROM " . $GLOBALS['ecs']->table('order_action') . " WHERE order_id = '" . $value['order_id'] . "' AND shipping_status = '" . SS_RECEIVED . "'";
            $confirm_take_time = $GLOBALS['db']->getOne($sql, true);

            $log_other = array(
                'confirm_take_time' => $confirm_take_time
            );

            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $log_other, 'UPDATE', "order_id = '" . $value['order_id'] . "'");

            $value['confirm_take_time'] = $confirm_take_time;
        }
        /* 处理确认收货时间 end */

        /* 查询更新支付状态 start */
        if (($value['order_status'] == OS_UNCONFIRMED || $value['order_status'] == OS_CONFIRMED || $value['order_status'] == OS_SPLITED) && $value['pay_status'] == PS_UNPAYED) {

            $pay_log = get_pay_log($value['order_id'], 1);
            if ($pay_log && $pay_log['is_paid'] == 0) {
                $payment = payment_info($value['pay_id']);

                $file_pay = ROOT_PATH . 'includes/modules/payment/' . $payment['pay_code'] . '.php';
                if ($payment && file_exists($file_pay)) {
                    /* 调用相应的支付方式文件 */
                    include_once($file_pay);

                    /* 取得在线支付方式的支付按钮 */
                    if (class_exists($payment['pay_code'])) {

                        $pay_obj = new $payment['pay_code'];
                        $is_callable = array($pay_obj, 'query');

                        /* 判断类对象方法是否存在 */
                        if (is_callable($is_callable)) {

                            $order_other = array(
                                'order_sn' => $value['order_sn'],
                                'log_id' => $pay_log['log_id']
                            );

                            $pay_obj->query($order_other);

                            $sql = "SELECT order_status, shipping_status, pay_status, pay_time FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $value['order_id'] . "' LIMIT 1";
                            $order_info = $GLOBALS['db']->getRow($sql);
                            if ($order_info) {
                                $value['order_status'] = $order_info['order_status'];
                                $value['shipping_status'] = $order_info['shipping_status'];
                                $value['pay_status'] = $order_info['pay_status'];
                                $value['pay_time'] = $order_info['pay_time'];
                            }
                        }
                    }
                }
            }
        }
        /* 查询更新支付状态 end */

        /* 增加账单订单信息 start */
        if (($value['order_status'] == OS_CONFIRMED || $value['order_status'] == OS_SPLITED) && $value['pay_status'] == PS_PAYED && $value['shipping_status'] == SS_RECEIVED) {

            $bill_info = array(
                'order_id' => $value['order_id']
            );

            $bill_order_info = get_bill_order($bill_info);

            if (!$bill_order_info) {

                /* 查询订单信息，检查状态 */
                $sql = "SELECT order_id, user_id, order_sn , order_status, shipping_status, pay_status, " .
                    "order_amount, goods_amount, tax, shipping_fee, insure_fee, pay_fee, pack_fee, card_fee, " .
                    "bonus, integral_money, coupons, discount, money_paid, surplus, confirm_take_time " .
                    "FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '" . $value['order_id'] . "'";

                $bill_order = $GLOBALS['db']->GetRow($sql);

                $seller_id = $GLOBALS['db']->getOne("SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $value['order_id'] . "'", true);
                $value_card = $GLOBALS['db']->getOne("SELECT use_val FROM " . $GLOBALS['ecs']->table('value_card_record') . " WHERE order_id = '" . $value['order_id'] . "'", true);

                $return_amount = get_order_return_amount($value['order_id']);

                $other = array(
                    'user_id' => $bill_order['user_id'],
                    'seller_id' => $seller_id,
                    'order_id' => $bill_order['order_id'],
                    'order_sn' => $bill_order['order_sn'],
                    'order_status' => $bill_order['order_status'],
                    'shipping_status' => SS_RECEIVED,
                    'pay_status' => $bill_order['pay_status'],
                    'order_amount' => $bill_order['order_amount'],
                    'return_amount' => $return_amount,
                    'goods_amount' => $bill_order['goods_amount'],
                    'tax' => $bill_order['tax'],
                    'shipping_fee' => $bill_order['shipping_fee'],
                    'insure_fee' => $bill_order['insure_fee'],
                    'pay_fee' => $bill_order['pay_fee'],
                    'pack_fee' => $bill_order['pack_fee'],
                    'card_fee' => $bill_order['card_fee'],
                    'bonus' => $bill_order['bonus'],
                    'integral_money' => $bill_order['integral_money'],
                    'coupons' => $bill_order['coupons'],
                    'discount' => $bill_order['discount'],
                    'value_card' => $value_card,
                    'money_paid' => $bill_order['money_paid'],
                    'surplus' => $bill_order['surplus'],
                    'confirm_take_time' => $value['confirm_take_time']
                );

                if ($seller_id) {
                    $insert_id = get_order_bill_log($other);
                    /* by zxk jisuan*/
                    //确认收货 商家结算到可用金额
                    order_confirm_change($bill_order['order_id'], $insert_id);
                    /* by zxk jisuan*/
                }
            }
        }
        /* 增加账单订单信息 end */

        //账单编号
        if ($value['chargeoff_status'] == 1 || $value['chargeoff_status'] == 2) {
            $bill = $GLOBALS['db']->getRow(" SELECT scb.id, scb.bill_sn, scb.seller_id, scb.proportion, commission_model FROM " . $GLOBALS['ecs']->table('seller_bill_order') . " AS sbo " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('seller_commission_bill') . " AS scb ON sbo.bill_id = scb.id " .
                "WHERE sbo.order_id = '" . $value['order_id'] . "' ");

            $row[$key]['bill_id'] = $bill['id'];
            $row[$key]['bill_sn'] = $bill['bill_sn'];
            $row[$key]['seller_id'] = $bill['seller_id'];
            $row[$key]['proportion'] = $bill['proportion'];
            $row[$key]['commission_model'] = $bill['commission_model'];
        }

        //取得团购活动信息
        if ($value['extension_code'] == 'group_buy') {
            $group_buy = group_buy_info($value['extension_id'], 0, "seller");
            //团购状态
            $status = group_buy_status($group_buy);
            if ($status == 0) {
                $row[$key]['cur_status'] = '未开始';
            } elseif ($status == 1) {
                $row[$key]['cur_status'] = '进行中';
            } elseif ($status == 2) {
                $row[$key]['cur_status'] = '已结束,请去团购活动页确认！';
            } elseif ($status == 3) {
                $row[$key]['cur_status'] = '团购成功';
            } else {
                $row[$key]['cur_status'] = '团购失败';
            }
        }

        //查商家ID
        $sql = "SELECT ru_id, extension_code AS iog_extension_code FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '" . $value['order_id'] . "' ";
        $order_goods = $GLOBALS['db']->getAll($sql);

        $value['ru_id'] = reset($order_goods)['ru_id'];
        if(count($order_goods) > 1){
            $iog_extension_codes = array_column($order_goods, 'iog_extension_code');
            $row[$key]['iog_extension_codes'] = array_unique($iog_extension_codes);
        }else{
            $row[$key]['iog_extension_code'] = reset($order_goods)['iog_extension_code'];
        }

        //查会员名称
        $sql = " SELECT user_name FROM ".$GLOBALS['ecs']->table('users')." WHERE user_id = '".$value['user_id']."'";
        $value['buyer'] = $GLOBALS['db']->getOne($sql, true);
        $row[$key]['buyer'] = !empty($value['buyer']) ? $value['buyer'] : $GLOBALS['_LANG']['anonymous'];

        $row[$key]['formated_order_amount'] = price_format($value['order_amount']);
        $row[$key]['formated_money_paid'] = price_format($value['money_paid']);
        $row[$key]['formated_total_fee'] = price_format($value['total_fee']);
        $row[$key]['short_order_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['add_time']);
        $row[$key]['formated_total_fee_order'] = price_format($value['total_fee_order']);
        /* 取得区域名 */
        $row[$key]['region'] = get_user_region_address($value['order_id']);

        //ecmoban模板堂 --zhuo start
        $row[$key]['user_name'] = get_shop_name($value['ru_id'], 1);

        $order_id = $value['order_id'];
        $date = array('order_id');

        $order_child = count(get_table_date('order_info', "main_order_id='$order_id'", $date, 1));
        $row[$key]['order_child'] = $order_child;

        $date = array('order_sn');
        $child_list = get_table_date('order_info', "main_order_id='$order_id'", $date, 1);
        $row[$key]['child_list'] = $child_list;
        //ecmoban模板堂 --zhuo end

        $order = array(
            'order_id' => $value['order_id'],
            'order_sn' => $value['order_sn']
        );

        $goods = get_order_goods($order);
        $row[$key]['goods_list'] = $goods['goods_list'];
        if ($value['order_status'] == OS_INVALID || $value['order_status'] == OS_CANCELED)
        {
            /* 如果该订单为无效或取消则显示删除链接 */
            $row[$key]['can_remove'] = 1;
        }
        else
        {
            $row[$key]['can_remove'] = 0;
        }
    }

    $arr = array('orders' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
}

/**
 * 取得供货商列表
 * @return array    二维数组
 */
function get_suppliers_list()
{
    $sql = 'SELECT *
            FROM ' . $GLOBALS['ecs']->table('suppliers') . '
            WHERE is_check = 1
            ORDER BY suppliers_name ASC';
    $res = $GLOBALS['db']->getAll($sql);

    if (!is_array($res))
    {
        $res = array();
    }

    return $res;
}

/**
 * 取得订单商品
 * @param   array     $order  订单数组
 * @return array
 */
function get_order_goods($order)
{
    global $ecs;
    $goods_list = array();
    $goods_attr = array();
    $sql = "SELECT o.*, g.model_inventory, g.model_attr AS model_attr, g.suppliers_id AS suppliers_id, g.goods_number AS storage, o.goods_attr, IFNULL(b.brand_name, '') AS brand_name, p.product_sn, oi.order_sn,oi.extension_code as oi_extension_code,oi.extension_id, " .
        "g.goods_thumb " .
        "FROM " . $GLOBALS['ecs']->table('order_goods') . " AS o ".
        "LEFT JOIN " . $GLOBALS['ecs']->table('products') . " AS p ON o.product_id = p.product_id " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON o.goods_id = g.goods_id " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('brand') . " AS b ON g.brand_id = b.brand_id " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . " AS oi ON oi.order_id = o.order_id " .
        "WHERE o.order_id = '$order[order_id]' ";
    $res = $GLOBALS['db']->query($sql);

    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        // 虚拟商品支持
        if ($row['is_real'] == 0)
        {
            /* 取得语言项 */
            $filename = ROOT_PATH . 'plugins/' . $row['extension_code'] . '/languages/common_' . $GLOBALS['_CFG']['lang'] . '.php';
            if (file_exists($filename))
            {
                include_once($filename);
                if (!empty($GLOBALS['_LANG'][$row['extension_code'].'_link']))
                {
                    $row['goods_name'] = $row['goods_name'] . sprintf($GLOBALS['_LANG'][$row['extension_code'].'_link'], $row['goods_id'], $order['order_sn']);
                }
            }
        }

        //ecmoban模板堂 --zhuo start
        if($row['product_id'] > 0){
            $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['ru_id'], $row['warehouse_id'], $row['area_id'], $row['model_attr']);
            $row['storage'] = $products['product_number'];
        }else{
            if($row['model_inventory'] == 1){
                $row['storage'] = get_warehouse_area_goods($row['warehouse_id'], $row['goods_id'], 'warehouse_goods');
            }elseif($row['model_inventory'] == 2){
                $row['storage'] = get_warehouse_area_goods($row['area_id'], $row['goods_id'], 'warehouse_area_goods');
            }
        }
        //ecmoban模板堂 --zhuo end

        $row['formated_subtotal']       = price_format($row['goods_price'] * $row['goods_number']);
        $row['formated_goods_price']    = price_format($row['goods_price']);

        $goods_attr[] = explode(' ', trim($row['goods_attr'])); //将商品属性拆分为一个数组

        if ($row['extension_code'] == 'package_buy')
        {
            $row['storage'] = '';
            $row['brand_name'] = '';
            $row['package_goods_list'] = get_package_goods_list($row['goods_id']);
        }

        //处理货品id
        $row['product_id'] = empty($row['product_id']) ? 0 : $row['product_id'];

        //图片显示
        $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);

        $trade_id = find_snapshot($row['order_sn'], $row['goods_id']);
        if ($trade_id) {
            $row['trade_url'] = "../trade_snapshot.php?act=trade&tradeId=" . $trade_id . "&snapshot=true";
        }

        //处理商品链接
        $row['url'] = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);

        $goods_list[] = $row;
    }

    $attr = array();
    $arr  = array();
    foreach ($goods_attr AS $index => $array_val)
    {
        foreach ($array_val AS $value)
        {
            $arr = explode(':', $value);//以 : 号将属性拆开
            $attr[$index][] =  @array('name' => $arr[0], 'value' => $arr[1]);
        }
    }

    return array('goods_list' => $goods_list, 'attr' => $attr);
}

/**
 * 取得礼包列表
 * @param   integer     $package_id  订单商品表礼包类商品id
 * @return array
 */
function get_package_goods_list($package_id)
{
    $sql = "SELECT pg.goods_id, g.goods_name, (CASE WHEN pg.product_id > 0 THEN p.product_number ELSE g.goods_number END) AS goods_number, p.goods_attr, p.product_id, pg.goods_number AS
            order_goods_number, g.goods_sn, g.is_real, p.product_sn
            FROM " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                LEFT JOIN " .$GLOBALS['ecs']->table('goods') . " AS g ON pg.goods_id = g.goods_id
                LEFT JOIN " . $GLOBALS['ecs']->table('products') . " AS p ON pg.product_id = p.product_id
            WHERE pg.package_id = '$package_id'";
    $resource = $GLOBALS['db']->query($sql);
    if (!$resource)
    {
        return array();
    }

    $row = array();

    /* 生成结果数组 取存在货品的商品id 组合商品id与货品id */
    $good_product_str = '';
    while ($_row = $GLOBALS['db']->fetch_array($resource))
    {
        if ($_row['product_id'] > 0)
        {
            /* 取存商品id */
            $good_product_str .= ',' . $_row['goods_id'];

            /* 组合商品id与货品id */
            $_row['g_p'] = $_row['goods_id'] . '_' . $_row['product_id'];
        }
        else
        {
            /* 组合商品id与货品id */
            $_row['g_p'] = $_row['goods_id'];
        }

        //生成结果数组
        $row[] = $_row;
    }
    $good_product_str = trim($good_product_str, ',');

    /* 释放空间 */
    unset($resource, $_row, $sql);

    /* 取商品属性 */
    if ($good_product_str != '')
    {
        $sql = "SELECT ga.goods_attr_id, ga.attr_value, ga.attr_price, a.attr_name
                FROM " .$GLOBALS['ecs']->table('goods_attr'). " AS ga, " .$GLOBALS['ecs']->table('attribute'). " AS a
                WHERE a.attr_id = ga.attr_id
                AND a.attr_type = 1
                AND goods_id IN ($good_product_str) ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id";
        $result_goods_attr = $GLOBALS['db']->getAll($sql);

        $_goods_attr = array();
        foreach ($result_goods_attr as $value)
        {
            $_goods_attr[$value['goods_attr_id']] = $value;
        }
    }

    /* 过滤货品 */
    $format[0] = "%s:%s[%d] <br>";
    $format[1] = "%s--[%d]";
    foreach ($row as $key => $value)
    {
        if ($value['goods_attr'] != '')
        {
            $goods_attr_array = explode('|', $value['goods_attr']);

            $goods_attr = array();
            foreach ($goods_attr_array as $_attr)
            {
                $goods_attr[] = sprintf($format[0], $_goods_attr[$_attr]['attr_name'], $_goods_attr[$_attr]['attr_value'], $_goods_attr[$_attr]['attr_price']);
            }

            $row[$key]['goods_attr_str'] = implode('', $goods_attr);
        }

        $row[$key]['goods_name'] = sprintf($format[1], $value['goods_name'], $value['order_goods_number']);
    }

    return $row;
}

/**
 * 订单单个商品或货品的已发货数量
 *
 * @param   int     $order_id       订单 id
 * @param   int     $goods_id       商品 id
 * @param   int     $product_id     货品 id
 *
 * @return  int
 */
function order_delivery_num($order_id, $goods_id, $product_id = 0)
{
    $sql = 'SELECT SUM(G.send_number) AS sums
            FROM ' . $GLOBALS['ecs']->table('delivery_goods') . ' AS G, ' . $GLOBALS['ecs']->table('delivery_order') . ' AS O
            WHERE O.delivery_id = G.delivery_id
            AND O.status = 0
            AND O.order_id = ' . $order_id . '
            AND G.extension_code <> "package_buy"
            AND G.goods_id = ' . $goods_id;

    $sql .= ($product_id > 0) ? " AND G.product_id = '$product_id'" : '';

    $sum = $GLOBALS['db']->getOne($sql);

    if (empty($sum))
    {
        $sum = 0;
    }

    return $sum;
}

/**
 * 判断订单是否已发货（含部分发货）
 * @param   int     $order_id  订单 id
 * @return  int     1，已发货；0，未发货
 */
function order_deliveryed($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    $sql = 'SELECT COUNT(delivery_id)
            FROM ' . $GLOBALS['ecs']->table('delivery_order') . '
            WHERE order_id = \''. $order_id . '\'
            AND status = 0';
    $sum = $GLOBALS['db']->getOne($sql);

    if ($sum)
    {
        $return_res = 1;
    }

    return $return_res;
}

/**
 * 更新订单商品信息
 * @param   int     $order_id       订单 id
 * @param   array   $_sended        Array(‘商品id’ => ‘此单发货数量’)
 * @param   array   $goods_list
 * @return  Bool
 */
function update_order_goods($order_id, $_sended, $goods_list = array())
{
    if (!is_array($_sended) || empty($order_id))
    {
        return false;
    }

    foreach ($_sended as $key => $value)
    {
        // 超值礼包
        if (is_array($value))
        {
            if (!is_array($goods_list))
            {
                $goods_list = array();
            }

            foreach ($goods_list as $goods)
            {
                if (($key != $goods['rec_id']) || (!isset($goods['package_goods_list']) || !is_array($goods['package_goods_list'])))
                {
                    continue;
                }

                $goods['package_goods_list'] = package_goods($goods['package_goods_list'], $goods['goods_number'], $goods['order_id'], $goods['extension_code'], $goods['goods_id']);
                $pg_is_end = true;

                foreach ($goods['package_goods_list'] as $pg_key => $pg_value)
                {
                    if ($pg_value['order_send_number'] != $pg_value['sended'])
                    {
                        $pg_is_end = false; // 此超值礼包，此商品未全部发货

                        break;
                    }
                }

                // 超值礼包商品全部发货后更新订单商品库存
                if ($pg_is_end)
                {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                            SET send_number = goods_number
                            WHERE order_id = '$order_id'
                            AND goods_id = '" . $goods['goods_id'] . "' ";

                    $GLOBALS['db']->query($sql, 'SILENT');
                }
            }
        }
        // 商品（实货）（货品）
        elseif (!is_array($value))
        {
            /* 检查是否为商品（实货）（货品） */
            foreach ($goods_list as $goods)
            {
                if ($goods['rec_id'] == $key && $goods['is_real'] == 1)
                {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') . "
                            SET send_number = send_number + $value
                            WHERE order_id = '$order_id'
                            AND rec_id = '$key' ";
                    $GLOBALS['db']->query($sql, 'SILENT');
                    break;
                }
            }
        }
    }

    return true;
}

/**
 * 更新订单虚拟商品信息
 * @param   int     $order_id       订单 id
 * @param   array   $_sended        Array(‘商品id’ => ‘此单发货数量’)
 * @param   array   $virtual_goods  虚拟商品列表
 * @return  Bool
 */
function update_order_virtual_goods($order_id, $_sended, $virtual_goods)
{
    if (!is_array($_sended) || empty($order_id))
    {
        return false;
    }
    if (empty($virtual_goods))
    {
        return true;
    }
    elseif (!is_array($virtual_goods))
    {
        return false;
    }

    foreach ($virtual_goods as $goods)
    {
        $sql = "UPDATE ".$GLOBALS['ecs']->table('order_goods'). "
                SET send_number = send_number + '" . $goods['num'] . "'
                WHERE order_id = '" . $order_id . "'
                AND goods_id = '" . $goods['goods_id'] . "' ";
        if (!$GLOBALS['db']->query($sql, 'SILENT'))
        {
            return false;
        }
    }

    return true;
}

/**
 * 订单中的商品是否已经全部发货
 * @param   int     $order_id  订单 id
 * @return  int     1，全部发货；0，未全部发货
 */
function get_order_finish($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    $sql = 'SELECT COUNT(rec_id)
            FROM ' . $GLOBALS['ecs']->table('order_goods') . '
            WHERE order_id = \'' . $order_id . '\'
            AND goods_number > send_number';

    $sum = $GLOBALS['db']->getOne($sql);
    if (empty($sum))
    {
        $return_res = 1;
    }

    return $return_res;
}

/**
 * 判断订单的发货单是否全部发货
 * @param   int     $order_id  订单 id
 * @return  int     1，全部发货；0，未全部发货；-1，部分发货；-2，完全没发货；
 */
function get_all_delivery_finish($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    /* 未全部分单 */
    if (!get_order_finish($order_id))
    {
        return $return_res;
    }
    /* 已全部分单 */
    else
    {
        // 是否全部发货
        $sql = "SELECT COUNT(delivery_id)
                FROM " . $GLOBALS['ecs']->table('delivery_order') . "
                WHERE order_id = '$order_id'
                AND status = 2 ";
        $sum = $GLOBALS['db']->getOne($sql);
        // 全部发货
        if (empty($sum))
        {
            $return_res = 1;
        }
        // 未全部发货
        else
        {
            /* 订单全部发货中时：当前发货单总数 */
            $sql = "SELECT COUNT(delivery_id)
            FROM " . $GLOBALS['ecs']->table('delivery_order') . "
            WHERE order_id = '$order_id'
            AND status <> 1 ";
            $_sum = $GLOBALS['db']->getOne($sql);
            if ($_sum == $sum)
            {
                $return_res = -2; // 完全没发货
            }
            else
            {
                $return_res = -1; // 部分发货
            }
        }
    }

    return $return_res;
}

function trim_array_walk(&$array_value)
{
    if (is_array($array_value))
    {
        array_walk($array_value, 'trim_array_walk');
    }else{
        $array_value = trim($array_value);
    }
}

function intval_array_walk(&$array_value)
{
    if (is_array($array_value))
    {
        array_walk($array_value, 'intval_array_walk');
    }else{
        $array_value = intval($array_value);
    }
}

/**
 * 删除发货单(不包括已退货的单子)
 * @param   int     $order_id  订单 id
 * @return  int     1，成功；0，失败
 */
function del_order_delivery($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    $sql = 'DELETE O, G
            FROM ' . $GLOBALS['ecs']->table('delivery_order') . ' AS O, ' . $GLOBALS['ecs']->table('delivery_goods') . ' AS G
            WHERE O.order_id = \'' . $order_id . '\'
            AND O.status = 0
            AND O.delivery_id = G.delivery_id';
    $query = $GLOBALS['db']->query($sql, 'SILENT');

    if ($query)
    {
        $return_res = 1;
    }

    return $return_res;
}

/**
 * 删除订单所有相关单子
 * @param   int     $order_id      订单 id
 * @param   int     $action_array  操作列表 Array('delivery', 'back', ......)
 * @return  int     1，成功；0，失败
 */
function del_delivery($order_id, $action_array)
{
    $return_res = 0;

    if (empty($order_id) || empty($action_array))
    {
        return $return_res;
    }

    $query_delivery = 1;
    $query_back = 1;
    if (in_array('delivery', $action_array))
    {
        $sql = 'DELETE O, G
                FROM ' . $GLOBALS['ecs']->table('delivery_order') . ' AS O, ' . $GLOBALS['ecs']->table('delivery_goods') . ' AS G
                WHERE O.order_id = \'' . $order_id . '\'
                AND O.delivery_id = G.delivery_id';
        $query_delivery = $GLOBALS['db']->query($sql, 'SILENT');
    }
    if (in_array('back', $action_array))
    {
        $sql = 'DELETE O, G
                FROM ' . $GLOBALS['ecs']->table('back_order') . ' AS O, ' . $GLOBALS['ecs']->table('back_goods') . ' AS G
                WHERE O.order_id = \'' . $order_id . '\'
                AND O.back_id = G.back_id';
        $query_back = $GLOBALS['db']->query($sql, 'SILENT');
    }

    if ($query_delivery && $query_back)
    {
        $return_res = 1;
    }

    return $return_res;
}

/**
 *  获取发货单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function delivery_list()
{
    $where = 'WHERE 1 ';
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    if($adminru['ru_id'] > 0){
        $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = do.order_id LIMIT 1) = '" .$adminru['ru_id']. "' ";
    }
    //ecmoban模板堂 --zhuo end

    $result = get_filter();
    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */

        $filter['delivery_sn'] = empty($_REQUEST['delivery_sn']) ? '' : trim($_REQUEST['delivery_sn']);
        $filter['order_sn'] = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
        $filter['order_id'] = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
        $filter['goods_id'] = empty($_REQUEST['goods_id']) ? 0 : intval($_REQUEST['goods_id']);
        if ($aiax == 1 && !empty($_REQUEST['consignee']))
        {
            $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);
        }
        $filter['consignee'] = empty($_REQUEST['consignee']) ? '' : trim($_REQUEST['consignee']);
        $filter['status'] = isset($_REQUEST['status']) ? $_REQUEST['status'] : -1;

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'do.update_time' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        if ($filter['order_sn'])
        {
            $where .= " AND do.order_sn LIKE '%" . mysql_like_quote($filter['order_sn']) . "%'";
        }
        if ($filter['goods_id'])
        {
            $where .= " AND (SELECT dg.goods_id FROM ".$GLOBALS['ecs']->table('delivery_goods')." AS dg WHERE dg.delivery_id = do.delivery_id LIMIT 1) = '" .$filter['goods_id']. "' ";
        }
        if ($filter['consignee'])
        {
            $where .= " AND do.consignee LIKE '%" . mysql_like_quote($filter['consignee']) . "%'";
        }

        if ($filter['delivery_sn'])
        {
            $where .= " AND do.delivery_sn LIKE '%" . mysql_like_quote($filter['delivery_sn']) . "%'";
        }

        /* 获取管理员信息 */
        $admin_info = admin_info();

        /* 如果管理员属于某个办事处，只列出这个办事处管辖的发货单 */
        if ($admin_info['agency_id'] > 0)
        {
            $where .= " AND do.agency_id = '" . $admin_info['agency_id'] . "' ";
        }

        /* 如果管理员属于某个供货商，只列出这个供货商的发货单 */
        if ($admin_info['suppliers_id'] > 0)
        {
            $where .= " AND do.suppliers_id = '" . $admin_info['suppliers_id'] . "' ";
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 10;
        }
        if($filter['status'] > -1){
            $where .= " AND do.status = '$filter[status]' ";
        }

        /* 记录总数 */
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('delivery_order') . " as do " . $where;
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT do.delivery_id, do.delivery_sn, do.order_sn, do.order_id, do.add_time, do.action_user, do.consignee, do.country,
                       do.province, do.city, do.district, do.tel, do.status, do.update_time, do.email, do.suppliers_id
                FROM " . $GLOBALS['ecs']->table("delivery_order") . " as do " .
            $where .
            " ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order']. "
                LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];

        $filter = $result['filter'];
    }

    /* 获取供货商列表 */
    $suppliers_list = get_suppliers_list();
    $_suppliers_list = array();
    foreach ($suppliers_list as $value)
    {
        $_suppliers_list[$value['suppliers_id']] = $value['suppliers_name'];
    }

    $row = $GLOBALS['db']->getAll($sql);

    /* 格式化数据 */
    foreach ($row AS $key => $value)
    {
        $row[$key]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['add_time']);
        $row[$key]['update_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['update_time']);
        if ($value['status'] == 1)
        {
            $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][1];
        }
        elseif ($value['status'] == 2)
        {
            $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][2];
        }
        else
        {
            $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][0];
        }
        $row[$key]['suppliers_name'] = isset($_suppliers_list[$value['suppliers_id']]) ? $_suppliers_list[$value['suppliers_id']] : '';

        $sql = "SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '" .$value['order_id']. "' LIMIT 0,1";
        $ru_id = $GLOBALS['db']->getOne($sql);
        $row[$key]['ru_name'] = get_shop_name($ru_id, 1); //ecmoban模板堂 --zhuo
    }
    $arr = array('delivery' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

/**
 *  获取退货单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function back_list()
{
    $where = 'WHERE 1 ';
    //ecmoban模板堂 --zhuo start
    $adminru = get_admin_ru_id();
    if($adminru['ru_id'] > 0){
        $where .= " AND (SELECT og.ru_id FROM " . $GLOBALS['ecs']->table('order_goods') .' as og' . " WHERE og.order_id = bo.order_id LIMIT 1) = '" .$adminru['ru_id']. "' ";
    }
    //ecmoban模板堂 --zhuo end

    //取消获取cookie信息 by wu
    //$result = get_filter();
    $result = false;

    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
        $filter['delivery_sn'] = empty($_REQUEST['delivery_sn']) ? '' : trim($_REQUEST['delivery_sn']);
        $filter['order_sn'] = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
        $filter['order_id'] = empty($_REQUEST['order_id']) ? 0 : intval($_REQUEST['order_id']);
        if ($aiax == 1 && !empty($_REQUEST['consignee']))
        {
            $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);
        }
        $filter['consignee'] = empty($_REQUEST['consignee']) ? '' : trim($_REQUEST['consignee']);

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'bo.update_time' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        if ($filter['order_sn'])
        {
            $where .= " AND bo.order_sn LIKE '%" . mysql_like_quote($filter['order_sn']) . "%'";
        }
        if ($filter['consignee'])
        {
            $where .= " AND bo.consignee LIKE '%" . mysql_like_quote($filter['consignee']) . "%'";
        }
        if ($filter['delivery_sn'])
        {
            $where .= " AND bo.delivery_sn LIKE '%" . mysql_like_quote($filter['delivery_sn']) . "%'";
        }

        /* 获取管理员信息 */
        $admin_info = admin_info();

        /* 如果管理员属于某个办事处，只列出这个办事处管辖的发货单 */
        if ($admin_info['agency_id'] > 0)
        {
            $where .= " AND bo.agency_id = '" . $admin_info['agency_id'] . "' ";
        }

        /* 如果管理员属于某个供货商，只列出这个供货商的发货单 */
        if ($admin_info['suppliers_id'] > 0)
        {
            $where .= " AND bo.suppliers_id = '" . $admin_info['suppliers_id'] . "' ";
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 15;
        }

        /* 记录总数 */
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('back_order') ." as bo " . $where;
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT bo.back_id, bo.delivery_sn, bo.order_sn, bo.order_id, bo.add_time, bo.action_user, bo.consignee, bo.country,
                       bo.province, bo.city, bo.district, bo.tel, bo.status, bo.update_time, bo.email, bo.return_time
                FROM " . $GLOBALS['ecs']->table("back_order")  ." as bo ".
            $where .
            " ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order']. "
                LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $GLOBALS['db']->getAll($sql);

    /* 格式化数据 */
    foreach ($row AS $key => $value)
    {
        $row[$key]['return_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['return_time']);
        $row[$key]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['add_time']);
        $row[$key]['update_time'] = local_date($GLOBALS['_CFG']['time_format'], $value['update_time']);
        if ($value['status'] == 1)
        {
            $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][1];
        }
        else
        {
            $row[$key]['status_name'] = $GLOBALS['_LANG']['delivery_status'][0];
        }
    }
    $arr = array('back' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

/**
 * 取得发货单信息
 * @param   int     $delivery_order   发货单id（如果delivery_order > 0 就按id查，否则按sn查）
 * @param   string  $delivery_sn      发货单号
 * @return  array   发货单信息（金额都有相应格式化的字段，前缀是formated_）
 */
function delivery_order_info($delivery_id, $delivery_sn = '')
{
    $return_order = array();
    if (empty($delivery_id) || !is_numeric($delivery_id))
    {
        return $return_order;
    }

    $where = '';
    /* 获取管理员信息 */
    $admin_info = admin_info();

    /* 如果管理员属于某个办事处，只列出这个办事处管辖的发货单 */
    if ($admin_info['agency_id'] > 0)
    {
        $where .= " AND agency_id = '" . $admin_info['agency_id'] . "' ";
    }

    /* 如果管理员属于某个供货商，只列出这个供货商的发货单 */
    if ($admin_info['suppliers_id'] > 0)
    {
        $where .= " AND suppliers_id = '" . $admin_info['suppliers_id'] . "' ";
    }

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('delivery_order');
    if ($delivery_id > 0)
    {
        $sql .= " WHERE delivery_id = '$delivery_id'";
    }
    else
    {
        $sql .= " WHERE delivery_sn = '$delivery_sn'";
    }

    $sql .= $where;
    $sql .= " LIMIT 0, 1";
    $delivery = $GLOBALS['db']->getRow($sql);
    if ($delivery)
    {
        /* 格式化金额字段 */
        $delivery['formated_insure_fee']     = price_format($delivery['insure_fee'], false);
        $delivery['formated_shipping_fee']   = price_format($delivery['shipping_fee'], false);

        /* 格式化时间字段 */
        $delivery['formated_add_time']       = local_date($GLOBALS['_CFG']['time_format'], $delivery['add_time']);
        $delivery['formated_update_time']    = local_date($GLOBALS['_CFG']['time_format'], $delivery['update_time']);

        $return_order = $delivery;
    }

    return $return_order;
}

/**
 * 取得退货单信息
 * @param   int     $back_id   退货单 id（如果 back_id > 0 就按 id 查，否则按 sn 查）
 * @return  array   退货单信息（金额都有相应格式化的字段，前缀是 formated_ ）
 */
function back_order_info($back_id)
{
    $return_order = array();
    if (empty($back_id) || !is_numeric($back_id))
    {
        return $return_order;
    }

    $where = '';
    /* 获取管理员信息 */
    $admin_info = admin_info();

    /* 如果管理员属于某个办事处，只列出这个办事处管辖的发货单 */
    if ($admin_info['agency_id'] > 0)
    {
        $where .= " AND agency_id = '" . $admin_info['agency_id'] . "' ";
    }

    /* 如果管理员属于某个供货商，只列出这个供货商的发货单 */
    if ($admin_info['suppliers_id'] > 0)
    {
        $where .= " AND suppliers_id = '" . $admin_info['suppliers_id'] . "' ";
    }

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('back_order') . "
            WHERE back_id = '$back_id'
            $where
            LIMIT 0, 1";
    $back = $GLOBALS['db']->getRow($sql);
    if ($back)
    {
        /* 格式化金额字段 */
        $back['formated_insure_fee']     = price_format($back['insure_fee'], false);
        $back['formated_shipping_fee']   = price_format($back['shipping_fee'], false);

        /* 格式化时间字段 */
        $back['formated_add_time']       = local_date($GLOBALS['_CFG']['time_format'], $back['add_time']);
        $back['formated_update_time']    = local_date($GLOBALS['_CFG']['time_format'], $back['update_time']);
        $back['formated_return_time']    = local_date($GLOBALS['_CFG']['time_format'], $back['return_time']);

        $return_order = $back;
    }

    return $return_order;
}

/**
 * 超级礼包发货数处理
 * @param   array   超级礼包商品列表
 * @param   int     发货数量
 * @param   int     订单ID
 * @param   varchar 虚拟代码
 * @param   int     礼包ID
 * @return  array   格式化结果
 */
function package_goods(&$package_goods, $goods_number, $order_id, $extension_code, $package_id)
{
    $return_array = array();

    if (count($package_goods) == 0 || !is_numeric($goods_number))
    {
        return $return_array;
    }

    foreach ($package_goods as $key=>$value)
    {
        $return_array[$key] = $value;
        $return_array[$key]['order_send_number'] = $value['order_goods_number'] * $goods_number;
        $return_array[$key]['sended'] = package_sended($package_id, $value['goods_id'], $order_id, $extension_code, $value['product_id']);
        $return_array[$key]['send'] = ($value['order_goods_number'] * $goods_number) - $return_array[$key]['sended'];
        $return_array[$key]['storage'] = $value['goods_number'];


        if ($return_array[$key]['send'] <= 0)
        {
            $return_array[$key]['send'] = $GLOBALS['_LANG']['act_good_delivery'];
            $return_array[$key]['readonly'] = 'readonly="readonly"';
        }

        /* 是否缺货 */
        if ($return_array[$key]['storage'] <= 0 && $GLOBALS['_CFG']['use_storage'] == '1')
        {
            $return_array[$key]['send'] = $GLOBALS['_LANG']['act_good_vacancy'];
            $return_array[$key]['readonly'] = 'readonly="readonly"';
        }
    }

    return $return_array;
}

/**
 * 获取超级礼包商品已发货数
 *
 * @param       int         $package_id         礼包ID
 * @param       int         $goods_id           礼包的产品ID
 * @param       int         $order_id           订单ID
 * @param       varchar     $extension_code     虚拟代码
 * @param       int         $product_id         货品id
 *
 * @return  int     数值
 */
function package_sended($package_id, $goods_id, $order_id, $extension_code, $product_id = 0)
{
    if (empty($package_id) || empty($goods_id) || empty($order_id) || empty($extension_code))
    {
        return false;
    }

    $sql = "SELECT SUM(DG.send_number)
            FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " . $GLOBALS['ecs']->table('delivery_order') . " AS o
            WHERE o.delivery_id = DG.delivery_id
            AND o.status IN (0, 2)
            AND o.order_id = '$order_id'
            AND DG.parent_id = '$package_id'
            AND DG.goods_id = '$goods_id'
            AND DG.extension_code = '$extension_code'";
    $sql .= ($product_id > 0) ? " AND DG.product_id = '$product_id'" : '';

    $send = $GLOBALS['db']->getOne($sql);

    return empty($send) ? 0 : $send;
}

/**
 * 改变订单中商品库存
 * @param   int     $order_id  订单 id
 * @param   array   $_sended   Array(‘商品id’ => ‘此单发货数量’)
 * @param   array   $goods_list
 * @return  Bool
 */
function change_order_goods_storage_split($order_id, $_sended, $goods_list = array())
{
    /* 参数检查 */
    if (!is_array($_sended) || empty($order_id))
    {
        return false;
    }

    foreach ($_sended as $key => $value)
    {
        // 商品（超值礼包）
        if (is_array($value))
        {
            if (!is_array($goods_list))
            {
                $goods_list = array();
            }
            foreach ($goods_list as $goods)
            {
                if (($key != $goods['rec_id']) || (!isset($goods['package_goods_list']) || !is_array($goods['package_goods_list'])))
                {
                    continue;
                }

                // 超值礼包无库存，只减超值礼包商品库存
                foreach ($goods['package_goods_list'] as $package_goods)
                {
                    if (!isset($value[$package_goods['goods_id']]))
                    {
                        continue;
                    }

                    // 减库存：商品（超值礼包）（实货）、商品（超值礼包）（虚货）
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') ."
                            SET goods_number = goods_number - '" . $value[$package_goods['goods_id']] . "'
                            WHERE goods_id = '" . $package_goods['goods_id'] . "' ";
                    $GLOBALS['db']->query($sql);
                }
            }
        }
        // 商品（实货）
        elseif (!is_array($value))
        {
            /* 检查是否为商品（实货） */
            foreach ($goods_list as $goods)
            {
                if ($goods['rec_id'] == $key && $goods['is_real'] == 1)
                {
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                            SET goods_number = goods_number - '" . $value . "'
                            WHERE goods_id = '" . $goods['goods_id'] . "' ";
                    $GLOBALS['db']->query($sql, 'SILENT');
                    break;
                }
            }
        }
    }

    return true;
}

/**
 *  超值礼包虚拟卡发货、跳过修改订单商品发货数的虚拟卡发货
 *
 * @access  public
 * @param   array      $goods      超值礼包虚拟商品列表数组
 * @param   string      $order_sn   本次操作的订单
 *
 * @return  boolen
 */
function package_virtual_card_shipping($goods, $order_sn)
{
    if (!is_array($goods))
    {
        return false;
    }

    /* 包含加密解密函数所在文件 */
    include_once(ROOT_PATH . 'includes/lib_code.php');

    // 取出超值礼包中的虚拟商品信息
    foreach ($goods as $virtual_goods_key => $virtual_goods_value)
    {
        /* 取出卡片信息 */
        $sql = "SELECT card_id, card_sn, card_password, end_date, crc32
                FROM ".$GLOBALS['ecs']->table('virtual_card')."
                WHERE goods_id = '" . $virtual_goods_value['goods_id'] . "'
                AND is_saled = 0
                LIMIT " . $virtual_goods_value['num'];
        $arr = $GLOBALS['db']->getAll($sql);
        /* 判断是否有库存 没有则推出循环 */
        if (count($arr) == 0)
        {
            continue;
        }

        $card_ids = array();
        $cards = array();

        foreach ($arr as $virtual_card)
        {
            $card_info = array();

            /* 卡号和密码解密 */
            if ($virtual_card['crc32'] == 0 || $virtual_card['crc32'] == crc32(AUTH_KEY))
            {
                $card_info['card_sn'] = decrypt($virtual_card['card_sn']);
                $card_info['card_password'] = decrypt($virtual_card['card_password']);
            }
            elseif ($virtual_card['crc32'] == crc32(OLD_AUTH_KEY))
            {
                $card_info['card_sn'] = decrypt($virtual_card['card_sn'], OLD_AUTH_KEY);
                $card_info['card_password'] = decrypt($virtual_card['card_password'], OLD_AUTH_KEY);
            }
            else
            {
                return false;
            }
            $card_info['end_date'] = date($GLOBALS['_CFG']['date_format'], $virtual_card['end_date']);
            $card_ids[] = $virtual_card['card_id'];
            $cards[] = $card_info;
        }

        /* 标记已经取出的卡片 */
        $sql = "UPDATE ".$GLOBALS['ecs']->table('virtual_card')." SET ".
            "is_saled = 1 ,".
            "order_sn = '$order_sn' ".
            "WHERE " . db_create_in($card_ids, 'card_id');
        if (!$GLOBALS['db']->query($sql))
        {
            return false;
        }

        /* 获取订单信息 */
        $sql = "SELECT order_id, order_sn, consignee, email FROM ".$GLOBALS['ecs']->table('order_info'). " WHERE order_sn = '$order_sn'";
        $order = $GLOBALS['db']->GetRow($sql);

        $cfg = $GLOBALS['_CFG']['send_ship_email'];
        if ($cfg == '1')
        {
            /* 发送邮件 */
            $GLOBALS['smarty']->assign('virtual_card',                   $cards);
            $GLOBALS['smarty']->assign('order',                          $order);
            $GLOBALS['smarty']->assign('goods',                          $virtual_goods_value);

            $GLOBALS['smarty']->assign('send_time', date('Y-m-d H:i:s'));
            $GLOBALS['smarty']->assign('shop_name', $GLOBALS['_CFG']['shop_name']);
            $GLOBALS['smarty']->assign('send_date', date('Y-m-d'));
            $GLOBALS['smarty']->assign('sent_date', date('Y-m-d'));

            $tpl = get_mail_template('virtual_card');
            $content = $GLOBALS['smarty']->fetch('str:' . $tpl['template_content']);
            send_mail($order['consignee'], $order['email'], $tpl['template_subject'], $content, $tpl['is_html']);
        }
    }

    return true;
}

/**
 * 删除发货单时进行退货
 *
 * @access   public
 * @param    int     $delivery_id      发货单id
 * @param    array   $delivery_order   发货单信息数组
 *
 * @return  void
 */
function delivery_return_goods($delivery_id, $delivery_order)
{
    /* 查询：取得发货单商品 */
    $goods_sql = "SELECT *
                 FROM " . $GLOBALS['ecs']->table('delivery_goods') . "
                 WHERE delivery_id = " . $delivery_order['delivery_id'];
    $goods_list = $GLOBALS['db']->getAll($goods_sql);
    /* 更新： */
    foreach ($goods_list as $key=>$val)
    {
        $sql = "UPDATE " . $GLOBALS['ecs']->table('order_goods') .
            " SET send_number = send_number-'".$goods_list[$key]['send_number']. "'".
            " WHERE order_id = '".$delivery_order['order_id']."' AND goods_id = '".$goods_list[$key]['goods_id']."' LIMIT 1";
        $GLOBALS['db']->query($sql);
    }
    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') .
        " SET shipping_status = '0' , order_status = 1".
        " WHERE order_id = '".$delivery_order['order_id']."' LIMIT 1";
    $GLOBALS['db']->query($sql);
}

/**
 * 删除发货单时删除其在订单中的发货单号
 *
 * @access   public
 * @param    int      $order_id              定单id
 * @param    string   $delivery_invoice_no   发货单号
 *
 * @return  void
 */
function del_order_invoice_no($order_id, $delivery_invoice_no)
{
    /* 查询：取得订单中的发货单号 */
    $sql = "SELECT invoice_no
            FROM " . $GLOBALS['ecs']->table('order_info') . "
            WHERE order_id = '$order_id'";
    $order_invoice_no = $GLOBALS['db']->getOne($sql);

    /* 如果为空就结束处理 */
    if (empty($order_invoice_no))
    {
        return;
    }

    /* 去除当前发货单号 */
    $order_array = explode('<br>', $order_invoice_no);
    $delivery_array = explode('<br>', $delivery_invoice_no);

    foreach ($order_array as $key => $invoice_no)
    {
        if ($ii = array_search($invoice_no, $delivery_array))
        {
            unset($order_array[$key], $delivery_array[$ii]);
        }
    }

    $arr['invoice_no'] = implode('<br>', $order_array);
    update_order($order_id, $arr);
}

/**
 * 获取站点根目录网址
 *
 * @access  private
 * @return  Bool
 */
function get_site_root_url()
{
    return 'http://' . $_SERVER['HTTP_HOST'] . str_replace('/' . SELLER_PATH . '/order.php', '', PHP_SELF);

}

//ecmoban模板堂 --zhuo start
function download_orderlist($result) {
    if(empty($result)) {
        return ecs_i("没有符合您要求的数据！^_^");
    }

    $data = ecs_i('订单号,商家名称,下单会员,下单时间,收货人,联系电话,地址,总金额,配送费用,保价费用,支付费用,余额金额,积分金额,红包金额,发票税额,折扣金额,优惠券金额,应付金额,确认状态,付款状态,发货状态,订单来源,支付方式'."\n");
    $count = count($result);
    for ($i = 0; $i < $count; $i++) {
        $order_sn = ecs_i('#'.$result[$i]['order_sn']); //订单号前加'#',避免被四舍五入 by wu
        $order_user = ecs_i($result[$i]['buyer']);
        $order_time = ecs_i($result[$i]['short_order_time']);
        $consignee = ecs_i($result[$i]['consignee']);
        $tel = !empty($result[$i]['mobile']) ? ecs_i($result[$i]['mobile']) : ecs_i($result[$i]['tel']);
        $address = ecs_i(addslashes(str_replace(",","，","[" . $result[$i]['region'] ."] ". $result[$i]['address'])));
        $order_amount = ecs_i($result[$i]['order_amount']);
        $shipping_fee = ecs_i($result[$i]['shipping_fee']);//配送费用
        $insure_fee = ecs_i($result[$i]['insure_fee']);//保价费用
        $pay_fee = ecs_i($result[$i]['pay_fee']);//支付费用
        $surplus = ecs_i($result[$i]['surplus']);//余额费用
        $integral_money = ecs_i($result[$i]['integral_money']);//积分金额
        $bonus = ecs_i($result[$i]['bonus']);//红包金额
        $tax = ecs_i($result[$i]['tax']);//发票税额
        $discount = ecs_i($result[$i]['discount']);//折扣金额
        $coupons = ecs_i($result[$i]['coupons']);//优惠券金额
        $order_status = ecs_i($GLOBALS['_LANG']['os'][$result[$i]['order_status']]);
        $seller_name = ecs_i($result[$i]['user_name']); //商家名称
        $pay_status = ecs_ecs_i($GLOBALS['_LANG']['ps'][$result[$i]['pay_status']]);
        $shipping_status = ecs_i($GLOBALS['_LANG']['ss'][$result[$i]['shipping_status']]);
        $froms = ecs_i($result[$i]['froms']);
        $pay_name = ecs_i($result[$i]['pay_name']);
        $data .= $order_sn . ',' . $seller_name  . ',' . $order_user . ',' .
            $order_time . ',' . $consignee . ',' . $tel . ',' .
            $address . ',' . ecs_i($result[$i]['total_fee']) . ',' .
            $shipping_fee . ',' . $insure_fee . ',' .
            $pay_fee . ',' . $surplus . ',' .
            $integral_money . ',' . $bonus . ',' .
            $tax . ',' . $discount . ',' . $coupons . ',' .
            $order_amount . ',' . $order_status . ',' .
            $pay_status . ',' . $shipping_status . ',' . $froms . ',' . $pay_name . "\n";
    }
    return $data;
}
function ecs_i($strInput) {
    return iconv('utf-8','gb2312',$strInput);//页面编码为utf-8时使用，否则导出的中文为乱码
}

//ecmoban模板堂 --zhuo end

/**
 * curl 获取
 */
function curlGet($url, $timeout = 5, $header = "") {
    $defaultHeader = '$header = "User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9.2.12) Gecko/20101026 Firefox/3.6.12\r\n";
        $header.="Accept:text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
        $header.="Accept-language: zh-cn,zh;q=0.5\r\n";
        $header.="Accept-Charset: utf-8;q=0.7,*;q=0.7\r\n";';
    $header = empty($header) ? $defaultHeader : $header;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);    // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($header)); //模拟的header头
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * 判断管理员对某一个操作是否有权限。
 *
 * 根据当前对应的action_code，然后再和用户session里面的action_list做匹配，以此来决定是否可以继续执行。
 * @param     string    $priv_str    操作对应的priv_str
 * @param     string    $msg_type       返回的类型
 * @return true/false
 */
function admin_priv($priv_str, $msg_type = '' , $msg_output = true)
{
    global $_LANG;

    if(!isset($_SESSION['seller_action_list'])){
        $admin_id = get_admin_id();
        $sql = 'SELECT action_list ' .
            ' FROM ' .$GLOBALS['ecs']->table('admin_user') .
            " WHERE user_id = '$admin_id'";
        $action_list = $GLOBALS['db']->getOne($sql, true);
        $_SESSION['seller_action_list'] = $action_list;
    }else{
        $action_list = $_SESSION['seller_action_list'];
    }

    if ($action_list == 'all')
    {
        return true;
    }

    if (strpos(',' . $action_list . ',', ',' . $priv_str . ',') === false)
    {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        if ( $msg_output)
        {
            sys_msg($_LANG['priv_error'], 0, $link);
        }
        return false;
    }
    else
    {
        return true;
    }
}

/**
 * 取得订单信息
 * @param   int     $order_id   订单id（如果order_id > 0 就按id查，否则按sn查）
 * @param   string  $order_sn   订单号
 * @return  array   订单信息（金额都有相应格式化的字段，前缀是formated_）
 */
function seller_order_info($order_id, $order_sn = '')
{
    /* 计算订单各种费用之和的语句 */
    $total_fee = ", (goods_amount - discount + tax + shipping_fee + insure_fee + pay_fee + pack_fee + card_fee) AS total_fee ";
    $order_id = intval($order_id);

    if ($order_id > 0)
    {
        //@模板堂-bylu 这里连表查下支付方法表,获取到"pay_code"字段值;
        $sql = "SELECT * $total_fee FROM " .$GLOBALS['ecs']->table('order_info'). " WHERE order_id = '$order_id'";
    }
    else
    {
        //@模板堂-bylu 这里连表查下支付方法表,获取到"pay_code"字段值;
        $sql = "SELECT * $total_fee from " .$GLOBALS['ecs']->table('order_info'). " WHERE order_sn = '$order_sn'";
    }
    $order = $GLOBALS['db']->getRow($sql);
    if ($order['cost_amount'] <= 0) {
        $order['cost_amount'] = goods_cost_price($order['order_id']);
    }
    /*获取发票ID start*/
    $user_id = $order['user_id'];
    $sql = "SELECT o.invoice_id FROM "
        . $GLOBALS['ecs']->table('order_invoice') . " AS o "
        . " LEFT JOIN " . $GLOBALS['ecs']->table('order_info')  . " AS oi ON o.inv_payee = oi.inv_payee "
        . " WHERE o.user_id='$user_id'";
    $order['invoice_id'] = $GLOBALS['db']->getOne($sql);
    /*获取发票ID end*/
    /* 格式化金额字段 */
    if ($order)
    {
        $order['order_id'] = $order['order_id'];
        $order['user_id'] = $order['user_id'];

        $sql = "SELECT use_val FROM " .$GLOBALS['ecs']->table('value_card_record'). " WHERE order_id = '$order_id'";
        $value_card = $GLOBALS['db']->getOne($sql, true);
        $order['use_val'] = $value_card;

        $payment = payment_info($order['pay_id']);
        $order['pay_code'] = $payment['pay_code'];

        $order['child_order'] = get_seller_order_child($order['order_id'], $order['main_order_id']);

        $order['formated_goods_amount'] = price_format($order['goods_amount'], false);
        $order['formated_cost_amount'] = $order['cost_amount'] > 0 ? price_format($order['cost_amount'], false) : 0;
        $order['formated_profit_amount'] = price_format($order['total_fee'] - $order['cost_amount'] - $order['shipping_fee'], false);
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
        $order['formated_coupons'] = price_format($order['coupons'], false);
        $order['formated_integral_money'] = price_format($order['integral_money'], false);
        $order['formated_value_card'] = price_format($order['use_val'], false);
        $order['formated_surplus'] = price_format($order['surplus'], false);
        $order['formated_order_amount'] = price_format(abs($order['order_amount']), false);
        $order['formated_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['add_time']);
        $order['pay_points'] = $order['integral']; //by kong  获取积分

        $order_goods = get_order_seller_id($order['order_id']);
        $order['ru_id'] = $order_goods['ru_id'];

        if (empty($order['confirm_take_time']) && $order['order_status'] == OS_CONFIRMED && $order['shipping_status'] == SS_RECEIVED && $order['shipping_status'] == PS_PAYED) {
            $sql = "SELECT log_time FROM " . $GLOBALS['ecs']->table("order_action") . " WHERE order_status = " . OS_CONFIRMED . " AND shipping_status = " . SS_RECEIVED . " " .
                "AND pay_status = " . PS_PAYED . " AND order_id = '" . $order['order_id'] . "'";
            $log_time = $GLOBALS['db']->getOne($sql, true);

            $other['confirm_take_time'] = $log_time;
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $other, 'UPDATE', "order_id = '" . $order['order_id'] . "'");

            $order['confirm_take_time'] = $log_time;
        }
    }
    return $order;
}


/*
 * 获取主订单的订单数量
 */
function get_seller_order_child($order_id, $main_order_id){

    $count = 0;
    if($main_order_id == 0){
        $sql = "SELECT COUNT(*) FROM " .$GLOBALS['ecs']->table('order_info'). "WHERE main_order_id  = '$order_id'" ;
        $count = $GLOBALS['db']->getOne($sql);
    }
    return $count;
}

/* 查询订单商家ID */
function get_order_seller_id($order = '', $type = 0){

    if($type == 1){
        $res = $GLOBALS['db']->getRow("SELECT og.ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " AS og, " .
            $GLOBALS['ecs']->table('order_info'). " AS o " .
            " WHERE og.order_id = o.order_id AND o.order_sn = '$order' LIMIT 1");
    }else{
        $res = $GLOBALS['db']->getRow("SELECT ru_id FROM " .$GLOBALS['ecs']->table('order_goods'). " WHERE order_id = '$order' LIMIT 1");
    }

    return $res;
}


/**
 * 订单线上付款日志
 * $id 支付日志ID或订单ID
 */
function get_pay_log($id, $type = 0, $is_paid = null) {

    if ($type == 1) {
        $select = "log_id, is_paid";
        $where = "order_id = '$id'";
    } else {
        $select = "*";
        $where = "log_id = '$id'";
    }

    if (is_null($is_paid) && !is_array($is_paid)) {
        $where .= " AND is_paid = 0";
    } elseif (!is_null($is_paid) && !is_array($is_paid)) {
        $where .= " AND is_paid = '$is_paid'";
    }

    $sql = "SELECT " . $select . " FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE " . $where . " LIMIT 1";
    return $GLOBALS['db']->getRow($sql);
}

/**
 * 查询用户地址信息
 * user
 * order
 */
function get_user_region_address($order_id = 0, $address = '', $type = 0) {

    if ($type == 1) {
        $table = 'order_return';
        $where = "o.ret_id = '$order_id'";
    } else {
        $table = 'order_info';
        $where = "o.order_id = '$order_id'";
    }

    /* 取得区域名 **|IFNULL(c.region_name, ''), '  ', |** */
    $sql = "SELECT concat(IFNULL(p.region_name, ''), " .
        "'  ', IFNULL(t.region_name, ''), '  ', IFNULL(d.region_name, ''), '  ', IFNULL(s.region_name, '')) AS region " .
        "FROM " . $GLOBALS['ecs']->table($table) . " AS o " .
        //"LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS c ON o.country = c.region_id " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS p ON o.province = p.region_id " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS t ON o.city = t.region_id " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS d ON o.district = d.region_id " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS s ON o.street = s.region_id " .
        "WHERE " . $where;
    $region = $GLOBALS['db']->getOne($sql);
    if ($address) {
        $region = $region . "&nbsp;" . $address;
    }

    return $region;
}
