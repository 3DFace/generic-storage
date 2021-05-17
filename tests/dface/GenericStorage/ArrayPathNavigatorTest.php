<?php

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\ArrayPathNavigator;
use PHPUnit\Framework\TestCase;

class ArrayPathNavigatorTest extends TestCase
{

	private array $x;

	protected function setUp() : void
	{
		parent::setUp();
		$p5 = new \stdClass();
		$p5->p1 = 'p5/p1';
		$this->x = [
			'p1' => 'p1',
			'p2' => [
				'p1' => 'p2/p1',
			],
			'p3' => [
				'p1' => [
					'p1' => 'p3/p1/p1',
				]
			],
			'p4' => null,
			'p5' => $p5,
		];
	}

	public function testGet() : void
	{
		$a = $this->x;

		self::assertEquals('p1', ArrayPathNavigator::getPropertyValue($a, ['p1']));
		self::assertEquals('p2/p1', ArrayPathNavigator::getPropertyValue($a, ['p2', 'p1']));
		self::assertEquals('p3/p1/p1', ArrayPathNavigator::getPropertyValue($a, ['p3', 'p1', 'p1']));
		self::assertEquals('p5/p1', ArrayPathNavigator::getPropertyValue($a, ['p5', 'p1']));
		self::assertNull(ArrayPathNavigator::getPropertyValue($a, ['p3', 'p2', 'p1']));
		self::assertNull(ArrayPathNavigator::getPropertyValue($a, ['p4'], 'x'));
		self::assertNull(ArrayPathNavigator::getPropertyValue($a, ['p4', 'p1']));
		self::assertNull(ArrayPathNavigator::getPropertyValue($a, ['p5', 'p2']));
	}

	public function testSet() : void
	{
		ArrayPathNavigator::setPropertyValue($this->x, ['p1'], 'x');
		self::assertEquals('x', $this->x['p1']);

		ArrayPathNavigator::setPropertyValue($this->x, ['p2', 'p1'], 'x');
		self::assertEquals('x', $this->x['p2']['p1']);

		ArrayPathNavigator::setPropertyValue($this->x, ['p3', 'p1', 'p1'], 'x');
		self::assertEquals('x', $this->x['p3']['p1']['p1']);

		ArrayPathNavigator::setPropertyValue($this->x, ['p3', 'p2', 'p1'], 'x');
		self::assertEquals('x', $this->x['p3']['p2']['p1']);

		ArrayPathNavigator::setPropertyValue($this->x, ['p4', 'p1'], 'x');
		self::assertEquals('x', $this->x['p4']['p1']);
	}

	public function testFallback() : void
	{
		ArrayPathNavigator::fallbackPropertyValue($this->x, ['p1'], 'x');
		self::assertEquals('p1', $this->x['p1']);

		ArrayPathNavigator::fallbackPropertyValue($this->x, ['p2', 'p1'], 'x');
		self::assertEquals('p2/p1', $this->x['p2']['p1']);

		ArrayPathNavigator::fallbackPropertyValue($this->x, ['p3', 'p2', 'p1'], 'x');
		self::assertEquals('x', $this->x['p3']['p2']['p1']);

		ArrayPathNavigator::fallbackPropertyValue($this->x, ['p4', 'p1'], 'x');
		self::assertNull($this->x['p4']);
	}

	public function testExtract() : void
	{
		$x = ArrayPathNavigator::extractProperty($this->x, ['p1']);
		self::assertArrayNotHasKey('p1', $this->x);
		self::assertEquals('p1', $x);

		$x = ArrayPathNavigator::extractProperty($this->x, ['p2', 'p1']);
		self::assertArrayNotHasKey('p1', $this->x['p2']);
		self::assertEquals('p2/p1', $x);

		$x = ArrayPathNavigator::extractProperty($this->x, ['p3', 'p1', 'p1']);
		self::assertArrayNotHasKey('p1', $this->x['p3']['p1']);
		self::assertEquals('p3/p1/p1', $x);

		$x = ArrayPathNavigator::extractProperty($this->x, ['p3', 'p3', 'p1']);
		self::assertArrayNotHasKey('p3', $this->x['p3']);
		self::assertNull($x);

		$x = ArrayPathNavigator::extractProperty($this->x, ['p4', 'p1']);
		self::assertNull($this->x['p4']);
		self::assertNull($x);

		$x = ArrayPathNavigator::extractProperty($this->x, ['p5', 'p1']);
		self::assertObjectNotHasAttribute('p1', $this->x['p5']);
		self::assertEquals('p5/p1', $x);

		$x = ArrayPathNavigator::extractProperty($this->x, ['p5', 'p2']);
		self::assertObjectNotHasAttribute('p2', $this->x['p5']);
		self::assertNull($x);

		$x = ArrayPathNavigator::extractProperty($this->x, ['p6']);
		self::assertArrayNotHasKey('p6', $this->x);
		self::assertNull($x);
	}

}
