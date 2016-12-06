<?php
namespace Modular\Forms;
use EmailField;
use FieldList;
use FormAction;
use HiddenField;
use Modular\Actions\Editable;
use RequiredFields;
use TextField;

/**
 *
 * SocialOrganisation contact info editing form
 *
 **/

class HasContactInfoForm extends SocialForm {
	const Action = HasContactInfo::Action;

	public function __construct($controller, $name, $organisationId) {

		$fields = FieldList::create(
			TextField::create("Address", 'Address')->setAttribute('placeholder', 'Address'),
			TextField::create("Location", "Location")->setAttribute('placeholder', 'Location'),

			TextField::create("PhoneNumber", 'Phone Number')->setAttribute('placeholder', 'Phone Number'),
			EmailField::create("Email", 'Email Address')->setAttribute('placeholder', 'Email Address'),
			HiddenField::create("OrganisationID")->setValue($organisationId),
			HiddenField::create("ID")
		);

		$actions = FieldList::create(
			FormAction::create('saveContactInfo')->setTitle("Save")->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields(["Address", "Location", "PhoneNumber", "Email"]);
		parent::__construct($controller, $name, $fields, $actions, $validator);

		if ($model = $controller->getModelInstance(Editable::Action)) {
			$this->setFormAction($model->ActionLink(static::Action));
		}

	}

}