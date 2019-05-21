<?php
//cgxlm
namespace Illuminate\Database\Eloquent;

class ModelNotFoundException extends \RuntimeException
{
	/**
     * Name of the affected Eloquent model.
     *
     * @var string
     */
	protected $model;
	/**
     * The affected model IDs.
     *
     * @var int|array
     */
	protected $ids;

	public function setModel($model, $ids = array())
	{
		$this->model = $model;
		$this->ids = array_wrap($ids);
		$this->message = 'No query results for model [' . $model . ']';

		if (0 < count($this->ids)) {
			$this->message .= ' ' . implode(', ', $this->ids);
		}
		else {
			$this->message .= '.';
		}

		return $this;
	}

	public function getModel()
	{
		return $this->model;
	}

	public function getIds()
	{
		return $this->ids;
	}
}

?>
