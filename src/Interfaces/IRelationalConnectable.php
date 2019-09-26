<?php
namespace Swolley\YardBird\Interfaces;

interface IRelationalConnectable extends IConnectable
{
	/**
	 * bind out params by reference with custom parameters depending by driver
	 * @param	mixed	$params			parameters to be binded
	 * @param	mixed	$st				statement. Mongo has no statement
	 * @param 	mixed	$outResult		reference to variable that will contain out values
	 * @param	int		$maxLength		max $outResultRef length
	 */
	static function bindOutParams(&$params, &$st, &$outResult, int $maxLength = 40000): void;

	/**
	 * lists all db tables
	 * @return	array	tables' name list
	 */
	function showTables(): array;

	/**
	 * gets tables columns' name and type
	 * @param	string|array	$tables	table name or array of names
	 * @return	array		table columns' name and type or list of tables columns' name and type
	 */
	function showColumns($tables): array;
}
