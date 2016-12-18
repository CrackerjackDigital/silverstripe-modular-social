<?php
namespace Modular\Extensions\Model;

use ClassInfo;
use Config;
use Controller;
use DataList;
use DataObject;
use DropdownField;
use FieldList;
use FileAttachmentField;
use FormField;
use Modular\Actions\Createable;
use Modular\Actions\Editable;
use Modular\Actions\Listable;
use Modular\Actions\Registerable;
use Modular\Actions\Viewable;
use Modular\debugging;
use Modular\enabler;
use Modular\Exceptions\Social as Exception;
use Modular\Forms\SocialForm;
use Modular\Forms\SocialForm as SocialModelForm;
use Modular\Interfaces\SocialModel as SocialModelInterface;
use Modular\ModelExtension;
use Modular\reflection;
use Modular\Types\SocialEdgeType as SocialEdgeType;
use RequiredFields;
use UploadField;

/**
 * Adds common functionality for a 'SocialModel'.
 *
 * Class SocialModel
 */
class SocialModel extends ModelExtension implements SocialModelInterface  {
	use debugging;
	use enabler;
	use reflection;

	// models are suffixed e.g. 'SocialOrganisation', except where external such as 'Member'
	const ModelClassNameSuffix = 'Model';

	const ModelHTMLAttributeName = 'model';

	/**
	 * Checks:
	 *  -   The current user is the model's Creator
	 *  -   via SocialEdgeType.check_permission if we can perform the requested action.
	 *
	 * @param        $actionCodes
	 * @param string $source where call is being made from, e.g. a controller will set this to 'action' on checking allowed_actions
	 * @return bool|int
	 * @throws \SS_HTTPResponse_Exception
	 */
	public function canDoIt($actionCodes, $source = '') {
		$source = $source ?: \Member::currentUser();

		$canDoIt = SocialEdgeType::check_permission(
			SocialMember::current_or_guest(), $this->getModelInstance(), $actionCodes
		);
		if ($source && !$canDoIt) {
			if ($source == 'action') {
				Controller::curr()->httpError('403', "Sorry, you are not permitted to do do that");
			}
		}
		return $canDoIt;
	}

	/**
	 * Check that 'EDT' permission exists for this model.
	 *
	 * @param null $member
	 * @return bool|int
	 */
	public function canEdit($member = null) {
		return $this->canDoIt(Editable::ActionCode);
	}

	/**
	 * Check that 'VEW' permission exists for this model.
	 *
	 * @param null $member
	 * @return bool|int
	 */
	public function canView($member = null) {
		return $this->canDoIt(Viewable::ActionCode);
	}

	/**
	 * Provide a form for viewing this model, fields are still form fields just disabled.
	 *
	 * @return SocialModelForm
	 */

	public function ViewForm() {
		$form = $this->formForMode(Viewable::ActionName);
		// NB this is overridded in SocialForm to just disable the fields.
		$form->makeReadOnly();
		return $form;
	}

	/**
	 * Provide a form for viewing this model, fields are still form fields just disabled.
	 *
	 * @return SocialModelForm
	 */

	public function ListItemForm() {
		$form = $this->formForMode(Listable::ActionName);
		// NB this is overridded in SocialForm to just disable the fields.
		$form->makeReadOnly();
		return $form;
	}

	/**
	 * Provides a form for editing this model.
	 *
	 * @return SocialModelForm
	 */
	public function EditForm() {
		return $this->formForMode(Editable::ActionName);
	}

	public function getModelID() {
		return $this()->ID;
	}

	/**
	 * Return this extensions owner. This is for orthogonality with Controller extensions.
	 *
	 * @return mixed
	 */
	public function getModelInstance() {
		return $this();
	}

	/**
	 * Return this extensions owners class. This is for orthognality with Controller extensions.
	 *
	 * @return string
	 */
	public function getModelClass() {
		return $this()->ClassName;
	}

	/**
	 * Return a form for this model used by different modes. May be the same form between modes.
	 *
	 * @param string $mode used to select which fields will be used from config.fields_for_mode
	 * @return \Modular\Forms\SocialForm
	 */
	public function formForMode($mode) {
		$formClassName = $this->getFormName();

		list($fields, $requiredFields) = $this->getFieldsForMode($mode);

		$validator = new RequiredFields($requiredFields);

		// get the actions depending on the mode from the controller. Controller extensions will add their actions here too.
		//        $actions = $this->getActionsForMode($mode);
		$actions = new FieldList();

		if (ClassInfo::exists($formClassName)) {
			$form = new $formClassName($this, $this->getFormName(), $fields, $actions, $validator);
		} else {
			$form = new SocialForm($this, $this->getFormName(), $fields, $actions, $validator);
		}
		$form->setDataModel($this());
		$form->disableSecurityToken();

		// we want to post back to the url which showed us.
		$form->setFormAction($this->ActionLink($mode));

		if ($this->canEdit()) {
			$form->addExtraClass('editable');
		} else {
			$form->addExtraClass('readonly');
		}

		return $form;
	}

