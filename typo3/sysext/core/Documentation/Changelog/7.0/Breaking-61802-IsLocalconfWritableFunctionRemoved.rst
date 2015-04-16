==================================================================
Breaking: #61802 - deprecated isLocalconfWritable function removed
==================================================================

Description
===========

The function :code:`isLocalconfWritable()` :code:`from \TYPO3\CMS\Core\Utility\ExtensionManagementUtility` has been removed.
The boostrap now just checks for the existence of the file and redirects to the install tool if it doesn't exist.

Impact
======

Extensions that still use the function :code:`isLocalconfWritable()` won't work.


Affected installations
======================

A TYPO3 instance is affected if a 3rd party extension uses the removed function.


Migration
=========

Remove the call to this function. The bootstrap takes care to check the existence of the file.