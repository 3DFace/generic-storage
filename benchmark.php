<?php

use dface\GenericStorage\Mysql\MyStorage;
use dface\GenericStorage\Mysql\MyStorageBuilder;
use dface\GenericStorage\MysqliSameLinkProvider;
use dface\GenericStorage\TestData;
use dface\GenericStorage\TestEntity;
use dface\GenericStorage\TestId;
use dface\sql\placeholders\DefaultFormatter;
use dface\sql\placeholders\DefaultParser;

include_once __DIR__.'/bootstrap.php';

$link = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_BASE);
$link->set_charset(DB_CHARSET);
$parser = new DefaultParser();
$formatter = new DefaultFormatter();

$provider = new MysqliSameLinkProvider($link);

$storage = (new MyStorageBuilder(TestEntity::class, $provider, 'test_gen_storage'))
	->setIdPropertyName('id')
	->setIdExtracted(true)
	->setRevisionPropertyName('revision')
	->setSeqIdPropertyName('seq_id')
	->addColumns([
		'email' => ['type' => 'VARCHAR(128)', 'mode' => MyStorage::COLUMN_MODE_SEPARATED],
		'data/a' => ['type' => 'VARCHAR(128)', 'mode' => MyStorage::COLUMN_MODE_SEPARATED],
	])
	->addIndexes([
		'INDEX email(email)',
	])
	->setBatchListSize(1000)
	->build();

function fillData(MyStorage $storage, mysqli $link)
{

	$storage->reset();

	$limit = 10000;

	/** @var TestEntity[] $data */
	$data = [];
	for ($i = 0; $i < $limit; $i++) {
		$id = TestId::generate(16);
		$e = new TestEntity($id, 'name-'.$i, 'name-'.$i.'@benchmark', new TestData('asd', $i), 1);
		$data[] = $e;
	}

	$started = microtime(true);

	$link->autocommit(false);

	foreach ($data as $e) {
		$id = $e->getId();
		$storage->saveItem($id, $e);
		/** @var TestEntity $x */
		$x = $storage->getItem($id);
		if ($e->getEmail() !== $x->getEmail()) {
			throw new LogicException("Email mismatch");
		}
	}

	$link->commit();
	echo(microtime(true) - $started);
}

fillData($storage, $link);

$storage->updateColumns(1000);


