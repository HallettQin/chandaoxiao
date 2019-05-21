<?php

/**
 * ECSHOP 前台公用函数库
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
 * 更新用户SESSION,COOKIE及登录时间、登录次数。
 *
 * @access  public
 * @return  void
 */
function update_user_info()
{
    if (!$_SESSION['user_id'])
    {
        return false;
    }

    /* 查询会员信息 */
    $time = date('Y-m-d');
    $sql = 'SELECT u.user_id, u.user_money,u.email, u.pay_points, u.user_rank, u.rank_points, '.
            ' IFNULL(b.type_money, 0) AS user_bonus, u.last_login, u.last_ip'.
            ' FROM ' .$GLOBALS['ecs']->table('users'). ' AS u ' .
            ' LEFT JOIN ' .$GLOBALS['ecs']->table('user_bonus'). ' AS ub'.
            ' ON ub.user_id = u.user_id AND ub.used_time = 0 ' .
            ' LEFT JOIN ' .$GLOBALS['ecs']->table('bonus_type'). ' AS b'.
            " ON b.type_id = ub.bonus_type_id AND b.use_start_date <= '$time' AND b.use_end_date >= '$time' ".
            " WHERE u.user_id = '$_SESSION[user_id]'";
    $row = $GLOBALS['db']->getRow($sql);
    if ($row)
    {
        /* 更新SESSION */
        $_SESSION['last_time']   = $row['last_login'];
        $_SESSION['last_ip']     = $row['last_ip'];
        $_SESSION['login_fail']  = 0;
        $_SESSION['email']       = $row['email'];

        /*判断是否是特殊等级，可能后台把特殊会员组更改普通会员组*/
        if($row['user_rank'] > 0)
        {
            $sql="SELECT special_rank FROM ".$GLOBALS['ecs']->table('user_rank')." WHERE rank_id = '$row[user_rank]'";
            if(!$GLOBALS['db']->getOne($sql))
            {
                $sql = "UPDATE " . $GLOBALS['ecs']->table('users') . "SET user_rank = 0 WHERE user_id = '$_SESSION[user_id]'";
                $GLOBALS['db']->query($sql);
                $row['user_rank'] = 0;
            }
        }

        /* 取得用户等级和折扣 */
        if ($row['user_rank'] == 0)
        {
            // 非特殊等级，根据等级积分计算用户等级（注意：不包括特殊等级）
            $sql = 'SELECT rank_id, discount FROM ' . $GLOBALS['ecs']->table('user_rank') . " WHERE special_rank = '0' AND min_points <= " . intval($row['rank_points']) . ' AND max_points > ' . intval($row['rank_points']);
            $rank_row = $GLOBALS['db']->getRow($sql);
            if ($rank_row)
            {
                $_SESSION['user_rank'] = $rank_row['rank_id'];
                $_SESSION['discount']  = $rank_row['discount'] / 100.00;
            }
            else
            {
                $_SESSION['user_rank'] = 0;
                $_SESSION['discount']  = 1;
            }
        }
        else
        {
            // 特殊等级
            $sql = 'SELECT rank_id, discount FROM ' . $GLOBALS['ecs']->table('user_rank') . " WHERE rank_id = '$row[user_rank]'";
            $rank_row = $GLOBALS['db']->getRow($sql);
            if ($rank_row)
            {
                $_SESSION['user_rank'] = $rank_row['rank_id'];
                $_SESSION['discount']  = $rank_row['discount'] / 100.00;
            }
            else
            {
                $sql = 'SELECT rank_id, discount FROM ' . $GLOBALS['ecs']->table('user_rank') . " WHERE special_rank = '0' AND min_points <= " . intval($row['rank_points']) . ' AND max_points > ' . intval($row['rank_points']);
                $rank_row = $GLOBALS['db']->getRow($sql);

                if ($rank_row) {
                    $_SESSION['user_rank'] = $rank_row['rank_id'];
                    $_SESSION['discount'] = $rank_row['discount'] / 100.00;
                } else {
                    $_SESSION['user_rank'] = 0;
                    $_SESSION['discount'] = 1;
                }
            }
        }
    }

    $sql = "DELETE FROM " .$GLOBALS['ecs']->table('sessions'). " WHERE userid = '" .$row['user_id']. "' AND adminid = 0 AND sesskey <> '" .SESS_ID. "'";
    $GLOBALS['db']->query($sql);

    /* 更新登录时间，登录次数及登录ip */
    $sql = "UPDATE " .$GLOBALS['ecs']->table('users'). " SET".
           " visit_count = visit_count + 1, ".
           " last_ip = '" .real_ip(). "',".
           " user_rank = '" .$_SESSION['user_rank']. "',".
           " last_login = '" .gmtime(). "'".
           " WHERE user_id = '" . $_SESSION['user_id'] . "'";
    $GLOBALS['db']->query($sql);

    return $row;
}

//检查权限
function priv() {
    global $_LANG;

    if (!isset($_SESSION['is_purchasers']) || !$_SESSION['is_purchasers']) {
        if (isset($_SESSION['is_suppliers'])) {
            show_message('对不起！您不是终端零售商，无权发起订单采购，谢谢！', ['返回首页'], ['index.php']);

        } else {
            show_message('对不起！您不是终端零售商，无权发起订单采购，谢谢！', ['返回首页', '去申请'], ['index.php', 'register.php']);
        }
    }
}

/**
 * 检查用户权限
 * @param $id
 */
function check_user_info($id) {
    if ($id == 0)
    {
        return;
    }

    if (!isset($GLOBALS['smarty'])) {
        //修复登录bug
        return;
    }
    //如果设置了采购商
    if (isset($_SESSION['is_purchasers'])) {
        $GLOBALS['smarty']->assign('is_purchasers', $_SESSION['is_purchasers']);
    } else {
        $sql  = 'SELECT u.user_id, u.is_purchasers'.
            ' FROM ' .$GLOBALS['ecs']->table('users'). ' AS u ' .
            " WHERE u.user_id = '$id'";

        $user = $GLOBALS['db']->getRow($sql);

        if ($user['is_purchasers'] == 1) {
            $GLOBALS['smarty']->assign('is_purchasers', 1);

            $_SESSION['is_purchasers'] = 1;
        } else {
            $GLOBALS['smarty']->assign('is_purchasers', 0);
            $_SESSION['is_purchasers'] = 0;
        }
    }

    if (isset($_SESSION['is_suppliers'])) {
        $GLOBALS['smarty']->assign('is_suppliers', $_SESSION['is_suppliers']);
    } else {
        $sql  = 'SELECT u.ru_id'.
            ' FROM ' .$GLOBALS['ecs']->table('admin_user'). ' AS u ' .
            " WHERE u.ru_id = '$id'";

        $user = $GLOBALS['db']->getRow($sql);

        if ($user) {
            $GLOBALS['smarty']->assign('is_suppliers', 1);

            $_SESSION['is_suppliers'] = 1;
        } else {
            $GLOBALS['smarty']->assign('is_suppliers', 0);
            $_SESSION['is_suppliers'] = 0;
        }
    }


    //公司类型
    if (isset($_SESSION['company_type'])) {
        $GLOBALS['smarty']->assign('company_type', $_SESSION['company_type']);
    } else {
        //注册时状态
        $sql = 'SELECT content ' . 'FROM ' .  $GLOBALS['ecs']->table('reg_extend_info') . ' WHERE user_id = ' . $id ." AND reg_field_id = 7";
        $type =  $GLOBALS['db']->getOne($sql);

        $_SESSION['company_type'] = $type;
        $GLOBALS['smarty']->assign('company_type', $_SESSION['company_type']);
    }
}

/**
 *  获取用户信息数组
 *
 * @access  public
 * @param
 *
 * @return array        $user       用户信息数组
 */
function get_user_info($id = 0)
{
    if ($id == 0)
    {
        $id = $_SESSION['user_id'];
    }

    $time = local_date('Y-m-d', gmtime());

    $sql  = 'SELECT u.user_id, u.email, u.user_name, u.user_money, u.mobile_phone, u.pay_points, u.rank_points, u.nick_name, u.user_rank'.
            ' FROM ' .$GLOBALS['ecs']->table('users'). ' AS u ' .
            " WHERE u.user_id = '$id'";
    $user = $GLOBALS['db']->getRow($sql);
    $bonus = get_user_bonus($id);

    /* 更新会员等级 */
    if($user['user_rank'] != $_SESSION['user_rank']){

        $sql = 'SELECT rank_id, discount FROM ' . $GLOBALS['ecs']->table('user_rank') . " WHERE rank_id = '" .$user['user_rank']. "' LIMIT 1";
        $rank_row = $GLOBALS['db']->getRow($sql);
        if ($rank_row) {
            $user['discount'] = $rank_row['discount'] / 100.00;
        }else{
            $user['discount'] = 1;
        }

        $sql = "UPDATE " . $GLOBALS['ecs']->table('sessions') . "SET user_rank = '" .$user['user_rank']. "', discount= '" .$user['discount']. "' WHERE userid = '" .$user['user_id']. "' AND adminid = 0";
        $GLOBALS['db']->query($sql);
    }

    //ecmoban模板堂 --zhuo start
    if($user['user_name'] != $_SESSION['user_name']){
        $username = $_SESSION['user_name'];
        $strlen = strlen($username);
        if(preg_match("/[^\d-., ]/",$username))
        {
            //不是数字
            $a="/([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)(\.[a-z]*)/i";  //邮箱匹配
            if(preg_match($a,$username)){
                $user['username']    = $user['email'];
            }else{
                $user['username']    = $user['user_name'];
            }
        }
        else
        {
            if($strlen != 11){
                //不是数字
                $a="/([a-z0-9]*[-_\.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)(\.[a-z]*)/i";  //邮箱匹配
                if(preg_match($a,$username)){
                        $user['username']    = $user['email'];
                }else{
                        $user['username']    = $user['user_name'];
                }
            }else{
                $user['username']    = $user['mobile_phone'];
            }
        }
    }else{
        $user['username']    = $user['user_name'];
    }

    $user['payPoints']  = $user['pay_points'];
    $user['userMoney']  = $user['user_money'];
    //ecmoban模板堂 --zhuo end

    $user['nick_name'] = !empty($user['nick_name']) ? $user['nick_name'] : $user['username'];

    $user['user_points'] = $user['pay_points'] . $GLOBALS['_CFG']['integral_name'];
    $user['user_money']  = price_format($user['user_money'], false);
    $user['user_bonus']  = price_format($bonus['bonus_value'], false);

    return $user;
}

//获取当前页面action by wu
function get_page_action() {
    //获取请求字符串
    $query_string = $_SERVER["QUERY_STRING"];
    if (!empty($query_string)) {
        //拆分为请求数组
        $query_arr = explode('&', $query_string);
        foreach ($query_arr as $key => $val) {
            //拆分为数据对
            $val_arr = explode('=', $val);
            if ($val_arr[0] == 'act') {
                //echo $val_arr[1];
				$GLOBALS['smarty']->assign('act', $val_arr[1]);
                if (!empty($GLOBALS['_LANG'][$val_arr[1]])) {
                    return $GLOBALS['_LANG'][$val_arr[1]];
                }
            }
        }
    }
    return '';
}

/**
 * 取得当前位置和页面标题
 *
 * @access  public
 * @param   integer     $cat    分类编号（只有商品及分类、文章及分类用到）
 * @param   string      $str    商品名、文章标题或其他附加的内容（无链接）
 * @return  array
 */
