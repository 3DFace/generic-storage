<?php

namespace dface\GenericStorage\Mysql;

use dface\criteria\node\Criteria;
use dface\GenericStorage\Generic\GenericStorage;

class MyStorage implements GenericStorage
{

	public const COLUMN_MODE_KEEP_BODY = 0;
	public const COLUMN_MODE_LOAD_FALLBACK = 2;
	public const COLUMN_MODE_SEPARATED = 3;

	private MyJsonStorage $jsonStorage;
	private MyLinkProvider $linkProvider;

	public function __construct(MyJsonStorage $jsonStorage, MyLinkProvider $linkProvider)
	{
		$this->jsonStorage = $jsonStorage;
		$this->linkProvider = $linkProvider;
	}

	public function getJsonStorage() : MyJsonStorage
	{
		return $this->jsonStorage;
	}

	public function getLinkProvider() : MyLinkProvider
	{
		return $this->linkProvider;
	}

	/**
	 * @param $id
	 * @return \JsonSerializable|null
	 */
	public function getItem($id) : ?\JsonSerializable
	{
		return $this->linkProvider->withLink(function (MyLink $link) use ($id) {
			return $this->jsonStorage->getItem($link, $id);
		});
	}

	/**
	 * @param iterable $ids
	 * @return iterable
	 */
	public function getItems(iterable $ids) : iterable
	{
		return $this->linkProvider->withLink(function (MyLink $link) use ($ids) {
			return $this->jsonStorage->getItems($link, $ids);
		});
	}

	/**
	 * @param $id
	 * @param \JsonSerializable $item
	 * @param int|null $expectedRevision
	 * @param bool $idempotency
	 */
	public function saveItem(
		$id,
		\JsonSerializable $item,
		int $expectedRevision = null,
		bool $idempotency = false
	) : void {
		$this->linkProvider->withLink(function (MyLink $link) use ($id, $item, $expectedRevision, $idempotency) {
			$this->jsonStorage->saveItem($link, $id, $item, $expectedRevision, $idempotency);
		});
	}

	/**
	 * @param $id
	 */
	public function removeItem($id) : void
	{
		$this->linkProvider->withLink(function (MyLink $link) use ($id) {
			$this->jsonStorage->removeItem($link, $id);
		});
	}

	/**
	 * @param Criteria $criteria
	 */
	public function removeByCriteria(Criteria $criteria) : void
	{
		$this->linkProvider->withLink(function (MyLink $link) use ($criteria) {
			$this->jsonStorage->removeByCriteria($link, $criteria);
		});
	}

	public function clear() : void
	{
		$this->linkProvider->withLink(function (MyLink $link) {
			$this->jsonStorage->clear($link);
		});
	}

	/**
	 * @param array $orderDef
	 * @param int $limit
	 * @return iterable
	 */
	public function listAll(array $orderDef = [], int $limit = 0) : iterable
	{
		return $this->linkProvider->withLink(function (MyLink $link) use ($orderDef, $limit) {
			return $this->jsonStorage->listAll($link, $orderDef, $limit);
		});
	}

	/**
	 * @param Criteria $criteria
	 * @param array $orderDef
	 * @param int $limit
	 * @return iterable
	 */
	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : iterable
	{
		return $this->linkProvider->withLink(function (MyLink $link) use ($criteria, $orderDef, $limit) {
			return $this->jsonStorage->listByCriteria($link, $criteria, $orderDef, $limit);
		});
	}

	public function updateColumns($batch_size = 10000) : void
	{
		$this->linkProvider->withLink(function (MyLink $link) use ($batch_size) {
			$this->jsonStorage->updateColumns($link, $batch_size);
		});
	}

	public function reset() : void
	{
		$this->linkProvider->withLink(function (MyLink $link) {
			$this->jsonStorage->reset($link);
		});
	}

}
