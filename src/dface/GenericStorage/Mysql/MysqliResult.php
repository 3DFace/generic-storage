<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

class MysqliResult implements MyResult
{

	/** @var null|\mysqli_result */
	private $result;

	public function __construct(?\mysqli_result $result)
	{
		$this->result = $result;
	}

	public function iterate() : iterable
	{
		if($this->result === null){
			throw new \LogicException('Query result is NULL');
		}
		return $this->result;
	}

	public function fetchRow() : ?array
	{
		if($this->result === null){
			throw new \LogicException('Query result is NULL');
		}
		return $this->result->fetch_row();
	}

	public function fetchAssoc() : ?array
	{
		if($this->result === null){
			throw new \LogicException('Query result is NULL');
		}
		return $this->result->fetch_assoc();
	}

}
