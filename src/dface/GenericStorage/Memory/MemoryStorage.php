<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Memory;

use dface\criteria\ArrayGraphNavigator;
use dface\criteria\Criteria;
use dface\criteria\PredicateCriteriaBuilder;
use dface\criteria\SimpleComparator;
use dface\GenericStorage\Generic\GenericStorage;

class MemoryStorage implements GenericStorage {

	/** @var string */
	private $className;
	private $storage = [];
	/** @var PredicateCriteriaBuilder */
	private $criteriaBuilder;

	public function __construct(string $className) {
		$this->className = $className;
		$this->criteriaBuilder = new PredicateCriteriaBuilder(
			new ArrayGraphNavigator(),
			new SimpleComparator()
		);
	}

	public function getItem($id) : ?\JsonSerializable {
		return $this->storage[(string)$id] ?? null;
	}

	public function getItems($ids) : \traversable {
		foreach($ids as $id){
			$k = (string)$id;
			if(isset($this->storage[$k])){
				yield $this->storage[$k];
			}
		}
	}

	public function saveItem($id, \JsonSerializable $item) : void {
		if(!$item instanceof $this->className){
			throw new \InvalidArgumentException("Stored item must be instance of $this->className");
		}
		$k = (string)$id;
		$this->storage[$k] = $item;
	}

	public function removeItem($id) : void {
		$k = (string)$id;
		unset($this->storage[$k]);
	}

	public function listAll() : \traversable {
		foreach($this->storage as $item){
			yield $item;
		}
	}

	public function listByCriteria(Criteria $criteria) : \traversable {
		$fn = $this->criteriaBuilder->build($criteria);
		foreach($this->storage as $item){
			$arr = $item->jsonSerialize();
			if($fn($arr)){
				yield $item;
			}
		}
	}

}
