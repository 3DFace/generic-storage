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
	/** @var ArrayGraphNavigator */
	private $navigator;
	/** @var SimpleComparator */
	private $comparator;

	public function __construct(string $className) {
		$this->className = $className;
		$this->comparator = new SimpleComparator();
		$this->navigator = new ArrayGraphNavigator();
		$this->criteriaBuilder = new PredicateCriteriaBuilder(
			$this->navigator,
			$this->comparator
		);
	}

	public function getItem($id) : ?\JsonSerializable {
		return $this->storage[(string)$id] ?? null;
	}

	public function getItems($ids) : \traversable {
		foreach($ids as $id){
			$k = (string)$id;
			if(isset($this->storage[$k])){
				yield $k => $this->storage[$k];
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

	public function removeByCriteria(Criteria $criteria) : void {
		$fn = $this->criteriaBuilder->build($criteria);
		foreach($this->storage as $k=>$item){
			$arr = $item->jsonSerialize();
			if($fn($arr)){
				unset($this->storage[$k]);
			}
		}
	}

	public function listAll(array $orderDef = [], int $limit = 0) : \traversable {
		$values = $this->storage;
		if($orderDef){
			$orderComparator = new MemoryOrderDefComparator($orderDef, $this->navigator, $this->comparator);
			uasort($values, function ($i1, $i2) use ($orderComparator){
				return $orderComparator->compare($i1, $i2);
			});
		}
		if($limit){
			$values = array_slice($values, 0, $limit, true);
		}
		return new \ArrayIterator($values);
	}

	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : \traversable {
		$fn = $this->criteriaBuilder->build($criteria);
		$values = [];
		foreach($this->storage as $k=>$item){
			$arr = $item->jsonSerialize();
			if($fn($arr)){
				$values[$k] = $item;
			}
		}
		if($orderDef){
			$orderComparator = new MemoryOrderDefComparator($orderDef, $this->navigator, $this->comparator);
			uasort($values, function ($i1, $i2) use ($orderComparator){
				return $orderComparator->compare($i1, $i2);
			});
		}
		if($limit){
			$values = array_slice($values, 0, $limit, true);
		}
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return new \ArrayIterator($values);
	}

}
