<?php
//DATA:2017-09-29
namespace App\Http\Index\Controllers;

class Index extends \App\Http\Base\Controllers\Frontend
{
	public function __construct()
	{
		parent::__construct();
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
		header('Access-Control-Allow-Headers: X-HTTP-Method-Override, Content-Type, x-requested-with, Authorization');
	}

	public function actionIndex()
	{
		if (IS_POST) {
			$preview = input('preview', 0);

			if ($preview) {
				$module = \App\Libraries\Compile::getModule('preview');
			}
			else {
				$module = \App\Libraries\Compile::getModule();
			}

			if ($module === false) {
				$module = \App\Libraries\Compile::initModule();
			}

			$this->response(array('error' => 0, 'data' => $module ? $module : ''));
		}

		$popup_ads = S('popup_ads');

		if ($popup_ads === false) {
			$popup_ads = dao('touch_ad')->where(array('position_id' => 2018))->find();
			S('popup_ads', $popup_ads, 600);
		}

		$time = gmtime();
		$popup_enabled = 1;
		if (($popup_ads['enabled'] == 1) && ($popup_ads['start_time'] <= $time) && ($time < $popup_ads['end_time'])) {
			if (!cookie('popup_enabled')) {
				$popup_enabled = get_data_path($popup_ads['ad_code'], 'afficheimg/');
				$ad_link = $popup_ads['ad_link'];
				cookie('ad_link', $ad_link);
				cookie('popup_enabled', $popup_enabled);
			}
		}

		$this->assign('ad_link', $ad_link);
		$this->assign('popup_ads', $popup_enabled);
		$pc_tempalate = dao('shop_config')->where(array('code' => 'template', 'type' => 'hidden'))->getField('value');
		$share_data = array('title' => C('shop.shop_title'), 'desc' => C('shop.shop_desc'), 'link' => '', 'img' => '/themes/' . $pc_tempalate . '/images/logo.gif');
		$this->assign('share_data', $this->get_wechat_share_content($share_data));
		$this->assign('page_title', C('shop.shop_name'));
		$this->assign('description', C('shop.shop_desc'));
		$this->assign('keywords', C('shop.shop_keywords'));
		$this->display();
	}

	public function actionAppNav()
	{
		$app = (C('shop.wap_index_pro') ? 1 : 0);
		$this->response(array('error' => 0, 'data' => $app));
	}

	public function actionNotice()
	{
		$condition = array('is_open' => 1, 'cat_id' => 12);
		$list = $this->db->table('article')->field('article_id, title, author, add_time, file_url, open_type')->where($condition)->order('article_type DESC, article_id DESC')->limit(5)->select();
		$res = array();

		foreach ($list as $key => $vo) {
			$res[$key]['text'] = $vo['title'];
			$res[$key]['url'] = build_uri('article', array('aid' => $vo['article_id']));
		}

		$this->response(array('error' => 0, 'data' => $res));
	}

	public function actionGoods()
	{
		$number = input('post.number', 10);
		$condition = array('intro' => input('post.type', ''));
		$list = $this->getGoodsList($condition, $number);
		$res = array();
		$endtime = gmtime();

		foreach ($list as $key => $vo) {
			$res[$key]['desc'] = $vo['name'];
			$res[$key]['sale'] = $vo['sales_volume'];
			$res[$key]['stock'] = $vo['goods_number'];

			if ($vo['promote_price']) {
				$res[$key]['price'] = min($vo['promote_price'], $vo['shop_price']);
			}
			else {
				$res[$key]['price'] = $vo['shop_price'];
			}

			$res[$key]['marketPrice'] = $vo['market_price'];
			$res[$key]['img'] = $vo['goods_thumb'];
			$res[$key]['link'] = $vo['url'];
			$endtime = ($endtime < $vo['promote_end_date'] ? $vo['promote_end_date'] : $endtime);
		}

		$this->response(array('error' => 0, 'data' => $res, 'endtime' => date('Y-m-d H:i:s', $endtime)));
	}

	public function actionSpa()
	{
		$this->display();
	}

	private function getGoodsList($param = array(), $size = 10)
	{
		$data = array('id' => 0, 'brand' => 0, 'intro' => '', 'price_min' => 0, 'price_max' => 0, 'filter_attr' => 0, 'sort' => 'goods_id', 'order' => 'desc', 'keyword' => '', 'isself' => 0, 'hasgoods' => 0, 'promotion' => 0, 'page' => 1, 'type' => 1, 'size' => $size, C('VAR_AJAX_SUBMIT') => 1);
		$data = array_merge($data, $param);
		$cache_id = md5(serialize($data));
		$list = S($cache_id);

		if ($list === false) {
			$url = url('category/index/products', $data, false, true);
			$res = \App\Extensions\Http::doGet($url);

			if ($res === false) {
				$res = file_get_contents($url);
			}

			if ($res) {
				$data = json_decode($res, 1);
				$list = (empty($data['list']) ? false : $data['list']);
				S($cache_id, $list, 600);
			}
		}

		return $list;
	}
}

?>
