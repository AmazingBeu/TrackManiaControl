<?php

namespace ManiaControl\Maps;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Entry;
use FML\Controls\Labels\Label_Text;
use FML\Controls\Quads\Quad_BgsPlayerCard;
use FML\Controls\Quads\Quad_Icons64x64_1;
use FML\Controls\Quads\Quad_UIConstruction_Buttons;
use FML\Controls\Quads\Quad_UIConstructionBullet_Buttons;
use FML\ManiaLink;
use FML\Script\Features\Paging;
use ManiaControl\Files\AsyncHttpRequest;
use ManiaControl\Files\FileUtil;
use ManiaControl\Logger;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Players\Player;
use Maniaplanet\DedicatedServer\Xmlrpc\AlreadyInListException;
use Maniaplanet\DedicatedServer\Xmlrpc\FileException;
use Maniaplanet\DedicatedServer\Xmlrpc\InvalidMapException;

/**
 * Maps Directory Browser
 *
 * @author    ManiaControl Team <mail@maniacontrol.com>
 * @copyright 2014-2020 ManiaControl Team
 * @license   http://www.gnu.org/licenses/ GNU General Public License, Version 3
 */
class DirectoryBrowser implements ManialinkPageAnswerListener {
	/*
	 * Constants
	 */
	const ACTION_SHOW          = 'MapsDirBrowser.Show';
	const ACTION_NAVIGATE_UP   = 'MapsDirBrowser.NavigateUp';
	const ACTION_NAVIGATE_ROOT = 'MapsDirBrowser.NavigateRoot';
	const ACTION_OPEN_FOLDER   = 'MapsDirBrowser.OpenFolder.';
	const ACTION_INSPECT_FILE  = 'MapsDirBrowser.InspectFile.';
	const ACTION_ADD_FILE      = 'MapsDirBrowser.AddFile.';
	const ACTION_ERASE_FILE    = 'MapsDirBrowser.EraseFile.';
	const ACTION_DOWNLOAD_FILE = 'MapsDirBrowser.DownloadFile';
	const WIDGET_NAME          = 'MapsDirBrowser.Widget';
	const CACHE_FOLDER_PATH    = 'FolderPath';

	/*
	 * Private properties
	 */
	/** @var ManiaControl $maniaControl */
	private $maniaControl = null;

