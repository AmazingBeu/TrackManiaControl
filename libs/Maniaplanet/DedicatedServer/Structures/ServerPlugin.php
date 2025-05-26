<?php
/**
 * ManiaPlanet dedicated server Xml-RPC client
 *
 * @license     http://www.gnu.org/licenses/lgpl.html LGPL License 3
 */

namespace Maniaplanet\DedicatedServer\Structures;

class ServerPlugin extends AbstractStructure
{
	/** @var string */
	public $name;
	/** @var string[] */
	public $settingsValues;
	/** @var ScriptSettings[] */
	public $settingsDesc = array();

	/**
	 * @return ScriptInfo
	 */
	public static function fromArray($array)
	{
		$object = parent::fromArray($array);
		$object->settingsDesc = ScriptSettings::fromArrayOfArray($object->paramDescs);
		return $object;
	}
}
