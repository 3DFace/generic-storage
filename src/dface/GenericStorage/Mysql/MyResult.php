<?php

namespace dface\GenericStorage\Mysql;

interface MyResult
{

	public function iterate() : iterable;

	public function fetchRow() : ?array;

	public function fetchAssoc() : ?array;

}

