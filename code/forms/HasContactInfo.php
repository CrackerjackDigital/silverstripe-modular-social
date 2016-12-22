<?php
namespace Modular\Forms\Social;
use EmailField;
use FieldList;
use FormAction;
use HiddenField;
use Modular\Actions\Editable;
use Modular\Forms\SocialForm;
use Modular\Relationships\Social\HasContactInfo;
use RequiredFields;
use TextField;

/**
 *
 * SocialOrganisation contact info editing form
 *
 **/

class HasContactInfoForm extends SocialForm {
	const ActionName = HasContactInfo::ActionName;

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

		if ($model = $controller->getModelInstance(Editable::ActionName)) {
			$this->setFormAction($model->ActionLink(static::Action));
		}

	}

}