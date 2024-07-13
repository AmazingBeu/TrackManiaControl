<?php

namespace ManiaControl\Plugins;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Labels\Label_Button;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\Script\Features\Paging;
use FML\Script\Script;
use ManiaControl\Admin\AuthenticationManager;
use ManiaControl\Configurator\ConfiguratorMenu;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use ManiaControl\Utils\WebReader;

/**
 * Configurator for installing Plugins
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2020 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class InstallMenu implements ConfiguratorMenu, ManialinkPageAnswerListener {
	/*
	 * Constants
	 */
	const SETTING_PERMISSION_INSTALL_PLUGINS = 'Install Plugins';
	const ACTION_PREFIX_INSTALL_PLUGIN       = 'PluginInstallMenu.Install.';
	const ACTION_REFRESH_LIST                = 'PluginInstallMenu.RefreshList';
	const SETTING_GAME_ONLY                  = 'Display only Plugins eligible for your game';
	const SETTING_VERSION_ONLY               = 'Display only Plugins eligible for your MC-version';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	/**
	 * Create a new plugin install menu instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// Permissions
		$this->maniaControl->getAuthenticationManager()->definePermissionLevel(self::SETTING_PERMISSION_INSTALL_PLUGINS, AuthenticationManager::AUTH_LEVEL_SUPERADMIN);

		//Settings
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_GAME_ONLY, true);
		$this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_VERSION_ONLY, true);

		// Callbacks
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_REFRESH_LIST, $this, 'handleRefreshListAction');
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::getTitle()
	 */
	public static function getTitle() {
		return 'Install Plugins';
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::getMenu()
	 */
	public function getMenu($width, $height, Script $script, Player $player) {
		$gameOnly    = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GAME_ONLY);
		$versionOnly = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_VERSION_ONLY);

		$paging = new Paging();
		$script->addFeature($paging);
		$frame = new Frame();

		// Config
		$pagerSize   = 9.;
		$entryHeight = 5.;
		$innerWidth = $width - 4;
		$innerHeight = $height - 16;
		$pageMaxCount = floor($innerHeight / $entryHeight);
		$pageFrame   = null;

		$url = ManiaControl::URL_WEBSERVICE . 'plugins';
		if ($gameOnly) {
			$game = $this->maniaControl->getMapManager()->getCurrentMap()->getGame();
			$url  .= '?game=' . $game;
		}
		$response   = WebReader::getUrl($url); //TODO async webrequest
		$dataJson   = $response->getContent();
		$pluginList = json_decode($dataJson);
		$index      = 0;

		if (!is_array($pluginList)) {
			// Error text
			$errorFrame = $this->getErrorFrame();
			$frame->addChild($errorFrame);
		} else if (empty($pluginList)) {
			// Empty text
			$emptyFrame = $this->getEmptyFrame();
			$frame->addChild($emptyFrame);
		} else {
			// Build plugin list
			// Pagers
			$pagerPrev = new Quad_Icons64x64_1();
			$frame->addChild($pagerPrev);
			$pagerPrev->setPosition($width * 0.5 - 12, $height * -0.5 + 5, 2);
			$pagerPrev->setSize($pagerSize, $pagerSize);
			$pagerPrev->setSubStyle($pagerPrev::SUBSTYLE_ArrowPrev);

			$pagerNext = clone $pagerPrev;
			$frame->addChild($pagerNext);
			$pagerNext->setPosition($width * 0.5 - 5, $height * -0.5 + 5, 2);
			$pagerNext->setSubStyle($pagerPrev::SUBSTYLE_ArrowNext);

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

			// Info tooltip
			$infoTooltipLabel = new Label();
			$repositionnedFrame->addChild($infoTooltipLabel);
			$infoTooltipLabel->setAlign($infoTooltipLabel::LEFT, $infoTooltipLabel::TOP);
			$infoTooltipLabel->setPosition(3, $height * -1 + 16);
			$infoTooltipLabel->setSize($width - 30, 20);
			$infoTooltipLabel->setTextSize(1);
			$infoTooltipLabel->setTranslate(true);
			$infoTooltipLabel->setVisible(false);
			$infoTooltipLabel->setAutoNewLine(true);
			$infoTooltipLabel->setMaxLines(5);
			
			// List plugins
			foreach ($pluginList as $plugin) {
				if ($this->maniaControl->getPluginManager()->isPluginIdInstalled($plugin->id)) {
					// Already installed -> Skip
					continue;
				}

				$isPluginCompatible = $this->isPluginCompatible($plugin);
				if ($versionOnly && !$isPluginCompatible) {
					continue;
				}

				if ($index % $pageMaxCount === 0) {
					// New page
					$pageFrame = new Frame();
					$repositionnedFrame->addChild($pageFrame);
					$paging->addPageControl($pageFrame);
					$index = 1;
				}

				$pluginFrame = new Frame();
				$pageFrame->addChild($pluginFrame);
				$pluginFrame->setY($entryHeight * $index * -1);

				$nameLabel = new Label_Text();
				$pluginFrame->addChild($nameLabel);
				$nameLabel->setHorizontalAlign($nameLabel::LEFT);
				$nameLabel->setX(2);
				$nameLabel->setSize($innerWidth * 0.6, $entryHeight);
				$nameLabel->setStyle($nameLabel::STYLE_TextCardSmall);
				$nameLabel->setTextSize(2);
				$nameLabel->setText($plugin->name);

				$description = "Author: {$plugin->author}\nVersion: {$plugin->currentVersion->version}\nDesc: {$plugin->description}";
				$infoTooltipLabel->setLineSpacing(1);
				$nameLabel->addTooltipLabelFeature($infoTooltipLabel, $description);

				if (!$isPluginCompatible) {
					// Incompatibility label
					$infoLabel = new Label_Text();
					$pluginFrame->addChild($infoLabel);
					$infoLabel->setHorizontalAlign($infoLabel::RIGHT)->setX($innerWidth * 0.47)->setSize($innerWidth * 0.33, $entryHeight)->setTextSize(1)->setTextColor('f30');
					if ($plugin->currentVersion->min_mc_version > ManiaControl::VERSION) {
						$infoLabel->setText("Needs at least MC-Version '{$plugin->currentVersion->min_mc_version}'");
					} else {
						$infoLabel->setText("Needs at most MC-Version '{$plugin->currentVersion->max_mc_version}'");
					}
				} else {
					// Install button
					$installButton = new Label_Button();
					$pluginFrame->addChild($installButton);
					$installButton->setHorizontalAlign($installButton::RIGHT);
					$installButton->setSize($innerWidth * 0.3, $entryHeight);
					$installButton->setX($innerWidth - 4);
					$installButton->setStyle($installButton::STYLE_CardButtonSmall);
					$installButton->setText('Install');
					$installButton->setTranslate(true);
					$installButton->setAction(self::ACTION_PREFIX_INSTALL_PLUGIN . $plugin->id);

					if ($plugin->currentVersion->verified > 0) {
						// Suggested quad
						$suggestedQuad = new Quad_Icons64x64_1();
						$pluginFrame->addChild($suggestedQuad);
						$suggestedQuad->setPosition($innerWidth - 2, $entryHeight * 0.12, 2);
						$suggestedQuad->setSize(4, 4);
						$suggestedQuad->setSubStyle($suggestedQuad::SUBSTYLE_StateSuggested);
					}
				}

				$index++;
			}
		}

		return $frame;
	}

	/**
	 * Build the Frame to display when an Error occurred
	 *
	 * @return Frame
	 */
	private function getErrorFrame() {
		$frame = new Frame();

		$infoLabel = new Label_Text();
		$frame->addChild($infoLabel);
		$infoLabel->setVerticalAlign($infoLabel::BOTTOM)->setY(2)->setSize(100, 25)->setTextColor('f30')->setTranslate(true)->setText('An error occurred. Please try again later.');

		$refreshQuad = new Quad_Icons64x64_1();
		$frame->addChild($refreshQuad);
		$refreshQuad->setY(-4)->setSize(8, 8)->setSubStyle($refreshQuad::SUBSTYLE_Refresh)->setAction(self::ACTION_REFRESH_LIST);

		return $frame;
	}

	/**
	 * Build the Frame to display when no Plugins are left to install
	 *
	 * @return Frame
	 */
	private function getEmptyFrame() {
		$frame = new Frame();

		$infoLabel = new Label_Text();
		$frame->addChild($infoLabel);
		$infoLabel->setSize(100, 50)->setTextColor('0f3')->setTranslate(true)->setText('No other plugins available.');

		return $frame;
	}

	/**
	 * Check if the given Plugin can be installed without Issues
	 *
	 * @param object $plugin
	 * @return bool
	 */
	private function isPluginCompatible($plugin) {
		if ($plugin->currentVersion->min_mc_version > 0 && $plugin->currentVersion->min_mc_version > ManiaControl::VERSION) {
			// ManiaControl needs to be updated
			return false;
		}
		if ($plugin->currentVersion->max_mc_version > 0 && $plugin->currentVersion->max_mc_version < ManiaControl::VERSION) {
			// Plugin is outdated
			return false;
		}
		return true;
	}

	/**
	 * @see \ManiaControl\Configurator\ConfiguratorMenu::saveConfigData()
	 */
	public function saveConfigData(array $configData, Player $player) {
	}

	/**
	 * Handle the Refresh MLAction
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleRefreshListAction(array $actionCallback, Player $player) {
		$this->maniaControl->getConfigurator()->showMenu($player, $this);
	}
}
