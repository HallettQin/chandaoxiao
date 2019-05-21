<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/25
 * Time: 10:52
 */

define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');
if (!empty($_SESSION['user_id'])) {
    //未登陆
    header("Location: index.php\n");
    exit;
}

//添加跳转
$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
$uachar = "/(nokia|sony|ericsson|mot|samsung|htc|sgh|lg|sharp|sie-|philips|panasonic|alcatel|lenovo|iphone|ipod|blackberry|meizu|android|netfront|symbian|ucweb|windowsce|palm|operamini|operamobi|opera mobi|openwave|nexusone|cldc|midp|wap|mobile)/i";

if(($ua == '' || preg_match($uachar, $ua))&& !strpos(strtolower($_SERVER['REQUEST_URI']),'wap'))
{
    $Loaction = 'mobile/';

    if (!empty($Loaction))
    {
        ecs_header("Location: $Loaction\n");

        exit;
    }
}

//首页描述
$position = assign_ur_here();
$smarty->assign('page_title', $position['title']);
$smarty->assign('keywords', htmlspecialchars($_CFG['shop_keywords']));
$smarty->assign('description', htmlspecialchars($_CFG['shop_desc']));

//
for($i=1;$i<=$_CFG['auction_ad'];$i++) {
    $home_banner   .= "'home_banner".$i.","; //预定轮播banner
}

$smarty->assign('home_banner', $home_banner);

$smarty->display('home.dwt', $cache_id);