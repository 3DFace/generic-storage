<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Mysql;

use dface\GenericStorage\Generic\UnderlyingStorageError;

abstract class MyFun
{

	/**
	 * @param \mysqli $link
	 * @param string $query
	 * @return bool|\mysqli_result
	 * @throws UnderlyingStorageError
	 */
	public static function query(\mysqli $link, string $query){
		try{
			$result = $link->query($query);
		}catch (\Exception $e){
			throw new UnderlyingStorageError($e->getMessage(), 0, $e);
		}
		if($result === false){
			throw new UnderlyingStorageError($link->error);
		}
		return $result;
	}

}
