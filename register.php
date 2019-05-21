<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/6
 * Time: 1:38
 */
define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require(dirname(__FILE__) . '/includes/lib_code.php');

require(ROOT_PATH . '/includes/lib_area.php');  //ecmoban模板堂 --zhuo

/* 过滤 XSS 攻击和SQL注入 */
get_request_filter();

//获取当前环境的 URL 地址
$url = $GLOBALS['ecs']->url();
$smarty->assign('url', $url);

$smarty->display('register.dwt');