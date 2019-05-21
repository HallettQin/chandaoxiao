<?php
//cgxlm
namespace App\Contracts\Repositories\Brand;

interface BrandRepositoryInterface
{
	public function getAllBrands();

	public function getBrandDetail($id);
}


?>
