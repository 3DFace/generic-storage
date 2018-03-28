<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Memory;

use dface\criteria\ArrayGraphNavigator;
use dface\criteria\Criteria;
use dface\criteria\PredicateCriteriaBuilder;
use dface\criteria\SimpleComparator;
use dface\GenericStorage\Generic\ArrayPathNavigator;
use dface\GenericStorage\Generic\GenericStorage;
use dface\GenericStorage\Generic\InvalidDataType;
use dface\GenericStorage\Generic\ItemAlreadyExists;
use dface\GenericStorage\Generic\UnexpectedRevision;

class MemoryStorage implements GenericStorage
{

	/** @var string */
	private $className;
	private $storage = [];
	/** @var PredicateCriteriaBuilder */
	private $criteriaBuilder;
	/** @var ArrayGraphNavigator */
	private $navigator;
	/** @var SimpleComparator */
	private $comparator;
	/** @var string[]|null */
	private $revisionPropertyPath;

	public function __construct(string $className, string $revisionPropertyName = null)
	{
		$this->className = $className;
		$this->comparator = new SimpleComparator();
		$this->navigator = new ArrayGraphNavigator();
		$this->criteriaBuilder = new PredicateCriteriaBuilder(
			$this->navigator,
			$this->comparator
		);
		if ($revisionPropertyName !== null) {
			$this->revisionPropertyPath = explode('/', $revisionPropertyName);
		}
	}

	public function getItem($id) : ?\JsonSerializable
	{
		[$arr, $rev] = $this->storage[(string)$id] ?? [null, 0];
		if($arr === null){
			return null;
		}
		return $this->deserialize($arr, $rev);
	}

	/**
	 * @param array|\traversable $ids
	 * @return \JsonSerializable[]|\traversable
	 */
	public function getItems($ids) : \traversable
	{
		foreach ($ids as $id) {
			$k = (string)$id;
			if (isset($this->storage[$k])) {
				[$arr, $rev] = $this->storage[$k];
				yield $k => $this->deserialize($arr, $rev);
			}
		}
	}

	/**
	 * @param $id
	 * @param \JsonSerializable $item
	 * @param int|null $expectedRevision
	 * @throws InvalidDataType
	 * @throws UnexpectedRevision|ItemAlreadyExists
	 */
	public function saveItem($id, \JsonSerializable $item, int $expectedRevision = null) : void
	{
		if (!$item instanceof $this->className) {
			throw new InvalidDataType("Stored item must be instance of $this->className");
		}
		$k = (string)$id;
		[, $rev] = $this->storage[$k] ?? [null, 0];
		if($expectedRevision !== null && $expectedRevision !== $rev) {
			if ($expectedRevision === 0) {
				throw new ItemAlreadyExists("Item '$id' already exists");
			}
			throw new UnexpectedRevision("Item '$id' expected revision $expectedRevision does not match actual $rev");
		}
		$this->storage[$k] = [$item->jsonSerialize(), $rev + 1];
	}

	public function removeItem($id) : void
	{
		$k = (string)$id;
		unset($this->storage[$k]);
	}

	public function removeByCriteria(Criteria $criteria) : void
	{
		$fn = $this->criteriaBuilder->build($criteria);
		foreach ($this->storage as $k => [$arr]) {
			if ($fn($arr)) {
				unset($this->storage[$k]);
			}
		}
	}

	public function clear() : void
	{
		$this->storage = [];
	}

	public function listAll(array $orderDef = [], int $limit = 0) : \traversable
	{
		$values = [];
		foreach ($this->storage as $k => [$arr]) {
			$values[$k] = $arr;
		}
		if ($orderDef) {
			$orderComparator = new MemoryOrderDefComparator($orderDef, $this->navigator, $this->comparator);
			uasort($values, function ($arr1, $arr2) use ($orderComparator) {
				return $orderComparator->compare($arr1, $arr2);
			});
		}
		if ($limit) {
			$values = \array_slice($values, 0, $limit, true);
		}
		foreach ($values as $k => $arr) {
			[, $rev] = $this->storage[$k];
			yield $k => $this->deserialize($arr, $rev);
		}
	}

	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : \traversable
	{
		$fn = $this->criteriaBuilder->build($criteria);
		$values = [];
		foreach ($this->storage as $k => [$arr]) {
			if ($fn($arr)) {
				$values[$k] = $arr;
			}
		}
		if ($orderDef) {
			$orderComparator = new MemoryOrderDefComparator($orderDef, $this->navigator, $this->comparator);
			uasort($values, function ($arr1, $arr2) use ($orderComparator) {
				return $orderComparator->compare($arr1, $arr2);
			});
		}
		if ($limit) {
			$values = \array_slice($values, 0, $limit, true);
		}
		foreach ($values as $k => $arr) {
			[, $rev] = $this->storage[$k];
			yield $k => $this->deserialize($arr, $rev);
		}
	}

	private function deserialize(array $arr, int $rev){
		if($this->revisionPropertyPath !== null){
			ArrayPathNavigator::setPropertyValue($arr, $this->revisionPropertyPath, $rev);
		}
		$cls = $this->className;
		/** @noinspection PhpUndefinedMethodInspection */
		return $cls::deserialize($arr);
	}

}
