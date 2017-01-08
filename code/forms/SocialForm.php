<?php
namespace Modular\Forms;

use DisabledTransformation;
use FormField;
use Modular\Interfaces\SocialModelController;

class SocialForm extends \MosaicForm {
	private static $allowed_actions = [
		'save',
	];

	/**
	 * SocialForm constructor.
	 *
	 * @param \Controller|SocialModelController $controller
	 * @param string      $name
	 * @param \FieldList  $fields
	 * @param \FieldList  $actions
	 * @param null        $validator
	 */
	public function __construct($controller, $name, $fields, $actions, $validator = null) {
		/** @var FormField $field */
		foreach ($fields as $field) {
			$field->setAttribute('placeholder', $field->attrTitle());
			$field->addExtraClass($field->getName());

			//added custom field templates to prevent cms fields overwriting
			switch ($field->Type()) {
				case 'text':
				case 'select2':
				case 'extraimages':
					$field->setFieldHolderTemplate("MosaicFormField_holder");
					break;

				default:
					//do nothing
					break;
			}
		}

		parent::__construct($controller, $name, $fields, $actions, $validator = null);

		if ($controller->hasMethod('getModelClass')) {
			$modelClass = $controller->modelClassName();

			$this->addExtraClass($modelClass);
			$this->setAttribute('data-class', $modelClass);
		}
	}

	/**
	 * Overload for typehint
	 * @return \Controller|SocialModelController
	 */
	public function getController() {
		return parent::getController();
	}

	public function allowedActions($limitToClass = null) {
		return parent::allowedActions($limitToClass);
	}

	/**
	 * We don't actually make form fields into a read-only version, just disable them.
	 */
	public function makeReadOnly() {
		$this->transform(new DisabledTransformation());
	}

	public function setSessionMessage($message = "", $type = 'success') {
		if ($message != "") {
			$this->getController()->setSessionMessage($message, $type);
		}

	}

}