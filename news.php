<?php

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

//ecmoban模板堂 --zhuo start
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);

if(isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])){
    $region_id = $_COOKIE['region_id'];
}
//ecmoban模板堂 --zhuo end
$dir = ROOT_PATH . 'data/cms_Templates/'.$GLOBALS['_CFG']['template'];
$preview = !empty($_REQUEST['preview']) ? intval($_REQUEST['preview']) : 0;
if($preview > 0 && file_exists($dir."/temp/pc_html.php")){
    $dir = $dir . "/temp";
}
if(file_exists($dir."/pc_html.php") && defined('THEME_EXTENSION')){
    require(ROOT_PATH . '/includes/lib_visual.php');
    assign_template();
    $position = assign_ur_here(0,"CMS频道");
    $smarty->assign('page_title',           $position['title']);     // 页面标题
    $smarty->assign('ur_here',              $position['ur_here']);   // 当前位置
    $smarty->assign('helps',                get_shop_help());        // 网店帮助
    
    $categories_pro = get_category_tree_leve_one();
    $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
    
    $smarty->assign('warehouse_id',       $region_id);
    $smarty->assign('area_id',       $area_id);
    
     /*重写图片链接*/
    $replace_data = array(
        'http://localhost/ecmoban_dsc2.0.5_20170518/',
        'http://localhost/ecmoban_dsc2.2.6_20170727/',
        'http://localhost/ecmoban_dsc2.3/'
    );
    //OSS文件存储ecmoban模板堂 --zhuo start
    if ($GLOBALS['_CFG']['open_oss'] == 1) {
        $bucket_info = get_bucket_info();
        $endpoint = $bucket_info['endpoint'];
    } else {
        $endpoint = !empty($GLOBALS['_CFG']['site_domain']) ? $GLOBALS['_CFG']['site_domain'] : '';
    }
    $page = get_html_file($dir."/pc_html.php");
    if ($page && $endpoint) {
        $desc_preg = get_goods_desc_images_preg($endpoint, $page);
        $page = $desc_preg['goods_desc'];
    }
    
    $page = str_replace($replace_data, $ecs->url(), $page);
    
    $user_id = !empty($_SESSION['user_id'])  ?  $_SESSION['user_id'] : 0;
    $smarty->assign('page',       $page);

    $smarty->display('news.dwt');
    exit;
}
/************************** 配置区域 **************************/
$custom_cata_id = 1; //请配置商品分类ID
$custom_catb_id = 6; //请配置商品分类ID
/************************** 配置区域 **************************/

//************************** 以下代码无需修改 **************************/

$cat_id = 1; //文章频道分类ID
$video_cat_id = 12; //文章频道视频栏目ID
$custom_cata_right_id = 17; //商品分类1右侧文章栏目id
$custom_catb_right_id = 858; //商品分类2右侧文章栏目id

/*------------------------------------------------------ */
//-- PROCESSOR
/*------------------------------------------------------ */

/* 获得页面的缓存ID */
$cache_id = sprintf('%X', crc32('news-' . $_CFG['lang']));

