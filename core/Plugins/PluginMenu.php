<?php

namespace ManiaControl\Plugins;

use FML\Components\CheckBox;
use FML\Components\ValuePicker;
use FML\Controls\Entry;
use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Labels\Label_Button;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Icons128x128_1;
use FML\Controls\Quads\Quad_Icons128x32_1;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\Script\Features\Paging;
use FML\Script\Script;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\CallbackManager;
use ManiaControl\Configurator\ConfiguratorMenu;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;


/**
 * Configurator for enabling and disabling Plugins
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2020 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class PluginMenu implements CallbackListener, ConfiguratorMenu, ManialinkPageAnswerListener {
	/*
	 * Constants
	 */
	const ACTION_PREFIX_ENABLEPLUGIN                = 'PluginMenu.Enable.';
	const ACTION_PREFIX_DISABLEPLUGIN               = 'PluginMenu.Disable.';
	const ACTION_PREFIX_SETTINGS                    = 'PluginMenu.Settings.';
	const ACTION_PREFIX_SETTING                     = 'PluginMenuSetting.';
	const ACTION_PREFIX_SETTING_LINK                = 'PluginMenuSettingLink.';
	const ACTION_PREFIX_MANAGE_SETTING_LINK         = 'PluginMenu.ManageSettingsLink.';
	const ACTION_BACK_TO_PLUGINS                    = 'PluginMenu.BackToPlugins';
	const ACTION_PREFIX_UPDATEPLUGIN                = 'PluginMenu.Update.';
	const ACTION_UPDATEPLUGINS                      = 'PluginMenu.Update.All';
	const SETTING_PERMISSION_CHANGE_PLUGIN_SETTINGS = 'Change Plugin Settings';
	const SETTING_CHECK_UPDATE_WHEN_OPENING         = 'Check update when opening the menu';
	const CACHE_SETTING_CLASS                       = 'PluginMenuCache.SettingClass';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	/**
	 * Construct a new plugin menu instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CHECK_UPDATE_WHEN_OPENING, true);

		// Callbacks
		$this->maniaControl->getCallbackManager()->registerCallbackListener(CallbackManager::CB_MP_PLAYERMANIALINKPAGEANSWER, $this, 'handleManialinkPageAnswer');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_BACK_TO_PLUGINS, $this, 'backToPlugins');

		// Permissions
		$this->maniaControl->getAuthenticationManager()->definePermissionLevel(self::SETTING_PERMISSION_CHANGE_PLUGIN_SETTINGS, AuthenticationManager::AUTH_LEVEL_SUPERADMIN);
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::getTitle()
	 */
	public static function getTitle() {
		return 'Plugins';
	}

	/**
	 * Return back to the plugins overview page
	 *
	 * @param array  $callback
	 * @param Player $player
	 */
	public function backToPlugins($callback, Player $player) {
		$player->destroyCache($this, self::CACHE_SETTING_CLASS);
		$this->maniaControl->getConfigurator()->showMenu($player, $this);
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::getMenu()
	 */
	public function getMenu($width, $height, Script $script, Player $player) {
		$paging = new Paging();
		$script->addFeature($paging);
		$frame = new Frame();

		$pluginClasses = $this->maniaControl->getPluginManager()->getPluginClasses();

		// Config
		$pagerSize    = 9.;
		$entryHeight  = 5.;
		$pageMaxCount = floor(($height * 0.70) / $entryHeight);

		// Pagers
		$pagerPrev = new Quad_Icons64x64_1();
		$frame->addChild($pagerPrev);
		$pagerPrev->setPosition($width * 0.39, $height * -0.44, 2);
		$pagerPrev->setSize($pagerSize, $pagerSize);
		$pagerPrev->setSubStyle(Quad_Icons64x64_1::SUBSTYLE_ArrowPrev);

		$pagerNext = new Quad_Icons64x64_1();
		$frame->addChild($pagerNext);
		$pagerNext->setPosition($width * 0.45, $height * -0.44, 2);
		$pagerNext->setSize($pagerSize, $pagerSize);
		$pagerNext->setSubStyle(Quad_Icons64x64_1::SUBSTYLE_ArrowNext);

		$paging->addButtonControl($pagerNext);
		$paging->addButtonControl($pagerPrev);

		$pageCountLabel = new Label_Text();
		$frame->addChild($pageCountLabel);
		$pageCountLabel->setHorizontalAlign($pageCountLabel::RIGHT);
		$pageCountLabel->setPosition($width * 0.35, $height * -0.44, 1);
		$pageCountLabel->setStyle($pageCountLabel::STYLE_TextTitle1);
		$pageCountLabel->setTextSize(2);

		$paging->setLabel($pageCountLabel);

		$SubMenu = $player->getCache($this, self::CACHE_SETTING_CLASS);
		if ($SubMenu) {
			// Show Settings Menu
			if (strpos($SubMenu, self::ACTION_PREFIX_SETTINGS) === 0) {
				$settingClass = substr($SubMenu, strlen(self::ACTION_PREFIX_SETTINGS));
				return $this->getPluginSettingsMenu($frame, $width, $height, $paging, $player, $settingClass);
			} else if (strpos($SubMenu, self::ACTION_PREFIX_MANAGE_SETTING_LINK) === 0) {
				$settingClass = substr($SubMenu, strlen(self::ACTION_PREFIX_MANAGE_SETTING_LINK));
				return $this->getManageSettingsLink($frame, $width, $height, $paging, $player, $settingClass);
			}
		}

		// Display normal Plugin List
		// Plugin pages
		$posY          = 0.;

		$pluginUpdates = null;
		if ($this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CHECK_UPDATE_WHEN_OPENING)) {
			$pluginUpdates = $this->maniaControl->getUpdateManager()->getPluginUpdateManager()->getPluginsUpdates();
		}

		usort($pluginClasses, function ($pluginClassA, $pluginClassB) {
			/** @var Plugin $pluginClassA */
			/** @var Plugin $pluginClassB */
			return strcmp($pluginClassA::getName(), $pluginClassB::getName());
		});

		$pageFrame = null;
		foreach ($pluginClasses as $index => $pluginClass) {
			/** @var Plugin $pluginClass */
			if ($index % $pageMaxCount === 0) {
				$pageFrame = new Frame();
				$frame->addChild($pageFrame);
				$paging->addPageControl($pageFrame);
				$posY = $height * 0.41;
			}

			$active = $this->maniaControl->getPluginManager()->isPluginActive($pluginClass);

			$pluginFrame = new Frame();
			$pageFrame->addChild($pluginFrame);
			$pluginFrame->setY($posY);

			$activeQuad = new Quad_Icons64x64_1();
			$pluginFrame->addChild($activeQuad);
			$activeQuad->setPosition($width * -0.45, -0.1, 1);
			$activeQuad->setSize($entryHeight * 0.9, $entryHeight * 0.9);
			if ($active) {
				$activeQuad->setSubStyle($activeQuad::SUBSTYLE_LvlGreen);
			} else {
				$activeQuad->setSubStyle($activeQuad::SUBSTYLE_LvlRed);
			}

			$nameLabel = new Label_Text();
			$pluginFrame->addChild($nameLabel);
			$nameLabel->setHorizontalAlign($nameLabel::LEFT);
			$nameLabel->setX($width * -0.4);
			$nameLabel->setSize($width * 0.5, $entryHeight);
			$nameLabel->setStyle($nameLabel::STYLE_TextCardSmall);
			$nameLabel->setTextSize(2);
			$nameLabel->setText($pluginClass::getName());

			$descriptionLabel = new Label();
			$pageFrame->addChild($descriptionLabel);
			$descriptionLabel->setAlign($descriptionLabel::LEFT, $descriptionLabel::BOTTOM);
			$descriptionLabel->setPosition($width * -0.45, $height * -0.45);
			$descriptionLabel->setSize($width * 0.7, $entryHeight);
			$descriptionLabel->setTextSize(2);
			$descriptionLabel->setTranslate(true);
			$descriptionLabel->setVisible(false);
			$descriptionLabel->setMaxLines(5);
			$descriptionLabel->setLineSpacing(1);
			$description = "Author: {$pluginClass::getAuthor()}\nVersion: {$pluginClass::getVersion()}\nDesc: {$pluginClass::getDescription()}";
			$nameLabel->addTooltipLabelFeature($descriptionLabel,$description);

			$quad = new Quad_Icons128x32_1();
			$pluginFrame->addChild($quad);
			$quad->setSubStyle($quad::SUBSTYLE_Settings);
			$quad->setX(15);
			$quad->setZ(1);
			$quad->setSize(5, 5);
			$quad->setAction(self::ACTION_PREFIX_SETTINGS . $pluginClass);

			$statusChangeButton = new Label_Button();
			$pluginFrame->addChild($statusChangeButton);
			$statusChangeButton->setHorizontalAlign($statusChangeButton::RIGHT);
			$statusChangeButton->setX($width * 0.45);
			$statusChangeButton->setStyle($statusChangeButton::STYLE_CardButtonSmall);
			if ($active) {
				$statusChangeButton->setTextPrefix('$f00');
				$statusChangeButton->setText('Deactivate');
				$statusChangeButton->setAction(self::ACTION_PREFIX_DISABLEPLUGIN . $pluginClass);
			} else {
				$statusChangeButton->setTextPrefix('a');
				$statusChangeButton->setText('Activate');
				$statusChangeButton->setAction(self::ACTION_PREFIX_ENABLEPLUGIN . $pluginClass);
			}

			if ($pluginUpdates && array_key_exists($pluginClass::getId(), $pluginUpdates)) {
				$quadUpdate = new Quad_Icons128x128_1();
				$pluginFrame->addChild($quadUpdate);
				$quadUpdate->setSubStyle($quadUpdate::SUBSTYLE_ProfileVehicle);
				$quadUpdate->setX($width / 2 - 3);
				$quadUpdate->setZ(2);
				$quadUpdate->setSize(5, 5);
				$quadUpdate->setAction(self::ACTION_PREFIX_UPDATEPLUGIN . $pluginClass);
			}

			$posY -= $entryHeight;
		}

		if ($pluginUpdates) {
			$updatePluginsButton = new Label_Button();
			$frame->addChild($updatePluginsButton);
			$updatePluginsButton->setHorizontalAlign($updatePluginsButton::RIGHT);
			$updatePluginsButton->setPosition($width * 0.5, $height * -0.37, 2);
			$updatePluginsButton->setWidth(10);
			$updatePluginsButton->setStyle($updatePluginsButton::STYLE_CardButtonSmallS);
			$updatePluginsButton->setText(count($pluginUpdates) . ' update(s)');
			$updatePluginsButton->setAction(self::ACTION_UPDATEPLUGINS);
		}

		return $frame;
	}

	/**
	 * Get the Frame with the Plugin Settings
	 *
	 * @param Frame  $frame
	 * @param float  $width
	 * @param float  $height
	 * @param Paging $paging
	 * @param Player $player
	 * @param string $settingClass
	 * @return Frame
	 */
	private function getPluginSettingsMenu(Frame $frame, $width, $height, Paging $paging, Player $player, $settingClass) {
		// TODO: centralize menu code to use by mc settings and plugin settings
		$settings = $this->maniaControl->getSettingManager()->getSettingsByClass($settingClass);
		$isunlinkable = $this->maniaControl->getSettingManager()->getSettingValue($this->maniaControl->getSettingManager(), SettingManager::SETTING_ALLOW_UNLINK_SERVER);

		$posY                 = 0;
		$index                = 0;
		$settingHeight        = 5.;
		$pageSettingsMaxCount = floor(($height * 0.70) / $settingHeight);
		$pageFrame            = null;

		//Headline Label
		$headLabel = new Label_Text();
		$frame->addChild($headLabel);
		$headLabel->setHorizontalAlign($headLabel::LEFT);
		$headLabel->setPosition($width * -0.46, $height * 0.41);
		$headLabel->setSize($width * 0.6, $settingHeight);
		$headLabel->setStyle($headLabel::STYLE_TextCardSmall);
		$headLabel->setTextSize(3);
		$headLabel->setText($settingClass);
		$headLabel->setTextColor('ff0');

		if (count($settings) > 64) {
			Logger::logWarning("You can't send more than 64 fields in Manialink Action");
			$this->maniaControl->getChat()->sendError("Some settings may not be saved because it has more than 64", $player);
		}

		foreach ($settings as $setting) {
			if ($index % $pageSettingsMaxCount === 0) {
				$pageFrame = new Frame();
				$frame->addChild($pageFrame);
				$paging->addPageControl($pageFrame);
				$posY = $height * 0.41 - $settingHeight * 1.5;
			}

			$settingFrame = new Frame();
			$pageFrame->addChild($settingFrame);
			$settingFrame->setY($posY);

			$nameLabel = new Label_Text();
			$settingFrame->addChild($nameLabel);
			$nameLabel->setHorizontalAlign($nameLabel::LEFT);
			$nameLabel->setX($width * -0.46);
			$nameLabel->setSize($width * 0.6, $settingHeight);
			$nameLabel->setStyle($nameLabel::STYLE_TextCardSmall);
			$nameLabel->setTextSize(2);
			$nameLabel->setText($setting->setting);
			$nameLabel->setTextColor('fff');

			$descriptionLabel = new Label_Text();
			$pageFrame->addChild($descriptionLabel);
			$descriptionLabel->setHorizontalAlign($descriptionLabel::LEFT);
			$descriptionLabel->setPosition(-0.45 * $width, $height * -0.38);
			$descriptionLabel->setSize(0.9 * $width, $settingHeight);
			$descriptionLabel->setTextSize(2);
			$descriptionLabel->setTranslate(true);
			$nameLabel->addTooltipLabelFeature($descriptionLabel, $setting->description);

			if ($isunlinkable) {
				$quadlink = new Quad();
				$settingFrame->addChild($quadlink);
				$quadlink->setPosition(-0.48 * $width, 0.2, -0.01);
				$quadlink->setSize(4, 4);
				$quadlink->setColorize("ccccccaa");
				$quadlink->setStyle("UICommon64_1");
				$quadlink->setSubStyle("Padlock_light");
				$quadlink->setStyleSelected($setting->linked);
			}

			if ($setting->type === Setting::TYPE_BOOL) {
				// Boolean checkbox
				$quad = new Quad();
				$quad->setPosition($width * 0.33, 0, -0.01);
				$quad->setSize(4, 4);
				$checkBox = new CheckBox(self::ACTION_PREFIX_SETTING . $setting->index, $setting->value, $quad);
				$settingFrame->addChild($checkBox);
			} else if ($setting->type === Setting::TYPE_SET) {
				// SET value picker
				$label = new Label_Text();
				$label->setX($width * 0.33);
				$label->setSize($width * 0.3, $settingHeight * 0.9);
				$label->setStyle($label::STYLE_TextValueSmall);
				$label->setTextSize(1);
				$valuePicker = new ValuePicker(self::ACTION_PREFIX_SETTING . $setting->index, $setting->set, $setting->value, $label);
				$settingFrame->addChild($valuePicker);
			} else {
				// Value entry
				$entry = new Entry();
				$settingFrame->addChild($entry);
				$entry->setX($width * 0.33);
				$entry->setSize($width * 0.3, $settingHeight * 0.9);
				$entry->setTextSize(1);
				$entry->setMaxLength(1000);
				$entry->setStyle(Label_Text::STYLE_TextValueSmall);
				$entry->setName(self::ACTION_PREFIX_SETTING . $setting->index);
				$entry->setDefault($setting->value);
			}

			$posY -= $settingHeight;

			$index++;
		}

		$backButton = new Label_Button();
		$frame->addChild($backButton);
		$backButton->setStyle($backButton::STYLE_CardMain_Quit);
		$backButton->setHorizontalAlign($backButton::LEFT);
		$backButton->setScale(0.5);
		$backButton->setText('Back');
		$backButton->setPosition(-$width / 2 + 5, -$height / 2 + 5);
		$backButton->setAction(self::ACTION_BACK_TO_PLUGINS);

		if ($isunlinkable) {
			$mapNameButton = $this->maniaControl->getManialinkManager()->getElementBuilder()->buildRoundTextButton(
				'Manage settings link',
				30,
				5,
				self::ACTION_PREFIX_MANAGE_SETTING_LINK . $settingClass
			);
			$frame->addChild($mapNameButton);
			$mapNameButton->setPosition(-$width / 2 + 60, $height * -0.45);
		}

		return $frame;
	}
	
	/**
	 * getManageSettingsLink
	 *
	 * @param  Frame $frame
	 * @param  float $width
	 * @param  float $height
	 * @param  Paging $paging
	 * @param  Player $player
	 * @param  mixed $settingClass
	 * @return void
	 */
	public function getManageSettingsLink(Frame $frame, $width, $height, Paging $paging, Player $player, $settingClass) {
		$settings = $this->maniaControl->getSettingManager()->getSettingsByClass($settingClass);

		$posY                 = 0;
		$index                = 0;
		$settingHeight        = 5.;
		$pageSettingsMaxCount = floor(($height * 0.78) / $settingHeight);
		$pageFrame            = null;

		if (count($settings) > 64) {
			Logger::logWarning("You can't send more than 64 fields in Manialink Action");
			$this->maniaControl->getChat()->sendError("Some settings may not be saved because it has more than 64", $player);
		}

		//Headline Label
		$headLabel = new Label_Text();
		$frame->addChild($headLabel);
		$headLabel->setHorizontalAlign($headLabel::LEFT);
		$headLabel->setPosition($width * -0.46, $height * 0.41);
		$headLabel->setSize($width * 0.6, $settingHeight);
		$headLabel->setStyle($headLabel::STYLE_TextCardSmall);
		$headLabel->setTextSize(3);
		$headLabel->setText($settingClass);
		$headLabel->setTextColor('ff0');

		foreach ($settings as $setting) {
			if ($index % $pageSettingsMaxCount === 0) {
				$pageFrame = new Frame();
				$frame->addChild($pageFrame);
				$paging->addPageControl($pageFrame);
				$posY = $height * 0.41 - $settingHeight * 1.5;
			}

			$settingFrame = new Frame();
			$pageFrame->addChild($settingFrame);
			$settingFrame->setY($posY);

			$nameLabel = new Label_Text();
			$settingFrame->addChild($nameLabel);
			$nameLabel->setHorizontalAlign($nameLabel::LEFT);
			$nameLabel->setX($width * -0.46);
			$nameLabel->setSize($width * 0.6, $settingHeight);
			$nameLabel->setStyle($nameLabel::STYLE_TextCardSmall);
			$nameLabel->setTextSize(2);
			$nameLabel->setText($setting->setting);
			$nameLabel->setTextColor('fff');

			$descriptionLabel = new Label_Text();
			$pageFrame->addChild($descriptionLabel);
			$descriptionLabel->setHorizontalAlign($descriptionLabel::LEFT);
			$descriptionLabel->setPosition(-0.45 * $width, $height * -0.40);
			$descriptionLabel->setSize(0.9 * $width, $settingHeight);
			$descriptionLabel->setTextSize(2);
			$descriptionLabel->setTranslate(true);
			$nameLabel->addTooltipLabelFeature($descriptionLabel, $setting->description);

			$quad = new Quad();
			$quad->setPosition($width * 0.33, 0.2, -0.01);
			$quad->setSize(4, 4);
			$checkBox = new CheckBox(self::ACTION_PREFIX_SETTING_LINK . $setting->index, $setting->linked, $quad);
			$checkBox->setEnabledDesign("UICommon64_1", "Padlock_light");
			$checkBox->setDisabledDesign("UICommon64_1", "Padlock_light");
			$settingFrame->addChild($checkBox);

			$posY -= $settingHeight;

			$index++;
		}

		$backButton = new Label_Button();
		$frame->addChild($backButton);
		$backButton->setStyle($backButton::STYLE_CardMain_Quit);
		$backButton->setHorizontalAlign($backButton::LEFT);
		$backButton->setScale(0.5);
		$backButton->setText('Back');
		$backButton->setPosition(-$width / 2 + 5, -$height / 2 + 5);
		$backButton->setAction(self::ACTION_PREFIX_SETTINGS . $settingClass);

		return $frame;
	}

	/**
	 * Handle PlayerManialinkPageAnswer callback
	 *
	 * @param array $callback
	 */
	public function handleManialinkPageAnswer(array $callback) {
		$actionId = $callback[1][2];
		$enable   = (strpos($actionId, self::ACTION_PREFIX_ENABLEPLUGIN) === 0);
		$disable  = (strpos($actionId, self::ACTION_PREFIX_DISABLEPLUGIN) === 0);
		$settings = (strpos($actionId, self::ACTION_PREFIX_SETTINGS) === 0);
		$managelink = (strpos($actionId, self::ACTION_PREFIX_MANAGE_SETTING_LINK) === 0);
		if (!$enable && !$disable && !$settings && !$managelink) {
			return;
		}

		$login  = $callback[1][1];
		$player = $this->maniaControl->getPlayerManager()->getPlayer($login);
		if (!$player) {
			return;
		}

		if (!$this->maniaControl->getAuthenticationManager()->checkPermission($player, self::SETTING_PERMISSION_CHANGE_PLUGIN_SETTINGS))
		{
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}

		if ($enable) {
			$pluginClass = substr($actionId, strlen(self::ACTION_PREFIX_ENABLEPLUGIN));
			/** @var Plugin $pluginClass */
			$activated = $this->maniaControl->getPluginManager()->activatePlugin($pluginClass, $player->login);
			if ($activated) {
				$this->maniaControl->getChat()->sendSuccess($pluginClass::getName() . ' activated!', $player);
				Logger::logInfo("{$player->login} activated '{$pluginClass}'!", true);
			} else {
				$this->maniaControl->getChat()->sendError('Error activating ' . $pluginClass::getName() . '!', $player);
			}
		} else if ($disable) {
			$pluginClass = substr($actionId, strlen(self::ACTION_PREFIX_DISABLEPLUGIN));
			/** @var Plugin $pluginClass */
			$deactivated = $this->maniaControl->getPluginManager()->deactivatePlugin($pluginClass);
			if ($deactivated) {
				$this->maniaControl->getChat()->sendSuccess($pluginClass::getName() . ' deactivated!', $player);
				Logger::logInfo("{$player->login} deactivated '{$pluginClass}'!", true);
			} else {
				$this->maniaControl->getChat()->sendError('Error deactivating ' . $pluginClass::getName() . '!', $player);
			}
		} else if ($settings || $managelink) {
			// Open Settings Menu
			$player->setCache($this, self::CACHE_SETTING_CLASS, $actionId);
		}

		// Reopen the Menu
		$this->maniaControl->getConfigurator()->showMenu($player, $this);
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::saveConfigData()
	 */
	public function saveConfigData(array $configData, Player $player) {
		if (!$this->maniaControl->getAuthenticationManager()->checkPermission($player, self::SETTING_PERMISSION_CHANGE_PLUGIN_SETTINGS)
		) {
			$this->maniaControl->getAuthenticationManager()->sendNotAllowed($player);
			return;
		}

		if (!$configData[3] || (strpos($configData[3][0]['Name'], self::ACTION_PREFIX_SETTING) !== 0 && strpos($configData[3][0]['Name'], self::ACTION_PREFIX_SETTING_LINK) !== 0)) {
			return;
		}


		foreach ($configData[3] as $settingData) {
			if (!$settingData || !isset($settingData['Value'])) {
				continue;
			}
			$data = explode(".", $settingData["Name"]);
			$type = $data[0];
			$index = $data[1];

			$settingObjectByIndex = $this->maniaControl->getSettingManager()->getSettingObjectByIndex($index);
			if (!$settingObjectByIndex) {
				continue;
			}

			if ($type . "." == self::ACTION_PREFIX_SETTING_LINK) {
				if ($settingData['Value'] && !$settingObjectByIndex->linked) {
					$this->maniaControl->getSettingManager()->deleteSettingUnlinked($settingObjectByIndex);		
					$setting = $this->maniaControl->getSettingManager()->getSettingObject($settingObjectByIndex->class, $settingObjectByIndex->setting);
					$setting->linked = True;
				} else if (!$settingData['Value'] && $settingObjectByIndex->linked) {
					$setting = $settingObjectByIndex;
					$setting->linked = false;
					$this->maniaControl->getSettingManager()->setSettingUnlinked($setting);
				} else {
					continue;
				}
			} else {
				$setting = $settingObjectByIndex;
				if ($settingData['Value'] == $setting->value) {
					continue;
				}
				$setting->value = $settingData['Value'];
			}
			$this->maniaControl->getSettingManager()->saveSetting($setting);
		}

		$this->maniaControl->getChat()->sendSuccess('Plugin Settings saved!', $player);

		// Reopen the Menu
		$this->maniaControl->getConfigurator()->showMenu($player, $this);
	}
}
