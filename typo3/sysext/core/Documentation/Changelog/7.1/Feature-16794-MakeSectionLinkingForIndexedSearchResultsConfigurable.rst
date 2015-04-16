===========================================================
Feature: #16794 - Linking of Indexed Search result sections
===========================================================

Description
===========

Per default the section headlines of indexed search results are links.
It is possible to disable those links, thus having the sections
displayed as simple text.

.. code-block:: typoscript

	plugin.tx_indexedsearch.linkSectionTitles = 0

Will result in not linked section headlines.
The setting is per default set to 1 in order to preserve current behaviour.