if (!$smarty->is_cached('news.dwt', $cache_id))
{
    /* 如果页面没有被缓存则重新获得页面的内容 */

    assign_template('a', array($cat_id));
    $position = assign_ur_here($cat_id);
    $smarty->assign('page_title',           $position['title']);     // 页面标题
    $smarty->assign('ur_here',              $position['ur_here']);   // 当前位置

    $smarty->assign('categories',           get_categories_tree(0)); // 分类树
    $smarty->assign('article_categories',   article_categories_tree($cat_id)); //文章分类树
    $smarty->assign('helps',                get_shop_help());        // 网店帮助

    if (defined('THEME_EXTENSION')) {
        $categories_pro = get_category_tree_leve_one();
        $smarty->assign('categories_pro', $categories_pro); // 分类树加强版
    }

    /* Meta */
    $meta = $db->getRow("SELECT cat_name, keywords, cat_desc FROM " . $ecs->table('article_cat') . " WHERE cat_id = '$cat_id'");

    if ($meta === false || empty($meta))
    {
        /* 如果没有找到任何记录则返回首页 */
        ecs_header("Location: ./\n");
        exit;
    }

    $smarty->assign('cat_id',      $cat_id);
    $smarty->assign('custom_cata_id', $custom_cata_id);
    $smarty->assign('custom_catb_id', $custom_catb_id);
	
    $smarty->assign('cat_name',    htmlspecialchars($meta['cat_name']));
    $smarty->assign('keywords',    htmlspecialchars($meta['keywords']));
    $smarty->assign('description', htmlspecialchars($meta['cat_desc']));
    $smarty->assign('themes_path', 'themes/' . $_CFG['template']);
	
    //banner广告位 by wang
    for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
        $article_channel_left_ad .= "'article_channel_left_ad" . $i . ","; //首页导航下方左侧广告
        $notic_down_ad .= "'notic_down_ad" . $i . ","; //商城公告下方广告
    }

    $smarty->assign('article_channel_left_ad', $article_channel_left_ad);
    $smarty->assign('notic_down_ad', $notic_down_ad);

    //最新、推荐的文章、商城公告

    $smarty->assign('top_articles', get_news_cat_articles($cat_id, 1, 2, 'article_type=1'));
	$smarty->assign('new_articles', get_news_cat_articles($cat_id, 1, 5, 'article_type=0'));

	$smarty->assign('notice_articles', get_news_cat_articles(13,1,6));//商城公告
	$smarty->assign('new_articles', get_news_cat_articles(5,1,6));//新手上路
	$smarty->assign('serve_articles', get_news_cat_articles(8,1,6));//服务保证
	$smarty->assign('pay_articles', get_news_cat_articles(7,1,6));//配送与支付
	
	

	//频道分类
    $cat_child_list = article_cat_list($cat_id, 0, false);
	
	//文章列表
    if (is_array($cat_child_list)) {
        foreach ($cat_child_list as $key => $vo) {
            if ($vo['parent_id'] == $cat_id) {
                $articles_list[$key] = get_new_articles($key,5);
            }
			else
			{
				unset($cat_child_list[$key]);	
			}
        }
    }

	$smarty->assign('cat_child_list', $cat_child_list);
    $smarty->assign('articles_list', $articles_list);
	
	$cat_select = array('cat_name', 'keywords', 'cat_desc', 'style', 'grade', 'filter_attr', 'parent_id');
    $cat = get_cat_info($custom_catb_id, $cat_select);   // 获得分类的相关信息
		
	//自定义栏目2
    $smarty->assign('custom_catb_info', $cat);
	$smarty->assign('cat_childb_list', get_child_tree($custom_catb_id));
    $smarty->assign('hot_goods', get_hot_goods_list($custom_catb_id,9));//左侧
    $smarty->assign('best_goods', get_best_goods_list($custom_catb_id,8));//中间
    
    $new_articles_2_info = get_article_cat_info(10);
    $smarty->assign('new_articles_2_info', $new_articles_2_info);
    $smarty->assign('new_articles_2', get_new_articles($custom_catb_right_id,9));//右侧
    
    //视频栏目
    $smarty->assign('video_cat_info', get_article_cat_info($video_cat_id));
    $smarty->assign('cat_id_articles_video', get_new_articles($video_cat_id,11));//视频栏目
	
    /*     * 小图 start* */
    for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
        $news_banner_small_left .= "'news_banner_small_left" . $i . ","; //新闻 左侧
    }
    for ($i = 1; $i <= $_CFG['auction_ad']; $i++) {
        $news_banner_small_right .= "'news_banner_small_right" . $i . ","; //新闻 右侧
    }
    
    $smarty->assign('news_banner_small_left', $news_banner_small_left);
    $smarty->assign('news_banner_small_right', $news_banner_small_right);

    assign_dynamic('news');
}