function assign_ur_here($cat = 0, $str = '', $strArr = array(), $url = '', $ru_id = 0)
{
	/* 初始数据 by wu */
    $data = array('head'=>NULL, 'body'=>NULL, 'foot'=>NULL);
	$activity_title = '';

    /* 判断是否重写，取得文件名 */
    $cur_url = basename(PHP_SELF);
    if (intval($GLOBALS['_CFG']['rewrite']))
    {
        $filename = strpos($cur_url,'-') ? substr($cur_url, 0, strpos($cur_url,'-')) : substr($cur_url, 0, -4);
    }
    else
    {
        $filename = substr($cur_url, 0, -4);
    }

    $ur_here = '';
    /* 初始化“页面标题”和“当前位置” */

    $page_title = $GLOBALS['_CFG']['shop_title'];
    if (!in_array($filename, array('category', 'goods', 'single_sun', 'brand'))){
        $ur_here    ='<span>'. '<a href=".">' . $GLOBALS['_LANG']['home'] . '</a>'. '</span>';
		$data['head'] = $GLOBALS['_LANG']['home']; //头部 by wu
    }

    if(empty($cat))
    {
        $ur_here    ='<span>'. '<a href=".">' . $GLOBALS['_LANG']['home'] . '</a>'. '</span>';
		$data['head'] = $GLOBALS['_LANG']['home']; //头部 by wu
    }

    /* 根据文件名分别处理中间的部分 */
    if ($filename != 'index')
    {
        /* 处理有分类的 */
        if (in_array($filename, array('category', 'goods', 'category_discuss', 'article_cat', 'article', 'brand', 'single_sun', 'store_street', 'presale','group_buy','exchange','seckill','snatch','wholesale_goods','sample' == $filename)))
        {
            /* 商品分类或商品 */
            if ('category' == $filename || 'goods' == $filename || 'category_discuss' == $filename || 'brand' == $filename || 'single_sun' == $filename || 'sample' == $filename ||'presale' == $filename || 'group_buy' == $filename|| 'exchange' == $filename || 'seckill' == $filename || 'snatch' == $filename || 'wholesale_goods' == $filename)
            {
                if ($cat > 0)
                {
                    if($filename == 'presale'){
                        $cat_arr = get_parent_cats($cat);

                        $key     = 'cid';
                        $type    = 'presale';
                    }elseif($filename == 'wholesale_goods'){
                        $cat_arr = get_wholesale_parent_cats($cat);
                        $key     = 'cid';
                        $type    = 'wholesale_cat';
                    } elseif($filename == 'sample'){
                        $cat_arr = get_parent_cats($cat);
                        $key     = 'cid';
                        $type    = 'sample';
                    }
					else{
                        $cat_arr = get_parent_cats($cat);
                        $key     = 'cid';
                        $type    = 'category';
                    }

                }
                else
                {
                    $cat_arr = array();
                }
            }


            /* 文章分类或文章 */
            elseif ('article_cat' == $filename || 'article' == $filename)
            {
                if ($cat > 0)
                {
                    $cat_arr = get_article_parent_cats($cat);

                    $key  = 'acid';
                    $type = 'article_cat';
                }
                else
                {
                    $cat_arr = array();
                }
            }

            /* 循环分类 */
            if (!empty($cat_arr)) {
                krsort($cat_arr);

                foreach ($cat_arr AS $kval => $val) {
                    $page_title = htmlspecialchars($val['cat_name']) . '_' . $page_title;
                    $args = array($key => $val['cat_id']);

                    if($type == 'presale'){
                        $args['act'] = 'category';
                    } elseif($type == 'sample') {
                        $args['act'] = 'list';
                    }

                    if ($type == 'article_cat') {
                        $ur_here .= '<span class="arrow">></span>' . '<span> <a href="' . build_uri($type, $args, $val['cat_name']) . '">' . htmlspecialchars($val['cat_name']) . '</a>' . '</span>';
                    }

                    if ($val['parent_id'] == 0 && !defined('THEME_EXTENSION')) {

                        if ($type == 'category' || $type == 'presale') {
                            $ur_here .= "<h1>" . $val['cat_name'] . "</h1>";
                        }
                    } else {
                        $ur_here .= '<span class="arrow">></span>'; //by wang
                        $ur_here .= '<span class="breadcrumb-item ziji">';
                        $ur_here .= '<span class="filter-tag"><a href="' . build_uri($type, $args, $val['cat_name']) . '">' . $val['cat_name'] . '</a><i class="sc-icon-right"></i></span>';
						$cat_arr[$kval]['url'] = build_uri($type, $args, $val['cat_name']); //url by wu
                        if ($val['cat_tree']) {
                            $ur_here .= '<div class="dorpdown-layer"><div class="dd-spacer"></div><div class="dorpdown-content-wrap">';

                            $ur_here .= "<ul>";
                            foreach ($val['cat_tree'] as $ckey => $crow) {
                                $ur_here .= '<li><a href="' . build_uri($type, array('cid' => $crow['cat_id']), $crow['cat_name']) . '" title="' . $crow['cat_name'] . '">' . $crow['cat_name'] . '</a></li>';
								$cat_arr[$kval]['cat_tree'][$ckey]['url'] = build_uri($type, array('cid' => $crow['cat_id']), $crow['cat_name']); //url by wu
                            }
                            $ur_here .= "</ul>";
                            $ur_here .= '</div></div>';
                        }
                        $ur_here .= '</span>';
                    }
                }
            }
			$data['body'] = $cat_arr; //中间 by wu
        }
        /* 处理无分类的 */
        else
        {
            /* 团购 */
            if ('group_buy' == $filename)
            {
				$activity_title = $GLOBALS['_LANG']['group_buy'];
                $page_title = $GLOBALS['_LANG']['group_buy_goods'] . '_' . $page_title;
                $args       = array('gbid' => '0');
                $ur_here   .= '<span class="arrow">></span>'.'<span> <a href="group_buy.php">'.$GLOBALS['_LANG']['group_buy_goods'] . '</a>' . '</span>';
            }
            /* 拍卖 */
            elseif ('auction' == $filename)
            {
				$activity_title = $GLOBALS['_LANG']['auction'];
                $page_title = $GLOBALS['_LANG']['auction'] . '_' . $page_title;
                $args       = array('auid' => '0');
                $ur_here   .= '<span class="arrow">></span>' . '<span> <a href="auction.php">'.
                                $GLOBALS['_LANG']['auction'] . '</a>' . '</span>';
            }
			elseif ('store_street' == $filename)
            {
                $page_title = $GLOBALS['_LANG']['auction'] . '_' . $page_title;
                $args       = array('auid' => '0');
                $ur_here   .= '<span class="arrow">></span>' . '<span> <a href="store_street.php">'.
                                $GLOBALS['_LANG']['store_street'] . '</a>' . '</span>';
            }
            /* 夺宝 */
            elseif ('snatch' == $filename)
            {
				$activity_title = $GLOBALS['_LANG']['snatch'];
                $page_title = $GLOBALS['_LANG']['snatch'] . '_' . $page_title;
                $args       = array('id' => '0');
                $ur_here   .= ' <span class="arrow">></span>' . '<span><a href="snatch.php">'. $GLOBALS['_LANG']['snatch_list'] . '</a>' . '</span>';
            }
            /* 批发 */
            elseif ('wholesale' == $filename)
            {
                $page_title = $page_title;
                $args       = array('wsid' => '0');
                $ur_here   .= ' <span class="arrow">></span>' . '<span> <a href="wholesale.php">'.
                                $GLOBALS['_LANG']['wholesale'] . '</a>' . '</span>';
            }
            /* 积分兑换 */
            elseif ('exchange' == $filename)
            {
				$activity_title = $GLOBALS['_LANG']['exchange'];
                $page_title = $GLOBALS['_LANG']['exchange'] . '_' . $page_title;
                $args       = array('wsid' => '0');
                $ur_here   .= ' <span class="arrow">></span>' . '<span> <a href="exchange.php">'.
                                $GLOBALS['_LANG']['exchange'] . '</a>' . '</span>';
            }

			 /* 晒单 by guan */
            elseif ('single_sun' == $filename)
            {
            	$page_title = $GLOBALS['_LANG']['single_user'] . '_' . $page_title;
            	$args       = array('siid' => '0');
            	$ur_here .= " <code>&gt;</code> ";
            	$ur_here   .= '<a href="single_sun.php">' .
            			$GLOBALS['_LANG']['single_user'] . '</a>';
            }
            // 优惠活动
            elseif ('activity' == $filename)
            {
                $page_title = $GLOBALS['_LANG']['shopping_activity'] . '_' . $page_title;
                $args       = array('auid' => '0');
                $ur_here   .= '<span class="arrow">></span>' . '<span> <a href="activity.php">'.
                                $GLOBALS['_LANG']['shopping_activity'] . '</a>' . '</span>';
            }
            // 预售活动
            elseif ('presale' == $filename)
            {
				$activity_title = $GLOBALS['_LANG']['presell'];
                $page_title = $GLOBALS['_LANG']['shopping_activity'] . '_' . $page_title;
                $args       = array('auid' => '0');
                $ur_here   .= '<span class="arrow">></span>' . '<span> <a href="presale.php">'.
                                $GLOBALS['_LANG']['presell'] . '</a>' . '</span>';
            }
            //ecmoban模板堂 --zhuo start
            /* 礼品卡 */
            elseif ('gift_gard' == $filename)
            {
				$activity_title = $GLOBALS['_LANG']['gift_card_exchange'];
            	$page_title = $GLOBALS['_LANG']['gift_card_exchange'] . '_' . $page_title;
            	$args       = array('wsid' => '0');
            	$ur_here   .= ' <code>&gt;</code> <a href="gift_gard.php">' .
            			$GLOBALS['_LANG']['gift_card_exchange'] . '</a>';
            }
            //ecmoban模板堂 --zhuo end

            /* 其他的在这里补充 */
			//专题
            elseif ('topic' == $filename)
            {
                $page_title = $GLOBALS['_LANG']['project'] . '_' . $page_title;
                $args       = array('id' => '0');
                $ur_here   .= '<span class="arrow">></span>' . '<span> <a href="topic.php">'.
                               $GLOBALS['_LANG']['project'] . '</a>' . '</span>';
            }
        }
    }

    /* 处理最后一部分 */
    if (!empty($str)) {

        $filename_arr = array('group_buy', 'seckill', 'auction', 'auction_list', 'store_street', 'snatch', 'wholesale', 'exchange', 'single_sun', 'activity', 'presale', 'gift_gard', 'topic', 'article_cat', 'wholesale_cat');

        if(!in_array($filename, $filename_arr)){
            $action = get_page_action();
        }

        if (!empty($action)) {
            $page_title = $action;
        }

        $page_title = $str . '_' . $page_title;

        $str = !empty($url) ? "<a href='" . $url . "'>" . $str . "</a>" : $str;
        $ur_here .= '<span class="arrow">></span>' . ' <span class="finish">' . $str . '</span>';
		$data['foot'] = $str; //尾部 by wu
    }

    if ($strArr) {
        if (count($strArr) > 1) {

            foreach ($strArr as $key => $row) {
                $strArr[$key] = "<span>" . $row . "</span>";
            }

            $ur_here .= '<span class="arrow">></span>';
            $implode_str = implode(',', $strArr);
            $strArr = str_replace(',', "<span class='arrow'>></span>", $implode_str);
            $ur_here .= $strArr;
        } else {
            $implode_str = implode(',', $strArr);
            $strArr = '<span class="arrow">></span>' . $implode_str;
            $ur_here .= '<span>' . $strArr . '</span>';
        }
    }

	/* 页面名称 by wu */
	$GLOBALS['smarty']->assign('filename', $filename);

	/* 页面数据 by wu */
	$GLOBALS['smarty']->assign('data', $data);
	$GLOBALS['smarty']->assign('activity_title', $activity_title);

    /* 返回值 */
    return array('title' => $page_title, 'ur_here' => $ur_here);
}

/**
 * 获得指定分类的所有上级分类
 *
 * @access  public
 * @param   integer $cat    分类编号
 * @return  array
 */
function get_parent_cats($cat)
{
    if ($cat == 0)
    {
        return array();
    }

    $arr = $GLOBALS['db']->GetAll('SELECT cat_id, cat_name, parent_id FROM ' . $GLOBALS['ecs']->table('category'));

    if (empty($arr))
    {
        return array();
    }

    $index = 0;
    $cats  = array();

    while (1)
    {
        foreach ($arr AS $row)
        {
            if ($cat == $row['cat_id'])
            {
                $cat = $row['parent_id'];

                $cats[$index]['cat_id']   = $row['cat_id'];
                $cats[$index]['cat_name'] = $row['cat_name'];
                $cats[$index]['parent_id'] = $row['parent_id'];

                $sql = "SELECT c.cat_id, c.cat_name FROM " .$GLOBALS['ecs']->table('category'). " AS c WHERE parent_id = '" .$row['cat_id']. "' AND is_show = '1'";
                $cats[$index]['cat_tree'] = $GLOBALS['db']->getAll($sql);

                $index++;
                break;
            }
        }

        if ($index == 0 || $cat == 0)
        {
            break;
        }
    }

    return $cats;
}

/**
 * 获得指定分类的所有上级分类
 *
 * @access  public
 * @param   integer $cat    分类编号
 * @return  array
 */
function get_presale_parent_cats($cat)
{
    if ($cat == 0)
    {
        return array();
    }

    $arr = $GLOBALS['db']->GetAll('SELECT cat_id, cat_name, parent_id FROM ' . $GLOBALS['ecs']->table('presale_cat'));

    if (empty($arr))
    {
        return array();
    }

    $index = 0;
    $cats  = array();

    while (1)
    {
        foreach ($arr AS $row)
        {
            if ($cat == $row['cat_id'])
            {
                $cat = $row['parent_id'];

                $cats[$index]['cat_id']   = $row['cat_id'];
                $cats[$index]['cat_name'] = $row['cat_name'];
                $cats[$index]['parent_id'] = $row['parent_id'];

                $sql = "SELECT cat_id, cat_name FROM " .$GLOBALS['ecs']->table('presale_cat'). " AS c WHERE parent_id = '" .$row['cat_id']. "' ";
                $cats[$index]['cat_tree'] = $GLOBALS['db']->getAll($sql);

                $index++;
                break;
            }
        }

        if ($index == 0 || $cat == 0)
        {
            break;
        }
    }

    return $cats;
}



/**
 * 根据提供的数组编译成页面标题
 *
 * @access  public
 * @param   string  $type   类型
 * @param   array   $arr    分类数组
 * @return  string
 */
function build_pagetitle($arr, $type = 'category')
{
    $str = '';

    foreach ($arr AS $val)
    {
        $str .= htmlspecialchars($val['cat_name']) . '_';
    }

    return $str;
}

/**
 * 根据提供的数组编译成当前位置
 *
 * @access  public
 * @param   string  $type   类型
 * @param   array   $arr    分类数组
 * @return  void
 */
function build_urhere($arr, $type = 'category')
{
    krsort($arr);

    $str = '';
    foreach ($arr AS $val)
    {
        switch ($type)
        {
            case 'category':
            case 'brand':
                $args = array('cid' => $val['cat_id']);
                break;
            case 'article_cat':
                $args = array('acid' => $val['cat_id']);
                break;
        }

        $str .= ' <code>&gt;</code> <span>' . htmlspecialchars($val['cat_name']) . '</span>';
    }

    return $str;
}

/**
 * 获得指定页面的动态内容
 *
 * @access  public
 * @param   string  $tmp    模板名称
 * @return  void
 */
