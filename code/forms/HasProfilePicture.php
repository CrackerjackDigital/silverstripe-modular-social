<?php
namespace Modular\Forms\Social;

use Modular\Actions\Editable;
use Modular\Forms\SocialForm;
use Modular\Relationships\Social\HasProfilePicture;
use FieldList;
use FileAttachmentField;
use HiddenField;
use FormAction;
use RequiredFields;

/**
 *
 * Member profile picture editing form
 *
 **/

class HasProfilePictureForm extends SocialForm {
	const Action = HasProfilePicture::Action;

	public function __construct($controller, $name, $id) {

		$fields = FieldList::create(
			FileAttachmentField::create(HasProfilePicture::FieldName, '')
				->imagesOnly()
				->setView('list'),
			HiddenField::create("ID")->setValue($id)
		);

		$actions = FieldList::create(
			FormAction::create('updateProfilePicture')->setTitle("Apply Picture")->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields(HasProfilePicture::FieldName);
		parent::__construct($controller, $name, $fields, $actions, $validator);
		if ($model = $controller->getModelInstance(Editable::ActionName)) {
			$this->setFormAction($model->ActionLink(self::Action));
		}
	}

}