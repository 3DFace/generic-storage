<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Generic;

use dface\criteria\Criteria;

interface GenericStorage {

	/**
	 * @param $id
	 * @return \JsonSerializable|null
	 * @throws UnderlyingStorageError
	 */
	public function getItem($id) : ?\JsonSerializable;

	/**
	 * @param array|\traversable $ids
	 * @return \JsonSerializable[]|\traversable
	 * @throws UnderlyingStorageError
	 */
	public function getItems($ids) : \traversable;

	/**
	 * @param $id
	 * @param \JsonSerializable $item
	 * @param null|int $expectedRevision
	 * @throws UnderlyingStorageError|InvalidDataType|ItemAlreadyExists|UnexpectedRevision|UniqueConstraintViolation
	 */
	public function saveItem($id, \JsonSerializable $item, int $expectedRevision = null) : void;

	/**
	 * @param $id
	 * @throws UnderlyingStorageError
	 */
	public function removeItem($id) : void;

	/**
	 * @param Criteria $criteria
	 * @throws UnderlyingStorageError
	 */
	public function removeByCriteria(Criteria $criteria) : void;

	/**
	 * @throws UnderlyingStorageError
	 */
	public function clear() : void;

	/**
	 * @param array[] $orderDef - list of pairs [`property`(string), `direction`(bool)]
	 * @param int $limit
	 * @return \traversable
	 * @throws UnderlyingStorageError
	 */
	public function listAll(array $orderDef = [], int $limit = 0) : \traversable;

	/**
	 * @param array[] $orderDef - list of pairs [`property`(string), `direction`(bool)]
	 * @param Criteria $criteria
	 * @param int $limit
	 * @return \traversable
	 * @throws UnderlyingStorageError
	 */
	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : \traversable;

}
