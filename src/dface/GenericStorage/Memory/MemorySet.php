<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Memory;

use dface\GenericStorage\Generic\GenericSet;

class MemorySet implements GenericSet {

	private $set = [];

	public function contains($entityId) : bool {
		return isset($this->set[(string)$entityId]);
	}

	public function add($entityId) : void {
		$this->set[(string)$entityId] = $entityId;
	}

	public function remove($entityId) : void {
		unset($this->set[(string)$entityId]);
	}

	public function iterate() : \traversable {
		foreach($this->set as $item){
			yield $item;
		}
	}

}
