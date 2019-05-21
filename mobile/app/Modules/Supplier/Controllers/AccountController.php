<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019-1-25
 * Time: 2:43
 */
namespace App\Modules\Supplier\Controllers;

use Think\Image;
use App\Modules\Base\Controllers\FrontendController;

class AccountController extends FrontendController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();

        $this->user_id = $_SESSION['user_id'];
        $this->actionchecklogin();
        $this->check_supplier();

        $file = [
            'order'
        ];
        $this->load_helper($file);
    }

    /**
     * 资金管理
     */
    public function actionIndex()
    {
        $seller_shopinfo = get_seller_shopinfo($_SESSION['user_id'], array('seller_money'));
        $this->assign('seller_shopinfo', $seller_shopinfo);
        $this->display();
    }

    public function actionLog() {
        if (IS_AJAX || $_GET['is_ajax'] == 1) {
            $list = get_seller_account_log();
            exit(json_encode(['log_list' => $list['log_list'], 'totalPage' => $list['page_count']]));
        }

        $this->assign('page_title', '资金明细');
        $this->display();
    }

    public function actionDetail() {
        if (IS_AJAX || $_GET['is_ajax'] == 1) {
            $list = get_account_log_list($_SESSION['user_id'], array(2, 3, 4, 5));
            exit(json_encode(['log_list' => $list['log_list'], 'totalPage' => $list['page_count']]));
        }

        $this->assign('page_title', '账户明细');
        $this->display();
    }
}
