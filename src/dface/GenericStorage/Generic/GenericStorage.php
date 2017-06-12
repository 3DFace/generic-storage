<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Generic;

use dface\criteria\Criteria;

interface GenericStorage {

	/**
	 * @param $id
	 * @return \JsonSerializable|null
	 *
	 * @throws GenericStorageError
	 */
	public function getItem($id) : ?\JsonSerializable;

	/**
	 * @param array|\traversable $ids
	 * @return \JsonSerializable[]|\traversable
	 *
	 * @throws GenericStorageError
	 */
	public function getItems($ids) : \traversable;

	/**
	 * @param $id
	 * @param \JsonSerializable $item
	 *
	 * @throws GenericStorageError
	 */
	public function saveItem($id, \JsonSerializable $item) : void;

	/**
	 * @param $id
	 *
	 * @throws GenericStorageError
	 */
	public function removeItem($id) : void;

	/**
	 * @return \traversable
	 *
	 * @throws GenericStorageError
	 */
	public function listAll() : \traversable;

	/**
	 * @param Criteria $criteria
	 * @return \traversable
	 *
	 * @throws GenericStorageError
	 */
	public function listByCriteria(Criteria $criteria) : \traversable;

}
