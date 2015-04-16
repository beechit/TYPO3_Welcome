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
 * Microdata Schema Plugin for TYPO3 htmlArea RTE
 */
define('TYPO3/CMS/Rtehtmlarea/Plugins/MicrodataSchema',
	['TYPO3/CMS/Rtehtmlarea/HTMLArea/Plugin/Plugin',
	'TYPO3/CMS/Rtehtmlarea/HTMLArea/UserAgent/UserAgent',
	'TYPO3/CMS/Rtehtmlarea/HTMLArea/DOM/DOM',
	'TYPO3/CMS/Rtehtmlarea/HTMLArea/Util/Util'],
	function (Plugin, UserAgent, Dom, Util) {

	var MicrodataSchema = function (editor, pluginName) {
		this.constructor.super.call(this, editor, pluginName);
	};
	Util.inherit(MicrodataSchema, Plugin);
	Util.apply(MicrodataSchema.prototype, {

		/**
		 * This function gets called by the class constructor
		 */
		configurePlugin: function (editor) {

			/**
			 * Registering plugin "About" information
			 */
			var pluginInformation = {
				version		: '1.0',
				developer	: 'Stanislas Rolland',
				developerUrl	: 'http://www.sjbr.ca/',
				copyrightOwner	: 'Stanislas Rolland',
				sponsor		: 'SJBR',
				sponsorUrl	: 'http://www.sjbr.ca/',
				license		: 'GPL'
			};
			this.registerPluginInformation(pluginInformation);

			/**
			 * Registering the buttons
			 */
			var button = this.buttonList[0];
			var buttonId = button[0];
			var buttonConfiguration = {
				id		: buttonId,
				tooltip		: this.localize(buttonId + '-Tooltip'),
				iconCls		: 'htmlarea-action-' + button[2],
				action		: 'onButtonPress',
				context		: button[1]
			};
			this.registerButton(buttonConfiguration);
			return true;
		},

		/**
		 * The list of buttons added by this plugin
		 */
		buttonList: [
			['ShowMicrodata', null, 'microdata-show']
		],
		/*
		 * Default configuration values for dialogue form fields
		 */
		configDefaults: {
			combo: {
				editable: true,
				selectOnFocus: true,
				typeAhead: true,
				triggerAction: 'all',
				forceSelection: true,
				mode: 'local',
				valueField: 'name',
				displayField: 'label',
				helpIcon: true,
				tpl: '<tpl for="."><div ext:qtip="{comment}" style="text-align:left;font-size:11px;" class="x-combo-list-item">{label}</div></tpl>'
			}
		},
		/*
		 * This function gets called when the editor is generated
		 */
		onGenerate: function () {
				// Create the types and properties stores
			this.typeStore = Ext.StoreMgr.lookup('itemtype');
			if (!this.typeStore) {
				this.typeStore = new Ext.data.JsonStore({
					autoDestroy:  false,
					autoLoad: true,
					fields: [{ name: 'label'}, { name: 'name'},  { name: 'comment'}, { name: 'subClassOf'}],
					listeners: {
						load: {
							fn: this.addMicrodataMarkingRules,
							scope: this
						}
					},
					root: 'types',
					storeId: 'itemtype',
					url: this.editorConfiguration.schemaUrl
				});
			} else {
				this.addMicrodataMarkingRules(this.typeStore);
			}
			this.typeStore = Ext.StoreMgr.lookup('itemprop');
			if (!this.propertyStore) {
				this.propertyStore = new Ext.data.JsonStore({
					autoDestroy:  false,
					autoLoad: true,
					fields: [{ name: 'label'}, { name: 'name'},  { name: 'comment'}, { name: 'domain'}, { name: 'range'}],
					listeners: {
						load: {
							fn: this.addMicrodataMarkingRules,
							scope: this
						}
					},
					root: 'properties',
					storeId: 'itemprop',
					url: this.editorConfiguration.schemaUrl
				});
			} else {
				this.addMicrodataMarkingRules(this.propertyStore);
			}
		},
		/*
		 * This function adds rules to the stylesheet for language mark highlighting
		 * Model: body.htmlarea-show-language-marks *[lang=en]:before { content: "en: "; }
		 * Works in IE8, but not in earlier versions of IE
		 */
		addMicrodataMarkingRules: function (store) {
			var styleSheet = this.editor.document.styleSheets[0];
			store.each(function (option) {
				var selector = 'body.htmlarea-show-microdata *[' + store.storeId + '="' + option.get('name') + '"]:before';
				var style = 'content: "' + option.get('label') + ': "; font-variant: small-caps;';
				var rule = selector + ' { ' + style + ' }';
				try {
					styleSheet.insertRule(rule, styleSheet.cssRules.length);
				} catch (e) {
					this.appendToLog('onGenerate', 'Error inserting css rule: ' + rule + ' Error text: ' + e, 'warn');
				}
				return true;
			}, this);
		},

		/**
		 * This function gets called when a button was pressed.
		 *
		 * @param	object		editor: the editor instance
		 * @param	string		id: the button id or the key
		 *
		 * @return	boolean		false if action is completed
		 */
		onButtonPress: function (editor, id, target) {
			// Could be a button or its hotkey
			var buttonId = this.translateHotKey(id);
			buttonId = buttonId ? buttonId : id;
			switch (buttonId) {
				case 'ShowMicrodata':
					this.toggleMicrodata();
					break;
				default	:
					break;
			}
			return false;
		},

		/**
		 * Toggles the display of microdata
		 *
		 * @param	boolean		forceMicrodata: if set, microdata is displayed whatever the current state
		 *
		 * @return	void
		 */
		toggleMicrodata: function (forceMicrodata) {
			var body = this.editor.document.body;
			if (!Dom.hasClass(body, 'htmlarea-show-microdata')) {
				Dom.addClass(body,'htmlarea-show-microdata');
			} else if (!forceMicrodata) {
				Dom.removeClass(body,'htmlarea-show-microdata');
			}
		},
		/*
		 * This function builds the configuration object for the Microdata fieldset
		 *
		 * @param object element: the element being edited, if any
		 * @param object configured properties for the microdata fields
		 *
		 * @return object the fieldset configuration object
		 */
		buildMicrodataFieldsetConfig: function (element, properties) {
			var typeStore = Ext.StoreMgr.lookup('itemtype');
			var propertyStore = Ext.StoreMgr.lookup('itemprop');
			var itemsConfig = [];
			this.inheritedType = 'none';
			var parent = element.parentNode;
			while (parent && !/^(body)$/i.test(parent.nodeName)) {
				if (parent.getAttribute('itemtype')) {
					this.inheritedType = parent.getAttribute('itemtype');
					break;
				} else {
					parent = parent.parentNode;
				}
			}
			var selectedType = element && element.getAttribute('itemtype') ? element.getAttribute('itemtype') : 'none';
			var selectedProperty = element && element.getAttribute('itemprop') ? element.getAttribute('itemprop') : 'none';
			itemsConfig.push({
				xtype: 'displayfield',
				itemId: 'currentItemType',
				fieldLabel: this.getHelpTip('currentItemType', 'currentItemType'),
				style: {
					fontWeight: 'bold'
				},
				value: this.inheritedType
			});
			itemsConfig.push(Ext.applyIf({
				xtype: 'combo',
				fieldLabel: this.getHelpTip('itemprop', 'itemprop'),
				hidden: this.inheritedType === 'none',
				itemId: 'itemprop',
				store: propertyStore,
				value: selectedProperty,
				width: ((properties['itemprop'] && properties['itemprop'].width) ? properties['itemprop'].width : 300)
			}, this.configDefaults['combo']));
			itemsConfig.push({
				itemId: 'itemscope',
				fieldLabel: this.getHelpTip('itemscope', 'itemscope'),
				listeners: {
					check: {
						fn: this.onItemScopeChecked,
						scope: this
					}
				},
				style: {
					marginBottom: '5px'
				},
				checked: element ? (element.getAttribute('itemscope') === 'itemscope') : false,
				xtype: 'checkbox'
			});
			itemsConfig.push(Ext.applyIf({
				xtype: 'combo',
				fieldLabel: this.getHelpTip('itemtype', 'itemtype'),
				hidden: element && !element.getAttribute('itemscope'),
				hideMode: 'visibility',
				itemId: 'itemtype',
				store: typeStore,
				value: selectedType,
				width: ((properties['itemtype'] && properties['itemtype'].width) ? properties['itemtype'].width : 300)
			}, this.configDefaults['combo']));
			return {
				xtype: 'fieldset',
				itemId: 'microdataFieldset',
				title: this.getHelpTip('', 'microdata'),
				defaultType: 'textfield',
				defaults: {
					labelSeparator: ':'
				},
				items: itemsConfig,
				labelWidth: 100,
				listeners: {
					afterrender: {
						fn: this.onMicroDataRender,
						scope: this
					}
				}
			};
		},
		/*
		 * Handler invoked when the Microdata fieldset is rendered
		 */
		onMicroDataRender: function (fieldset) {
			this.fieldset = fieldset;
			var typeStore = Ext.StoreMgr.lookup('itemtype');
			var index = typeStore.findExact('name', this.inheritedType);
			if (index !== -1) {
					// If there is an inherited type, set the label
				var inheritedTypeName = typeStore.getAt(index).get('label');
				this.fieldset.find('itemId', 'currentItemType')[0].setValue(inheritedTypeName);
					// Filter the properties by the inherited type, if any
				var propertyCombo = this.fieldset.find('itemId', 'itemprop')[0];
				var selectedProperty = propertyCombo.getValue();
					// Filter the properties by the inherited type, if any
				this.filterPropeties(this.inheritedType, selectedProperty);
			}
		},
		/*
		 * Handler invoked when the itemscope checkbox is checked/unchecked
		 *
		 */
		onItemScopeChecked: function (checkbox, checked) {
			this.fieldset.find('itemId', 'itemtype')[0].setVisible(checked);
			this.synch
		},
		/*
		 * Filter out properties not part of the selected type
		 */
		filterPropeties: function (type, selectedProperty) {
			var typeStore = Ext.StoreMgr.lookup('itemtype');
			var propertyStore = Ext.StoreMgr.lookup('itemprop');
			if (propertyStore.realSnapshot) {
				propertyStore.snapshot = propertyStore.realSnapshot;
				delete propertyStore.realSnapshot;
				propertyStore.clearFilter(true);
			}
			var index,
				superType = type,
				superTypes = [];
			while (superType) {
				superTypes.push(superType);
				index = typeStore.findExact('name', superType);
				if (index !== -1) {
					superType = typeStore.getAt(index).get('subClassOf');
				} else {
					superType = null;
				}
			}
			var superTypes = new RegExp( '^(' + superTypes.join('|') + ')$', 'i');
			propertyStore.filterBy(function (property) {
					// Filter out properties not part of the type
				return superTypes.test(property.get('domain')) || property.get('name') === 'none';
			});
				// Make sure the combo list is filtered
			propertyStore.realSnapshot = propertyStore.snapshot;
			propertyStore.snapshot = propertyStore.data;
			var propertyCombo = this.fieldset.find('itemId', 'itemprop')[0];
			propertyCombo.clearValue();
			propertyCombo.setValue(selectedProperty);
		},

		/**
		 * Set microdata attributes of the element
		 */
		setMicrodataAttributes: function (element) {
			if (this.fieldset) {
				var comboFields = this.fieldset.findByType('combo');
				for (var i = comboFields.length; --i >= 0;) {
					var field = comboFields[i];
					var itemId = field.getItemId();
					var value = field.getValue();
					switch (itemId) {
						case 'itemprop':
						case 'itemtype':
							element.setAttribute(itemId, (value === 'none') ? '' : value);
							break;
					}
				}
				var itemScopeField = this.fieldset.find('itemId', 'itemscope')[0];
				if (itemScopeField) {
					if (itemScopeField.getValue()) {
						element.setAttribute('itemscope', 'itemscope');
					} else {
						element.removeAttribute('itemscope');
						element.removeAttribute('itemtype');
					}
				}
			}
		},

		/**
		 * This function gets called when the toolbar is updated
		 */
		onUpdateToolbar: function (button, mode, selectionEmpty, ancestors, endPointsInSameBlock) {
			if (this.getEditorMode() === 'wysiwyg' && this.editor.isEditable()) {
				switch (button.itemId) {
					case 'ShowMicrodata':
						button.setInactive(!Dom.hasClass(this.editor.document.body, 'htmlarea-show-microdata'));
						break;
					default:
						break;
				}
			}
		}
	});

	return MicrodataSchema;

});
