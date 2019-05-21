<?php
//cgxlm
namespace app\modules\wechatseller\best;

class Best extends \app\http\wechat\controllers\Plugin
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
		$articles = array('type' => 'text', 'content' => '暂无精品');
		$map['store_best'] = 1;
		$map['is_on_sale'] = 1;
		$map['is_delete'] = 0;
		$map['is_alone_sale'] = 1;
		$map['user_id'] = $this->ru_id;
		$map['review_status'] = array('gt', 2);
		$data = dao('goods')->field('goods_id, goods_name, goods_img')->where($map)->order('sort_order ASC, goods_id desc')->limit(4)->select();

		if (!empty($data)) {
			$articles = array();
			$articles['type'] = 'news';

			foreach ($data as $key => $val) {
				$articles['content'][$key]['PicUrl'] = get_wechat_image_path($val['goods_img']);
				$articles['content'][$key]['Title'] = $val['goods_name'];
				$articles['content'][$key]['Url'] = __HOST__ . url('goods/index/index', array('id' => $val['goods_id'], 'ru_id' => $this->ru_id));
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
