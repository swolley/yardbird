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
	 * @param	mixed		$sth						statement
	 * @param	int			$fetchMode				(optional) fetch mode. default ASSOCIATIVE ARRAY
	 * @param	int|string	$fetchModeParam			(optional) fetch mode param if fetch mode is class or column
	 * @param	array		$fetchPropsLateParams	(optional) constructor params if fetch mode has FETCH_PROPS_LATE option
	 * @param	array								fetched and parsed data
	 */
	static function fetch($sth, int $fetchMode = self::FETCH_ASSOC, $fetchModeParam = 0, array $fetchPropsLateParams = []): array;

	/**
	 * bind passed parameters for sql injection
	 * @param	array	$params	parameters to be binded
	 * @param	mixed	$sth		(optional) statement. Mongo has no statement
	 * @return	bool			params binded correctly
	 */
	static function bindParams(array &$params, &$sth = null): bool;
}
