<?php
if (!defined('IN_ECS')) {
    header("Location: index.php\n");
    exit;
}

//ecmoban模板堂 --zhuo start
$area_info = get_area_info($province_id);
$area_id = $area_info['region_id'];

$where = "regionId = '$province_id'";
$date = array('parent_id');
$region_id = get_table_date('region_warehouse', $where, $date, 2);
//ecmoban模板堂 --zhuo end

//商家不存则跳转回首页
$shop_date = array('shop_id');
$shop_where = "user_id = '$merchant_id'";
$shop_id = get_table_date('merchants_shop_information', $shop_where, $shop_date);
$preview = !empty($_REQUEST['preview'])  ?  $_REQUEST['preview'] : 0;

if(($merchant_id == 0 || $shop_id < 1) && $temp_code == ''){
    header("Location: index.php\n");
    exit;
}

//判断店铺是否关闭
$sql = "SELECT status FROM".$ecs->table('seller_shopinfo')."WHERE ru_id = '$merchant_id' LIMIT 1";
$shop_status = $db->getOne($sql);
//关闭则跳转首页
if($shop_status == 0 && $preview == 0){
    header("Location: index.php\n");
    exit;
}

//如果审核通过，判断店铺是否存在模板，不存在 导入默认模板
    $tpl_dir = ROOT_PATH . 'data/seller_templates/seller_tem_' . $merchant_id; //获取店铺模板目录
$tpl_arr = get_dir_file_list($tpl_dir);
if (empty($tpl_arr)) {
    $new_suffix = get_new_dirName($merchant_id);
    $dir = ROOT_PATH . "data/seller_templates/seller_tem/Bucket_tpl"; //原目录
    $file = $tpl_dir . "/" . $new_suffix; //目标目录
    if (!empty($new_suffix)) {
        //新建目录
        if (!is_dir($file)) {
            make_dir($file);
        }
        recurse_copy($dir, $file, 1);
        $result['error'] = 0;
    }
    $sql = " UPDATE" . $ecs->table('seller_shopinfo') . "SET seller_templates = '$new_suffix' WHERE ru_id=" . $merchant_id;
    $db->query($sql);
}
if (empty($tem)) {
    /* 获取默认模板 */
    $sql = "SELECT seller_templates FROM" . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = '$merchant_id'";
    $tem = $GLOBALS['db']->getOne($sql, true);
}

/**
 * 店铺可视化
 * 下载OSS模板文件
 */
get_down_sellertemplates($merchant_id, $tem);

$pc_page = get_seller_templates($merchant_id, 1, $tem, $preview);//获取页面内容
$pc_page['out'] = str_replace('../data/',"data/",$pc_page['out'],$i);

//OSS文件存储ecmoban模板堂 --zhuo start
if ($GLOBALS['_CFG']['open_oss'] == 1) {
    $bucket_info = get_bucket_info();
    $endpoint = $bucket_info['endpoint'];
} else {
    $endpoint = !empty($GLOBALS['_CFG']['site_domain']) ? $GLOBALS['_CFG']['site_domain'] : '';
}
if ($pc_page['out'] && $endpoint) {
    $desc_preg = get_goods_desc_images_preg($endpoint, $pc_page['out']);
    $pc_page['out'] = $desc_preg['goods_desc'];
}
$pc_page['temp'] = $temp;
assign_template('',array(),$merchant_id);
$shop_name = get_shop_name($merchant_id, 1); //店铺名称	
$grade_info = get_seller_grade($merchant_id); //等级信息
$store_conut = get_merchants_store_info($merchant_id);
$store_info = get_merchants_store_info($merchant_id, 1);
$position = assign_ur_here(0, $shop_name);
$smarty->assign('page_title',      $position['title']);    // 页面标题
$smarty->assign('ur_here',         $position['ur_here']);  // 当前位置
$smarty->assign('helps',           get_shop_help());       // 网店帮助
$smarty->assign('pc_page',$pc_page);
$smarty->assign('store',       $store_info); // 店铺背景
$build_uri = array(
    'urid' => $merchant_id,
    'append' => $shop_name
);

$domain_url = get_seller_domain_url($merchant_id, $build_uri);
$merchants_url = $domain_url['domain_name'];
$smarty->assign('merchants_url',          $merchants_url);  //网站域名

if($merchant_id > 0){
	$merchants_goods_comment = get_merchants_goods_comment($merchant_id); //商家所有商品评分类型汇总
}

$smarty->assign('merch_cmt',  $merchants_goods_comment); 
$smarty->assign('shop_name',  $shop_name); 
$categories_pro = get_category_tree_leve_one();
$smarty->assign('categories_pro',  $categories_pro); // 分类树加强版
    
