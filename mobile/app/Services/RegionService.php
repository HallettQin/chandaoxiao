<?php
//cgxlm
namespace App\Services;

class RegionService
{
	private $regionRepository;

	public function __construct(\App\Repositories\Region\RegionRepository $regionRepository)
	{
		$this->regionRepository = $regionRepository;
	}

	public function regionList($args)
	{
		$regionId = (empty($args['id']) ? 0 : $args['id']);

		if (empty($args['id'])) {
			$list = $this->regionRepository->regionListByType(0);
		}
		else {
			$type = $this->regionRepository->getRegionTypeById($regionId);
			$list = $this->regionRepository->regionListByType($type + 1);
		}

		foreach ($list as $k => $v) {
			if ($v['parent_id'] != $regionId) {
				unset($list[$k]);
			}
		}

		sort($list);
		return $list;
	}
}


?>
