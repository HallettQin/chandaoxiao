<?php
//cgxlm
namespace Payment\Utils;

class DataParser
{
	static public function toXml($values)
	{
		if (!is_array($values) || (count($values) <= 0)) {
			return false;
		}

		$xml = '<xml>';

		foreach ($values as $key => $val) {
			if (is_numeric($val)) {
				$xml .= '<' . $key . '>' . $val . '</' . $key . '>';
			}
			else {
				$xml .= '<' . $key . '><![CDATA[' . $val . ']]></' . $key . '>';
			}
		}

		$xml .= '</xml>';
		return $xml;
	}

	static public function toArray($xml)
	{
		if (!$xml) {
			return false;
		}

		$xml_parser = xml_parser_create();

		if (!xml_parse($xml_parser, $xml, true)) {
			xml_parser_free($xml_parser);
			return false;
		}

		libxml_disable_entity_loader(true);
		$data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
		return $data;
	}

	static public function toQRimg($text, $widthHeight = '150', $ecLevel = 'L', $margin = '0')
	{
		$chl = urlencode($text);
		return 'http://chart.apis.google.com/chart?chs=' . $widthHeight . 'x' . $widthHeight . '&cht=qr&chld=' . $ecLevel . '|' . $margin . '&chl=' . $chl;
	}
}


?>
