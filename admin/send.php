<?php
//cgxlm
define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
$backUrl = $ecs->url() . ADMIN_PATH . '/receive.php';
header('location:https://www.99bill.com');
exit();

?>
