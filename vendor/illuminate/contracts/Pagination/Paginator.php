<?php
//cgxlm
namespace Illuminate\Contracts\Pagination;

interface Paginator
{
	public function url($page);

	public function appends($key, $value = NULL);

	public function fragment($fragment = NULL);

	public function nextPageUrl();

	public function previousPageUrl();

	public function items();

	public function firstItem();

	public function lastItem();

	public function perPage();

	public function currentPage();

	public function hasPages();

	public function hasMorePages();

	public function isEmpty();

	public function render($view = NULL, $data = array());
}


?>