	/**
	 * Return <ModelClass>_Form as form name to use in templates for this class.
	 *
	 * @return string
	 */
	private function getFormName() {
		return $this()->class . "Form";
	}

	/**
	 * Returns config.route_part of the extended model, or lowercase config.singular_name if not set.
	 *
	 * So SocialOrganisation -> /organisation
	 *
	 * @sideeffect Triggers a Notice user error if config.route_part is not set and using config.singular_name
	 *
	 * @return string
	 */
	public function getRoutePart() {
		if (!$part = $this()->config()->get('route_part')) {
			user_error("No config.route_part declared on {$this()->class}", "notice");
			$part = strtolower($this()->singular_name());
		}
		return $part;
	}

	/**
	 * Returns fields for a particular mode from the extended model decorated via updateFieldsForMode and
	 * decorateFields calls on the extended model. Normally this would be handled by the model deriving
	 * from social model however this will be called instead via extension mechanism if that is not the case.
	 *
	 * @param $mode
	 * @return array
	 */
	public function getFieldsForMode($mode) {
		return static::fields_for_mode($this(), $mode);
	}

	/**
	 * Return a form from the extended model for a mode.
	 *
	 * @param string $mode
	 * @return Form|null
	 */
	public function getFormForMode($mode) {
		return static::form_for_mode($this(), $mode);
	}

	/**
	 * Returns fields for a model in a particular mode/action. Fields included
	 * are taken from the models config.fields_for_mode keyed by $action.
	 * Fields are initially from getFrontEndFields, fields not returned but in
	 * the fields_for_mode map are added as HiddenFields (for e.g. ID field).
	 * If no fields_for_mode map for a mode if specified on the model then all
	 * frontEndFields are returned.
	 *
	 * @param SocialModelInterface|DataObject $model
	 * @param                                 $mode
	 * @return array [FieldList fields, array requiredFields]
	 */
	public static function fields_for_mode($model, $mode) {
		$frontEndFields = $model->getFrontEndFields();

		$requiredFields = [];

		$allModesFields = $model->config()->get('fields_for_mode') ?: [];

		// check we have custom action fields defined, defined for the action, and the action array isn't empty.
		if (isset($allModesFields[ $mode ])
			&& ($modeFields = $allModesFields[ $mode ])
		) {

			// filter out fields which don't exists in the modeFields for this mode.
			$fields = new FieldList($frontEndFields->filterByCallback(
				function ($field) use ($modeFields) {
					return array_key_exists(
						$field->getName(),
						$modeFields
					);
				}
			)->toArray());

			// iterate through the fields and see if they need replacing, are required or have a different label
			/** @var FormField $field */
			foreach ($fields as $field) {

				$fieldInfo = $modeFields[ $field->getName() ];

				list($fieldName, $fieldType, $fieldLabel, $required) = self::decode_field_info($field, $field->getName(), $fieldInfo, $requiredFields);

				// if fieldType doesn't match that from the defined fields_for_mode replace with correct type.
				if ($fieldType != $field->class) {

					$newField = new $fieldType(
						$fieldName,
						$fieldLabel
					);
					$fields->replaceField($fieldName, $newField);
				}
				if ($required) {
					$field->setAttribute('required', 'required')->setAttribute('aria-required', 'true');
				}

			}
			// add fields which don't exist in frontEndFields as new fields using
			foreach ($modeFields as $fieldName => $fieldInfo) {

				if (!$field = $fields->fieldByName($fieldName)) {

					list($fieldName, $fieldType, $fieldLabel, $required) = self::decode_field_info(null, $fieldName, $fieldInfo, $requiredFields);

					/** @var FormField $field */
					$field = new $fieldType(
						$fieldName,
						$fieldLabel
					);

					$fields->push($field);

					if ($required) {
						$field->setAttribute('required', 'required')->setAttribute('aria-required', 'true');
					}
				}
			}
			$fields->changeFieldOrder(array_keys($modeFields));

		} else {
			// no fields in map on model, use all frontEndFields, need to set the values from the model.
			$fields = $frontEndFields;
		}

		// now set the field values from the model and set the model class
		/** @var FormField $field */
		foreach ($fields as &$field) {
			if ($field->hasMethod('updateFieldFromModel')) {
				$field->updateFieldFromModel($model, $mode);
			} else {

				$value = self::get_field_value($field, $model);

				$field->setValue($value);
				// 2015-11-12 this is handled by Mosaic now
//                $field->setAttribute(self::ModelHTMLAttributeName, $model->class);
			}
		}

		// call model to see if it wants to update it's fields for this mode.
		$model->extend('updateFieldsForMode', $model, $fields, $mode, $requiredFields);

		$model->extend('decorateFields', $fields, $mode);

		return [$fields, $requiredFields];
	}

