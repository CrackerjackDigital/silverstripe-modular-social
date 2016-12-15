<?php
namespace Modular\Forms\Social;
use Convert;
use DataObject;
use EmailField;
use FieldList;
use \Form;
use FormAction;
use RequiredFields;
use Session;
use SS_HTTPRequest;
use TextField;

/**
 *
 * Initial signup form
 * It validates user's email address and organisation name
 *
 **/

class InitSignUpForm extends Form {
	const EmailFieldName = 'Email';
	const OrganisationFieldName = 'OrganisationName';

	public function __construct($controller, $name) {

		$fields = FieldList::create(
			EmailField::create(self::EmailFieldName, '')->setAttribute('placeholder', 'Email Address'),

			TextField::create(self::OrganisationFieldName, '')->setAttribute('placeholder', 'Company Name')
		);

		$actions = FieldList::create(
			(new FormAction('signup', "Sign up for free*"))->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields(self::EmailFieldName, self::OrganisationFieldName);

		parent::__construct($controller, $name, $fields, $actions, $validator);
	}

	public static function transient_key($fieldName) {
		return __CLASS__ . '.' . $fieldName;
	}
}