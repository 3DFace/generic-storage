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
	 * @return MyQueryResult
	 * @throws UnderlyingStorageError
	 */
	public function query(string $query) : MyQueryResult
	{
		try{
			$res = $this->mysqli->query($query);
		}catch (\Throwable $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
		if ($res === false) {
			throw new UnderlyingStorageError($this->mysqli->error);
		}
		return new MysqliQueryResult($res);
	}

	public function command(string $query) : MyCommandResult
	{
		try{
			$res = $this->mysqli->query($query);
		}catch (\Throwable $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
		if ($res === false) {
			throw new UnderlyingStorageError($this->mysqli->error);
		}
		return new MysqliCommandResult($this->mysqli->affected_rows, $this->mysqli->insert_id);
	}

	public function getMysqli() : \mysqli
	{
		return $this->mysqli;
	}

}
