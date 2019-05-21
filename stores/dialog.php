<?php

/**
 * ECSHOP 弹窗管理
 * ============================================================================
 * 版权所有 2016-2018 产供销网络科技(广州)有限公司，并保留所有权利。
 * 网站地址: http://www.chandaoxiao.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: Hallett
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require(ROOT_PATH . '/includes/cls_json.php');
$json = new JSON;

/*------------------------------------------------------ */
//-- 删除
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'operate') {
    $result = array('dialog_type' => '', 'app' => '', 'content' => '');
    
    $page = isset($_REQUEST['page']) && !empty($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $dialog_type = empty($_REQUEST['dialog_type']) ? '' : trim($_REQUEST['dialog_type']);
    $app = empty($_REQUEST['app']) ? '' : trim($_REQUEST['app']);
    $message = empty($_REQUEST['message']) ? '' : trim($_REQUEST['message']);

    $smarty->assign("dialog_type", $dialog_type);
    $smarty->assign("app", $app);
    $smarty->assign("message", $message);
    $smarty->assign("page", $page);
    
    $result['page'] = $page;
    $result['dialog_type'] = $dialog_type;
    $result['app'] = $app;
    $result['content'] = $GLOBALS['smarty']->fetch('dialog.dwt');
    die($json->encode($result));
}


?>
