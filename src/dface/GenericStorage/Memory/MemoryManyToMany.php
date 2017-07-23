<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Memory;

use dface\GenericStorage\Generic\GenericManyToMany;

class MemoryManyToMany implements GenericManyToMany {

	/** @var string[][] */
	private $left = [];
	/** @var string[][] */
	private $right = [];

	public function getAllByLeft($left) : \traversable {
		foreach($this->left[(string)$left] ?? [] as $right){
			yield $right;
		}
	}

	public function getAllByRight($right) : \traversable {
		foreach($this->right[(string)$right] ?? [] as $left){
			yield $left;
		}
	}

	public function add($left, $right) : void {
		$this->left[(string)$left][(string)$right] = $right;
		$this->right[(string)$right][(string)$left] = $left;
	}

	public function remove($left, $right) : void {
		unset(
			$this->left[(string)$left][(string)$right],
			$this->right[(string)$right][(string)$left]
		);
	}

	public function clearLeft($left) : void {
		unset($this->left[(string)$left]);
	}

	public function clearRight($right) : void {
		unset($this->right[(string)$right]);
	}

}
