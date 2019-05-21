<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/20
 * Time: 4:56
 */
define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');

// by zxk 跳转
$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

$uachar = "/(nokia|sony|ericsson|mot|samsung|htc|sgh|lg|sharp|sie-|philips|panasonic|alcatel|lenovo|iphone|ipod|blackberry|meizu|android|netfront|symbian|ucweb|windowsce|palm|operamini|operamobi|opera mobi|openwave|nexusone|cldc|midp|wap|mobile)/i";

if(($ua == '' || preg_match($uachar, $ua))&& !strpos(strtolower($_SERVER['REQUEST_URI']),'wap'))
{
    if(isset($_REQUEST['act']) && $_REQUEST['act'] == 'view'){
        $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    }

    $Loaction = 'mobile/index.php?m=sample&a=detail&id=' . $id;

    if (!empty($Loaction))
    {
        ecs_header("Location: $Loaction\n");

        exit;
    }
}

//分区类型
$smarty->assign('act_type', 'sample');

//ecmoban模板堂 --zhuo start
require(ROOT_PATH . 'includes/lib_area.php');  //ecmoban模板堂 --zhuo
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
//ecmoban模板堂 --zhuo end

$keywords   = !empty($_REQUEST['keywords'])   ? htmlspecialchars(trim($_REQUEST['keywords'])):'';

if(isset($_REQUEST['keywords'])){
    clear_all_files();
}

if ((DEBUG_MODE & 2) != 2)
{
    $smarty->caching = true;
}

$user_id = isset($_SESSION['user_id'])? $_SESSION['user_id'] : 0;

$ua = strtolower($_SERVER['HTTP_USER_AGENT']);

$uachar = "/(nokia|sony|ericsson|mot|samsung|sgh|lg|philips|panasonic|alcatel|lenovo|cldc|midp|mobile)/i";

if(($ua == '' || preg_match($uachar, $ua))&& !strpos(strtolower($_SERVER['REQUEST_URI']),'wap'))
{
    if(isset($_REQUEST['act']) && $_REQUEST['act'] == 'view'){
        $group_buy_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    }

    $Loaction = 'mobile/index.php?m=groupbuy&a=detail&id=' . $group_buy_id;

    if (!empty($Loaction))
    {
        ecs_header("Location: $Loaction\n");

        exit;
    }
}

/*------------------------------------------------------ */
//-- act 操作项的初始化
/*------------------------------------------------------ */

if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'index';
}

