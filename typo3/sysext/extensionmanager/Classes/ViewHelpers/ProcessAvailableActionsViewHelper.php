<?php
namespace TYPO3\CMS\Extensionmanager\ViewHelpers;

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
 * View helper to let 3rd-party extensions process the list of available
 * actions for a given extension.
 *
 * @author Xavier Perseguers <xavier@typo3.org>
 * @internal
 */
class ProcessAvailableActionsViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\Link\ActionViewHelper {

	const SIGNAL_ProcessActions = 'processActions';

	/**
	 * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 * @inject
	 */
	protected $signalSlotDispatcher;

	/**
	 * Processes the list of actions.
	 *
	 * @param string $extension
	 * @return string the rendered list of actions
	 */
	public function render($extension) {
		$html = $this->renderChildren();
		$actions = preg_split('#\\n\\s*#s', trim($html));

		$actions = $this->emitProcessActionsSignal($extension, $actions);

		return implode(' ', $actions);
	}

	/**
	 * Emits a signal after the list of actions is processed
	 *
	 * @param string $extension
	 * @param array $actions
	 * @return array Modified action array
	 */
	protected function emitProcessActionsSignal($extension, array $actions) {
		$this->signalSlotDispatcher->dispatch(
			__CLASS__,
			static::SIGNAL_ProcessActions,
			array(
				$extension,
				&$actions,
			)
		);
		return $actions;
	}

}
