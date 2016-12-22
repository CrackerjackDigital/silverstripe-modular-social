<?php
namespace Modular\Forms\Social;

use FieldList;
use FileAttachmentField;
use FormAction;
use HiddenField;
use Modular\Actions\Editable;
use Modular\Forms\SocialForm;
use Modular\Relationships\Social\HasLogo;
use RequiredFields;

/**
 *
 * SocialOrganisation logo editing form
 *
 **/

class HasLogoForm extends SocialForm {
	const ActionName = HasLogo::ActionName;

	public function __construct($controller, $name, $id) {

		$fields = FieldList::create(
			FileAttachmentField::create(HasLogo::FieldName, '')
				->imagesOnly()
				->setView('list'),
			HiddenField::create("ID")->setValue($id)
		);

		$actions = FieldList::create(
			FormAction::create('updateLogo')->setTitle("Apply logo")->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields(HasLogo::FieldName);
		parent::__construct($controller, $name, $fields, $actions, $validator);

		if ($model = $controller->getModelInstance(Editable::ActionName)) {
			$this->setFormAction($model->ActionLink(self::ActionName));
		}
	}

}