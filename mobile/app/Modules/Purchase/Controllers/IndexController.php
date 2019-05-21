<?php
//cgxlm
namespace App\Modules\Purchase\Controllers;

class IndexController extends \App\Modules\Base\Controllers\FrontendController
{
    protected $children;
    protected $cat_id = 0;
    protected $mode = '';

	public function actionIndex()
	{
		$this->assign('page_title', '采购模式');

        //模式
        $mode = isset($_GET['mode'])?trim($_GET['mode']):'groupbuy';
        $this->assign('mode', $mode);

            //获取所有一级分类
        $category = S('category0');
        if (!$category) {
            $category = get_child_tree(0);
            S('category0', $category);
        }

        $id = isset($_GET['id'])?intval($_GET['id']): 0;
        $ids = array_column($category, 'id');
        if (!$id || !in_array($id, $ids)) {
            $id = $category[0]['id'];
        }

        $this->assign('id', $id);
        $this->assign('category', $category);
		$this->display();
	}


    //ajax 获取商品
    public function actionProducts() {
        $page = I('request.page', 1, 'intval');
        if (IS_AJAX || $_GET['is_ajax']) {
            $size = 10;

            $cat_id = I('request.id', 0, 'intval');
            if (empty($cat_id)) {
                exit(json_encode(['code' => 1, 'message' => '请选择分类']));
            }

            $mode = I('get.mode');
            if (!in_array($mode, ['groupbuy', 'presale', 'wholesale', 'sample'])) {
                exit(json_encode(['code' => 1, 'message' => '请选择商品模式']));
            }

            $goods = activity_list($cat_id, $mode, 'act_id', 'desc', $page, $size);
            die(json_encode(['lists' => $goods['list'], 'totalPage' => ceil($goods['total'] / $size), 'id'=>$cat_id]));
        }
    }

    protected function init_params() {
        $this->cat_id = I('request.id', 0, 'intval');

        if ($this->cat_id == 0) {
            $this->children = 0;
        } else {
            $this->children = get_children($this->cat_id);
        }

        $mode = I('mode');
        if (!in_array($mode, ['groupbuy', 'presale', 'wholesale', 'sample'])) {
            exit(json_encode(['code' => 1, 'message' => '请选择商品模式']));
        }

        $this->mode = $mode;

    }

	public function actionList()
	{
		$this->assign('title', '现货列表');
		$this->assign('action', 'list');
		$this->display();
	}

	public function actionGoods()
	{
		$this->assign('title', '现货详情');
		$this->assign('action', 'goods');
		$this->display();
	}

	public function actionCart()
	{
		$this->assign('title', '进货单');
		$this->assign('action', 'cart');
		$this->display();
	}

	public function actionInfo()
	{
		$this->assign('title', '现货首页');
		$this->assign('action', 'info');
		$result = array();
		$this->ajaxReturn($result);
	}

	public function actionShow()
	{
		$this->assign('title', '求购信息');
		$this->assign('action', 'show');
		$this->display();
	}
}

?>