$categories_pro = get_category_tree_leve_one();
$smarty->assign('categories_pro',  $categories_pro); // 分类树加强版

$store_category = get_user_store_category($merchant_id); //店铺导航栏
$smarty->assign('store_category',         $store_category);

//商家二维码 by wu start
$sql="select ss.*,sq.*, msf.license_fileImg from ".$ecs->table('seller_shopinfo')." as ss ".
	" left join".$ecs->table('seller_qrcode')." as sq on sq.ru_id=ss.ru_id ".
	" left join".$ecs->table('merchants_steps_fields')." as msf on msf.user_id = ss.ru_id ".
	" where ss.ru_id='$merchant_id'";
$basic_info = $db->getRow($sql);

$logo = str_replace('../', '',$basic_info['qrcode_thumb']);
$size = '155x155';
$url = $ecs->url();
//$data = $url."qrurl.php?type=seller&id=".$merchant_id;
$data = $url."mobile/index.php?r=store/index/shop_info&id=".$merchant_id;
$errorCorrectionLevel = 'Q'; // 纠错级别：L、M、Q、H
$matrixPointSize = 4; // 点的大小：1到10
$filename = "seller_imgs/seller_qrcode/seller_qrcode_" . $merchant_id . ".png";
QRcode::png($data, $filename, $errorCorrectionLevel, $matrixPointSize);
$QR = imagecreatefrompng($filename);
if($logo !== FALSE)
{
        $logo = imagecreatefromstring(file_get_contents($logo));
        $QR_width = imagesx($QR);
        $QR_height = imagesy($QR);		
        $logo_width = imagesx($logo);
        $logo_height = imagesy($logo);			
        // Scale logo to fit in the QR Code
        $logo_qr_width = $QR_width/5;
        $scale = $logo_width/$logo_qr_width;
        $logo_qr_height = $logo_height/$scale;
        $from_width = ($QR_width-$logo_qr_width)/2;
        //echo $from_width;exit;
        imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);			
}
imagepng($QR, $filename);
imagedestroy($QR);
$smarty->assign('seller_qrcode_img', $filename);
$smarty->assign('seller_qrcode_text', $basic_info['shop_name']);
//商家二维码 by wu end
$basic_info['shop_logo'] = str_replace('../', '', $basic_info['shop_logo']);

//OSS文件存储ecmoban模板堂 --zhuo start
if($GLOBALS['_CFG']['open_oss'] == 1 && $basic_info['shop_logo']){
    $bucket_info = get_bucket_info();
    $basic_info['shop_logo'] = $bucket_info['endpoint'] . $basic_info['shop_logo'];
}else{
    $basic_info['shop_logo'] = $_CFG['site_domain'] . $basic_info['shop_logo'];
}
//OSS文件存储ecmoban模板堂 --zhuo end    

if ($GLOBALS['_CFG']['customer_service'] == 0) {
    $im_merchant_id = 0;
}else{
    $im_merchant_id = $merchant_id;
}

/*  @author-bylu 判断当前商家是否允许"在线客服" start  */
$shop_information = get_shop_name($im_merchant_id);
$shop_information['kf_tel'] =$db->getOne("SELECT kf_tel FROM ".$ecs->table('seller_shopinfo')."WHERE ru_id = '$im_merchant_id'");
//判断当前商家是平台,还是入驻商家 bylu
if($im_merchant_id == 0){
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

/*处理客服QQ数组 by kong*/
if($basic_info['kf_qq']){
    $kf_qq=array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
    $kf_qq=explode("|",$kf_qq[0]);
    if(!empty($kf_qq[1])){
        $basic_info['kf_qq'] = $kf_qq[1];
    }else{
       $basic_info['kf_qq'] = ""; 
    }
    
}else{
    $basic_info['kf_qq'] = "";
}
/*处理客服旺旺数组 by kong*/
if($basic_info['kf_ww']){
    $kf_ww=array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
    $kf_ww=explode("|",$kf_ww[0]);
    if(!empty($kf_ww[1])){
        $basic_info['kf_ww'] = $kf_ww[1];
    }else{
        $basic_info['kf_ww'] ="";
    }
    
}else{
    $basic_info['kf_ww'] ="";
}

$cat_list = cat_list(0, 1, 0, 'merchants_category', array(), 0, $merchant_id);
$smarty->assign('cat_store_list',  $cat_list);
$smarty->assign('basic_info',         $basic_info);  //店铺详细信息
$smarty->assign('grade_info',$grade_info);
$smarty->assign('site_domain',          $_CFG['site_domain']);  //网站域名
$smarty->assign('merchant_id',          $merchant_id);
$smarty->assign('warehouse_id',       $region_id);
$smarty->assign('area_id',       $area_id);
$smarty->assign('temp_code',       $temp_code);

$smarty->display('preview.dwt');

?>