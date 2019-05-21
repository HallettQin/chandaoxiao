<?php
//cgxlm
namespace app\transformer;

class ArticleTransformer extends Transformer
{
	public function transform(array $map)
	{
		return array('id' => $map['article_id'], 'title' => $map['title']);
	}
}

?>
