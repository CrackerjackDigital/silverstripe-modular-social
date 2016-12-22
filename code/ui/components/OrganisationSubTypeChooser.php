<?php
namespace Modular\UI\Components\Social;

use Config;
use FieldList;
use GroupedDropdownField;
use Modular\Types\Social\OrganisationSubType;
use Modular\Types\Social\OrganisationType;
use Modular\UI\Component;

class OrganisationSubTypeChooser extends Component {
	const IDFieldName = 'OrganisationSubTypeID';

	protected static $field_name = self::IDFieldName;
	protected static $field_label = 'Choose SocialOrganisation Type & Sub-Types';

	public function __construct($organisationSubTypeID = null, $placeholder = 'Choose SocialOrganisation Type & Sub-Types') {
		list($fieldName, $fieldLabel) = self::get_field_config();

		$tree = [];

		foreach (OrganisationType::get() as $orgType) {
			$tree[$orgType->Title] = [];

			foreach ($orgType->OrganisationSubTypes() as $subType) {
				$tree[$orgType->Title] += [
					$subType->ID => $subType->Title,
				];
			}
		}

		$fields = new FieldList([
			$field = new GroupedDropdownField(
				$fieldName,
				$fieldLabel,
				$tree,
				$organisationSubTypeID
			),
		]);
		$field->addExtraClass('select2field');
		$field->setAttribute('data-placeholder', $placeholder);
		$field->setAttribute('placeholder', $placeholder);
		$field->setAttribute('multiple', 'multiple');
		// $field->setName(self::IDFieldName . "[]");
		$field->setAttribute('name', self::IDFieldName . "[]");

		parent::__construct($fields);
	}

	public function setValue($value) {
		list($fieldName) = self::get_field_config();

		if ($innerField = $this->fieldByName($fieldName)) {
			$innerField->setValue($value);
		}
	}

	/**
	 * @return int
	 */
	public function getOrganisationTypeID() {
		if ($subTypeID = $this->getOrganisationSubTypeID()) {
			$subType = OrganisationSubType::get()->byID($subTypeID);
			if ($subType) {
				$type = OrganisationType::get()->byID($subType->OrganisationTypeID);
				if ($type) {
					return $type->ID;
				}
			}
		}
	}

	public function getOrganisationSubTypeID() {
		return $this->fieldByName(self::IDFieldName)->getValue();
	}

	public function setOrganisationTypeID($value) {
		// do nowt, this is gotten from the SubTypeID by SocialOrganisationType action.
		return $this;
	}

	public function setOrganisationSubTypeID($value) {
		$this->setValue($value);
		return $this;
	}

	/**
	 * Returns array of
	 * -    config.field_name
	 * -    _t.OrganisationSubType.field_title
	 *
	 * @return array
	 */
	public static function get_field_config() {
		$className = get_called_class();
		return [
			Config::inst()->get($className, 'field_name') ?: static::$field_name,
			_t("$className.Label", static::$field_label),
		];
	}
}