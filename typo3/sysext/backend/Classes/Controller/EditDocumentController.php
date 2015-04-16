<?php
namespace TYPO3\CMS\Backend\Controller;

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

use TYPO3\CMS\Backend\Form\FormEngine;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Utility\IconUtility;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Script Class: Drawing the editing form for editing records in TYPO3.
 * Notice: It does NOT use tce_db.php to submit data to, rather it handles submissions itself
 *
 * @author Kasper Skårhøj <kasperYYYY@typo3.com>
 */
class EditDocumentController {

	/**
	 * GPvar "edit": Is an array looking approx like [tablename][list-of-ids]=command, eg.
	 * "&edit[pages][123]=edit". See \TYPO3\CMS\Backend\Utility\BackendUtility::editOnClick(). Value can be seen modified
	 * internally (converting NEW keyword to id, workspace/versioning etc).
	 *
	 * @var array
	 */
	public $editconf;

	/**
	 * Commalist of fieldnames to edit. The point is IF you specify this list, only those
	 * fields will be rendered in the form. Otherwise all (available) fields in the record
	 * is shown according to the types configuration in $GLOBALS['TCA']
	 *
	 * @var bool
	 */
	public $columnsOnly;

	/**
	 * Default values for fields (array with tablenames, fields etc. as keys).
	 * Can be seen modified internally.
	 *
	 * @var array
	 */
	public $defVals;

	/**
	 * Array of values to force being set (as hidden fields). Will be set as $this->defVals
	 * IF defVals does not exist.
	 *
	 * @var array
	 */
	public $overrideVals;

	/**
	 * If set, this value will be set in $this->retUrl (which is used quite many places
	 * as the return URL). If not set, "dummy.php" will be set in $this->retUrl
	 *
	 * @var string
	 */
	public $returnUrl;

	/**
	 * Close-document command. Not really sure of all options...
	 *
	 * @var int
	 */
	public $closeDoc;

	/**
	 * Quite simply, if this variable is set, then the processing of incoming data will be performed
	 * as if a save-button is pressed. Used in the forms as a hidden field which can be set through
	 * JavaScript if the form is somehow submitted by JavaScript).
	 *
	 * @var bool
	 */
	public $doSave;

	/**
	 * The data array from which the data comes...
	 *
	 * @var array
	 */
	public $data;

	/**
	 * @var array
	 */
	public $mirror;

	/**
	 * Clear-cache cmd.
	 *
	 * @var string
	 */
	public $cacheCmd;

	/**
	 * Redirect (not used???)
	 *
	 * @var string
	 */
	public $redirect;

	/**
	 * Boolean: If set, then the GET var "&id=" will be added to the
	 * retUrl string so that the NEW id of something is returned to the script calling the form.
	 *
	 * @var bool
	 */
	public $returnNewPageId;

	/**
	 * @var string
	 */
	public $vC;

	/**
	 * update BE_USER->uc
	 *
	 * @var array
	 */
	public $uc;

	/**
	 * ID for displaying the page in the frontend (used for SAVE/VIEW operations)
	 *
	 * @var int
	 */
	public $popViewId;

	/**
	 * Additional GET vars for the link, eg. "&L=xxx"
	 *
	 * @var string
	 */
	public $popViewId_addParams;

	/**
	 * Alternative URL for viewing the frontend pages.
	 *
	 * @var string
	 */
	public $viewUrl;

	/**
	 * If this is pointing to a page id it will automatically load all content elements
	 * (NORMAL column/default language) from that page into the form!
	 *
	 * @var int
	 */
	public $editRegularContentFromId;

	/**
	 * Alternative title for the document handler.
	 *
	 * @var string
	 */
	public $recTitle;

	/**
	 * Disable help... ?
	 *
	 * @var bool
	 */
	public $disHelp;

	/**
	 * If set, then no SAVE/VIEW button is printed
	 *
	 * @var bool
	 */
	public $noView;

	/**
	 * If set, the $this->editconf array is returned to the calling script
	 * (used by wizard_add.php for instance)
	 *
	 * @var bool
	 */
	public $returnEditConf;

	/**
	 * localization mode for TCEforms (eg. "text")
	 *
	 * @var string
	 */
	public $localizationMode;

	/**
	 * Workspace used for the editing action.
	 *
	 * @var NULL|integer
	 */
	protected $workspace;

	/**
	 * document template object
	 *
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * a static HTML template, usually in templates/alt_doc.html
	 *
	 * @var string
	 */
	public $template;

	/**
	 * Content accumulation
	 *
	 * @var string
	 */
	public $content;

	/**
	 * Return URL script, processed. This contains the script (if any) that we should
	 * RETURN TO from the alt_doc.php script IF we press the close button. Thus this
	 * variable is normally passed along from the calling script so we can properly return if needed.
	 *
	 * @var string
	 */
	public $retUrl;

	/**
	 * Contains the parts of the REQUEST_URI (current url). By parts we mean the result of resolving
	 * REQUEST_URI (current url) by the parse_url() function. The result is an array where eg. "path"
	 * is the script path and "query" is the parameters...
	 *
	 * @var array
	 */
	public $R_URL_parts;

	/**
	 * Contains the current GET vars array; More specifically this array is the foundation for creating
	 * the R_URI internal var (which becomes the "url of this script" to which we submit the forms etc.)
	 *
	 * @var array
	 */
	public $R_URL_getvars;

	/**
	 * Set to the URL of this script including variables which is needed to re-display the form. See main()
	 *
	 * @var string
	 */
	public $R_URI;

	/**
	 * Is loaded with the "title" of the currently "open document" - this is used in the
	 * Document Selector box. (see makeDocSel())
	 *
	 * @var string
	 */
	public $storeTitle;

	/**
	 * Contains an array with key/value pairs of GET parameters needed to reach the
	 * current document displayed - used in the Document Selector box. (see compileStoreDat())
	 *
	 * @var array
	 */
	public $storeArray;

	/**
	 * Contains storeArray, but imploded into a GET parameter string (see compileStoreDat())
	 *
	 * @var string
	 */
	public $storeUrl;

	/**
	 * Hashed value of storeURL (see compileStoreDat())
	 *
	 * @var string
	 */
	public $storeUrlMd5;

	/**
	 * Module session data
	 *
	 * @var array
	 */
	public $docDat;

	/**
	 * An array of the "open documents" - keys are md5 hashes (see $storeUrlMd5) identifying
	 * the various documents on the GET parameter list needed to open it. The values are
	 * arrays with 0,1,2 keys with information about the document (see compileStoreDat()).
	 * The docHandler variable is stored in the $docDat session data, key "0".
	 *
	 * @var array
	 */
	public $docHandler;

	/**
	 * Array of the elements to create edit forms for.
	 *
	 * @var array
	 */
	public $elementsData;

	/**
	 * Pointer to the first element in $elementsData
	 *
	 * @var array
	 */
	public $firstEl;

	/**
	 * Counter, used to count the number of errors (when users do not have edit permissions)
	 *
	 * @var int
	 */
	public $errorC;

	/**
	 * Counter, used to count the number of new record forms displayed
	 *
	 * @var int
	 */
	public $newC;

	/**
	 * Is set to the pid value of the last shown record - thus indicating which page to
	 * show when clicking the SAVE/VIEW button
	 *
	 * @var int
	 */
	public $viewId;

	/**
	 * Is set to additional parameters (like "&L=xxx") if the record supports it.
	 *
	 * @var string
	 */
	public $viewId_addParams;

	/**
	 * Module TSconfig, loaded from main() based on the page id value of viewId
	 *
	 * @var array
	 */
	public $modTSconfig;

	/**
	 * instance of TCEforms class
	 *
	 * @var \TYPO3\CMS\Backend\Form\FormEngine
	 */
	public $tceforms;

	/**
	 * Contains the root-line path of the currently edited record(s) - for display.
	 *
	 * @var string
	 */
	public $generalPathOfForm;

	/**
	 * Used internally to disable the storage of the document reference (eg. new records)
	 *
	 * @var bool
	 */
	public $dontStoreDocumentRef;

