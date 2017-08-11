<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Generic;

use dface\criteria\Criteria;

interface GenericStorage {

	/**
	 * @param $id
	 * @return \JsonSerializable|null
	 * @throws GenericStorageError
	 */
	public function getItem($id) : ?\JsonSerializable;

	/**
	 * @param array|\traversable $ids
	 * @return \JsonSerializable[]|\traversable
	 * @throws GenericStorageError
	 */
	public function getItems($ids) : \traversable;

	/**
	 * @param $id
	 * @param \JsonSerializable $item
	 * @throws GenericStorageError
	 */
	public function saveItem($id, \JsonSerializable $item) : void;

	/**
	 * @param $id
	 * @throws GenericStorageError
	 */
	public function removeItem($id) : void;

	/**
	 * @param Criteria $criteria
	 * @throws GenericStorageError
	 */
	public function removeByCriteria(Criteria $criteria) : void;

	/**
	 * @param array[] $orderDef - list of pairs [`property`(string), `direction`(bool)]
	 * @param int $limit
	 * @return \traversable
	 * @throws GenericStorageError
	 */
	public function listAll(array $orderDef = [], int $limit = 0) : \traversable;

	/**
	 * @param array[] $orderDef - list of pairs [`property`(string), `direction`(bool)]
	 * @param Criteria $criteria
	 * @param int $limit
	 * @return \traversable
	 * @throws GenericStorageError
	 */
	public function listByCriteria(Criteria $criteria, array $orderDef = [], int $limit = 0) : \traversable;

}
