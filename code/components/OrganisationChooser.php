<?php
class OrganisationChooserField extends \Modular\UI\Component {
	const IDFieldName = 'OrganisationID';
	const CreateNewFieldName = '_CreateNewOrganisation';

	protected static $field_name = self::IDFieldName;

	protected static $field_label = 'Already Registered Organisation';

	protected static $value_seperator = ',';

	public function __construct($selectedOrganisationID = null, $allOrganisations = null) {
		// if a list of Organisations not supplied then get all
		$allOrganisations = $allOrganisations
		?: Organisation::get()
			->sort('Title')
			->map()
			->toArray();

		list($fieldName, $fieldLabel) = self::get_field_config();

		$fields = new FieldList([
			$dropdown = new Select2Field(
				$fieldName,
				$fieldLabel,
				$allOrganisations,
				$selectedOrganisationID
			),
			$checkbox = new CheckboxField(
				self::CreateNewFieldName,
				_t('OrganisationChooserField.CreateNewLabel', 'Create new organisation')
			),
		]);
		$dropdown->setDataModel('Organisation');
		$dropdown->setEmptyString('');
		$dropdown->setAttribute('placeholder', 'Choose an organisation')->setAttribute('data-placeholder', 'Choose an organisation');
		$checkbox->setDataModel('Organisation');

		parent::__construct($fields);
	}

	/**
	 * If model passed in is an Organisation then update this field from model fields.
	 *
	 * @param DataObject $model
	 * @param FieldList $fields
	 * @param $mode
	 * @param array $requiredFields
	 */
	public function updateFieldFromModel(DataObject $model, $mode, $fieldInfo) {
		if ($model instanceof Organisation) {
			$this->setValue($model->ID);
		}
	}

	/**
	 * Returns array of
	 * -    config.field_name
	 * -    _t.HasInterests.field_title
	 * -    config.value_seperator
	 *
	 * @return array
	 */
	protected static function get_field_config() {
		$className = get_called_class();
		return [
			Config::inst()->get($className, 'field_name') ?: static::$field_name,
			_t("$className.Label", static::$field_label),
			Config::inst()->get($className, 'value_seperator') ?: static::$value_seperator,
		];
	}

	/**
	 * Configure for a given mode e.g. 'view', 'new' or 'edit'
	 */
	public function setMode($mode) {
		if ($mode == 'view') {
			$this->showCreateNew(false);
		}
	}

	/**
	 * Override to return the 'inner' field's name so can be used in search etc as is the model field name.
	 * @return string
	 */
	public function getName() {
		return self::IDFieldName;
	}

	/**
	 * Return the inner fields value.
	 */
	public function getValue() {
		return $this->children->fieldByName(self::IDFieldName)->Value();
	}

	/**
	 * Set selected values.
	 *
	 * @param array $value
	 * @return $this
	 */
	public function setValue($value) {
		$this->children->fieldByName(self::IDFieldName)->setValue($value);
		return $this;
	}

	/**
	 * Set available values.
	 * @param array $options
	 * @return $this
	 */
	public function setOptions(array $options) {
		$this->children->fieldByName(self::IDFieldName)->setOptions($options);
		return $this;
	}

	public static function decode($sentValue) {
		return $sentValue;
	}

	/**
	 * Adds or removes 'hidden' css class on CreateNewOrganisation checkbox.
	 *
	 * @param $show boolean true to show, false to hide.
	 */
	public function showCreateNew($show) {
		$field = $this->children->fieldByName(self::CreateNewFieldName);
		if ($show) {
			$field->removeExtraClass('hidden');
		} else {
			$field->addExtraClass('hidden');
		}
		return $this;
	}

}