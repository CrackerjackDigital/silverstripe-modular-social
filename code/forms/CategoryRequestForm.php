<?php
namespace Modular\Forms;

use FieldList;
use FormAction;
use HiddenField;
use Modular\Actions\Editable;
use RequiredFields;
use TextareaField;

class CategoryRequestForm extends \Modular\Forms\SocialForm {
	public function __construct($controller, $name, $id) {

		$fields = FieldList::create(
			TextareaField::create("CategoryName", "")
				->setAttribute("placeholder", 'New Category Name'),

			HiddenField::create("ID")->setValue($id)
		);

		$actions = FieldList::create(
			FormAction::create('RequestCategory')->setTitle("Send Request")->addExtraClass("btn btn-blue")
		);

		$validator = new RequiredFields("CategoryName");
		parent::__construct($controller, $name, $fields, $actions, $validator);
		if ($model = $controller->getModelInstance(Editable::Action)) {
			$this->setFormAction($model->ActionLink("category-request"));
		}
	}

}