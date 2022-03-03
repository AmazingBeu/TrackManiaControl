<?php

namespace ManiaControl\Manialinks;

use ManiaControl\ManiaControl;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Players\Player;
use ManiaControl\Players\PlayerManager;

use FML\ManiaLink;

/**
 * Class managing the Custom UI in ManiaPlanet
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2020 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class ToggleInterfaceManager implements CallbackListener {

	/*
	 * Constants
	 */
	const MLID                    = 'ToggleInterface.KeyListener';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	private $manialink = "";

	/**
	 * Create a custom UI manager instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		$this->buildManiaLink();

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::AFTERINIT, $this, 'handleAfterInit');
		$this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerJoined');
		//$this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'handleSettingChanged'); // TODO Setting Change Key
	}


	/**
	 * Handle ManiaControl AfterInit callback
	 *
	 * @internal
	 */
	public function handleAfterInit() {		
		$players = $this->maniaControl->getPlayerManager()->getPlayers();
		if (!empty($players)) {
			$this->maniaControl->getManialinkManager()->sendManialink($this->manialink , $players, 0, false, false);
		}
	}

	/**
	 * Handle PlayerJoined Callback
	 *
	 * @param Player $player
	 */
	public function handlePlayerJoined(Player $player) {
		$this->maniaControl->getManialinkManager()->sendManialink($this->manialink , $player, 0, false, false);
	}

	/**
	 * Build the Manialink with only the Toggle Interface script feature
	 */
	private function buildManiaLink() {
		$manialink = new ManiaLink(self::MLID);
		$manialink->getScript()->addFeature(new \FML\Script\Features\ToggleInterface("F9"));
		$this->manialink = (string) $manialink;
	}
}
