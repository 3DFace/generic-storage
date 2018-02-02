<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage\Memory;

use dface\criteria\Comparator;
use dface\criteria\ObjectGraphNavigator;

class MemoryOrderDefComparator {

	/** @var array[] */
	private $orderDef;
	/** @var ObjectGraphNavigator */
	private $navigator;
	/** @var Comparator */
	private $valueComparator;

	public function __construct(
		array $orderDef,
		ObjectGraphNavigator $navigator,
		Comparator $valueComparator
	) {
		$this->orderDef = $orderDef;
		$this->navigator = $navigator;
		$this->valueComparator = $valueComparator;
	}

	public function compare(array $arr1, array $arr2) : int {
		foreach($this->orderDef as [$property, $asc]){
			$v1 = $this->navigator->getValue($arr1, $property);
			$v2 = $this->navigator->getValue($arr2, $property);
			$x = $this->valueComparator->compare($v1, $v2);
			if($x !== 0){
				return $asc ? $x : -$x;
			}
		}
		return 0;
	}

}
