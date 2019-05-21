<?php
//cgxlm
namespace App\Http\Wechat\Plugins\news;

class News extends \App\Http\Wechat\Controllers\Plugin
{
	protected $plugin_name = '';
	protected $ru_id = 0;
	protected $cfg = array();

	public function __construct($cfg = array())
	{
		parent::__construct();
		$this->plugin_name = strtolower(basename(__FILE__, '.php'));
		$this->cfg = $cfg;
		$this->ru_id = $this->cfg['ru_id'];
	}

	public function install()
	{
		$this->plugin_display('install', $this->cfg);
	}

	public function returnData($fromusername, $info)
	{
		$articles = array('type' => 'text', 'content' => '暂无新品');
		$map = array('is_on_sale' => 1, 'is_delete' => 0, 'is_alone_sale' => 1);

		if (0 < $this->ru_id) {
			$map['store_new'] = 1;
			$map['user_id'] = $this->ru_id;
			$map['review_status'] = array('gt', 2);
		}
		else {
			$map['is_new'] = 1;
		}

		$data = dao('goods')->field('goods_id, goods_name, goods_img')->where($map)->order('sort_order ASC, goods_id desc')->limit(4)->select();

		if (!empty($data)) {
			$articles = array();
			$articles['type'] = 'news';

			foreach ($data as $key => $val) {
				$articles['content'][$key]['PicUrl'] = get_wechat_image_path($val['goods_img']);
				$articles['content'][$key]['Title'] = $val['goods_name'];
				$articles['content'][$key]['Url'] = __HOST__ . url('goods/index/index', array('id' => $val['goods_id'], 'ru_id' => $this->ru_id));
			}

			if ($this->ru_id == 0) {
				$this->updatePoint($fromusername, $info);
			}
		}

		return $articles;
	}

	public function updatePoint($fromusername, $info)
	{
		if (!empty($info)) {
			$config = array();
			$config = unserialize($info['config']);
			if (isset($config['point_status']) && ($config['point_status'] == 1)) {
				$where = 'openid = "' . $fromusername . '" and keywords = "' . $info['command'] . '" and createtime > (UNIX_TIMESTAMP(NOW())- ' . $config['point_interval'] . ')';
				$sql = 'SELECT count(*) as num FROM {pre}wechat_point WHERE ' . $where . 'ORDER BY createtime DESC';
				$num = $GLOBALS['db']->query($sql);

				if ($num[0]['num'] < $config['point_num']) {
					$this->do_point($fromusername, $info, $config['point_value']);
				}
			}
		}
	}

	public function executeAction()
	{
	}
}

?>
