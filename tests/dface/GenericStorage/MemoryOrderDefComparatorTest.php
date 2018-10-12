<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\criteria\ArrayGraphNavigator;
use dface\criteria\SimpleComparator;
use dface\GenericStorage\Memory\MemoryOrderDefComparator;
use PHPUnit\Framework\TestCase;

class MemoryOrderDefComparatorTest extends TestCase {

	/** @var SimpleComparator */
	private $comparator;
	/** @var ArrayGraphNavigator */
	private $navigator;

	public function setUp(){
		$this->comparator = new SimpleComparator();
		$this->navigator = new ArrayGraphNavigator();
	}

	private function init(array $orderDef) : MemoryOrderDefComparator
	{
		return new MemoryOrderDefComparator($orderDef, $this->navigator, $this->comparator);
	}

	public function testAll() : void
	{
		$e1 = (new TestEntity(TestId::generate(16), 'Name1', 'Email', null, 1))->jsonSerialize();
		$e2 = (new TestEntity(TestId::generate(16), 'Name2', 'Email', null, 1))->jsonSerialize();

		$c = $this->init([['name', true]]);
		$this->assertEquals(-1, $c->compare($e1, $e2));

		$c = $this->init([['name', false]]);
		$this->assertEquals(1, $c->compare($e1, $e2));

		$c = $this->init([['email', true]]);
		$this->assertEquals(0, $c->compare($e1, $e2));

		$c = $this->init([['email', false]]);
		$this->assertEquals(0, $c->compare($e1, $e2));

		$c = $this->init([['email', true], ['name', true]]);
		$this->assertEquals(-1, $c->compare($e1, $e2));

		$c = $this->init([['email', true], ['name', false]]);
		$this->assertEquals(1, $c->compare($e1, $e2));

		$c = $this->init([['name', true], ['email', true]]);
		$this->assertEquals(-1, $c->compare($e1, $e2));

		$c = $this->init([['name', false], ['email', false]]);
		$this->assertEquals(1, $c->compare($e1, $e2));

		$c = $this->init([]);
		$this->assertEquals(0, $c->compare($e1, $e2));
	}

}
