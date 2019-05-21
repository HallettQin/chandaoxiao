<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/9
 * Time: 21:17
 */
namespace App\Modules\Supplier\Controllers;

use Think\Image;
use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
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
     * 会员中心欢迎页
     */
    public function actionIndex()
    {
        $this->assign('index', 1);

        $this->assign_total();


        $this->assign('page_title', '生产制造商信息台首页');
        $this->display();
    }
}