$smarty->display('news.dwt', $cache_id);


/*------------------------------------------------------ */
//-- PRIVATE FUNCTION
/*------------------------------------------------------ */


/**
 * 获得文章分类下的文章列表
 *
 * @access  public
 * @param   integer     $cat_id
 * @param   integer     $page
 * @param   integer     $size
 *
 * @return  array
 */
function get_news_cat_articles($cat_id, $page = 1, $size = 20 ,$condition = '1')
{
    //取出所有非0的文章
    if ($cat_id == '-1')
    {
        $cat_str = 'cat_id > 0';
    }
    else
    {
        $cat_str = get_article_children($cat_id);
    }
    $sql = 'SELECT article_id, title, author, add_time, file_url, open_type,description' .
               ' FROM ' .$GLOBALS['ecs']->table('article') .
               ' WHERE is_open = 1 AND ' . $cat_str . ' and ' . $condition .
               ' ORDER BY article_type DESC, article_id DESC';
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page-1) * $size);

    $arr = array();
    if ($res)
    {
        while ($row = $GLOBALS['db']->fetchRow($res))
        {
            $article_id = $row['article_id'];

            $arr[$article_id]['id']          = $article_id;
            $arr[$article_id]['title']       = $row['title'];
            $arr[$article_id]['short_title'] = $GLOBALS['_CFG']['article_title_length'] > 0 ? sub_str($row['title'], $GLOBALS['_CFG']['article_title_length']) : $row['title'];
            $arr[$article_id]['author']      = empty($row['author']) || $row['author'] == '_SHOPHELP' ? $GLOBALS['_CFG']['shop_name'] : $row['author'];
            $arr[$article_id]['url']         = $row['open_type'] != 1 ? build_uri('article', array('aid'=>$article_id), $row['title']) : trim($row['file_url']);
            $arr[$article_id]['add_time']    = date('Y.m.d', $row['add_time']);
			$arr[$article_id]['description']          = trim($row['description']);
			$arr[$article_id]['file_url']  = $row['file_url'];
        }
    }

    return $arr;
}

function get_news_goods_list($cat_id, $num)
{
    $sql = 'Select g.goods_id,g.cat_id, g.goods_name, g.goods_name_style, g.market_price, g.shop_price, g.promote_price, ' .
                "g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb, g.goods_img, g.seller_note, " .
                "g.is_new, g.is_new, g.is_hot, g.is_promote, g.add_time, g.last_update " .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
            //"Where g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND g.is_best = 1 AND (" . $cat_id . " OR " . get_extension_goods($cat_id) .")";
			"Where g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND g.is_best = 1 ";
$cats = get_children($cat_id);
$where = !empty($cats) ? "AND ($cats OR " . get_extension_goods($cats) . ") " : '';

$sql .=$where." order by g.goods_id desc  LIMIT $num "; 

    $res = $GLOBALS['db']->getAll($sql);

    $goods = array();
    foreach ($res AS $idx => $row)
    {
        $goods[$idx]['id']           = $row['goods_id'];
        $goods[$idx]['name']         = $row['goods_name'];
        $goods[$idx]['brief']        = $row['goods_brief'];
        $goods[$idx]['brand_name']   = $row['brand_name'];
        $goods[$idx]['goods_style_name']   = add_style($row['goods_name'],$row['goods_name_style']);

        $goods[$idx]['short_name']   = $GLOBALS['_CFG']['goods_name_length'] > 0 ?sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $goods[$idx]['short_style_name']   = add_style($goods[$idx]['short_name'],$row['goods_name_style']);
        $goods[$idx]['market_price'] = price_format($row['market_price']);
        $goods[$idx]['shop_price']   = price_format($row['shop_price']);
        $goods[$idx]['thumb']        = empty($row['goods_thumb']) ? $GLOBALS['_CFG']['no_picture'] : $row['goods_thumb'];
        $goods[$idx]['goods_img']    = empty($row['goods_img'])   ? $GLOBALS['_CFG']['no_picture'] : $row['goods_img'];
        $goods[$idx]['url']          = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        //$goods[$idx]['count']        = ments_count_all_goods_index($row['goods_id']);
        //$goods[$idx]['cat_name']     = get_class_child($row['cat_id']);
        $goods[$idx]['seller_note']  = $row['seller_note'];
        $goods[$idx]['add_time']     = date('Y.m', $row['add_time']);
    }

    return $goods;
}

