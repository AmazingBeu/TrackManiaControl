<?php

namespace TheM;

use FML\Controls\Frame;
use FML\Controls\Labels\Label_Button;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Utils\Formatter;

/**
 * TeamSpeak Info plugin
 * Based on the TeamSpeak Info plugin created by undef.de:
 * http://forum.maniaplanet.com/viewtopic.php?f=450&t=24805
 *
 * @author TheM
 */
class TeamSpeakPlugin implements CallbackListener, CommandListener, ManialinkPageAnswerListener, TimerListener, Plugin {
	/*
	 * Constants
	 */
	const ID      = 23;
	const VERSION = 0.11;
	const NAME    = 'TeamSpeak Plugin';
	const AUTHOR  = 'TheM';

	const TEAMSPEAK_SID        = 'TS Server ID';
	const TEAMSPEAK_SERVERHOST = 'TS Server host';
	const TEAMSPEAK_SERVERPORT = 'TS Server port';
	const TEAMSPEAK_QUERYHOST  = 'TS Server Query host';
	const TEAMSPEAK_QUERYPORT  = 'TS Server Query port';
	const TEAMSPEAK_QUERYUSER  = 'TS Server Query user';
	const TEAMSPEAK_QUERYPASS  = 'TS Server Query password';

	const ACTION_OPEN_TSVIEWER = 'TSViewer.OpenWidget';

	const TS_ICON       = 'Teamspeak.png';
	const TS_ICON_MOVER = 'Teamspeak_logo_press.png';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;
	private $serverData = array();
	private $refreshTime = 0;
	private $refreshInterval = 90;

	/**
	 * Prepares the Plugin (Init Settings)
	 *
	 * @param ManiaControl $maniaControl
	 * @return mixed
	 */
	public static function prepare(ManiaControl $maniaControl) {
		$maniaControl->settingManager->initSetting(get_class(), self::TEAMSPEAK_SID, 1);
		$maniaControl->settingManager->initSetting(get_class(), self::TEAMSPEAK_SERVERHOST, 'ts3.somehoster.com');
		$maniaControl->settingManager->initSetting(get_class(), self::TEAMSPEAK_SERVERPORT, 9987);
		$maniaControl->settingManager->initSetting(get_class(), self::TEAMSPEAK_QUERYHOST, '');
		$maniaControl->settingManager->initSetting(get_class(), self::TEAMSPEAK_QUERYPORT, 10011);
		$maniaControl->settingManager->initSetting(get_class(), self::TEAMSPEAK_QUERYUSER, '');
		$maniaControl->settingManager->initSetting(get_class(), self::TEAMSPEAK_QUERYPASS, '');
	}

	/**
	 * Get plugin id
	 *
	 * @return int
	 */
	public static function getId() {
		return self::ID;
	}

	/**
	 * Get Plugin Name
	 *
	 * @return string
	 */
	public static function getName() {
		return self::NAME;
	}

	/**
	 * Get Plugin Version
	 *
	 * @return float
	 */
	public static function getVersion() {
		return self::VERSION;
	}

	/**
	 * Get Plugin Author
	 *
	 * @return string
	 */
	public static function getAuthor() {
		return self::AUTHOR;
	}

	/**
	 * Get Plugin Description
	 *
	 * @return string
	 */
	public static function getDescription() {
		return 'Plugin offers a Connection with a TeamSpeak Server (via Widgets).';
	}

	/**
	 * Load the plugin
	 *
	 * @param \ManiaControl\ManiaControl $maniaControl
	 * @return bool
	 */
	public function load(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->checkConfig();

		$this->refreshTime = time();

		$this->maniaControl->manialinkManager->getIconManager()->addIcon(self::TS_ICON);
		$this->maniaControl->manialinkManager->getIconManager()->addIcon(self::TS_ICON_MOVER);

		$this->maniaControl->timerManager->registerTimerListening($this, 'ts3_queryServer', 1000);

		$this->addToMenu();
	}

	/**
	 * Function used to check certain configuration options to check if they can be used.
	 *
	 * @throws \Exception
	 */
	private function checkConfig() {
		if ($this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_SERVERHOST) == 'ts3.somehoster.com') {
			$error = 'Missing the required serverhost, please set it up before enabling the TeamSpeak plugin!';
			throw new \Exception($error);
		}

