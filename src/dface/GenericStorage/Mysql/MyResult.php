<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

interface MyResult
{

	public function iterate() : \Traversable;

	public function fetchRow() : ?array;

	public function fetchAssoc() : ?array;

}

