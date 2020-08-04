<?php

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\UnderlyingStorageError;

interface MyLink
{

	/**
	 * @param string $str
	 * @return string
	 */
	public function escapeString(string $str) : string;

	/**
	 * @param string $query
	 * @return MyResult
	 * @throws UnderlyingStorageError
	 */
	public function query(string $query) : MyResult;

	/**
	 * @param string $query
	 * @throws UnderlyingStorageError
	 */
	public function command(string $query) : void;

	/**
	 * @return int
	 */
	public function getAffectedRows() : int;

	/**
	 * @return mixed
	 */
	public function getInsertedId();

}
