<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Drivers\Oci;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Interfaces\IRelationalConnectable;

/**
 * @covers Swolley\YardBird\Drivers\Oci
 */
final class OciTest extends TestCase
{
	///////////////////////////////// UNIT ////////////////////////////////////////////////
	/**
     * @codeCoverageIgnore
	 * @group unit
     */
	public function test_Oci_should_implements_IRelationalConnectable(): void
	{
		$reflection = new \ReflectionClass(Oci::class);
		$this->assertTrue($reflection->implementsInterface(IRelationalConnectable::class));
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Oci::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_exception_if_missing_or_empty_parameters_passed(): void
    {
		$this->expectException(BadMethodCallException::class);
		new Oci([ 'host' => '', 'user' => null, 'password' => null ]);
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Oci::validateConnectionParams
	 * @group unit
	 */
	public function test_validateConnectionParams_should_return_array_if_correct_params(): array
    {
		$reflection = new \ReflectionClass(Oci::class);
		$method = $reflection->getMethod('validateConnectionParams');
		$method->setAccessible(true);
		$result = $method->invokeArgs($reflection, [[ 'driver' => 'oci', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'sid' => 'sid', 'dbName' => 'name' ]]);
		$expected = [ 'driver' => 'oci', 'host' => '127.0.0.1', 'user' => 'username', 'password' => 'userpassword', 'sid' => 'sid', 'port' => 1521, 'charset' => 'UTF8', 'dbName' => 'name' ];
		$this->assertEquals($result, $expected);

		return $result;
	}

	/**
	 * @covers Swolley\YardBird\Drivers\Oci::__construct
	 * @depends test_validateConnectionParams_should_return_array_if_correct_params
	 * @group unit
	 */
	public function test_constructor_should_throw_exception_if_cant_establish_connection($params): void
	{
		$this->expectException(ConnectionException::class);
		new Oci($params);
	}

	///////////////////////////////// INTEGRATION ////////////////////////////////////////////////

	/*public function test_sql_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('sql')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->sql('SELECT * FROM table WHERE id=:id', [ 'unusedname' => 'value' ]);
	}

	public function test_sql_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('sql')
			->will($this->throwException(new QueryException));

		$dbMock->sql('SELECT * FROM table WHERE id=:id', [ 'id' => 'value' ]);
	}

	public function test_select_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('select')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->select('table', ['field1', 'field2'], ['unusedname' => function(){} ]);
	}

	public function test_select_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('select')
			->will($this->throwException(new QueryException));

		$dbMock->select('table', ['field1'], ['field1' => 'value' ]);
	}

	public function test_insert_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('insert')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->insert('table', ['name' => function() {} ]);
	}

	public function test_insert_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('insert')
			->will($this->throwException(new QueryException));

		$dbMock->insert('table', ['name' => 'value' ]);
	}

	public function test_update_should_throw_exception_if_where_param_not_valid(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('update')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->update('table', ['name' => 'table' ], [ 'invalidarray' ]);
	}

	public function test_update_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('update')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->update('table', ['name' => function() {} ]);
	}

	public function test_update_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('update')
			->will($this->throwException(new QueryException));

		$dbMock->update('table', ['name' => 'value' ]);
	}

	public function test_delete_should_throw_exception_if_where_param_not_valid(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('delete')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->delete('table', ['name' => 'table' ], [ 'invalidarray' ]);
	}

	public function test_delete_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('delete')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->delete('table', ['name' => function() {} ]);
	}

	public function test_delete_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('delete')
			->will($this->throwException(new QueryException));

		$dbMock->delete('table', ['name' => 'value' ]);
	}

	public function test_procedure_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('procedure')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->procedure('table', ['name' => function() {} ]);
	}

	public function test_procedure_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(Oci::class);
		$dbMock->method('procedure')
			->will($this->throwException(new QueryException));

		$dbMock->procedure('table', ['name' => 'value' ]);
	}*/	
}