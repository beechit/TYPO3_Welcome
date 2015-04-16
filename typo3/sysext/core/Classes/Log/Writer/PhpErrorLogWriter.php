<?php
namespace TYPO3\CMS\Core\Log\Writer;

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
 * Log writer that writes the log records into PHP error log.
 *
 * @author Steffen Gebert <steffen.gebert@typo3.org>
 * @author Steffen Müller <typo3@t3node.com>
 */
class PhpErrorLogWriter extends AbstractWriter {

	/**
	 * Writes the log record
	 *
	 * @param \TYPO3\CMS\Core\Log\LogRecord $record Log record
	 * @return \TYPO3\CMS\Core\Log\Writer\WriterInterface $this
	 * @throws \RuntimeException
	 */
	public function writeLog(\TYPO3\CMS\Core\Log\LogRecord $record) {
		$levelName = \TYPO3\CMS\Core\Log\LogLevel::getName($record->getLevel());
		$data = $record->getData();
		$data = !empty($data) ? '- ' . json_encode($data) : '';
		$message = sprintf('TYPO3 [%s] request="%s" component="%s": %s %s', $levelName, $record->getRequestId(), $record->getComponent(), $record->getMessage(), $data);
		if (FALSE === error_log($message)) {
			throw new \RuntimeException('Could not write log record to PHP error log', 1345036336);
		}
		return $this;
	}

}
