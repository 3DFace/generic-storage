<?php

namespace dface\GenericStorage\Mysql;

class MysqliCommandResult implements MyCommandResult
{

	private int $affected_rows;
	private $inserted_id;

	public function __construct(int $affected_rows, $inserted_id)
	{
		$this->affected_rows = $affected_rows;
		$this->inserted_id = $inserted_id;
	}

	public function getAffectedRows() : int
	{
		return $this->affected_rows;
	}

	public function getInsertedId()
	{
		return $this->inserted_id;
	}

}
