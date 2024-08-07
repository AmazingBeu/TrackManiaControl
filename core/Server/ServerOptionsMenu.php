<?php

namespace ManiaControl\Server;

use FML\Components\CheckBox;
use FML\Controls\Entry;
use FML\Controls\Frame;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\Script\Features\Paging;
use FML\Script\Script;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Communication\CommunicationAnswer;
use ManiaControl\Communication\CommunicationListener;
use ManiaControl\Communication\CommunicationMethods;
use ManiaControl\Configurator\ConfiguratorMenu;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Players\Player;
use Maniaplanet\DedicatedServer\Structures\ServerOptions;
use Maniaplanet\DedicatedServer\Xmlrpc\ServerOptionsException;

/**
 * Class offering a Configurator Menu for Server Options
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2020 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class ServerOptionsMenu implements CallbackListener, ConfiguratorMenu, TimerListener, CommunicationListener {
	/*
	 * Constants
	 */
	const CB_SERVER_OPTION_CHANGED                 = 'ServerOptionsMenu.OptionChanged';
	const CB_SERVER_OPTIONS_CHANGED                = 'ServerOptionsMenu.OptionsChanged';
	const SETTING_PERMISSION_CHANGE_SERVER_OPTIONS = 'Change Server Options';
	const SETTING_LOAD_DEFAULT_SETTINGS_STARTUP    = 'Load Stored Server Options on Startup';
	const TABLE_SERVER_OPTIONS                     = 'mc_server_options';
	const ACTION_PREFIX_OPTION                     = 'ServerOptionsMenu.';

	/** @deprecated */
	const CB_SERVERSETTING_CHANGED = self::CB_SERVER_OPTION_CHANGED;
	/** @deprecated */
	const CB_SERVERSETTINGS_CHANGED = self::CB_SERVER_OPTIONS_CHANGED;

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	/**
	 * Construct a new server options menu instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->initTables();

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::ONINIT, $this, 'onInit');
		$this->maniaControl->getTimerManager()->registerTimerListening($this, 'saveCurrentServerOptions', 6 * 3600 * 1000);

		// Permissions
		$this->maniaControl->getAuthenticationManager()->definePermissionLevel(self::SETTING_PERMISSION_CHANGE_SERVER_OPTIONS, AuthenticationManager::AUTH_LEVEL_SUPERADMIN);

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_LOAD_DEFAULT_SETTINGS_STARTUP, false);

		//TODO remove to somewhere cleaner
		//Communication Listenings
		$this->initalizeCommunicationListenings();
	}

	/**
	 * Initialize necessary database tables
	 *
	 * @return bool
	 */
	private function initTables() {
		$mysqli    = $this->maniaControl->getDatabase()->getMysqli();
		$query     = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_SERVER_OPTIONS . "` (
				`index` int(11) NOT NULL AUTO_INCREMENT,
				`serverIndex` int(11) NOT NULL,
				`optionName` varchar(100) NOT NULL,
				`optionValue` varchar(500) NOT NULL,
				`changed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`index`),
				UNIQUE KEY `option` (`serverIndex`, `optionName`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Server Options' AUTO_INCREMENT=1;";
		$statement = $mysqli->prepare($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
			return false;
		}
		$statement->execute();
		if ($statement->error) {
			trigger_error($statement->error, E_USER_ERROR);
			return false;
		}
		$statement->close();
		return true;
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::getTitle()
	 */
	public static function getTitle() {
		return 'Server Options';
	}

	/**
	 * Save the current server options in case they have been changed by an external tool
	 *
	 * @return bool
	 */
	public function saveCurrentServerOptions() {
		$serverOptions = $this->maniaControl->getClient()->getServerOptions();
		return $this->saveServerOptions($serverOptions);
	}

	/**
	 * Save the given server options in the database
	 *
	 * @param ServerOptions $serverOptions
	 * @param bool          $triggerCallbacks
	 * @return bool
	 */
	private function saveServerOptions(ServerOptions $serverOptions, $triggerCallbacks = false) {
		$mysqli    = $this->maniaControl->getDatabase()->getMysqli();
		$query     = "INSERT INTO `" . self::TABLE_SERVER_OPTIONS . "` (
				`serverIndex`,
				`optionName`,
				`optionValue`
				) VALUES (
				?, ?, ?
				) ON DUPLICATE KEY UPDATE
				`optionValue` = VALUES(`optionValue`);";
		$statement = $mysqli->prepare($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}

		$optionName  = null;
		$optionValue = null;
		$statement->bind_param('iss', $this->maniaControl->getServer()->index, $optionName, $optionValue);

		$serverOptionsArray = $serverOptions->toArray();
		foreach ($serverOptionsArray as $optionName => $optionValue) {
			if ($optionValue === null) {
				continue;
			}

			$statement->execute();
			if ($statement->error) {
				trigger_error($statement->error);
				$statement->close();
				return false;
			}

			if ($triggerCallbacks) {
				$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_SERVER_OPTION_CHANGED, array(self::CB_SERVER_OPTION_CHANGED, $optionName, $optionValue));
			}
		}

		$statement->close();
		return true;
	}

	/**
	 * Handle OnInit callback
	 */
	public function onInit() {
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_LOAD_DEFAULT_SETTINGS_STARTUP)) {
			$this->loadOptionsFromDatabase();
		}
	}

	/**
	 * Load options from database
	 *
	 * @return bool
	 */
	public function loadOptionsFromDatabase() {
		$mysqli      = $this->maniaControl->getDatabase()->getMysqli();
		$serverIndex = $this->maniaControl->getServer()->index;
		$query       = "SELECT * FROM `" . self::TABLE_SERVER_OPTIONS . "`
				WHERE `serverIndex` = {$serverIndex};";
		$result      = $mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}

		$oldServerOptions = $this->maniaControl->getClient()->getServerOptions();
		$newServerOptions = new ServerOptions();

		while ($row = $result->fetch_object()) {
			$optionName = lcfirst($row->optionName);
			if (!property_exists($oldServerOptions, $optionName)) {
				continue;
			}
			$newServerOptions->$optionName = $row->optionValue;
			settype($newServerOptions->$optionName, gettype($oldServerOptions->$optionName));
		}
		$result->free();

		$this->fillUpMandatoryOptions($newServerOptions, $oldServerOptions);

		$loaded = false;
		try {
			$loaded = $this->maniaControl->getClient()->setServerOptions($newServerOptions);
		} catch (ServerOptionsException $e) {
			$this->maniaControl->getChat()->sendExceptionToAdmins($e);
		}

		if ($loaded) {
			Logger::logInfo('Server Options successfully loaded!');
		} else {
			Logger::logError('Error loading Server Options!');
		}

		return $loaded;
	}

	/**
	 * Fill up the new server options object with the necessary options based on the old options object
	 *
	 * @param ServerOptions $newServerOptions
	 * @param ServerOptions $oldServerOptions
	 * @return ServerOptions
	 */
	private function fillUpMandatoryOptions(ServerOptions &$newServerOptions, ServerOptions $oldServerOptions) {
		$mandatoryOptions = array('name', 'comment', 'password', 'passwordForSpectator', 'nextCallVoteTimeOut', 'callVoteRatio');
		foreach ($mandatoryOptions as $optionName) {
			if (!isset($newServerOptions->$optionName) && isset($oldServerOptions->$optionName)) {
				$newServerOptions->$optionName = $oldServerOptions->$optionName;
			}
		}
		return $newServerOptions;
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::getMenu()
	 */
	public function getMenu($width, $height, Script $script, Player $player) {
		$paging = new Paging();
		$script->addFeature($paging);
		$frame = new Frame();

		$serverOptions      = $this->maniaControl->getClient()->getServerOptions();
		$serverOptionsArray = $serverOptions->toArray();

		// Config
		$pagerSize     = 9.;
		$optionHeight  = 5.;
		$labelTextSize = 2;

		$tipNextValues = new Label_Text();
		$frame->addChild($tipNextValues);
		$tipNextValues->setPosition(- $width * 0.45, $height * -0.44, 1);
		$tipNextValues->setTextSize(0.5);
		$tipNextValues->setTextFont("GameFontSemiBold");
		$tipNextValues->setHorizontalAlign($tipNextValues::LEFT);
		$tipNextValues->setAutoNewLine(true);
		$tipNextValues->setSize(45,10);
		$tipNextValues->setText("All options starting by \"Next\" are applied after a map change");

		// Pagers
		$pagerPrev = new Quad_Icons64x64_1();
		$frame->addChild($pagerPrev);
		$pagerPrev->setPosition($width * 0.5 - 12, $height * -0.5 + 5, 2);
		$pagerPrev->setSize($pagerSize, $pagerSize);
		$pagerPrev->setSubStyle($pagerPrev::SUBSTYLE_ArrowPrev);

		$pagerNext = new Quad_Icons64x64_1();
		$frame->addChild($pagerNext);
		$pagerNext->setPosition($width * 0.5 - 5, $height * -0.5 + 5, 2);
		$pagerNext->setSize($pagerSize, $pagerSize);
		$pagerNext->setSubStyle($pagerNext::SUBSTYLE_ArrowNext);

		$pageCountLabel = new Label_Text();
		$frame->addChild($pageCountLabel);
		$pageCountLabel->setHorizontalAlign($pageCountLabel::RIGHT);
		$pageCountLabel->setPosition($width * 0.5 - 16, $height * -0.5 + 5, 1);
		$pageCountLabel->setStyle($pageCountLabel::STYLE_TextTitle1);
		$pageCountLabel->setTextSize(2);

		$paging->addButtonControl($pagerNext);
		$paging->addButtonControl($pagerPrev);
		$paging->setLabel($pageCountLabel);

		$repositionnedFrame = new Frame();
        $frame->addChild($repositionnedFrame);
        $repositionnedFrame->setPosition($width * -0.5, $height * 0.5);

        $pagesFrame = new Frame();
        $repositionnedFrame->addChild($pagesFrame);
        $pagesFrame->setY(-8.);

		// Pages
		$posY      = 0.;
		$index     = 0;
		$pageFrame = null;
		$pageMaxCount = floor(($height - 16) / $optionHeight);

		foreach ($serverOptionsArray as $name => $value) {
			// Continue on CurrentMaxPlayers...
			if (strpos($name, 'Current') !== false) continue; // TODO: display 'Current...' somewhere

			if ($index % $pageMaxCount === 0) {
				$pageFrame = new Frame();
				$pagesFrame->addChild($pageFrame);
				$paging->addPageControl($pageFrame);
				$posY = 0.;
			}

			$optionsFrame = new Frame();
			$pageFrame->addChild($optionsFrame);
			$optionsFrame->setY($posY);

			$nameLabel = new Label_Text();
			$optionsFrame->addChild($nameLabel);
			$nameLabel->setHorizontalAlign($nameLabel::LEFT);
			$nameLabel->setX(2);
			$nameLabel->setSize($width * 0.6, $optionHeight);
			$nameLabel->setStyle($nameLabel::STYLE_TextCardSmall);
			$nameLabel->setTextSize($labelTextSize)->setText($name);
			$nameLabel->setTextColor('fff');

			if (is_bool($value)) {
				// Boolean checkbox
				$quad = new Quad();
				$quad->setPosition($width - $width * 0.3 / 2 - 2, 0, -0.01);
				$quad->setSize(4, 4);
				$checkBox = new CheckBox(self::ACTION_PREFIX_OPTION . $name, $value, $quad);
				$optionsFrame->addChild($checkBox);
			} else {
				// Other
				$entry = new Entry();
				$optionsFrame->addChild($entry);
				$entry->setStyle(Label_Text::STYLE_TextValueSmall);
				$entry->setX($width - $width *  0.3 / 2 - 2);
				$entry->setTextSize(1);
				$entry->setSize($width * 0.3, $optionHeight * 0.9);
				$entry->setName(self::ACTION_PREFIX_OPTION . $name);
				$entry->setDefault($value);

				if ($name === 'Comment') {
					$entry->setSize($width * 0.3, $optionHeight * 3. + $optionHeight * 0.9);
					$entry->setAutoNewLine(true);
					$entry->setVerticalAlign($entry::TOP);
					$entry->setY($optionHeight * 1.5 + 2.5);
					$optionsFrame->setY($posY - $optionHeight * 1.5);
					$posY -= $optionHeight * 3.;
					$index += 3;
				} else if (strpos($name, 'Password') !== false) {
					$entry->setTextFormat("Password");
					$entry->setId($name);

					$quad = new Quad();
					$quad->setPosition($width - $width * 0.3 - 4, 0, -0.01);
					$quad->setSize(4, 4);
					$checkBox = new CheckBox(null, false, $quad);
					$checkBox->setEnabledDesign("UICommon64_1", "Eye_light");
					$checkBox->setDisabledDesign("UICommon64_1", "Eye_light");
					$checkBox->setCustomScript("
					declare CMlEntry Entry <=> (Quad_CheckBox.Parent.Parent.GetFirstChild(\"$name\") as CMlEntry);
					if (Entry != Null) {
						if (Quad_CheckBox.StyleSelected) {
							Entry.TextFormat = CMlEntry::ETextFormat::Basic;
						} else {
							Entry.TextFormat = CMlEntry::ETextFormat::Password;
						}
					}");
					$optionsFrame->addChild($checkBox);
				}
			}

			$posY -= $optionHeight;
			$index++;
		}

		return $frame;
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::saveConfigData()
	 */
	public function saveConfigData(array $configData, Player $player) {
		if (!$this->maniaControl->getAuthenticationManager()->checkPermission($player, self::SETTING_PERMISSION_CHANGE_SERVER_OPTIONS)) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}
		if (!$configData[3] || strpos($configData[3][0]['Name'], self::ACTION_PREFIX_OPTION) !== 0) {
			return;
		}

		$prefixLength = strlen(self::ACTION_PREFIX_OPTION);

		$oldServerOptions = $this->maniaControl->getClient()->getServerOptions();
		$newServerOptions = new ServerOptions();

		foreach ($configData[3] as $option) {
			$optionName                    = lcfirst(substr($option['Name'], $prefixLength));
			$newServerOptions->$optionName = $option['Value'];
			settype($newServerOptions->$optionName, gettype($oldServerOptions->$optionName));
		}

		$this->fillUpMandatoryOptions($newServerOptions, $oldServerOptions);

		$success = $this->applyNewServerOptions($newServerOptions, $player);
		if ($success) {
			$this->maniaControl->getChat()->sendSuccess('Server Options saved!', $player);
		} else {
			$this->maniaControl->getChat()->sendError('Server Options saving failed!', $player);
		}

		// Reopen the Menu
		$this->maniaControl->getConfigurator()->showMenu($player, $this);
	}

	/**
	 * Apply the array of new Server Options
	 *
	 * @param ServerOptions $newServerOptions
	 * @param Player        $player
	 * @return bool
	 */
	private function applyNewServerOptions(ServerOptions $newServerOptions, $player = null) {
		try {
			$this->maniaControl->getClient()->setServerOptions($newServerOptions);
		} catch (ServerOptionsException $e) {
			$this->maniaControl->getChat()->sendException($e, $player);
			return false;
		}

		$this->saveServerOptions($newServerOptions, true);

		$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_SERVER_OPTIONS_CHANGED, array(self::CB_SERVER_OPTIONS_CHANGED));

		return true;
	}


	/**
	 * Initializes the communication Listenings
	 */
	private function initalizeCommunicationListenings() {
		//Communication Listenings
		$this->maniaControl->getCommunicationManager()->registerCommunicationListener(CommunicationMethods::GET_SERVER_OPTIONS, $this, function ($data) {
			return new CommunicationAnswer($this->maniaControl->getClient()->getServerOptions());
		});

		$this->maniaControl->getCommunicationManager()->registerCommunicationListener(CommunicationMethods::SET_SERVER_OPTIONS, $this, function ($data) {
			if (!is_object($data) || !property_exists($data, "serverOptions")) {
				return new CommunicationAnswer("No valid ServerOptions provided!", true);
			}

			$oldServerOptions = $this->maniaControl->getClient()->getServerOptions();
			$newServerOptions = new ServerOptions();

			foreach ($data->serverOptions as $name => $value) {
				$optionName                    = $name;
				$newServerOptions->$optionName = $value;
				settype($newServerOptions->$optionName, gettype($oldServerOptions->$optionName));
			}

			$this->fillUpMandatoryOptions($newServerOptions, $oldServerOptions);


			try {
				$success = $this->applyNewServerOptions($newServerOptions);
			} catch (ServerOptionsException $exception) {
				return new CommunicationAnswer($exception->getMessage(), true);
			}

			//Trigger Server Options Changed Callback
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_SERVER_OPTIONS_CHANGED, array(self::CB_SERVER_OPTIONS_CHANGED));

			return new CommunicationAnswer(array("success" => $success));
		});
	}
}