function assign_dynamic($tmp, $warehouse_id = 0, $area_id = 0)
{

    $sql = 'SELECT id, number, type, sort_order FROM ' . $GLOBALS['ecs']->table('template') .
        " WHERE filename = '$tmp' AND type > 0 AND remarks ='' AND theme='" . $GLOBALS['_CFG']['template'] . "'";
    $res = $GLOBALS['db']->getAll($sql);

   foreach ($res AS $row)
    {
        switch ($row['type'])
        {
            case 1:
                /* 分类下的商品 */
                //$GLOBALS['smarty']->assign('goods_cat_' . $row['id'], assign_cat_goods($row['id'], $row['number'], 'web', '', 'cat', $warehouse_id, $area_id, $row['sort_order'])); //ecmoban模板堂 --zhuo
            	$GLOBALS['smarty']->assign('goods_cat_' . $row['id'], array());
			break;
            case 2:
                /* 品牌的商品 */
                $brand_goods = assign_brand_goods($row['id'], $row['number'], 0, '', $warehouse_id, $area_id);

                $GLOBALS['smarty']->assign('brand_goods_' . $row['id'], $brand_goods['goods']);
                $GLOBALS['smarty']->assign('goods_brand_' . $row['id'], $brand_goods['brand']);
            break;
            case 3:
                /* 文章列表 */
                $cat_articles = assign_articles($row['id'], $row['number']);

                $GLOBALS['smarty']->assign('articles_cat_' . $row['id'], $cat_articles['cat']);
                $GLOBALS['smarty']->assign('articles_' . $row['id'], $cat_articles['arr']);
            break;
        }
    }
}

/**
 * 分配文章列表给smarty
 *
 * @access  public
 * @param   integer     $id     文章分类的编号
 * @param   integer     $num    文章数量
 * @return  array
 */
function assign_articles($id, $num)
{
    $sql = 'SELECT cat_name FROM ' . $GLOBALS['ecs']->table('article_cat') . " WHERE cat_id = '" . $id ."' ORDER BY sort_order ASC";

    $cat['id']       = $id;
    $cat['name']     = $GLOBALS['db']->getOne($sql);
    $cat['url']      = build_uri('article_cat', array('acid' => $id), $cat['name']);

    $articles['cat'] = $cat;
    $articles['arr'] = get_cat_articles($id, 1, $num);

    return $articles;
}

/**
 * 分配帮助信息
 *
 * @access  public
 * @return  array
 */
function get_shop_help()
{
    $sql = 'SELECT c.cat_id, c.cat_name, c.sort_order, a.article_id, a.title, a.file_url, a.open_type ' .
            'FROM ' .$GLOBALS['ecs']->table('article'). ' AS a ' .
            'LEFT JOIN ' .$GLOBALS['ecs']->table('article_cat'). ' AS c ' .
            'ON a.cat_id = c.cat_id WHERE c.cat_type = 5 AND a.is_open = 1 ' .
            'ORDER BY c.sort_order ASC, a.article_id';
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res AS $key => $row)
    {
        $arr[$row['cat_id']]['cat_id']                       = build_uri('article_cat', array('acid'=> $row['cat_id']), $row['cat_name']);
        $arr[$row['cat_id']]['cat_name']                     = $row['cat_name'];
        $arr[$row['cat_id']]['article'][$key]['article_id']  = $row['article_id'];
        $arr[$row['cat_id']]['article'][$key]['title']       = $row['title'];
        $arr[$row['cat_id']]['article'][$key]['short_title'] = $GLOBALS['_CFG']['article_title_length'] > 0 ?
            sub_str($row['title'], $GLOBALS['_CFG']['article_title_length']) : $row['title'];
        $arr[$row['cat_id']]['article'][$key]['url']         = $row['open_type'] != 1 ?
            build_uri('article', array('aid' => $row['article_id']), $row['title']) : trim($row['file_url']);
    }

	while(count($arr)>10)
	{
		array_pop($arr);
	}

    return $arr;
}

/**
 * 文章分类 ecmoban模板堂 --zhuo
 *
 * @access  public
 * @return  array
 */
function get_cat_list($cat_id = 0, $type = 0) {

    if ($type == 1) {
        $where = " parent_id = '$cat_id' ";
    } else {
        $where = " cat_id = '$cat_id' ";
    }

    $sql = 'SELECT cat_id, cat_name, sort_order, parent_id ' .
            'FROM ' . $GLOBALS['ecs']->table('article_cat') . " WHERE " . $where .
            'ORDER BY sort_order ASC';

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res AS $key => $row) {
        $arr[$key]['cat_id'] = $row['cat_id'];
        $arr[$key]['cat_name'] = $row['cat_name'];
        $arr[$key]['parent_id'] = $row['parent_id'];
        $arr[$key]['url'] = build_uri('article_cat', array('acid' => $row['cat_id']), $row['cat_name']);

        $child_list = get_cat_list($row['cat_id'], 1);
        $arr[$key]['child_list'] = $child_list;
    }

    return $arr;
}

/**
 * 创建分页信息
 *
 * @access  public
 * @param   string  $app            程序名称，如category
 * @param   string  $cat            分类ID
 * @param   string  $record_count   记录总数
 * @param   string  $size           每页记录数
 * @param   string  $sort           排序类型
 * @param   string  $order          排序顺序
 * @param   string  $page           当前页
 * @param   string  $keywords       查询关键字
 * @param   string  $brand          品牌
 * @param   string  $price_min      最小价格
 * @param   string  $price_max      最高价格
 * @return  void
 */
function assign_pager($app, $cat, $record_count, $size, $sort, $order, $page = 1,
                        $keywords = '', $brand = 0, $price_min = 0, $price_max = 0, $display_type = 'list', $filter_attr='', $url_format='', $sch_array='', $merchant_id = 0, $keyword = '', $ubrand = '', $act = '',$ship='', $self='', $have='', $mbid = 0)
{
    $sch = array('keywords'  => $keywords,
                 'sort'      => $sort,
                 'order'     => $order,
                 'cat'       => $cat,
                 'brand'     => $brand,
                 'price_min' => $price_min,
                 'price_max' => $price_max,
                 'filter_attr'=>$filter_attr,
                 'display'   => $display_type,
				 'urid'   => $merchant_id,
				 'keyword'   => $keyword,
				 'ubrand'   => $ubrand
        );

    $page = intval($page);
    if ($page < 1)
    {
        $page = 1;
    }

    $page_count = $record_count > 0 ? intval(ceil($record_count / $size)) : 1;

    $pager['page']         = $page;
    $pager['size']         = $size;
    $pager['sort']         = $sort;
    $pager['order']        = $order;
    $pager['record_count'] = $record_count;
    $pager['page_count']   = $page_count;
    $pager['display']      = $display_type;
    $pager['ship']         = $ship;
    $pager['self']         = $self;
    $pager['have']         = $have;
    $pager['mbid']         = $mbid;

    switch ($app)
    {
        case 'category':
            $uri_args = array('cid' => $cat, 'bid' => $brand, 'ubrand' => $ubrand, 'price_min'=>$price_min, 'price_max'=>$price_max, 'filter_attr'=>$filter_attr, 'sort' => $sort, 'order' => $order, 'display' => $display_type, 'ship' => $ship, 'self' => $self, 'have' => $have);
			break;
        case 'merchants_store': //ecmoban模板堂 --zhuo
            $uri_args = array('cid' => $cat, 'urid' => $merchant_id, 'bid' => $brand, 'keyword' => $keyword, 'price_min'=>$price_min, 'price_max'=>$price_max, 'filter_attr'=>$filter_attr, 'sort' => $sort, 'order' => $order, 'display' => $display_type);
            break;
		case 'merchants_store_shop': //ecmoban模板堂 --zhuo
            $uri_args = array('cid' => $cat, 'urid' => $merchant_id, 'bid' => $brand, 'keyword' => $keyword, 'price_min'=>$price_min, 'price_max'=>$price_max, 'filter_attr'=>$filter_attr, 'sort' => $sort, 'order' => $order, 'display' => $display_type);
            break;
        case 'article_cat':
            $uri_args = array('acid' => $cat, 'sort' => $sort, 'order' => $order);
            break;
        case 'brand':
            $uri_args = array('cid' => $cat, 'bid' => $brand, 'mbid' => $mbid, 'price_min'=>$price_min, 'price_max'=>$price_max, 'sort' => $sort, 'order' => $order, 'display' => $display_type, 'ship'=>$ship, 'self' => $self, 'have' => $have);
            break;
        case 'brandn':
            $uri_args = array('cid' => $cat, 'bid' => $brand, 'sort' => $sort, 'order' => $order, 'display' => $display_type, 'act' => $act);
            break;
        case 'search':
            $uri_args = array('cid' => $cat, 'bid' => $brand, 'sort' => $sort, 'order' => $order);
            break;
        case 'exchange':
            $uri_args = array('cid' => $cat, 'integral_min'=>$price_min, 'integral_max'=>$price_max, 'sort' => $sort, 'order' => $order, 'display' => $display_type);
            break;
		case 'history_list':
            $uri_args = array('cid' => $cat);//ecmoban模板堂 --zhuo   浏览列表插件
            break;

        //ecmoban模板堂 zhuo start
        case 'gift_gard':
            $uri_args = array('cid' => $cat, 'sort' => $sort, 'order' => $order);
            break;
        //ecmoban模板堂 zhuo end
    }
    /* 分页样式 */
    $pager['styleid'] = isset($GLOBALS['_CFG']['page_style'])? intval($GLOBALS['_CFG']['page_style']) : 0;

    $page_prev  = ($page > 1) ? $page - 1 : 1;
    $page_next  = ($page < $page_count) ? $page + 1 : $page_count;
    if ($pager['styleid'] == 0)
    {
        if (!empty($url_format))
        {
            $pager['page_first'] = $url_format . 1;
            $pager['page_prev']  = $url_format . $page_prev;
            $pager['page_next']  = $url_format . $page_next;
            $pager['page_last']  = $url_format . $page_count;
        }
        else
        {
            $pager['page_first'] = build_uri($app, $uri_args, '', 1, $keywords);
            $pager['page_prev']  = build_uri($app, $uri_args, '', $page_prev, $keywords);
            $pager['page_next']  = build_uri($app, $uri_args, '', $page_next, $keywords);
            $pager['page_last']  = build_uri($app, $uri_args, '', $page_count, $keywords);
        }
        $pager['array']      = array();

        for ($i = 1; $i <= $page_count; $i++)
        {
            $pager['array'][$i] = $i;
        }
    }
    else
    {
        $_pagenum = 10;     // 显示的页码
        $_offset = 2;       // 当前页偏移值
        $_from = $_to = 0;  // 开始页, 结束页
        if($_pagenum > $page_count)
        {
            $_from = 1;
            $_to = $page_count;
        }
        else
        {
            $_from = $page - $_offset;
            $_to = $_from + $_pagenum - 1;
            if($_from < 1)
            {
                $_to = $page + 1 - $_from;
                $_from = 1;
                if($_to - $_from < $_pagenum)
                {
                    $_to = $_pagenum;
                }
            }
            elseif($_to > $page_count)
            {
                $_from = $page_count - $_pagenum + 1;
                $_to = $page_count;
            }
        }
        if (!empty($url_format))
        {
            $pager['page_first'] = ($page - $_offset > 1 && $_pagenum < $page_count) ? $url_format . 1 : '';
            $pager['page_prev']  = ($page > 1) ? $url_format . $page_prev : '';
            $pager['page_next']  = ($page < $page_count) ? $url_format . $page_next : '';
            $pager['page_last']  = ($_to < $page_count) ? $url_format . $page_count : '';
            $pager['page_kbd']  = ($_pagenum < $page_count) ? true : false;
            $pager['page_number'] = array();
            for ($i=$_from;$i<=$_to;++$i)
            {
                $pager['page_number'][$i] = $url_format . $i;
            }
        }
        else
        {
            $pager['page_first'] = ($page - $_offset > 1 && $_pagenum < $page_count) ? build_uri($app, $uri_args, '', 1, $keywords) : '';
            $pager['page_prev']  = ($page > 1) ? build_uri($app, $uri_args, '', $page_prev, $keywords) : '';
            $pager['page_next']  = ($page < $page_count) ? build_uri($app, $uri_args, '', $page_next, $keywords) : '';
            $pager['page_last']  = ($_to < $page_count) ? build_uri($app, $uri_args, '', $page_count, $keywords) : '';
            $pager['page_kbd']  = ($_pagenum < $page_count) ? true : false;
            $pager['page_number'] = array();
            for ($i=$_from;$i<=$_to;++$i)
            {
                $pager['page_number'][$i] = build_uri($app, $uri_args, '', $i, $keywords);
            }
        }
    }

    if (!empty($sch_array))
    {
        $pager['search'] = $sch_array;
    }
    else
    {
        $pager['search']['category'] = $cat;
        foreach ($sch AS $key => $row)
        {
            $pager['search'][$key] = $row;
        }
    }

    $GLOBALS['smarty']->assign('pager', $pager);
}

/**
 *  生成给pager.lbi赋值的数组
 *
 * @access  public
 * @param   string      $url        分页的链接地址(必须是带有参数的地址，若不是可以伪造一个无用参数)
 * @param   array       $param      链接参数 key为参数名，value为参数值
 * @param   int         $record     记录总数量
 * @param   int         $page       当前页数
 * @param   int         $size       每页大小
 *
 * @return  array       $pager
 */
