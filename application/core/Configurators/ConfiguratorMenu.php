<?php

namespace ManiaControl\Configurators;

use FML\Script\Script;
use ManiaControl\Players\Player;

/**
 * Interface for Configurator Menus
 *
 * @author steeffeen & kremsy
 */
interface ConfiguratorMenu {

	/**
	 * Get the Menu Title
	 *
	 * @return string
	 */
	public function getTitle();

	/**
	 * Get the Configurator Menu Frame
	 *
	 * @param float  $width
	 * @param float  $height
	 * @param Script $script
	 * @return \FML\Controls\Frame
	 */
	public function getMenu($width, $height, Script $script);

	/**
	 * Save the Config Data
	 *
	 * @param array  $configData
	 * @param Player $player
	 */
	public function saveConfigData(array $configData, Player $player);
}
