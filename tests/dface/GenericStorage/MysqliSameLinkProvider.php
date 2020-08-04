<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyLinkProvider;
use dface\GenericStorage\Mysql\MysqliLink;

class MysqliSameLinkProvider implements MyLinkProvider
{

	private MysqliLink $link;

	public function __construct(\mysqli $link)
	{
		$this->link = new MysqliLink($link);
	}

	public function withLink($callback)
	{
		return $callback($this->link);
	}

	public function getLink() : MysqliLink
	{
		return $this->link;
	}

}