function get_pager($url, $param, $record_count, $page = 1, $size = 10)
{
    $size = intval($size);
    if ($size < 1)
    {
        $size = 10;
    }

    $page = intval($page);
    if ($page < 1)
    {
        $page = 1;
    }

    $record_count = intval($record_count);

    $page_count = $record_count > 0 ? intval(ceil($record_count / $size)) : 1;
    if ($page > $page_count)
    {
        $page = $page_count;
    }
    /* 分页样式 */
    $pager['styleid'] = isset($GLOBALS['_CFG']['page_style'])? intval($GLOBALS['_CFG']['page_style']) : 0;

    $page_prev  = ($page > 1) ? $page - 1 : 1;
    $page_next  = ($page < $page_count) ? $page + 1 : $page_count;

    /* 将参数合成url字串 */
    $param_url = '?';
    foreach ($param AS $key => $value)
    {
        $param_url .= $key . '=' . $value . '&';
    }

    $pager['url']          = $url;
    $pager['start']        = ($page -1) * $size;
    $pager['page']         = $page;
    $pager['size']         = $size;
    $pager['record_count'] = $record_count;
    $pager['page_count']   = $page_count;

    if ($pager['styleid'] == 0)
    {
        $pager['page_first']   = $url . $param_url . 'page=1';
        $pager['page_prev']    = $url . $param_url . 'page=' . $page_prev;
        $pager['page_next']    = $url . $param_url . 'page=' . $page_next;
        $pager['page_last']    = $url . $param_url . 'page=' . $page_count;
        $pager['array']  = array();
        for ($i = 1; $i <= $page_count; $i++)
        {
            $pager['array'][$i] = $i;
        }
    }
    else
    {
        $_pagenum = 10;     // 显示的页码
        $_offset = 2;       // 当前页偏移值
        $_from = $_to = 0;  // 开始页, 结束页
        if($_pagenum > $page_count)
        {
            $_from = 1;
            $_to = $page_count;
        }
        else
        {
            $_from = $page - $_offset;
            $_to = $_from + $_pagenum - 1;
            if($_from < 1)
            {
                $_to = $page + 1 - $_from;
                $_from = 1;
                if($_to - $_from < $_pagenum)
                {
                    $_to = $_pagenum;
                }
            }
            elseif($_to > $page_count)
            {
                $_from = $page_count - $_pagenum + 1;
                $_to = $page_count;
            }
        }
        $url_format = $url . $param_url . 'page=';
        $pager['page_first'] = ($page - $_offset > 1 && $_pagenum < $page_count) ? $url_format . 1 : '';
        $pager['page_prev']  = ($page > 1) ? $url_format . $page_prev : '';
        $pager['page_next']  = ($page < $page_count) ? $url_format . $page_next : '';
        $pager['page_last']  = ($_to < $page_count) ? $url_format . $page_count : '';
        $pager['page_kbd']  = ($_pagenum < $page_count) ? true : false;
        $pager['page_number'] = array();
        for ($i=$_from;$i<=$_to;++$i)
        {
            $pager['page_number'][$i] = $url_format . $i;
        }
    }
    $pager['search'] = $param;

    return $pager;
}

/**
 * 调用调查内容
 *
 * @access  public
 * @param   integer $id   调查的编号
 * @return  array
 */
function get_vote($id = '')
{
    /* 随机取得一个调查的主题 */
    if (empty($id))
    {
        $time = gmtime();
        $sql = 'SELECT vote_id, vote_name, can_multi, vote_count, RAND() AS rnd' .
               ' FROM ' . $GLOBALS['ecs']->table('vote') .
               " WHERE start_time <= '$time' AND end_time >= '$time' ".
               ' ORDER BY rnd LIMIT 1';
    }
    else
    {
        $sql = 'SELECT vote_id, vote_name, can_multi, vote_count' .
               ' FROM ' . $GLOBALS['ecs']->table('vote').
               " WHERE vote_id = '$id'";
    }

    $vote_arr = $GLOBALS['db']->getRow($sql);

    if ($vote_arr !== false && !empty($vote_arr))
    {
        /* 通过调查的ID,查询调查选项 */
        $sql_option = 'SELECT v.*, o.option_id, o.vote_id, o.option_name, o.option_count ' .
                      'FROM ' . $GLOBALS['ecs']->table('vote') . ' AS v, ' .
                            $GLOBALS['ecs']->table('vote_option') . ' AS o ' .
                      "WHERE o.vote_id = v.vote_id AND o.vote_id = '$vote_arr[vote_id]' ORDER BY o.option_order ASC, o.option_id DESC";
        $res = $GLOBALS['db']->getAll($sql_option);

        /* 总票数 */
        $sql = 'SELECT SUM(option_count) AS all_option FROM ' . $GLOBALS['ecs']->table('vote_option') .
               " WHERE vote_id = '" . $vote_arr['vote_id'] . "' GROUP BY vote_id";
        $option_num = $GLOBALS['db']->getOne($sql);

        $arr = array();
        $count = 100;
        foreach ($res AS $idx => $row)
        {
            if ($option_num > 0 && $idx == count($res) - 1)
            {
                $percent = $count;
            }
            else
            {
                $percent = ($row['vote_count'] > 0 && $option_num > 0) ? round(($row['option_count'] / $option_num) * 100) : 0;

                $count -= $percent;
            }
            $arr[$row['vote_id']]['options'][$row['option_id']]['percent'] = $percent;

            $arr[$row['vote_id']]['vote_id']    = $row['vote_id'];
            $arr[$row['vote_id']]['vote_name']  = $row['vote_name'];
            $arr[$row['vote_id']]['can_multi']  = $row['can_multi'];
            $arr[$row['vote_id']]['vote_count'] = $row['vote_count'];

            $arr[$row['vote_id']]['options'][$row['option_id']]['option_id']    = $row['option_id'];
            $arr[$row['vote_id']]['options'][$row['option_id']]['option_name']  = $row['option_name'];
            $arr[$row['vote_id']]['options'][$row['option_id']]['option_count'] = $row['option_count'];
        }

        $vote_arr['vote_id'] = (!empty($vote_arr['vote_id'])) ? $vote_arr['vote_id'] : '';

        $vote = array('id' => $vote_arr['vote_id'], 'content' => $arr);

        return $vote;
    }
}

/**
 * 获得浏览器名称和版本
 *
 * @access  public
 * @return  string
 */
function get_user_browser()
{
    if (empty($_SERVER['HTTP_USER_AGENT']))
    {
        return '';
    }

    $agent       = $_SERVER['HTTP_USER_AGENT'];
    $browser     = '';
    $browser_ver = '';

    if (preg_match('/MSIE\s([^\s|;]+)/i', $agent, $regs))
    {
        $browser     = 'Internet Explorer';
        $browser_ver = $regs[1];
    }
    elseif (preg_match('/FireFox\/([^\s]+)/i', $agent, $regs))
    {
        $browser     = 'FireFox';
        $browser_ver = $regs[1];
    }
    elseif (preg_match('/Maxthon/i', $agent, $regs))
    {
        $browser     = '(Internet Explorer ' .$browser_ver. ') Maxthon';
        $browser_ver = '';
    }
    elseif (preg_match('/Opera[\s|\/]([^\s]+)/i', $agent, $regs))
    {
        $browser     = 'Opera';
        $browser_ver = $regs[1];
    }
    elseif (preg_match('/OmniWeb\/(v*)([^\s|;]+)/i', $agent, $regs))
    {
        $browser     = 'OmniWeb';
        $browser_ver = $regs[2];
    }
    elseif (preg_match('/Netscape([\d]*)\/([^\s]+)/i', $agent, $regs))
    {
        $browser     = 'Netscape';
        $browser_ver = $regs[2];
    }
    elseif (preg_match('/safari\/([^\s]+)/i', $agent, $regs))
    {
        $browser     = 'Safari';
        $browser_ver = $regs[1];
    }
    elseif (preg_match('/NetCaptor\s([^\s|;]+)/i', $agent, $regs))
    {
        $browser     = '(Internet Explorer ' .$browser_ver. ') NetCaptor';
        $browser_ver = $regs[1];
    }
    elseif (preg_match('/Lynx\/([^\s]+)/i', $agent, $regs))
    {
        $browser     = 'Lynx';
        $browser_ver = $regs[1];
    }

    if (!empty($browser))
    {
       return addslashes($browser . ' ' . $browser_ver);
    }
    else
    {
        return 'Unknow browser';
    }
}

/**
 * 判断是否为搜索引擎蜘蛛
 *
 * @access  public
 * @return  string
 */
function is_spider($record = true)
{
    static $spider = NULL;

    if ($spider !== NULL)
    {
        return $spider;
    }

    if (empty($_SERVER['HTTP_USER_AGENT']))
    {
        $spider = '';

        return '';
    }

    $searchengine_bot = array(
        'googlebot',
        'mediapartners-google',
        'baiduspider+',
        'msnbot',
        'yodaobot',
        'yahoo! slurp;',
        'yahoo! slurp china;',
        'iaskspider',
        'sogou web spider',
        'sogou push spider'
    );

    $searchengine_name = array(
        'GOOGLE',
        'GOOGLE ADSENSE',
        'BAIDU',
        'MSN',
        'YODAO',
        'YAHOO',
        'Yahoo China',
        'IASK',
        'SOGOU',
        'SOGOU'
    );

    $spider = strtolower($_SERVER['HTTP_USER_AGENT']);

    foreach ($searchengine_bot AS $key => $value)
    {
        if (strpos($spider, $value) !== false)
        {
            $spider = $searchengine_name[$key];

            if ($record === true)
            {
                $GLOBALS['db']->autoReplace($GLOBALS['ecs']->table('searchengine'), array('date' => local_date('Y-m-d'), 'searchengine' => $spider, 'count' => 1), array('count' => 1));
            }

            return $spider;
        }
    }

    $spider = '';

    return '';
}

/**
 * 获得客户端的操作系统
 *
 * @access  private
 * @return  void
 */
function get_os()
{
    if (empty($_SERVER['HTTP_USER_AGENT']))
    {
        return 'Unknown';
    }

    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $os    = '';

    if (strpos($agent, 'win') !== false)
    {
        if (strpos($agent, 'nt 5.1') !== false)
        {
            $os = 'Windows XP';
        }
        elseif (strpos($agent, 'nt 5.2') !== false)
        {
            $os = 'Windows 2003';
        }
        elseif (strpos($agent, 'nt 5.0') !== false)
        {
            $os = 'Windows 2000';
        }
        elseif (strpos($agent, 'nt 6.0') !== false)
        {
            $os = 'Windows Vista';
        }
        elseif (strpos($agent, 'nt') !== false)
        {
            $os = 'Windows NT';
        }
        elseif (strpos($agent, 'win 9x') !== false && strpos($agent, '4.90') !== false)
        {
            $os = 'Windows ME';
        }
        elseif (strpos($agent, '98') !== false)
        {
            $os = 'Windows 98';
        }
        elseif (strpos($agent, '95') !== false)
        {
            $os = 'Windows 95';
        }
        elseif (strpos($agent, '32') !== false)
        {
            $os = 'Windows 32';
        }
        elseif (strpos($agent, 'ce') !== false)
        {
            $os = 'Windows CE';
        }
    }
    elseif (strpos($agent, 'linux') !== false)
    {
        $os = 'Linux';
    }
    elseif (strpos($agent, 'unix') !== false)
    {
        $os = 'Unix';
    }
    elseif (strpos($agent, 'sun') !== false && strpos($agent, 'os') !== false)
    {
        $os = 'SunOS';
    }
    elseif (strpos($agent, 'ibm') !== false && strpos($agent, 'os') !== false)
    {
        $os = 'IBM OS/2';
    }
    elseif (strpos($agent, 'mac') !== false && strpos($agent, 'pc') !== false)
    {
        $os = 'Macintosh';
    }
    elseif (strpos($agent, 'powerpc') !== false)
    {
        $os = 'PowerPC';
    }
    elseif (strpos($agent, 'aix') !== false)
    {
        $os = 'AIX';
    }
    elseif (strpos($agent, 'hpux') !== false)
    {
        $os = 'HPUX';
    }
    elseif (strpos($agent, 'netbsd') !== false)
    {
        $os = 'NetBSD';
    }
    elseif (strpos($agent, 'bsd') !== false)
    {
        $os = 'BSD';
    }
    elseif (strpos($agent, 'osf1') !== false)
    {
        $os = 'OSF1';
    }
    elseif (strpos($agent, 'irix') !== false)
    {
        $os = 'IRIX';
    }
    elseif (strpos($agent, 'freebsd') !== false)
    {
        $os = 'FreeBSD';
    }
    elseif (strpos($agent, 'teleport') !== false)
    {
        $os = 'teleport';
    }
    elseif (strpos($agent, 'flashget') !== false)
    {
        $os = 'flashget';
    }
    elseif (strpos($agent, 'webzip') !== false)
    {
        $os = 'webzip';
    }
    elseif (strpos($agent, 'offline') !== false)
    {
        $os = 'offline';
    }
    else
    {
        $os = 'Unknown';
    }

    return $os;
}

/**
 * 统计访问信息
 *
 * @access  public
 * @return  void
 */
