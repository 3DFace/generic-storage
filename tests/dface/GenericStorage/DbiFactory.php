<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\Mysql\MysqlException;

class DbiFactory {

	public static function getConnection() : \mysqli {
		static $dbi;
		if($dbi === null){
			$fac = self::getConnectionFactory();
			$dbi = $fac();
		}
		return $dbi;
	}

	public static function getConnectionFactory() : callable {
		static $fac;
		if($fac === null){
			$fac = function (){
				/** @noinspection UntrustedInclusionInspection */
				include_once __DIR__.'/../../../options.php';
				$link = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_BASE);
				if(!$link){
					throw new MysqlException(mysqli_connect_error());
				}
				$link->set_charset(DB_CHARSET);
				return $link;
			};
		}
		return $fac;
	}

}
