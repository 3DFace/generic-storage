<?php

namespace dface\GenericStorage\Memory;

use dface\criteria\builder\ArrayGraphNavigator;
use dface\criteria\builder\PredicateCriteriaBuilder;
use dface\criteria\builder\SimpleComparator;
use dface\criteria\node\Criteria;
use dface\GenericStorage\Generic\ArrayPathNavigator;
use dface\GenericStorage\Generic\GenericStorage;
use dface\GenericStorage\Generic\InvalidDataType;
use dface\GenericStorage\Generic\ItemAlreadyExists;
use dface\GenericStorage\Generic\UnexpectedRevision;

class MemoryStorage implements GenericStorage
{

	/** @var string */
	private string $className;
	private array $storage = [];
	private PredicateCriteriaBuilder $criteriaBuilder;
	private ArrayGraphNavigator $navigator;
	private SimpleComparator $comparator;
	/** @var string[] */
	private array $revisionPropertyPath;
	/** @var string[] */
	private array $seqIdPropertyPath;
	private int $autoIncrement = 0;

	public function __construct(
		string $className,
		string $revisionPropertyName = null,
		string $seqIdPropertyName = null
	) {
		$this->className = $className;
		$this->comparator = new SimpleComparator();
		$this->navigator = new ArrayGraphNavigator();
		$this->criteriaBuilder = new PredicateCriteriaBuilder(
			$this->navigator,
			$this->comparator
		);
		$this->revisionPropertyPath = $revisionPropertyName !== null ? \explode('/', $revisionPropertyName) : [];
		$this->seqIdPropertyPath = $seqIdPropertyName !== null ? \explode('/', $seqIdPropertyName) : [];
	}

	public function getItem($id) : ?\JsonSerializable
	{
		$record = $this->storage[(string)$id] ?? null;
		if ($record === null) {
			return null;
		}
		[$arr, $rev, $seq_id] = $record;
		return $this->deserialize($arr, $rev, $seq_id);
	}

	/**
	 * @param iterable $ids
	 * @return \JsonSerializable[]|iterable
	 */
	public function getItems(iterable $ids) : iterable
	{
		foreach ($ids as $id) {
			$k = (string)$id;
			if (isset($this->storage[$k])) {
				[$arr, $rev, $seq_id] = $this->storage[$k];
				yield $k => $this->deserialize($arr, $rev, $seq_id);
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
		$record = $this->storage[$k] ?? null;
		$this->autoIncrement++;
		if ($record === null) {
			$record = [null, 0, $this->autoIncrement];
		}
		[, $rev, $seq_id] = $record;
		if ($expectedRevision !== null && $expectedRevision !== $rev) {
			if ($expectedRevision === 0) {
				throw new ItemAlreadyExists("Item '$id' already exists");
			}
			throw new UnexpectedRevision("Item '$id' expected revision $expectedRevision does not match actual $rev");
		}
		$this->storage[$k] = [$item->jsonSerialize(), $rev + 1, $seq_id];
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

	public function listAll(array $orderDef = [], int $limit = 0) : iterable
	{
		$values = [];
		foreach ($this->storage as $k => [$arr]) {
			$values[$k] = $arr;
		}
		yield from $this->iterateValues($values, $orderDef, $limit);
	}

	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : iterable
	{
		$fn = $this->criteriaBuilder->build($criteria);
		$values = [];
		foreach ($this->storage as $k => [$arr]) {
			if ($fn($arr)) {
				$values[$k] = $arr;
			}
		}
		yield from $this->iterateValues($values, $orderDef, $limit);
	}

	private function iterateValues(array $values, array $orderDef, int $limit) : \Generator
	{
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
			[, $rev, $seq_id] = $this->storage[$k];
			yield $k => $this->deserialize($arr, $rev, $seq_id);
		}
	}

	private function deserialize(array $arr, int $rev, int $seq_id)
	{
		if ($this->revisionPropertyPath) {
			ArrayPathNavigator::setPropertyValue($arr, $this->revisionPropertyPath, $rev);
		}
		if ($this->seqIdPropertyPath) {
			ArrayPathNavigator::setPropertyValue($arr, $this->seqIdPropertyPath, $seq_id);
		}
		$cls = $this->className;
		/** @noinspection PhpUndefinedMethodInspection */
		return $cls::deserialize($arr);
	}

}