		$this->ts3_queryServer(); // Get latest information from the TeamSpeak server
		if (!isset($this->serverData['channels']) || count($this->serverData['channels']) == 0) {
			$error = 'Could not make proper connections with the server!';
			throw new \Exception($error);
		}
	}

	/**
	 * TeamSpeak related functions
	 * The functions are based upon tsstatus.php from http://tsstatus.sebastien.me/
	 * and were optimized by SilentStorm.
	 * Functions originally from the TeamSpeakInfo plugin made by undef.de for XAseco(2) and MPAseco.
	 */

	public function ts3_queryServer() {
		if (time() >= $this->refreshTime) {
			$this->refreshTime = (time() + $this->refreshInterval);

			$queryHost = $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_QUERYHOST);
			$host      = $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_SERVERHOST);

			$host      = ($queryHost != '') ? $queryHost : $host;
			$queryPort = $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_QUERYPORT);

			$socket = fsockopen(@$host, $queryPort, $errno, $errstr, 2);
			if ($socket) {
				socket_set_timeout($socket, 2);
				$is_ts3 = trim(fgets($socket)) == 'TS3';
				if (!$is_ts3) {
					trigger_error('[TeamSpeakPlugin] Server at "' . $host . '" is not a Teamspeak3-Server or you have setup a bad query-port!', E_USER_WARNING);
				}

				$queryuser = $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_QUERYUSER);
				$querypass = $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_QUERYPASS);
				if (($queryuser != '') && !is_numeric($queryuser) && $queryuser != false && ($querypass != '') && !is_numeric($querypass) && $querypass != false) {
					$ret = $this->ts3_sendCommand($socket, 'login client_login_name=' . $this->ts3_escape($queryuser) . ' client_login_password=' . $this->ts3_escape($querypass));
					if (stripos($ret, "error id=0") === false) {
						trigger_error("[TeamSpeakPlugin] Failed to authenticate with TS3 Server! Make sure you put the correct username & password in teamspeak.xml", E_USER_WARNING);
						return;
					}
				}

				$response = '';
				$response .= $this->ts3_sendCommand($socket, 'use sid=' . $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_SID));
				$this->ts3_sendCommand($socket, 'clientupdate client_nickname=' . $this->ts3_escape('ManiaControl Viewer'));
				$response .= $this->ts3_sendCommand($socket, 'serverinfo');
				$response .= $this->ts3_sendCommand($socket, 'channellist -topic -flags -voice -limits');
				$response .= $this->ts3_sendCommand($socket, 'clientlist -uid -away -voice -groups');

				fwrite($socket, "quit\n");
				fclose($socket);

				$lines = explode("error id=0 msg=ok\n\r", $response);
				if (count($lines) == 5) {
					$serverdata                   = $this->ts3_parseLine($lines[1]);
					$this->serverData['server']   = $serverdata[0];
					$this->serverData['channels'] = $this->ts3_parseLine($lines[2]);

					$users                     = $this->ts3_parseLine($lines[3]);
					$this->serverData['users'] = array(); // reset userslist
					foreach ($users as $user) {
						if ($user['client_nickname'] != 'ManiaControl Viewer') {
							$this->serverData['users'][] = $user;
						}
					}

					// Subtract reserved slots
					$this->serverData['server']['virtualserver_maxclients'] -= $this->serverData['server']['virtualserver_reserved_slots'];

					// Make ping value int
					$this->serverData['server']['virtualserver_total_ping'] = intval($this->serverData['server']['virtualserver_total_ping']);

					// Format the Date of server startup
					$this->serverData['server']['virtualserver_uptime'] = date('Y-m-d H:i:s', (time() - $this->serverData['server']['virtualserver_uptime']));

					// Always subtract all Query Clients
					$this->serverData['server']['virtualserver_clientsonline'] -= $this->serverData['server']['virtualserver_queryclientsonline'];
				}
			} else {
				trigger_error("[TeamSpeakPlugin] Failed to connect with TS3 server; socket error: " . $errstr . " [" . $errno . "]", E_USER_WARNING);
			}
		}
	}

	/**
	 * TS Function to send a command to the TeamSpeak server.
	 *
	 * @param resource $socket
	 * @param string   $cmd
	 * @return string
	 */
	private function ts3_sendCommand($socket, $cmd) {
		@fwrite($socket, "$cmd\n");

		$response = '';
		/*while(strpos($response, 'error id=') === false) {
			$response .= fread($socket, 8096);
		}*/

		/*while (!feof($socket)) {
			$response .= fread($socket, 8192);
		}*/

		$info = array('timed_out' => false);
		while (!feof($socket) && !$info['timed_out'] && strpos($response, 'error id=') === false) {
			$response .= fread($socket, 1024);
			$info = stream_get_meta_data($socket);
		}

		return $response;
	}

	/**
	 * TS Function used to escape characters in channelnames.
	 *
	 * @param string $str
	 * @return mixed
	 */
	private function ts3_escape($str) {
		return str_replace(array(chr(92), chr(47), chr(32), chr(124), chr(7), chr(8), chr(12), chr(10), chr(3), chr(9), chr(11)), array('\\\\', "\/", "\s", "\p", "\a", "\b", "\f", "\n", "\r", "\t", "\v"), $str);
	}

	/**
	 * TS Function used to parse lines in the serverresponse.
	 *
	 * @param string $rawLine
	 * @return array
	 */
	private function ts3_parseLine($rawLine) {

		$datas    = array();
		$rawItems = explode('|', $rawLine);

		foreach ($rawItems as &$rawItem) {
			$rawDatas  = explode(' ', $rawItem);
			$tempDatas = array();
			foreach ($rawDatas as &$rawData) {
				$ar                = explode("=", $rawData, 2);
				$tempDatas[$ar[0]] = isset($ar[1]) ? $this->ts3_unescape($ar[1]) : '';
			}
			$datas[] = $tempDatas;
		}
		unset($rawItem, $rawData);

		return $datas;
	}

	/**
	 * TS Function used to unescape characters in channelnames.
	 *
	 * @param string $str
	 * @return mixed
	 */
	private function ts3_unescape($str) {
		return str_replace(array('\\\\', "\/", "\s", "\p", "\a", "\b", "\f", "\n", "\r", "\t", "\v"), array(chr(92), chr(47), chr(32), chr(124), chr(7), chr(8), chr(12), chr(10), chr(3), chr(9), chr(11)), $str);
	}

	/**
	 * Function used insert the icon into the menu.
	 */
	private function addToMenu() {
		$this->maniaControl->manialinkManager->registerManialinkPageAnswerListener(self::ACTION_OPEN_TSVIEWER, $this, 'command_tsViewer');
		$itemQuad = new Quad();
		$itemQuad->setImage($this->maniaControl->manialinkManager->getIconManager()->getIcon(self::TS_ICON));
		$itemQuad->setImageFocus($this->maniaControl->manialinkManager->getIconManager()->getIcon(self::TS_ICON_MOVER));
		$itemQuad->setAction(self::ACTION_OPEN_TSVIEWER);
		$this->maniaControl->actionsMenu->addMenuItem($itemQuad, true, 1, 'Open TeamSpeak Viewer');
	}

	/**
	 * Unload the plugin and its resources
	 */
	public function unload() {
		$this->serverData = array();
		$this->maniaControl->actionsMenu->removeMenuItem(1, true);
	}

	/**
	 * Function handling the pressing of the icon.
	 *
	 * @param array  $chatCallback
	 * @param Player $player
	 */
	public function command_tsViewer(array $chatCallback, Player $player) {
		$this->showWidget($player);
	}

	/**
	 * Function showing the TeamSpeak widget to the player.
	 *
	 * @param mixed $player
	 */
	private function showWidget($player) {
		$width        = $this->maniaControl->manialinkManager->getStyleManager()->getListWidgetsWidth();
		$height       = $this->maniaControl->manialinkManager->getStyleManager()->getListWidgetsHeight();
		$quadStyle    = $this->maniaControl->manialinkManager->getStyleManager()->getDefaultMainWindowStyle();
		$quadSubstyle = $this->maniaControl->manialinkManager->getStyleManager()->getDefaultMainWindowSubStyle();

		$maniaLink = new ManiaLink(ManialinkManager::MAIN_MLID);

		// Main frame
		$frame = new Frame();
		$maniaLink->add($frame);
		$frame->setSize($width, $height);
		$frame->setPosition(0, 0, 10);

		// Background
		$backgroundQuad = new Quad();
		$frame->add($backgroundQuad);
		$backgroundQuad->setSize($width, $height);
		$backgroundQuad->setStyles($quadStyle, $quadSubstyle);

		// Close Quad (X)
		$closeQuad = new Quad_Icons64x64_1();
		$frame->add($closeQuad);
		$closeQuad->setPosition($width * 0.483, $height * 0.467, 3);
		$closeQuad->setSize(6, 6);
		$closeQuad->setSubStyle(Quad_Icons64x64_1::SUBSTYLE_QuitRace);
		$closeQuad->setAction(ManialinkManager::ACTION_CLOSEWIDGET);

		$serverName = new Label_Text();
		$frame->add($serverName);
		$serverName->setY($height / 2 - 4);
		$serverName->setX(-70);
		$serverName->setStyle($serverName::STYLE_TextCardMedium);
		$serverName->setHAlign('left');
		$serverName->setTextSize(1);
		$serverName->setText('$oServername:$o ' . $this->serverData['server']['virtualserver_name']);
		$serverName->setTextColor('fff');

		$serverVersion = new Label_Text();
		$frame->add($serverVersion);
		$serverVersion->setY($height / 2 - 4);
		$serverVersion->setX(2);
		$serverVersion->setStyle($serverVersion::STYLE_TextCardMedium);
		$serverVersion->setHAlign('left');
		$serverVersion->setTextSize(1);
		$serverVersion->setText('$oServerversion:$o ' . $this->serverData['server']['virtualserver_version']);
		$serverVersion->setTextColor('fff');

		$clients = new Label_Text();
		$frame->add($clients);
		$clients->setY($height / 2 - 7);
		$clients->setX(-70);
		$clients->setStyle($clients::STYLE_TextCardMedium);
		$clients->setHAlign('left');
		$clients->setTextSize(1);
		$clients->setText('$oConnected clients:$o ' . $this->serverData['server']['virtualserver_clientsonline'] . '/' . $this->serverData['server']['virtualserver_maxclients']);
		$clients->setTextColor('fff');

		$channels = new Label_Text();
		$frame->add($channels);
		$channels->setY($height / 2 - 7);
		$channels->setX(2);
		$channels->setStyle($channels::STYLE_TextCardMedium);
		$channels->setHAlign('left');
		$channels->setTextSize(1);
		$noChannels = 0;
		foreach ($this->serverData['channels'] as $channel) {
			if ($channel['channel_maxclients'] == 0 || strpos($channel['channel_name'], 'spacer') > 0) {
				continue;
			}
			$noChannels++;
		}
		$channels->setText('$oChannels:$o ' . $noChannels);
		$channels->setTextColor('fff');

		// Join button
		$joinButton = new Label_Button();
		$frame->add($joinButton);
		$joinButton->setWidth(150);
		$joinButton->setY($height / 2 - 11.5);
		$joinButton->setStyle($joinButton::STYLE_CardButtonSmallWide);
		$joinButton->setText('Join TeamSpeak: ' . $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_SERVERHOST) . ':' . $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_SERVERPORT));
		$joinButton->setTextColor('fff');
		$url = 'ts3server://' . $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_SERVERHOST) . '/?port=' . $this->maniaControl->settingManager->getSettingValue($this, self::TEAMSPEAK_SERVERPORT) . '&nickname=' . rawurlencode(Formatter::stripCodes($player->nickname));
		$joinButton->setUrl($url);

		$leftListQuad = new Quad();
		$frame->add($leftListQuad);
		$leftListQuad->setSize((($width / 2) - 5), ($height - 18));
		$leftListQuad->setX(-36);
		$leftListQuad->setY($height / 2 - 46);
		$leftListQuad->setStyles($quadStyle, $quadSubstyle);

		$channels = array();
		$users    = array();
		$userId   = 0;
		$index    = 0;
		$startX   = -69.5;
		foreach ($this->serverData['channels'] as $channel) {
			if ($channel['channel_maxclients'] == 0 || strpos($channel['channel_name'], 'spacer') > 0) {
				continue;
			}
			$channelLabel = new Label_Text();
			$frame->add($channelLabel);
			$y = 17.5 + ($index * 2.5);
			$channelLabel->setY($height / 2 - $y);
			$x = $startX;
			if ($channel['pid'] != 0) {
				$x = $startX + 5;
			}
			$channelLabel->setX($x);
			$channelLabel->setStyle($channelLabel::STYLE_TextCardMedium);
			$channelLabel->setHAlign('left');
			$channelLabel->setTextSize(1);
			$channelLabel->setScale(0.9);
			if ($channel['channel_flag_default'] == 1) {
				$channel['total_clients'] = ($channel['total_clients'] - 1);
			} // remove query client
			$channelLabel->setText('$o' . $channel['channel_name'] . '$o (' . $channel['total_clients'] . ')');
			$channelLabel->setTextColor('fff');

			$channels[$index] = $channelLabel;

			$index++;
			foreach ($this->serverData['users'] as $user) {
				if ($user['cid'] == $channel['cid']) {
					$userLabel = new Label_Text();
					$frame->add($userLabel);
					$y = 17.5 + ($index * 2.5);
					$userLabel->setY($height / 2 - $y);
					if ($channel['pid'] != 0) {
						$x = $startX + 7;
					} else {
						$x = $startX + 2;
					}
					$userLabel->setX($x);
					$userLabel->setStyle($userLabel::STYLE_TextCardMedium);
					$userLabel->setHAlign('left');
					$userLabel->setTextSize(1);
					$userLabel->setScale(0.9);
					$userLabel->setText($user['client_nickname']);
					$userLabel->setTextColor('fff');
					$users[$userId] = $userLabel;

					$userId++;
					$index++;

					if ($index > 22) {
						$index  = 0;
						$startX = 2.5;
					}
				}
			}

			if ($index > 22) {
				$index  = 0;
				$startX = 2.5;
			}
		}

		$rightlistQuad = new Quad();
		$frame->add($rightlistQuad);
		$rightlistQuad->setSize((($width / 2) - 5), ($height - 18));
		$rightlistQuad->setX(36);
		$rightlistQuad->setY($height / 2 - 46);
		$rightlistQuad->setStyles($quadStyle, $quadSubstyle);

		$this->maniaControl->manialinkManager->displayWidget($maniaLink, $player, 'TSViewer');
	}
}
