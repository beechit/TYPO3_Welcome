<?php
namespace TYPO3\CMS\Install\Updates;

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

/**
 * Migrate backend shorcut urls
 *
 * @author Wouter Wolters <typo3@wouterwolters.nl>
 */
class MigrateShortcutUrlsUpdate extends AbstractUpdate {

	/**
	 * @var string
	 */
	protected $title = 'Migrate backend shortcut urls';

	/**
	 * Checks if an update is needed
	 *
	 * @param string &$description The description for the update
	 * @return bool Whether an update is needed (TRUE) or not (FALSE)
	 */
	public function checkForUpdate(&$description) {
		$shortcutsCount = $this->getDatabaseConnection()->exec_SELECTcountRows('uid', 'sys_be_shortcuts');
		if ($this->isWizardDone() || $shortcutsCount === 0) {
			return FALSE;
		}

		$description = 'Migrate old shorcut urls to the new module urls.';

		return TRUE;
	}

	/**
	 * Performs the database update if shorcuts are available
	 *
	 * @param array &$databaseQueries Queries done in this update
	 * @param mixed &$customMessages Custom messages
	 * @return bool
	 */
	public function performUpdate(array &$databaseQueries, &$customMessages) {
		$db = $this->getDatabaseConnection();
		$shortcuts = $db->exec_SELECTgetRows('uid,url', 'sys_be_shortcuts', '1=1');
		if (!empty($shortcuts)) {
			foreach ($shortcuts as $shortcut) {
				$decodedUrl = urldecode($shortcut['url']);
				$encodedUrl = str_replace(
					array(
						'/typo3/sysext/cms/layout/db_layout.php?&',
						'/typo3/sysext/cms/layout/db_layout.php?',
						'/typo3/file_edit.php?&',
					),
					array(
						'/typo3/mod.php?&M=web_layout&',
						urlencode('/typo3/mod.php?&M=web_layout&'),
						'/typo3/mod.php?&M=file_edit&',
					),
					$decodedUrl
				);

				$db->exec_UPDATEquery(
					'sys_be_shortcuts',
					'uid=' . (int)$shortcut['uid'],
					array(
						'url' => $encodedUrl,
					)
				);
				$databaseQueries[] = $db->debug_lastBuiltQuery;
			}
		}

		$this->markWizardAsDone();
		return TRUE;
	}

}
