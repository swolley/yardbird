<?php
namespace  Swolley\YardBird\Utils;
use Swolley\YardBird\Interfaces\IConnectable;

class Mapper
{
	public function _invoke(IConnectable &$conn)
	{
		$tables = $conn->showColumns($conn->showTables());
		
	}
}