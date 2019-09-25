<?php
namespace  Swolley\Database\Utils;
use Swolley\Database\Interfaces\IConnectable;

class Mapper
{
	public function _invoke(IConnectable &$conn)
	{
		$tables = $conn->showColumns($conn->showTables());
		
	}
}