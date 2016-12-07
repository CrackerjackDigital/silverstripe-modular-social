<?php
namespace Modular\Forms;
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
			FormAction::create('doRegistrationChecks')->setTitle("Sign up for free*")->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields(self::EmailFieldName, self::OrganisationFieldName);

		parent::__construct($controller, $name, $fields, $actions, $validator);
	}

	public function doRegistrationChecks(array $data, Form $form, SS_HTTPRequest $request) {
		//Check for existing member email address
		if ($member = DataObject::get_one("Member", "`Email` = '" . Convert::raw2sql($data[self::EmailFieldName]) . "'")) {
			//Set form data from submitted values
			Session::set("FormInfo.{$this->FormName()}.data", $data);

			//Set error message
			$form->addErrorMessage('Email', "Sorry, that email address already exists. \nPlease choose another.", 'bad');
			//Return back to form
			return $this->controller->redirectBack();
		}
		// save info into session for Registerable extension to pick up
		Session::set(self::transient_key(self::EmailFieldName), $data[self::EmailFieldName]);
		Session::set(self::transient_key(self::OrganisationFieldName), $data[self::OrganisationFieldName]);

		return $this->controller->redirect("member/register");
	}

	public static function transient_key($fieldName) {
		return __CLASS__ . '.' . $fieldName;
	}



}