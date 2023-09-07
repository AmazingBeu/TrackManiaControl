<?php

namespace ManiaControl\Settings;

use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\Callbacks;
use ManiaControl\General\UsageInformationAble;
use ManiaControl\General\UsageInformationTrait;
use ManiaControl\ManiaControl;
use ManiaControl\Plugins\PluginManager;
use ManiaControl\Utils\ClassUtil;

/**
 * Class managing ManiaControl Settings and Configurations
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2020 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class SettingManager implements CallbackListener, UsageInformationAble {
	use UsageInformationTrait;
	
	/*
	 * Constants
	 */
	const TABLE_SETTINGS                = 'mc_settings';
	const CB_SETTING_CHANGED            = 'SettingManager.SettingChanged';

	const SETTING_ALLOW_UNLINK_SERVER               = 'Allow to unlink settings with multiple servers';
	const SETTING_DELETE_UNUSED_SETTING_AT_START    = 'Delete unused settings at ManiaControl start';
	const SETTING_DISABLE_SETTING_CACHE             = 'Disable settings cache';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;
	/** @var Setting[] $storedSettings */
	private $storedSettings = array();
	/** @var bool $disableCache */
	private $disableCache = false;

	/**
	 * Construct a new setting manager instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;
		$this->initTables();

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(Callbacks::AFTERINIT, $this, 'handleAfterInit');

		$this->initSetting($this, self::SETTING_ALLOW_UNLINK_SERVER, false);
		$this->initSetting($this, self::SETTING_DELETE_UNUSED_SETTING_AT_START, true);
		$this->initSetting($this, self::SETTING_DISABLE_SETTING_CACHE, false, "only for not linked settings");
	}

	/**
	 * Initialize the necessary database tables
	 *
	 * @return bool
	 */
	private function initTables() {
		$mysqli            = $this->maniaControl->getDatabase()->getMysqli();
		$settingTableQuery = "CREATE TABLE IF NOT EXISTS `" . self::TABLE_SETTINGS . "` (
				`index` INT(11) NOT NULL AUTO_INCREMENT,
				`class` VARCHAR(100) NOT NULL,
				`setting` VARCHAR(150) NOT NULL,
				`type` VARCHAR(50) NOT NULL,
				`value` VARCHAR(150) NOT NULL,
				`default` VARCHAR(100) NOT NULL,
				`set` VARCHAR(100) NOT NULL,
				`changed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (`index`),
				UNIQUE KEY `settingId` (`class`,`setting`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Settings and Configurations' AUTO_INCREMENT=1;";
		$result            = $mysqli->query($settingTableQuery);
		if ($mysqli->error) {
			trigger_error($mysqli->error, E_USER_ERROR);
		}

		$mysqli->query("ALTER TABLE `" . self::TABLE_SETTINGS . "` ADD `description` VARCHAR(500) DEFAULT NULL;");
		if ($mysqli->error) {
			// If not Duplicate
			if ($mysqli->errno !== 1060) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}
		}
		
		// Grow the size limit for plugins settings
		$mysqli->query("ALTER TABLE `" . self::TABLE_SETTINGS . "` MODIFY `value` VARCHAR(1000);");
		if ($mysqli->error) {
			// If not Duplicate
			if ($mysqli->errno !== 1060) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}
		}

		// Add priority value
		$mysqli->query("ALTER TABLE `" . self::TABLE_SETTINGS . "` ADD `priority` INT(5) DEFAULT 100;");
		if ($mysqli->error) {
			// If not Duplicate
			if ($mysqli->errno !== 1060) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}
		}

		// Add link status
		$mysqli->query("ALTER TABLE `" . self::TABLE_SETTINGS . "` ADD COLUMN `linked` TINYINT(1) DEFAULT 1 AFTER `type`,
						ADD COLUMN `serverIndex` INT(11)  DEFAULT 0 AFTER `linked`,
						DROP INDEX `settingId`,
						ADD UNIQUE KEY `settingId` (`class`,`setting`,`serverIndex`);");
		if ($mysqli->error) {
			// If not Duplicate
			if ($mysqli->errno !== 1060) {
				trigger_error($mysqli->error, E_USER_ERROR);
			}
		}
		return $result;
	}

	/**
	 * Handle After Init Callback
	 */
	public function handleAfterInit() {
		$this->disableCache = $this->getSettingValue($this, self::SETTING_DISABLE_SETTING_CACHE);
		$this->deleteUnusedSettings();
	}

	/**
	 * Delete all unused Settings that haven't been initialized during the current Startup
	 *
	 * @return bool
	 */
	private function deleteUnusedSettings() {
		if (!$this->getSettingValue($this, self::SETTING_DELETE_UNUSED_SETTING_AT_START)) {
			return;
		}

		$mysqli       = $this->maniaControl->getDatabase()->getMysqli();
		$settingStatement = $mysqli->prepare("DELETE FROM `" . self::TABLE_SETTINGS . "`
				WHERE ((`linked` = 0 AND `serverIndex` = ?) OR `linked` = 1) AND `changed` < NOW() - INTERVAL 1 HOUR;");
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}

		$serverInfo      = $this->maniaControl->getServer();
		if ($serverInfo === null) {
			return;
		} else {
			$serverIndex = $serverInfo->index;
		}
		$settingStatement->bind_param('i', $serverIndex);
		if (!$settingStatement->execute()) {
			trigger_error('Error executing MySQL query: ' . $settingStatement->error);
		}
		$result = $settingStatement->get_result();
		if ($result) {
			$this->clearStorage();
			return true;
		}
		return false;
	}

	/**
	 * Clear the Settings Storage
	 */
	public function clearStorage() {
		$this->storedSettings = array();
	}

	/**
	 * @deprecated
	 * @see SettingManager::getSettingValueByIndex()
	 */
	public function getSettingByIndex($settingIndex, $defaultValue = null) {
		return $this->getSettingValueByIndex($settingIndex, $defaultValue);
	}

	/**
	 * Get a Setting Value by its Index
	 *
	 * @param int   $settingIndex
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	public function getSettingValueByIndex($settingIndex, $defaultValue = null) {
		$setting = $this->getSettingObjectByIndex($settingIndex);
		if (!$setting) {
			return $defaultValue;
		}
		return $setting->value;
	}

	/**
	 * Get a Setting Object by its Index
	 *
	 * @param int $settingIndex
	 * @return Setting
	 */
	public function getSettingObjectByIndex($settingIndex) {
		$mysqli       = $this->maniaControl->getDatabase()->getMysqli();
		$settingQuery = "SELECT * FROM `" . self::TABLE_SETTINGS . "`
				WHERE `index` = {$settingIndex};";
		$result       = $mysqli->query($settingQuery);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}
		if ($result->num_rows <= 0) {
			$result->free();
			return null;
		}

		/** @var Setting $setting */
		$setting = $result->fetch_object(Setting::CLASS_NAME, array(false, null, null));
		$result->free();

		$this->storeSetting($setting);

		return $setting;
	}

	/**
	 * Store the given Setting
	 *
	 * @param Setting $setting
	 */
	private function storeSetting(Setting $setting) {
		if ($this->disableCache && $setting->linked) return;
		$this->storedSettings[$setting->class . $setting->setting] = $setting;
	}

	/**
	 * Set a Setting for the given Object
	 *
	 * @param mixed       $object
	 * @param string      $settingName
	 * @param mixed       $value
	 * @param string|null $description
	 * @return bool
	 */
	public function setSetting($object, $settingName, $value, $description = null) {
		//TODO nowhere used, everywhere saveSettings used, is it depreciated?
		$setting = $this->getSettingObject($object, $settingName);
		if ($setting) {
			$setting->value = $value;
			$saved          = $this->saveSetting($setting);
			if (!$saved) {
				return false;
			}
		} else {
			$saved = $this->initSetting($object, $settingName, $value, $description);
			if (!$saved) {
				return false;
			}
			$setting = $this->getSettingObject($object, $settingName, $value);
		}

		$this->storeSetting($setting);

		return true;
	}

	/**
	 * Get Setting by Name for the given Object
	 *
	 * @param mixed  $object
	 * @param string $settingName
	 * @param mixed  $default
	 * @return Setting
	 */
	public function getSettingObject($object, $settingName, $default = null) {
		$settingClass = ClassUtil::getClass($object);

		// Retrieve from Storage if possible
		$storedSetting = $this->getStoredSetting($object, $settingName);
		if ($storedSetting) {
			return $storedSetting;
		}

		// Fetch setting
		$mysqli       = $this->maniaControl->getDatabase()->getMysqli();
		$settingStatement = $mysqli->prepare("SELECT * FROM `" . self::TABLE_SETTINGS . "`
				WHERE `class` = ? 
				AND `setting` = ?
				AND (`serverIndex` = ? OR `serverIndex` = 0) ORDER BY `serverIndex` DESC ;");
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}

		$serverInfo      = $this->maniaControl->getServer();
		if ($serverInfo == null) {
			$serverIndex = "";
		} else {
			$serverIndex = $serverInfo->index;
		}
		$settingStatement->bind_param('ssi', $settingClass, $settingName, $serverIndex);
		if (!$settingStatement->execute()) {
			trigger_error('Error executing MySQL query: ' . $settingStatement->error);
		}
		$result = $settingStatement->get_result();
		if ($result->num_rows <= 0) {
			$result->free();
			if ($default === null) {
				return null;
			}
			$saved = $this->initSetting($object, $settingName, $default);
			if ($saved) {
				return $this->getSettingObject($object, $settingName, $default);
			} else {
				return null;
			}
		}

		/** @var Setting $setting */
		$setting = $result->fetch_object(Setting::CLASS_NAME, array(false, null, null));
		$result->free();

		$this->storeSetting($setting);

		return $setting;
	}

	/**
	 * Retrieve a stored Setting
	 *
	 * @param mixed  $settingClass
	 * @param string $settingName
	 * @return Setting
	 */
	private function getStoredSetting($settingClass, $settingName) {
		$settingClass = ClassUtil::getClass($settingClass);
		if (isset($this->storedSettings[$settingClass . $settingName])) {
			return $this->storedSettings[$settingClass . $settingName];
		}
		return null;
	}

	/**
	 * Initialize a Setting for the given Object
	 *
	 * @param mixed       $object
	 * @param string      $settingName
	 * @param mixed       $defaultValue
	 * @param string|null $description
	 * @return bool
	 */
	public function initSetting($object, $settingName, $defaultValue, $description = null, $priority = 100) {
		$setting = new Setting($object, $settingName, $defaultValue, $description, $priority);
		return $this->saveSetting($setting, true);
	}

	/**
	 * Save the given Setting in the Database
	 *
	 * @param Setting $setting
	 * @param bool    $init
	 * @return bool
	 */
	public function saveSetting(Setting $setting, $init = false) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		if ($init) {
			$existingsetting = $this->getSettingObject($setting->class, $setting->setting);
			if ($existingsetting !== null && !$existingsetting->linked){
				$setting->linked = false;
			}

			// Init - Keep old value if the default didn't change
			$valueUpdateString = '`value` = IF(`default` = VALUES(`default`), `value`, VALUES(`default`)),
					`serverIndex` = IF(`serverIndex` IS NULL, 0, `serverIndex`)';
		} else {
			// Set - Update value in any case
			$valueUpdateString = '`value` = VALUES(`value`), `serverIndex` = VALUES(`serverIndex`)';
		}
		$settingQuery     = "INSERT INTO `" . self::TABLE_SETTINGS . "` (
				`class`,
				`setting`,
				`type`,
				`description`,
				`value`,
				`serverIndex`,
				`linked`,
				`default`,
				`set`,
				`priority`
				) VALUES (
				?, ?, ?, ?, ?, ?, ?, ?, ?, ?
				) ON DUPLICATE KEY UPDATE
				`index` = LAST_INSERT_ID(`index`),
				`type` = VALUES(`type`),
				{$valueUpdateString},
				`linked` = VALUES(`linked`),
				`default` = VALUES(`default`),
				`set` = VALUES(`set`),
				`description` = VALUES(`description`),
				`priority` = VALUES(`priority`),
				`changed` = NOW();";
		$settingStatement = $mysqli->prepare($settingQuery);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}
		$formattedValue   = $setting->getFormattedValue();
		$formattedDefault = $setting->getFormattedDefault();
		$formattedSet     = $setting->getFormattedSet();
		$serverInfo      = $this->maniaControl->getServer();

		if ($setting->linked || $serverInfo == null || $serverInfo->index == null) {
			$serverIndex = 0;
		} else {
			$serverIndex = $serverInfo->index;
		}
		$settingStatement->bind_param(
			'sssssiissi',
			$setting->class,
			$setting->setting,
			$setting->type,
			$setting->description,
			$formattedValue,
			$serverIndex,
			$setting->linked,
			$formattedDefault,
			$formattedSet,
			$setting->priority
		);
		$settingStatement->execute();
		if ($settingStatement->error) {
			trigger_error($settingStatement->error);
			$settingStatement->close();
			return false;
		}
		$settingStatement->close();

		// Trigger Settings Changed Callback
		if (!$init) {
			$this->maniaControl->getCallbackManager()->triggerCallback(self::CB_SETTING_CHANGED, $setting);
		}

		if ($setting->setting === self::SETTING_DISABLE_SETTING_CACHE) {
			$this->disableCache = $setting->value;
			if ($this->disableCache) {
				$this->clearStorage();
			}
		}

		return true;
	}

	/**
	 * @deprecated
	 * @see SettingManager::getSettingValue()
	 */
	public function getSetting($object, $settingName, $default = null) {
		return $this->getSettingValue($object, $settingName, $default);
	}

	/**
	 * Get the Setting Value directly
	 *
	 * @param mixed  $object
	 * @param string $settingName
	 * @param mixed  $default
	 * @return mixed
	 */
	public function getSettingValue($object, $settingName, $default = null) {
		$setting = $this->getSettingObject($object, $settingName, $default);
		if ($setting) {
			return $setting->value;
		}
		return null;
	}
	
	/**
	 * setSettingUnlinked
	 *
	 * @param  mixed $object
	 * @param  string $settingName
	 * @return void
	 */
	public function setSettingUnlinked($object, $settingName = "") {
		if ($object instanceof Setting) {
			$settingClass   = $object->class;
			$settingName = $object->setting;
		} else {
			$settingClass = ClassUtil::getClass($object);
		}

		// Fetch setting
		$mysqli       = $this->maniaControl->getDatabase()->getMysqli();
		$settingStatement = $mysqli->prepare("UPDATE `" . self::TABLE_SETTINGS . "`
				SET `linked` = 0, `changed` = NOW()
				WHERE `class` = ? 
				AND `setting` = ?");
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}
		
		$settingStatement->bind_param('ss', $settingClass, $settingName);
		if (!$settingStatement->execute()) {
			trigger_error('Error executing MySQL query: ' . $settingStatement->error);
		}
		$result = $settingStatement->get_result();

		if (isset($this->storedSettings[$settingClass . $settingName])) {
			unset($this->storedSettings[$settingClass . $settingName]);
		}
		return $result;
	}

	/**
	 * Reset a Setting to its Default Value
	 *
	 * @param mixed  $object
	 * @param string $settingName
	 * @return bool
	 */
	public function resetSetting($object, $settingName = null) {
		if ($object instanceof Setting) {
			$settingClass   = $object->class;
			$settingName = $object->setting;
		} else {
			$settingClass = ClassUtil::getClass($object);
		}
		$mysqli       = $this->maniaControl->getDatabase()->getMysqli();
		$settingStatement = $mysqli->prepare("UPDATE `" . self::TABLE_SETTINGS . "`
				SET `value` = `default`
				WHERE `class` = ?
				AND `setting` = ?
				AND (`serverIndex` = ? OR `serverIndex` = 0) ORDER BY `serverIndex` DESC LIMIT 1;"); // TODO : by server if linked or not
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}

		$serverInfo      = $this->maniaControl->getServer();
		if ($serverInfo == null) {
			$serverIndex = 0;
		} else {
			$serverIndex = $serverInfo->index;
		}
		$settingStatement->bind_param('ssi', $settingClass, $settingName, $serverIndex);
		if (!$settingStatement->execute()) {
			trigger_error('Error executing MySQL query: ' . $settingStatement->error);
		}
		$result = $settingStatement->get_result();

		if (isset($this->storedSettings[$settingClass . $settingName])) {
			unset($this->storedSettings[$settingClass . $settingName]);
		}

		return $result;
	}

	/**
	 * Delete a Setting
	 *
	 * @param mixed  $object
	 * @param string $settingName
	 * @return bool
	 */
	public function deleteSetting($object, $settingName = null) {
		if ($object instanceof Setting) {
			$className   = $object->class;
			$settingName = $object->setting;
		} else {
			$className = ClassUtil::getClass($object);
		}

		$mysqli       = $this->maniaControl->getDatabase()->getMysqli();
		$settingQuery = "DELETE FROM `" . self::TABLE_SETTINGS . "`
				WHERE `class` = '" . $mysqli->escape_string($className) . "'
				AND `setting` = '" . $mysqli->escape_string($settingName) . "';";
		$result       = $mysqli->query($settingQuery);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return false;
		}

		if (isset($this->storedSettings[$className . $settingName])) {
			unset($this->storedSettings[$className . $settingName]);
		}

		return $result;
	}

	/**
	 * Delete unlinked settings
	 *
	 * @param mixed  $object
	 * @param string $settingName
	 * @return bool
	 */
	public function deleteSettingUnlinked($object, $settingName = null) {
		if ($object instanceof Setting) {
			$settingClass   = $object->class;
			$settingName = $object->setting;
		} else {
			$settingClass = ClassUtil::getClass($object);
		}

		// Fetch setting
		$mysqli       = $this->maniaControl->getDatabase()->getMysqli();
		$settingStatement = $mysqli->prepare("DELETE FROM `" . self::TABLE_SETTINGS . "`
				WHERE `class` = ? 
				AND `setting` = ?
				AND `serverIndex` != 0");
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}

		$settingStatement->bind_param('ss', $settingClass, $settingName);
		if (!$settingStatement->execute()) {
			trigger_error('Error executing MySQL query: ' . $settingStatement->error);
		}
		$result = $settingStatement->get_result();

		if (isset($this->storedSettings[$settingClass . $settingName])) {
			unset($this->storedSettings[$settingClass . $settingName]);
		}

		return $result;
	}

	/**
	 * Get all Settings for the given Class
	 *
	 * @param mixed $object
	 * @return Setting[]
	 */
	public function getSettingsByClass($object) {
		$className = ClassUtil::getClass($object);
		$mysqli    = $this->maniaControl->getDatabase()->getMysqli();
		// LIMIT is required to keep unlinked setting
		$settingStatement = $mysqli->prepare("SELECT * FROM (SELECT * FROM `" . self::TABLE_SETTINGS . "` 
												WHERE class = ? AND (`serverIndex` = ? OR `serverIndex` = 0)
												ORDER BY `serverIndex` DESC 
												LIMIT 9999999) 
												as t GROUP BY `setting` ORDER BY `priority` ASC, `setting`;");
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}
		$serverInfo      = $this->maniaControl->getServer();
		if ($serverInfo == null) {
			$serverIndex = 0;
		} else {
			$serverIndex = $serverInfo->index;
		}
		$settingStatement->bind_param('si', $className, $serverIndex);
		if (!$settingStatement->execute()) {
			trigger_error('Error executing MySQL query: ' . $settingStatement->error);
		}
		$result = $settingStatement->get_result();
		$settings = array();
		while ($setting = $result->fetch_object(Setting::CLASS_NAME, array(false, null, null))) {
			$settings[$setting->index] = $setting;
		}
		$result->free();
		return $settings;
	}

	/**
	 * Get all Settings
	 * CAREFUL: could have multiple time the same setting if in unlinked mode
	 *
	 * @return Setting[]
	 */
	public function getSettings() { 
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query  = "SELECT * FROM `" . self::TABLE_SETTINGS . "`
				ORDER BY `class` ASC, `priority` ASC, `setting` ASC;";
		$result = $mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}
		$settings = array();
		while ($setting = $result->fetch_object(Setting::CLASS_NAME, array(false, null, null))) {
			$settings[$setting->index] = $setting;
		}
		$result->free();
		return $settings;
	}

	/**
	 * Get all Setting Classes
	 *
	 * @param bool $hidePluginClasses
	 * @return string[]
	 */
	public function getSettingClasses($hidePluginClasses = false) {
		$mysqli = $this->maniaControl->getDatabase()->getMysqli();
		$query  = "SELECT DISTINCT `class` FROM `" . self::TABLE_SETTINGS . "`
				ORDER BY `class` ASC;";
		$result = $mysqli->query($query);
		if ($mysqli->error) {
			trigger_error($mysqli->error);
			return null;
		}
		$settingClasses = array();
		while ($row = $result->fetch_object()) {
			if (!$hidePluginClasses || !PluginManager::isPluginClass($row->class)) {
				array_push($settingClasses, $row->class);
			}
		}
		$result->free();
		return $settingClasses;
	}
}