	/**
	 * Returns a form for the extended model in supplied mode.
	 *
	 * e.g. PostReply::form_for_mode('reply') will return
	 * form with PostReply.config.fields_for_mode['reply'] fields.
	 *
	 * @param SocialModelInterface|DataObject|SocialModel $model
	 * @param                                              $mode
	 * @return mixed
	 */
	public static function form_for_mode(SocialModelInterface $model, $mode) {
		if (!$model->hasMethod('formForMode')) {
			return $model->formForMode($mode);
		}
	}

	/**
	 * Models provide an array of fields to show for each mode where the key is
	 * the name and the value is either:
	 * - a string which is the required field type
	 * - a boolean which is the 'requiredness' (true for required)
	 * - an array of 2 members in order: type and requiredness
	 * - an array of three members in order: type, requiredness and label
	 *
	 * @param FormField $field
	 * @param           $fieldName
	 * @param           $fieldInfo
	 * @param array     $requiredFields
	 * @return array
	 * @throws Exception
	 */
	protected static function decode_field_info(FormField $field, $fieldName, &$fieldInfo, array &$requiredFields) {
		if ($field instanceof FormField) {
			$fieldLabel = $field->attrTitle();
			$fieldType = $field->class;
		} else {
			$fieldLabel = $fieldName;
			$fieldType = 'TextField';
		}
		$required = false;

		// decode information for this field, maybe boolean for required or array with more info
		if (is_array($fieldInfo)) {
			if (2 === count($fieldInfo)) {
				// info array as fieldType and required flag
				list($fieldType, $required) = $fieldInfo;

			} else if (3 === count($fieldInfo)) {

				// info array as fieldType, required flag and label
				list($fieldType, $required, $fieldLabel) = $fieldInfo;

			} else {
				// we only understand one or two items in the array
				throw new Exception("Invalid count in field info array");
			}

		} elseif (is_bool($fieldInfo)) {

			// value is requiredness
			$required = $fieldInfo;

		} elseif ($fieldInfo) {

			// value is field type
			$fieldType = $fieldInfo;

		}
		if ($required) {
			$requiredFields[] = $fieldName;
		}
		return [$fieldName, $fieldType, $fieldLabel, $required];
	}

	protected static function get_field_value(FormField $field, DataObject $model) {
		$fieldName = $field->getName();

		$value = null;

		if ($model->hasMethod($fieldName)) {
			if ($relationClass = $model->getRelationClass($fieldName)) {
				// method is a relationship we can handle one-to-many.

				if ($field instanceof UploadField || $field instanceof FileAttachmentField) {
					if ($model->hasField($fieldName . 'ID')) {
						$value = [
							'Files' => [
								$model->{$fieldName . 'ID'},
							],
						];
					} else {
						$value = [
							'Files' => $model->$fieldName()->column('ID') ?: [],
						];
					}
				} else {
					if ($model->hasField($fieldName . 'ID')) {
						$value = $model->{$fieldName . 'ID'};
					} else {
						// TODO: is this a dangling condition or should it never be met?
					}
				}

			} else {
				// just call the method
				$value = $model->$fieldName();
			}
		} else {
			$value = $model->$fieldName;
		}
		return $value;
	}

	/**
	 * Return a link of format /model/id/action e.g. /organisation/10/edit .
	 *
	 * @param $action
	 * @return String
	 */
	public function ActionLink($action, $includeID = true) {
		$action = strtolower($action);

		return Controller::curr()->join_links(
			'/',
			strtolower($this()->config()->get('route_part') ?: $this()->class),
			($action === Createable::ActionName || $action === Registerable::ActionName)
				? null
				: $includeID
				? $this()->ID
				: null,
			$action
		);
	}

	/**
	 * Returns either a link to edit or view depending on permissions. Returns nothing
	 * if can't view.
	 *
	 * @return String
	 */
	public function EditOrViewLink() {
		if ($this->canView()) {
			return Controller::curr()->join_links(
				'/',
				strtolower($this()->config()->get('route_part') ?: $this()->class),
				$this()->ID,
				Viewable::ActionName
			);
		}
	}

	/**
	 * Sets the model class a field belongs to, e.g. for use by Searchable and for styling.
	 *
	 * @param FormField $field
	 * @param           $modelClass
	 */
	public static function set_field_model_class(FormField $field, $modelClass) {
		$field->setAttribute(SocialModel::ModelHTMLAttributeName, $modelClass);
	}

	/**
	 * Returns the model class a field belongs to.
	 *
	 * @param FormField $field
	 * @return string
	 */
	public static function get_field_model_class(FormField $field) {
		return $field->getAttribute(SocialModel::ModelHTMLAttributeName);
	}

