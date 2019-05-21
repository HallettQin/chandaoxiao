<?php

namespace App\Modules\Bonus\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    public function __construct()
    {
        parent::__construct();
        $files = [
            'clips',
            'transaction',
            'main'
        ];
        L(require(LANG_PATH . C('shop.lang') . '/user.php'));
        L(require(LANG_PATH . C('shop.lang') . '/flow.php'));
        $this->assign('lang', array_change_key_case(L()));
        $this->load_helper($files);
    }

    /**
     * 显示所有种类的红包
     * $status 0用户 1商品 2订单金额 3线下 4自行领取
     */
    public function actionIndex()
    {
        $size = 5;
        $page = I('page', 1, 'intval');
        $status = 4;
        if (IS_AJAX) {
            $bonus_list = get_bonus_list($size, $page, $status);
            exit(json_encode(['bonus_list' => $bonus_list, 'totalPage' => $bonus_list['totalpage']]));
        }
        $this->assign('status', $status);
        $this->assign('page_title', '红包列表');
        $this->display();
    }

    /**
     * 领取红包
     * @param int $type_id 红包id
     */
    public function actionGetBonus()
    {
        $type_id = I('bonus_id');
        if (IS_AJAX) {
            if (empty($_SESSION['user_id'])) {
                die(json_encode(['msg' => "请登录", 'error' => '1']));
            }
            $sql = " SELECT bonus_id FROM " . $GLOBALS['ecs']->table('user_bonus') . " WHERE bonus_type_id = '$type_id' AND user_id = '$_SESSION[user_id]' LIMIT 1 ";
            $exist = $GLOBALS['db']->getOne($sql);
            if (!empty($exist)) {
                die(json_encode(['msg' => L('already_got'), 'error' => '2']));
            } else {
                $sql = " SELECT bonus_id FROM {pre}user_bonus WHERE bonus_type_id = '$type_id' AND user_id = 0 LIMIT 1 ";
                $bonus_id = $this->db->getOne($sql);

                if (empty($bonus_id)) {
                    die(json_encode(['msg' => L('no_bonus'), 'error' => '2']));
                } else {
                    $data = [
                        'user_id' => $_SESSION['user_id'],
                        'bind_time' => gmtime()
                    ];
                    $this->db->autoExecute($GLOBALS['ecs']->table('user_bonus'), $data, 'UPDATE', "bonus_id   = '$bonus_id'");
                    die(json_encode(['msg' => L('get_success'), 'error' => '2']));
                }
            }
        }
    }
}