	/**
	 * Construct a new directory browser instance
	 *
	 * @param ManiaControl $maniaControl
	 */
	public function __construct(ManiaControl $maniaControl) {
		$this->maniaControl = $maniaControl;

		// ManiaLink Actions
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_SHOW, $this, 'handleActionShow');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_NAVIGATE_UP, $this, 'handleNavigateUp');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACTION_NAVIGATE_ROOT, $this, 'handleNavigateRoot');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerRegexListener($this->buildActionRegex(self::ACTION_OPEN_FOLDER), $this, 'handleOpenFolder');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerRegexListener($this->buildActionRegex(self::ACTION_INSPECT_FILE), $this, 'handleInspectFile');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerRegexListener($this->buildActionRegex(self::ACTION_ADD_FILE), $this, 'handleAddFile');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerRegexListener($this->buildActionRegex(self::ACTION_ERASE_FILE), $this, 'handleEraseFile');
		$this->maniaControl->getManialinkManager()->registerManialinkPageAnswerRegexListener($this->buildActionRegex(self::ACTION_DOWNLOAD_FILE), $this, 'handleDownloadFile');
	}

	/**
	 * Build the regex to register for the given action
	 *
	 * @param string $actionName
	 * @return string
	 */
	private function buildActionRegex($actionName) {
		return '/' . $actionName . '*/';
	}

	/**
	 * Handle 'Show' action
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleActionShow(array $actionCallback, Player $player) {
		$this->showManiaLink($player);
	}

	/**
	 * Build and show the Browser ManiaLink to the given Player
	 *
	 * @param Player $player
	 * @param mixed  $nextFolder
	 */
	public function showManiaLink(Player $player, $nextFolder = null) {
		$oldFolderPath  = $player->getCache($this, self::CACHE_FOLDER_PATH);
		$isInMapsFolder = false;
		if (!$oldFolderPath) {
			$oldFolderPath  = $this->maniaControl->getServer()->getDirectory()->getMapsFolder();
			$isInMapsFolder = true;
		}
		$folderPath = $oldFolderPath;
		if (is_string($nextFolder)) {
			$newFolderPath = realpath($oldFolderPath . $nextFolder);
			if ($newFolderPath) {
				$folderPath = $newFolderPath . DIRECTORY_SEPARATOR;
				$folderName = basename($newFolderPath);
				switch ($folderName) {
					case 'Maps':
						$mapsDir        = dirname($this->maniaControl->getServer()->getDirectory()->getMapsFolder());
						$folderDir      = dirname($folderPath);
						$isInMapsFolder = ($mapsDir === $folderDir);
						break;
					case 'UserData':
						$dataDir   = dirname($this->maniaControl->getServer()->getDirectory()->getUserDataFolder());
						$folderDir = dirname($folderPath);
						if ($dataDir === $folderDir) {
							// Prevent navigation out of maps directory
							return;
						}
						break;
				}
			}
		}
		$player->setCache($this, self::CACHE_FOLDER_PATH, $folderPath);

		$maniaLink = new ManiaLink(ManialinkManager::MAIN_MLID);
		$script    = $maniaLink->getScript();
		$paging    = new Paging();
		$script->addFeature($paging);
		$frame = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultListFrame($script, $paging);
		$maniaLink->addChild($frame);

		$width     = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsWidth();
		$height    = $this->maniaControl->getManialinkManager()->getStyleManager()->getListWidgetsHeight();
		$index     = 0;
		$posY      = $height / 2 - 10;
		$pageFrame = null;
		$pageMaxCount = floor(($height * 0.78) / 4);

		$navigateRootQuad = new Quad_Icons64x64_1();
		$frame->addChild($navigateRootQuad);
		$navigateRootQuad->setPosition($width * -0.47, $height * 0.45)->setSize(4, 4)->setSubStyle($navigateRootQuad::SUBSTYLE_ToolRoot);

		$navigateUpQuad = new Quad_Icons64x64_1();
		$frame->addChild($navigateUpQuad);
		$navigateUpQuad->setPosition($width * -0.44, $height * 0.45)->setSize(4, 4)->setSubStyle($navigateUpQuad::SUBSTYLE_ToolUp);

		if (!$isInMapsFolder) {
			$navigateRootQuad->setAction(self::ACTION_NAVIGATE_ROOT);
			$navigateUpQuad->setAction(self::ACTION_NAVIGATE_UP);
		}

		$directoryLabel = new Label_Text();
		$frame->addChild($directoryLabel);
		$dataFolder    = $this->maniaControl->getServer()->getDirectory()->getUserDataFolder();
		$directoryText = substr($folderPath, strlen($dataFolder));
		$directoryLabel->setPosition($width * -0.41, $height * 0.45)->setSize($width * 0.85, 4)->setHorizontalAlign($directoryLabel::LEFT)->setText($directoryText)->setTextSize(2);

		$tooltipLabel = new Label();
		$frame->addChild($tooltipLabel);
		$tooltipLabel->setPosition($width * -0.48, $height * -0.44)->setSize($width * 0.8, 5)->setHorizontalAlign($tooltipLabel::LEFT)->setTextSize(1);

		$mapFiles = $this->scanMapFiles($folderPath);

		if (is_array($mapFiles)) {
			if (empty($mapFiles)) {
				$emptyLabel = new Label();
				$frame->addChild($emptyLabel);
				$emptyLabel->setY(20)->setTextColor('aaa')->setText('No files found.')->setTranslate(true);
			} else {
				$canAddMaps   = $this->maniaControl->getAuthenticationManager()->checkPermission($player, MapManager::SETTING_PERMISSION_ADD_MAP);
				$canEraseMaps = $this->maniaControl->getAuthenticationManager()->checkPermission($player, MapManager::SETTING_PERMISSION_ERASE_MAP);

				foreach ($mapFiles as $filePath => $fileName) {
					$shortFilePath = substr($filePath, strlen($folderPath));

					if ($index % $pageMaxCount === 0) {
						// New Page
						$pageFrame = new Frame();
						$frame->addChild($pageFrame);
						$posY = $height / 2 - 10;
						$paging->addPageControl($pageFrame);
					}

					// Map Frame
					$mapFrame = new Frame();
					$pageFrame->addChild($mapFrame);
					$mapFrame->setY($posY);

					if ($index % 2 === 0) {
						// Striped background line
						$lineQuad = new Quad_BgsPlayerCard();
						$mapFrame->addChild($lineQuad);
						$lineQuad->setZ(-1)->setSize($width, 4)->setSubStyle($lineQuad::SUBSTYLE_BgPlayerCardBig);
					}

					// File name Label
					$nameLabel = new Label_Text();
					$mapFrame->addChild($nameLabel);
					$nameLabel->setX($width * -0.48)->setSize($width * 0.79, 4)->setHorizontalAlign($nameLabel::LEFT)->setStyle($nameLabel::STYLE_TextCardRaceRank)->setTextSize(1)->setText($fileName);

					if (is_dir($filePath)) {
						// Folder
						$nameLabel->setAction(self::ACTION_OPEN_FOLDER . substr($shortFilePath, 0, -1))->addTooltipLabelFeature($tooltipLabel, 'Open folder ' . $fileName);
					} else {
						// File
						$nameLabel->setAction(self::ACTION_INSPECT_FILE . $fileName)->addTooltipLabelFeature($tooltipLabel, 'Inspect file ' . $fileName);

						if ($canAddMaps) {
							// 'Add' button
							$addButton = new Quad_UIConstructionBullet_Buttons();
							$mapFrame->addChild($addButton);
							$addButton->setX($width * 0.42)->setSize(4, 4)->setSubStyle($addButton::SUBSTYLE_NewBullet)->setAction(self::ACTION_ADD_FILE . $fileName)->addTooltipLabelFeature($tooltipLabel, 'Add map ' . $fileName);
						}

						if ($canEraseMaps) {
							// 'Erase' button
							$eraseButton = new Quad_UIConstruction_Buttons();
							$mapFrame->addChild($eraseButton);
							$eraseButton->setX($width * 0.46)->setSize(4, 4)->setSubStyle($eraseButton::SUBSTYLE_Erase)->setAction(self::ACTION_ERASE_FILE . $fileName)->addTooltipLabelFeature($tooltipLabel, 'Erase file ' . $fileName);
						}
					}

					$posY -= 4;
					$index++;
				}
			}

			$downloadPosX = -$width / 2 + 5;

			$label = new Label_Text();
			$frame->addChild($label);
			$label->setPosition($downloadPosX, $height * -0.39);
			$label->setHorizontalAlign($label::LEFT);
			$label->setTextSize(1);
			$label->setText('Download from URL: ');

			$downloadPosX += 30;
	
			$entry = new Entry();
			$frame->addChild($entry);
			$entry->setStyle(Label_Text::STYLE_TextValueSmall);
			$entry->setHorizontalAlign($entry::LEFT);
			$entry->setPosition($downloadPosX, $height * -0.39);
			$entry->setTextSize(1);
			$entry->setSize($width * 0.35, 4);
			$entry->setName("Value");

			$downloadPosX += $width * 0.35 + 10;

			//Search for Map-Name
			$mapNameButton = $this->maniaControl->getManialinkManager()->getElementBuilder()->buildRoundTextButton(
				'Download',
				18,
				5,
				self::ACTION_DOWNLOAD_FILE
			);
			$frame->addChild($mapNameButton);
			$mapNameButton->setPosition($downloadPosX, $height * -0.39);

		} else {
			$errorLabel = new Label();
			$frame->addChild($errorLabel);
			$errorLabel->setY(20)->setTextColor('f30')->setText('No access to the directory.')->setTranslate(true);
		}

		$this->maniaControl->getManialinkManager()->displayWidget($maniaLink, $player, self::WIDGET_NAME);
	}

	/**
	 * Scan the given directory for Map files
	 *
	 * @param string $directory
	 * @return array|bool
	 */
	protected function scanMapFiles($directory) {
		if (!is_readable($directory) || !is_dir($directory)) {
			return false;
		}
		$mapFiles = array();
		$dirFiles = scandir($directory, SCANDIR_SORT_NONE);
		natcasesort($dirFiles);
		foreach ($dirFiles as $fileName) {
			if (FileUtil::isHiddenFile($fileName)) {
				continue;
			}
			$fullFileName = $directory . $fileName;
			if (!is_readable($fullFileName)) {
				continue;
			}
			if (is_dir($fullFileName)) {
				$mapFiles[$fullFileName . DIRECTORY_SEPARATOR] = $fileName . DIRECTORY_SEPARATOR;
				continue;
			} else {
				if ($this->isMapFileName($fileName)) {
					$mapFiles[$fullFileName] = $fileName;
				}
			}
		}
		return $mapFiles;
	}

	/**
	 * Check if the given file name represents a Map file
	 *
	 * @param string $fileName
	 * @return bool
	 */
	protected function isMapFileName($fileName) {
		$mapFileNameEnding = '.map.gbx';
		$fileNameEnding    = strtolower(substr($fileName, -strlen($mapFileNameEnding)));
		return ($fileNameEnding === $mapFileNameEnding);
	}

	/**
	 * Handle 'NavigateRoot' action
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleNavigateRoot(array $actionCallback, Player $player) {
		$player->destroyCache($this, self::CACHE_FOLDER_PATH);
		$this->showManiaLink($player);
	}

	/**
	 * Handle 'NavigateUp' action
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleNavigateUp(array $actionCallback, Player $player) {
		$this->showManiaLink($player, '..');
	}

	/**
	 * Handle 'OpenFolder' page action
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleOpenFolder(array $actionCallback, Player $player) {
		$actionName = $actionCallback[1][2];
		$folderName = substr($actionName, strlen(self::ACTION_OPEN_FOLDER));
		$this->showManiaLink($player, $folderName);
	}

	/**
	 * Handle 'InspectFile' page action
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleInspectFile(array $actionCallback, Player $player) {
		$actionName = $actionCallback[1][2];
		$fileName   = substr($actionName, strlen(self::ACTION_INSPECT_FILE));
		// TODO: show inspect file view
		var_dump($fileName);
	}

	/**
	 * Handle 'AddFile' page action
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleAddFile(array $actionCallback, Player $player) {
		$actionName = $actionCallback[1][2];
		$fileName   = substr($actionName, strlen(self::ACTION_ADD_FILE));
		$folderPath = $player->getCache($this, self::CACHE_FOLDER_PATH);
		$filePath   = $folderPath . $fileName;

		$mapsFolder       = $this->maniaControl->getServer()->getDirectory()->getMapsFolder();
		$relativeFilePath = substr($filePath, strlen($mapsFolder));

		// Check for valid map
		try {
			$this->maniaControl->getClient()->checkMapForCurrentServerParams($relativeFilePath);
		} catch (InvalidMapException $exception) {
			$this->maniaControl->getChat()->sendException($exception, $player);
			return;
		} catch (FileException $exception) {
			$this->maniaControl->getChat()->sendException($exception, $player);
			return;
		}

		// Add map to map list
		try {
			$this->maniaControl->getClient()->insertMap($relativeFilePath);
		} catch (AlreadyInListException $exception) {
			$this->maniaControl->getChat()->sendException($exception, $player);
			return;
		}
		$map = $this->maniaControl->getMapManager()->fetchMapByFileName($relativeFilePath);
		if (!$map) {
			$this->maniaControl->getChat()->sendError('Error occurred.', $player);
			return;
		}

		//Update MX Data and ID
		$this->maniaControl->getMapManager()->getMXManager()->fetchManiaExchangeMapInformation($map);

		$map->lastUpdate = time();
		//Update Map Timestamp in Database
		$this->maniaControl->getMapManager()->updateMapTimestamp($map->uid);

		// Message
		$message = $this->maniaControl->getChat()->formatMessage(
			'%s added %s!',
			$player,
			$map
		);
		$this->maniaControl->getChat()->sendSuccess($message);
		Logger::logInfo($message, true);

		// Queue requested Map
		$this->maniaControl->getMapManager()->getMapQueue()->addMapToMapQueue($player, $map);
	}

	/**
	 * Handle 'EraseFile' page action
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleEraseFile(array $actionCallback, Player $player) {
		$actionName = $actionCallback[1][2];
		$fileName   = substr($actionName, strlen(self::ACTION_ERASE_FILE));
		$folderPath = $player->getCache($this, self::CACHE_FOLDER_PATH);
		$filePath   = $folderPath . $fileName;
		if (@unlink($filePath)) {
			$message = $this->maniaControl->getChat()->formatMessage(
				'Erased %s!',
				$fileName
			);
			$this->maniaControl->getChat()->sendSuccess($message, $player);
			$this->showManiaLink($player);
		} else {
			$message = $this->maniaControl->getChat()->formatMessage(
				'Could not erase %s!',
				$fileName
			);
			$this->maniaControl->getChat()->sendError($message, $player);
		}
	}



	/**
	 * Handle 'handleDownloadFile' page action
	 *
	 * @param array  $actionCallback
	 * @param Player $player
	 */
	public function handleDownloadFile(array $actionCallback, Player $player) {
		$url = trim($actionCallback[1][3][0]["Value"]);
		$folderPath = $player->getCache($this, self::CACHE_FOLDER_PATH);
		if (filter_var($url, FILTER_VALIDATE_URL)) {
			
			$asyncHttpRequest = new AsyncHttpRequest($this->maniaControl, $url);
			$asyncHttpRequest->setCallable(function ($file, $error, $headers) use ($url, $folderPath, $player) {
				if (!$file || $error) {
					$message = "Impossible to download the file: " .  $error;
					$this->maniaControl->getChat()->sendError($message, $player);
					Logger::logError($message);
					return;
				}
				$filePath = "";
				
				$contentdispositionheader = "";
				foreach ($headers as $key => $value) {
					if (strtolower($key) === "content-disposition") {
						$contentdispositionheader = urldecode($value);
						break;
					}
				}

				if ($contentdispositionheader !== "") {
					$value = $contentdispositionheader;

					if (strpos($value, ';') !== false) {
						
						list($type, $attr_parts) = explode(';', $value, 2);
				
						$attr_parts = explode(';', $attr_parts);
						$attributes = array();
				
						foreach ($attr_parts as $part) {
							if (strpos($part, '=') === false) {
								continue;
							}
				
							list($key, $value) = explode('=', $part, 2);
				
							$attributes[trim($key)] = trim($value);
						}
				
						$attrNames = ['filename*' => true, 'filename' => false];
						$filename = null;
						$isUtf8 = false;
						foreach ($attrNames as $attrName => $utf8) {
							if (!empty($attributes[$attrName])) {
								$filename = trim($attributes[$attrName]);
								$isUtf8 = $utf8;
								break;
							}
						}

						if ($filename !== null) {
							if ($isUtf8 && strpos($filename, "utf-8''") === 0 && $filename = substr($filename, strlen("utf-8''"))) {
								$filePath = $folderPath . FileUtil::getClearedFileName(rawurldecode($filename));
							}
							if (substr($filename, 0, 1) === '"' && substr($filename, -1, 1) === '"') {
								$filePath = $folderPath . substr($filename, 1, -1);
							} else {
								$filePath = $folderPath . $filename;
							}
						}
					}

					if (!$this->isMapFileName($filePath)) {
						$message = "File is not a map: " . $filename;
						$this->maniaControl->getChat()->sendError($message, $player);
						Logger::logError($message);
						return;
					}
				} else {
					$path = parse_url($url, PHP_URL_PATH);

					// extracted basename
					$filePath = $folderPath . basename($path);

					if (!$this->isMapFileName($filePath)) {
						$filePath .= ".Map.Gbx";
					}
				}

				if ($filePath != "") {
					if (file_exists($filePath)) {
						$index = 1;
						while (file_exists(substr($filePath, 0, -8) . "-" . $index . ".Map.Gbx")) {
							$index++;
						}
						$filePath = substr($filePath, 0, -8) . "-" . $index . ".Map.Gbx";
					}
					$bytes = file_put_contents($filePath, $file);
					if (!$bytes || $bytes <= 0) {
						$message = "Impossible to determine filename";
						$this->maniaControl->getChat()->sendError($message, $player);
						Logger::logError($message);
						return;
					}
				}
				$this->showManiaLink($player, $folderPath);
			});

			$asyncHttpRequest->getData();
		}
	}
}
