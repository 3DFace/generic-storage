<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\ArrayPathNavigator;
use PHPUnit\Framework\TestCase;

class ArrayPathNavigatorTest extends TestCase
{

	private $x;

	protected function setUp()
	{
		parent::setUp();
		$this->x = [
			'p1' => 'p1',
			'p2' => [
				'p1' => 'p2/p1',
			],
			'p3' => [
				'p1' => [
					'p1' => 'p3/p1/p1',
				]
			]
		];
	}

	public function testGet() : void
	{
		$a = $this->x;

		$this->assertEquals('p1', ArrayPathNavigator::getPropertyValue($a, ['p1']));
		$this->assertEquals('p2/p1', ArrayPathNavigator::getPropertyValue($a, ['p2', 'p1']));
		$this->assertEquals('p3/p1/p1', ArrayPathNavigator::getPropertyValue($a, ['p3', 'p1', 'p1']));
		$this->assertNull(ArrayPathNavigator::getPropertyValue($a, ['p3', 'p2', 'p1']));
	}

	public function testSet() : void
	{

		ArrayPathNavigator::setPropertyValue($this->x, ['p1'], 'x');
		$this->assertEquals('x', $this->x['p1']);

		ArrayPathNavigator::setPropertyValue($this->x, ['p2', 'p1'], 'x');
		$this->assertEquals('x', $this->x['p2']['p1']);

		ArrayPathNavigator::setPropertyValue($this->x, ['p3', 'p1', 'p1'], 'x');
		$this->assertEquals('x', $this->x['p3']['p1']['p1']);

		ArrayPathNavigator::setPropertyValue($this->x, ['p3', 'p2', 'p1'], 'x');
		$this->assertEquals('x', $this->x['p3']['p2']['p1']);

	}

	public function testUnset() : void
	{

		ArrayPathNavigator::unsetProperty($this->x, ['p1']);
		$this->assertArrayNotHasKey('p1', $this->x);

		ArrayPathNavigator::unsetProperty($this->x, ['p2', 'p1']);
		$this->assertArrayNotHasKey('p1', $this->x['p2']);

		ArrayPathNavigator::unsetProperty($this->x, ['p3', 'p1', 'p1']);
		$this->assertArrayNotHasKey('p1', $this->x['p3']['p1']);

		ArrayPathNavigator::unsetProperty($this->x, ['p3', 'p3', 'p1']);
		$this->assertArrayNotHasKey('p3', $this->x['p3']);

	}

}
