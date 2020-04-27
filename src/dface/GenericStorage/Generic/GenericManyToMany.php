<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Generic;

interface GenericManyToMany
{

	/**
	 * @param $left
	 * @return iterable
	 *
	 * @throws GenericStorageError
	 */
	public function getAllByLeft($left) : iterable;

	/**
	 * @param $right
	 * @return iterable
	 *
	 * @throws GenericStorageError
	 */
	public function getAllByRight($right) : iterable;

	/**
	 * @param $left
	 * @param $right
	 * @return bool
	 * @throws GenericStorageError
	 */
	public function has($left, $right) : bool;

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

	/**
	 * @throws UnderlyingStorageError
	 */
	public function clear() : void;

}
