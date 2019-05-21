<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

class QueueEntityResolver implements \Illuminate\Contracts\Queue\EntityResolver
{
	public function resolve($type, $id)
	{
		$instance = (new $type())->find($id);

		if ($instance) {
			return $instance;
		}

		throw new \Illuminate\Contracts\Queue\EntityNotFoundException($type, $id);
	}
}

?>
