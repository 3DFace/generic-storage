<?php

namespace dface\GenericStorage\Mysql;

interface MyCommandResult
{

	/**
	 * @return int
	 */
	public function getAffectedRows() : int;

	/**
	 * @return mixed
	 */
	public function getInsertedId();

}
