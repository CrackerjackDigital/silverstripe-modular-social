<?php
class ProductAndServiceChooserField extends \Modular\UI\Component {
	const IDFieldName = 'OrganisationProductsAndServices';

	protected static $field_name = self::IDFieldName;
	protected static $field_label = 'Products And Services';
	protected static $value_seperator = ',';

	public function __construct($selectedProductAndServices = null, $allProductAndServices = null) {
		$allProductAndServices = $allProductAndServices ?: OrganisationProductAndServiceType::get()->sort('Title')->map()->toArray();

		list($fieldName, $fieldLabel) = self::get_field_config();

		$fields = new FieldList([
			$field = new Select2TagField($fieldName, $fieldLabel),
		]);
		$field->setValue($selectedProductAndServices);
		$field->setOptions($allProductAndServices);

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
		if ($model->hasField('ToProductAndServiceTypeID')) {
			$this->setValue($model->ToProductAndServiceTypeID);
		}
	}

	/**
	 * Set the inner field to the value passed in.
	 *
	 * @param mixed $value
	 * @return FormField|void
	 */
	public function setValue($value) {
		list($fieldName) = self::get_field_config();

		if ($innerField = $this->fieldByName($fieldName)) {
			$innerField->setValue($value);
		}
	}

	/**
	 * Given a list of CSV interests, return an array of IDs.
	 * @param $sentValue
	 * @return DataList of OrganisationProductAndServiceType .
	 */
	public static function decode($sentValue) {
		$titles = explode(self::tag_seperator(), $sentValue);
		return OrganisationProductAndServiceType::get()
			->filter('Title', $titles)
			->column('ID');
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

}