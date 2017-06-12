<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Generic;

interface GenericSet {

	/**
	 * @return array|\traversable
	 *
	 * @throws GenericStorageError
	 */
	public function iterate() : \traversable;

	/**
	 * @param $id
	 * @return bool
	 *
	 * @throws GenericStorageError
	 */
	public function contains($id) : bool;

	/**
	 * @param $id
	 * @return void
	 *
	 * @throws GenericStorageError
	 */
	public function add($id) : void;

	/**
	 * @param $id
	 * @return void
	 *
	 * @throws GenericStorageError
	 */
	public function remove($id) : void;

}
