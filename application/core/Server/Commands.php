<?php

namespace ManiaControl\Server;

use FML\Controls\Quads\Quad_BgRaceScore2;
use FML\Controls\Quads\Quad_Icons128x32_1;
use FML\Controls\Quads\Quad_Icons64x64_1;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use Maniaplanet\DedicatedServer\Xmlrpc\GameModeException;

/**
 * Class offering various Commands related to the Dedicated Server
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class Commands implements CallbackListener, CommandListener, ManialinkPageAnswerListener, TimerListener {
	/*
	 * Constants
	 */
	const ACTION_SET_PAUSE                         = 'ServerCommands.SetPause';
	const ACTION_EXTEND_WARMUP                     = 'ServerCommands.ExtendWarmup';
	const ACTION_END_WARMUP                        = 'ServerCommands.EndWarmup';
	const ACTION_CANCEL_VOTE                       = 'ServerCommands.CancelVote';
	const CB_VOTE_CANCELLED                        = 'ServerCommands.VoteCancelled';
	const SETTING_PERMISSION_CANCEL_VOTE           = 'Cancel Vote';
	const SETTING_PERMISSION_SET_PAUSE             = 'Set Pause';
	const SETTING_PERMISSION_HANDLE_WARMUP         = 'Handle Warmup';
	const SETTING_PERMISSION_SHOW_SYSTEMINFO       = 'Show SystemInfo';
	const SETTING_PERMISSION_SHUTDOWN_SERVER       = 'Shutdown Server';
	const SETTING_PERMISSION_CHANGE_SERVERSETTINGS = 'Change ServerSettings';
	const COMMAND_EXTEND_WARMUP                    = 'WarmUp_Extend';
	const COMMAND_FORCE_WARMUP                     = 'Command_ForceWarmUp';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;
	private $serverShutdownTime = -1;
	private $serverShutdownEmpty = false;

	/**
	 * Create a new server commands instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Register for callbacks
		$this->maniaControl->timerManager->registerTimerListening($this, 'each5Seconds', 5000);
		$this->maniaControl->callbackManager->registerCallbackListener(Callbacks::ONINIT, $this, 'handleOnInit');
		$this->maniaControl->callbackManager->registerCallbackListener(Callbacks::WARMUPSTATUS, $this, 'handleWarmUpStatus');

		// Register for commands
		$this->maniaControl->commandManager->registerCommandListener('setservername', $this, 'commandSetServerName', true, 'Sets the ServerName.');
		$this->maniaControl->commandManager->registerCommandListener('setpwd', $this, 'commandSetPwd', true, 'Sets play password.');
		$this->maniaControl->commandManager->registerCommandListener('setspecpwd', $this, 'commandSetSpecPwd', true, 'Sets spectator password.');
		$this->maniaControl->commandManager->registerCommandListener('setmaxplayers', $this, 'commandSetMaxPlayers', true, 'Sets the maximum number of players.');
		$this->maniaControl->commandManager->registerCommandListener('setmaxspectators', $this, 'commandSetMaxSpectators', true, 'Sets the maximum number of spectators.');
		$this->maniaControl->commandManager->registerCommandListener('shutdownserver', $this, 'commandShutdownServer', true, 'Shuts down the ManiaPlanet server.');
		$this->maniaControl->commandManager->registerCommandListener('systeminfo', $this, 'commandSystemInfo', true, 'Shows system information.');
		$this->maniaControl->commandManager->registerCommandListener('cancel', $this, 'commandCancelVote', true, 'Cancels the current vote.');

		// Register for page actions
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_SET_PAUSE, $this, 'setPause');
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_EXTEND_WARMUP, $this, 'commandExtendWarmup');
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_END_WARMUP, $this, 'commandEndWarmup');
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_CANCEL_VOTE, $this, 'commandCancelVote');
	}

	/**
	 * Handle ManiaControl OnInit Callback
	 */
	public function handleOnInit() {
		// Define Permissions
		$this->maniaControl->authenticationManager->definePermissionLevel(self::SETTING_PERMISSION_SHUTDOWN_SERVER, AuthenticationManager::AUTH_LEVEL_SUPERADMIN);
		$this->maniaControl->authenticationManager->definePermissionLevel(self::SETTING_PERMISSION_SHOW_SYSTEMINFO, AuthenticationManager::AUTH_LEVEL_SUPERADMIN);
		$this->maniaControl->authenticationManager->definePermissionLevel(self::SETTING_PERMISSION_CHANGE_SERVERSETTINGS, AuthenticationManager::AUTH_LEVEL_ADMIN);
		$this->maniaControl->authenticationManager->definePermissionLevel(self::SETTING_PERMISSION_SET_PAUSE, AuthenticationManager::AUTH_LEVEL_MODERATOR);
		$this->maniaControl->authenticationManager->definePermissionLevel(self::SETTING_PERMISSION_CANCEL_VOTE, AuthenticationManager::AUTH_LEVEL_MODERATOR);
		$this->maniaControl->authenticationManager->definePermissionLevel(self::SETTING_PERMISSION_HANDLE_WARMUP, AuthenticationManager::AUTH_LEVEL_MODERATOR);

		$this->updateCancelVoteMenuItem();
		$this->updateWarmUpMenuItems();
	}

	/**
	 * Add the cancel vote menu item
	 */
	private function updateCancelVoteMenuItem() {
		$itemQuad = new Quad_Icons64x64_1();
		$itemQuad->setSubStyle($itemQuad::SUBSTYLE_ArrowRed);
		$itemQuad->setAction(self::ACTION_CANCEL_VOTE);
		$this->maniaControl->actionsMenu->addMenuItem($itemQuad, false, 30, 'Cancel Vote');
	}

	/**
	 * Manage the WarmUp related menu items
	 */
	private function updateWarmUpMenuItems() {
		$pauseExists = false;
		try {
			$scriptInfos = $this->maniaControl->client->getModeScriptInfo();
			foreach ($scriptInfos->commandDescs as $param) {
				if ($param->name === self::COMMAND_FORCE_WARMUP) {
					$pauseExists = true;
					break;
				}
			}
			$this->maniaControl->client->triggerModeScriptEvent("WarmUp_GetStatus");
		} catch (GameModeException $e) {
		}

		// Add pause menu item
		if ($pauseExists) {
			$itemQuad = new Quad_Icons128x32_1();
			$itemQuad->setSubStyle($itemQuad::SUBSTYLE_ManiaLinkSwitch);
			$itemQuad->setAction(self::ACTION_SET_PAUSE);
			$this->maniaControl->actionsMenu->addAdminMenuItem($itemQuad, 13, 'Pause the current game');
		}

	}

	/**
	 * Handle the WarmupStatus Callback, and removes or adds the Menu Items for extending / Stopping warmup
	 *
	 * @param $warmupEnabled
	 */
	public function handleWarmUpStatus($warmupEnabled) {
		if ($warmupEnabled) {
			// Extend WarmUp menu item
			$itemQuad = new Quad_BgRaceScore2();
			$itemQuad->setSubStyle($itemQuad::SUBSTYLE_SendScore);
			$itemQuad->setAction(self::ACTION_EXTEND_WARMUP);
			$this->maniaControl->actionsMenu->addMenuItem($itemQuad, false, 14, 'Extend Warmup');

			// Stop WarmUp menu item
			$itemQuad = new Quad_Icons64x64_1();
			$itemQuad->setSubStyle($itemQuad::SUBSTYLE_ArrowGreen);
			$itemQuad->setAction(self::ACTION_END_WARMUP);
			$this->maniaControl->actionsMenu->addMenuItem($itemQuad, false, 15, 'End Warmup');
		} else {
			$this->maniaControl->actionsMenu->removeMenuItem(14, false);
			$this->maniaControl->actionsMenu->removeMenuItem(15, false);
		}
	}

	/**
	 * Handle //cancelvote command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function commandCancelVote(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_CANCEL_VOTE)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}

		if ($this->maniaControl->client->cancelVote()) {
			$this->maniaControl->chat->sendInformation($player->getEscapedNickname() . ' cancelled the Vote!');
		} else {
			$this->maniaControl->chat->sendInformation("There's no vote running currently!", $player);
		}

		$this->maniaControl->callbackManager->triggerCallback(self::CB_VOTE_CANCELLED, $player);
	}


	/**
	 * Extend the WarmUp
	 *
	 * @param array  $callback
	 * @param Player $player
	 */
	public function commandExtendWarmup(array $callback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_HANDLE_WARMUP)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}

		try {
			$this->maniaControl->client->triggerModeScriptEvent('WarmUp_Extend', '10');
			$this->maniaControl->chat->sendInformation('$<' . $player->nickname . '$> extended the WarmUp by 10 seconds!');
		} catch (GameModeException $e) {
		}
	}

	/**
	 * End the WarmUp
	 *
	 * @param array  $callback
	 * @param Player $player
	 */
	public function commandEndWarmup(array $callback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_HANDLE_WARMUP)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}

		try {
			$this->maniaControl->client->triggerModeScriptEvent('WarmUp_Stop', '');
			$this->maniaControl->chat->sendInformation('$<' . $player->nickname . '$> stopped the WarmUp!');
		} catch (GameModeException $e) {
		}
	}

	/**
	 * Pause the current game
	 *
	 * @param array  $callback
	 * @param Player $player
	 */
	public function setPause(array $callback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_SET_PAUSE)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		try {
			$this->maniaControl->client->sendModeScriptCommands(array('Command_ForceWarmUp' => true));
			$this->maniaControl->chat->sendInformation('$<' . $player->nickname . '$> paused the Game!');
		} catch (GameModeException $e) {
		}
	}

	/**
	 * Check Stuff each 5 Seconds
	 */
	public function each5Seconds() {
		// TODO: move empty & delayed shutdown code into server class
		// Empty shutdown
		if ($this->serverShutdownEmpty) {
			if ($this->maniaControl->playerManager->getPlayerCount(false) <= 0) {
				$this->shutdownServer('empty');
			}
		}

		// Delayed shutdown
		if ($this->serverShutdownTime > 0) {
			if (time() >= $this->serverShutdownTime) {
				$this->shutdownServer('delayed');
			}
		}
	}

	/**
	 * Perform server shutdown
	 *
	 * @param string $login
	 */
	private function shutdownServer($login = '-') {
		$this->maniaControl->log("Server shutdown requested by '{$login}'!");
		$this->maniaControl->client->stopServer();
	}

	/**
	 * Handle //systeminfo command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function commandSystemInfo(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_SHOW_SYSTEMINFO)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$systemInfo = $this->maniaControl->client->getSystemInfo();
		$message    = 'SystemInfo: ip=' . $systemInfo->publishedIp . ', port=' . $systemInfo->port . ', p2pPort=' . $systemInfo->p2PPort . ', title=' . $systemInfo->titleId . ', login=' . $systemInfo->serverLogin . '.';
		$this->maniaControl->chat->sendInformation($message, $player->login);
	}

	/**
	 * Handle //shutdownserver command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function commandShutdownServer(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_SHUTDOWN_SERVER)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		// Check for delayed shutdown
		$params = explode(' ', $chat[1][2]);
		if (count($params) >= 2) {
			$param = $params[1];
			if (strtolower($param) === 'empty') {
				$this->serverShutdownEmpty = !$this->serverShutdownEmpty;
				if ($this->serverShutdownEmpty) {
					$this->maniaControl->chat->sendInformation("The server will shutdown as soon as it's empty!", $player->login);
					return;
				}
				$this->maniaControl->chat->sendInformation("Empty-shutdown cancelled!", $player->login);
				return;
			}
			$delay = (int)$param;
			if ($delay <= 0) {
				// Cancel shutdown
				$this->serverShutdownTime = -1;
				$this->maniaControl->chat->sendInformation("Delayed shutdown cancelled!", $player->login);
				return;
			}
			// Trigger delayed shutdown
			$this->serverShutdownTime = time() + $delay * 60.;
			$this->maniaControl->chat->sendInformation("The server will shut down in {$delay} minutes!", $player->login);
			return;
		}
		$this->shutdownServer($player->login);
	}

	/**
	 * Handle //setservername command
	 *
	 * @param array  $chat
	 * @param Player $player
	 */
	public function commandSetServerName(array $chat, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_CHANGE_SERVERSETTINGS)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$params = explode(' ', $chat[1][2], 2);
		if (count($params) < 2) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setservername ManiaPlanet Server', $player->login);
			return;
		}
		$serverName = $params[1];
		$this->maniaControl->client->setServerName($serverName);
		$this->maniaControl->chat->sendSuccess("Server name changed to: '{$serverName}'!", $player->login);
	}

	/**
	 * Handle //setpwd command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function commandSetPwd(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_CHANGE_SERVERSETTINGS)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$messageParts   = explode(' ', $chatCallback[1][2], 2);
		$password       = '';
		$successMessage = 'Password removed!';
		if (isset($messageParts[1])) {
			$password       = $messageParts[1];
			$successMessage = "Password changed to: '{$password}'!";
		}
		$this->maniaControl->client->setServerPassword($password);
		$this->maniaControl->chat->sendSuccess($successMessage, $player->login);
	}

	/**
	 * Handle //setspecpwd command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function commandSetSpecPwd(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_CHANGE_SERVERSETTINGS)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$messageParts   = explode(' ', $chatCallback[1][2], 2);
		$password       = '';
		$successMessage = 'Spectator password removed!';
		if (isset($messageParts[1])) {
			$password       = $messageParts[1];
			$successMessage = "Spectator password changed to: '{$password}'!";
		}
		$this->maniaControl->client->setServerPasswordForSpectator($password);
		$this->maniaControl->chat->sendSuccess($successMessage, $player->login);
	}

	/**
	 * Handle //setmaxplayers command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function commandSetMaxPlayers(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_CHANGE_SERVERSETTINGS)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$messageParts = explode(' ', $chatCallback[1][2], 2);
		if (!isset($messageParts[1])) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setmaxplayers 16', $player->login);
			return;
		}
		$amount = $messageParts[1];
		if (!is_numeric($amount)) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setmaxplayers 16', $player->login);
			return;
		}
		$amount = (int)$amount;
		if ($amount < 0) {
			$amount = 0;
		}

		$this->maniaControl->client->setMaxPlayers($amount);
		$this->maniaControl->chat->sendSuccess("Changed max players to: {$amount}", $player->login);
	}

	/**
	 * Handle //setmaxspectators command
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function commandSetMaxSpectators(array $chatCallback, Player $player) {
		if (!$this->maniaControl->authenticationManager->checkPermission($player, self::SETTING_PERMISSION_CHANGE_SERVERSETTINGS)) {
			$this->maniaControl->authenticationManager->sendNotAllowed($player);
			return;
		}
		$messageParts = explode(' ', $chatCallback[1][2], 2);
		if (!isset($messageParts[1])) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setmaxspectators 16', $player->login);
			return;
		}
		$amount = $messageParts[1];
		if (!is_numeric($amount)) {
			$this->maniaControl->chat->sendUsageInfo('Usage example: //setmaxspectators 16', $player->login);
			return;
		}
		$amount = (int)$amount;
		if ($amount < 0) {
			$amount = 0;
		}

		$this->maniaControl->client->setMaxSpectators($amount);
		$this->maniaControl->chat->sendSuccess("Changed max spectators to: {$amount}", $player->login);
	}
}
