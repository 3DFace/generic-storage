<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorageBuilder;
use dface\Mysql\MysqlException;
use dface\sql\placeholders\FormatterException;
use dface\sql\placeholders\ParserException;

class MyGenericStorageBatched10Test extends GenericStorageTest {

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
			->addColumns([
				'email' => 'VARCHAR(128)',
				'data/a' => 'VARCHAR(128)',
			])
			->addIndexes([
				'INDEX email(email)',
			])
			->setBatchListSize(10)
			->setIdBatchSize(10)
			->setTemporary(true)
			->build();
		$this->storage->reset();
	}

}
