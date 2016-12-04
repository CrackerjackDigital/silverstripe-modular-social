<?php
namespace Modular\Relationships;

use ArrayList;
use ClassInfo;
use DataList;
use DataObject;
use FieldList;
use FormField;
use Modular\Object;
use Modular\Types\SocialActionType;
use SS_List;

/**
 * Base class for action classes which consist of one object with an intermediate action record with a
 * ActionType and ID for another object.
 *
 * Concrete classes should declare functions (using hasOnePosts for example):
 *
 * Posts calls Related
 * PostChooser calls Chooser
 * getPostID calls getRelatedID
 * hasPost calls hasOneRelated
 * addPost calls addAction
 * removePost calls removeAction
 * setPosts calls setActions
 *
 * You may choose not to do so and provide custom logic instead.
 *
 *
 */
class SocialHasManyMany extends \Modular\ModelExtension {
	private static $url_handlers = [
		'$ID/related/$ActionName!' => 'related',
	];
	private static $allowed_actions = [
		'related' => '->canShowRelated',
	];

	// required in implementation class name of other object (not owner's) e.g. 'Post'
	protected static $other_class_name = '';

	// optional if need to override manufactured one, e.g. 'ToPostID'
	protected static $other_key_field = '';

	// optional if need to override manufactured one, e.g. 'PostChooser'
	protected static $chooser_field = '';

	// if need to override manufactured one e.g. if action is 'RelatedPosts' but model is 'Post' not 'Post'.
	protected static $action_name = '';

	/**
	 * Return form component used to modify this action. If no static::$chooser_field set then return null.
	 *
	 * @return OrganisationChooserField
	 */
	protected function Chooser() {
		if (static::$chooser_field) {
			$className = static::$chooser_field;
			// create chooser field and set options to map of other class ID => Title
			$field = (new $className())
				->setOptions(
					DataObject::get($this->getOtherClassName())->map()->toArray()
				);

			// TODO: why?
			if ($instance = $this->related('ADM')->first()) {
				$field->setValue($instance->ID);
			}

			return $field;
		}
	}

	/**
	 * Return an executed list of related items matching actions provided (not a Query).
	 *
	 * @param null|string|array $actionCodes - optional csv or array of codes to include
	 * @return \SS_List
	 */
	public function actionList($actionCodes = null) {
		if (is_array($actionCodes)) {
			$codes = $actionCodes;
		} else if (false !== strpos($actionCodes, ',')) {
			$codes = explode(',', $actionCodes);
		} else {
			$codes = [$actionCodes];
		}
		$listItems = new ArrayList();

		foreach ($codes as $code) {
			$listItems->merge(
				$this->actions($code)
			);
		}
		return $listItems;
	}

	/**
	 * Update action fields by adding a chooser field which is found by:
	 * - existence of class static::$chooser_field
	 * - existence of class $this->getOtherClassName() . 'ChooserField' e.g. PostChooserField
	 * - owner having method $this->getOtherClassName() . 'Chooser' e.g. PostChooser()
	 *
	 * @param FieldList $fields
	 * @param           $mode
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, &$requiredFields = []) {
		$chooser = null;

		$otherClassName = $this->getOtherClassName();

		if ($chooserField = static::$chooser_field) {

			if (\ClassInfo::exists($chooserField)) {

				$chooser = Object::create_from_string($chooserField);

			} else {

				if (ClassInfo::exists($otherClassName . 'ChooserField')) {

					$chooser = Object::create_from_string($otherClassName . 'ChooserField');

				} else if ($this()->hasMethod($otherClassName . 'Chooser')) {

					$chooserMethod = $otherClassName . 'Chooser';
					$chooser = $this->$chooserMethod();
				}
			}
			if ($chooser instanceof FormField) {

				$fields->push(
					$chooser
				);
				if ($chooser->hasMethod('setMode')) {
					$chooser->setMode($mode);
				}
			}
		}
	}

	/**
	 * Add search fields for Organisations if Organisation is in the csvWhat array/string. This is called as
	 * an extend e.g. by SearchableExtension.
	 *
	 * @param FieldList    $fields
	 * @param string|array $csvWhat
	 */
	public function updateSearchFields(FieldList &$fields, &$csvWhat) {
		$csvWhat = is_array($csvWhat) ? $csvWhat : explode(',', $csvWhat);

		if (in_array(static::$other_class_name, $csvWhat)) {
			list($modelFields,) = singleton(static::$other_class_name)->getFieldsForMode('search');
			$fields->merge(
				$modelFields
			);
		}
	}

