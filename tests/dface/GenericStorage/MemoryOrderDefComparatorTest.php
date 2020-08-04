<?php

namespace dface\GenericStorage;

use dface\criteria\builder\ArrayGraphNavigator;
use dface\criteria\builder\SimpleComparator;
use dface\GenericStorage\Memory\MemoryOrderDefComparator;
use PHPUnit\Framework\TestCase;

class MemoryOrderDefComparatorTest extends TestCase
{

	private SimpleComparator $comparator;
	private ArrayGraphNavigator $navigator;

	public function setUp() : void
	{
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
		self::assertEquals(-1, $c->compare($e1, $e2));

		$c = $this->init([['name', false]]);
		self::assertEquals(1, $c->compare($e1, $e2));

		$c = $this->init([['email', true]]);
		self::assertEquals(0, $c->compare($e1, $e2));

		$c = $this->init([['email', false]]);
		self::assertEquals(0, $c->compare($e1, $e2));

		$c = $this->init([['email', true], ['name', true]]);
		self::assertEquals(-1, $c->compare($e1, $e2));

		$c = $this->init([['email', true], ['name', false]]);
		self::assertEquals(1, $c->compare($e1, $e2));

		$c = $this->init([['name', true], ['email', true]]);
		self::assertEquals(-1, $c->compare($e1, $e2));

		$c = $this->init([['name', false], ['email', false]]);
		self::assertEquals(1, $c->compare($e1, $e2));

		$c = $this->init([]);
		self::assertEquals(0, $c->compare($e1, $e2));
	}

}
