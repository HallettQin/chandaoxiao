<?php

/**
 * ECSHOP 动态内容函数库
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
 * 获得查询次数以及查询时间
 *
 * @access  public
 * @return  string
 */
function insert_query_info()
{
    if ($GLOBALS['db']->queryTime == '')
    {
        $query_time = 0;
    }
    else
    {
        if (PHP_VERSION >= '5.0.0')
        {
            $query_time = number_format(microtime(true) - $GLOBALS['db']->queryTime, 6);
        }
        else
        {
            list($now_usec, $now_sec)     = explode(' ', microtime());
            list($start_usec, $start_sec) = explode(' ', $GLOBALS['db']->queryTime);
            $query_time = number_format(($now_sec - $start_sec) + ($now_usec - $start_usec), 6);
        }
    }

    /* 内存占用情况 */
    if ($GLOBALS['_LANG']['memory_info'] && function_exists('memory_get_usage'))
    {
        $memory_usage = sprintf($GLOBALS['_LANG']['memory_info'], memory_get_usage() / 1048576);
    }
    else
    {
        $memory_usage = '';
    }

    /* 是否启用了 gzip */
    $gzip_enabled = gzip_enabled() ? $GLOBALS['_LANG']['gzip_enabled'] : $GLOBALS['_LANG']['gzip_disabled'];

    $online_count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('sessions'));

    /* 加入触发cron代码 */
    $cron_method = empty($GLOBALS['_CFG']['cron_method']) ? '<img src="' .$GLOBALS['_CFG']['site_domain']. 'api/cron.php?t=' . gmtime() . '" alt="" style="width:0px;height:0px;" />' : '';

    return sprintf($GLOBALS['_LANG']['query_info'], $GLOBALS['db']->queryCount, $query_time, $online_count) . $gzip_enabled . $memory_usage . $cron_method;
}

/**
 * 调用浏览历史by wang修改
 *
 * @access  public
 * @return  string
 */
function insert_history()
{
    $str = '<ul>';
    if (!empty($_COOKIE['ECS']['history']))
    {
        $where = db_create_in($_COOKIE['ECS']['history'], 'g.goods_id');
        //ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['review_goods'] == 1) {
            $where .= ' AND g.review_status > 2 ';
        }
        
        $warehouse_id = isset($_COOKIE['area_region']) && !empty($_COOKIE['area_region']) ? $_COOKIE['area_region'] : 0;
        $province_id = isset($_COOKIE['province']) && !empty($_COOKIE['province']) ? $_COOKIE['province'] : 0;
        
        if (isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])) {
            $warehouse_id = $_COOKIE['region_id'];
        }

        $area_info = get_area_info($province_id);
        $area_id = $area_info['region_id'];
        
        $leftJoin = '';
        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
        }

        $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_goods') . " AS wg ON g.goods_id = wg.goods_id AND wg.region_id = '$warehouse_id' ";
        $leftJoin .= " LEFT JOIN " . $GLOBALS['ecs']->table('warehouse_area_goods') . " AS wag ON g.goods_id = wag.goods_id AND wag.region_id = '$area_id' ";

        //ecmoban模板堂 --zhuo end
        $sql = 'SELECT g.goods_id, g.goods_name, g.goods_thumb, ' .
                "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                'g.product_price, g.product_promote_price FROM ' . $GLOBALS['ecs']->table('goods') . " AS g " .
                $leftJoin .
                " WHERE $where AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ORDER BY INSTR('" . $_COOKIE['ECS']['history'] . "', g.goods_id) LIMIT 0,5";
        $query = $GLOBALS['db']->query($sql);
        
        $res = array();
        while ($row = $GLOBALS['db']->fetch_array($query))
        {
            
            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            } else {
                $promote_price = 0;
            }
            
            /**
             * 重定义商品价格
             * 商品价格 + 属性价格
             * start
             */
            $price_info = get_goods_one_attr_price($row, $warehouse_id, $area_id, $promote_price);
            $row = !empty($row) ? array_merge($row, $price_info) : $row;
            $promote_price = $row['promote_price'];
            /**
             * 重定义商品价格
             * end
             */
            
            $goods['goods_id'] = $row['goods_id'];
            $goods['goods_name'] = $row['goods_name'];
            $goods['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $goods['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $goods['shop_price'] = price_format($row['shop_price']);
            $goods['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
            
            if ($promote_price > 0) {
                $price = $goods['shop_price'];
            } else {
                $price = $goods['promote_price'];
            }
            
            $goods['url'] = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);
            //by wang
            $str.='<li><div class="p-img"><a href="' . $goods['url'] . '" target="_blank" title="' . $goods['goods_name'] . '"><img src="' . $goods['goods_thumb'] . '" width="178" height="178"></a></div>
                            <div class="p-name"><a href="' . $goods['url'] . '" target="_blank">' . $goods['short_name'] . '</a></div><div class="p-price">' . $price . '</div>
                            <a href="javascript:addToCart(' . $goods['goods_id'] . ');" class="btn">加入进货单</a></li>';
        }
    }
	$str.="</ul>";
    return $str;
}

function insert_history_test()
{
    $warehouse_id = isset($_COOKIE['area_region']) && !empty($_COOKIE['area_region']) ? $_COOKIE['area_region'] : 0;
    $province_id = isset($_COOKIE['province']) && !empty($_COOKIE['province']) ? $_COOKIE['province'] : 0;
    
    if (isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])) {
        $warehouse_id = $_COOKIE['region_id'];
    }

    $area_info = get_area_info($province_id);
    $area_id = $area_info['region_id'];

    $str = '';
    if (!empty($_COOKIE['ECS']['history']))
    {
        $where = db_create_in($_COOKIE['ECS']['history'], 'g.goods_id');

        //ecmoban模板堂 --zhuo start
        $leftJoin = '';	

        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
        }

        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
        //ecmoban模板堂 --zhuo end	
        
        $sql = 'SELECT g.goods_id, g.goods_name, g.goods_thumb, ' .
                "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                'g.is_promote, g.promote_start_date, g.promote_end_date, g.product_price, g.product_promote_price FROM ' . $GLOBALS['ecs']->table('goods') . " as g " .
                $leftJoin .
                "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                " WHERE $where AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 limit 0,10";

        $query = $GLOBALS['db']->query($sql);
        $res = array();
		
        while ($row = $GLOBALS['db']->fetch_array($query))
        {
            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            } else {
                $promote_price = 0;
            }

            /**
             * 重定义商品价格
             * 商品价格 + 属性价格
             * start
             */
            $price_info = get_goods_one_attr_price($row, $warehouse_id, $area_id, $promote_price);
            $row = !empty($row) ? array_merge($row, $price_info) : $row;
            $promote_price = $row['promote_price'];
            /**
             * 重定义商品价格
             * end
             */
            
            $goods['goods_id'] = $row['goods_id'];
            $goods['goods_name'] = $row['goods_name'];
            $goods['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $goods['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $goods['shop_price'] = price_format($row['shop_price']);
            $goods['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
            $goods['is_promote'] = $row['is_promote'];
            $goods['url'] = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);

            if ($promote_price > 0) {
                $price = $goods['shop_price'];
            } else {
                $price = $goods['promote_price'];
            }

            $str.='<dl class="nch-sidebar-bowers">
                    <dt class="goods-name"><a href="'.$goods['url'].'" target="_blank" title="'.$goods['goods_name'].'">'.$goods['short_name'].'</a></dt>
                    <dd class="goods-pic"><a href="'.$goods['url'].'" target="_blank"><img src="'.$goods['goods_thumb'].'" alt="'.$goods['goods_name'].'" /></a></dd>
                    <dd class="goods-price">'.$price.'</dd>
                    </dl>';
        }
        
    }
	
    return $str;
}

function insert_history_info($num = 0)
{
    $res = array();
    
    $num = !empty($num) ? intval($num) : 0;
    
    $warehouse_id = isset($_COOKIE['area_region']) && !empty($_COOKIE['area_region']) ? $_COOKIE['area_region'] : 0;
    $province_id = isset($_COOKIE['province']) && !empty($_COOKIE['province']) ? $_COOKIE['province'] : 0;
    
    if (isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])) {
        $warehouse_id = $_COOKIE['region_id'];
    }

    $area_info = get_area_info($province_id);
    $area_id = $area_info['region_id'];

    if (!empty($_COOKIE['ECS']['history']))
    {
        $where = db_create_in($_COOKIE['ECS']['history'], 'g.goods_id');

        //ecmoban模板堂 --zhuo start
        $leftJoin = '';	

        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
        }

        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
        //ecmoban模板堂 --zhuo end
        
        $limit = "";
        if (!empty($num) && $num > 0) {
            $limit = " limit 0,$num";
        } else {
            $limit = " limit 0,14 ";
        }

        $sql = 'SELECT g.goods_id, g.goods_name, g.goods_thumb, ' .
                "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                'g.is_promote, g.promote_start_date, g.promote_end_date, g.product_price, g.product_promote_price FROM ' . $GLOBALS['ecs']->table('goods') . " as g " .
                $leftJoin .
                "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                " WHERE $where AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 " . $limit;

        $query = $GLOBALS['db']->query($sql);
        $res = array();
		$k=0;
        while ($row = $GLOBALS['db']->fetch_array($query))
        {
            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            } else {
                $promote_price = 0;
            }

            /**
             * 重定义商品价格
             * 商品价格 + 属性价格
             * start
             */
            $price_info = get_goods_one_attr_price($row, $warehouse_id, $area_id, $promote_price);
            $row = !empty($row) ? array_merge($row, $price_info) : $row;
            $promote_price = $row['promote_price'];
            /**
             * 重定义商品价格
             * end
             */
            
            $goods['goods_id'] = $row['goods_id'];
            $goods['goods_name'] = $row['goods_name'];
            $goods['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $goods['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $goods['shop_price'] = price_format($row['shop_price']);
            $goods['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
            $goods['url'] = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);

            $res[$key++]=$goods;
        }
        
    }
    
    return $res;
}

function insert_history_category()
{
    $warehouse_id = isset($_COOKIE['area_region']) && !empty($_COOKIE['area_region']) ? $_COOKIE['area_region'] : 0;
    $province_id = isset($_COOKIE['province']) && !empty($_COOKIE['province']) ? $_COOKIE['province'] : 0;
    
    if (isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])) {
        $warehouse_id = $_COOKIE['region_id'];
    }

    $area_info = get_area_info($province_id);
    $area_id = $area_info['region_id'];

    $str = '';
    if (!empty($_COOKIE['ECS']['history']))
    {
        $where = db_create_in($_COOKIE['ECS']['history'], 'g.goods_id');

        //ecmoban模板堂 --zhuo start
        $leftJoin = '';	

        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
        }

        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
        //ecmoban模板堂 --zhuo end	
        
        $sql = 'SELECT g.goods_id, g.goods_name, g.goods_thumb, ' .
                "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                'g.is_promote, g.promote_start_date, g.promote_end_date, g.product_price, g.product_promote_price FROM ' . $GLOBALS['ecs']->table('goods') . " as g " .
                $leftJoin .
                "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                " WHERE $where AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 limit 0,14";

        $query = $GLOBALS['db']->query($sql);
        $res = array();
		
        while ($row = $GLOBALS['db']->fetch_array($query))
        {
            if ($row['promote_price'] > 0)
            {
                    $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            }
            else
            {
                    $promote_price = 0;
            }
            
            /**
             * 重定义商品价格
             * 商品价格 + 属性价格
             * start
             */
            $price_info = get_goods_one_attr_price($row, $warehouse_id, $area_id, $promote_price);
            $row = !empty($row) ? array_merge($row, $price_info) : $row;
            $promote_price = $row['promote_price'];
            /**
             * 重定义商品价格
             * end
             */
            
            $goods['goods_id'] = $row['goods_id'];
            $goods['goods_name'] = $row['goods_name'];
            $goods['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $goods['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $goods['shop_price'] = price_format($row['shop_price']);
            $goods['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
            $goods['url'] = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);

            if ($promote_price > 0) {
                $price = $goods['promote_price'];
            } else {
                $price = $goods['shop_price'];
            }

            $str.='<li>
                        <div class="produc-content">
                            <div class="p-img"><a href="'.$goods['url'].'" target="_blank" title="'.$goods['goods_name'].'"><img src="'.$goods['goods_thumb'].'" width="142" height="142" /></a></div>
                            <div class="p-price">'.$price.'</div>
                            <div class="btns"><a href="'.$goods['url'].'" target="_blank" class="btn-9">立即购买</a></div>
                        </div>
                    </li>';
        }
        
    }
	
    return $str;
}