/**
 * 获得分类的信息
 *
 * @param   integer $cat_id
 *
 * @return  void
 */
function get_article_cat_info($cat_id)
{
    $sql = 'SELECT cat_id, cat_name FROM ' . $GLOBALS['ecs']->table('article_cat') ." WHERE cat_id = '$cat_id'";
    $res = $GLOBALS['db']->getRow($sql);
    
    return $res;
}

/**
 * 获得最新的文章列表。
 *
 * @access  private
 * @return  array
 */
function get_new_articles($cat_id = 0, $limit = 12)
{
    $condition = get_article_children($cat_id);//如果是头条则只显示cat_id为6和9栏目下的信息
    $sql = 'SELECT a.article_id, a.content, a.title, ac.cat_name, a.add_time, a.description, a.file_url, a.open_type, ac.cat_id, ac.cat_name ' .
            ' FROM ' . $GLOBALS['ecs']->table('article') . ' AS a, ' .
                $GLOBALS['ecs']->table('article_cat') . ' AS ac' .
            ' WHERE a.is_open = 1 AND article_type=0 and a.cat_id = ac.cat_id AND ac.' . $condition .
            ' ORDER BY a.article_type DESC, a.add_time DESC LIMIT ' . $limit;

    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();
    foreach ($res AS $idx => $row)
    {
        $arr[$idx]['id']          = $row['article_id'];
        $arr[$idx]['title']       = $row['title'];
		 $arr[$idx]['content']       = $row['content'];
		$arr[$idx]['description'] = $row['description'];
        $arr[$idx]['short_title'] = $GLOBALS['_CFG']['article_title_length'] > 0 ?
                                        sub_str($row['title'], $GLOBALS['_CFG']['article_title_length']) : $row['title'];
        $arr[$idx]['cat_name']    = $row['cat_name'];
        $arr[$idx]['add_time']    = local_date('Y.m.d', $row['add_time']);//local_date($GLOBALS['_CFG']['date_format'], $row['add_time']);
        $arr[$idx]['url']         = $row['open_type'] != 1 ?
                                        build_uri('article', array('aid' => $row['article_id']), $row['title']) : trim($row['file_url']);
        $arr[$idx]['cat_url']     = build_uri('article_cat', array('acid' => $row['cat_id']), $row['cat_name']);
        $arr[$idx]['file_url']     = $row['file_url'];
    }

    return $arr;
}

