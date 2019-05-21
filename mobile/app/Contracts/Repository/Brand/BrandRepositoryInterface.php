<?php
//cgxlm
namespace App\Contracts\Repository\Brand;

interface BrandRepositoryInterface
{
	public function getAllBrands();

	public function getBrandDetail($id);
}


?>