	/**
	 * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	protected $signalSlotDispatcher;

	/**
	 * Constructor
	 */
	public function __construct() {
		$GLOBALS['SOBE'] = $this;
		$GLOBALS['LANG']->includeLLFile('EXT:lang/locallang_alt_doc.xml');
	}

	/**
	 * Get the SignalSlot dispatcher
	 *
	 * @return \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	protected function getSignalSlotDispatcher() {
		if (!isset($this->signalSlotDispatcher)) {
			$this->signalSlotDispatcher = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
		}
		return $this->signalSlotDispatcher;
	}

	/**
	 * Emits a signal after a function was executed
	 *
	 * @param string $signalName
	 */
	protected function emitFunctionAfterSignal($signalName) {
		$this->getSignalSlotDispatcher()->dispatch(__CLASS__, $signalName . 'After', array($this));
	}

	/**
	 * First initialization.
	 *
	 * @return void
	 */
	public function preInit() {
		if (GeneralUtility::_GP('justLocalized')) {
			$this->localizationRedirect(GeneralUtility::_GP('justLocalized'));
		}
		// Setting GPvars:
		$this->editconf = GeneralUtility::_GP('edit');
		$this->defVals = GeneralUtility::_GP('defVals');
		$this->overrideVals = GeneralUtility::_GP('overrideVals');
		$this->columnsOnly = GeneralUtility::_GP('columnsOnly');
		$this->returnUrl = GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl'));
		$this->closeDoc = GeneralUtility::_GP('closeDoc');
		$this->doSave = GeneralUtility::_GP('doSave');
		$this->returnEditConf = GeneralUtility::_GP('returnEditConf');
		$this->localizationMode = GeneralUtility::_GP('localizationMode');
		$this->workspace = GeneralUtility::_GP('workspace');
		$this->uc = GeneralUtility::_GP('uc');
		// Setting override values as default if defVals does not exist.
		if (!is_array($this->defVals) && is_array($this->overrideVals)) {
			$this->defVals = $this->overrideVals;
		}
		// Setting return URL
		$this->retUrl = $this->returnUrl ?: 'dummy.php';
		// Fix $this->editconf if versioning applies to any of the records
		$this->fixWSversioningInEditConf();
		// Make R_URL (request url) based on input GETvars:
		$this->R_URL_parts = parse_url(GeneralUtility::getIndpEnv('REQUEST_URI'));
		$this->R_URL_getvars = GeneralUtility::_GET();
		$this->R_URL_getvars['edit'] = $this->editconf;
		// MAKE url for storing
		$this->compileStoreDat();
		// Initialize more variables.
		$this->dontStoreDocumentRef = 0;
		$this->storeTitle = '';
		// Get session data for the module:
		$this->docDat = $GLOBALS['BE_USER']->getModuleData('alt_doc.php', 'ses');
		$this->docHandler = $this->docDat[0];
		// If a request for closing the document has been sent, act accordingly:
		if ($this->closeDoc > 0) {
			$this->closeDocument($this->closeDoc);
		}
		// If NO vars are sent to the script, try to read first document:
		// Added !is_array($this->editconf) because editConf must not be set either.
		// Anyways I can't figure out when this situation here will apply...
		if (is_array($this->R_URL_getvars) && count($this->R_URL_getvars) < 2 && !is_array($this->editconf)) {
			$this->setDocument($this->docDat[1]);
		}

		// Sets a temporary workspace, this request is based on
		if ($this->workspace !== NULL) {
			$this->getBackendUser()->setTemporaryWorkspace($this->workspace);
		}

		$this->emitFunctionAfterSignal(__FUNCTION__);
	}

	/**
	 * Detects, if a save command has been triggered.
	 *
	 * @return bool TRUE, then save the document (data submitted)
	 */
	public function doProcessData() {
		$out = $this->doSave || isset($_POST['_savedok_x']) || isset($_POST['_saveandclosedok_x']) || isset($_POST['_savedokview_x']) || isset($_POST['_savedoknew_x']) || isset($_POST['_translation_savedok_x']) || isset($_POST['_translation_savedokclear_x']);
		return $out;
	}

