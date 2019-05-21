<?php

namespace App\Modules\User\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class BargainController extends FrontendController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->user_id = $_SESSION['user_id'];
        $this->actionchecklogin();
        L(require(LANG_PATH . C('shop.lang') . '/user.php'));
        L(require(LANG_PATH . C('shop.lang') . '/flow.php'));
        $files = [
            'clips',
            'transaction',
            'main'
        ];
        $this->load_helper($files);
    }

    /**
     * 我参与的砍价活动
     */
    public function actionIndex()
    {
        if (IS_AJAX) {
            $size = 10;
            $page = I('page', 1, 'intval');
            $crowd_buy = bargain_buy_list($this->user_id, $size, $page);//我参与的砍价活动列表
            exit(json_encode(['list' => $crowd_buy['list'], 'totalPage' => $crowd_buy['totalpage']]));
        }
        $this->assign('page_title', '我的砍价活动');
        $this->display();
    }




    /**
     * 验证是否登录
     */
    public function actionchecklogin()
    {
        if (!$this->user_id) {
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if (IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: " . url('user/login/index', ['back_act' => $url]));
            exit;
        }
    }
}
