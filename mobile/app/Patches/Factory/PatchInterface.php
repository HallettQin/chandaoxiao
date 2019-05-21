<?php
//cgxlm
namespace App\Patches\Factory;

interface PatchInterface
{
	public function updateDatabaseOptionally();

	public function updateFiles();
}


?>
