<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/8
 * Time: 16:05
 */
define('IN_ECS', true);
require dirname(__FILE__) . '/includes/init.php';
define('SUPPLIERS_ACTION_LIST', 'delivery_view,back_view');
require 'mc_function.php';

//采购商列表
function kuaidi_list($audit = '') {
    $result = get_filter();

    if ($result === false) {
        $aiax = (isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0);
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'ASC' : trim($_REQUEST['sort_order']);
        $where = 'WHERE 1 ';

        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
        if (isset($_REQUEST['page_size']) && (0 < intval($_REQUEST['page_size']))) {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        } else {
            if (isset($_COOKIE['ECSCP']['page_size']) && (0 < intval($_COOKIE['ECSCP']['page_size']))) {
                $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
            }
            else {
                $filter['page_size'] = 15;
            }
        }

        $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('kuaidi') . " " . $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        $filter['page_count'] = 0 < $filter['record_count'] ? ceil($filter['record_count'] / $filter['page_size']) : 1;
        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('kuaidi') . " " . $where . "ORDER BY " . $filter['sort_by'] . ' ' . $filter['sort_order'] . "\r\n                LIMIT " . (($filter['page'] - 1) * $filter['page_size']) . ', ' . $filter['page_size'] . ' ';
        set_filter($filter, $sql);
    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $row = $GLOBALS['db']->getAll($sql);
    $arr = array('result' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
}

if ($_REQUEST['act'] == 'list') {
    $smarty->assign('ur_here', $_LANG['01_kuaidi_list']);
    $result = kuaidi_list('1, 2');

    $smarty->assign('list', $result['result']);
    $smarty->assign('filter', $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count', $result['page_count']);

    $smarty->assign('menu_select', array('action' => 'purchasers_list', 'current' => '01_kuaidi_list'));
    $smarty->assign('full_page', 1);
    $smarty->display('kuaidi_list.dwt');
} elseif ($_REQUEST['act'] == 'add') {
//    admin_priv('cat_manage');

    $smarty->assign('form_act', 'insert');

    $smarty->assign('ur_here', $_LANG['02_kuaidi_add']);
    $smarty->assign('action_link', array('href' => 'kuaidi.php?act=list', 'text' => $_LANG['01_kuaidi_list']));

    $smarty->display('kuaidi_info.dwt');
} elseif ($_REQUEST['act'] == 'edit') {
//    admin_priv('cat_manage');
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);

    $date = array('*');
    $where = "id = '$id'";

    $note_info = get_table_date('kuaidi', $where, $date);
    $smarty->assign('note',    $note_info);

    $smarty->assign('form_act', 'update');

    $smarty->assign('ur_here', $_LANG['03_kuaidi_edit']);
    $smarty->assign('action_link', array('href' => 'kuaidi.php?act=list', 'text' => $_LANG['01_kuaidi_list']));

    $smarty->display('kuaidi_info.dwt');
} elseif ($_REQUEST['act'] == 'insert' || $_REQUEST['act'] == 'update') {
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);

    $other['login_name'] = empty($_POST['login_name']) ? '' : trim($_POST['login_name']);
    if ($_POST['password']) {
        $other['password'] = md5(trim($_POST['password']));
    }
    $other['company_name'] = empty($_POST['company_name']) ? '' : trim($_POST['company_name']);
    $other['mobile'] = empty($_POST['mobile']) ? '' : trim($_POST['mobile']);
    $other['real_name'] = empty($_POST['real_name']) ? '' : trim($_POST['real_name']);
    $other['bank_mobile'] = empty($_POST['bank_mobile']) ? '' : trim($_POST['bank_mobile']);
    $other['bank_name'] = empty($_POST['bank_name']) ? '' : trim($_POST['bank_name']);
    $other['bank_card'] = empty($_POST['bank_card']) ? '' : trim($_POST['bank_card']);
    $other['status'] = empty($_POST['status']) ? 0 : intval($_POST['status']);
    $other['company_key'] = empty($_POST['company_key']) ? '' : strtolower(trim($_POST['company_key']));

    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('kuaidi') . ' where company_key = "' . $other['company_key'] . '"';

    if ($id) {
        $sql .= ' AND id != '.$id;
    }
    if ($GLOBALS['db']->getRow($sql)) {
        sys_msg($lang_name, 0, $link);
    }

    if($id){
        $db->autoExecute($ecs->table('kuaidi'), $other, "UPDATE", "id = '$id'");
        $href = 'alitongxin_configure.php?act=edit&id=' . $id;

        $lang_name = $_LANG['edit_success'];
    } else{
        $other['add_time'] = gmtime();
        $db->autoExecute($ecs->table('kuaidi'), $other);
        $href = 'alitongxin_configure.php?act=edit&id=' . $id;

        $lang_name = $_LANG['add_success'];
    }
    $href = 'kuaidi.php?act=list';
    $link[] = array('text' => $_LANG['go_back'], 'href'=>$href);
    sys_msg($lang_name, 0, $link);
} elseif ( $_REQUEST['act'] == 'import') {
    //导入
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);

    $smarty->assign('ur_here', $_LANG['04_kuaidi_import']);
    $smarty->assign('action_link', array('href' => 'kuaidi.php?act=list', 'text' => $_LANG['01_kuaidi_list']));

    $smarty->assign('id',    $id);
    $smarty->assign('form_act', 'import_insert');

    $smarty->display('kuaidi_import.dwt');
} elseif ( $_REQUEST['act'] == 'import_insert') {
    require ROOT_PATH . '/includes/phpexecl/PHPExcel.php';

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    if (!$id) {
        sys_msg('链接失效', 0, $link);
    }
    if (!$_FILES['upload_file']) {
        sys_msg('没有上传文件;', 0, $link);
    }


    $kuaidi =  $GLOBALS['db']->getRow('SELECT * FROM ' . $GLOBALS['ecs']->table('kuaidi') . ' WHERE id =' .$id, true);
    if (!$kuaidi) {
        sys_msg('链接失效', 0, $link);
    }

    $company_key = $kuaidi['company_key'];

    if (!$kuaidi['status']) {
        sys_msg('快递公司账号已关闭', 0, $link);
    }


    $add = empty($_REQUEST['add']) ? 0 : intval($_REQUEST['add']);

    $path = '../mc_upfile/' . date('Ym') . '/';
    $file_chk = uploadfile('upload_file', $path, 'kuaidi.php?act=import&id='.$id, 1024000, 'xls');
    if ($file_chk) {
        $filename = $path . $file_chk[0];

        $objReader = PHPExcel_IOFactory::createReader('Excel5');//use excel2007 for 2007 format
        $objPHPExcel = $objReader->load($filename); //$filename可以是上传的表格，或者是指定的表格
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow(); // 取得总行数
        // $highestColumn = $sheet->getHighestColumn(); // 取得总列数

        //循环读取excel表格,读取一条,插入一条
        //j表示从哪一行开始读取  从第二行开始读取，因为第一行是标题不保存
        //$a表示列号
        $arr = [];
        for($j=2;$j<=$highestRow;$j++)
        {
            $a = $objPHPExcel->getActiveSheet()->getCell("A".$j)->getValue(); //订单号
            $b = $objPHPExcel->getActiveSheet()->getCell("B".$j)->getValue(); //重量
            $c = $objPHPExcel->getActiveSheet()->getCell("C".$j)->getValue(); //价格
            $d = $objPHPExcel->getActiveSheet()->getCell("D".$j)->getValue(); //英文标识

            if ($a && $b && $c && strtolower($d) == strtolower($company_key)) {
                //订单更新
                order_save_kuaidi_weight_price($kuaidi['id'], $a, $b, $c, $add);
                //发货单更新
                deliver_order_kuaidi_weight_price($kuaidi['id'], $a, $b, $c, $add);
            } else {
                if ($a) {
                    array_push($arr, $a);
                }
            }
        }
        unlink($filename);
        $info = "恭喜，批量导入成功！";
        if ($arr) {
            $info .= '但发货单号：';
            $info .= implode(',', $arr);
            $info .= '导入失败';
            sys_msg($info, 0, $link, false);
        } else {
            sys_msg($info, 0, $link, true);
        }
    }
    else {
        sys_msg('文件未上传成功;', 0, $link);
    }
}
