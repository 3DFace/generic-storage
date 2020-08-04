<?php

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\UnderlyingStorageError;

class MysqliLink implements MyLink
{

	private \mysqli $mysqli;

	public function __construct(\mysqli $link)
	{
		$this->mysqli = $link;
	}

	public function escapeString(string $str) : string
	{
		return $this->mysqli->real_escape_string($str);
	}

	/**
	 * @param string $query
	 * @return MyResult
	 * @throws UnderlyingStorageError
	 */
	public function query(string $query) : MyResult
	{
		try{
			$res = $this->mysqli->query($query);
		}catch (\Throwable $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
		if ($res === false) {
			throw new UnderlyingStorageError($this->mysqli->error);
		}
		return new MysqliResult($res);
	}

	public function command(string $query) : void
	{
		try{
			$res = $this->mysqli->query($query);
		}catch (\Throwable $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
		if ($res === false) {
			throw new UnderlyingStorageError($this->mysqli->error);
		}
	}

	public function getAffectedRows() : int
	{
		return $this->mysqli->affected_rows;
	}

	public function getInsertedId()
	{
		return $this->mysqli->insert_id;
	}

	public function getMysqli() : \mysqli
	{
		return $this->mysqli;
	}

}
