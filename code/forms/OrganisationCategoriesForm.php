<?php
namespace Modular\Forms;
use FieldList;
use FormAction;
use HiddenField;
use Modular\Actions\Editable;
use OrganisationTypesChooser;
use RequiredFields;

class OrganisationCategoriesForm extends SocialForm  {
	const Action = HasOrganisationCategories::Action;

	public function __construct($controller, $name, $id) {

		$fields = FieldList::create(
			OrganisationTypesChooser::create(),
			HiddenField::create("ID")->setValue($id)
		);

		$actions = FieldList::create(
			FormAction::create('categories')->setTitle("Save SocialOrganisation Types")->addExtraClass("btn btn-blue btn-large")
		);

		$validator = new RequiredFields();
		parent::__construct($controller, $name, $fields, $actions, $validator);

		if ($model = $controller->model(Editable::Action)) {
			$this->setFormAction($model->ActionLink(static::Action));
		}

	}

}