	/**
	 * Return queries which will retrieve models related to the extended class by action code, or if the model
	 * has not been saved an empty ArrayList
	 *
	 * @param null $actionCodes
	 * @return DataList|ArrayList
	 */
	public function related($actionCodes = null) {
		if ($this()->isInDB()) {
			$otherKeyField = $this->getOtherKeyField();

			$actions = $this->actions($actionCodes);
			if ($actions && $actions->count()) {

				$ids = $actions->column($otherKeyField);

				if ($ids) {
					$modelClass = $this->getOtherClassName();

					return DataObject::get($modelClass)->filter('ID', $ids);
				}
			}
		}
		return new ArrayList();
	}

	/**
	 * Returns an array of the items considered to be favourites of the extended class. These are for multiple
	 * action codes and action names.
	 *
	 * @param string|array $parentActionCodes e.g. 'LIK,FOL'
	 * @param array        $actionNames       e.g. 'Organisations'
	 * @return ArrayList
	 */
	protected function relatedByParent($parentActionCodes, $actionNames = []) {
		$related = new ArrayList();

		if ($this()->isInDB()) {
			$actionNames = $actionNames
				? (is_array($actionNames)
					? $actionNames
					: explode(',', $actionNames))
				: [$this->getActionName()];

			// get action type records which are children of the passed in code.
			$actionTypeIDs = SocialActionType::get_by_parent($parentActionCodes)->column('ID');

			// for each of the action names append records which match the current action type
			foreach ($actionNames as $actionName) {

				// check we can call the actionName as a method
				// TODO hasAction doesn't work, I don't think hasMethod checks actions.
				if (true || $this()->hasAction($actionName)) {
					$actions = $this()
						->$actionName()
						->filter('ActionTypeID', $actionTypeIDs);

					foreach ($actions as $action) {
						$toClassName = $action->config()->get('has_one')[ $action::ToFieldName ];
						$toKeyName = $action::ToFieldName . 'ID';

						$related->merge(
							DataObject::get($toClassName)->filter('ID', $action->$toKeyName)
						);
					}
				}
			}
			$related->removeDuplicates();
		}
		return $related;
	}

	/**
	 * Get first related instances's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	protected function getRelatedID($actionCode) {
		if ($instance = $this->related($actionCode)->first()) {
			return $instance->ID;
		}
	}

	/**
	 * Return name of 'other' class this owner is related to e.g. 'Post'.
	 *
	 * @return string
	 */
	protected function getOtherClassName() {
		return static::$other_class_name;
	}

	/**
	 * Return static::$other_key_field or manufactured e.g. 'ToPostID'.
	 *
	 * @return string
	 */
	protected function getOtherKeyField() {
		$modelName = $this->getOtherClassName();
		if (substr($modelName, -5, 5) === 'Model') {
			$modelName = substr($modelName, 0, -5);
		}
		return static::$other_key_field ?: ('To' . $modelName . 'ID');
	}

	/**
	 * Return name of action from owner to this action, either
	 * overridden with static::$action_name or manufactured e.g. 'RelatedPosts'
	 *
	 * @return string
	 */
	protected function getActionName() {
		return static::$action_name ?: ('Related' . $this->getOtherClassName() . 's');
	}