function visit_stats()
{
    if (isset($GLOBALS['_CFG']['visit_stats']) && $GLOBALS['_CFG']['visit_stats'] == 'off')
    {
        return;
    }
    $time = gmtime();
    /* 检查客户端是否存在访问统计的cookie */
    $visit_times = (!empty($_COOKIE['ECS']['visit_times'])) ? intval($_COOKIE['ECS']['visit_times']) + 1 : 1;
    setcookie('ECS[visit_times]', $visit_times, $time + 86400 * 365, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);

    $browser  = get_user_browser();
    $os       = get_os();
    $ip       = real_ip();
    //$area     = ecs_geoip($ip);
	//修改获取地区方式 by wu
	$area_info = get_ip_area_name();
	$area = $area_info['area_name'];

    /* 语言 */
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']))
    {
        $pos  = strpos($_SERVER['HTTP_ACCEPT_LANGUAGE'], ';');
        $lang = addslashes(($pos !== false) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, $pos) : $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }
    else
    {
        $lang = '';
    }

    /* 来源 */
    if (!empty($_SERVER['HTTP_REFERER']) && strlen($_SERVER['HTTP_REFERER']) > 9)
    {
        $pos = strpos($_SERVER['HTTP_REFERER'], '/', 9);
        if ($pos !== false)
        {
            $domain = substr($_SERVER['HTTP_REFERER'], 0, $pos);
            $path   = substr($_SERVER['HTTP_REFERER'], $pos);

            /* 来源关键字 */
            if (!empty($domain) && !empty($path))
            {
                save_searchengine_keyword($domain, $path);
            }
        }
        else
        {
            $domain = $path = '';
        }
    }
    else
    {
        $domain = $path = '';
    }

    $sql = 'INSERT INTO ' . $GLOBALS['ecs']->table('stats') . ' ( ' .
                'ip_address, visit_times, browser, system, language, area, ' .
                'referer_domain, referer_path, access_url, access_time' .
            ') VALUES (' .
                "'$ip', '$visit_times', '$browser', '$os', '$lang', '$area', ".
                "'" . addslashes($domain) ."', '" . addslashes($path) ."', '" . addslashes(PHP_SELF) ."', '" . $time . "')";
    $GLOBALS['db']->query($sql);
}

/**
 * 保存搜索引擎关键字
 *
 * @access  public
 * @return  void
 */
