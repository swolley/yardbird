<?php
namespace Swolley\Database\Interfaces;

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
}
