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

$position = assign_ur_here();
$smarty->assign('page_title', $position['title']);

//
for($i=1;$i<=$_CFG['auction_ad'];$i++) {
    $home_banner   .= "'home_banner".$i.","; //预售轮播banner
}

$smarty->assign('home_banner', $home_banner);

$smarty->display('home.dwt', $cache_id);