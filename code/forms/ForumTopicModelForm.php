<?php
namespace Modular\Forms;
use FieldList;
use FileAttachmentField;
use FormAction;
use Forum;
use HiddenField;
use RequiredFields;
use Select2Field;
use TextareaField;
use TextField;

/**
 *
 * Forum Form
 *
 **/

class ForumTopicForm extends SocialForm {

	public function __construct($controller, $name, $fields, $actions, $validator) {

		$fields = FieldList::create(

			TextField::create('Title', 'Title')->setAttribute('placeholder', 'Forum Topic Title'),
			TextareaField::create('Synopsis', 'Synopsis')->setAttribute('placeholder', 'Synopsis'),
			Select2Field::create("ForumID", "Forum")
				->setSource(Forum::get()->map("ID", "Title"))
				->setEmptyString("Please select Forum"),
			FileAttachmentField::create("Files", 'Attach files')
				->setMultiple(true)
				->setView('grid'),
			HiddenField::create("ID")

		);

		$actions = FieldList::create(
			FormAction::create('doSave')->setTitle("Save")->addExtraClass("btn btn-green")
		);

		$validator = new RequiredFields('Title', 'Synopsis', 'ForumID');
		parent::__construct($controller, $name, $fields, $actions, $validator);
	}
// var_dump($request);exit();
}