<?php
//cgxlm
function getStoreIdByGoodsId($id)
{
	$sql = 'SELECT store_id FROM ' . $GLOBALS['ecs']->table('store_goods') . ' WHERE goods_id = ' . $id;
	$res = $GLOBALS['db']->getRow($sql);
	return $res['store_id'];
}


?>
