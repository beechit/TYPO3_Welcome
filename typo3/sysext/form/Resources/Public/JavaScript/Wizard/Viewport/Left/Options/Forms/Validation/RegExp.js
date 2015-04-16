Ext.namespace('TYPO3.Form.Wizard.Viewport.Left.Options.Forms.Validation');

/**
 * The regular expression validation rule
 *
 * @class TYPO3.Form.Wizard.Viewport.Left.Options.Forms.Validation.RegExp
 * @extends TYPO3.Form.Wizard.Viewport.Left.Options.Forms.Validation.Rule
 */
TYPO3.Form.Wizard.Viewport.Left.Options.Forms.Validation.RegExp = Ext.extend(TYPO3.Form.Wizard.Viewport.Left.Options.Forms.Validation.Rule, {
	/**
	 * @cfg {String} rule
	 *
	 * The name of this rule
	 */
	rule: 'regexp',

	/**
	 * Constructor
	 *
	 * Add the configuration object to this component
	 * @param config
	 */
	constructor: function(config) {
		Ext.apply(this, {
			configuration: {
				breakOnError: 0,
				showMessage: 1,
				message: TYPO3.l10n.localize('tx_form_system_validate_regexp.message'),
				error: TYPO3.l10n.localize('tx_form_system_validate_regexp.error'),
				expression: ''
			}
		});
		TYPO3.Form.Wizard.Viewport.Left.Options.Forms.Validation.RegExp.superclass.constructor.apply(this, arguments);
	}
});

Ext.reg('typo3-form-wizard-viewport-left-options-forms-validation-regexp', TYPO3.Form.Wizard.Viewport.Left.Options.Forms.Validation.RegExp);