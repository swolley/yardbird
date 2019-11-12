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
	 * fetch and parse query results
	 * @param	mixed		$sth					statement
	 * @param	int			$fetchMode				(optional) fetch mode. default ASSOCIATIVE ARRAY
	 * @param	int|string	$fetchModeParam			(optional) fetch mode param if fetch mode is class or column
	 * @param	array		$fetchPropsLateParams	(optional) constructor params if fetch mode has FETCH_PROPS_LATE option
	 * @return	array		fetched and parsed data
	 */
	//static function fetch($sth, int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

	//static function count($sth): int;

	/**
	 * bind passed parameters for sql injection
	 * @param	array	$params	parameters to be binded
	 * @param	mixed	$sth	(optional) statement. Mongo has no statement
	 * @return	bool	params binded correctly
	 */
	static function bindParams(array &$params, &$sth = null): bool;

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
