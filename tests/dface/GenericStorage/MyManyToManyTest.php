<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Generic\UnderlyingStorageError;
use dface\GenericStorage\Mysql\MyManyToMany;
use dface\Mysql\MysqlException;
use dface\Mysql\MysqliConnection;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;

class MyManyToManyTest extends GenericManyToManyTest {

	/** @var MysqliConnection */
	private $dbi;

	/**
	 * @throws MysqlException
	 * @throws FormatterException
	 * @throws ParserException
	 */
	protected function setUp() : void {
		$link = DbiFactory::getConnection();
		$this->dbi = new MysqliConnection($link, new DefaultParser(), new DefaultFormatter());
		$this->assoc = new MyManyToMany(
			$link,
			'test_many_to_many',
			TestId::class,
			TestId::class,
			'left', 'right', true);
		$this->assoc->reset();
	}

	/**
	 * @throws FormatterException
	 * @throws MySqlException
	 * @throws ParserException
	 */
	private function broke(){
		/** @noinspection SqlResolve */
		$this->dbi->query('DROP TABLE test_many_to_many');
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testGetAllByLeftTroubles(){
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->assoc->getAllByLeft(new TestId()));
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testGetAllByRightTroubles(){
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		iterator_to_array($this->assoc->getAllByRight(new TestId()));
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
		$this->assoc->add(new TestId(), new TestId());
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
		$this->assoc->remove(new TestId(), new TestId());
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testClearRightTroubles(){
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->clearRight(new TestId());
	}

	/**
	 * @throws FormatterException
	 * @throws Generic\GenericStorageError
	 * @throws MysqlException
	 * @throws ParserException
	 */
	public function testClearLeftTroubles(){
		$this->broke();
		$this->expectException(UnderlyingStorageError::class);
		$this->expectExceptionCode(0);
		$this->assoc->clearLeft(new TestId());
	}

}
