<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Swolley\YardBird\Drivers\MySqliExtended;
use Swolley\YardBird\Exceptions\QueryException;
use Swolley\YardBird\Exceptions\ConnectionException;
use Swolley\YardBird\Exceptions\BadMethodCallException;
use Swolley\YardBird\Exceptions\UnexpectedValueException;
use Swolley\YardBird\Interfaces\IRelationalConnectable;

final class MySqliExtendedTest extends TestCase
{

	///////////////////////////////// UNIT ////////////////////////////////////////////////
	public function test_MySqliExtended_should_implements_IRelationalConnectable(): void
	{
		$reflection = new \ReflectionClass(MySqliExtended::class);
		$this->assertTrue($reflection->implementsInterface(IRelationalConnectable::class));
	}

	public function test_validateConnectionParams_should_return_exception_if_no_valid_parameters_passed(): void
    {
		$this->expectException(BadMethodCallException::class);
		new MySqliExtended([
			'host' => '',
			'user' => null,
			'password' => null
		]);
	}

	public function test_validateConnectionParams_should_return_exception_if_missing_parameters(): void
    {
		$this->expectException(BadMethodCallException::class);
		new MySqliExtended([
			'driver' => 'mysql'
		]);
	}

	public function test_constructor_should_throw_exception_if_cant_establish_connection(): void
	{
		$this->expectException(ConnectionException::class);
		$params = ['host' => 'localhost', 'port' => 3306, 'dbName' => 'invalid', 'charset' => 'UTF8', 'user' => 'invalid', 'password' => 'invalid'];
		new MySqliExtended($params);
	}

	/*public function test_sql_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('sql')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->sql('SELECT * FROM table WHERE id=:id', [ 'unusedname' => 'value' ]);
	}

	public function test_sql_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('sql')
			->will($this->throwException(new QueryException));

		$dbMock->sql('SELECT * FROM table WHERE id=:id', [ 'id' => 'value' ]);
	}

	public function test_select_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('select')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->select('table', ['field1', 'field2'], ['unusedname' => function(){} ]);
	}

	public function test_select_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('select')
			->will($this->throwException(new QueryException));

		$dbMock->select('table', ['field1'], ['field1' => 'value' ]);
	}

	public function test_insert_should_throw_exception_if_driver_not_supported(): void
	{
		$this->expectException(\Exception::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('insert')
			->will($this->throwException(new \Exception));

		$dbMock->insert('table', ['field1' => 'field2']);
	}

	public function test_insert_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('insert')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->insert('table', ['name' => function() {} ]);
	}

	public function test_insert_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('insert')
			->will($this->throwException(new QueryException));

		$dbMock->insert('table', ['name' => 'value' ]);
	}

	public function test_update_should_throw_exception_if_where_param_not_valid(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('update')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->update('table', ['name' => 'table' ], [ 'invalidarray' ]);
	}

	public function test_update_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('update')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->update('table', ['name' => function() {} ]);
	}

	public function test_update_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('update')
			->will($this->throwException(new QueryException));

		$dbMock->update('table', ['name' => 'value' ]);
	}

	public function test_delete_should_throw_exception_if_where_param_not_valid(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('delete')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->delete('table', ['name' => 'table' ], [ 'invalidarray' ]);
	}

	public function test_delete_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('delete')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->delete('table', ['name' => function() {} ]);
	}

	public function test_delete_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('delete')
			->will($this->throwException(new QueryException));

		$dbMock->delete('table', ['name' => 'value' ]);
	}

	public function test_procedure_should_throw_exception_if_parameters_not_binded(): void
	{
		$this->expectException(UnexpectedValueException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('procedure')
			->will($this->throwException(new UnexpectedValueException));

		$dbMock->procedure('table', ['name' => function() {} ]);
	}

	public function test_procedure_should_throw_exception_if_cant_execute_query(): void
	{
		$this->expectException(QueryException::class);
		$dbMock = $this->createMock(MySqliExtended::class);
		$dbMock->method('procedure')
			->will($this->throwException(new QueryException));

		$dbMock->procedure('table', ['name' => 'value' ]);
	}*/
	///////////////////////////////// INTEGRATION ////////////////////////////////////////////////
	
}