	/**
	 * Decorate fields as required for framework/design:
	 * - reads alternate label from lang file for ModelName.FieldNameLabel if available
	 * - sets placeholder for HTML5 aware browsers
	 * - adds the field name to the field for output in field holder as data-field and css class
	 *
	 *
	 * @param FieldList $fields
	 * @param           $mode - unused right now
	 */
	public function decorateFields(FieldList $fields, $mode) {
		/** @var FormField $field */
		foreach ($fields as $field) {
			$fieldName = $field->getName();
			$modelClass = $this()->class;

			$field->setAttribute('data-field', $fieldName);
			$field->addExtraClass($fieldName);

			$title = $field->Title();

			$langTitle = _t("$modelClass.{$fieldName}Label", $title);
			if ($langTitle != $title) {
				$field->setTitle($langTitle);
			}
			if (!$field instanceof UploadField) {
				if (!$field->getAttribute('placeholder')) {
					$field->setAttribute('placeholder', $field->getAttribute('data-placeholder') ?: $field->attrTitle());
				}
			}

			if ($field instanceof DropdownField) {
				if (!trim($field->getEmptyString())) {
					$field->setEmptyString($field->attrTitle());
				}
			}
			if (isset($field->children)) {
				$this->decorateFields($field->children, $mode);
			}
		}
	}



	/**
	 * Return the owner's route_part in the case of Model Extensions.
	 *
	 * @return string
	 */
	public function endpoint() {
		return $this()->config()->get('route_part');
	}

	/**
	 * Remove fields from the field list which are defined in the extension, e.g. they may be replaced with a widget.
	 *
	 * @param FieldList $fields
	 * @param bool      $removeDBFields
	 * @param bool      $removeHasOneFields
	 */
	protected static function remove_own_fields(FieldList $fields, $removeDBFields = true, $removeHasOneFields = true) {
		$ownDBFields = $removeDBFields
			? (Config::inst()->get(get_called_class(), 'db', Config::UNINHERITED) ?: [])
			: [];
		$ownHasOneFields = $removeHasOneFields
			? (Config::inst()->get(get_called_class(), 'has_one', Config::UNINHERITED) ?: [])
			: [];

		$ownFields = array_merge(
			array_keys($ownDBFields),
			array_map(
				function ($item) {
					return $item . 'ID';
				},
				array_keys($ownHasOneFields)
			)
		);

		array_map(
			function ($fieldName) use ($fields) {
				$fields->removeByName($fieldName);
			},
			$ownFields
		);
	}

	/**
	 * Checks model config.has_many and config.many_many to see if the provided relationship
	 * exists on the model to another model. e.g. 'RelatedMembers'
	 *
	 * @param $relationshipName
	 * @return bool
	 */
	public function hasRelationship($relationshipName) {
		return array_key_exists(
			$relationshipName,
			array_keys(
				array_merge(
					$this()->config()->get('has_many') ?: [],
					$this()->config()->get('many_many') ?: []
				)
			)
		);
	}

	/**
	 * Returns models which are related to this model by $actionCode.
	 *
	 * @param $otherModelOrClassName
	 * @param $actionCode
	 * @return DataList
	 */
	public function getRelatedModels($otherModelOrClassName, $actionCode) {
		if ($otherModelOrClassName instanceof DataObject) {
			$otherModelOrClassName = $otherModelOrClassName->class;
		}
		/** @var SocialEdgeType $Action */
		$Action = SocialEdgeType::get_heirarchy(
			$this->getModelClass(),
			$otherModelOrClassName,
			$actionCode
		)->first();

		if ($Action) {
			$relationshipClassName = $Action->getRelationshipClassName();
			return DataObject::get($relationshipClassName)
				->filter([
					$Action->getFromFieldName() => $this()->ID,
					'ActionID'                  => $Action->ID,
				]);
		}
	}

	/**
	 * If a field has changed from its current value this will return true and set its previous value,
	 * otherwise returns false and sets previous value to null.
	 *
	 * if ($this->getChangeFieldInfo('Title', $previousValue)) {
	 * }
	 *
	 * @param string $fieldName
	 * @param mixed  $previousValue reference variable receives the previous value if changed or null if not.
	 * @param mixed  $currentValue  will always receive the current value (which may be null)
	 * @return bool
	 */
	protected function fieldValueChanged($fieldName, &$previousValue = null, &$currentValue = null) {
		$currentValue = $this()->$fieldName;
		if ($this()->isChanged($fieldName)) {
			if (array_key_exists($fieldName, $changes = $this()->getChangedFields())) {
				$previousValue = $changes[ $fieldName ]['before'];
			}
			return true;
		} else {
			$previousValue = null;
			return false;
		}
	}

}
