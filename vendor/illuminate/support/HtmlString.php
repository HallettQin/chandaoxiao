<?php
//cgxlm
namespace Illuminate\Support;

class HtmlString implements \Illuminate\Contracts\Support\Htmlable
{
	/**
     * The HTML string.
     *
     * @var string
     */
	protected $html;

	public function __construct($html)
	{
		$this->html = $html;
	}

	public function toHtml()
	{
		return $this->html;
	}

	public function __toString()
	{
		return $this->toHtml();
	}
}

?>
