<?php
namespace TYPO3\CMS\Taskcenter\Controller;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class provides a taskcenter for BE users
 *
 * @author Georg Ringer <typo3@ringerge.org>
 */
class TaskModuleController extends \TYPO3\CMS\Backend\Module\BaseScriptClass {

	/**
	 * @var array
	 */
	protected $pageinfo;

	/**
	 * The name of the module
	 *
	 * @var string
	 */
	protected $moduleName = 'user_task';

	/**
	 * Initializes the Module
	 *
	 * @return void
	 */
	public function __construct() {
		$GLOBALS['LANG']->includeLLFile('EXT:taskcenter/task/locallang.xlf');
		$this->MCONF = array(
			'name' => $this->moduleName
		);
		parent::init();
		// Initialize document
		$this->doc = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
		$this->doc->setModuleTemplate(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('taskcenter') . 'Resources/Private/Templates/mod_template.html');
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->getPageRenderer()->loadJquery();
		$this->doc->addStyleSheet('tx_taskcenter', '../' . \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::siteRelPath('taskcenter') . 'Resources/Public/Styles/styles.css');
	}

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return void
	 */
	public function menuConfig() {
		$this->MOD_MENU = array('mode' => array());
		$this->MOD_MENU['mode']['information'] = $GLOBALS['LANG']->sL('LLL:EXT:taskcenter/locallang.xlf:task_overview');
		$this->MOD_MENU['mode']['tasks'] = 'Tasks';
		parent::menuConfig();
	}

	/**
	 * Creates the module's content. In this case it rather acts as a kind of #
	 * dispatcher redirecting requests to specific tasks.
	 *
	 * @return void
	 */
	public function main() {
		$docHeaderButtons = $this->getButtons();
		$markers = array();
		$this->doc->postCode = $this->doc->wrapScriptTags('if (top.fsMod) { top.fsMod.recentIds["web"] = 0; }');

		// Render content depending on the mode
		$mode = (string)$this->MOD_SETTINGS['mode'];
		if ($mode == 'information') {
			$this->renderInformationContent();
		} else {
			$this->renderModuleContent();
		}
		// Compile document
		$markers['FUNC_MENU'] = \TYPO3\CMS\Backend\Utility\BackendUtility::getFuncMenu(0, 'SET[mode]', $this->MOD_SETTINGS['mode'], $this->MOD_MENU['mode']);
		$markers['CONTENT'] = $this->content;
		// Build the <body> for the module
		$this->content = $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		// Renders the module page
		$this->content = $this->doc->render($GLOBALS['LANG']->getLL('title'), $this->content);
	}

	/**
	 * Prints out the module's HTML
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

	/**
	 * Generates the module content by calling the selected task
	 *
	 * @return void
	 */
	protected function renderModuleContent() {
		$title = ($content = ($actionContent = ''));
		$chosenTask = (string)$this->MOD_SETTINGS['function'];
		// Render the taskcenter task as default
		if (empty($chosenTask) || $chosenTask == 'index') {
			$chosenTask = 'taskcenter.tasks';
		}
		// Render the task
		list($extKey, $taskClass) = explode('.', $chosenTask, 2);
		$title = $GLOBALS['LANG']->sL($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter'][$extKey][$taskClass]['title']);
		if (class_exists($taskClass)) {
			$taskInstance = GeneralUtility::makeInstance($taskClass, $this);
			if ($taskInstance instanceof \TYPO3\CMS\Taskcenter\TaskInterface) {
				// Check if the task is restricted to admins only
				if ($this->checkAccess($extKey, $taskClass)) {
					$actionContent .= $taskInstance->getTask();
				} else {
					$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('error-access', TRUE), $GLOBALS['LANG']->getLL('error_header'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
					$actionContent .= $flashMessage->render();
				}
			} else {
				// Error if the task is not an instance of \TYPO3\CMS\Taskcenter\TaskInterface
				$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, sprintf($GLOBALS['LANG']->getLL('error_no-instance', TRUE), $taskClass, \TYPO3\CMS\Taskcenter\TaskInterface::class), $GLOBALS['LANG']->getLL('error_header'), \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR);
				$actionContent .= $flashMessage->render();
			}
		} else {
			$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->sL('LLL:EXT:taskcenter/task/locallang_mod.xlf:mlang_labels_tabdescr'), $GLOBALS['LANG']->sL('LLL:EXT:taskcenter/task/locallang_mod.xlf:mlang_tabs_tab'), \TYPO3\CMS\Core\Messaging\FlashMessage::INFO);
			$actionContent .= $flashMessage->render();
		}
		$content = '<div id="taskcenter-main">
						<div id="taskcenter-menu">' . $this->indexAction() . '</div>
						<div id="taskcenter-item" class="' . htmlspecialchars(($extKey . '-' . $taskClass)) . '">' . $actionContent . '
						</div>
					</div>';
		$this->content .= $content;
	}

