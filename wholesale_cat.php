<?php
 
define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
require ROOT_PATH . '/includes/lib_area.php';
require ROOT_PATH . 'includes/lib_wholesale.php';
$page = (!(empty($_REQUEST['page'])) && (0 < intval($_REQUEST['page'])) ? intval($_REQUEST['page']) : 1);
$size = (!(empty($_CFG['page_size'])) && (0 < intval($_CFG['page_size'])) ? intval($_CFG['page_size']) : 10);
$default_sort_order_type   = 'act_id';
$sort = (isset($_REQUEST['sort']) && in_array(trim(strtolower($_REQUEST['sort'])), array('act_id', 'add_time', 'sales_volume', 'comments_number'))) ? trim($_REQUEST['sort']) : $default_sort_order_type;
$order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC')) ? trim($_REQUEST['order']) : $default_sort_order_method);
if (empty($_REQUEST['act'])) 
{
	$_REQUEST['act'] = 'list';
}
if ($_REQUEST['act'] == 'list') 
{
	$cat_id = (empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']));
	if ($cat_id) 
	{
		$sql = ' SELECT cat_name FROM ' . $ecs->table('wholesale_cat') . ' WHERE cat_id = \'' . $cat_id . '\' ';
		$smarty->assign('cat_name', $db->getOne($sql));
	}

    if (!$cat_id) {
        show_message('参数错误!');
    }
    $smarty->assign('cat_id', $cat_id);    //分类

	if (defined('THEME_EXTENSION')) 
	{
		$business_cate = get_wholesale_cat();
		$smarty->assign('business_cate', $business_cate);
	}

    $pager = get_pager('wholesale_cat.php', array('act' => 'list', 'brand'=>$brand, 'keywords' => $keywords, 'sort' => $sort, 'order' => $order), $count, $page, $size);

//    $smarty->assign('pager', $pager);

    $brand = $ecs->get_explode_filter($_REQUEST['brand']); //过滤品牌参数

    /* 平台品牌筛选 */
    if (true) {
        $children = get_children($cat_id);

        $cat_keys = get_array_keys_cat($cat_id);
        $brand_select = '';
        $brand_tag_where = '';


        //关联地区显示商品
        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
            $brand_select = " , ( SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('link_area_goods') . " as lag WHERE lag.goods_id = g.goods_id AND lag.region_id = '$area_id' LIMIT 1) AS area_goods_num ";
            $where_having = " AND area_goods_num > 0 ";
        }

        if ($GLOBALS['_CFG']['review_goods'] == 1) {
            $brand_tag_where .= ' AND g.review_status > 2 ';
        }

        $sql = "SELECT b.brand_id, b.brand_name, b.brand_logo, COUNT(*) AS goods_num " . $brand_select .
            "FROM " . $GLOBALS['ecs']->table('brand') . "AS b ".
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.brand_id = b.brand_id AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 $brand_tag_where ".
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods_cat') . " AS gc ON g.goods_id = gc.goods_id " .
            " WHERE $children OR " . 'gc.cat_id ' . db_create_in(array_unique(array_merge(array($cat_id), $cat_keys))) . " AND b.is_show = 1 " .
            "GROUP BY b.brand_id HAVING goods_num > 0 $where_having ORDER BY b.sort_order, b.brand_id ASC";


        $brands_list = $GLOBALS['db']->getAll($sql);


        //by zxk
        $pin = new pin();    /*  增加获取字母类 这里实例化对象 */

        $brands = array();
        foreach ($brands_list AS $key => $val)
        {
            $temp_key = $key; //by zhang

            $brands[$temp_key]['brand_id'] = $val['brand_id'];
            $brands[$temp_key]['brand_name'] = $val['brand_name'];

            //by zhang start
            $bdimg_path="data/brandlogo/";   				   // 图片路径
            $bd_logo=$val['brand_logo']?$val['brand_logo']:""; // 图片名称
            if(empty($bd_logo)){
                $brands[$temp_key]['brand_logo'] =""; 		   // 获取品牌图片
            }else{
                $brands[$temp_key]['brand_logo'] =$bdimg_path.$bd_logo;
            }

            $brands[$temp_key]['brand_letters'] = strtoupper(substr($pin->Pinyin($val['brand_name'],'UTF8'),0,1));  //获取品牌字母
            //by zhang end

            //OSS文件存储ecmoban模板堂 --zhuo start
            if($GLOBALS['_CFG']['open_oss'] == 1 && $brands[$temp_key]['brand_logo']){
                $bucket_info = get_bucket_info();
                $brands[$temp_key]['brand_logo'] = $bucket_info['endpoint'] . $brands[$temp_key]['brand_logo'];
            }
            //OSS文件存储ecmoban模板堂 --zhuo end


            $brands[$temp_key]['url'] = build_uri('wholesale_category', array('view'=>'list', 'cid' => $cat_id, 'bid' => $val['brand_id'], 'filter_attr'=>$filter_attr_str), $cat['cat_name']);

            /* 判断品牌是否被选中 */ // by zhang
            if (!strpos($brand,",") && $brand == $brands_list[$key]['brand_id'])
            {
                $brands[$temp_key]['selected'] = 1;
            }
            if (stripos($brand,","))
            {
                $brand2=explode(",",$brand);
                for ($i=0; $i <$brand2[$i] ; $i++) {
                    if($brand2[$i]==$brands_list[$key]['brand_id']){
                        $brands[$temp_key]['selected'] = 1;
                    }
                }
            }
        }

        $letter=range('A','Z');
        $smarty->assign('letter', $letter);

        // 为0或没设置的时候 加载模板
        if($brands){
            $smarty->assign('brands', $brands);
        }


        foreach ($brands as $key => $value) {
            if ($value['selected'] == 1) {
                $bd.=$value['brand_name'] . ",";
                $get_bd[$key]['brand_id'] = $value['brand_id'];

                if ($_CFG['rewrite']) {
                    $brand_id = "b" . $get_bd[$key]['brand_id'];
                    if (stripos($value['url'], $brand_id)) {
                        $get_bd[$key]['url'] = str_replace($brand_id, "b0", $value['url']);
                    }
                } else {
                    $brand_id = "brand=" . $get_bd[$key]['brand_id'];
                    if (stripos($value['url'], $brand_id)) {
                        $get_bd[$key]['url'] = str_replace($brand_id, "brand=0", $value['url']);
                    }
                }
                $br_url = $get_bd[$key]['url'];
            }
        }

        $get_brand['br_url'] = $br_url;
        $get_brand['bd']=substr($bd,0,-1);

        //添加品牌
        $smarty->assign('brand', $brand);

        $smarty->assign('get_bd',            $get_brand);               // 品牌已选模块
        //by zhang end
    }

	$position = assign_ur_here();
	$goods_list = get_wholesale_list($cat_id, $size, $page, $sort, $order, $brand);
	$children = get_children($cat_id, 3, 0, 'wholesale_cat');
	$count = get_wholesale_cat_goodsCount($children, $cat_id, '', $brand);
	$smarty->assign('goods_list', $goods_list);
	$smarty->assign('page_title', $position['title']);
	$smarty->assign('ur_here', $position['ur_here']);
	$smarty->assign('helps', get_shop_help());
	assign_cat_pager('wholesale_cat', $cat_id, $count, $size, $sort, $order, $page = 1);
	assign_template('wholesale');

    //分区类型
    $smarty->assign('act_type', 'wholesale');
    $smarty->assign('cat_name', '现货列表');

	$smarty->display('wholesale_cat.dwt');
}
function get_wholesale_list($cat_id, $size, $page, $sort, $order, $brand)
{
    $list = array();
    $where = ' WHERE 1 ';
    $table = 'category';
    $type = 0;
    $children = get_children($cat_id, $type, 0, $table);

    if ($brand)
    {
        if (stripos($brand,",")) {
            $where .= " AND g.brand_id in (".$brand.")";
        } else {
            $where .= " AND g.brand_id = '$brand'";
        }
    }

    if ($cat_id)
    {
        $where .= ' AND (' . $children .') ';
    }

    $sql = 'SELECT w.*, g.goods_thumb, g.user_id,g.goods_name as goods_name, g.shop_price, market_price, MIN(wvp.volume_number) AS volume_number, MIN(wvp.volume_price) AS volume_price ' . 'FROM ' . $GLOBALS['ecs']->table('wholesale') . ' AS w, ' . $GLOBALS['ecs']->table('goods') . ' AS g ' . ' LEFT JOIN ' . $GLOBALS['ecs']->table('wholesale_volume_price') . ' AS wvp ON wvp.goods_id = g.goods_id ' . $where . ' AND w.goods_id = g.goods_id AND w.review_status = 3 GROUP BY goods_id order by '.$sort. " ". $order;
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);


    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        if (empty($row['goods_thumb']))
        {
            $row['goods_thumb'] = $GLOBALS['_CFG']['no_picture'];
        }
        $shop_information = get_shop_name($row['user_id']);
        $row['is_IM'] = $shop_information['is_IM'];
        if ($row['user_id'] == 0)
        {
            if ($GLOBALS['db']->getOne('SELECT kf_im_switch FROM ' . $GLOBALS['ecs']->table('seller_shopinfo') . 'WHERE ru_id = 0', true))
            {
                $row['is_dsc'] = true;
            }
            else
            {
                $row['is_dsc'] = false;
            }
        }
        else
        {
            $row['is_dsc'] = false;
        }
        $row['goods_url'] = build_uri('wholesale_goods', array('aid' => $row['act_id']), $row['goods_name']);
        $properties = get_goods_properties($row['goods_id']);
        $row['goods_attr'] = $properties['pro'];
        $row['goods_sale'] = get_sale($row['goods_id']);
        $row['goods_extend'] = get_wholesale_extend($row['goods_id']);
        $row['goods_price'] = $row['goods_price'];
        $row['moq'] = $row['moq'];
        $row['volume_number'] = $row['volume_number'];
        $row['volume_price'] = $row['volume_price'];
        $row['rz_shopName'] = get_shop_name($row['user_id'], 1);
        $build_uri = array('urid' => $row['user_id'], 'append' => $row['rz_shopName']);
        $domain_url = get_seller_domain_url($row['user_id'], $build_uri);
        $row['store_url'] = $domain_url['domain_name'];
        $row['shop_price'] = price_format($row['shop_price']);
        $row['market_price'] = price_format($row['market_price']);
        $list[] = $row;
    }

    return $list;
}
function assign_cat_pager($app, $cat, $record_count, $size, $sort, $order, $page = 1) 
{
	$sch = array('sort' => $sort, 'order' => $order, 'cat' => $cat);
	$page = intval($page);
	if ($page < 1) 
	{
		$page = 1;
	}
	$page_count = (0 < $record_count ? intval(ceil($record_count / $size)) : 1);
	$pager['page'] = $page;
	$pager['size'] = $size;
	$pager['sort'] = $sort;
	$pager['order'] = $order;
	$pager['record_count'] = $record_count;
	$pager['page_count'] = $page_count;
	switch ($app) 
	{
		case 'wholesale_cat': $uri_args = array('act' => 'list', 'cid' => $cat, 'sort' => $sort, 'order' => $order);
		break;
	}
	$page_prev = (1 < $page ? $page - 1 : 1);
	$page_next = ($page < $page_count ? $page + 1 : $page_count);
	$_pagenum = 10;
	$_offset = 2;
	$_from = $_to = 0;
	if ($page_count < $_pagenum) 
	{
		$_from = 1;
		$_to = $page_count;
	}
	else 
	{
		$_from = $page - $_offset;
		$_to = ($_from + $_pagenum) - 1;
		if ($_from < 1) 
		{
			$_to = ($page + 1) - $_from;
			$_from = 1;
			if (($_to - $_from) < $_pagenum) 
			{
				$_to = $_pagenum;
			}
		}
		else if ($page_count < $_to) 
		{
			$_from = ($page_count - $_pagenum) + 1;
			$_to = $page_count;
		}
	}
	if (!(empty($url_format))) 
	{
		$pager['page_first'] = ((1 < ($page - $_offset)) && ($_pagenum < $page_count) ? $url_format . 1 : '');
		$pager['page_prev'] = (1 < $page ? $url_format . $page_prev : '');
		$pager['page_next'] = ($page < $page_count ? $url_format . $page_next : '');
		$pager['page_last'] = ($_to < $page_count ? $url_format . $page_count : '');
		$pager['page_kbd'] = ($_pagenum < $page_count ? true : false);
		$pager['page_number'] = array();
		for ($i = $_from; $i <= $_to; ++$i) 
		{
			$pager['page_number'][$i] = $url_format . $i;
		}
	}
	else 
	{
		$pager['page_first'] = ((1 < ($page - $_offset)) && ($_pagenum < $page_count) ? build_uri($app, $uri_args, '', 1, $keywords) : '');
		$pager['page_prev'] = (1 < $page ? build_uri($app, $uri_args, '', $page_prev, $keywords) : '');
		$pager['page_next'] = ($page < $page_count ? build_uri($app, $uri_args, '', $page_next, $keywords) : '');
		$pager['page_last'] = ($_to < $page_count ? build_uri($app, $uri_args, '', $page_count, $keywords) : '');
		$pager['page_kbd'] = ($_pagenum < $page_count ? true : false);
		$pager['page_number'] = array();
		for ($i = $_from; $i <= $_to; ++$i) 
		{
			$pager['page_number'][$i] = build_uri($app, $uri_args, '', $i, $keywords);
		}
	}
	$GLOBALS['smarty']->assign('pager', $pager);
}
function get_wholesale_cat_goodsCount($children, $cat_id, $ext = '', $brand)
{
	$where = ' wc.is_show = 1 AND ' . $children . ' AND w.review_status = 3 ';
	if ($cat_id) 
	{
		$where .= ' AND w.wholesale_cat_id = \'' . $cat_id . '\' ';
	}

    if ($brand)
    {
        if (stripos($brand,",")) {
            $where .= " AND g.brand_id in (".$brand.")";
        } else {
            $where .= " AND g.brand_id = '$brand'";
        }
    }

	$leftJoin = '';
	$leftJoin .= ' LEFT JOIN ' . $GLOBALS['ecs']->table('wholesale_cat') . ' as wc on w.wholesale_cat_id = wc.cat_id ';
	$leftJoin .= ' LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' as g on g.goods_id = w.goods_id ';
	return $GLOBALS['db']->getOne('SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('wholesale') . ' AS w ' . $leftJoin . ' WHERE ' . $where . ' ' . $ext);
}
?>