<?php
/* author: Ponomarev Denis <ponomarev@gmail.com> */

namespace dface\GenericStorage;

use dface\GenericStorage\Mysql\MyStorageBuilder;

class MyGenericStorageDbEncodedTest extends GenericStorageTest {

	protected function setUp() {
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
			->setBatchListSize(1)
			->setIdBatchSize(10)
			->setTemporary(true)
			->setDbCharset('cp1251')
			->build();
		$this->storage->reset();
	}

}
