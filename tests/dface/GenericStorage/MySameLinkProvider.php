<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyLinkProvider;

class MySameLinkProvider implements MyLinkProvider
{

	/** @var \mysqli */
	private $link;

	/**
	 * @param \mysqli $link
	 */
	public function __construct(\mysqli $link)
	{
		$this->link = $link;
	}

	public function withLink($callback)
	{
		return $callback($this->link);
	}

	public function getLink() : \mysqli
	{
		return $this->link;
	}

}