if (!empty($_REQUEST['act']) && $_REQUEST['act'] == 'price') {
    include('includes/cls_json.php');
    header("Content-type: text/html; charset=utf-8");

    $json   = new JSON;
    $res    = array('err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1);

    $act_id = isset($_REQUEST['act_id']) ? intval($_REQUEST['act_id']) : 0;

    if ($act_id <= 0)
    {
        exit($json->encode($res));
    }

    $sample = sample_info($act_id);
    if (empty($sample))
    {
        exit($json->encode($res));
    }

    //商品id
    $goods_id     = $sample['goods_id']; //仓库管理的地区ID
    $attr_id    = isset($_REQUEST['attr']) ? explode(',', $_REQUEST['attr']) : array();
    $warehouse_id     = (isset($_REQUEST['warehouse_id'])) ? intval($_REQUEST['warehouse_id']) : 0;
    $area_id     = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID

    $onload     = (isset($_REQUEST['onload'])) ? trim($_REQUEST['onload']) : ''; //仓库管理的地区ID

    $goods_attr = (isset($_REQUEST['goods_attr']) && !(empty($_REQUEST['goods_attr'])) ? explode(',', $_REQUEST['goods_attr']) : array());
    $attr_ajax = get_goods_attr_ajax($goods_id, $goods_attr, $attr_id);


    //获取阶梯价格
    $price_ladder = $sample['price_ladder'];
    $prices = array_column($price_ladder, 'formated_price');

    $sample['goods_price_formatted'] = max($prices);

    $smarty->assign('goods', $sample);

    $main_attr_list = get_main_attr_list($goods_id, $attr_id);
    $smarty->assign('main_attr_list', $main_attr_list);

    $res['main_attr_list'] = $smarty->fetch('library/main_attr_list.lbi');
    exit($json->encode($res));

} elseif ($_REQUEST['act'] == 'index') {

    /**小图 start**/
    for($i=1;$i<=$_CFG['auction_ad'];$i++) {
        $sample_banner   .= "'sample_banner".$i.","; //样品轮播banner
        $sample_banner_small   .= "'sample_banner_small".$i.","; //样品小轮播

        //热门分类
        $top .= "'top_cat_sample".$i.",";
        //每日上新
        $new .=  "'new_cat_sample".$i.",";
    }

    $smarty->assign('top', $top);
    $smarty->assign('new', $new);

    /* 模板赋值 */
    $position = assign_ur_here(0, '样品专区');
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置

    $smarty->assign('pager', array('act'=>'index'));
    $smarty->assign('sample_banner',       $sample_banner);
    $smarty->assign('sample_banner_small',       $sample_banner_small);
    /**小图 end**/

    /* 显示模板 */
    $smarty->display('sample_index.dwt');
} elseif ($_REQUEST['act'] == 'list') {
    $position = assign_ur_here(0, $_LANG['all_category']);
    $smarty->assign('page_title', $position['title']);

    //分类ID
    $cat_id = isset($_REQUEST['id']) && intval($_REQUEST['id']) > 0 ? intval($_REQUEST['id']) : 0;

    $default_sort_order_type   = 'act_id';
    $sort = (isset($_REQUEST['sort']) && in_array(trim(strtolower($_REQUEST['sort'])), array('act_id', 'add_time', 'sales_volume', 'comments_number'))) ? trim($_REQUEST['sort']) : $default_sort_order_type;
    $order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC'))) ? trim($_REQUEST['order']) : $default_sort_order_method;


    $brand = $ecs->get_explode_filter($_REQUEST['brand']); //过滤品牌参数

    if (!$cat_id) {
        show_message('参数错误!');
    }
    if($cat_id){
        $children = get_children($cat_id);
    }

    $smarty->assign('cat_id', $cat_id);     //分类

    $pager = get_pager('sample.php', array('act' => 'list', 'brand'=>$brand, 'keywords' => $keywords, 'sort' => $sort, 'order' => $order), $count, $page, $size);
    $smarty->assign('pager', $pager);

    /* 模板赋值 */
    $position = assign_ur_here(0, '样品专区');
    $smarty->assign('page_title', $position['title']);    // 页面标题
    $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置

    /* 初始化分页信息 */
    $page = isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0 ? intval($_REQUEST['page']) : 1;
    $size = isset($_CFG['page_size']) && intval($_CFG['page_size']) > 0 ? intval($_CFG['page_size']) : 10; /* 取得每页记录数 */
    $size = 1;

    $count = sample_count($children, $keywords, $brand);
    if ($count > 0 ) {
        /* 计算总页数 */
        $page_count = ceil($count / $size);

        /* 取得当前页 */
        $page = isset($_REQUEST['page']) && intval($_REQUEST['page']) > 0 ? intval($_REQUEST['page']) : 1;

        $page = $page > $page_count ? $page_count : $page;

        /* 缓存id：语言 - 每页记录数 - 当前页 */
        $cache_id = $_CFG['lang'] . '-' . $cat_id . '-' . $size . '-' . $page . '-' . $sort . '-' . $order . '-' . $price_min . '-' . $price_max . '-' . $keywords  . '-' . $brand ;
        $cache_id = sprintf('%X', crc32($cache_id));
    } else {
        /* 缓存id：语言 */
        $cache_id = $_CFG['lang'];
        $cache_id = sprintf('%X', crc32($cache_id));
    }

    /* 平台品牌筛选 */
    if (true) {
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


            $brands[$temp_key]['url'] = build_uri('sample_category', array('view'=>'list', 'cid' => $cat_id, 'bid' => $val['brand_id'], 'filter_attr'=>$filter_attr_str), $cat['cat_name']);

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

    /* 如果没有缓存，生成缓存 */
    if (!$smarty->is_cached('sample_list.dwt', $cache_id)) {
        $list = get_sample_list($children, $size, $page, $keywords, $sort, $order, $brand);
        $smarty->assign('list', $list);
    }

    /* 显示模板 */
    $smarty->display('sample_list.dwt');
} elseif ($_REQUEST['act'] == 'view') {
    /* 取得参数：样品活动id */
    $sample_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    if ($sample_id <= 0)
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* 取得取得活动信息 */
    $sample = sample_info($sample_id, 0, $user_id);

    /* 取得样品商品信息 */
    $goods_id = $sample['goods_id'];

    //检查权限
    $priv_user_id = get_table_date('goods', 'goods_id=\'' . $goods_id . '\'', array('user_id'), 2);
    if (!is_scscp_admin() && !is_scscp_seller($priv_user_id)) {
        priv();
    }

    if (true) {
        //商品信息
        $goods = get_goods_info($goods_id, $region_id, $area_id);
        if (empty($goods))
        {
            ecs_header("Location: ./\n");
            exit;
        }
        $smarty->assign('goods', $goods);
        $smarty->assign('id',           $goods_id);

        //评分 start
        $comment_allCount = get_goods_comment_count($goods_id);
        $comment_all = get_comments_percent($goods_id);
        $smarty->assign('comment_allCount',        $comment_allCount);
        $smarty->assign('comment_all',  $comment_all);


        if($goods['user_id'] > 0){
            $merchants_goods_comment = get_merchants_goods_comment($goods['user_id']); //商家所有商品评分类型汇总
            $smarty->assign('merch_cmt',  $merchants_goods_comment);
        }
        //评分 end

        //ecmoban模板堂 --zhuo start
        $shop_info = get_merchants_shop_info('merchants_steps_fields', $goods['user_id']);
        $adress = get_license_comp_adress($shop_info['license_comp_adress']);

        $smarty->assign('shop_info',       $shop_info);
        $smarty->assign('adress',       $adress);

        $province_list = get_warehouse_province();

        $smarty->assign('province_list',                $province_list); //省、直辖市

        $city_list = get_region_city_county($province_id);
        $smarty->assign('city_list',                $city_list); //省下级市

        $district_list = get_region_city_county($city_id);
        $smarty->assign('district_list',                $district_list);//市下级县

        $smarty->assign('goods_id',			$goods_id); //商品ID

        $warehouse_list = get_warehouse_list_goods();
        $smarty->assign('warehouse_list',			$warehouse_list); //仓库列

        $warehouse_name = get_warehouse_name_id($region_id);

        $smarty->assign('warehouse_name',			$warehouse_name); //仓库名称
        $smarty->assign('region_id',			$region_id); //商品仓库region_id

        $smarty->assign('user_id',			$_SESSION['user_id']);

        $smarty->assign('shop_price_type', $goods['model_price']); //商品价格运营模式 0代表统一价格（默认） 1、代表仓库价格 2、代表地区价格
        $smarty->assign('area_id', $area_id); //地区ID

        //ecmoban模板堂 --zhuo start 仓库

        /* 取得商品的规格 */
        $properties = get_goods_properties($goods_id);
        $smarty->assign('properties', $properties['pro']);    //商品属性
        $smarty->assign('specification', $properties['spe']); // 商品规格
        $smarty->assign('specscount',       count($properties['spe']));

        $smarty->assign('area_htmlType',  'presale');

        $smarty->assign('province_row',  get_region_info($province_id));
        $smarty->assign('city_row',  get_region_info($city_id));
        $smarty->assign('district_row',  get_region_info($district_id));

        //模板赋值
        $smarty->assign('cfg', $_CFG);
        $position = assign_ur_here($sample['cat_id'], $sample['goods_name'], array(), '', $sample['user_id']);
        $smarty->assign('page_title', $position['title']);    // 页面标题
        $smarty->assign('ur_here',    $position['ur_here']);  // 当前位置

        $smarty->assign('categories', get_categories_tree()); // 分类树
        $smarty->assign('helps',      get_shop_help());       // 网店帮助
    }

    //　详情部分 评分 start
    $comment_all = get_comments_percent($goods_id);

    if($goods['user_id'] > 0){
        $merchants_goods_comment = get_merchants_goods_comment($goods['user_id']); //商家所有商品评分类型汇总
    }
    $smarty->assign('comment_all',  $comment_all);

    /**
     * 店铺分类
     */
    if ($goods['user_id']) {
        $goods_store_cat = get_child_tree_pro(0, 0, 'merchants_category', 0, $goods['user_id']);

        if ($goods_store_cat) {
            $goods_store_cat = array_values($goods_store_cat);
        }

        $smarty->assign('goods_store_cat', $goods_store_cat);
    }


    $discuss_list = get_discuss_all_list($goods_id, 0, 1, 10);
    $smarty->assign('discuss_list',       $discuss_list);

    //更新商品点击次数
    $sql = 'UPDATE ' . $ecs->table('goods') . ' SET click_count = click_count + 1 '.
        "WHERE goods_id = '" . $sample['goods_id'] . "'";
    $db->query($sql);

    $smarty->assign('act_id',  $sample_id);
    $smarty->assign('now_time',  gmtime());           // 当前系统时间

    $basic_info = get_shop_info_content($goods['user_id']);

    $basic_date = array('region_name');
    $basic_info['province'] = get_table_date('region', "region_id = '" . $basic_info['province'] . "'", $basic_date, 2);
    $basic_info['city'] = get_table_date('region', "region_id= '" . $basic_info['city'] . "'", $basic_date, 2) . "市";

    /*  @author-bylu 判断当前商家是否允许"在线客服" start  */
    $shop_information = get_shop_name($goods['user_id']);//通过ru_id获取到店铺信息;
    $shop_information['kf_tel'] =$db->getOne("SELECT kf_tel FROM ".$ecs->table('seller_shopinfo')."WHERE ru_id = '".$goods['user_id']."'");

    //判断当前商家是平台,还是入驻商家 bylu
    if($goods_info['user_id'] == 0){
        //判断平台是否开启了IM在线客服
        if($db->getOne("SELECT kf_im_switch FROM ".$ecs->table('seller_shopinfo')."WHERE ru_id = 0")){
            $shop_information['is_dsc'] = true;
        }else{
            $shop_information['is_dsc'] = false;
        }
    }else{
        $shop_information['is_dsc'] = false;
    }
    $smarty->assign('shop_information',$shop_information);
    /*  @author-bylu  end  */

    $smarty->assign('basic_info',  $basic_info);

    $area = array(
        'region_id' => $region_id,  //仓库ID
        'province_id' => $province_id,
        'city_id' => $city_id,
        'district_id' => $district_id,
        'street_id' => $street_id,
        'street_list' => $street_list,
        'goods_id' => $goods_id,
        'user_id' => $user_id,
        'area_id' => $area_info['region_id'],
        'merchant_id' => $goods['user_id'],
    );

    $smarty->assign('area',  $area);

    if (!defined('THEME_EXTENSION')) {
        //商品运费
        $region = array(1, $province_id, $city_id, $district_id, $street_id, $street_list);
        $shippingFee = goodsShippingFee($goods_id, $region_id, $region);
        $smarty->assign('shippingFee', $shippingFee);
    }

    //@author guan start
    if ($_CFG['two_code']) {
        require(dirname(__FILE__) . '/includes/phpqrcode/phpqrcode.php'); //by zxk

        $group_buy_path = ROOT_PATH .IMAGE_DIR. "/sample_wenxin/";

        if (!file_exists($group_buy_path)) {
            make_dir($group_buy_path);
        }

        $logo = empty($_CFG['two_code_logo']) ? $goods['goods_img'] : str_replace('../', '', $_CFG['two_code_logo']);

        $size = '200x200';
        $url = $ecs->url();
        $two_code_links = trim($_CFG['two_code_links']);
        $two_code_links = empty($two_code_links) ? $url : $two_code_links;
        $data = $two_code_links . 'sample.php?act=view&id=' . $sample_id;
        $errorCorrectionLevel = 'H'; // 纠错级别：L、M、Q、H
        $matrixPointSize = 4; // 点的大小：1到10
        $filename = IMAGE_DIR . "/sample_wenxin/weixin_code_" . $goods['goods_id'] . ".png";

        QRcode::png($data, $filename, $errorCorrectionLevel, $matrixPointSize);

        $QR = imagecreatefrompng($filename);
        //$QR = imagecreatefrompng('./chart.png');//外面那QR图
        if ($logo !== FALSE) {
            $logo = imagecreatefromstring(file_get_contents($logo));

            $QR_width = imagesx($QR);
            $QR_height = imagesy($QR);

            $logo_width = imagesx($logo);
            $logo_height = imagesy($logo);

            // Scale logo to fit in the QR Code
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width / $logo_qr_width;
            $logo_qr_height = $logo_height / $scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //echo $from_width;exit;
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);
        }

        imagepng($QR, $filename);
        imagedestroy($QR);
        $smarty->assign('weixin_img_url', $filename);
        $smarty->assign('weixin_img_text', trim($_CFG['two_code_mouse']));
        $smarty->assign('two_code', trim($_CFG['two_code']));
    }



    $smarty->assign('sample', $sample);
    $smarty->assign('pictures',   get_goods_gallery($goods_id)); // 商品相册
    $smarty->display('sample_goods.dwt');
}  elseif ($_REQUEST['act'] == 'buy') {
    priv();

    /* 查询：判断是否登录 */
    if ($_SESSION['user_id'] <= 0)
    {
        show_message($_LANG['gb_error_login'], '', '', 'error');
    }

    include_once 'includes/cls_json.php';
    $json = new JSON();
    $result = array('error' => 0, 'message' => '', 'content' => '');

    /* 查询：取得参数：预售活动id */
    $sample_id = isset($_REQUEST['act_id']) ? intval($_REQUEST['act_id']) : 0;
    if ($sample_id <= 0)
    {
        ecs_header("Location: ./\n");
        exit;
    }

    //获取团购信息
    $goods_id = get_table_date('sample_activity', 'act_id=\'' . $sample_id . '\'', array('goods_id'), 2);

    /* 查询：取得规格 */
    $specs = isset($_POST['goods_spec']) ? htmlspecialchars(trim($_POST['goods_spec'])) : '';
    $attr_id = !empty($specs) ? explode(',', $specs) : '';

    /* 查询：取得预售商品信息 */
    $goods = goods_info($goods_id, $warehouse_id, $area_id, array(), $attr_id);

    if (empty($goods))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    $properties = get_goods_properties($goods_id, $region_id, $area_id);  // 获得商品的规格和属性
    $specscount = count($properties['spe']);

    //获取商品总数
    if (0 < $specscount)
    {
        $attr_array = (empty($_REQUEST['attr_array']) ? array() : $_REQUEST['attr_array']);
        $num_array = (empty($_REQUEST['num_array']) ? array() : $_REQUEST['num_array']);
        $total_number = array_sum($num_array);
    }
    else
    {
        $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
        $total_number = $goods_number;
    }

    /* 查询：取得预售活动信息 */
    $sample = sample_info($sample_id, $total_number, $user_id);
    if (empty($sample))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* 查询：检查预售活动是否是进行中 */
    if ($sample['review_status'] != 3)
    {
        show_message('商品不存在或者商品已下架', '', '', 'error');
    }

    //最小起批量
//    if ($sample['moq'] && $total_number < $sample['moq']) {
//        show_message('您订购的商品少于最小起订量', '', '', 'error');
//    }

    /* 更新：清空进货单中所有样品商品 */
    include_once(ROOT_PATH . 'includes/lib_order.php');
//    clear_cart(CART_SAMPLE_GOODS);


    //ecmoban模板堂 --zhuo start 限购
    $nowTime = gmtime();
    $start_date = $goods['xiangou_start_date'];
    $end_date = $goods['xiangou_end_date'];
    $warehouse_id = intval($goods->warehouse_id);
    $area_id = intval($goods->area_id);

    if(!empty($_SESSION['user_id'])){
        $sess = "";
    }else{
        $sess = real_cart_mac_ip();
    }
    //ecmoban模板堂 --zhuo end

    //商品最终价格
    $goods_price = $sample['cur_price'];

    $common_cart = array(
        'user_id'        => $_SESSION['user_id'],
        'session_id'     => $sess,
        'goods_id'       => $sample['goods_id'],
        'goods_sn'       => addslashes($goods['goods_sn']),
        'goods_name'     => addslashes($goods['goods_name']),
        'market_price'   => $goods['market_price'],
        'goods_price'    => $goods_price,
        //ecmoban模板堂 --zhuo start
        'ru_id'          => $goods['user_id'],
        'warehouse_id'   => $region_id,
        'area_id'        => $area_id,
        //ecmoban模板堂 --zhuo end
        'is_real'        => $goods['is_real'],
        'extension_code' => 'sample',
        'extension_id' => $sample['act_id'],
        'parent_id'      => 0,
        'rec_type'       => 0,
        'is_gift'        => 0,
        'freight' => $goods['freight'],
        'tid' => $goods['tid'],
    );

    $sess_id = ' user_id = \'' . $_SESSION['user_id'] . '\' ';
    if (0 < $specscount) {
        foreach ($attr_array as $key => $val ) {
            $val = trim($val, ',');
            $specs = $val;
            $_specs = explode(',', $val);

            $product_info = get_products_info($goods['goods_id'], $_specs, $warehouse_id, $area_id);
            empty($product_info) ? $product_info = array('product_number' => 0, 'product_id' => 0) : '';

            $number = $num_array[$key];

//            if ($number > 1) {
//                show_message('样品每个规格最多只能选择一件', '', '', 'error');
//            }

            /* 查询：查询规格名称和值，不考虑价格 */
            $attr_list = array();
            $sql = "SELECT a.attr_name, g.attr_value " .
                "FROM " . $ecs->table('goods_attr') . " AS g, " .
                $ecs->table('attribute') . " AS a " .
                "WHERE g.attr_id = a.attr_id " .
                "AND g.goods_attr_id " . db_create_in($specs) . " ORDER BY a.sort_order, a.attr_id, g.goods_attr_id";
            $res = $db->query($sql);
            while ($row = $db->fetchRow($res))
            {
                $attr_list[] = $row['attr_name'] . ': ' . $row['attr_value'];
            }
            $goods_attr = join(chr(13) . chr(10), $attr_list);

            $innerJoin = 'inner join '.$ecs->table('order_goods') . " AS og on oi.order_id = og.order_id ";


            //是否已经购买过
            $sql = "SELECT count(*) " .
                "FROM " . $ecs->table('order_info') . " AS oi " . $innerJoin.
                "WHERE og.goods_id = ".$goods_id . ' AND order_status != 2 AND user_id = '.$_SESSION['user_id'] ." AND goods_attr_id = '$specs' ";
            $count = $db->getOne($sql);
//            if ($count > 0) {
//                show_message('样品商品('.addslashes($goods_attr).')已经购买过了', '', '', 'error');
//            }

            $cart = $common_cart;
            $cart['product_id'] = $product_info['product_id'];
            $cart['goods_number'] = $number;
            $cart['goods_attr'] = addslashes($goods_attr);
            $cart['goods_attr_id'] = $specs;

            $set = get_find_in_set(array_filter($_specs), 'goods_attr_id', ',');
            $sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ' . $set . ' ';
            $rec_id = $GLOBALS['db']->getOne($sql);

            if (!(empty($rec_id)))
            {
                $db->autoExecute($ecs->table('cart'), $cart, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
            }
            else
            {
                $db->autoExecute($ecs->table('cart'), $cart, 'INSERT');
            }
        }
    } else {
        $product_info = array('product_number' => 0, 'product_id' => 0);

        if($goods['model_attr'] == 1){
            $table_products = "products_warehouse";
            $type_files = " and warehouse_id = '$warehouse_id'";
        }elseif($goods['model_attr'] == 2){
            $table_products = "products_area";
            $type_files = " and area_id = '$area_id'";
        }else{
            $table_products = "products";
            $type_files = "";
        }

//        if ($goods_number > 1) {
//            show_message('样品每个规格最多只能选择一件', '', '', 'error');
//        }

        $innerJoin = 'inner join '.$ecs->table('order_goods') . " AS og on oi.order_id = og.order_id ";

        //是否已经购买过
        $sql = "SELECT count(*) " .
            "FROM " . $ecs->table('order_info') . " AS oi " . $innerJoin.
            "WHERE og.goods_id = ".$goods_id . ' AND order_status != 2 AND user_id = '.$_SESSION['user_id'];
        $count = $db->getOne($sql);
//        if ($count > 0) {
//            show_message('样品商品已经购买过了', '', '', 'error');
//        }

        $cart = $common_cart;
        $cart['product_id'] = $product_info['product_id'];
        $cart['goods_number'] = $goods_number;
        $cart['goods_attr'] = addslashes($goods_attr);
        $cart['goods_attr_id'] = $specs;

        $sql = ' SELECT rec_id FROM ' . $GLOBALS['ecs']->table('cart') . ' WHERE ' . $sess_id . ' AND goods_id = \'' . $goods_id . '\' ';
        $rec_id = $GLOBALS['db']->getOne($sql);
        if (!(empty($rec_id)))
        {
            $db->autoExecute($ecs->table('cart'), $cart, 'UPDATE', 'rec_id=\'' . $rec_id . '\'');
        }
        else
        {
            $db->autoExecute($ecs->table('cart'), $cart, 'INSERT');
        }
    }


    /* 更新：记录购物流程类型：预售 */
    calculate_cart_goods_price($goods_id, '', 'sample', $sample_id);
    $cart_info = insert_cart_info(1);
    $result['cart_num'] = $cart_info['number'];
    exit($json->encode($result));
} elseif (!empty($_REQUEST['act']) && 'get_select_record' == $_REQUEST['act']) {
    include_once(ROOT_PATH . 'includes/lib_order.php');
    include 'includes/cls_json.php';
    $json = new JSON();
    $result = array('error' => '', 'message' => 0, 'content' => '');

    $act_id = isset($_REQUEST['act_id']) ? intval($_REQUEST['act_id']) : 0;
    if ($act_id <= 0)
    {
        exit($json->encode($res));
    }

    $sample = sample_info($act_id);
    if (empty($sample))
    {
        exit($json->encode($res));
    }

    $goods_id     = $sample['goods_id']; //仓库管理的地区ID

    //by zxk 获取商品规格
    $properties = get_goods_properties($goods_id, $region_id, $area_id);
    $specscount = count($properties['spe']);

    if (0 < $specscount) {
        $attr_array = (empty($_REQUEST['attr_array']) ? array() : $_REQUEST['attr_array']);
        $num_array = (empty($_REQUEST['num_array']) ? array() : $_REQUEST['num_array']);
        $result['total_number'] = array_sum($num_array);
        $attr_num_array = array();
        foreach ($attr_array as $key => $val )
        {
            $arr = array();
            $arr['attr'] = $val;
            $arr['num'] = $num_array[$key];
            $attr_num_array[] = $arr;
        }

        $record_data = get_select_record_data($goods_id, $attr_num_array);
        $smarty->assign('record_data', $record_data);
        $result['record_data'] = $smarty->fetch('library/select_record_data.lbi');
    } else {
        $goods_number = (empty($_REQUEST['goods_number']) ? 0 : intval($_REQUEST['goods_number']));
        $result['total_number'] = $goods_number;
    }

    $data = calculate_goods_price($act_id, $result['total_number'], 'sample');
    $result['data'] = $data;
    exit($json->encode($result));
}

/* 取得样品活动总数 */
function sample_count($children = '', $keywords='', $brand=0)
{
    $where = '';
    $where .= " AND g.is_delete = 0";

    if($children){
        $where .= " AND ($children OR " . get_extension_goods($children) . ")";
    }

    if ($brand)
    {
        if (stripos($brand,",")) {
            $where .= " AND g.brand_id in (".$brand.")";
        } else {
            $where .= " AND g.brand_id = '$brand'";
        }
    }

    if ($keywords)
    {
        $where = "AND (ga.act_name LIKE '%$keywords%' OR g.goods_name LIKE '%$keywords%') ";
    }
    $sql = "SELECT COUNT(*) " .
        "FROM " . $GLOBALS['ecs']->table('sample_activity') ." AS ga ".
        "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON ga.goods_id = g.goods_id " .
        "WHERE " . "ga.review_status = 3 " . $where;

    return $GLOBALS['db']->getOne($sql);
}

/**
 * 取得某页的所有样品活动
 * @param   int     $size   每页记录数
 * @param   int     $page   当前页
 * @return  array
 */
function get_sample_list($children = '', $size, $page, $keywords, $sort, $order, $brand=0) {
    $list = array();
    $where = '';
    $where .= " AND g.is_delete = 0";

    if ($brand)
    {
        if (stripos($brand,",")) {
            $where .= " AND g.brand_id in (".$brand.")";
        } else {
            $where .= " AND g.brand_id = '$brand'";
        }
    }

    if($children){
        $where .= " AND ($children OR " . get_extension_goods($children) . ")";
    }

    if ($keywords)
    {
        $where = "AND (ga.act_name LIKE '%$keywords%' OR g.goods_name LIKE '%$keywords%') ";
    }

    $sql = "SELECT g.*, ga.act_id, ga.act_name, ga.ext_info " .
        "FROM " . $GLOBALS['ecs']->table('sample_activity') ." AS ga ".
        "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON ga.goods_id = g.goods_id " .
        "WHERE " . "ga.review_status = 3 " . $where .'GROUP BY g.goods_id ORDER BY '."$sort $order";

    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    while ($row = $GLOBALS['db']->fetchRow($res)) {

        $ext_info = unserialize($row['ext_info']);
        $row = array_merge($row, $ext_info);

        $row['url'] = build_uri('sample', array('acid'=>$row['act_id'], 'act'=>'view'));
        $row['market_price']     = price_format($row['market_price']);
        $row['shop_price']     = price_format($row['shop_price']);
//        $row['goods_sale'] = get_sale($row['goods_id']);

        /* 处理价格阶梯 */
        $price_ladder = $row['price_ladder'];
        if (!is_array($price_ladder) || empty($price_ladder))
        {
            $price_ladder = array(array('amount' => 0, 'price' => 0));
        }
        else
        {
            foreach ($price_ladder as $key => $amount_price)
            {
                $price_ladder[$key]['formated_price'] = price_format($amount_price['price']);
            }
        }

        $row['price_ladder'] = $price_ladder;

        $list[] = $row;
    }

    return $list;
}

/**
 * 获取商品ajax属性是否都选中
 */
function get_goods_attr_ajax($goods_id, $goods_attr, $goods_attr_id){

    $arr = array();
    $arr['attr_id'] = '';
    $where = "";
    if($goods_attr){

        $goods_attr = implode(",", $goods_attr);
        $where .= " AND ga.attr_id IN($goods_attr)";

        if($goods_attr_id){
            $goods_attr_id = implode(",", $goods_attr_id);
            $where .= " AND ga.goods_attr_id IN($goods_attr_id)";
        }

        $sql = "SELECT ga.goods_attr_id, ga.attr_id, ga.attr_value  FROM " .$GLOBALS['ecs']->table('goods_attr') ." AS ga".
            " LEFT JOIN " . $GLOBALS['ecs']->table('attribute') ." AS a ON ga.attr_id = a.attr_id ".
            " WHERE  ga.goods_id = '$goods_id' $where AND a.attr_type > 0 ORDER BY a.sort_order, a.attr_id, ga.goods_attr_id";
        $res = $GLOBALS['db']->getAll($sql);

        foreach($res as $key=>$row){
            $arr[$row['attr_id']][$row['goods_attr_id']] = $row;

            $arr['attr_id'] .= $row['attr_id'] . ",";
        }

        if($arr['attr_id']){
            $arr['attr_id'] = substr($arr['attr_id'], 0, -1);
            $arr['attr_id'] = explode(",", $arr['attr_id']);
        }else{
            $arr['attr_id'] = array();
        }
    }

    return $arr;
}

function get_main_attr_list($goods_id = 0, $attr = array())
{
    $sql = ' SELECT DISTINCT attr_id FROM ' . $GLOBALS['ecs']->table('goods_attr') . ' WHERE goods_id = \'' . $goods_id . '\'';
    $attr_ids = $GLOBALS['db']->getCol($sql);
    if (!(empty($attr_ids)))
    {
        $attr_ids = implode(',', $attr_ids);
        $sort_order = ' ORDER BY sort_order DESC, attr_id DESC ';

        //单一属性bug
        $sql = ' SELECT attr_id FROM ' . $GLOBALS['ecs']->table('attribute') . ' WHERE  attr_type > 0 AND attr_id IN (' . $attr_ids . ') ' . $sort_order . ' LIMIT 1 ';
        $attr_id = $GLOBALS['db']->getOne($sql);
        $sql = ' SELECT goods_attr_id, attr_value FROM ' . $GLOBALS['ecs']->table('goods_attr') . ' WHERE goods_id = \'' . $goods_id . '\' AND attr_id = \'' . $attr_id . '\' ORDER BY goods_attr_id ';
        $data = $GLOBALS['db']->getAll($sql);

        if ($data) {
            foreach ($data as $key => $val) {
                $new_arr = array_merge($attr, array($val['goods_attr_id']));
                $data[$key]['attr_group'] = implode(',', $new_arr);
            }

            return $data;
        }

    }
    return false;
}

function get_select_record_data($goods_id = 0, $attr_num_array = array())
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
        $data['main_attr'] = get_goods_attr_array($key);
        foreach ($val as $k => $v )
        {
            $a = array();
            $a['attr_num'] = $v;
            $b = get_goods_attr_array($k);
            $c = $b[0];
            $a = array_merge($a, $c);
            $data['end_attr'][] = $a;
        }
        $record_data[$key] = $data;
    }
    return $record_data;
}