	/**
	 * Do processing of data, submitting it to TCEmain.
	 *
	 * @return void
	 */
	public function processData() {
		// GPvars specifically for processing:
		$control = GeneralUtility::_GP('control');
		$this->data = GeneralUtility::_GP('data');
		$this->cmd = GeneralUtility::_GP('cmd');
		$this->mirror = GeneralUtility::_GP('mirror');
		$this->cacheCmd = GeneralUtility::_GP('cacheCmd');
		$this->redirect = GeneralUtility::_GP('redirect');
		$this->returnNewPageId = GeneralUtility::_GP('returnNewPageId');
		$this->vC = GeneralUtility::_GP('vC');
		// See tce_db.php for relevate options here:
		// Only options related to $this->data submission are included here.
		/** @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
		$tce = GeneralUtility::makeInstance(\TYPO3\CMS\Core\DataHandling\DataHandler::class);
		$tce->stripslashes_values = 0;

		if (!empty($control)) {
			$tce->setControl($control);
		}
		if (isset($_POST['_translation_savedok_x'])) {
			$tce->updateModeL10NdiffData = 'FORCE_FFUPD';
		}
		if (isset($_POST['_translation_savedokclear_x'])) {
			$tce->updateModeL10NdiffData = 'FORCE_FFUPD';
			$tce->updateModeL10NdiffDataClear = TRUE;
		}
		// Setting default values specific for the user:
		$TCAdefaultOverride = $GLOBALS['BE_USER']->getTSConfigProp('TCAdefaults');
		if (is_array($TCAdefaultOverride)) {
			$tce->setDefaultsFromUserTS($TCAdefaultOverride);
		}
		// Setting internal vars:
		if ($GLOBALS['BE_USER']->uc['neverHideAtCopy']) {
			$tce->neverHideAtCopy = 1;
		}
		$tce->debug = 0;
		$tce->disableRTE = !$GLOBALS['BE_USER']->isRTE();
		// Loading TCEmain with data:
		$tce->start($this->data, $this->cmd);
		if (is_array($this->mirror)) {
			$tce->setMirror($this->mirror);
		}
		// Checking referer / executing
		$refInfo = parse_url(GeneralUtility::getIndpEnv('HTTP_REFERER'));
		$httpHost = GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY');
		if ($httpHost != $refInfo['host'] && $this->vC != $GLOBALS['BE_USER']->veriCode() && !$GLOBALS['TYPO3_CONF_VARS']['SYS']['doNotCheckReferer']) {
			$tce->log('', 0, 0, 0, 1, 'Referer host \'%s\' and server host \'%s\' did not match and veriCode was not valid either!', 1, array($refInfo['host'], $httpHost));
			debug('Error: Referer host did not match with server host.');
		} else {
			// Perform the saving operation with TCEmain:
			$tce->process_uploads($_FILES);
			$tce->process_datamap();
			$tce->process_cmdmap();
			// If pages are being edited, we set an instruction about updating the page tree after this operation.
			if ($tce->pagetreeNeedsRefresh && (isset($this->data['pages']) || $GLOBALS['BE_USER']->workspace != 0 && count($this->data))) {
				BackendUtility::setUpdateSignal('updatePageTree');
			}
			// If there was saved any new items, load them:
			if (count($tce->substNEWwithIDs_table)) {
				// save the expanded/collapsed states for new inline records, if any
				\TYPO3\CMS\Backend\Form\Element\InlineElement::updateInlineView($this->uc, $tce);
				$newEditConf = array();
				foreach ($this->editconf as $tableName => $tableCmds) {
					$keys = array_keys($tce->substNEWwithIDs_table, $tableName);
					if (count($keys) > 0) {
						foreach ($keys as $key) {
							$editId = $tce->substNEWwithIDs[$key];
							// Check if the $editId isn't a child record of an IRRE action
							if (!(is_array($tce->newRelatedIDs[$tableName]) && in_array($editId, $tce->newRelatedIDs[$tableName]))) {
								// Translate new id to the workspace version:
								if ($versionRec = BackendUtility::getWorkspaceVersionOfRecord($GLOBALS['BE_USER']->workspace, $tableName, $editId, 'uid')) {
									$editId = $versionRec['uid'];
								}
								$newEditConf[$tableName][$editId] = 'edit';
							}
							// Traverse all new records and forge the content of ->editconf so we can continue to EDIT these records!
							if ($tableName == 'pages' && $this->retUrl != 'dummy.php' && $this->returnNewPageId) {
								$this->retUrl .= '&id=' . $tce->substNEWwithIDs[$key];
							}
						}
					} else {
						$newEditConf[$tableName] = $tableCmds;
					}
				}
				// Resetting editconf if newEditConf has values:
				if (count($newEditConf)) {
					$this->editconf = $newEditConf;
				}
				// Finally, set the editconf array in the "getvars" so they will be passed along in URLs as needed.
				$this->R_URL_getvars['edit'] = $this->editconf;
				// Unsetting default values since we don't need them anymore.
				unset($this->R_URL_getvars['defVals']);
				// Re-compile the store* values since editconf changed...
				$this->compileStoreDat();
			}
			// See if any records was auto-created as new versions?
			if (count($tce->autoVersionIdMap)) {
				$this->fixWSversioningInEditConf($tce->autoVersionIdMap);
			}
			// If a document is saved and a new one is created right after.
			if (isset($_POST['_savedoknew_x']) && is_array($this->editconf)) {
				// Finding the current table:
				reset($this->editconf);
				$nTable = key($this->editconf);
				// Finding the first id, getting the records pid+uid
				reset($this->editconf[$nTable]);
				$nUid = key($this->editconf[$nTable]);
				$nRec = BackendUtility::getRecord($nTable, $nUid, 'pid,uid');
				// Setting a blank editconf array for a new record:
				$this->editconf = array();
				if ($this->getNewIconMode($nTable) == 'top') {
					$this->editconf[$nTable][$nRec['pid']] = 'new';
				} else {
					$this->editconf[$nTable][-$nRec['uid']] = 'new';
				}
				// Finally, set the editconf array in the "getvars" so they will be passed along in URLs as needed.
				$this->R_URL_getvars['edit'] = $this->editconf;
				// Re-compile the store* values since editconf changed...
				$this->compileStoreDat();
			}
			$tce->printLogErrorMessages(isset($_POST['_saveandclosedok_x']) || isset($_POST['_translation_savedok_x']) ? $this->retUrl : $this->R_URL_parts['path'] . '?' . GeneralUtility::implodeArrayForUrl('', $this->R_URL_getvars));
		}
		//  || count($tce->substNEWwithIDs)... If any new items has been save, the document is CLOSED
		// because if not, we just get that element re-listed as new. And we don't want that!
		if (isset($_POST['_saveandclosedok_x']) || isset($_POST['_translation_savedok_x']) || $this->closeDoc < 0) {
			$this->closeDocument(abs($this->closeDoc));
		}
	}

	/**
	 * Initialize the normal module operation
	 *
	 * @return void
	 */
	public function init() {
		// Setting more GPvars:
		$this->popViewId = GeneralUtility::_GP('popViewId');
		$this->popViewId_addParams = GeneralUtility::_GP('popViewId_addParams');
		$this->viewUrl = GeneralUtility::_GP('viewUrl');
		$this->editRegularContentFromId = GeneralUtility::_GP('editRegularContentFromId');
		$this->recTitle = GeneralUtility::_GP('recTitle');
		$this->disHelp = GeneralUtility::_GP('disHelp');
		$this->noView = GeneralUtility::_GP('noView');
		$this->perms_clause = $GLOBALS['BE_USER']->getPagePermsClause(1);
		// Set other internal variables:
		$this->R_URL_getvars['returnUrl'] = $this->retUrl;
		$this->R_URI = $this->R_URL_parts['path'] . '?' . GeneralUtility::implodeArrayForUrl('', $this->R_URL_getvars);
		// MENU-ITEMS:
		// If array, then it's a selector box menu
		// If empty string it's just a variable, that'll be saved.
		// Values NOT in this array will not be saved in the settings-array for the module.
		$this->MOD_MENU = array(
			'showPalettes' => ''
		);
		// Setting virtual document name
		$this->MCONF['name'] = 'xMOD_alt_doc.php';
		// CLEANSE SETTINGS
		$this->MOD_SETTINGS = BackendUtility::getModuleData($this->MOD_MENU, GeneralUtility::_GP('SET'), $this->MCONF['name']);
		// Create an instance of the document template object
		$this->doc = $GLOBALS['TBE_TEMPLATE'];
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->setModuleTemplate('EXT:backend/Resources/Private/Templates/alt_doc.html');
		$this->doc->form = '<form action="' . htmlspecialchars($this->R_URI) . '" method="post" enctype="' . $GLOBALS['TYPO3_CONF_VARS']['SYS']['form_enctype'] . '" name="editform" onsubmit="document.editform._scrollPosition.value=(document.documentElement.scrollTop || document.body.scrollTop); return TBE_EDITOR.checkSubmit(1);">';
		$this->doc->getPageRenderer()->loadPrototype();
		// override the default jumpToUrl
		$this->doc->JScodeArray['jumpToUrl'] = '
			function jumpToUrl(URL,formEl) {
				if (!TBE_EDITOR.isFormChanged()) {
					window.location.href = URL;
				} else if (formEl && formEl.type=="checkbox") {
					formEl.checked = formEl.checked ? 0 : 1;
				}
			}
';
		$this->doc->JScode = $this->doc->wrapScriptTags('
				// Object: TS:
				// passwordDummy and decimalSign are used by tbe_editor.js and have to be declared here as
				// TS object overwrites the object declared in tbe_editor.js
			function typoSetup() {	//
				this.uniqueID = "";
				this.passwordDummy = "********";
				this.PATH_typo3 = " ";
				this.decimalSign = ".";
			}
			var TS = new typoSetup();

				// Info view:
			function launchView(table,uid,bP) {	//
				var backPath= bP ? bP : "";
				var thePreviewWindow="";
				thePreviewWindow = window.open(backPath+"show_item.php?table="+encodeURIComponent(table)+"&uid="+encodeURIComponent(uid),"ShowItem"+TS.uniqueID,"height=300,width=410,status=0,menubar=0,resizable=0,location=0,directories=0,scrollbars=1,toolbar=0");
				if (thePreviewWindow && thePreviewWindow.focus) {
					thePreviewWindow.focus();
				}
			}
			function deleteRecord(table,id,url) {	//
				if (
					' . ($GLOBALS['BE_USER']->jsConfirmation(4) ? 'confirm(' . GeneralUtility::quoteJSvalue($GLOBALS['LANG']->getLL('deleteWarning')) . ')' : '1==1') . '
				)	{
					window.location.href = ' . GeneralUtility::quoteJSvalue(BackendUtility::getModuleUrl('tce_db') . '&cmd[') . '+table+"]["+id+"][delete]=1' . BackendUtility::getUrlToken('tceAction') . '&redirect="+escape(url)+"&vC=' . $GLOBALS['BE_USER']->veriCode() . '&prErr=1&uPT=1";
				}
				return false;
			}
		' . (isset($_POST['_savedokview_x']) && $this->popViewId ? 'if (window.opener) { ' . BackendUtility::viewOnClick($this->popViewId, '', BackendUtility::BEgetRootLine($this->popViewId), '', $this->viewUrl, $this->popViewId_addParams, FALSE) . ' } else { ' . BackendUtility::viewOnClick($this->popViewId, '', BackendUtility::BEgetRootLine($this->popViewId), '', $this->viewUrl, $this->popViewId_addParams) . ' } ' : ''));
		// Setting up the context sensitive menu:
		$this->doc->getContextMenuCode();
		$this->doc->bodyTagAdditions = 'onload="window.scrollTo(0,' . MathUtility::forceIntegerInRange(GeneralUtility::_GP('_scrollPosition'), 0, 10000) . ');"';

		$this->emitFunctionAfterSignal(__FUNCTION__);
	}

	/**
	 * Main module operation
	 *
	 * @return void
	 */
	public function main() {
		// Begin edit:
		if (is_array($this->editconf)) {
			// Initialize TCEforms (rendering the forms)
			$this->tceforms = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Form\FormEngine::class);
			$this->tceforms->initDefaultBEMode();
			$this->tceforms->doSaveFieldName = 'doSave';
			$this->tceforms->localizationMode = GeneralUtility::inList('text,media', $this->localizationMode) ? $this->localizationMode : '';
			// text,media is keywords defined in TYPO3 Core API..., see "l10n_cat"
			$this->tceforms->returnUrl = $this->R_URI;
			$this->tceforms->palettesCollapsed = !$this->MOD_SETTINGS['showPalettes'];
			$this->tceforms->disableRTE = !$GLOBALS['BE_USER']->isRTE();
			$this->tceforms->enableClickMenu = TRUE;
			$this->tceforms->enableTabMenu = TRUE;
			// Clipboard is initialized:
			// Start clipboard
			$this->tceforms->clipObj = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Clipboard\Clipboard::class);
			// Initialize - reads the clipboard content from the user session
			$this->tceforms->clipObj->initializeClipboard();
			// Setting external variables:
			$this->tceforms->edit_showFieldHelp = $GLOBALS['BE_USER']->uc['edit_showFieldHelp'];
			if ($this->editRegularContentFromId) {
				$this->editRegularContentFromId();
			}
			// Creating the editing form, wrap it with buttons, document selector etc.
			$editForm = $this->makeEditForm();
			if ($editForm) {
				$this->firstEl = reset($this->elementsData);
				// Checking if the currently open document is stored in the list of "open documents" - if not, then add it:
				if (($this->docDat[1] !== $this->storeUrlMd5 || !isset($this->docHandler[$this->storeUrlMd5])) && !$this->dontStoreDocumentRef) {
					$this->docHandler[$this->storeUrlMd5] = array($this->storeTitle, $this->storeArray, $this->storeUrl, $this->firstEl);
					$GLOBALS['BE_USER']->pushModuleData('alt_doc.php', array($this->docHandler, $this->storeUrlMd5));
					BackendUtility::setUpdateSignal('OpendocsController::updateNumber', count($this->docHandler));
				}
				// Module configuration
				$this->modTSconfig = $this->viewId ? BackendUtility::getModTSconfig($this->viewId, 'mod.xMOD_alt_doc') : array();
				$body = $this->tceforms->printNeededJSFunctions_top();
				$body .= $this->compileForm($editForm);
				$body .= $this->tceforms->printNeededJSFunctions();
				$body .= $this->functionMenus();
				$body .= $this->tceformMessages();
			}
		}
		// Access check...
		// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = BackendUtility::readPageAccess($this->viewId, $this->perms_clause);
		// Setting up the buttons and markers for docheader
		$docHeaderButtons = $this->getButtons();
		$markers = array(
			'LANGSELECTOR' => $this->langSelector(),
			'EXTRAHEADER' => $this->extraFormHeaders(),
			'CSH' => $docHeaderButtons['csh'],
			'CONTENT' => $body
		);
		// Build the <body> for the module
		$this->content = $this->doc->startPage('TYPO3 Edit Document');
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		$this->content .= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
	}

	/**
	 * Outputting the accumulated content to screen
	 *
	 * @return void
	 */
	public function printContent() {
		echo $this->content;
	}

	/***************************
	 *
	 * Sub-content functions, rendering specific parts of the module content.
	 *
	 ***************************/
	/**
	 * Creates the editing form with TCEforms, based on the input from GPvars.
	 *
	 * @return string HTML form elements wrapped in tables
	 */
	public function makeEditForm() {
		// Initialize variables:
		$this->elementsData = array();
		$this->errorC = 0;
		$this->newC = 0;
		$thePrevUid = '';
		$editForm = '';
		$trData = NULL;
		// Traverse the GPvar edit array
		// Tables:
		foreach ($this->editconf as $table => $conf) {
			if (is_array($conf) && $GLOBALS['TCA'][$table] && $GLOBALS['BE_USER']->check('tables_modify', $table)) {
				// Traverse the keys/comments of each table (keys can be a commalist of uids)
				foreach ($conf as $cKey => $cmd) {
					if ($cmd == 'edit' || $cmd == 'new') {
						// Get the ids:
						$ids = GeneralUtility::trimExplode(',', $cKey, TRUE);
						// Traverse the ids:
						foreach ($ids as $theUid) {
							// Checking if the user has permissions? (Only working as a precaution,
							// because the final permission check is always down in TCE. But it's
							// good to notify the user on beforehand...)
							// First, resetting flags.
							$hasAccess = 1;
							$deniedAccessReason = '';
							$deleteAccess = 0;
							$this->viewId = 0;
							// If the command is to create a NEW record...:
							if ($cmd == 'new') {
								// NOTICE: the id values in this case points to the page uid onto which the
								// record should be create OR (if the id is negativ) to a record from the
								// same table AFTER which to create the record.
								if ((int)$theUid) {
									// Find parent page on which the new record reside
									// Less than zero - find parent page
									if ($theUid < 0) {
										$calcPRec = BackendUtility::getRecord($table, abs($theUid));
										$calcPRec = BackendUtility::getRecord('pages', $calcPRec['pid']);
									} else {
										// always a page
										$calcPRec = BackendUtility::getRecord('pages', abs($theUid));
									}
									// Now, calculate whether the user has access to creating new records on this position:
									if (is_array($calcPRec)) {
										// Permissions for the parent page
										$CALC_PERMS = $GLOBALS['BE_USER']->calcPerms($calcPRec);
										if ($table == 'pages') {
											// If pages:
											$hasAccess = $CALC_PERMS & 8 ? 1 : 0;
											$this->viewId = 0;
										} else {
											$hasAccess = $CALC_PERMS & 16 ? 1 : 0;
											$this->viewId = $calcPRec['uid'];
										}
									}
								}
								// Don't save this document title in the document selector if the document is new.
								$this->dontStoreDocumentRef = 1;
							} else {
								// Edit:
								$calcPRec = BackendUtility::getRecord($table, $theUid);
								BackendUtility::fixVersioningPid($table, $calcPRec);
								if (is_array($calcPRec)) {
									if ($table == 'pages') { // If pages:
										$CALC_PERMS = $GLOBALS['BE_USER']->calcPerms($calcPRec);
										$hasAccess = $CALC_PERMS & 2 ? 1 : 0;
										$deleteAccess = $CALC_PERMS & 4 ? 1 : 0;
										$this->viewId = $calcPRec['uid'];
									} else {
										// Fetching pid-record first
										$CALC_PERMS = $GLOBALS['BE_USER']->calcPerms(BackendUtility::getRecord('pages', $calcPRec['pid']));
										$hasAccess = $CALC_PERMS & 16 ? 1 : 0;
										$deleteAccess = $CALC_PERMS & 16 ? 1 : 0;
										$this->viewId = $calcPRec['pid'];
										// Adding "&L=xx" if the record being edited has a languageField with a value larger than zero!
										if ($GLOBALS['TCA'][$table]['ctrl']['languageField'] && $calcPRec[$GLOBALS['TCA'][$table]['ctrl']['languageField']] > 0) {
											$this->viewId_addParams = '&L=' . $calcPRec[$GLOBALS['TCA'][$table]['ctrl']['languageField']];
										}
									}
									// Check internals regarding access:
									$isRootLevelRestrictionIgnored = BackendUtility::isRootLevelRestrictionIgnored($table);
									if ($hasAccess || (string)$calcPRec['pid'] === '0' && $isRootLevelRestrictionIgnored) {
										$hasAccess = $GLOBALS['BE_USER']->recordEditAccessInternals($table, $calcPRec);
										$deniedAccessReason = $GLOBALS['BE_USER']->errorMsg;
									}
								} else {
									$hasAccess = 0;
								}
							}
							if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/alt_doc.php']['makeEditForm_accessCheck'])) {
								foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['typo3/alt_doc.php']['makeEditForm_accessCheck'] as $_funcRef) {
									$_params = array(
										'table' => $table,
										'uid' => $theUid,
										'cmd' => $cmd,
										'hasAccess' => $hasAccess
									);
									$hasAccess = GeneralUtility::callUserFunction($_funcRef, $_params, $this);
								}
							}
							// AT THIS POINT we have checked the access status of the editing/creation of
							// records and we can now proceed with creating the form elements:
							if ($hasAccess) {
								$prevPageID = is_object($trData) ? $trData->prevPageID : '';
								$trData = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Form\DataPreprocessor::class);
								$trData->addRawData = TRUE;
								$trData->defVals = $this->defVals;
								$trData->lockRecords = 1;
								$trData->disableRTE = !$GLOBALS['BE_USER']->isRTE();
								$trData->prevPageID = $prevPageID;
								// 'new'
								$trData->fetchRecord($table, $theUid, $cmd == 'new' ? 'new' : '');
								$rec = reset($trData->regTableItems_data);
								$rec['uid'] = $cmd == 'new' ? uniqid('NEW', TRUE) : $theUid;
								if ($cmd == 'new') {
									$rec['pid'] = $theUid == 'prev' ? $thePrevUid : $theUid;
								}
								$this->elementsData[] = array(
									'table' => $table,
									'uid' => $rec['uid'],
									'pid' => $rec['pid'],
									'cmd' => $cmd,
									'deleteAccess' => $deleteAccess
								);
								// Now, render the form:
								if (is_array($rec)) {
									// Setting visual path / title of form:
									$this->generalPathOfForm = $this->tceforms->getRecordPath($table, $rec);
									if (!$this->storeTitle) {
										$this->storeTitle = $this->recTitle ? htmlspecialchars($this->recTitle) : BackendUtility::getRecordTitle($table, $rec, TRUE);
									}
									// Setting variables in TCEforms object:
									$this->tceforms->hiddenFieldList = '';
									$this->tceforms->globalShowHelp = !$this->disHelp;
									if (is_array($this->overrideVals) && is_array($this->overrideVals[$table])) {
										$this->tceforms->hiddenFieldListArr = array_keys($this->overrideVals[$table]);
									}
									// Register default language labels, if any:
									$this->tceforms->registerDefaultLanguageData($table, $rec);
									// Create form for the record (either specific list of fields or the whole record):
									$panel = '';
									if ($this->columnsOnly) {
										if (is_array($this->columnsOnly)) {
											$panel .= $this->tceforms->getListedFields($table, $rec, $this->columnsOnly[$table]);
										} else {
											$panel .= $this->tceforms->getListedFields($table, $rec, $this->columnsOnly);
										}
									} else {
										$panel .= $this->tceforms->getMainFields($table, $rec);
									}
									$panel = $this->tceforms->wrapTotal($panel, $rec, $table);
									// Setting the pid value for new records:
									if ($cmd == 'new') {
										$panel .= '<input type="hidden" name="data[' . $table . '][' . $rec['uid'] . '][pid]" value="' . $rec['pid'] . '" />';
										$this->newC++;
									}
									// Display "is-locked" message:
									if ($lockInfo = BackendUtility::isRecordLocked($table, $rec['uid'])) {
										$flashMessage = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessage::class, htmlspecialchars($lockInfo['msg']), '', \TYPO3\CMS\Core\Messaging\FlashMessage::WARNING);
										/** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
										$flashMessageService = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Messaging\FlashMessageService::class);
										/** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
										$defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
										$defaultFlashMessageQueue->enqueue($flashMessage);
									}
									// Combine it all:
									$editForm .= $panel;
								}
								$thePrevUid = $rec['uid'];
							} else {
								$this->errorC++;
								$editForm .= $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.noEditPermission', TRUE) . '<br /><br />' . ($deniedAccessReason ? 'Reason: ' . htmlspecialchars($deniedAccessReason) . '<br /><br />' : '');
							}
						}
					}
				}
			}
		}
		return $editForm;
	}

	/**
	 * Create the panel of buttons for submitting the form or otherwise perform operations.
	 *
	 * @return array All available buttons as an assoc. array
	 */
	protected function getButtons() {
		$buttons = array(
			'save' => '',
			'save_view' => '',
			'save_new' => '',
			'save_close' => '',
			'close' => '',
			'delete' => '',
			'undo' => '',
			'history' => '',
			'columns_only' => '',
			'csh' => '',
			'translation_save' => '',
			'translation_saveclear' => ''
		);
		// Render SAVE type buttons:
		// The action of each button is decided by its name attribute. (See doProcessData())
		if (!$this->errorC && !$GLOBALS['TCA'][$this->firstEl['table']]['ctrl']['readOnly']) {
			// SAVE button:
			$buttons['save'] = IconUtility::getSpriteIcon('actions-document-save', array('html' => '<input type="image" name="_savedok" class="c-inputButton" src="clear.gif" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:rm.saveDoc', TRUE) . '" />'));
			// SAVE / VIEW button:
			if ($this->viewId && !$this->noView && $this->getNewIconMode($this->firstEl['table'], 'saveDocView')) {
				$buttons['save_view'] = IconUtility::getSpriteIcon('actions-document-save-view', array('html' => '<input onclick="window.open(\'\', \'newTYPO3frontendWindow\');" type="image" class="c-inputButton" name="_savedokview" src="clear.gif" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:rm.saveDocShow', TRUE) . '" />'));
			}
			// SAVE / NEW button:
			if (count($this->elementsData) == 1 && $this->getNewIconMode($this->firstEl['table'])) {
				$buttons['save_new'] = IconUtility::getSpriteIcon('actions-document-save-new', array('html' => '<input type="image" class="c-inputButton" name="_savedoknew" src="clear.gif" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:rm.saveNewDoc', TRUE) . '" />'));
			}
			// SAVE / CLOSE
			$buttons['save_close'] = IconUtility::getSpriteIcon('actions-document-save-close', array('html' => '<input type="image" class="c-inputButton" name="_saveandclosedok" src="clear.gif" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:rm.saveCloseDoc', TRUE) . '" />'));
			// FINISH TRANSLATION / SAVE / CLOSE
			if ($GLOBALS['TYPO3_CONF_VARS']['BE']['explicitConfirmationOfTranslation']) {
				$buttons['translation_save'] = '<input type="image" class="c-inputButton" name="_translation_savedok" src="' . IconUtility::skinImg($this->doc->backPath, 'sysext/t3skin/images/icons/actions/document-save-translation.png', '', 1) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:rm.translationSaveDoc', TRUE) . '" /> ';
				$buttons['translation_saveclear'] = '<input type="image" class="c-inputButton" name="_translation_savedokclear" src="' . IconUtility::skinImg($this->doc->backPath, 'sysext/t3skin/images/icons/actions/document-save-cleartranslationcache.png', '', 1) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:rm.translationSaveDocClear', TRUE) . '" />';
			}
		}
		// CLOSE button:
		$buttons['close'] = '<a href="#" onclick="document.editform.closeDoc.value=1; document.editform.submit(); return false;" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:rm.closeDoc', TRUE) . '">' . IconUtility::getSpriteIcon('actions-document-close') . '</a>';
		// DELETE + UNDO buttons:
		if (!$this->errorC && !$GLOBALS['TCA'][$this->firstEl['table']]['ctrl']['readOnly'] && count($this->elementsData) == 1) {
			if ($this->firstEl['cmd'] != 'new' && MathUtility::canBeInterpretedAsInteger($this->firstEl['uid'])) {
				// Delete:
				if ($this->firstEl['deleteAccess'] && !$GLOBALS['TCA'][$this->firstEl['table']]['ctrl']['readOnly'] && !$this->getNewIconMode($this->firstEl['table'], 'disableDelete')) {
					$aOnClick = 'return deleteRecord(\'' . $this->firstEl['table'] . '\',\'' . $this->firstEl['uid'] . '\', unescape(\'' . rawurlencode($this->retUrl) . '\'));';
					$buttons['delete'] = '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '" title="' . $GLOBALS['LANG']->getLL('deleteItem', TRUE) . '">' . IconUtility::getSpriteIcon('actions-edit-delete') . '</a>';
				}
				// Undo:
				$undoRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tstamp', 'sys_history', 'tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->firstEl['table'], 'sys_history') . ' AND recuid=' . (int)$this->firstEl['uid'], '', 'tstamp DESC', '1');
				if ($undoButtonR = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($undoRes)) {
					$aOnClick = 'window.location.href=' .
						GeneralUtility::quoteJSvalue(
							BackendUtility::getModuleUrl(
								'record_history',
								array(
									'element' => $this->firstEl['table'] . ':' . $this->firstEl['uid'],
									'revert' => 'ALL_FIELDS',
									'sumUp' => -1,
									'returnUrl' => $this->R_URI,
								)
							)
						) . '; return false;';
					$buttons['undo'] = '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '"' . ' title="' . htmlspecialchars(sprintf($GLOBALS['LANG']->getLL('undoLastChange'), BackendUtility::calcAge(($GLOBALS['EXEC_TIME'] - $undoButtonR['tstamp']), $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.minutesHoursDaysYears')))) . '">' . IconUtility::getSpriteIcon('actions-edit-undo') . '</a>';
				}
				if ($this->getNewIconMode($this->firstEl['table'], 'showHistory')) {
					$aOnClick = 'window.location.href=' .
						GeneralUtility::quoteJSvalue(
							BackendUtility::getModuleUrl(
								'record_history',
								array(
									'element' => $this->firstEl['table'] . ':' . $this->firstEl['uid'],
									'returnUrl' => $this->R_URI,
								)
							)
						) . '; return false;';
					$buttons['history'] = '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . IconUtility::getSpriteIcon('actions-document-history-open') . '</a>';
				}
				// If only SOME fields are shown in the form, this will link the user to the FULL form:
				if ($this->columnsOnly) {
					$buttons['columns_only'] = '<a href="' . htmlspecialchars(($this->R_URI . '&columnsOnly=')) . '" title="' . $GLOBALS['LANG']->getLL('editWholeRecord', TRUE) . '">' . IconUtility::getSpriteIcon('actions-document-open') . '</a>';
				}
			}
		}
		// add the CSH icon
		$buttons['csh'] = BackendUtility::cshItem('xMOD_csh_corebe', 'TCEforms');
		$buttons['shortcut'] = $this->shortCutLink();
		$buttons['open_in_new_window'] = $this->openInNewWindowLink();
		return $buttons;
	}

	/**
	 * Returns the language switch/selector for editing,
	 * show only when a single record is edited
	 * - multiple records are too confusing
	 *
	 * @return string The HTML
	 */
	public function langSelector() {
		$langSelector = '';
		if (count($this->elementsData) == 1) {
			$langSelector = $this->languageSwitch($this->firstEl['table'], $this->firstEl['uid'], $this->firstEl['pid']);
		}
		return $langSelector;
	}

	/**
	 * Compiles the extra form headers if the tceforms
	 *
	 * @return string The HTML
	 */
	public function extraFormHeaders() {
		$extraTemplate = '';
		if (is_array($this->tceforms->extraFormHeaders)) {
			$extraTemplate = HtmlParser::getSubpart($this->doc->moduleTemplate, '###DOCHEADER_EXTRAHEADER###');
			$extraTemplate = HtmlParser::substituteMarker($extraTemplate, '###EXTRAHEADER###', implode(LF, $this->tceforms->extraFormHeaders));
		}
		return $extraTemplate;
	}

	/**
	 * Put together the various elements (buttons, selectors, form) into a table
	 *
	 * @param string $editForm HTML form.
	 * @return string Composite HTML
	 */
	public function compileForm($editForm) {
		$formContent = '
			<!-- EDITING FORM -->
			' . $editForm . '

			<input type="hidden" name="returnUrl" value="' . htmlspecialchars($this->retUrl) . '" />
			<input type="hidden" name="viewUrl" value="' . htmlspecialchars($this->viewUrl) . '" />';
		if ($this->returnNewPageId) {
			$formContent .= '<input type="hidden" name="returnNewPageId" value="1" />';
		}
		$formContent .= '<input type="hidden" name="popViewId" value="' . htmlspecialchars($this->viewId) . '" />';
		if ($this->viewId_addParams) {
			$formContent .= '<input type="hidden" name="popViewId_addParams" value="' . htmlspecialchars($this->viewId_addParams) . '" />';
		}
		$formContent .= '
			<input type="hidden" name="closeDoc" value="0" />
			<input type="hidden" name="doSave" value="0" />
			<input type="hidden" name="_serialNumber" value="' . md5(microtime()) . '" />
			<input type="hidden" name="_scrollPosition" value="" />' . FormEngine::getHiddenTokenField('editRecord');
		return $formContent;
	}

	/**
	 * Create the checkbox buttons in the bottom of the pages.
	 *
	 * @return string HTML for function menus.
	 */
	public function functionMenus() {
		if ($GLOBALS['BE_USER']->getTSConfigVal('options.enableShowPalettes')) {
			// Show palettes:

			return '<div class="checkbox">' .
				'<label for="checkShowPalettes">' .
					BackendUtility::getFuncCheck('', 'SET[showPalettes]', $this->MOD_SETTINGS['showPalettes'], 'alt_doc.php', (GeneralUtility::implodeArrayForUrl('', array_merge($this->R_URL_getvars, array('SET' => ''))) . BackendUtility::getUrlToken('editRecord')), 'id="checkShowPalettes"') .
					$GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.showPalettes', TRUE) .
				'</label>'.
			'</div>';
		} else {
			return '';
		}
	}

	/**
	 * Create shortcut icon
	 *
	 * @return string
	 */
	public function shortCutLink() {
		if ($this->returnUrl == 'close.html' || !$GLOBALS['BE_USER']->mayMakeShortcut()) {
			return '';
		}
		return $this->doc->makeShortcutIcon('returnUrl,edit,defVals,overrideVals,columnsOnly,returnNewPageId,editRegularContentFromId,disHelp,noView', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name'], 1);
	}

	/**
	 * Creates open-in-window link
	 *
	 * @return string
	 */
	public function openInNewWindowLink() {
		if ($this->returnUrl == 'close.html') {
			return '';
		}
		$aOnClick = 'vHWin=window.open(' . GeneralUtility::quoteJSvalue(GeneralUtility::linkThisScript(array('returnUrl' => 'close.html'))) . ',\'' . md5($this->R_URI) . '\',\'width=670,height=500,status=0,menubar=0,scrollbars=1,resizable=1\');vHWin.focus();return false;';
		return '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '" title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.openInNewWindow', TRUE) . '">' . IconUtility::getSpriteIcon('actions-window-open') . '</a>';
	}

	/**
	 * Reads comment messages from TCEforms and prints them in a HTML comment in the bottom of the page.
	 *
	 * @return void
	 */
	public function tceformMessages() {
		if (count($this->tceforms->commentMessages)) {
			$tceformMessages = '
				<!-- TCEFORM messages
				' . htmlspecialchars(implode(LF, $this->tceforms->commentMessages)) . '
				-->
			';
		}
		return $tceformMessages;
	}

	/***************************
	 *
	 * Localization stuff
	 *
	 ***************************/
	/**
	 * Make selector box for creating new translation for a record or switching to edit the record in an existing language.
	 * Displays only languages which are available for the current page.
	 *
	 * @param string $table Table name
	 * @param int $uid Uid for which to create a new language
	 * @param int $pid Pid of the record
	 * @return string <select> HTML element (if there were items for the box anyways...)
	 */
	public function languageSwitch($table, $uid, $pid = NULL) {
		$content = '';
		$languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
		$transOrigPointerField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
		// Table editable and activated for languages?
		if ($GLOBALS['BE_USER']->check('tables_modify', $table) && $languageField && $transOrigPointerField && !$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerTable']) {
			if (is_null($pid)) {
				$row = BackendUtility::getRecord($table, $uid, 'pid');
				$pid = $row['pid'];
			}
			// Get all avalibale languages for the page
			$langRows = $this->getLanguages($pid);
			// Page available in other languages than default language?
			if (is_array($langRows) && count($langRows) > 1) {
				$rowsByLang = array();
				$fetchFields = 'uid,' . $languageField . ',' . $transOrigPointerField;
				// Get record in current language
				$rowCurrent = BackendUtility::getLiveVersionOfRecord($table, $uid, $fetchFields);
				if (!is_array($rowCurrent)) {
					$rowCurrent = BackendUtility::getRecord($table, $uid, $fetchFields);
				}
				$currentLanguage = $rowCurrent[$languageField];
				// Disabled for records with [all] language!
				if ($currentLanguage > -1) {
					// Get record in default language if needed
					if ($currentLanguage && $rowCurrent[$transOrigPointerField]) {
						$rowsByLang[0] = BackendUtility::getLiveVersionOfRecord($table, $rowCurrent[$transOrigPointerField], $fetchFields);
						if (!is_array($rowsByLang[0])) {
							$rowsByLang[0] = BackendUtility::getRecord($table, $rowCurrent[$transOrigPointerField], $fetchFields);
						}
					} else {
						$rowsByLang[$rowCurrent[$languageField]] = $rowCurrent;
					}
					if ($rowCurrent[$transOrigPointerField] || $currentLanguage === '0') {
						// Get record in other languages to see what's already available
						$translations = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows($fetchFields, $table, 'pid=' . (int)$pid . ' AND ' . $languageField . '>0' . ' AND ' . $transOrigPointerField . '=' . (int)$rowsByLang[0]['uid'] . BackendUtility::deleteClause($table) . BackendUtility::versioningPlaceholderClause($table));
						foreach ($translations as $row) {
							$rowsByLang[$row[$languageField]] = $row;
						}
					}
					$langSelItems = array();
					foreach ($langRows as $lang) {
						if ($GLOBALS['BE_USER']->checkLanguageAccess($lang['uid'])) {
							$newTranslation = isset($rowsByLang[$lang['uid']]) ? '' : ' [' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xlf:labels.new', TRUE) . ']';
							// Create url for creating a localized record
							if ($newTranslation) {
								$href = $this->doc->issueCommand('&cmd[' . $table . '][' . $rowsByLang[0]['uid'] . '][localize]=' . $lang['uid'], $this->backPath . 'alt_doc.php?justLocalized=' . rawurlencode(($table . ':' . $rowsByLang[0]['uid'] . ':' . $lang['uid'])) . '&returnUrl=' . rawurlencode($this->retUrl) . BackendUtility::getUrlToken('editRecord'));
							} else {
								$href = $this->backPath . 'alt_doc.php?';
								$href .= '&edit[' . $table . '][' . $rowsByLang[$lang['uid']]['uid'] . ']=edit';
								$href .= '&returnUrl=' . rawurlencode($this->retUrl) . BackendUtility::getUrlToken('editRecord');
							}
							$langSelItems[$lang['uid']] = '
								<option value="' . htmlspecialchars($href) . '"' . ($currentLanguage == $lang['uid'] ? ' selected="selected"' : '') . '>' . htmlspecialchars(($lang['title'] . $newTranslation)) . '</option>';
						}
					}
					// If any languages are left, make selector:
					if (count($langSelItems) > 1) {
						$onChange = 'if(this.options[this.selectedIndex].value){window.location.href=(this.options[this.selectedIndex].value);}';
						$content = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_general.xlf:LGL.language', TRUE) . ' <select name="_langSelector" onchange="' . htmlspecialchars($onChange) . '">
							' . implode('', $langSelItems) . '
							</select>';
					}
				}
			}
		}
		return $content;
	}

	/**
	 * Redirects to alt_doc with new parameters to edit a just created localized record
	 *
	 * @param string $justLocalized String passed by GET &justLocalized=
	 * @return void
	 */
	public function localizationRedirect($justLocalized) {
		list($table, $orig_uid, $language) = explode(':', $justLocalized);
		if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['languageField'] && $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) {
			$localizedRecord = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('uid', $table, $GLOBALS['TCA'][$table]['ctrl']['languageField'] . '=' . (int)$language . ' AND ' . $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] . '=' . (int)$orig_uid . BackendUtility::deleteClause($table) . BackendUtility::versioningPlaceholderClause($table));
			if (is_array($localizedRecord)) {
				// Create parameters and finally run the classic page module for creating a new page translation
				$params = '&edit[' . $table . '][' . $localizedRecord['uid'] . ']=edit';
				$returnUrl = '&returnUrl=' . rawurlencode(GeneralUtility::sanitizeLocalUrl(GeneralUtility::_GP('returnUrl')));
				$location = $GLOBALS['BACK_PATH'] . 'alt_doc.php?' . $params . $returnUrl . BackendUtility::getUrlToken('editRecord');
				HttpUtility::redirect($location);
			}
		}
	}

	/**
	 * Returns sys_language records available for record translations on given page.
	 *
	 * @param int $id Page id: If zero, the query will select all sys_language records from root level which are NOT hidden. If set to another value, the query will select all sys_language records that has a pages_language_overlay record on that page (and is not hidden, unless you are admin user)
	 * @return array Language records including faked record for default language
	 */
	public function getLanguages($id) {
		$modSharedTSconfig = BackendUtility::getModTSconfig($id, 'mod.SHARED');
		// Fallback non sprite-configuration
		if (preg_match('/\\.gif$/', $modSharedTSconfig['properties']['defaultLanguageFlag'])) {
			$modSharedTSconfig['properties']['defaultLanguageFlag'] = str_replace('.gif', '', $modSharedTSconfig['properties']['defaultLanguageFlag']);
		}
		$languages = array(
			0 => array(
				'uid' => 0,
				'pid' => 0,
				'hidden' => 0,
				'title' => $modSharedTSconfig['properties']['defaultLanguageLabel'] !== ''
						? $modSharedTSconfig['properties']['defaultLanguageLabel'] . ' (' . $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_mod_web_list.xlf:defaultLanguage') . ')'
						: $GLOBALS['LANG']->sl('LLL:EXT:lang/locallang_mod_web_list.xlf:defaultLanguage'),
				'flag' => $modSharedTSconfig['properties']['defaultLanguageFlag']
			)
		);
		$exQ = $GLOBALS['BE_USER']->isAdmin() ? '' : ' AND sys_language.hidden=0';
		if ($id) {
			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('sys_language.*', 'pages_language_overlay,sys_language', 'pages_language_overlay.sys_language_uid=sys_language.uid AND pages_language_overlay.pid=' . (int)$id . BackendUtility::deleteClause('pages_language_overlay') . $exQ, 'pages_language_overlay.sys_language_uid,sys_language.uid,sys_language.pid,sys_language.tstamp,sys_language.hidden,sys_language.title,sys_language.language_isocode,sys_language.static_lang_isocode,sys_language.flag', 'sys_language.title');
		} else {
			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('sys_language.*', 'sys_language', 'sys_language.hidden=0', '', 'sys_language.title');
		}
		if ($rows) {
			foreach ($rows as $row) {
				$languages[$row['uid']] = $row;
			}
		}
		return $languages;
	}

	/***************************
	 *
	 * Other functions
	 *
	 ***************************/
	/**
	 * Fix $this->editconf if versioning applies to any of the records
	 *
	 * @param array $mapArray Mapping between old and new ids if auto-versioning has been performed.
	 * @return void
	 */
	public function fixWSversioningInEditConf($mapArray = FALSE) {
		// Traverse the editConf array
		if (is_array($this->editconf)) {
			// Tables:
			foreach ($this->editconf as $table => $conf) {
				if (is_array($conf) && $GLOBALS['TCA'][$table]) {
					// Traverse the keys/comments of each table (keys can be a commalist of uids)
					$newConf = array();
					foreach ($conf as $cKey => $cmd) {
						if ($cmd == 'edit') {
							// Traverse the ids:
							$ids = GeneralUtility::trimExplode(',', $cKey, TRUE);
							foreach ($ids as $idKey => $theUid) {
								if (is_array($mapArray)) {
									if ($mapArray[$table][$theUid]) {
										$ids[$idKey] = $mapArray[$table][$theUid];
									}
								} else {
									// Default, look for versions in workspace for record:
									$calcPRec = $this->getRecordForEdit($table, $theUid);
									if (is_array($calcPRec)) {
										// Setting UID again if it had changed, eg. due to workspace versioning.
										$ids[$idKey] = $calcPRec['uid'];
									}
								}
							}
							// Add the possibly manipulated IDs to the new-build newConf array:
							$newConf[implode(',', $ids)] = $cmd;
						} else {
							$newConf[$cKey] = $cmd;
						}
					}
					// Store the new conf array:
					$this->editconf[$table] = $newConf;
				}
			}
		}
	}

	/**
	 * Get record for editing.
	 *
	 * @param string $table Table name
	 * @param int $theUid Record UID
	 * @return array Returns record to edit, FALSE if none
	 */
	public function getRecordForEdit($table, $theUid) {
		// Fetch requested record:
		$reqRecord = BackendUtility::getRecord($table, $theUid, 'uid,pid');
		if (is_array($reqRecord)) {
			// If workspace is OFFLINE:
			if ($GLOBALS['BE_USER']->workspace != 0) {
				// Check for versioning support of the table:
				if ($GLOBALS['TCA'][$table] && $GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
					// If the record is already a version of "something" pass it by.
					if ($reqRecord['pid'] == -1) {
						// (If it turns out not to be a version of the current workspace there will be trouble, but that is handled inside TCEmain then and in the interface it would clearly be an error of links if the user accesses such a scenario)
						return $reqRecord;
					} else {
						// The input record was online and an offline version must be found or made:
						// Look for version of this workspace:
						$versionRec = BackendUtility::getWorkspaceVersionOfRecord($GLOBALS['BE_USER']->workspace, $table, $reqRecord['uid'], 'uid,pid,t3ver_oid');
						return is_array($versionRec) ? $versionRec : $reqRecord;
					}
				} else {
					// This means that editing cannot occur on this record because it was not supporting versioning which is required inside an offline workspace.
					return FALSE;
				}
			} else {
				// In ONLINE workspace, just return the originally requested record:
				return $reqRecord;
			}
		} else {
			// Return FALSE because the table/uid was not found anyway.
			return FALSE;
		}
	}

	/**
	 * Function, which populates the internal editconf array with editing commands for all tt_content elements from the normal column in normal language from the page pointed to by $this->editRegularContentFromId
	 *
	 * @return void
	 */
	public function editRegularContentFromId() {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'tt_content', 'pid=' . (int)$this->editRegularContentFromId . BackendUtility::deleteClause('tt_content') . BackendUtility::versioningPlaceholderClause('tt_content') . ' AND colPos=0 AND sys_language_uid=0', '', 'sorting');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$ecUids = array();
			while ($ecRec = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$ecUids[] = $ecRec['uid'];
			}
			$this->editconf['tt_content'][implode(',', $ecUids)] = 'edit';
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
	}

	/**
	 * Populates the variables $this->storeArray, $this->storeUrl, $this->storeUrlMd5
	 *
	 * @return void
	 * @see makeDocSel()
	 */
	public function compileStoreDat() {
		$this->storeArray = GeneralUtility::compileSelectedGetVarsFromArray('edit,defVals,overrideVals,columnsOnly,disHelp,noView,editRegularContentFromId,workspace', $this->R_URL_getvars);
		$this->storeUrl = GeneralUtility::implodeArrayForUrl('', $this->storeArray);
		$this->storeUrlMd5 = md5($this->storeUrl);
	}

	/**
	 * Function used to look for configuration of buttons in the form: Fx. disabling buttons or showing them at various positions.
	 *
	 * @param string $table The table for which the configuration may be specific
	 * @param string $key The option for look for. Default is checking if the saveDocNew button should be displayed.
	 * @return string Return value fetched from USER TSconfig
	 */
	public function getNewIconMode($table, $key = 'saveDocNew') {
		$TSconfig = $GLOBALS['BE_USER']->getTSConfig('options.' . $key);
		$output = trim(isset($TSconfig['properties'][$table]) ? $TSconfig['properties'][$table] : $TSconfig['value']);
		return $output;
	}

	/**
	 * Handling the closing of a document
	 *
	 * @param int $code Close code: 0/1 will redirect to $this->retUrl, 3 will clear the docHandler (thus closing all documents) and otehr values will call setDocument with ->retUrl
	 * @return void
	 */
	public function closeDocument($code = 0) {
		// If current document is found in docHandler,
		// then unset it, possibly unset it ALL and finally, write it to the session data
		if (isset($this->docHandler[$this->storeUrlMd5])) {
			// add the closing document to the recent documents
			$recentDocs = $GLOBALS['BE_USER']->getModuleData('opendocs::recent');
			if (!is_array($recentDocs)) {
				$recentDocs = array();
			}
			$closedDoc = $this->docHandler[$this->storeUrlMd5];
			$recentDocs = array_merge(array($this->storeUrlMd5 => $closedDoc), $recentDocs);
			if (count($recentDocs) > 8) {
				$recentDocs = array_slice($recentDocs, 0, 8);
			}
			// remove it from the list of the open documents
			unset($this->docHandler[$this->storeUrlMd5]);
			if ($code == '3') {
				$recentDocs = array_merge($this->docHandler, $recentDocs);
				$this->docHandler = array();
			}
			$GLOBALS['BE_USER']->pushModuleData('opendocs::recent', $recentDocs);
			$GLOBALS['BE_USER']->pushModuleData('alt_doc.php', array($this->docHandler, $this->docDat[1]));
			BackendUtility::setUpdateSignal('OpendocsController::updateNumber', count($this->docHandler));
		}
		// If ->returnEditConf is set, then add the current content of editconf to the ->retUrl variable: (used by other scripts, like wizard_add, to know which records was created or so...)
		if ($this->returnEditConf && $this->retUrl != 'dummy.php') {
			$this->retUrl .= '&returnEditConf=' . rawurlencode(json_encode($this->editconf));
		}
		// If code is NOT set OR set to 1, then make a header location redirect to $this->retUrl
		if (!$code || $code == 1) {
			HttpUtility::redirect($this->retUrl);
		} else {
			$this->setDocument('', $this->retUrl);
		}
	}

	/**
	 * Redirects to the document pointed to by $currentDocFromHandlerMD5 OR $retUrl (depending on some internal calculations).
	 * Most likely you will get a header-location redirect from this function.
	 *
	 * @param string $currentDocFromHandlerMD5 Pointer to the document in the docHandler array
	 * @param string $retUrl Alternative/Default retUrl
	 * @return void
	 */
	public function setDocument($currentDocFromHandlerMD5 = '', $retUrl = 'dummy.php') {
		if ($retUrl === 'dummy.php') {
			return;
		}
		if (!$this->modTSconfig['properties']['disableDocSelector'] && is_array($this->docHandler) && count($this->docHandler)) {
			if (isset($this->docHandler[$currentDocFromHandlerMD5])) {
				$setupArr = $this->docHandler[$currentDocFromHandlerMD5];
			} else {
				$setupArr = reset($this->docHandler);
			}
			if ($setupArr[2]) {
				$sParts = parse_url(GeneralUtility::getIndpEnv('REQUEST_URI'));
				$retUrl = $sParts['path'] . '?' . $setupArr[2] . '&returnUrl=' . rawurlencode($retUrl);
			}
		}
		HttpUtility::redirect($retUrl);
	}

	/**
	 * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
	 */
	protected function getBackendUser() {
		return $GLOBALS['BE_USER'];
	}

}