function save_searchengine_keyword($domain, $path)
{
    if (strpos($domain, 'google.com.tw') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'GOOGLE TAIWAN';
        $keywords = urldecode($regs[1]); // google taiwan
    }
    if (strpos($domain, 'google.cn') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'GOOGLE CHINA';
        $keywords = urldecode($regs[1]); // google china
    }
    if (strpos($domain, 'google.com') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'GOOGLE';
        $keywords = urldecode($regs[1]); // google
    }
    elseif (strpos($domain, 'baidu.') !== false && preg_match('/wd=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'BAIDU';
        $keywords = urldecode($regs[1]); // baidu
    }
    elseif (strpos($domain, 'baidu.') !== false && preg_match('/word=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'BAIDU';
        $keywords = urldecode($regs[1]); // baidu
    }
    elseif (strpos($domain, '114.vnet.cn') !== false && preg_match('/kw=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'CT114';
        $keywords = urldecode($regs[1]); // ct114
    }
    elseif (strpos($domain, 'iask.com') !== false && preg_match('/k=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'IASK';
        $keywords = urldecode($regs[1]); // iask
    }
    elseif (strpos($domain, 'soso.com') !== false && preg_match('/w=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'SOSO';
        $keywords = urldecode($regs[1]); // soso
    }
    elseif (strpos($domain, 'sogou.com') !== false && preg_match('/query=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'SOGOU';
        $keywords = urldecode($regs[1]); // sogou
    }
    elseif (strpos($domain, 'so.163.com') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'NETEASE';
        $keywords = urldecode($regs[1]); // netease
    }
    elseif (strpos($domain, 'yodao.com') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'YODAO';
        $keywords = urldecode($regs[1]); // yodao
    }
    elseif (strpos($domain, 'zhongsou.com') !== false && preg_match('/word=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'ZHONGSOU';
        $keywords = urldecode($regs[1]); // zhongsou
    }
    elseif (strpos($domain, 'search.tom.com') !== false && preg_match('/w=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'TOM';
        $keywords = urldecode($regs[1]); // tom
    }
    elseif (strpos($domain, 'live.com') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'MSLIVE';
        $keywords = urldecode($regs[1]); // MSLIVE
    }
    elseif (strpos($domain, 'tw.search.yahoo.com') !== false && preg_match('/p=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'YAHOO TAIWAN';
        $keywords = urldecode($regs[1]); // yahoo taiwan
    }
    elseif (strpos($domain, 'cn.yahoo.') !== false && preg_match('/p=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'YAHOO CHINA';
        $keywords = urldecode($regs[1]); // yahoo china
    }
    elseif (strpos($domain, 'yahoo.') !== false && preg_match('/p=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'YAHOO';
        $keywords = urldecode($regs[1]); // yahoo
    }
    elseif (strpos($domain, 'msn.com.tw') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'MSN TAIWAN';
        $keywords = urldecode($regs[1]); // msn taiwan
    }
    elseif (strpos($domain, 'msn.com.cn') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'MSN CHINA';
        $keywords = urldecode($regs[1]); // msn china
    }
    elseif (strpos($domain, 'msn.com') !== false && preg_match('/q=([^&]*)/i', $path, $regs))
    {
        $searchengine = 'MSN';
        $keywords = urldecode($regs[1]); // msn
    }

    if (!empty($keywords))
    {
        $gb_search = array('YAHOO CHINA', 'TOM', 'ZHONGSOU', 'NETEASE', 'SOGOU', 'SOSO', 'IASK', 'CT114', 'BAIDU');
        if (EC_CHARSET == 'utf-8' && in_array($searchengine, $gb_search))
        {
            $keywords = ecs_iconv('GBK', 'UTF8', $keywords);
        }
        if (EC_CHARSET == 'gbk' && !in_array($searchengine, $gb_search))
        {
            $keywords = ecs_iconv('UTF8', 'GBK', $keywords);
        }

        $GLOBALS['db']->autoReplace($GLOBALS['ecs']->table('keywords'), array('date' => local_date('Y-m-d'), 'searchengine' => $searchengine, 'keyword' => addslashes($keywords), 'count' => 1), array('count' => 1));
    }
}

/**
 * 获得指定用户、商品的所有标记
 *
 * @access  public
 * @param   integer $goods_id
 * @param   integer $user_id
 * @return  array
 */
function get_tags($goods_id = 0, $user_id = 0)
{
    $where = '';
    if ($goods_id > 0)
    {
        $where .= " goods_id = '$goods_id'";
    }

    if ($user_id > 0)
    {
        if ($goods_id > 0)
        {
            $where .= " AND";
        }
        $where .= " user_id = '$user_id'";
    }

    if ($where > '')
    {
        $where = ' WHERE' . $where;
    }

    $sql = 'SELECT tag_id, user_id, tag_words, COUNT(tag_id) AS tag_count' .
            ' FROM ' . $GLOBALS['ecs']->table('tag') .
            "$where GROUP BY tag_words";
    $arr = $GLOBALS['db']->getAll($sql);

    return $arr;
}

/**
 * 获取指定主题某个模板的主题的动态模块
 *
 * @access  public
 * @param   string       $theme    模板主题
 * @param   string       $tmp      模板名称
 *
 * @return array()
 */
function get_dyna_libs($theme, $tmp)
{
    $tmp_arr = explode('.', $tmp);
    $ext = end($tmp_arr);
    $tmp = basename($tmp,".$ext");
    $sql = 'SELECT region, library, sort_order, id, number, type' .
            ' FROM ' . $GLOBALS['ecs']->table('template') .
            " WHERE theme = '$theme' AND filename = '" . $tmp . "' AND type > 0 AND remarks=''".
            ' ORDER BY region, library, sort_order';
    $res = $GLOBALS['db']->getAll($sql);

    $dyna_libs = array();
    foreach ($res AS $row)
    {
        $dyna_libs[$row['region']][$row['library']][] = array(
            'id'     => $row['id'],
            'number' => $row['number'],
            'type'   => $row['type']
        );
    }

    return $dyna_libs;
}

/**
 * 替换动态模块
 *
 * @access  public
 * @param   string       $matches    匹配内容
 *
 * @return string        结果
 */
function dyna_libs_replace($matches)
{
    $key = '/' . $matches[1];

    if ($row = array_shift($GLOBALS['libs'][$key]))
    {
        $str = '';
        switch($row['type'])
        {
            case 1:
                // 分类的商品
                $str = '{assign var="cat_goods" value=$cat_goods_' .$row['id']. '}{assign var="goods_cat" value=$goods_cat_' .$row['id']. '}';
                break;
            case 2:
                // 品牌的商品
                $str = '{assign var="brand_goods" value=$brand_goods_' .$row['id']. '}{assign var="goods_brand" value=$goods_brand_' .$row['id']. '}';
                break;
            case 3:
                // 文章列表
                $str = '{assign var="articles" value=$articles_' .$row['id']. '}{assign var="articles_cat" value=$articles_cat_' .$row['id']. '}';
                break;
            case 4:
                //广告位
                $str = '{assign var="ads_id" value=' . $row['id'] . '}{assign var="ads_num" value=' . $row['number'] . '}';
                break;
        }
        return $str . $matches[0];
    }
    else
    {
        return $matches[0];
    }
}

/**
 * 处理上传文件，并返回上传图片名(上传失败时返回图片名为空）
 *
 * @access  public
 * @param array     $upload     $_FILES 数组
 * @param array     $type       图片所属类别，即data目录下的文件夹名
 *
 * @return string               上传图片名
 */
function upload_file($upload, $type)
{
    if (!empty($upload['tmp_name']))
    {
        $ftype = check_file_type($upload['tmp_name'], $upload['name'], '|png|jpg|jpeg|gif|doc|xls|txt|zip|ppt|pdf|rar|docx|xlsx|pptx|');
        if (!empty($ftype))
        {
            $name = date('Ymd');
            for ($i = 0; $i < 6; $i++)
            {
                $name .= chr(mt_rand(97, 122));
            }

            $name = $_SESSION['user_id'] . '_' . $name . '.' . $ftype;

            $target = ROOT_PATH . DATA_DIR . '/' . $type . '/' . $name;
            if (!move_upload_file($upload['tmp_name'], $target))
            {
                $GLOBALS['err']->add($GLOBALS['_LANG']['upload_file_error'], 1);

                return false;
            }
            else
            {
                return $name;
            }
        }
        else
        {
            $GLOBALS['err']->add($GLOBALS['_LANG']['upload_file_type'], 1);

            return false;
        }
    }
    else
    {
        $GLOBALS['err']->add($GLOBALS['_LANG']['upload_file_error']);
        return false;
    }
}

/**
 * 显示一个提示信息
 *
 * @access  public
 * @param   string  $content
 * @param   string  $link
 * @param   string  $href
 * @param   string  $type               信息类型：warning, error, info
 * @param   string  $auto_redirect      是否自动跳转
 * @return  void
 */
function show_message($content, $links = '', $hrefs = '', $type = 'info', $auto_redirect = true)
{
    assign_template();

    $msg['content'] = $content;
    if (is_array($links) && is_array($hrefs))
    {
        if (!empty($links) && count($links) == count($hrefs))
        {
            foreach($links as $key =>$val)
            {
                $msg['url_info'][$val] = $hrefs[$key];
            }
            $msg['back_url'] = $hrefs['0'];
        }
    }
    else
    {
        $link   = empty($links) ? $GLOBALS['_LANG']['back_up_page'] : $links;
        $href    = empty($hrefs) ? 'javascript:history.back()'       : $hrefs;
        $msg['url_info'][$link] = $href;
        $msg['back_url'] = $href;
    }

    $msg['type']    = $type;
    $position = assign_ur_here(0, $GLOBALS['_LANG']['sys_msg']);
    $GLOBALS['smarty']->assign('page_title', $position['title']);   // 页面标题
    $GLOBALS['smarty']->assign('ur_here',    $position['ur_here']); // 当前位置

    if (is_null($GLOBALS['smarty']->get_template_vars('helps')))
    {
        $GLOBALS['smarty']->assign('helps', get_shop_help()); // 网店帮助
    }

    if (defined('THEME_EXTENSION')) {
        $categories_pro = get_category_tree_leve_one();
        $GLOBALS['smarty']->assign('categories_pro', $categories_pro); // 分类树加强版
    }

    $GLOBALS['smarty']->assign('auto_redirect', $auto_redirect);
    $GLOBALS['smarty']->assign('message', $msg);
    $GLOBALS['smarty']->display('message.dwt');

    exit;
}

/**
 * 将一个形如+10, 10, -10, 10%的字串转换为相应数字，并返回操作符号
 *
 * @access  public
 * @param   string      str     要格式化的数据
 * @param   char        operate 操作符号，只能返回‘+’或‘*’;
 * @return  float       value   浮点数
 */
function parse_rate_value($str, &$operate)
{
    $operate = '+';
    $is_rate = false;

    $str = trim($str);
    if (empty($str))
    {
        return 0;
    }
    if ($str[strlen($str) - 1] == '%')
    {
        $value = floatval($str);
        if ($value > 0)
        {
            $operate = '*';

            return $value / 100;
        }
        else
        {
            return 0;
        }
    }
    else
    {
        return floatval($str);
    }
}

/**
 * 重新计算进货单中的商品价格：目的是当用户登录时享受会员价格，当用户退出登录时不享受会员价格
 * 如果商品有促销，价格不变
 *
 * @access  public
 * @return  void
 */
function recalculate_price() {

    $user_id = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

    if ($user_id > 0) {
        /* 取得有可能改变价格的商品：除配件和赠品之外的商品 */ //去掉 AND c.parent_id = 0 ecmoban模板堂 --zhuo
        $sql = 'SELECT c.rec_id, c.goods_id, c.goods_attr_id, c.ru_id, c.warehouse_id, c.area_id, g.promote_price, g.promote_start_date, c.goods_number, c.goods_price as c_price, ' .
                "c.extension_code, g.promote_end_date, IFNULL(mp.user_price, g.shop_price * '$_SESSION[discount]') AS member_price " .
                'FROM ' . $GLOBALS['ecs']->table('cart') . ' AS c ' .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' AS g ON g.goods_id = c.goods_id ' .
                "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '" . $_SESSION['user_rank'] . "' " .
                "WHERE c.session_id = '" . real_cart_mac_ip() . "' AND c.is_gift = 0 AND c.goods_id > 0 " .
                "AND c.rec_type = '" . CART_GENERAL_GOODS . "'"; // AND c.extension_code <> 'package_buy'

        $res = $GLOBALS['db']->getAll($sql);

        if ($GLOBALS['_CFG']['add_shop_price'] == 1) {
            $add_tocart = 1;
        } else {
            $add_tocart = 0;
        }

        $nowTime = gmtime();
        foreach ($res AS $row) {
            $attr_id = empty($row['goods_attr_id']) ? array() : explode(',', $row['goods_attr_id']);

            //ecmoban模板堂 --zhuo start
            $goods_price = get_final_price($row['goods_id'], $row['goods_number'], true, $attr_id, $row['warehouse_id'], $row['area_id'], 0, 0, $add_tocart);

            $sql = "select rec_id from " . $GLOBALS['ecs']->table('cart') .
                    " where goods_id = '" . $row['goods_id'] . "' " .
                    " AND user_id =  '$user_id' AND extension_code <> 'package_buy' " .
                    " AND goods_attr_id = '" . $row['goods_attr_id'] . "'" .
                    " AND warehouse_id = '" . $row['warehouse_id'] . "'" .
                    " AND is_real = 1 and group_id = ''";
            $rec_id = $GLOBALS['db']->getOne($sql, true);

            //ecmoban模板堂 --zhuo start 限购
            $xiangouInfo = get_purchasing_goods_info($row['goods_id']);
            $start_date = $xiangouInfo['xiangou_start_date'];
            $end_date = $xiangouInfo['xiangou_end_date'];

            if ($xiangouInfo['is_xiangou'] == 1 && $nowTime > $start_date && $nowTime < $end_date) {
                $orderGoods = get_for_purchasing_goods($start_date, $end_date, $row['goods_id'], $user_id);
                $cart_number = $orderGoods['goods_number'] + $row['goods_number'];

                if ($orderGoods['goods_number'] >= $xiangouInfo['xiangou_num']) {
                    $row['goods_number'] = 0;
                    $error = 1;
                } else if ($cart_number >= $xiangouInfo['xiangou_num']) {
                    $row['goods_number'] = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                    $error = 2;
                } else {
                    $error = 0;
                }
            } else {
                $error = 0;
            }
            //ecmoban模板堂 --zhuo end 限购

            if ($error == 1) {
                $del = "DELETE FROM " . $GLOBALS['ecs']->table('cart') .
                        " WHERE goods_id = '" . $row['goods_id'] . "' AND rec_id = '" . $row['rec_id'] . "' AND warehouse_id = '" . $row['warehouse_id'] . "'";
                $GLOBALS['db']->query($del);
            } else {
                if ($rec_id > 0) {

                    if ($error == 2) {
                        $set = "goods_number = '" . $row['goods_number'] . "'";
                    } else {
                        $set = "goods_number = goods_number + " . $row['goods_number'];
                    }

                    $goods_sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET " . $set . " WHERE rec_id = '$rec_id'";

                    $del_sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE rec_id = '" . $row['rec_id'] . "'";
                    $GLOBALS['db']->query($del_sql);
                } else {

                    if ($row['extension_code'] != 'package_buy') {
                        $set = "";
                        if ($goods_price > 0) {
                            $set = "goods_price = '$goods_price', ";
                        }

                        $goods_sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET " . $set . " user_id = '$user_id', session_id = '', goods_number = '" . $row['goods_number'] . "' " .
                                " WHERE goods_id = '" . $row['goods_id'] . "' AND rec_id = '" . $row['rec_id'] . "' AND warehouse_id = '" . $row['warehouse_id'] . "'";
                    } else {
                        $goods_sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET user_id = '$user_id', session_id = '', goods_number = '" . $row['goods_number'] . "'" .
                                "WHERE rec_id = '" . $row['rec_id'] . "'";
                    }
                }

                $GLOBALS['db']->query($goods_sql);
            }
        }

        /* 删除赠品，重新选择 */
        $GLOBALS['db']->query('DELETE FROM ' . $GLOBALS['ecs']->table('cart') . " WHERE session_id = '" . real_cart_mac_ip() . "' AND is_gift > 0");
    }
}

/**
 * 查询评论内容
 *
 * @access  public
 * @params  integer     $id
 * @params  integer     $type
 * @params  integer     $page
 * @return  array
 */
function assign_comment($id, $type, $page = 1, $cmtType = 0)
{
    require_once('includes/cls_pager.php');
    $tag = array();
    $idStr = '"' . $id ."|". $cmtType . '"';

    /* 取得评论列表 */
    if($cmtType == 1){ //好评
            $where = " AND comment_rank in(5,4)";
    }elseif($cmtType == 2){ //中评
            $where = " AND comment_rank in(3,2)";
    }elseif($cmtType == 3){ //差评
            $where = " AND comment_rank = 1";
    }

    /* 取得评论列表 */
    $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('comment'). " WHERE id_value = '$id' AND comment_type = '$type' AND status = 1 AND parent_id = 0 $where";
    $count = $GLOBALS['db']->getOne($sql);

    $size  = !empty($GLOBALS['_CFG']['comments_number']) ? $GLOBALS['_CFG']['comments_number'] : 5;

	$comment =new Pager($count, $size, '', $idStr, 0, $page, 'gotoPage', 1);
	$limit = $comment->limit;
	$pager = $comment->fpage(array(0,4,5,6,9));

    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') .
            " WHERE id_value = '$id' AND comment_type = '$type' AND status = 1 AND parent_id = 0 $where".
            ' ORDER BY add_time DESC ' . $limit;
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach($res as $row)
    {
		$row['user_name']=setAnonymous($row['user_name']); //处理用户名 by wu
        $ids .= $ids ? ",$row[comment_id]" : $row['comment_id'];
        $arr[$row['comment_id']]['id']       = $row['comment_id'];
        $arr[$row['comment_id']]['email']    = $row['email'];
        $arr[$row['comment_id']]['username'] = $row['user_name'];
        $arr[$row['comment_id']]['user_id'] = $row['user_id'];
        $arr[$row['comment_id']]['id_value'] = $row['id_value'];
        $arr[$row['comment_id']]['useful'] = $row['useful'];
        $arr[$row['comment_id']]['user_picture'] = $GLOBALS['db']->getOne("select user_picture from " .$GLOBALS['ecs']->table('users'). " where user_id = '" .$row['user_id']. "'");
        $arr[$row['comment_id']]['content']  = nl2br(str_replace('\n', '<br />', htmlspecialchars($row['content'])));
        $arr[$row['comment_id']]['rank']     = $row['comment_rank'];
        $arr[$row['comment_id']]['server']     = $row['comment_server'];
        $arr[$row['comment_id']]['delivery']     = $row['comment_delivery'];
        $arr[$row['comment_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
        $arr[$row['comment_id']]['buy_goods'] = get_user_buy_goods_order($row['id_value'], $row['user_id'], $row['order_id']);
        //商品印象
        if($row['goods_tag']){
            $row['goods_tag'] = explode(",", $row['goods_tag']);
            foreach($row['goods_tag'] as $key=>$val){
                $tag[$key]['txt'] = $val;
                //印象数量
                $tag[$key]['num'] = comment_goodstag_num($row['id_value'], $val);
            }
            $arr[$row['comment_id']]['goods_tag'] = $tag;
        }

        $reply = get_reply_list($row['id_value'], $row['comment_id']);
        $arr[$row['comment_id']]['reply_list'] = $reply['reply_list'];
        $arr[$row['comment_id']]['reply_count'] = $reply['reply_count'];
        $arr[$row['comment_id']]['reply_size'] = $reply['reply_size'];
	$arr[$row['comment_id']]['reply_pager'] = $reply['reply_pager'];

        $img_list = get_img_list($row['id_value'], $row['comment_id']);
        $arr[$row['comment_id']]['img_list'] = $img_list;
        $arr[$row['comment_id']]['img_cont'] = count($img_list);

        //OSS文件存储ecmoban模板堂 --zhuo start
        if ((strpos($arr[$row['comment_id']]['user_picture'], 'http://') === false && strpos($arr[$row['comment_id']]['user_picture'], 'https://') === false)) {
            if ($GLOBALS['_CFG']['open_oss'] == 1 && $arr[$row['comment_id']]['user_picture']) {
                $bucket_info = get_bucket_info();
                $arr[$row['comment_id']]['user_picture'] = $bucket_info['endpoint'] . $arr[$row['comment_id']]['user_picture'];
            }
        }
        //OSS文件存储ecmoban模板堂 --zhuo end
    }

    /* 取得已有回复的评论 */
    if ($ids)
    {
        $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') .
                " WHERE parent_id IN( $ids ) AND user_id = 0";
        $res = $GLOBALS['db']->query($sql);
        while ($row = $GLOBALS['db']->fetch_array($res))
        {
            $arr[$row['parent_id']]['re_content']  = nl2br(str_replace('\n', '<br />', htmlspecialchars($row['content'])));
            $arr[$row['parent_id']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            $arr[$row['parent_id']]['re_email']    = $row['email'];
            $arr[$row['parent_id']]['re_username'] = $row['user_name'];
			$shop_info = get_shop_name($row['ru_id']);
			$arr[$row['parent_id']]['shop_name'] = $shop_info['shop_name'];
        }
    }

    $cmt = array('comments' => $arr, 'pager' => $pager,  'count' => $count,  'size' => $size);

    return $cmt;
}


/**
 * 查询评论内容 //晒单评价
 *
 * @access  public
 * @params  integer     $id
 * @params  integer     $type
 * @params  integer     $page
 * @return  array
 */
function assign_comments_single($id, $type, $page = 1)
{
    /* 取得评论列表 */
    $count = $GLOBALS['db']->getOne('SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('single').
           " WHERE goods_id = '$id'");
    $size  = !empty($GLOBALS['_CFG']['comments_number']) ? $GLOBALS['_CFG']['comments_number'] : 5;

    $page_count = ($count > 0) ? intval(ceil($count / $size)) : 1;

    $sql = 'SELECT single_id, user_id, user_name, single_name, single_description as content, addtime, comment_id FROM ' . $GLOBALS['ecs']->table('single') .
            " WHERE goods_id = '$id'".
            ' ORDER BY addtime DESC';
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page-1) * $size);

    $arr = array();
    $ids = '';
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $ids .= $ids ? ",$row[single_id]" : $row['single_id'];
        $arr[$row['single_id']]['single_id'] = $row['single_id'];
        $arr[$row['single_id']]['user_name'] = $row['user_name'];
		$arr[$row['single_id']]['comment_id'] = $row['comment_id'];
		$arr[$row['single_id']]['user_picture'] = $GLOBALS['db']->getOne("select user_picture from " .$GLOBALS['ecs']->table('users'). " where user_id = '" .$row['user_id']. "'");
        $arr[$row['single_id']]['content']  = str_replace('\r\n', '<br />', htmlspecialchars($row['content']));
        $arr[$row['single_id']]['content']  = nl2br(str_replace('\n', '<br />', $arr[$row['single_id']]['content']));
        $arr[$row['single_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['addtime']);

		$img_list = get_img_list($id, $row['single_id']);
		$arr[$row['single_id']]['img_list'] = $img_list;
		$arr[$row['single_id']]['img_cont'] = count($img_list);

		$arr[$row['single_id']]['useful'] = $GLOBALS['db']->getOne("select useful from " .$GLOBALS['ecs']->table('comment'). " where comment_id = '".$row['comment_id']."'");
		$arr[$row['single_id']]['reply_count'] = $GLOBALS['db']->getOne("select count(*) from " .$GLOBALS['ecs']->table('comment'). " where parent_id = '".$row['comment_id']."'");

		$single_reply = assign_comments_single_reply($row['comment_id'], $type);
		$arr[$row['single_id']]['reply_comment'] = $single_reply['reply_comments'];
		$arr[$row['single_id']]['reply_paper'] = $single_reply['reply_paper'];
    }

    /* 分页样式 */
    $pager['page']         = $page;
    $pager['size']         = $size;
    $pager['record_count'] = $count;
    $pager['page_count']   = $page_count;
    $pager['page_first']   = "javascript:single_gotoPage(1,$id,$type)";
    $pager['page_prev']    = $page > 1 ? "javascript:single_gotoPage(" .($page-1). ",$id,$type)" : 'javascript:;';
    $pager['page_next']    = $page < $page_count ? 'javascript:single_gotoPage(' .($page + 1) . ",$id,$type)" : 'javascript:;';
    $pager['page_last']    = $page < $page_count ? 'javascript:single_gotoPage(' .$page_count. ",$id,$type)"  : 'javascript:;';

    $cmt = array('comments' => $arr, 'pager' => $pager);
    return $cmt;
}

/**
 * 查询评论内容 //晒单评价
 *
 * @access  public
 * @params  integer     $id
 * @params  integer     $type
 * @params  integer     $page
 * @return  array
 */
function assign_comments_single_reply($parent_id = 0, $type = 0, $page = 1)
{
	require_once('includes/cls_newPage.php');

	$count = $GLOBALS['db']->getOne('SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('comment').
           " WHERE parent_id = '$parent_id' AND single_id > 0");

	$reply_comment =new Page($count, 5, '', $parent_id, 0, $page, 'single_reply_gotoPage', 1);
	$limit = $reply_comment->limit;
	$reply_paper = $reply_comment->fpage(array(0,4,5,6,9));

    /* 取得评论列表 */
    $sql = 'SELECT comment_id, user_name, content, add_time FROM ' . $GLOBALS['ecs']->table('comment') .
            " WHERE parent_id = '$parent_id' AND single_id > 0 ".
            ' ORDER BY add_time DESC ' . $limit;
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach($res as $key=>$row)
    {
        $arr[$row['comment_id']]['comment_id'] = $row['comment_id'];
        $arr[$row['comment_id']]['user_name'] = $row['user_name'];
        $arr[$row['comment_id']]['content']  = str_replace('\r\n', '<br />', htmlspecialchars($row['content']));
        $arr[$row['comment_id']]['content']  = nl2br(str_replace('\n', '<br />', $arr[$row['comment_id']]['content']));
        $arr[$row['comment_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
	}

	$cmt = array('reply_comments' => $arr, 'reply_paper' => $reply_paper);

    return $cmt;
}

function assign_template($ctype = '', $catlist = array(),$merchant_id = 0)
{
    global $smarty;

    $smarty->assign('rewrite',       $GLOBALS['_CFG']['rewrite']);
    $smarty->assign('image_width',   $GLOBALS['_CFG']['image_width']);
    $smarty->assign('image_height',  $GLOBALS['_CFG']['image_height']);
    $smarty->assign('points_name',   $GLOBALS['_CFG']['integral_name']);
    $smarty->assign('qq',            explode(',', $GLOBALS['_CFG']['qq']));
    $smarty->assign('ww',            explode(',', $GLOBALS['_CFG']['ww']));
    $smarty->assign('ym',            explode(',', $GLOBALS['_CFG']['ym']));
    $smarty->assign('msn',           explode(',', $GLOBALS['_CFG']['msn']));
    $smarty->assign('skype',         explode(',', $GLOBALS['_CFG']['skype']));
    $smarty->assign('stats_code',    $GLOBALS['_CFG']['stats_code']);
    $smarty->assign('copyright',     sprintf($GLOBALS['_LANG']['copyright'], date('Y'), $GLOBALS['_CFG']['shop_name']));
    $smarty->assign('shop_name',     $GLOBALS['_CFG']['shop_name']);
    $smarty->assign('service_email', $GLOBALS['_CFG']['service_email']);
    $smarty->assign('service_phone', $GLOBALS['_CFG']['service_phone']);
    $smarty->assign('shop_address',  $GLOBALS['_CFG']['shop_address']);
    $smarty->assign('ad_reminder',   $GLOBALS['_CFG']['ad_reminder']); //广告位提示设置
    $smarty->assign('licensed',      license_info());
    $smarty->assign('ecs_version',   VERSION);
    $smarty->assign('icp_number',    $GLOBALS['_CFG']['icp_number']);
    $smarty->assign('username',      !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : '');

    //获取店铺描述，关键字
    $seller_head = array();
    if($merchant_id > 0) {
        $sql = "SELECT shop_keyword ,street_desc FROM" . $GLOBALS['ecs']->table('seller_shopinfo') . "WHERE ru_id = '" . $merchant_id . "'";
        $seller_head = $GLOBALS['db']->getRow($sql);
    }

    //判断是否已经注册变量
    if (is_null($GLOBALS['smarty']->get_template_vars('keywords'))) {
        if (isset($seller_head['shop_keyword']) && $seller_head['shop_keyword'] != '') {
            $smarty->assign('keywords', htmlspecialchars($seller_head['shop_keyword']));
        } else {
            $smarty->assign('keywords', htmlspecialchars($GLOBALS['_CFG']['shop_keywords']));
        }
    }

    if (is_null($GLOBALS['smarty']->get_template_vars('description'))) {
        if (isset($seller_head['street_desc']) && $seller_head['street_desc'] != '') {
            $smarty->assign('description', htmlspecialchars($seller_head['street_desc']));
        } else {
            $smarty->assign('description', htmlspecialchars($GLOBALS['_CFG']['shop_desc']));
        }
    }

    $smarty->assign('ecjia_qrcode',    $GLOBALS['_CFG']['ecjia_qrcode']);
    $smarty->assign('ectouch_qrcode',    $GLOBALS['_CFG']['ectouch_qrcode']);
    $smarty->assign('index_down_logo',    $GLOBALS['_CFG']['index_down_logo']);
    $smarty->assign('site_commitment',    $GLOBALS['_CFG']['site_commitment']);
    $smarty->assign('user_login_logo',    $GLOBALS['_CFG']['user_login_logo']);
    $smarty->assign('login_logo_pic',    $GLOBALS['_CFG']['login_logo_pic']);

    $business_logo = isset($GLOBALS['_CFG']['business_logo']) && !empty($GLOBALS['_CFG']['business_logo']) ? $GLOBALS['_CFG']['business_logo'] : '';
    $smarty->assign('business_logo',    $business_logo);

    if (defined('THEME_EXTENSION')) {
        $smarty->assign('top_cat_list', cat_list(0)); //网站导航顶级分类
        $smarty->assign('nav_cat_model', $GLOBALS['_CFG']['nav_cat_model']); //网站导航顶级分类
        if ($GLOBALS['_CFG']['nav_cat_model']) {
            $smarty->assign('nav_cat_num', 16);
        } else {
            $smarty->assign('nav_cat_num', 8);
        }
    }

    $smarty->assign('navigator_list',        get_navigator($ctype, $catlist));  //自定义导航栏
    $links = index_get_links();
    $smarty->assign('img_links',       $links['img']);
    $smarty->assign('txt_links',       $links['txt']);

    $partner_links = index_get_links('partner_list');
    $smarty->assign('partner_img_links',       $partner_links['img']);
    $smarty->assign('partner_txt_links',       $partner_links['txt']);

    //URL html伪静态
    $smarty->assign('url_seckill', setRewrite('seckill.php'));
    $smarty->assign('url_categoryall', setRewrite('categoryall.php'));
    $smarty->assign('url_index', setRewrite('index.php'));
    $smarty->assign('url_merchants', setRewrite('merchants.php'));
    $smarty->assign('url_merchants_steps', setRewrite('merchants_steps.php'));
    $smarty->assign('url_merchants_steps_site', setRewrite('merchants_steps_site.php'));
    $smarty->assign('url_presale', setRewrite('presale.php'));
    $smarty->assign('url_presale_new', build_uri('presale', array('act' => 'new')));
    $smarty->assign('url_business_buy', build_uri('wholesale', array('act' => 'buy')));
    $smarty->assign('url_presale_advance', build_uri('presale', array('act' => 'advance')));
    $smarty->assign('shop_reg_closed', $GLOBALS['_CFG']['shop_reg_closed']);
    $smarty->assign('dwt_shop_name', $GLOBALS['_LANG']['dwt_shop_name']);

    //楼层样式左侧定位导航样式
    if (isset($GLOBALS['_CFG']['floor_nav_type'])) {
        if ($GLOBALS['_CFG']['floor_nav_type'] == 1) {
            $smarty->assign('floor_nav_type', "one");
        } elseif ($GLOBALS['_CFG']['floor_nav_type'] == 2) {
            $smarty->assign('floor_nav_type', "two");
        } elseif ($GLOBALS['_CFG']['floor_nav_type'] == 3) {
            $smarty->assign('floor_nav_type', "sthree");
        } elseif ($GLOBALS['_CFG']['floor_nav_type'] == 4) {
            $smarty->assign('floor_nav_type', "four");
        }
    }

    if (!empty($GLOBALS['_CFG']['search_keywords']))
    {
        $searchkeywords = explode(',', trim($GLOBALS['_CFG']['search_keywords']));
    }
    else
    {
        $searchkeywords = array();
    }

    $smarty->assign('searchkeywords', $searchkeywords);
}

/**
 * 获得所有的友情链接
 *
 * @access  private
 * @return  array
 */
function index_get_links($table = 'friend_link')
{
    $sql = 'SELECT link_id, link_logo, link_name, link_url FROM ' . $GLOBALS['ecs']->table($table) . ' ORDER BY show_order';
    $res = $GLOBALS['db']->getAll($sql);

    $links['img'] = $links['txt'] = array();

    foreach ($res AS $row)
    {
        if($row['link_logo']){
            $row['link_logo'] = get_image_path($row['link_id'], $row['link_logo'], true);
        }else{
            $row['link_logo'] = '';
        }

        if (!empty($row['link_logo']))
        {
            $links['img'][] = array('name' => $row['link_name'],
                                    'url'  => $row['link_url'],
                                    'logo' => $row['link_logo']);
        }
        else
        {
            $links['txt'][] = array('name' => $row['link_name'],
                                    'url'  => $row['link_url']);
        }
    }

    return $links;
}

/**
 * 将一个本地时间戳转成GMT时间戳
 *
 * @access  public
 * @param   int     $time
 *
 * @return int      $gmt_time;
 */
function time2gmt($time)
{
    return strtotime(gmdate('Y-m-d H:i:s', $time));
}

/**
 * 查询会员的红包金额
 *
 * @access  public
 * @param   integer     $user_id
 * @return  void
 */
function get_user_bonus($user_id = 0)
{
    if ($user_id == 0)
    {
        $user_id = $_SESSION['user_id'];
    }

    $day = local_getdate();
    $cur_date = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);
    $useDate = " AND bt.use_start_date < " .$cur_date. " AND bt.use_end_date > " . $cur_date;

    $sql = "SELECT SUM(bt.type_money) AS bonus_value, COUNT(*) AS bonus_count ".
            "FROM " .$GLOBALS['ecs']->table('user_bonus'). " AS ub, ".
                $GLOBALS['ecs']->table('bonus_type') . " AS bt ".
            "WHERE ub.user_id = '$user_id' AND ub.bonus_type_id = bt.type_id AND ub.order_id = 0" . $useDate;
    $row = $GLOBALS['db']->getRow($sql);

    return $row;
}

/**
 * 保存推荐uid
 *
 * @access  public
 * @param   void
 *
 * @return void
 * @author xuanyan
 **/
function set_affiliate()
{
    $config = unserialize($GLOBALS['_CFG']['affiliate']);
    if (!empty($_GET['u']) && $config['on'] == 1)
    {
        if(!empty($config['config']['expire']))
        {
            if($config['config']['expire_unit'] == 'hour')
            {
                $c = 1;
            }
            elseif($config['config']['expire_unit'] == 'day')
            {
                $c = 24;
            }
            elseif($config['config']['expire_unit'] == 'week')
            {
                $c = 24 * 7;
            }
            else
            {
                $c = 1;
            }
            setcookie('ecshop_affiliate_uid', intval($_GET['u']), gmtime() + 3600 * $config['config']['expire'] * $c, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        }
        else
        {
            setcookie('ecshop_affiliate_uid', intval($_GET['u']), gmtime() + 3600 * 24, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']); // 过期时间为 1 天
        }
    }
}

/**
 * 获取推荐uid
 *
 * @access  public
 * @param   void
 *
 * @return int
 * @author xuanyan
 **/
function get_affiliate()
{
    if (!empty($_COOKIE['ecshop_affiliate_uid']))
    {
        $uid = intval($_COOKIE['ecshop_affiliate_uid']);
        if ($GLOBALS['db']->getOne('SELECT user_id FROM ' . $GLOBALS['ecs']->table('users') . "WHERE user_id = '$uid'"))
        {
            return $uid;
        }
        else
        {
            setcookie('ecshop_affiliate_uid', '', 1, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
        }
    }

    return 0;
}

/**
 * 获得指定分类同级的所有分类以及该分类下的子分类
 *
 * @access  public
 * @param   integer     $cat_id     分类编号为0时调用顶级分类
 * @param   integer     $cat_type   文章分类类型，默认为1自定义分类，2为系统保留分类 by wang
 * @return  array
 */
function article_categories_tree($cat_id = 0,$cat_type=1)
{
    if ($cat_id > 0)
    {
        $sql = 'SELECT parent_id FROM ' . $GLOBALS['ecs']->table('article_cat') . " WHERE cat_id = '$cat_id'";
        $parent_id = $GLOBALS['db']->getOne($sql);
    }
    else
    {
        $parent_id = 0;
    }

    /*
     判断当前分类中全是是否是底级分类，
     如果是取出底级分类上级分类，
     如果不是取当前分类及其下的子分类
    */
    $sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('article_cat') . " WHERE parent_id = '$parent_id' and cat_type='$cat_type'";
    if ($GLOBALS['db']->getOne($sql))
    {
        /* 获取当前分类及其子分类 */
        $sql = 'SELECT a.cat_id, a.cat_name, a.sort_order AS parent_order, a.cat_id, ' .
                    'b.cat_id AS child_id, b.cat_name AS child_name, b.sort_order AS child_order ' .
                'FROM ' . $GLOBALS['ecs']->table('article_cat') . ' AS a ' .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('article_cat') . ' AS b ON b.parent_id = a.cat_id ' .
                "WHERE a.parent_id = '$parent_id' AND a.cat_type='$cat_type' ORDER BY parent_order ASC";
    }
    else
    {
        /* 获取当前分类及其父分类 */
        $sql = 'SELECT a.cat_id, a.cat_name, b.cat_id AS child_id, b.cat_name AS child_name, b.sort_order ' .
                'FROM ' . $GLOBALS['ecs']->table('article_cat') . ' AS a ' .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('article_cat') . ' AS b ON b.parent_id = a.cat_id ' .
                "WHERE b.parent_id = '$parent_id' AND b.cat_type = 1 ORDER BY sort_order ASC";
    }
    $res = $GLOBALS['db']->getAll($sql);
    $cat_arr = array();
    foreach ($res AS $row)
    {
        $cat_arr[$row['cat_id']]['id']   = $row['cat_id'];
        $cat_arr[$row['cat_id']]['name'] = $row['cat_name'];
        $cat_arr[$row['cat_id']]['url']  = build_uri('article_cat', array('acid' => $row['cat_id']), $row['cat_name']);

        if ($row['child_id'] != NULL)
        {
            $cat_arr[$row['cat_id']]['children'][$row['child_id']]['id']   = $row['child_id'];
            $cat_arr[$row['cat_id']]['children'][$row['child_id']]['name'] = $row['child_name'];
            $cat_arr[$row['cat_id']]['children'][$row['child_id']]['url']  = build_uri('article_cat', array('acid' => $row['child_id']), $row['child_name']);
			$cat_arr[$row['cat_id']]['children'][$row['child_id']]['children']   = get_article_child_cats($row['child_id']);
        }

    }

    return $cat_arr;
}

/**
 * 获得指定文章分类的子分类by wang
 *
 * @access  public
 * @param   integer $cat    分类编号
 * @return  array
 */
function get_article_child_cats($cat)
{
	 $sql="select cat_id,cat_name,cat_id,cat_name,sort_order from ".$GLOBALS['ecs']->table('article_cat')." where parent_id='$cat' ORDER BY sort_order ASC";
	 $res=$GLOBALS['db']->getAll($sql);
	 $cat_arr=array();
	 foreach($res as $row)
	 {
		$cat_arr[$row['cat_id']]['id']   = $row['cat_id'];
        $cat_arr[$row['cat_id']]['name'] = $row['cat_name'];
        $cat_arr[$row['cat_id']]['url']  = build_uri('article_cat', array('acid' => $row['cat_id']), $row['cat_name']);
	 }
	 return $cat_arr;
}


/**
 * 获得指定文章分类的所有上级分类
 *
 * @access  public
 * @param   integer $cat    分类编号
 * @return  array
 */
function get_article_parent_cats($cat)
{
    if ($cat == 0)
    {
        return array();
    }

    $arr = $GLOBALS['db']->GetAll('SELECT cat_id, cat_name, parent_id FROM ' . $GLOBALS['ecs']->table('article_cat'));

    if (empty($arr))
    {
        return array();
    }

    $index = 0;
    $cats  = array();

    while (1)
    {
        foreach ($arr AS $row)
        {
            if ($cat == $row['cat_id'])
            {
                $cat = $row['parent_id'];

                $cats[$index]['cat_id']   = $row['cat_id'];
                $cats[$index]['cat_name'] = $row['cat_name'];

                $index++;
                break;
            }
        }

        if ($index == 0 || $cat == 0)
        {
            break;
        }
    }

    return $cats;
}

/**
 * 取得某模板某库设置的数量
 * @param   string      $template   模板名，如index
 * @param   string      $library    库名，如recommend_best
 * @param   int         $def_num    默认数量：如果没有设置模板，显示的数量
 * @return  int         数量
 */
function get_library_number($library, $template = null)
{
    global $page_libs;

    if (empty($template))
    {
        $template = basename(PHP_SELF);
        $template = substr($template, 0, strrpos($template, '.'));
    }
    $template = addslashes($template);

    static $lib_list = array();

    /* 如果没有该模板的信息，取得该模板的信息 */
    if (!isset($lib_list[$template]))
    {
        $lib_list[$template] = array();
        $sql = "SELECT library, number FROM " . $GLOBALS['ecs']->table('template') .
                " WHERE theme = '" . $GLOBALS['_CFG']['template'] . "'" .
                " AND filename = '$template' AND remarks='' ";
        $res = $GLOBALS['db']->query($sql);
        while ($row = $GLOBALS['db']->fetchRow($res))
        {
            $lib = basename(strtolower(substr($row['library'], 0, strpos($row['library'], '.'))));
            $lib_list[$template][$lib] = $row['number'];
        }
    }

    $num = 0;
    if (isset($lib_list[$template][$library]))
    {
        $num = intval($lib_list[$template][$library]);
    }
    else
    {
        /* 模板设置文件查找默认值 */
        include_once(ROOT_PATH . ADMIN_PATH . '/includes/lib_template.php');
        static $static_page_libs = null;
        if ($static_page_libs == null)
        {
            $static_page_libs = $page_libs;
        }
        $lib = '/library/' . $library . '.lbi';

        $num = isset($static_page_libs[$template][$lib]) ? $static_page_libs[$template][$lib] :  3;
    }

    return $num;
}

/**
 * 取得自定义导航栏列表
 * @param   string      $type    位置，如top、bottom、middle
 * @return  array         列表
 */
function get_navigator($ctype = '', $catlist = array())
{
    $sql = 'SELECT * FROM '. $GLOBALS['ecs']->table('nav') . '
            WHERE ifshow = \'1\' ORDER BY type, vieworder';
    $res = $GLOBALS['db']->query($sql);

    $cur_url = substr(strrchr($_SERVER['REQUEST_URI'],'/'),1);

    if (intval($GLOBALS['_CFG']['rewrite']))
    {
        if(strpos($cur_url, '-'))
        {
            preg_match('/([a-z]*)-([0-9]*)/',$cur_url,$matches);
            $cur_url = $matches[1].'.php?id='.$matches[2];
        }
    }
    else
    {
        $cur_url = substr(strrchr($_SERVER['REQUEST_URI'],'/'),1);
    }

    $noindex = false;
    $active = 0;
    $navlist = array(
        'top' => array(),
        'middle' => array(),
        'bottom' => array()
    );
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $navlist[$row['type']][] = array(
            'name'      =>  $row['name'],
            'opennew'   =>  $row['opennew'],
            'url'       =>  setRewrite($row['url']),
            'ctype'     =>  $row['ctype'],
            'cid'       =>  $row['cid'],
            );
    }

    /*遍历自定义是否存在currentPage*/
    foreach($navlist['middle'] as $k=>$v)
    {
        $condition = empty($ctype) ? (strpos($v['url'], $cur_url) !== false) : (strpos($v['url'], $cur_url) !== false && strlen($cur_url) == strlen($v['url']));
        if ($condition)
        {
            $navlist['middle'][$k]['active'] = 1;
            $noindex = true;
            $active += 1;
        }
        if(substr($v['url'],0,8)=='category')
        {
            $cat_id = $v['cid'];
            $cat_list = get_categories_tree_xaphp($cat_id);
            $navlist['middle'][$k]['cat'] = 1;
            $navlist['middle'][$k]['cat_list'] = $cat_list;
        }
    }

    if(!empty($ctype) && $active < 1)
    {
        foreach($catlist as $key => $val)
        {
            foreach($navlist['middle'] as $k=>$v)
            {
                if(!empty($v['ctype']) && $v['ctype'] == $ctype && $v['cid'] == $val && $active < 1)
                {
                    $navlist['middle'][$k]['active'] = 1;
                    $noindex = true;
                    $active += 1;
                }
            }
        }
    }

    if ($noindex == false) {
        $navlist['config']['index'] = 1;
    }

    return $navlist;
}

/**
 * 自定义导航调取分类
 */
function get_categories_tree_xaphp($cat_id = 0) {

    /* 获取当前分类及其子分类 */
    $sql = 'SELECT cat_id,cat_name ,parent_id,is_show ' .
            'FROM ' . $GLOBALS['ecs']->table('category') .
            "WHERE parent_id = '$cat_id' AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res AS $row) {
        $cat_arr[$row['cat_id']]['id'] = $row['cat_id'];
        $cat_arr[$row['cat_id']]['name'] = $row['cat_name'];
        $cat_arr[$row['cat_id']]['url'] = build_uri('category', array('cid' => $row['cat_id']), $row['cat_name']);

        if (isset($row['cat_id']) != NULL) {
            $cat_arr[$row['cat_id']]['cat_id'] = get_child_tree($row['cat_id']);
        }
    }


    if (isset($cat_arr)) {
        return $cat_arr;
    }
}

/**
 * 授权信息内容
 *
 * @return  str
 */
function license_info()
{
    if($GLOBALS['_CFG']['licensed'] > 0)
    {
        /* 获取HOST */
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST']))
        {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        }
        elseif (isset($_SERVER['HTTP_HOST']))
        {
            $host = $_SERVER['HTTP_HOST'];
        }
        $url_domain=url_domain();
        $host = 'http://' . $host .$url_domain ;
        $license = '<a href="http://www.ecmoban.com/license.php?product=ecshop_b2c&url=' . urlencode($host) . '" target="_blank"
>&nbsp;&nbsp;Licensed</a>';
        return $license;
    }
    else
    {
        return '';
    }
}
function url_domain()
{
    $curr = strpos(PHP_SELF, ADMIN_PATH . '/') !== false ?
            preg_replace('/(.*)(' . ADMIN_PATH . ')(\/?)(.)*/i', '\1', dirname(PHP_SELF)) :
            dirname(PHP_SELF);

    $root = str_replace('\\', '/', $curr);

    if (substr($root, -1) != '/')
    {
        $root .= '/';
    }

    return $root;
}

/**
 * 显示一个提示信息 by Leah
 *
 * @access  public
 * @param   string  $content
 * @param   string  $link
 * @param   string  $href
 * @param   string  $type               信息类型：warning, error, info
 * @param   string  $auto_redirect      是否自动跳转
 * @return  void
 */
function show_message1($content, $links = '', $hrefs = '', $type = 'info', $auto_redirect = false, $order_return = array())
{
    assign_template();

    $msg['content'] = $content;
    if (is_array($links) && is_array($hrefs))
    {
        if (!empty($links) && count($links) == count($hrefs))
        {
            foreach($links as $key =>$val)
            {
                $msg['url_info'][$val] = $hrefs[$key];
            }
            $msg['back_url'] = $hrefs['0'];
        }
    }
    else
    {
        $link   = empty($links) ? $GLOBALS['_LANG']['back_up_page'] : $links;
        $href    = empty($hrefs) ? 'javascript:history.back()'       : $hrefs;
        $msg['url_info'][$link] = $href;
        $msg['back_url'] = $href;
    }

    $msg['type']    = $type;
    $position = assign_ur_here(0, $GLOBALS['_LANG']['sys_msg']);
    $GLOBALS['smarty']->assign('page_title', $position['title']);   // 页面标题
    $GLOBALS['smarty']->assign('ur_here',    $position['ur_here']); // 当前位置
    $GLOBALS['smarty']->assign('hrf', $hrefs  );
    if (is_null($GLOBALS['smarty']->get_template_vars('helps')))
    {
        $GLOBALS['smarty']->assign('helps', get_shop_help()); // 网店帮助
    }

    $GLOBALS['smarty']->assign('auto_redirect', $auto_redirect);
    $GLOBALS['smarty']->assign('message', $msg);

    $GLOBALS['smarty']->assign('order_return', $order_return);

    $GLOBALS['smarty']->display('message1.dwt');

    exit;
}

//晒单图片
function get_img_list($id, $comment_id){
    if (!$comment_id) {
        return [];
    }
    $sql = "SELECT comment_id,comment_img,img_thumb FROM " .$GLOBALS['ecs']->table('comment_img'). " WHERE goods_id = '$id' AND comment_id = '$comment_id'";
    $res = $GLOBALS['db']->getAll($sql);
    foreach($res as $key=>$row){

        //OSS文件存储ecmoban模板堂 --zhuo start
        if($GLOBALS['_CFG']['open_oss'] == 1){
            $bucket_info = get_bucket_info();
            $res[$key]['comment_img']    = $bucket_info['endpoint'] . $row['comment_img'];
            $res[$key]['img_thumb']    = $bucket_info['endpoint'] . $row['img_thumb'];
        }
        //OSS文件存储ecmoban模板堂 --zhuo end
    }

    return $res;
}

//获得最新文章列表by wang
function get_new_article($num=0)
{
	$sql="select article_id,cat_id,title,author,add_time from ".$GLOBALS['ecs']->table('article')." where 1 AND is_open = 1 order by add_time desc limit 0,".$num;
	$articles=$GLOBALS['db']->getAll($sql);
	foreach($articles as $key=>$val)
	{
		$articles[$key]['url'] = build_uri('article', array('aid' => $val['article_id']), $val['title']);
		$articles[$key]['add_time']=local_date($GLOBALS['_CFG']['time_format'], $val['add_time']);
	}
	return $articles;
}

/**
 * 获得指定分类的所有上级分类
 *
 * @access  public
 * @param   integer $cat    分类编号
 * @return  array
 */
function get_wholesale_parent_cats($cat)
{
    if ($cat == 0)
    {
        return array();
    }

    $arr = $GLOBALS['db']->GetAll('SELECT cat_id, cat_name, parent_id FROM ' . $GLOBALS['ecs']->table('wholesale_cat'));

    if (empty($arr))
    {
        return array();
    }

    $index = 0;
    $cats  = array();

    while (1)
    {
        foreach ($arr AS $row)
        {
            if ($cat == $row['cat_id'])
            {
                $cat = $row['parent_id'];

                $cats[$index]['cat_id']   = $row['cat_id'];
                $cats[$index]['cat_name'] = $row['cat_name'];
                $cats[$index]['parent_id'] = $row['parent_id'];

                $sql = "SELECT cat_id, cat_name FROM " .$GLOBALS['ecs']->table('wholesale_cat'). " AS c WHERE parent_id = '" .$row['cat_id']. "' ";
                $cats[$index]['cat_tree'] = $GLOBALS['db']->getAll($sql);

                $index++;
                break;
            }
        }

        if ($index == 0 || $cat == 0)
        {
            break;
        }
    }

    return $cats;
}
?>
