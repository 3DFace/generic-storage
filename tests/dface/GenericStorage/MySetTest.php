<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Mysql\MySet;
use dface\Mysql\MysqlException;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;

class MySetTest extends GenericSetTest {

	/** @var MysqliConnection */
	private $dbi;

	/**
	 * @throws MysqlException
	 * @throws FormatterException
	 * @throws ParserException
	 */
	protected function setUp() : void {
		$this->dbi = DbiFactory::getConnection();
		$this->set = new MySet(
			$this->dbi,
			'test_set',
			TestId::class,
			true);
		$this->set->reset();
	}

	/**
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	private function broke(){
		/** @noinspection SqlResolve */
		$this->dbi->query('DROP TABLE test_set');
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testIterateTroubles(){
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->set->iterate());
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testContainsTroubles(){
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->set->contains(new TestId());
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testAddTroubles(){
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->set->add(new TestId());
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testRemoveTroubles(){
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->set->remove(new TestId());
	}

}
