<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

class LinkProviderFactory
{

	public static function createLinkProvider() : MysqliSameLinkProvider
	{
		return new MysqliSameLinkProvider(self::getStaticConnection());
	}

	public static function getStaticConnection() : \mysqli
	{
		static $link;
		if ($link === null) {
			include_once __DIR__.'/../../../options.php';
			$link = \mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_BASE);
			if (!$link) {
				throw new \RuntimeException(\mysqli_connect_error());
			}
			$link->set_charset(DB_CHARSET);
		}
		return $link;
	}

}