	/**
	 * Generates the information content
	 *
	 * @return void
	 */
	protected function renderInformationContent() {
		$content = $this->description($GLOBALS['LANG']->getLL('mlang_tabs_tab'), $GLOBALS['LANG']->sL('LLL:EXT:taskcenter/task/locallang_mod.xlf:mlang_labels_tabdescr'));
		$content .= $GLOBALS['LANG']->getLL('taskcenter-about');
		if ($GLOBALS['BE_USER']->isAdmin()) {
			$content .= '<br /><br />' . $this->description($GLOBALS['LANG']->getLL('taskcenter-adminheader'), $GLOBALS['LANG']->getLL('taskcenter-admin'));
		}
		$this->content .= $content;
	}

	/**
	 * Render the headline of a task including a title and an optional description.
	 *
	 * @param string $title Title
	 * @param string $description Description
	 * @return string formatted title and description
	 */
	public function description($title, $description = '') {
		$content = '<h1>' . nl2br(htmlspecialchars($title)) . '</h1>';
		if (!empty($description)) {
			$content .= '<p class="description">' . nl2br(htmlspecialchars($description)) . '</p>';
		}
		return $content;
	}

	/**
	 * Render a list of items as a nicely formated definition list including a
	 * link, icon, title and description.
	 * The keys of a single item are:
	 * - title:				Title of the item
	 * - link:					Link to the task
	 * - icon: 				Path to the icon or Icon as HTML if it begins with <img
	 * - description:	Description of the task, using htmlspecialchars()
	 * - descriptionHtml:	Description allowing HTML tags which will override the
	 * description
	 *
	 * @param array $items List of items to be displayed in the definition list.
	 * @param bool $mainMenu Set it to TRUE to render the main menu
	 * @return string Fefinition list
	 */
	public function renderListMenu($items, $mainMenu = FALSE) {
		$content = ($section = '');
		$count = 0;
		// Change the sorting of items to the user's one
		if ($mainMenu) {
			$this->doc->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Taskcenter/Taskcenter');
			$userSorting = unserialize($GLOBALS['BE_USER']->uc['taskcenter']['sorting']);
			if (is_array($userSorting)) {
				$newSorting = array();
				foreach ($userSorting as $item) {
					if (isset($items[$item])) {
						$newSorting[] = $items[$item];
						unset($items[$item]);
					}
				}
				$items = $newSorting + $items;
			}
		}
		if (is_array($items) && count($items) > 0) {
			foreach ($items as $item) {
				$title = htmlspecialchars($item['title']);
				$icon = ($additionalClass = ($collapsedStyle = ''));
				// Check for custom icon
				if (!empty($item['icon'])) {
					if (strpos($item['icon'], '<img ') === FALSE) {
						$absIconPath = GeneralUtility::getFileAbsFilename($item['icon']);
						// If the file indeed exists, assemble relative path to it
						if (file_exists($absIconPath)) {
							$icon = $GLOBALS['BACK_PATH'] . '../' . str_replace(PATH_site, '', $absIconPath);
							$icon = '<img src="' . $icon . '" title="' . $title . '" alt="' . $title . '" />';
						}
						if (@is_file($icon)) {
							$icon = '<img' . \TYPO3\CMS\Backend\Utility\IconUtility::skinImg($GLOBALS['BACK_PATH'], $icon, 'width="16" height="16"') . ' title="' . $title . '" alt="' . $title . '" />';
						}
					} else {
						$icon = $item['icon'];
					}
				}
				$description = $item['descriptionHtml'] ?: '<p>' . nl2br(htmlspecialchars($item['description'])) . '</p>';
				$id = $this->getUniqueKey($item['uid']);
				// Collapsed & expanded menu items
				if ($mainMenu && isset($GLOBALS['BE_USER']->uc['taskcenter']['states'][$id]) && $GLOBALS['BE_USER']->uc['taskcenter']['states'][$id]) {
					$collapsedStyle = 'style="display:none"';
					$additionalClass = 'collapsed';
				} else {
					$additionalClass = 'expanded';
				}
				// First & last menu item
				if ($count == 0) {
					$additionalClass .= ' first-item';
				} elseif ($count + 1 === count($items)) {
					$additionalClass .= ' last-item';
				}
				// Active menu item
				$active = (string)$this->MOD_SETTINGS['function'] == $item['uid'] ? ' active-task' : '';
				// Main menu: Render additional syntax to sort tasks
				if ($mainMenu) {
					$dragIcon = \TYPO3\CMS\Backend\Utility\IconUtility::getSpriteIcon('actions-document-move', array('title' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.move', TRUE)));
					$section = '<div class="down">&nbsp;</div>
								<div class="drag">' . $dragIcon . '</div>';
					$backgroundClass = 't3-row-header ';
				}
				$content .= '<li class="' . $additionalClass . $active . '" id="el_' . $id . '">
								' . $section . '
								<div class="image">' . $icon . '</div>
								<div class="' . $backgroundClass . 'link"><a href="' . $item['link'] . '">' . $title . '</a></div>
								<div class="content " ' . $collapsedStyle . '>' . $description . '</div>
							</li>';
				$count++;
			}
			$navigationId = $mainMenu ? 'id="task-list"' : '';
			$content = '<ul ' . $navigationId . ' class="task-list">' . $content . '</ul>';
		}
		return $content;
	}

	/**
	 * Shows an overview list of available reports.
	 *
	 * @return string List of available reports
	 */
	protected function indexAction() {
		$content = '';
		$tasks = array();
		$icon = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('taskcenter') . 'task/task.gif';
		// Render the tasks only if there are any available
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter']) && count($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter']) > 0) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter'] as $extKey => $extensionReports) {
				foreach ($extensionReports as $taskClass => $task) {
					if (!$this->checkAccess($extKey, $taskClass)) {
						continue;
					}
					$link = BackendUtility::getModuleUrl('user_task') . '&SET[function]=' . $extKey . '.' . $taskClass;
					$taskTitle = $GLOBALS['LANG']->sL($task['title']);
					$taskDescriptionHtml = '';
					// Check for custom icon
					if (!empty($task['icon'])) {
						$icon = GeneralUtility::getFileAbsFilename($task['icon']);
					}
					if (class_exists($taskClass)) {
						$taskInstance = GeneralUtility::makeInstance($taskClass, $this);
						if ($taskInstance instanceof \TYPO3\CMS\Taskcenter\TaskInterface) {
							$taskDescriptionHtml = $taskInstance->getOverview();
						}
					}
					// Generate an array of all tasks
					$uniqueKey = $this->getUniqueKey($extKey . '.' . $taskClass);
					$tasks[$uniqueKey] = array(
						'title' => $taskTitle,
						'descriptionHtml' => $taskDescriptionHtml,
						'description' => $GLOBALS['LANG']->sL($task['description']),
						'icon' => $icon,
						'link' => $link,
						'uid' => $extKey . '.' . $taskClass
					);
				}
			}
			$content .= $this->renderListMenu($tasks, TRUE);
		} else {
			$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, $GLOBALS['LANG']->getLL('no-tasks', TRUE), '', \TYPO3\CMS\Core\Messaging\FlashMessage::INFO);
			$this->content .= $flashMessage->render();
		}
		return $content;
	}

	/**
	 * Create the panel of buttons for submitting the form or otherwise
	 * perform operations.
	 *
	 * @return array All available buttons as an assoc. array
	 */
	protected function getButtons() {
		$buttons = array(
			'csh' => \TYPO3\CMS\Backend\Utility\BackendUtility::cshItem('_MOD_web_func', ''),
			'shortcut' => '',
			'open_new_window' => $this->openInNewWindow()
		);
		// Shortcut
		if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
			$buttons['shortcut'] = $this->doc->makeShortcutIcon('', 'function', $this->moduleName);
		}
		return $buttons;
	}

	/**
	 * Check the access to a task. Considered are:
	 * - Admins are always allowed
	 * - Tasks can be restriced to admins only
	 * - Tasks can be blinded for Users with TsConfig taskcenter.<extensionkey>.<taskName> = 0
	 *
	 * @param string $extKey Extension key
	 * @param string $taskClass Name of the task
	 * @return bool Access to the task allowed or not
	 */
	protected function checkAccess($extKey, $taskClass) {
		// Check if task is blinded with TsConfig (taskcenter.<extkey>.<taskName>
		$tsConfig = $GLOBALS['BE_USER']->getTSConfig('taskcenter.' . $extKey . '.' . $taskClass);
		if (isset($tsConfig['value']) && (int)$tsConfig['value'] === 0) {
			return FALSE;
		}
		// Admins are always allowed
		if ($GLOBALS['BE_USER']->isAdmin()) {
			return TRUE;
		}
		// Check if task is restricted to admins
		if ((int)$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['taskcenter'][$extKey][$taskClass]['admin'] === 1) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * Returns HTML code to dislay an url in an iframe at the right side of the taskcenter
	 *
	 * @param string $url Url to display
	 * @param int $max
	 * @return string Code that inserts the iframe (HTML)
	 */
	public function urlInIframe($url, $max = 0) {
		return '<iframe scrolling="auto"  width="100%" src="' . $url . '" name="list_frame" id="list_frame" frameborder="no"></iframe>';
	}

	/**
	 * Create a unique key from a string which can be used in Prototype's Sortable
	 * Therefore '_' are replaced
	 *
	 * @param string $string string which is used to generate the identifier
	 * @return string Modified string
	 */
	protected function getUniqueKey($string) {
		$search = array('.', '_');
		$replace = array('-', '');
		return str_replace($search, $replace, $string);
	}

	/**
	 * This method prepares the link for opening the devlog in a new window
	 *
	 * @return string Hyperlink with icon and appropriate JavaScript
	 */
	protected function openInNewWindow() {
		$url = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
		$onClick = 'devlogWin=window.open(\'' . $url . '\',\'taskcenter\',\'width=790,status=0,menubar=1,resizable=1,location=0,scrollbars=1,toolbar=0\');return false;';
		$content = '<a href="#" onclick="' . htmlspecialchars($onClick) . '">' .
			IconUtility::getSpriteIcon('actions-window-open', array('title' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.openInNewWindow', TRUE))) .
		'</a>';
		return $content;
	}

}
