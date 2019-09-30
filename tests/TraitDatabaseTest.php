<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Interfaces\TraitDatabase;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Exceptions\ConnectionException;

/**
 * @covers Swolley\YardBird\Interfaces\TraitDatabase
 */
final class TraitDatabaseTest extends TestCase
{
	use TraitDatabase;
	///////////////////////////////// UNIT ////////////////////////////////////////////////
	/**
	 * @covers Swolley\YardBird\Interfaces\TraitDatabase::getInfo
	 * @uses Swolley\YardBird\Interfaces\TraitDatabase::setInfo
	 * @uses Swolley\YardBird\Interfaces\TraitDatabase::getType
	 * @uses Swolley\YardBird\Interfaces\TraitDatabase::getHost
	 * @uses Swolley\YardBird\Interfaces\TraitDatabase::getDbName
	 * @uses Swolley\YardBird\Utils\Utils
	 */
	public function test_getInfo(): void
	{
		$this->setInfo(['driver' => 'pgsql', 'host' => '127.0.0.1', 'dbName' => 'name'], true);
		$this->assertEquals($this->getInfo(), ['driver' => 'postgres', 'host' => '127.0.0.1', 'dbName' => 'name']);

		$this->setInfo(['driver' => 'mssql', 'host' => '127.0.0.1', 'dbName' => 'name'], true);
		$this->assertEquals($this->getInfo(), ['driver' => 'sqlserver', 'host' => '127.0.0.1', 'dbName' => 'name']);
	}
}
