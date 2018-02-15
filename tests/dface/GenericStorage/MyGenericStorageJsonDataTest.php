<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorageBuilder;
use dface\Mysql\MysqlException;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;

class MyGenericStorageJsonDataTest extends GenericStorageTest {

	/**
	 * @throws MysqlException
	 * @throws FormatterException
	 * @throws ParserException
	 */
	protected function setUp(){
		$dbi = DbiFactory::getConnection();
		$dbiFac = DbiFactory::getConnectionFactory();
		$this->storage = (new MyStorageBuilder(TestEntity::class, $dbi, 'test_gen_storage'))
			->setDedicatedConnectionFactory($dbiFac)
			->setIdPropertyName('id')
			->setRevisionPropertyName('revision')
			->addColumns([
//				'email' => 'VARCHAR(128)',
			])
			->addGeneratedColumns([
				'`email` VARCHAR(128) AS (JSON_UNQUOTE(JSON_EXTRACT(`$data`, \'$.email\'))) VIRTUAL',
				'`data/a` VARCHAR(128) AS (JSON_UNQUOTE(JSON_EXTRACT(`$data`, \'$.data.a\'))) VIRTUAL',
			])
			->addIndexes([
				'INDEX email(email)',
			])
			->setBatchListSize(10)
			->setIdBatchSize(10)
			->setTemporary(false)
			->setDataColumnDef('JSON')
			->setDataMaxSize(65535)
			->setCompressed(true)
			->build();
		$this->storage->reset();
	}

}