function insert_cartinfo() {
    $cart_info = insert_cart_info(1);
    $GLOBALS['smarty']->assign('cart_info', $cart_info);
    $output = $GLOBALS['smarty']->fetch('library/cartinfo.lbi');
    return $output;
}

/**
 * 调用进货单信息
 *
 * @access  public
 * @return  string
 * $num int by wang查询数据的数量
 */
function insert_cart_info($type = 0, $num = 0) {
    
    $num = !empty($num) ? intval($num) : 0;
    
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }

    $limit = '';

    if ($type == 1) {
        $limit = " LIMIT 0,4";
    }
    if (!empty($num) && $num > 0) {
        $limit = " LIMIT 0,$num";
    }
    //ecmoban模板堂 --zhuo end

    if($type > 0 || $type == 4){
        $sql = 'SELECT c.*,g.goods_thumb,g.goods_id,c.goods_number,c.goods_price' .
                ' FROM ' . $GLOBALS['ecs']->table('cart') . " AS c " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.goods_id=c.goods_id " .
                " WHERE " . $c_sess . " AND rec_type = '" . CART_GENERAL_GOODS . "' and c.stages_qishu='-1' AND c.store_id = 0 " . $limit; //这里限定不查出分期购商品 bylu;
        $row = $GLOBALS['db']->getAll($sql);

        $arr = array();
        $cart_value = '';
        foreach ($row AS $k => $v) {
            //判断商品类型，如果是超值礼包则修改链接和缩略图 by wu start
            if ($v['extension_code'] == 'package_buy') {
                $arr[$k]['url'] = 'package.php';
            } else if ($v['extension_code'] == 'group_buy') {
                $arr[$k]['url'] = build_uri('group_buy', array('act' => 'view', 'gbid' => $v['extension_id']), $v['goods_name']);
                $arr[$k]['goods_thumb'] = get_image_path($v['goods_id'], $v['goods_thumb'], true);
            } else if ($v['extension_code'] == 'presale') {
                $arr[$k]['url'] = build_uri('presale', array('act' => 'view', 'presaleid' => $v['extension_id']), $v['goods_name']);
                $arr[$k]['goods_thumb'] = get_image_path($v['goods_id'], $v['goods_thumb'], true);
            } else if ($v['extension_code'] == 'sample') {
                $arr[$k]['url'] = build_uri('sample', array('act' => 'view', 'cid' => $v['extension_id']), $v['goods_name']);
                $arr[$k]['goods_thumb'] = get_image_path($v['goods_id'], $v['goods_thumb'], true);
            } else if ($v['extension_code'] == 'wholesale') {
                $arr[$k]['url'] = build_uri('wholesale_goods', array( 'aid' => $v['extension_id']), $v['goods_name']);
                $arr[$k]['goods_thumb'] = get_image_path($v['goods_id'], $v['goods_thumb'], true);
            } else {
                $arr[$k]['url'] = build_uri('goods', array('gid' => $v['goods_id']), $v['goods_name']);
                $arr[$k]['goods_thumb'] = get_image_path($v['goods_id'], $v['goods_thumb'], true);
            }
            //判断商品类型，如果是超值礼包则修改链接和缩略图 by wu end
            $arr[$k]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                    sub_str($v['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $v['goods_name'];
            $arr[$k]['goods_number'] = $v['goods_number'];
            $arr[$k]['goods_name'] = $v['goods_name'];
            $arr[$k]['goods_price'] = price_format($v['goods_price']);
            $arr[$k]['rec_id'] = $v['rec_id'];
            $arr[$k]['warehouse_id'] = $v['warehouse_id'];
            $arr[$k]['area_id'] = $v['area_id'];
            $arr[$k]['extension_code'] = $v['extension_code'];
            $arr[$k]['is_gift'] = $v['is_gift'];

            if ($v['extension_code'] == 'package_buy') {
                $arr[$k]['package_goods_list'] = get_package_goods($v['goods_id']);
            }

            $cart_value = !empty($cart_value) ? $cart_value . ',' . $v['rec_id'] : $v['rec_id'];

            $properties = get_goods_properties($v['goods_id'], $v['warehouse_id'], $v['area_id'], $v['goods_attr_id'], 1);

            if ($properties['spe']) {
                $arr[$k]['spe'] = array_values($properties['spe']);
            } else {
                $arr[$k]['spe'] = array();
            }
        }
    }
    
    $sql = 'SELECT SUM(goods_number) AS number, SUM(goods_price * goods_number) AS amount' .
            ' FROM ' . $GLOBALS['ecs']->table('cart') .
            " WHERE " . $sess_id . " AND rec_type = '" . CART_GENERAL_GOODS . "' and stages_qishu='-1' AND store_id = 0"; //这里限定不查出分期购商品 bylu;
    $row = $GLOBALS['db']->getRow($sql);


    if ($row) {
        $number = intval($row['number']);
        $amount = floatval($row['amount']);
    } else {
        $number = 0;
        $amount = 0;
    }

    if ($type == 1) {
        $cart = array('goods_list' => $arr, 'number' => $number, 'amount' => price_format($amount, false), 'goods_list_count' => count($arr));

        return $cart;
    } elseif ($type == 2) {
        //by wang
        $cart = array('goods_list' => $arr, 'number' => $number, 'amount' => price_format($amount, false), 'goods_list_count' => count($arr));

        return $cart;
    } else {
        $GLOBALS['smarty']->assign('number', $number);
        $GLOBALS['smarty']->assign('amount', $amount);
        
        if($type == 4){
            $GLOBALS['smarty']->assign('cart_info', $row);
            $GLOBALS['smarty']->assign('cart_value', $cart_value); //by wang
            $GLOBALS['smarty']->assign('goods', $arr);
        }else{
            $GLOBALS['smarty']->assign('goods', array());
        }
        
        $GLOBALS['smarty']->assign('str', sprintf($GLOBALS['_LANG']['cart_info'], $number, price_format($amount, false)));
        
        $output = $GLOBALS['smarty']->fetch('library/cart_info.lbi');
        return $output;
    }
}

/**
 * 调用进货单加减返回信息
 *
 * @access  public
 * @return  string
 */
function insert_flow_info($goods_price,$market_price,$saving,$save_rate,$goods_amount,$real_goods_count)
{
    $GLOBALS['smarty']->assign('goods_price', $goods_price);
    $GLOBALS['smarty']->assign('market_price', $market_price);
    $GLOBALS['smarty']->assign('saving', $saving);
    $GLOBALS['smarty']->assign('save_rate', $save_rate);
    $GLOBALS['smarty']->assign('goods_amount', $goods_amount);
    $GLOBALS['smarty']->assign('real_goods_count', $real_goods_count);

    $output = $GLOBALS['smarty']->fetch('library/flow_info.lbi');
    return $output;
}

/**
 * 进货单弹出框返回信息
 *
 * @access  public
 * @return  string
 */
function insert_show_div_info($goods_number,$script_name,$goods_id,$goods_recommend,$goods_amount,$real_goods_count)
{
    $GLOBALS['smarty']->assign('goods_number', $goods_number);
    $GLOBALS['smarty']->assign('script_name', $script_name);
    $GLOBALS['smarty']->assign('goods_id', $goods_id);
    $GLOBALS['smarty']->assign('goods_recommend', $goods_recommend);
    $GLOBALS['smarty']->assign('goods_amount', $goods_amount);
    $GLOBALS['smarty']->assign('real_goods_count', $real_goods_count);

    $output = $GLOBALS['smarty']->fetch('library/show_div_info.lbi');
    return $output;
}


/**
 * 调用指定的广告位的广告
 *
 * @access  public
 * @param   integer $id     广告位ID
 * @param   integer $num    广告数量
 * @return  string
 */
function insert_ads($arr)
{
    static $static_res = NULL;
    
    $arr['num'] = intval($arr['num']);
	$arr['id'] = intval($arr['id']);

    $arr['id'] = isset($arr['id']) && !empty($arr['id']) ? intval($arr['id']) : 0;
    $arr['num'] = isset($arr['num']) && !empty($arr['num']) ? intval($arr['num']) : 0;
    
    $time = gmtime();
    if (!empty($arr['num']) && $arr['num'] != 1)
    {
        $sql  = 'SELECT a.ad_id, a.position_id, a.media_type, a.ad_link, a.ad_code, a.ad_name, p.ad_width, ' .
                    'p.ad_height, p.position_style, RAND() AS rnd ' .
                'FROM ' . $GLOBALS['ecs']->table('ad') . ' AS a '.
                'LEFT JOIN ' . $GLOBALS['ecs']->table('ad_position') . ' AS p ON a.position_id = p.position_id ' .
                "WHERE enabled = 1 AND start_time <= '" . $time . "' AND end_time >= '" . $time . "' ".
                    "AND a.position_id = '" . $arr['id'] . "' " .
                'ORDER BY rnd LIMIT ' . $arr['num'];
        $res = $GLOBALS['db']->GetAll($sql);
    }
    else
    {
        if ($static_res[$arr['id']] === NULL)
        {
            $sql  = 'SELECT a.ad_id, a.position_id, a.media_type, a.ad_link, a.ad_code, a.ad_name, p.ad_width, '.
                        'p.ad_height, p.position_style, RAND() AS rnd ' .
                    'FROM ' . $GLOBALS['ecs']->table('ad') . ' AS a '.
                    'LEFT JOIN ' . $GLOBALS['ecs']->table('ad_position') . ' AS p ON a.position_id = p.position_id ' .
                    "WHERE enabled = 1 AND a.position_id = '" . $arr['id'] .
                        "' AND start_time <= '" . $time . "' AND end_time >= '" . $time . "' " .
                    'ORDER BY rnd LIMIT 1';
            $static_res[$arr['id']] = $GLOBALS['db']->GetAll($sql);
        }
        $res = $static_res[$arr['id']];
    }
    $ads = array();
    $position_style = '';

    foreach ($res AS $row)
    {
        if ($row['position_id'] != $arr['id'])
        {
            continue;
        }
        $position_style = $row['position_style'];
        switch ($row['media_type'])
        {
            case 0: // 图片广告
                //OSS文件存储ecmoban模板堂 --zhuo start
                if((strpos($row['ad_code'], 'http://') === false && strpos($row['ad_code'], 'https://') === false)){
                    if($GLOBALS['_CFG']['open_oss'] == 1 && !empty($row['ad_code'])){
                        $bucket_info = get_bucket_info();
                        $src = $bucket_info['endpoint'] . DATA_DIR . '/afficheimg/' . $row['ad_code'];
                    }else{
                        $src = DATA_DIR . "/afficheimg/$row[ad_code]";
                    }
                }else{
                    $src = $row['ad_code'];
                }
                //OSS文件存储ecmoban模板堂 --zhuo end
         
                $ads[] = "<a href='affiche.php?ad_id=$row[ad_id]&amp;uri=" .urlencode($row["ad_link"]). "'    
                target='_blank'><img src='$src' width='" .$row['ad_width']. "' height='$row[ad_height]'
                border='0' /></a>";
                break;
            case 1: // Flash
                
                //OSS文件存储ecmoban模板堂 --zhuo start
                if((strpos($row['ad_code'], 'http://') === false && strpos($row['ad_code'], 'https://') === false)){
                    if($GLOBALS['_CFG']['open_oss'] == 1 && !empty($row['ad_code'])){
                        $bucket_info = get_bucket_info();
                        $src = $bucket_info['endpoint'] . DATA_DIR . '/afficheimg/' . $row['ad_code'];
                    }else{
                        $src = DATA_DIR . "/afficheimg/$row[ad_code]";
                    }
                }else{
                    $src = $row['ad_code'];
                }
                //OSS文件存储ecmoban模板堂 --zhuo end
                
                $ads[] = "<object classid=\"clsid:d27cdb6e-ae6d-11cf-96b8-444553540000\" " .
                         "codebase=\"http://fpdownload.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=8,0,0,0\"  " .
                           "width='$row[ad_width]' height='$row[ad_height]'>
                           <param name='movie' value='$src'>
                           <param name='quality' value='high'>
                           <embed src='$src' quality='high'
                           pluginspage='http://www.macromedia.com/go/getflashplayer'
                           type='application/x-shockwave-flash' width='$row[ad_width]'
                           height='$row[ad_height]'></embed>
                         </object>";
                break;
            case 2: // CODE
                $ads[] = $row['ad_code'];
                break;
            case 3: // TEXT
                
                //OSS文件存储ecmoban模板堂 --zhuo start
                if($GLOBALS['_CFG']['open_oss'] == 1 && !empty($row['ad_code'])){
                    $bucket_info = get_bucket_info();
                    $row['ad_code'] = $bucket_info['endpoint'] . $row['ad_code'];
                }
                //OSS文件存储ecmoban模板堂 --zhuo end
                    
                $ads[] = "<a href='affiche.php?ad_id=$row[ad_id]&amp;uri=" .urlencode($row["ad_link"]). "' target='_blank'>" .htmlspecialchars($row['ad_code']). '</a>';
                break;
        }
    }
    $position_style = 'str:' . $position_style;

    $need_cache = $GLOBALS['smarty']->caching;
    $GLOBALS['smarty']->caching = false;

    $GLOBALS['smarty']->assign('ads', $ads);
    $val = $GLOBALS['smarty']->fetch($position_style);

    $GLOBALS['smarty']->caching = $need_cache;

    return $val;
}

/**
 * 调用会员信息
 *
 * @access  public
 * @return  string
 */
function insert_member_info()
{
    $need_cache = $GLOBALS['smarty']->caching;
    $GLOBALS['smarty']->caching = false;

    if ($_SESSION['user_id'] > 0)
    {
        $GLOBALS['smarty']->assign('user_info', get_user_info());
    }
    else
    {
        if (!empty($_COOKIE['ECS']['username']))
        {
            $GLOBALS['smarty']->assign('ecs_username', stripslashes($_COOKIE['ECS']['username']));
        }
        $captcha = intval($GLOBALS['_CFG']['captcha']);
        if (($captcha & CAPTCHA_LOGIN) && (!($captcha & CAPTCHA_LOGIN_FAIL) || (($captcha & CAPTCHA_LOGIN_FAIL) && $_SESSION['login_fail'] > 2)) && gd_version() > 0)
        {
            $GLOBALS['smarty']->assign('enabled_captcha', 1);
            $GLOBALS['smarty']->assign('rand', mt_rand());
        }
    }
	
    $GLOBALS['smarty']->assign('shop_name', $GLOBALS['_CFG']['shop_name']);

    $GLOBALS['smarty']->assign('shop_reg_closed', $GLOBALS['_CFG']['shop_reg_closed']);

    $output = $GLOBALS['smarty']->fetch('library/member_info.lbi');

    $GLOBALS['smarty']->caching = $need_cache;

    return $output;
}

/**
 * 调用评论信息
 *
 * @access  public
 * @return  string
 */
function insert_comments($arr)
{
    $arr['id'] = isset($arr['id']) && !empty($arr['id']) ? intval($arr['id']) : 0;
    $arr['type'] = isset($arr['type']) ? addslashes($arr['type']) : '';
    
    $arr['id'] = intval($arr['id']);
	$arr['type'] = addslashes($arr['type']);

    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;

    /* 验证码相关设置 */
    if ((intval($GLOBALS['_CFG']['captcha']) & CAPTCHA_COMMENT) && gd_version() > 0)
    {
        $GLOBALS['smarty']->assign('enabled_captcha', 1);
        $GLOBALS['smarty']->assign('rand', mt_rand());
    }
    $GLOBALS['smarty']->assign('username',     stripslashes($_SESSION['user_name']));
    $GLOBALS['smarty']->assign('email',        $_SESSION['email']);
    $GLOBALS['smarty']->assign('comment_type', $arr['type']);
    $GLOBALS['smarty']->assign('id',           $arr['id']);
    $cmt = assign_comment($arr['id'],          $arr['type']);

    $GLOBALS['smarty']->assign('comments',     $cmt['comments']);
    $GLOBALS['smarty']->assign('pager',        $cmt['pager']);
    $GLOBALS['smarty']->assign('count',        $cmt['count']);
    $GLOBALS['smarty']->assign('size',        $cmt['size']);


    $val = $GLOBALS['smarty']->fetch('library/comments_list.lbi');

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;

    return $val;
}


/**
 * 调用评论信息
 *
 * @access  public
 * @return  string
 */
function insert_comments_single($arr)
{
    $arr['id'] = isset($arr['id']) && !empty($arr['id']) ? intval($arr['id']) : 0;
    $arr['type'] = isset($arr['type']) ? addslashes($arr['type']) : '';
    
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;

    /* 验证码相关设置 */
    if ((intval($GLOBALS['_CFG']['captcha']) & CAPTCHA_COMMENT) && gd_version() > 0)
    {
        $GLOBALS['smarty']->assign('enabled_captcha', 1);
        $GLOBALS['smarty']->assign('rand', mt_rand());
    }
    $GLOBALS['smarty']->assign('username',     stripslashes($_SESSION['user_name']));
    $GLOBALS['smarty']->assign('email',        $_SESSION['email']);
    $GLOBALS['smarty']->assign('comment_type', $arr['type']);
    $GLOBALS['smarty']->assign('id',           $arr['id']);
    $cmt = assign_comments_single($arr['id'],          $arr['type']);

    $GLOBALS['smarty']->assign('comments_single',     $cmt['comments']);
    $GLOBALS['smarty']->assign('single_pager',        $cmt['pager']);


    $val = $GLOBALS['smarty']->fetch('library/comments_single_list.lbi');

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;

    return $val;
}

/**
 * 调用商品购买记录
 *
 * @access  public
 * @return  string
 */
function insert_bought_notes($arr)
{
    $arr['id'] = isset($arr['id']) && !empty($arr['id']) ? intval($arr['id']) : 0;
    $arr['id'] = intval($arr['id']);
    
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;

    /* 商品购买记录 */
    $sql = 'SELECT u.user_name, og.goods_number, oi.add_time, IF(oi.order_status IN (2, 3, 4), 0, 1) AS order_status ' .
           'FROM ' . $GLOBALS['ecs']->table('order_info') . ' AS oi LEFT JOIN ' . $GLOBALS['ecs']->table('users') . ' AS u ON oi.user_id = u.user_id, ' . $GLOBALS['ecs']->table('order_goods') . ' AS og ' .
           'WHERE oi.order_id = og.order_id AND ' . gmtime() . ' - oi.add_time < 2592000 AND og.goods_id = ' . $arr['id'] . ' ORDER BY oi.add_time DESC LIMIT 5';
    $bought_notes = $GLOBALS['db']->getAll($sql);

    foreach ($bought_notes as $key => $val)
    {
        $bought_notes[$key]['add_time'] = local_date("Y-m-d G:i:s", $val['add_time']);
    }

    $sql = 'SELECT count(*) ' .
           'FROM ' . $GLOBALS['ecs']->table('order_info') . ' AS oi LEFT JOIN ' . $GLOBALS['ecs']->table('users') . ' AS u ON oi.user_id = u.user_id, ' . $GLOBALS['ecs']->table('order_goods') . ' AS og ' .
           'WHERE oi.order_id = og.order_id AND ' . gmtime() . ' - oi.add_time < 2592000 AND og.goods_id = ' . $arr['id'];
    $count = $GLOBALS['db']->getOne($sql);


    /* 商品购买记录分页样式 */
    $pager = array();
    $pager['page']         = $page = 1;
    $pager['size']         = $size = 5;
    $pager['record_count'] = $count;
    $pager['page_count']   = $page_count = ($count > 0) ? intval(ceil($count / $size)) : 1;;
    $pager['page_first']   = "javascript:gotoBuyPage(1,$arr[id])";
    $pager['page_prev']    = $page > 1 ? "javascript:gotoBuyPage(" .($page-1). ",$arr[id])" : 'javascript:;';
    $pager['page_next']    = $page < $page_count ? 'javascript:gotoBuyPage(' .($page + 1) . ",$arr[id])" : 'javascript:;';
    $pager['page_last']    = $page < $page_count ? 'javascript:gotoBuyPage(' .$page_count. ",$arr[id])"  : 'javascript:;';

    $GLOBALS['smarty']->assign('notes', $bought_notes);
    $GLOBALS['smarty']->assign('pager', $pager);


    $val= $GLOBALS['smarty']->fetch('library/bought_notes.lbi');

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;

    return $val;
}


/**
 * 调用在线调查信息
 *
 * @access  public
 * @return  string
 */
function insert_vote()
{
    $vote = get_vote();
    if (!empty($vote))
    {
        $GLOBALS['smarty']->assign('vote_id',     $vote['id']);
        $GLOBALS['smarty']->assign('vote',        $vote['content']);
    }
    $val = $GLOBALS['smarty']->fetch('library/vote.lbi');

    return $val;
}

//ecmoban模板堂 --zhuo start
/**
 * 通过类型与传入的ID获取广告内容  修改 zuo start
 *
 * @param string $type
 * @param int $id
 * @return string
 */	
 //广告位大图				
function insert_get_adv($arr)
{
	$need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;

    /* 验证码相关设置 */
    if ((intval($GLOBALS['_CFG']['captcha']) & CAPTCHA_COMMENT) && gd_version() > 0)
    {
        $GLOBALS['smarty']->assign('enabled_captcha', 1);
        $GLOBALS['smarty']->assign('rand', mt_rand());
    }
   
    $ad_type = substr($arr['logo_name'], 0, 12);
    $GLOBALS['smarty']->assign('ad_type', $ad_type);
    
    $name = $arr['logo_name'];
    $GLOBALS['smarty']->assign('ad_posti', get_ad_posti($name, $ad_type));

    $val = $GLOBALS['smarty']->fetch('library/position_get_adv.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;

	
	return $val;
}

function get_ad_posti($name = '', $ad_type = '') {
    
    $name = !empty($name) ? addslashes($name) : '';
    $name = "ad.ad_name = '$name' AND ";

    $time = gmtime();
    $sql = "SELECT ap.ad_width, ap.ad_height, ad.ad_id, ad.ad_name, ad.ad_code, ad.ad_link, ad.link_color, ad.start_time, ad.end_time, ad.ad_type, ad.goods_name FROM " . 
            $GLOBALS['ecs']->table('ad_position') . " AS ap LEFT JOIN " . $GLOBALS['ecs']->table('ad') . " AS ad ON ad.position_id = ap.position_id " .
            " WHERE " . $name . " ad.media_type=0 AND '$time' > ad.start_time AND '$time' < ad.end_time AND ad.enabled=1 AND theme = '" . $GLOBALS['_CFG']['template'] . "'";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $key => $row) {
        $arr[$key]['ad_name'] = $row['ad_name'];
        $arr[$key]['ad_code'] = $GLOBALS['_CFG']['site_domain'] . DATA_DIR . '/afficheimg/' . $row['ad_code'];

        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1 && !empty($row['ad_code'])) {
            $bucket_info = get_bucket_info();
            $arr[$key]['ad_code'] = $bucket_info['endpoint'] . DATA_DIR . '/afficheimg/' . $row['ad_code'];
        }
        //OSS文件存储ecmoban模板堂 --zhuo end

        if ($row["ad_link"]) {
            $row["ad_link"] = 'affiche.php?ad_id=' . $row['ad_id'] . '&amp;uri=' . urlencode($row["ad_link"]);
        }

        $arr[$key]['ad_link'] = $row["ad_link"];
        $arr[$key]['ad_width'] = $row['ad_width'];
        $arr[$key]['ad_height'] = $row['ad_height'];
        $arr[$key]['link_color'] = $row['link_color'];
        $arr[$key]['posti_type'] = $ad_type;
        $arr[$key]['start_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['start_time']);
        $arr[$key]['end_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['end_time']);
        $arr[$key]['ad_type'] = $row['ad_type'];
        $arr[$key]['goods_name'] = $row['goods_name'];
    }

    return $arr;
}

//广告位小图
function insert_get_adv_child($arr)
{
    $arr['id'] = isset($arr['id']) && !empty($arr['id']) ? intval($arr['id']) : 0;
    
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $arr['warehouse_id'] = isset($arr['warehouse_id']) ? intval($arr['warehouse_id']) : 0;
    $arr['area_id'] = isset($arr['area_id']) ? intval($arr['area_id']) : 0;

    /* 验证码相关设置 */
    if ((intval($GLOBALS['_CFG']['captcha']) & CAPTCHA_COMMENT) && gd_version() > 0)
    {
        $GLOBALS['smarty']->assign('enabled_captcha', 1);
        $GLOBALS['smarty']->assign('rand', mt_rand());
    }
	
    if($arr['id'] && $arr['ad_arr'] != ''){
        $id_name = '_'.$arr['id']."',";
        $str_ad = str_replace(',',$id_name,$arr['ad_arr']);
        $in_ad_arr = substr($str_ad,0,strlen($str_ad)-1);
    }else{
        $id_name = "',";
        $str_ad = str_replace(',',$id_name,$arr['ad_arr']);
        $in_ad_arr = substr($str_ad,0,strlen($str_ad)-1);
    }
    $ad_child = get_ad_posti_child($in_ad_arr, $arr['warehouse_id'], $arr['area_id']);
    $GLOBALS['smarty']->assign('ad_child', $ad_child);

    $merch = substr(substr($arr['ad_arr'],0,6),1);
    $users = substr(substr($arr['ad_arr'],0,8),1);
    $index_ad = substr(substr($arr['ad_arr'],0,9),1);
    $cat_goods_banner = substr(substr($arr['ad_arr'],0,17),1);
    $cat_goods_hot = substr(substr($arr['ad_arr'],0,14),1);
    $index_brand = substr(substr($arr['ad_arr'],0,19),1);

    $marticle = explode(',',$GLOBALS['_CFG']['marticle']);

    //添加楼层分类
    $GLOBALS['smarty']->assign('category_id', $arr['id']);
    $val = $GLOBALS['smarty']->fetch('library/position_get_adv_small.lbi');
    
    if (!defined('THEME_EXTENSION')) {
        if ($arr['id'] == $marticle[0] && $merch == 'merch') {
            $val = $GLOBALS['smarty']->fetch('library/position_merchantsIn.lbi');
        } elseif ($users == 'users_a') {
            $val = $GLOBALS['smarty']->fetch('library/position_merchantsIn_users.lbi');
        } elseif ($users == 'users_b') {
            $val = $GLOBALS['smarty']->fetch('library/position_merchants_usersBott.lbi');
        }
    }

    if($index_ad == 'index_ad'){
        $val = $GLOBALS['smarty']->fetch('library/index_ad_position.lbi');
    }elseif($cat_goods_banner == 'cat_goods_banner' && isset($arr['floor_style_tpl'])){
		$GLOBALS['smarty']->assign('floor_style_tpl', $arr['floor_style_tpl']);
        $val = $GLOBALS['smarty']->fetch('library/cat_goods_banner.lbi');
    }
    
    if($cat_goods_hot == 'cat_goods_hot'){
        $val = $GLOBALS['smarty']->fetch('library/cat_goods_hot.lbi');
    }
    
    if($index_brand == 'index_brand_banner'){
        $val = $GLOBALS['smarty']->fetch('library/index_brand_banner.lbi');
    }elseif($index_brand == 'index_group_banner'){
        $val = $GLOBALS['smarty']->fetch('library/index_group_banner.lbi');
    }elseif($index_brand == 'index_banner_group'){
        if (!defined('THEME_EXTENSION')) {
            $prom_ad = array();
            if (!empty($ad_child) && is_array($ad_child)) {
                foreach ($ad_child as $key => $val) {
                    if ($val['goods_info']['promote_end_date'] < gmtime()) {
                        unset($ad_child[$key]);
                    }
                }
            }
            $prom_ad = $ad_child;

            $GLOBALS['smarty']->assign('prom_ad', $prom_ad);
            $val = $GLOBALS['smarty']->fetch('library/index_banner_group_list.lbi');
        }
    }

    //热门推荐分类
    $top_cat = substr(substr($arr['ad_arr'], 0, 8), 1);
    if ($top_cat == 'top_cat') {
        $val = $GLOBALS['smarty']->fetch('library/top_cat.lbi');
    }

    //每日上限
    $new_cat = substr(substr($arr['ad_arr'], 0, 8), 1);
    if ($top_cat == 'new_cat') {
        $val = $GLOBALS['smarty']->fetch('library/new_cat.lbi');
    }

    //分类推荐小图标
    $wholesale_recommend = substr(substr($arr['ad_arr'], 0, 20), 1);
    if ($wholesale_recommend == 'wholesale_recommend') {
//        var_dump("sss");
    }

    //分类页楼层推荐 及 品牌
    $floor_recommend = substr(substr($arr['ad_arr'], 0, 16), 1);
    if ($floor_recommend == 'floor_recommend') {
        $GLOBALS['smarty']->assign('category_id', '001');
        $val = $GLOBALS['smarty']->fetch('library/floor_cat_banner.lbi');
    }

    $floor_brand = substr(substr($arr['ad_arr'], 0, 12), 1);
    if ($floor_brand == 'floor_brand') {
        $GLOBALS['smarty']->assign('category_id', '002');
        $val = $GLOBALS['smarty']->fetch('library/floor_cat_banner.lbi');
    }

    //登录页轮播广告 by wu
    $login_banner = substr(substr($arr['ad_arr'], 0, 13), 1);
    if ($login_banner == 'login_banner') {
        $val = $GLOBALS['smarty']->fetch('library/login_banner.lbi');
    }
    //顶级分类页（家电/食品）幻灯广告 by wu
	$top_style_cate_banner=substr(substr($arr['ad_arr'],0,22),1);
    if($top_style_cate_banner == 'top_style_elec_banner'){
        $val = $GLOBALS['smarty']->fetch('library/cat_top_ad.lbi');
    }elseif($top_style_cate_banner == 'top_style_food_banner'){
		$val = $GLOBALS['smarty']->fetch('library/cat_top_ad.lbi');
	}
	//顶级分类页（家电）底部横幅广告 by wu
	$top_style_cate_row=substr(substr($arr['ad_arr'],0,20),1);
    if($top_style_cate_row == 'top_style_elec_foot'){
        $val = $GLOBALS['smarty']->fetch('library/top_style_food.lbi');
    }
	//顶级分类页（家电/食品）楼层横幅广告 by wu
	$top_style_cate_row=substr(substr($arr['ad_arr'],0,19),1);
    if($top_style_cate_row == 'top_style_elec_row'){
        $val = $GLOBALS['smarty']->fetch('library/top_style_food.lbi');
    }elseif($top_style_cate_row == 'top_style_food_row'){
		$val = $GLOBALS['smarty']->fetch('library/top_style_food.lbi');
	}
	//顶级分类页（家电）品牌广告 by wu
	$top_style_elec_brand=substr(substr($arr['ad_arr'],0,21),1);
    if($top_style_elec_brand == 'top_style_elec_brand'){
        $val = $GLOBALS['smarty']->fetch('library/top_style_elec_brand.lbi');
    }
	//顶级分类页（家电/食品）楼层左侧广告 by wu
	$top_style_elec_left=substr(substr($arr['ad_arr'],0,20),1);
    if($top_style_elec_left == 'top_style_elec_left'){
        $val = $GLOBALS['smarty']->fetch('library/cat_top_floor_ad.lbi');
    }
	$top_style_food_left=substr(substr($arr['ad_arr'],0,20),1);
    if($top_style_food_left == 'top_style_food_left'){
        $val = $GLOBALS['smarty']->fetch('library/cat_top_floor_ad.lbi');
    }	
	//顶级分类页（食品）热门广告 by wu
	$top_style_food_hot=substr(substr($arr['ad_arr'],0,19),1);
    if($top_style_food_hot == 'top_style_food_hot'){
        $val = $GLOBALS['smarty']->fetch('library/top_style_food_hot.lbi');
    }

	//众筹首页轮播图 by wu
	$zc_index_banner=substr(substr($arr['ad_arr'],0,16),1);
    if($zc_index_banner == 'zc_index_banner'){
        $val = $GLOBALS['smarty']->fetch('library/zc_index_banner.lbi');
    }

    // 样品首页 大轮播图
    $sample_banner = substr(substr($arr['ad_arr'],0,14),1);
    if($sample_banner == 'sample_banner'){
        $val = $GLOBALS['smarty']->fetch('library/sample_banner.lbi');
    }

    //样品首页小轮播
    $sample_banner_small = substr(substr($arr['ad_arr'],0,20),1);
    if($sample_banner_small == 'sample_banner_small'){
        $val = $GLOBALS['smarty']->fetch('library/sample_banner_small.lbi');
    }
	
    // 预售首页 大轮播图
    $presale_banner = substr(substr($arr['ad_arr'],0,15),1);
    if($presale_banner == 'presale_banner'){
        $val = $GLOBALS['smarty']->fetch('library/presale_banner.lbi');
    }

    // 预售首页 大轮播图
    $presale_banner = substr(substr($arr['ad_arr'],0,12),1);
    if($presale_banner == 'home_banner'){
        $val = $GLOBALS['smarty']->fetch('library/home_banner.lbi');
    }
    
    //预售首页小轮播
    $presale_banner_small = substr(substr($arr['ad_arr'],0,21),1);
    if($presale_banner_small == 'presale_banner_small'){
        $val = $GLOBALS['smarty']->fetch('library/presale_banner_small.lbi');
    }
    //预售首页小轮播  左侧的banner
    $presale_banner_small_left = substr(substr($arr['ad_arr'],0,26),1);
    if($presale_banner_small_left == 'presale_banner_small_left')
    {
        $val = $GLOBALS['smarty']->fetch('library/presale_banner_small_left.lbi');
    }
	
    //新闻首页小轮播  左侧的banner
    $news_banner_small_left = substr(substr($arr['ad_arr'],0,23),1);
    if($news_banner_small_left == 'news_banner_small_left')
    {
        $val = $GLOBALS['smarty']->fetch('library/news_banner_small_left.lbi');
    }
	
    //新闻首页小轮播  右侧的banner
    $news_banner_small_right = substr(substr($arr['ad_arr'],0,24),1);
    if($news_banner_small_right == 'news_banner_small_right')
    {
        $val = $GLOBALS['smarty']->fetch('library/news_banner_small_right.lbi');
    }
	
    //预售首页小轮播  右侧的banner
    $presale_banner_small_right = substr(substr($arr['ad_arr'],0,27),1);
    if($presale_banner_small_right == 'presale_banner_small_right')
    {
        $val = $GLOBALS['smarty']->fetch('library/presale_banner_small_right.lbi');
    }
    //预售 新品页轮播图
    $presale_banner_new = substr(substr($arr['ad_arr'],0,19),1);
    if($presale_banner_new == 'presale_banner_new')
    {
        $val = $GLOBALS['smarty']->fetch('library/presale_banner_new.lbi');
    }
    //预售 抢先订页 轮播图
    $presale_banner_advance = substr(substr($arr['ad_arr'],0,23),1);
    if($presale_banner_advance == 'presale_banner_advance')
    {
        $val = $GLOBALS['smarty']->fetch('library/presale_banner_advance.lbi');
    }
    
    //预售 抢先订页 轮播图
    $presale_banner_category = substr(substr($arr['ad_arr'],0,24),1);
    if($presale_banner_category == 'presale_banner_category')
    {
        $val = $GLOBALS['smarty']->fetch('library/presale_banner_category.lbi');
    }
    
    //品牌首页分类下广告by wang
    $brand_cat_ad = substr(substr($arr['ad_arr'],0,13),1);
    if($brand_cat_ad == 'brand_cat_ad'){
        $val = $GLOBALS['smarty']->fetch('library/brand_cat_ad.lbi');
    }

    //顶级分类页首页幻灯片by wang
    $cat_top_ad = substr(substr($arr['ad_arr'],0,11),1);
    if($cat_top_ad == 'cat_top_ad'){

            $val = $GLOBALS['smarty']->fetch('library/cat_top_ad.lbi');
    }

    //顶级分类页首页新品首发左侧上广告by wang
    $cat_top_new_ad = substr(substr($arr['ad_arr'],0,15),1);

    if($cat_top_new_ad == 'cat_top_new_ad'){
            $val = $GLOBALS['smarty']->fetch('library/cat_top_new_ad.lbi');
    }

    //顶级分类页首页新品首发左侧下广告by wang
    $cat_top_newt_ad = substr(substr($arr['ad_arr'],0,16),1);

    if($cat_top_newt_ad == 'cat_top_newt_ad'){

            $val = $GLOBALS['smarty']->fetch('library/cat_top_newt_ad.lbi');
    }

    //顶级分类页首页楼层左侧广告幻灯片by wang
    $cat_top_floor_ad = substr(substr($arr['ad_arr'],0,17),1);
    if($cat_top_floor_ad == 'cat_top_floor_ad'){
            $val = $GLOBALS['smarty']->fetch('library/cat_top_floor_ad.lbi');
    }

    //首页幻灯片下优惠商品左侧广告by wang
    $cat_top_prom_ad = substr(substr($arr['ad_arr'],0,16),1);
    if($cat_top_prom_ad == 'cat_top_prom_ad'){
            $val = $GLOBALS['smarty']->fetch('library/cat_top_prom_ad.lbi');
    }

    //CMS频道页面左侧广告
    $article_channel_left_ad = substr(substr($arr['ad_arr'],0,24),1);

    if($article_channel_left_ad == 'article_channel_left_ad'){

            $val = $GLOBALS['smarty']->fetch('library/article_channel_left_ad.lbi');
    }

    //CMS频道页面商城公告下方广告
    $notic_down_ad = substr(substr($arr['ad_arr'],0,14),1);
    if($notic_down_ad == 'notic_down_ad'){
            $val = $GLOBALS['smarty']->fetch('library/notic_down_ad.lbi');
    }

    //品牌商品页面上方左侧广告
    $brand_list_left_ad = substr(substr($arr['ad_arr'],0,19),1);
    if($brand_list_left_ad == 'brand_list_left_ad'){
            $val = $GLOBALS['smarty']->fetch('library/brand_list_left_ad.lbi');
    }

    //品牌商品页面上方右侧广告
    $brand_list_right_ad = substr(substr($arr['ad_arr'],0,20),1);
    if($brand_list_right_ad == 'brand_list_right_ad'){
            $val = $GLOBALS['smarty']->fetch('library/brand_list_right_ad.lbi');
    }elseif($brand_list_right_ad == 'category_top_banner'){
        $val = $GLOBALS['smarty']->fetch('library/category_top_banner.lbi');
    }

    //搜索商品页面上方左侧广告
    $search_left_ad = substr(substr($arr['ad_arr'],0,15),1);
    if($search_left_ad == 'search_left_ad'){
            $val = $GLOBALS['smarty']->fetch('library/search_left_ad.lbi');
    }

    //搜索商品页面上方右侧广告
    $search_right_ad = substr(substr($arr['ad_arr'],0,16),1);
    if($search_right_ad == 'search_right_ad'){
            $val = $GLOBALS['smarty']->fetch('library/search_right_ad.lbi');
    }
	
    //搜索全部分类页左边广告
    $category_all_left = substr(substr($arr['ad_arr'],0,18),1);
    if($category_all_left == 'category_all_left'){
        $val = $GLOBALS['smarty']->fetch('library/category_all_left.lbi');
    }elseif($category_all_left == 'category_top_left'){
        $val = $GLOBALS['smarty']->fetch('library/category_top_left.lbi');
    }
    
    //搜索全部分类页右边广告
    $category_all_right = substr(substr($arr['ad_arr'],0,19),1);
    if($category_all_right == 'category_all_right'){
        $val = $GLOBALS['smarty']->fetch('library/category_all_right.lbi');
    }
    /*活动广告图  by kong*/
    $activity_top_banner=substr(substr($arr['ad_arr'],0,16),1);
    if($activity_top_banner == 'activity_top_ad'){
        $val = $GLOBALS['smarty']->fetch('library/activity_top_ad.lbi');
    }
    /*活动广告图  by kong*/
    $store_street_ad=substr(substr($arr['ad_arr'],0,16),1);
    if($store_street_ad == 'store_street_ad'){
        $val = $GLOBALS['smarty']->fetch('library/store_street_ad.lbi');
    }
    //品牌首页广告 qin
    $brandn_top_ad = substr(substr($arr['ad_arr'],0,14),1);
    $brandn_left_ad = substr(substr($arr['ad_arr'],0,15),1);
    if($brandn_top_ad == 'brandn_top_ad')
    {
        $val = $GLOBALS['smarty']->fetch('library/brandn_top_ad.lbi');
    }
    if($brandn_left_ad == 'brandn_left_ad')
    {
        $val = $GLOBALS['smarty']->fetch('library/brandn_left_ad.lbi');
    }


    /*  @author-bylu 优惠券首页顶部轮播广告 start  */
    $coupons_index = substr(substr($arr['ad_arr'],0,14),1);

    if($coupons_index == 'coupons_index'){
        $val = $GLOBALS['smarty']->fetch('library/coupons_index.lbi');
    }
    /*  @author-bylu  end  */
    
    /* 商品分类页 --zhuo start  */
    $category_top_ad = substr(substr($arr['ad_arr'],0,16),1);

    if($category_top_ad == 'category_top_ad'){
        $val = $GLOBALS['smarty']->fetch('library/category_top_ad.lbi');
    }
    /* 商品分类页 --zhuo end  */
	
    //新首页模板首页分类广告图 liu
    $recommend_category = substr(substr($arr['ad_arr'], 0, 19), 1);
    if ($recommend_category == 'recommend_category') {

        $val = $GLOBALS['smarty']->fetch('library/recommend_category.lbi');
    }



    //新首页模板达人专区广告 liu
    $export_field_ad = substr(substr($arr['ad_arr'], 0, 16), 1);
    if ($export_field_ad == 'expert_field_ad') {
        $val = $GLOBALS['smarty']->fetch('library/expert_field.lbi');
    }

    //新首页模板推荐店铺广告 liu
    $recommend_merchants = substr(substr($arr['ad_arr'], 0, 20), 1);
    if ($recommend_merchants == 'recommend_merchants') {
        $GLOBALS['smarty']->assign('cat_id', $arr['id']);
        $val = $GLOBALS['smarty']->fetch('library/recommend_merchants.lbi');
    }

    //秒杀活动顶部广告 liu
    $seckill_top_ad = substr(substr($arr['ad_arr'], 0, 15), 1);
    if ($seckill_top_ad == 'seckill_top_ad') {
        $val = $GLOBALS['smarty']->fetch('library/seckill_top_ad.lbi');
    }

    $floorBanner = substr(substr($arr['ad_arr'], 0, 13), 1);
    if ($floorBanner == 'floor_banner') {
        $val = $GLOBALS['smarty']->fetch('library/floor_banner.lbi');
    }

    //新首页模板楼层左侧广告 liu
    if (defined('THEME_EXTENSION')) {
        $cat_goods_ad_left = substr(substr($arr['ad_arr'], 0, 18), 1);
        if ($cat_goods_ad_left == 'cat_goods_ad_left') {
            $GLOBALS['smarty']->assign('floor_style_tpl', $arr['floor_style_tpl']);
            $val = $GLOBALS['smarty']->fetch('library/cat_goods_ad_left.lbi');
        }
        //顶级分类页（家电模板）全部分类右侧广告
        $cate_layer_elec_row = substr(substr($arr['ad_arr'], 0, 20), 1);
        if ($cate_layer_elec_row == 'cate_layer_elec_row') {
            $val = $GLOBALS['smarty']->fetch('library/cate_layer_right.lbi');
        }
        //顶级分类页（家电模板）轮播右侧广告
        $top_style_right_banner = substr(substr($arr['ad_arr'], 0, 23), 1);
        if ($top_style_right_banner == 'top_style_right_banner') {
            $val = $GLOBALS['smarty']->fetch('library/cate_layer_right.lbi');
        }
        //顶级分类页（家电模板）品牌左侧广告
        $top_style_elec_brand_left = substr(substr($arr['ad_arr'], 0, 26), 1);
        if ($top_style_elec_brand_left == 'top_style_elec_brand_left') {
            $val = $GLOBALS['smarty']->fetch('library/cate_layer_right.lbi');
        }
        //顶级分类页（女装）楼层右侧广告
        $cat_top_floor_ad_right = substr(substr($arr['ad_arr'], 0, 23), 1);
        if ($cat_top_floor_ad_right == 'cat_top_floor_ad_right') {
            $val = $GLOBALS['smarty']->fetch('library/cat_top_floor_ad_right.lbi');
        }
        //入驻首页头部广告
        $merchants_index_top = substr(substr($arr['ad_arr'], 0, 20), 1);
        if ($merchants_index_top == 'merchants_index_top') {
            $val = $GLOBALS['smarty']->fetch('library/merchants_index_top_ad.lbi');
        }
        //入驻首页类目广告
        $merchants_index_category_ad = substr(substr($arr['ad_arr'], 0, 28), 1);
        if ($merchants_index_category_ad == 'merchants_index_category_ad') {
            if ($arr['id'] > 0) {
                $sql = "SELECT cat_name FROM" . $GLOBALS['ecs']->table('category') . "WHERE parent_id = 0 AND is_show = 1 AND cat_id = '" . $arr['id'] . "'";
                $GLOBALS['smarty']->assign('cat_name', $GLOBALS['db']->getOne($sql));
            }
            $val = $GLOBALS['smarty']->fetch('library/merchants_index_category_ad.lbi');
        }
        //入驻首页成功案例
        $merchants_index_case_ad = substr(substr($arr['ad_arr'], 0, 24), 1);
        if ($merchants_index_case_ad == 'merchants_index_case_ad') {
            $val = $GLOBALS['smarty']->fetch('library/merchants_index_case_ad.lbi');
        }
        //入驻首页成功案例
        $wholesale_ad = substr(substr($arr['ad_arr'], 0, 13), 1);
        if ($wholesale_ad == 'wholesale_ad') {
            $val = $GLOBALS['smarty']->fetch('library/wholesale_ad.lbi');
        }
        
        $bonushome_ad = substr(substr($arr['ad_arr'], 0, 10), 1);
        if ($bonushome_ad == 'bonushome') {
            if($_COOKIE['bonushome_adv'] == 1){
                $val = '';
            }else{
                setcookie('bonushome_adv', 1, gmtime() + 3600 * 10, $GLOBALS['cookie_path'], $GLOBALS['cookie_domain']);
                $val = $GLOBALS['smarty']->fetch('library/bonushome_ad.lbi');
            }
        }
        
        //新首页模板楼层右侧广告 liu
        $cat_goods_ad_right = substr(substr($arr['ad_arr'], 0, 19), 1);
        if ($cat_goods_ad_right == 'cat_goods_ad_right') {
            $GLOBALS['smarty']->assign('floor_style_tpl', $arr['floor_style_tpl']);
            $val = $GLOBALS['smarty']->fetch('library/cat_goods_ad_right.lbi');
        }
    }

    //新模板品牌首页广告 by wu
    $brand_index_ad = substr(substr($arr['ad_arr'], 0, 15), 1);
    if ($brand_index_ad == 'brand_index_ad') {
        $val = $GLOBALS['smarty']->fetch('library/brand_index_ad.lbi');
    }

    //新模板首页楼层 liu
    $category_top_default_brand = substr(substr($arr['ad_arr'], 0, 27), 1);
    if ($category_top_default_brand == 'category_top_default_brand') {
        $val = $GLOBALS['smarty']->fetch('library/category_top_default_brand.lbi');
    }

    //新模板顶级分类页广告 by wu
    $category_top_ad = substr(substr($arr['ad_arr'], 0, 16), 1);
    if ($category_top_ad == 'category_top_default_best_head' || $category_top_ad == 'category_top_default_new_head') {
        $val = $GLOBALS['smarty']->fetch('library/category_top_default_head.lbi');
    } elseif ($category_top_ad == 'category_top_default_best_left' || $category_top_ad == 'category_top_default_new_left') {
        $val = $GLOBALS['smarty']->fetch('library/category_top_default_left.lbi');
    }

    $merchants_index = substr(substr($arr['ad_arr'], 0, 20), 1);
    if ($merchants_index == 'merchants_index') {
        $val = $GLOBALS['smarty']->fetch('library/category_top_banner.lbi');
    }

    $merchants_index_flow = substr(substr($arr['ad_arr'], 0, 21), 1);
    if ($merchants_index_flow == 'merchants_index_flow') {
        $val = $GLOBALS['smarty']->fetch('library/merchants_index_flow.lbi');
    }

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;

    return $val;
}

function get_ad_posti_child($cat_n_child = '', $warehouse_id = 0, $area_id = 0) {
    
    if ($cat_n_child == 'sy') {
        $cat_n_child = '';
    }
    if (!empty($cat_n_child)) {
        $cat_child = " ad.ad_name in($cat_n_child) and ";
    }

    $time = gmtime();
    $sql = "SELECT ap.ad_width, ap.ad_height, ad.ad_id, ad.ad_name, ad.ad_code, ad.ad_bg_code, ad.ad_link, ad.link_man, ad.link_color, ad.b_title, ad.s_title, ad.start_time, ad.end_time, ad.ad_type, ad.goods_name FROM " .
            $GLOBALS['ecs']->table('ad_position') . " AS ap " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('ad') . " AS ad ON ad.position_id = ap.position_id " .
            " WHERE " . $cat_child . " ad.media_type=0 AND '$time' > ad.start_time AND '$time' < ad.end_time and ad.enabled=1 AND theme = '" . $GLOBALS['_CFG']['template'] . "' ORDER BY ad_name,ad.ad_id ASC";
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res as $key => $row) {
        $key = $key + 1;
        $arr[$key]['ad_name'] = $row['ad_name'];
        //出来广告图片链接
        if($row['ad_code']){
            if (strpos($row['ad_code'], 'http://') === false && strpos($row['ad_code'], 'https://') === false)
            {
                $src = DATA_DIR . '/afficheimg/'. $row['ad_code'];
                
                $src = get_image_path(0, $src);
                
                $arr[$key]['ad_code'] = $src;
            }
            else
            {
                $src = $row['ad_code'];
                $src = str_replace('../', '', $src);
                $src = get_image_path(0, $src);
               $arr[$key]['ad_code'] = $src;
            }
        }
        
        if ($row['ad_bg_code']) {
            if (strpos($row['ad_bg_code'], 'http://') === false && strpos($row['ad_bg_code'], 'https://') === false) {
                $src = DATA_DIR . '/afficheimg/' . $row['ad_bg_code'];
                $src = get_image_path(0, $src);
                $arr[$key]['ad_bg_code'] = $src;
            } else {
                $src = $row['ad_bg_code'];
                $src = str_replace('../', '', $src);
                $src = get_image_path(0, $src);
                $arr[$key]['ad_bg_code'] = $src;
            }
        }

        $arr[$key]['ad_link'] = $row["ad_link"];
        $arr[$key]['link_man'] = $row["link_man"];
        $arr[$key]['ad_width'] = $row['ad_width'];
        $arr[$key]['ad_height'] = $row['ad_height'];
        $arr[$key]['link_color'] = $row['link_color'];
        $arr[$key]['b_title'] = $row['b_title'];
        $arr[$key]['s_title'] = $row['s_title'];
        $arr[$key]['start_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['start_time']);
        $arr[$key]['end_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['end_time']);
        $arr[$key]['ad_type'] = $row['ad_type'];
        $arr[$key]['goods_name'] = $row['goods_name'];

        if ($row['goods_name'] && $row['ad_type']) {
            $arr[$key]['goods_info'] = get_goods_ad_promote($row['goods_name'], $warehouse_id, $area_id);
            
            if((strpos($row['ad_link'], 'http://') !== false || strpos($row['ad_link'], 'https://') !== false)){
                $row['ad_link'] = '';
            }
            
            if (empty($row['ad_link'])) {
                $arr[$key]['ad_link'] = $arr[$key]['goods_info']['url'];
            }
        }else{
            if ($row["ad_link"]) {
                $row["ad_link"] = 'affiche.php?ad_id=' . $row['ad_id'] . '&amp;uri=' . urlencode($row["ad_link"]);
            }
        }
    }

    return $arr;
}

//广告位促销商品
function get_goods_ad_promote($goods_name = '', $warehouse_id = 0, $area_id = 0){
    
//    $goods_name = !empty($goods_name) ? str_replace("'", "", $goods_name) : '';
    $goods_name = !empty($goods_name) ? addslashes($goods_name) : '';
    
    $time = gmtime();
    $leftJoin = "";
    //ecmoban模板堂 --zhuo start
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    $where = '';
    if($GLOBALS['_CFG']['open_area_goods'] == 1){
            $leftJoin .= " left join " .$GLOBALS['ecs']->table('link_area_goods'). " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
    }

    if($GLOBALS['_CFG']['review_goods'] == 1){
            $where .= ' AND g.review_status > 2 ';
    }
    
    $where .= " AND g.goods_name = '$goods_name' ";
    //ecmoban模板堂 --zhuo end	

    $sql = 'SELECT g.goods_id, g.goods_name, g.goods_name_style, g.comments_number, g.sales_volume,g.market_price, ' . 
			' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
            "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " .
			"IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                "promote_start_date, promote_end_date, g.goods_brief, g.goods_thumb, goods_img, b.brand_name, " .
                "g.is_best, g.is_new, g.is_hot, g.is_promote, RAND() AS rnd " .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
			$leftJoin . 
            'LEFT JOIN ' . $GLOBALS['ecs']->table('brand') . ' AS b ON b.brand_id = g.brand_id ' .
            "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp ".
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ".
            'WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ' .
            " AND g.is_promote = 1 AND promote_start_date <= '$time' AND promote_end_date >= '$time' " . $where . "ORDER BY g.sort_order, g.last_update DESC";

    $row = $GLOBALS['db']->getRow($sql);
    
    if ($row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            $row['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
        } else {
            $row['promote_price'] = '';
        }

        $row['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
        $row['goods_img'] = get_image_path($row['goods_id'], $row['goods_img'], true);

        $row['market_price'] = price_format($row['market_price']);
        $row['shop_price'] = price_format($row['shop_price']);
        $row['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
    }
    
    return $row;
}
//ecmoban模板堂 --zhuo end

/*** 调用评论信息条数*/    
function insert_comments_count($arr) {

    $arr['id'] = isset($arr['id']) && !empty($arr['id']) ? intval($arr['id']) : 0;
    $arr['type'] = isset($arr['type']) && !empty($arr['type']) ? intval($arr['type']) : 0;

    $count = $GLOBALS['db']->getOne('SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('comment') .
            "WHERE id_value='$arr[id]'" . "AND comment_type='$arr[type]' AND status = 1 AND parent_id = 0");
    return $count;
}

/**
 * 调用对比栏上的浏览历史
 *
 * @access  public
 * @return  string
 * @author by guan
 */
function insert_history_arr()
{
    $str = '';
    if (!empty($_COOKIE['ECS']['history']))
    {
        $goods_cookie = json_decode(str_replace('\\', '', $_COOKIE['compareItems']), true);
        $goods_ids = array();
        if (!empty($goods_cookie)) {
            foreach ($goods_cookie as $key => $val) {
                $goods_ids[] = $val['d'];
            }
        }

        $where = db_create_in($_COOKIE['ECS']['history'], 'goods_id');
        $sql   = 'SELECT goods_id, goods_name,goods_type, market_price, goods_thumb, shop_price FROM ' . $GLOBALS['ecs']->table('goods') .
                " WHERE $where AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0";
        $query = $GLOBALS['db']->query($sql);
        $res = array();
        while ($row = $GLOBALS['db']->fetch_array($query))
        {
            $goods['goods_id'] = $row['goods_id'];
            $goods['goods_name'] = $row['goods_name'];
            $goods['goods_type'] = $row['goods_type'];
            $goods['market_price'] = price_format($row['market_price']);
            $goods['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $goods['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $goods['shop_price'] = price_format($row['shop_price']);
            $goods['url'] = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);
			
            if (in_array($goods['goods_id'], $goods_ids)) {
                $btn_class = "btn-compare-s_red";
                $history_select = 1;
            } else {
                $btn_class = "btn-compare-s";
                $history_select = 0;
            }

            $str.='<li style="width:226px;"><dl class="hasItem"><dt><a href="'.$goods['url'].'" target="_blank"><img src="'.$goods['goods_thumb'].'" alt="'.$goods['goods_name'].'" width="50" height="50" /></a></dt><dd><a class="diff-item-name" href="'.$goods['url'].'" target="_blank" title="'.$goods['goods_name'].'">'.$goods['short_name'].'</a><span class="p-price"><a id="history_btn'.$goods['goods_id'].'" class="btn-compare '.$btn_class.'" onmouseover="onchangeBtnClass(this, '.$goods['goods_id'].');" onmouseout="RemoveBtnClass(this, '.$goods['goods_id'].');" href="javascript:duibi_submit(this,'.$goods['goods_id'].');"><span>对比</span></a><strong class="J-p-1069555">' . $goods['shop_price'] . '</strong></span></dd>'.'</dl><input type="hidden" id="history_id'.$goods['goods_id'].'" value="'.$goods['goods_id'].'" /><input type="hidden" id="history_name'.$goods['goods_id'].'" value="'.$goods['goods_name'].'" /><input type="hidden" id="history_img'.$goods['goods_id'].'" value="'.$goods['goods_thumb'].'" /><input type="hidden" id="history_market'.$goods['goods_id'].'" value="'.$goods['market_price'].'" /><input type="hidden" id="history_shop'.$goods['goods_id'].'" value="'.$goods['shop_price'].'" /><input type="hidden" id="history_type'.$goods['goods_id'].'" value="'.$goods['goods_type'].'" /><input type="hidden" id="history_select'.$goods['goods_id'].'" value="'.$history_select.'" /></li>';
        }
    }
    return $str;
}

/**
 * 首页轮播图右侧登录入口
 */				
function insert_index_user_info()
{
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $GLOBALS['smarty']->assign('user_id', $_SESSION['user_id']);
    $GLOBALS['smarty']->assign('info',        get_user_default($_SESSION['user_id']));
    
    //首页文章栏目
    if (!empty($GLOBALS['_CFG']['index_article_cat'])) {
        
        $index_article_cat = array();
        $index_article_cat_arr = explode(',', $GLOBALS['_CFG']['index_article_cat']);
        
        foreach ($index_article_cat_arr as $key => $val) {
            $index_article_cat[] = assign_articles($val, 3);
        }
        
        $GLOBALS['smarty']->assign('index_article_cat', $index_article_cat);
    }

    $val = $GLOBALS['smarty']->fetch('library/index_user_info.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;
	
    return $val;
}

/**
 * 批发轮播图右侧登录入口
 */				
function insert_business_user_info()
{
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $GLOBALS['smarty']->assign('user_id', $_SESSION['user_id']);
    $GLOBALS['smarty']->assign('info',        get_user_default($_SESSION['user_id']));

    //批发首页文章栏目
    if (!empty($GLOBALS['_CFG']['wholesale_article_cat'])) {
        
        $wholesale_article_cat = array();
        $wholesale_article_cat_arr = explode(',', $GLOBALS['_CFG']['wholesale_article_cat']);
        
        foreach ($wholesale_article_cat_arr as $key => $val) {
            $wholesale_article_cat[] = assign_articles($val, 3);
        }
        
        $GLOBALS['smarty']->assign('wholesale_article_cat', $wholesale_article_cat);
    }

    $val = $GLOBALS['smarty']->fetch('library/business_user_info.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;
	
    return $val;
}

/**
 * 左侧分类树导航 
 * 新模板 cgxlm
 */				
function insert_category_tree_nav($arr = array())
{
    $act_type = isset($arr['act_type']) && !empty($arr['act_type']) ? addslashes($arr['act_type']) : 'wholesale';
    $nav_cat_model = isset($arr['cat_model']) && !empty($arr['cat_model']) ? addslashes($arr['cat_model']) : '';
    $nav_cat_num = isset($arr['cat_num']) && !empty($arr['cat_num']) ? intval($arr['cat_num']) : 0;
    
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $categories_pro = get_category_tree_leve_one();
    foreach ($categories_pro as $key => $categories) {
        $cat_id = $categories['id'];
        $child_tree = cat_list($cat_id, 1);

        $child_tree_array = [];
        if ($child_tree) {
            foreach ($child_tree as $k => $child) {
                if ($child['child_tree']) {
                    $child_tree_array = array_merge($child_tree_array, $child['child_tree']);
                }
            }
        }
        $categories_pro[$key]['child_tree'] = $child_tree_array;
    }

    $GLOBALS['smarty']->assign('categories_pro',  $categories_pro); // 分类树加强版
    
    $GLOBALS['smarty']->assign('nav_cat_model',  $nav_cat_model);
    $GLOBALS['smarty']->assign('nav_cat_num',  $nav_cat_num);
    $GLOBALS['smarty']->assign('act_type',  $act_type);
    
    $val = $GLOBALS['smarty']->fetch('library/category_tree_nav.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;
	
    return $val;
}

/**
 * 分类楼层广告
 * by zxk
 */
function insert_category_floor($arr = array()) {
    //加载配置
    $_CFG = load_config();
    $act_type = isset($arr['act_type']) && !empty($arr['act_type']) ? addslashes($arr['act_type']) : 'wholesale';

    //获取所有一级分类
    $catetorys = get_category_list(0);
    $GLOBALS['smarty']->assign('catetorys', $catetorys);
    $ads = '';
    for ($i = 1; $i <= $_CFG['auction_ad']; $i++)
    {
        $ads .= '\'wholesale_cat_ad' . $i . ',';
    }

    $val = $GLOBALS['smarty']->fetch('library/page_category_floor.lbi');
    return $val;
}

/**
 * 首页悬浮登录入口
 * by yanxin
 */				
function insert_index_suspend_info()
{
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $GLOBALS['smarty']->assign('user_id', $_SESSION['user_id']);
    $GLOBALS['smarty']->assign('info',        get_user_default($_SESSION['user_id']));

    $val = $GLOBALS['smarty']->fetch('library/index_suspend_info.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;
	
    return $val;
}

/**
 * 首页秒杀活动
 */				
function insert_index_seckill_goods()
{
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $seckill_goods = get_seckill_goods();
    $GLOBALS['smarty']->assign('seckill_goods', $seckill_goods);       //秒杀活动
    $GLOBALS['smarty']->assign('url_seckill',     setRewrite('seckill.php'));	
    
    $val = $GLOBALS['smarty']->fetch('library/seckill_goods_list.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;
	
    return $val;
}

/**
 * 网站左侧浮动框内容
 */				
function insert_user_menu_position()
{
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;

    $rank = get_rank_info();
    if ($rank) {
        $GLOBALS['smarty']->assign('rank_name', $rank['rank_name']);
    }

    $GLOBALS['smarty']->assign('info',        get_user_default($_SESSION['user_id']));

    $cart_info = insert_cart_info(1);
    $GLOBALS['smarty']->assign('cart_info',        $cart_info);
	
    $val = $GLOBALS['smarty']->fetch('library/user_menu_position.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;
	
    return $val;
}

/**
 * 商品详情页讨论圈title
 */				
function insert_goods_comment_title($arr)
{
    $arr['goods_id'] = isset($arr['goods_id']) && !empty($arr['goods_id']) ? intval($arr['goods_id']) : 0;
    
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $goods_id = $arr['goods_id'];
    $comment_allCount = get_goods_comment_count($goods_id);
    $comment_good = get_goods_comment_count($goods_id, 1);
    $comment_middle = get_goods_comment_count($goods_id, 2);
    $comment_short = get_goods_comment_count($goods_id, 3);

    $GLOBALS['smarty']->assign('comment_allCount',        $comment_allCount);
    $GLOBALS['smarty']->assign('comment_good',        $comment_good);
    $GLOBALS['smarty']->assign('comment_middle',        $comment_middle);
    $GLOBALS['smarty']->assign('comment_short',        $comment_short);

    $val = $GLOBALS['smarty']->fetch('library/goods_comment_title.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;
	
    return $val;
}

/**
 * 商品详情页讨论圈title
 */				
function insert_goods_discuss_title($arr)
{
    $arr['goods_id'] = isset($arr['goods_id']) && !empty($arr['goods_id']) ? intval($arr['goods_id']) : 0;
    
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $goods_id = $arr['goods_id'];
    $all_count = get_discuss_type_count($goods_id); //帖子总数
    $t_count = get_discuss_type_count($goods_id, 1); //讨论帖总数
    $w_count = get_discuss_type_count($goods_id, 2); //问答帖总数
    $q_count = get_discuss_type_count($goods_id, 3); //圈子帖总数
    $s_count = get_commentImg_count($goods_id); //晒单帖总数
	
	$all_count += $s_count;//总数加上晒单贴的总数

    $GLOBALS['smarty']->assign('all_count',       $all_count);   
    $GLOBALS['smarty']->assign('t_count',       $t_count);    
    $GLOBALS['smarty']->assign('w_count',       $w_count);    
    $GLOBALS['smarty']->assign('q_count',       $q_count);    
    $GLOBALS['smarty']->assign('s_count',       $s_count);
        
    $val = $GLOBALS['smarty']->fetch('library/goods_discuss_title.lbi');  

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;
	
    return $val;
}

//获取头部城市筛选模块
function insert_header_region()
{
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;

    $GLOBALS['smarty']->assign('site_domain', $GLOBALS['_CFG']['site_domain']);
    $val = $GLOBALS['smarty']->fetch('library/header_region_style.lbi');

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;

    return $val;
}

//by wang获得推荐品牌信息
function insert_recommend_brands($arr,$brand_id='') {
    
    $arr['num'] = isset($arr['num']) && !empty($arr['num']) ? intval($arr['num']) : 0;
    $where_brand = '';
    if(!empty($brand_id)){
        $where_brand = " AND b.brand_id in ($brand_id) ";
    }
    $where = ' where be.is_recommend=1 AND b.is_show = 1 '.$where_brand.' order by b.sort_order asc ';
    if (intval($arr['num']) > 0) {
        $where.=" limit 0," . intval($arr['num']);
    }
    $sql = "select b.* from " . $GLOBALS['ecs']->table('brand') . " as b left join " . $GLOBALS['ecs']->table('brand_extend') . " as be on b.brand_id=be.brand_id " . $where;
    $val = '';
    $recommend_brands = $GLOBALS['db']->getAll($sql);

    foreach ($recommend_brands AS $key => $val) {
		$recommend_brands[$key]['brand_logo'] = empty($val['brand_logo']) ? str_replace(array('../'), '', $GLOBALS['_CFG']['no_brand']) : DATA_DIR . '/brandlogo/'.$val['brand_logo'];
        if($val['site_url'] && strlen($val['site_url']) > 8){
            $recommend_brands[$key]['url'] = $val['site_url'];
        }else{
             $recommend_brands[$key]['url'] = build_uri('brandn', array('bid' => $val['brand_id']), $val['brand_name']);
        }
	if (defined('THEME_EXTENSION')){
		$recommend_brands[$key]['collect_count'] = get_collect_brand_user_count($val['brand_id']);
		$recommend_brands[$key]['is_collect'] = get_collect_user_brand($val['brand_id']);
    	}
        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1 && $val['brand_logo']) {
            $bucket_info = get_bucket_info();
            $recommend_brands[$key]['brand_logo'] = $bucket_info['endpoint'] . DATA_DIR . '/brandlogo/' . $val['brand_logo'];
        }
        //OSS文件存储ecmoban模板堂 --zhuo end    
    }

    if (count($recommend_brands) > 0) {
        $need_cache = $GLOBALS['smarty']->caching;
        $need_compile = $GLOBALS['smarty']->force_compile;

        $GLOBALS['smarty']->caching = false;
        $GLOBALS['smarty']->force_compile = true;

        $GLOBALS['smarty']->assign('recommend_brands', $recommend_brands);
        $val = $GLOBALS['smarty']->fetch('library/index_brand_street.lbi');

        $GLOBALS['smarty']->caching = $need_cache;
        $GLOBALS['smarty']->force_compile = $need_compile;
    }
    return $val;
}

//by wang 随机关键字
function insert_rand_keyword()
{
    $searchkeywords = explode(',', trim($GLOBALS['_CFG']['search_keywords']));
    if (count($searchkeywords) > 0) {
        return $searchkeywords[rand(0, count($searchkeywords) - 1)];
    } else {
        return '';
    }
}

//获得楼层设置内容by wang
function insert_get_floor_content($arr) {
    $filename = !empty($arr['filename']) ? addslashes(trim($arr['filename'])) : '0';
    $region = !empty($arr['region']) ? addslashes(trim($arr['region'])) : '0';
    $id = !empty($arr['id']) ? intval($arr['id']) : '0';
    $field = !empty($arr['field']) ? addslashes(trim($arr['field'])) : 'brand_id';
    $theme = $GLOBALS['_CFG']['template'];

    $sql = "SELECT " . $field . " FROM " . $GLOBALS['ecs']->table('floor_content') . " where filename='$filename' and region='$region' and id='$id' and theme='$theme'";

    return $GLOBALS['db']->getCol($sql);
}

/**
 * 调用浏览历史 //ecmoban模板堂 --zhuo
 *
 * @access  public
 * @return  string
 */
function insert_history_goods($parameter) {
    
    $warehouse_id = !empty($parameter['warehouse_id']) ? intval($parameter['warehouse_id']) : 0;
    $goods_id = !empty($parameter['goods_id']) ? intval($parameter['goods_id']) : 0;
    $area_id = !empty($parameter['area_id']) ? intval($parameter['area_id']) : 0;
    
    if (empty($warehouse_id)) {
        $warehouse_id = isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id']) ? intval($_COOKIE['region_id']) : 0;
    }

    $arr = array();
    if (!empty($_COOKIE['ECS']['history'])) {
        $where = db_create_in($_COOKIE['ECS']['history'], 'g.goods_id');
        if ($GLOBALS['_CFG']['review_goods'] == 1) {
            $where .= ' AND g.review_status > 2 ';
        }
        $leftJoin = '';

        $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
        }

        if ($goods_id > 0) {
            $where .= " AND g.goods_id <> '$goods_id' ";
        }

        $sql = 'SELECT g.goods_id, g.user_id, g.goods_name, g.goods_thumb, g.goods_img, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
                "IFNULL(IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]'), g.shop_price * '$_SESSION[discount]')  AS shop_price, " . 
                "IFNULL(IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)), g.promote_price) AS promote_price, " .
                'g.market_price, g.sales_volume, g.model_attr, g.promote_start_date, g.promote_end_date, g.product_price, g.product_promote_price' .
                ' FROM ' . $GLOBALS['ecs']->table('goods') . " as g " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                $leftJoin .
                " WHERE $where AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 order by INSTR('" . $_COOKIE['ECS']['history'] . "',g.goods_id) limit 0,10";

        $res = $GLOBALS['db']->query($sql);

        while ($row = $GLOBALS['db']->fetchRow($res)) {

            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            } else {
                $promote_price = 0;
            }

            /**
             * 重定义商品价格
             * 商品价格 + 属性价格
             * start
             */
            $price_info = get_goods_one_attr_price($row, $warehouse_id, $area_id, $promote_price);
            $row = !empty($row) ? array_merge($row, $price_info) : $row;
            $promote_price = $row['promote_price'];
            /**
             * 重定义商品价格
             * end
             */
            
            $arr[$row['goods_id']]['goods_id'] = $row['goods_id'];
            $arr[$row['goods_id']]['goods_name'] = $row['goods_name'];
            $arr[$row['goods_id']]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                    sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $arr[$row['goods_id']]['goods_thumb'] = get_image_path($row['goods_id'], $row['goods_thumb'], true);
            $arr[$row['goods_id']]['goods_img'] = get_image_path($row['goods_id'], $row['goods_img']);
            $arr[$row['goods_id']]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
            $arr[$row['goods_id']]['sales_volume'] = $row['sales_volume'];
            $arr[$row['goods_id']]['shop_name'] = get_shop_name($row['user_id'], 1); //店铺名称
            $arr[$row['goods_id']]['shopUrl'] = build_uri('merchants_store', array('urid' => $row['user_id']));

            $arr[$row['goods_id']]['market_price'] = price_format($row['market_price']);
            $arr[$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
            $arr[$row['goods_id']]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
        }
    }

    $GLOBALS['smarty']->assign('history_goods', $arr);
    $val = $GLOBALS['smarty']->fetch('library/history_goods.lbi');

    return $val;
}

//调用浏览记录 by wu
function insert_history_goods_pro() {
    $history_goods = get_history_goods(0, $GLOBALS['region_id'], $GLOBALS['area_info']['region_id']);
    $history_count = array();
    if ($history_goods) {
        for ($i = 0; $i < count($history_goods) / 6; $i++) {
            //$history_count[$i]=$i; 修改浏览记录 by wu
            for ($j = 0; $j < 6; $j++) {
                if (pos($history_goods)) {
                    $history_count[$i][] = pos($history_goods);
                    next($history_goods);
                } else {
                    break;
                }
            }
        }
    }

    $GLOBALS['smarty']->assign('history_count', $history_count);
    $GLOBALS['smarty']->assign('history_goods', $history_goods);

    $val = $GLOBALS['smarty']->fetch('library/cate_top_history_goods.lbi');
    return $val;
}

//众筹支持者列表 by wu
function get_backer_list($zcid = 0, $page = 1, $size = 10) {

    $zcid = !empty($zcid) ? intval($zcid) : 0;
    $page = !empty($page) ? intval($page) : 0;
    $size = !empty($size) ? intval($size) : 0;

    $GLOBALS['smarty']->assign('zcid', $zcid);

    //获取总数量
    $sql = " SELECT join_num from " . $GLOBALS['ecs']->table('zc_project') . " where id='$zcid' ";
    $record_count = $GLOBALS['db']->getOne($sql);

    //支持者列表
    $sql = " SELECT oi.user_id,u.user_name,u.user_picture,zg.price " .
            " FROM " . $GLOBALS['ecs']->table('order_info') . " as oi " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('users') . " as u on u.user_id=oi.user_id " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('zc_goods') . " as zg on zg.id=oi.zc_goods_id " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('zc_project') . " as zd on zd.id=zg.pid " .
            " WHERE oi.is_zc_order=1 AND oi.pay_status=2 AND zd.id = '$zcid' " .
            " ORDER BY oi.order_id DESC " .
            " LIMIT " . (($page - 1) * $size) . "," . $size;
    $backer_list = $GLOBALS['db']->getAll($sql);

    //补充信息
    foreach ($backer_list as $key => $val) {
        //用户名匿名
        $backer_list[$key]['user_name'] = setAnonymous($val['user_name']);

        //格式化价格
        $backer_list[$key]['formated_price'] = price_format($val['price']);

        //支持数量
        $sql = " select COUNT(order_id) FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE is_zc_order=1 AND user_id=" . $val['user_id'];
        $backer_list[$key]['back_num'] = intval($GLOBALS['db']->getOne($sql));
    }
    $GLOBALS['smarty']->assign('backer_list', $backer_list);

    //页面跳转信息
    $GLOBALS['smarty']->assign('curr_page', $page); //当前页
    $GLOBALS['smarty']->assign('prev_page', $page - 1);
    $GLOBALS['smarty']->assign('next_page', $page + 1);
    $GLOBALS['smarty']->assign('third_page', $page + 2);
    $pager = get_pager('', array('act' => 'list'), $record_count, $page, $size);
    $GLOBALS['smarty']->assign('pager', $pager);

    $html = $GLOBALS['smarty']->fetch('library/zc_backer_list.lbi');
    return $html;
}

//众筹话题列表 by wu
function get_topic_list($zcid = 0, $page = 1, $size = 10) {

    $zcid = !empty($zcid) ? intval($zcid) : 0;
    $page = !empty($page) ? intval($page) : 0;
    $size = !empty($size) ? intval($size) : 0;
    
    $GLOBALS['smarty']->assign('zcid', $zcid);

    //总数量
    $sql = " SELECT COUNT(topic_id) FROM " . $GLOBALS['ecs']->table('zc_topic') . " WHERE pid='$zcid' AND parent_topic_id=0 AND topic_status = 1 ";
    $record_count = $GLOBALS['db']->getOne($sql);

    //话题列表
    $sql = " SELECT * FROM " . $GLOBALS['ecs']->table('zc_topic') .
            " WHERE pid='$zcid' AND parent_topic_id=0 AND topic_status = 1 " .
            " ORDER BY topic_id DESC " .
            " LIMIT " . (($page - 1) * $size) . "," . $size;
    $topic_list = $GLOBALS['db']->getAll($sql);

    //补充信息
    foreach ($topic_list as $key => $val) {
        //用户名、头像
        $sql = " select user_name,user_picture from " . $GLOBALS['ecs']->table('users') . " where user_id=" . $val['user_id'];
        $user_info = $GLOBALS['db']->getRow($sql);
        $topic_list[$key]['user_name'] = setAnonymous($user_info['user_name']);
        $topic_list[$key]['user_picture'] = $user_info['user_picture'];

        //时间的处理
        $topic_list[$key]['time_past'] = get_time_past($val['add_time'], gmtime());

        //子评论列表
        $sql = " select * from " . $GLOBALS['ecs']->table('zc_topic') . " where parent_topic_id=" . $val['topic_id'] . " AND topic_status = 1 order by topic_id desc limit 5";
        $child_topic = $GLOBALS['db']->getAll($sql);
        if (count($child_topic) > 0) {
            foreach ($child_topic as $k => $v) {
                $sql = " select user_name,user_picture from " . $GLOBALS['ecs']->table('users') . " where user_id=" . $v['user_id'];
                $child_user_info = $GLOBALS['db']->getRow($sql);
                $child_topic[$k]['user_name'] = setAnonymous($child_user_info['user_name']);
                $child_topic[$k]['user_picture'] = $child_user_info['user_picture'];
                $child_topic[$k]['time_past'] = get_time_past($v['add_time'], gmtime());

                //回复对象
                if ($v['reply_topic_id'] > 0) {
                    $sql = " select u.user_name from " . $GLOBALS['ecs']->table('zc_topic') . " as zt " .
                            " left join " . $GLOBALS['ecs']->table('users') . " as u on u.user_id=zt.user_id " .
                            " where zt.topic_id= " . $v['reply_topic_id'] . " AND zt.topic_status = 1 ";
                    $reply_user_info = $GLOBALS['db']->getRow($sql);
                    $child_topic[$k]['reply_user'] = setAnonymous($reply_user_info['user_name']);
                }
            }
        }
        $topic_list[$key]['child_topic'] = $child_topic;

        //子评论数量
        $sql = " select count(*) from " . $GLOBALS['ecs']->table('zc_topic') . " where parent_topic_id=" . $val['topic_id'] . " AND topic_status = 1 order by topic_id desc ";
        $topic_list[$key]['child_topic_num'] = $GLOBALS['db']->getOne($sql);
    }
    $GLOBALS['smarty']->assign('topic_list', $topic_list);
    //var_dump($topic_list);
    //页面跳转信息
    $GLOBALS['smarty']->assign('curr_page', $page); //当前页
    $GLOBALS['smarty']->assign('prev_page', $page - 1);
    $GLOBALS['smarty']->assign('next_page', $page + 1);
    $GLOBALS['smarty']->assign('third_page', $page + 2);
    $pager = get_pager('', array('act' => 'list'), $record_count, $page, $size);
    $GLOBALS['smarty']->assign('pager', $pager);

    $html = $GLOBALS['smarty']->fetch('library/zc_topic_list.lbi');
    return $html;
}

/* 会员中心语言函数 */
function insert_get_page_no_records($arr) {
    if (isset($GLOBALS['_LANG'][$arr['filename']][$arr['act']]['no_records'])) {
        return $GLOBALS['_LANG'][$arr['filename']][$arr['act']]['no_records'];
    } else {
        return $GLOBALS['_LANG']['no_records'];
    }
}

/**
 * 调用商品地区信息
 *
 * @access  public
 * @return  string
 */
function insert_goods_delivery_area_js($arr)
{
    $need_cache = $GLOBALS['smarty']->caching;
    $need_compile = $GLOBALS['smarty']->force_compile;

    $GLOBALS['smarty']->caching = false;
    $GLOBALS['smarty']->force_compile = true;
    
    $area = array(
        'goods_id' => $arr['area']['goods_id'],
        'region_id' => $arr['area']['region_id'],
        'province_id' => $arr['area']['province_id'],
        'city_id' => $arr['area']['city_id'],
        'district_id' => $arr['area']['district_id'],
        'street_id' => $arr['area']['street_id'],
        'street_list' => $arr['area']['street_list'],
        'merchant_id' => $arr['area']['merchant_id'],
        'user_id' => $arr['area']['user_id'],
        'area_id' => $arr['area']['area_id']
    );

    $area['region_id'] = isset($_COOKIE['area_region']) && !empty($_COOKIE['area_region']) ? $_COOKIE['area_region'] : $area['region_id'];
    $area['province_id'] = isset($_COOKIE['province']) ? $_COOKIE['province'] : $area['province_id'];
    $area['city_id'] = isset($_COOKIE['city']) ? $_COOKIE['city'] : $area['city_id'];
    $area['district_id'] = isset($_COOKIE['district']) ? $_COOKIE['district'] :$area['district_id'];
    $area['street_id'] = isset($_COOKIE['street']) ? $_COOKIE['street'] : $area['street_id'];
    $area['street_list'] = isset($_COOKIE['street_list']) ? $_COOKIE['street_list'] : $area['street_list'];
    
    $GLOBALS['smarty']->assign('area',        $area);
    $val = $GLOBALS['smarty']->fetch('library/goods_delivery_area_js.lbi');

    $GLOBALS['smarty']->caching = $need_cache;
    $GLOBALS['smarty']->force_compile = $need_compile;

    return $val;
}

/**
 * 调用批发进货单信息
 */

function insert_wholesale_cart_info()
{
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $c_sess = " wc.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $c_sess = " wc.session_id = '" . real_cart_mac_ip() . "' ";
    }
    
    //获取商品
    $sql = 'SELECT wc.rec_id, wc.goods_name, wc.goods_attr_id,wc.goods_price, g.goods_thumb,g.goods_id,w.act_id,wc.goods_number,wc.goods_price' .
            ' FROM ' . $GLOBALS['ecs']->table('wholesale_cart') . " AS wc " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.goods_id=wc.goods_id " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('wholesale') . " AS w ON w.goods_id=wc.goods_id " .
            " WHERE " . $c_sess;
    $row = $GLOBALS['db']->getAll($sql);
    $arr = array();
    $cart_value = '';
    foreach ($row AS $k => $v) {
        $arr[$k]['rec_id'] = $v['rec_id'];
        $arr[$k]['url'] = build_uri('wholesale_goods', array('aid' => $v['act_id']), $v['goods_name']);
        $arr[$k]['goods_thumb'] = get_image_path($v['goods_id'], $v['goods_thumb'], true);
        $arr[$k]['goods_number'] = $v['goods_number'];
        $arr[$k]['goods_price'] = $v['goods_price'];
        $arr[$k]['goods_name'] = $v['goods_name'];
        @$arr[$k]['goods_attr'] = array_values(get_wholesale_attr_array($v['goods_attr_id']));
        $cart_value = !empty($cart_value) ? $cart_value . ',' . $v['rec_id'] : $v['rec_id'];
    }
    $sql = 'SELECT COUNT(rec_id) AS cart_number, SUM(goods_number) AS number, SUM(goods_price * goods_number) AS amount' .
            ' FROM ' . $GLOBALS['ecs']->table('wholesale_cart') .
            " WHERE " . $sess_id;
    $row = $GLOBALS['db']->getRow($sql);
    if ($row) {
        $cart_number = intval($row['cart_number']);
        $number = intval($row['number']);
        $amount = price_format(floatval($row['amount']));
    } else {
        $cart_number = 0;
        $number = 0;
        $amount = 0;
    }
    $GLOBALS['smarty']->assign('cart_value', $cart_value);
    $GLOBALS['smarty']->assign('number', $number);
    $GLOBALS['smarty']->assign('amount', $amount);
    $GLOBALS['smarty']->assign('str', $cart_number);
    $GLOBALS['smarty']->assign('goods', $arr);

    $output = $GLOBALS['smarty']->fetch('library/wholesale_cart_info.lbi');
    return $output;
}

/**
 * 调用批发进货单加减返回信息
 *
 * @access  public
 * @return  string
 */
function insert_wholesale_flow_info($goods_price)
{
    $GLOBALS['smarty']->assign('goods_price', $goods_price);

    $output = $GLOBALS['smarty']->fetch('library/wholesale_flow_info.lbi');
    return $output;
}

//by wang 随机关键字
function insert_wholesale_rand_keyword()
{
    $searchkeywords = explode(',', trim($GLOBALS['_CFG']['wholesale_search_keywords']));
    if (count($searchkeywords) > 0) {
        return $searchkeywords[rand(0, count($searchkeywords) - 1)];
    } else {
        return '';
    }
}

/*
 * 处理属性，返回数组
 * goods_attr_id 字符串，如 1,2,3
 * 返回数组
 */
function get_wholesale_attr_array($goods_attr_id = ''){
	if(empty($goods_attr_id)){
		return false;
	}
	$sort_order = " ORDER BY a.sort_order ASC, a.attr_id ASC ";
	$sql = " SELECT a.attr_name, ga.attr_value FROM ".$GLOBALS['ecs']->table('wholesale_goods_attr')." AS ga ".
		" LEFT JOIN ".$GLOBALS['ecs']->table('attribute')." AS a ON a.attr_id = ga.attr_id ".
		" WHERE ga.goods_attr_id IN ($goods_attr_id) ".$sort_order;
	$res = $GLOBALS['db']->getAll($sql);

	return $res;
}
?>