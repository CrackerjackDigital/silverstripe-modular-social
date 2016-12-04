<?php
namespace Modular\Forms;
use FieldList;
use FileAttachmentField;
use HiddenField;
use Modular\Actions\Editable;
use TextareaField;

/**
 *
 *
 **/

class PostForm extends SocialForm {

	public function __construct($controller, $name, $fields, $actions, $validator) {

		$uploadField = $fields->fieldByName("AttachImages");
		if ($uploadField) {
			$uploadField->imagesOnly()->setMultiple(true)->setView('grid');
		} else {
			//creates field for uploads via ajax
			$fields = FieldList::create(
				TextareaField::create("Body"),
				new HiddenField('ID')
			);
		}

		if ($model = $controller->getModelInstance(Editable::Action)) {

			if ($model->ForumTopicID == 0) {
				$fields->push(FileAttachmentField::create("AttachImages", 'Add more images')
						->imagesOnly()
						->setMultiple(true)
						->setView('grid'));
			}
			$this->setFormAction($model->ActionLink(Editable::Action));
		}

		parent::__construct($controller, $name, $fields, $actions, $validator);

	}

}