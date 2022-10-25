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
	 * @return MyQueryResult
	 * @throws UnderlyingStorageError
	 */
	public function query(string $query) : MyQueryResult;

	/**
	 * @param string $query
	 * @return MyCommandResult
	 * @throws UnderlyingStorageError
	 */
	public function command(string $query) : MyCommandResult;

	public function close() : void;

}
