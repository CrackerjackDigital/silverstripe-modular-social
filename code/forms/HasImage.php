<?php
namespace Modular\Forms\Social;

use FieldList;
use FileAttachmentField;
use FormAction;
use HiddenField;
use Modular\Actions\Editable;
use Modular\Forms\SocialForm;
use RequiredFields;

/**
 *
 * SocialOrganisation logo editing form
 *
 **/

class HasImageForm extends SocialForm {
	const ActionName = '';
	const FieldName = '';

	public function __construct($controller, $name, $id) {

		$fields = FieldList::create(
			FileAttachmentField::create(static::FieldName, '')
				->imagesOnly()
				->setView('grid'),
			HiddenField::create("ID")->setValue($id)
		);

		$actions = FieldList::create(
			FormAction::create('update')->setTitle("Apply cover image")->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields(static::FieldName);
		parent::__construct($controller, $name, $fields, $actions, $validator);

		if ($model = $controller->getModelInstance(Editable::ActionName)) {
			$this->setFormAction($model->ActionLink(static::Action));
		}

	}

}