/**
* 获得cat_id热门列表。ecmoban修改开始
*
* @access  private
* @param   integer
* @return  array 
*/
function get_hot_goods_list($cat_id, $num)
{
    $sql = 'Select g.goods_id,g.cat_id, g.goods_name, g.goods_name_style, g.market_price, g.shop_price, g.promote_price, ' .
                "g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb, g.goods_img, g.seller_note, " .
                "g.is_new, g.is_new, g.is_hot, g.is_promote, g.add_time, g.last_update " .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
            "Where g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND g.is_hot = 1 AND (" . $cat_id . " OR " . get_extension_goods($cat_id) .")";
$cats = get_children($cat_id);
$where = !empty($cats) ? "AND ($cats OR " . get_extension_goods($cats) . ") " : '';

$sql .=$where." order by g.goods_id desc  LIMIT $num "; 

    $res = $GLOBALS['db']->getAll($sql);

    $goods = array();
    foreach ($res AS $idx => $row)
    {
        $goods[$idx]['id']           = $row['goods_id'];
        $goods[$idx]['name']         = $row['goods_name'];
        $goods[$idx]['brief']        = $row['goods_brief'];
        $goods[$idx]['brand_name']   = $row['brand_name'];
        $goods[$idx]['goods_style_name']   = add_style($row['goods_name'],$row['goods_name_style']);

        $goods[$idx]['short_name']   = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                                           sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $goods[$idx]['short_style_name']   = add_style($goods[$idx]['short_name'],$row['goods_name_style']);
        $goods[$idx]['market_price'] = price_format($row['market_price']);
        $goods[$idx]['shop_price']   = price_format($row['shop_price']);
        $goods[$idx]['thumb']        = empty($row['goods_thumb']) ? $GLOBALS['_CFG']['no_picture'] : $row['goods_thumb'];
        $goods[$idx]['goods_img']    = empty($row['goods_img'])   ? $GLOBALS['_CFG']['no_picture'] : $row['goods_img'];
        $goods[$idx]['url']          = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        //$goods[$idx]['count']        = ments_count_all_goods_index($row['goods_id']);
        //$goods[$idx]['cat_name']     = get_class_child($row['cat_id']);
        $goods[$idx]['seller_note']  = $row['seller_note'];
        $goods[$idx]['add_time']     = date('Y.m.d', $row['add_time']);
        $goods[$idx]['last_update']  = date('Y.m', $row['last_update']);
    }

    return $goods;
} 


function get_best_goods_list($cat_id, $num)
{
    $sql = 'Select g.goods_id,g.cat_id, g.goods_name, g.goods_name_style, g.market_price, g.shop_price, g.promote_price, ' .
                "g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb, g.goods_img, g.seller_note, " .
                "g.is_new, g.is_new, g.is_hot, g.is_promote, g.add_time, g.last_update " .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
            "Where g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND g.is_best = 1 AND (" . $cat_id . " OR " . get_extension_goods($cat_id) .")";
$cats = get_children($cat_id);
$where = !empty($cats) ? "AND ($cats OR " . get_extension_goods($cats) . ") " : '';

$sql .=$where." order by g.goods_id desc  LIMIT $num "; 

    $res = $GLOBALS['db']->getAll($sql);

    $goods = array();
    foreach ($res AS $idx => $row)
    {
        $goods[$idx]['id']           = $row['goods_id'];
        $goods[$idx]['name']         = $row['goods_name'];
        $goods[$idx]['brief']        = $row['goods_brief'];
        $goods[$idx]['brand_name']   = $row['brand_name'];
        $goods[$idx]['goods_style_name']   = add_style($row['goods_name'],$row['goods_name_style']);

        $goods[$idx]['short_name']   = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                                           sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $goods[$idx]['short_style_name']   = add_style($goods[$idx]['short_name'],$row['goods_name_style']);
        $goods[$idx]['market_price'] = price_format($row['market_price']);
        $goods[$idx]['shop_price']   = price_format($row['shop_price']);
        $goods[$idx]['thumb']        = empty($row['goods_thumb']) ? $GLOBALS['_CFG']['no_picture'] : $row['goods_thumb'];
        $goods[$idx]['goods_img']    = empty($row['goods_img'])   ? $GLOBALS['_CFG']['no_picture'] : $row['goods_img'];
        $goods[$idx]['url']          = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        //$goods[$idx]['count']        = ments_count_all_goods_index($row['goods_id']);
        //$goods[$idx]['cat_name']     = get_class_child($row['cat_id']);
        $goods[$idx]['seller_note']  = $row['seller_note'];
        $goods[$idx]['add_time']     = date('Y.m', $row['add_time']);
        $goods[$idx]['last_update']  = date('Y.m', $row['last_update']);
    }

    return $goods;
}