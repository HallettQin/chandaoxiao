<?php

namespace App\Custom\Guestbook\Controllers;

use App\Modules\Base\Controllers\FrontendController;

class IndexController extends FrontendController
{
    public function actionIndex()
    {
        echo 'this guestbook list. ';
        echo '<a href="' . url('add') . '">Goto Add</a>';
    }

    public function actionAdd()
    {
        $this->display();
    }

    public function actionSave()
    {
        $post = [
            'title' => I('title'),
            'content' => I('content')
        ];

        // 验证数据
        // todo

        // 保存数据
        // $this->model->table('guestbook')->data($post)->add();

        // 页面跳转
        $this->redirect('index');
    }
}
