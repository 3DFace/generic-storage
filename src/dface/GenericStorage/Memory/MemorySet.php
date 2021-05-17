<?php

namespace dface\GenericStorage\Memory;

use dface\GenericStorage\Generic\GenericSet;

class MemorySet implements GenericSet
{

	private array $set = [];

	public function contains($id) : bool
	{
		return isset($this->set[(string)$id]);
	}

	public function add($id) : void
	{
		$this->set[(string)$id] = $id;
	}

	public function remove($id) : void
	{
		unset($this->set[(string)$id]);
	}

	public function clear() : void
	{
		$this->set = [];
	}

	public function iterate() : iterable
	{
		foreach ($this->set as $item) {
			yield $item;
		}
	}

}
