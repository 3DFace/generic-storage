<?php

namespace dface\GenericStorage\Memory;

use dface\GenericStorage\Generic\GenericManyToMany;

class MemoryManyToMany implements GenericManyToMany
{

	/** @var string[][] */
	private array $left = [];
	/** @var string[][] */
	private array $right = [];

	public function getAllByLeft($left) : iterable
	{
		foreach ($this->left[(string)$left] ?? [] as $right) {
			yield $right;
		}
	}

	public function getAllByRight($right) : iterable
	{
		foreach ($this->right[(string)$right] ?? [] as $left) {
			yield $left;
		}
	}

	public function has($left, $right) : bool
	{
		return isset($this->left[(string)$left][(string)$right]);
	}

	public function add($left, $right) : void
	{
		$this->left[(string)$left][(string)$right] = $right;
		$this->right[(string)$right][(string)$left] = $left;
	}

	public function remove($left, $right) : void
	{
		unset(
			$this->left[(string)$left][(string)$right],
			$this->right[(string)$right][(string)$left]
		);
	}

	public function clearLeft($left) : void
	{
		unset($this->left[(string)$left]);
	}

	public function clearRight($right) : void
	{
		unset($this->right[(string)$right]);
	}

	public function clear() : void
	{
		$this->left = [];
		$this->right = [];
	}

}
