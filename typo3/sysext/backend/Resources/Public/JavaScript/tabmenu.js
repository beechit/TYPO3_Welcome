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
 * javascript functions regarding the "dyntabmenu" used throughout the TYPO3 backend
 */

var DTM_array = DTM_array || [];

	// if tabs are used in a popup window the array might not exists
if (!top.DTM_currentTabs) {
	top.DTM_currentTabs = [];
}

function DTM_activate(idBase,index,doToogle) {
		// Hiding all:
	if (DTM_array[idBase]) {
		for(var cnt = 0; cnt < DTM_array[idBase].length; cnt++) {
			if (DTM_array[idBase][cnt] !== idBase + '-' + index) {
				document.getElementById(DTM_array[idBase][cnt]+'-DIV').className = "tab-pane";
				// Only Overriding when Tab not disabled
				if (document.getElementById(DTM_array[idBase][cnt]+'-MENU').attributes.getNamedItem('class').value !== 'disabled') {
					document.getElementById(DTM_array[idBase][cnt]+'-MENU').attributes.getNamedItem('class').value = 'tab';
				}
			}
		}
	}

		// Showing one:
	if (document.getElementById(idBase+'-'+index+'-DIV')) {
		if (doToogle && document.getElementById(idBase+'-'+index+'-DIV').className === 'tab-pane active') {
			document.getElementById(idBase+'-'+index+'-DIV').className = "tab-pane";
			document.getElementById(idBase+'-'+index+'-MENU').attributes.getNamedItem('class').value = 'tab';
			top.DTM_currentTabs[idBase] = -1;
		} else {
			document.getElementById(idBase+'-'+index+'-DIV').className = "tab-pane active";
			document.getElementById(idBase+'-'+index+'-MENU').attributes.getNamedItem('class').value = 'active';
			top.DTM_currentTabs[idBase] = index;
		}
	}
	document.getElementById(idBase+'-'+index+'-MENU').attributes.getNamedItem('class').value = 'active';
}
function DTM_toggle(idBase,index,isInit) {
		// Showing one:
	if (document.getElementById(idBase+'-'+index+'-DIV')) {
		if (document.getElementById(idBase+'-'+index+'-DIV').className === 'tab-pane active') {
			document.getElementById(idBase+'-'+index+'-DIV').className = "tab-pane";
			if (isInit) {
				document.getElementById(idBase+'-'+index+'-MENU').attributes.getNamedItem('class').value = 'tab';
			}
			top.DTM_currentTabs[idBase+'-'+index] = 0;
		} else {
			document.getElementById(idBase+'-'+index+'-DIV').className = "tab-pane active";
			if (isInit) {
				document.getElementById(idBase+'-'+index+'-MENU').attributes.getNamedItem('class').value = 'active';
			}
			top.DTM_currentTabs[idBase+'-'+index] = 1;
		}
	}
}
