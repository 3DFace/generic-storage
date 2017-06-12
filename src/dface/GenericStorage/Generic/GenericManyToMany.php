<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Generic;

interface GenericManyToMany {

	/**
	 * @param $left
	 * @return array|\traversable
	 *
	 * @throws GenericStorageError
	 */
	public function getAllByLeft($left) : \traversable;

	/**
	 * @param $right
	 * @return array|\traversable
	 *
	 * @throws GenericStorageError
	 */
	public function getAllByRight($right) : \traversable;

	/**
	 * @param $left
	 * @param $right
	 *
	 * @throws GenericStorageError
	 */
	public function add($left, $right) : void;

	/**
	 * @param $left
	 * @param $right
	 *
	 * @throws GenericStorageError
	 */
	public function remove($left, $right) : void;

	/**
	 * @param $left
	 *
	 * @throws GenericStorageError
	 */
	public function clearLeft($left) : void;

	/**
	 * @param $right
	 *
	 * @throws GenericStorageError
	 */
	public function clearRight($right) : void;

}
