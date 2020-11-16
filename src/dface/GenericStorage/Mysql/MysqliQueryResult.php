<?php

namespace dface\GenericStorage\Mysql;

class MysqliQueryResult implements MyQueryResult
{

	private \mysqli_result $result;

	public function __construct(\mysqli_result $result)
	{
		$this->result = $result;
	}

	public function iterate() : iterable
	{
		return $this->result;
	}

	public function fetchRow() : ?array
	{
		return $this->result->fetch_row();
	}

	public function fetchAssoc() : ?array
	{
		return $this->result->fetch_assoc();
	}

}