	/**
	 * Return first related instance found with ID and optionally actionCode.
	 *
	 * @param      $instanceID
	 * @param null $actionCode
	 * @return \DataObject
	 */
	protected function hasAction($instanceID, $actionCode = null) {
		$otherClassName = $this->getOtherClassName();
		$otherKeyField = $this->getOtherKeyField();

		if ($action = $this->actions($actionCode)
			->filter($otherKeyField, $instanceID)
			->first()
		) {

			$otherID = $action->{$otherKeyField};

			return DataObject::get_by_id($otherClassName, $otherID);
		}
		return null;
	}

	/**
	 * Relate an instance to this object by supplied action.
	 *
	 * Creates a action class object if Instane and ActionType records
	 * exist for supplied parameters and adds it to the action collection.
	 *
	 * @param int    $instanceID
	 * @param string $actionCode
	 * @return bool
	 */
	protected function addAction($instanceID, $actionCode) {
		if ($this->hasAction($instanceID, $actionCode)) {
			return true;
		}
		$otherClassName = $this->getOtherClassName();
		$otherKeyField = $this->getOtherKeyField();

		$instance = DataObject::get_by_id($otherClassName, $instanceID);
		if ($instance) {
			$actionType = $this->getAllowedActionTypes('Organisation')
				->filter([
					'Code' => $actionCode,
				])->first();

			if ($actionType) {
				$x = $this()->class . $otherClassName . 'ActionType';
				$actionClassName = $this->getActionName();
				$actionFieldName = 'From' . $this()->class . 'ID';

				$actionRecord = new $actionClassName([
					'ActionTypeID'   => $actionType->ID,
					$actionFieldName => $this()->ID,
					$otherKeyField   => $instanceID,
				]);

				$actionName = $this->getActionName();

				$this()->$actionName()->add($actionRecord);
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove actions from this object to an instance, optionally by a supplied type.
	 *
	 * @param int         $instanceID
	 * @param string|null $actionCode
	 * @return int count of actions deleted
	 */
	protected function removeAction($instanceID, $actionCode = null) {
		$actions = $this()
			->Action($actionCode)
			->filter(static::$other_key_field, $instanceID);

		$count = $actions->count();

		foreach ($actions as $action) {
			$action->delete();
		}
		return $count;
	}

	/**
	 * Clear out all actions of a provided type and add new one.
	 *
	 * @param $instanceID
	 * @param $actionCode
	 */
	protected function setActions($instanceID, $actionCode) {
		if ($existing = $this->actions($actionCode)) {
			foreach ($existing as $action) {
				$action->delete();
			}
		}
		$this->addAction($instanceID, $actionCode);
	}

	/**
	 * Return Actions allowed from this owner class to foreign class.
	 *
	 * @param $foreignClass
	 * @return DataList
	 */
	private function getAllowedActions($foreignClass) {
		$filters = [
			'AllowedFrom' => $this()->class,
			'AllowedTo'   => $foreignClass,
		];
		return SocialActionType::get()->filter($filters);
	}

	/**
	 * Return owner's action records (not the Related foreign objects) optionally filtered by type. If the owner
	 * isn't in the database then returns an empty ArrayList instead.
	 *
	 * @param null|string|array $actionCodes
	 * @return SS_List|null
	 */
	public function actions($actionCodes = null) {
		if ($this()->isInDB()) {
			$actionCodes = is_array($actionCodes) ? $actionCodes
				: explode(',', $actionCodes);

			// have to check or filter call errors with unsaved action error.
			$actionName = $this->getActionName();

			$actionClassName = $this->getOtherClassName();

			if ($actionCodes && $this()->isInDB()) {
				$actions = SocialActionType::get_heirarchy($this(), $actionClassName, $actionCodes);

				if ($actions->count()) {
					return $this()->$actionName()->filter('ActionID', $actions->column('ID'));
				}
			} else {
				// will be empty but useable (hence check for isInDB above which will return something which is not useable).
				return $this()->$actionName();
			}
		}
		return null;
	}

}