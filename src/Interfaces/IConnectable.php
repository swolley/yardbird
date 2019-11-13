<?php
namespace Swolley\YardBird\Interfaces;

interface IConnectable extends ICrudable
{
	/**
	 * validates connection parameters before trying to open connection
	 * @param	array	$params	connection parameters
	 * @return  array	parsed and validated parameters
	 * @throws	\BadMethodCallException	if missing parameters
	 * @throws	\UnexpectedValueException if no requested driver available
	 */
	static function validateConnectionParams(array $params): array;

	/**
	 * bind passed parameters for sql injection
	 * @param	array	$params	parameters to be binded
	 * @param	mixed	$stmt	(optional) statement. Mongo has no statement
	 * @return	bool	params binded correctly
	 */
	static function bindParams(array &$params, &$stmt = null): bool;

	/**
	 * lists all db tables
	 * @return	array	tables' name list
	 */
	function showTables(): array;

	/**
	 * gets tables columns' name and type
	 * @param	string|array	$tables	table name or array of names
	 * @return	array	table columns' name and type or list of tables columns' name and type
	 */
	function showColumns($tables);
}
