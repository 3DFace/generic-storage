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

	/**
	 * @param string $className
	 * @param MyLinkProvider $link_provider
	 * @param string $tableName
	 * @param string|null $idPropertyName
	 * @param string $idColumnDef
	 * @param bool $idExtracted
	 * @param string|null $revisionPropertyName
	 * @param string|null $seqIdPropertyName
	 * @param array $add_generated_columns
	 * @param array $add_columns
	 * @param array $add_indexes
	 * @param bool $has_unique_secondary
	 * @param bool $temporary
	 * @param int $batch_list_size
	 * @param int $id_batch_size
	 * @param string $dataColumnDef
	 * @param int $dataMaxSize
	 * @param bool $compressed
	 */
	public function __construct(
		string $className,
		MyLinkProvider $link_provider,
		string $tableName,
		?string $idPropertyName = null,
		string $idColumnDef = 'BINARY(16)',
		bool $idExtracted = false,
		?string $revisionPropertyName = null,
		?string $seqIdPropertyName = null,
		array $add_generated_columns = [],
		array $add_columns = [],
		array $add_indexes = [],
		bool $has_unique_secondary = false,
		bool $temporary = false,
		int $batch_list_size = 10000,
		int $id_batch_size = 500,
		string $dataColumnDef = 'TEXT',
		int $dataMaxSize = 65535,
		bool $compressed = true
	) {
		$this->linkProvider = $link_provider;
		$this->jsonStorage = new MyJsonStorage(
			$className,
			$tableName,
			$idPropertyName,
			$idColumnDef,
			$idExtracted,
			$revisionPropertyName,
			$seqIdPropertyName,
			$add_generated_columns,
			$add_columns,
			$add_indexes,
			$has_unique_secondary,
			$temporary,
			$batch_list_size,
			$id_batch_size,
			$dataColumnDef,
			$dataMaxSize,
			$compressed);
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
