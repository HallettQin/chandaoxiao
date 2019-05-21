<?php
//cgxlm
namespace Illuminate\Database\Query;

class JoinClause extends Builder
{
	/**
     * The type of join being performed.
     *
     * @var string
     */
	public $type;
	/**
     * The table the join clause is joining to.
     *
     * @var string
     */
	public $table;
	/**
     * The parent query builder instance.
     *
     * @var \Illuminate\Database\Query\Builder
     */
	private $parentQuery;

	public function __construct(Builder $parentQuery, $type, $table)
	{
		$this->type = $type;
		$this->table = $table;
		$this->parentQuery = $parentQuery;
		parent::__construct($parentQuery->getConnection(), $parentQuery->getGrammar(), $parentQuery->getProcessor());
	}

	public function on($first, $operator = NULL, $second = NULL, $boolean = 'and')
	{
		if ($first instanceof \Closure) {
			return $this->whereNested($first, $boolean);
		}

		return $this->whereColumn($first, $operator, $second, $boolean);
	}

	public function orOn($first, $operator = NULL, $second = NULL)
	{
		return $this->on($first, $operator, $second, 'or');
	}

	public function newQuery()
	{
		return new static($this->parentQuery, $this->type, $this->table);
	}
}

?>
