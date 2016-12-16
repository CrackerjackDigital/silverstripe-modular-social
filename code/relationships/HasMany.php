<?php
namespace Modular\Relationships\Social;

use ArrayList;
use Config;
use DataList;
use DataObject;
use FieldList;
use Modular\Interfaces\ModelWriteHandlers;
use Modular\Types\SocialEdgeType;
use SS_HTTPRequest;

/**
 * Extension class for implementing HasMany actions, e.g. between a Member and InterestTypes
 */

abstract class HasMany extends \Modular\ModelExtension implements ModelWriteHandlers  {
	// foreign class should be set in implementation, e.g. 'SocialInterestType'
	protected static $other_class;

	// name of field in other class we link to e.g. 'ToInterestTypeID'
	protected static $other_field;

	// name of action class we handle e.g. 'MemberInterestAction'
	protected static $action_class;

	// the name of the action on the primary data object, e.g. 'RelatedInterests'
	protected static $relationship_name;

	// name of field added to form
	protected static $field_name;

	// label for field if not in _t.HasInterests.Label
	protected static $field_label;

	protected static $remove_field_name;

	// we're using select2 for one-to-many actions so multiple values are returned by a seperator
	protected static $value_seperator = ',';

	/**
	 * Add an InterestChooser to the form fields if we are in the array of fields for the current mode.
	 * @param FieldList $fields
	 * @param $mode
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, array &$requiredFields = []) {
		$modeFields = $this->owner->config()->get('fields_for_mode');
		if (isset($modeFields[$mode]) && isset($modeFields[$mode][static::$remove_field_name])) {
			$fields->removeByName(static::$remove_field_name);

			$fields->push(
				$this->Chooser()
			);
		}
	}

	/**
	 * If we're in the database then return a list of the related objects (not the actions).
	 * @return ArrayList|DataList
	 */
	protected function getActions() {
		if ($this->owner->isInDB()) {
			$actions = $this->actions();

			$ids = $actions->column(static::$other_field);
			return DataObject::get(static::$other_class)
				->filter('ID', $ids);
		}
		return new ArrayList();
	}

	protected function hasActions($typeID) {
		return $this->getActions()->filter('ID', $typeID)->first();
	}

	/**
	 * @param $typeID
	 * @return $this
	 */
	protected function addAction($typeID) {
		if (!$related = $this->hasActions($typeID)) {
			// check exists and is allowed for owner's class
			$action = $this->getAllowedActionTypes()->filter('ID', $typeID)->first();

			if ($action) {
				$actionClassName = static::$action_class;
				$actionFieldName = 'From' . $this->owner->class . 'ID';
				$relationshipName = static::$relationship_name;

				$actionRecord = new $actionClassName([
					static::$other_field => $typeID,
					$actionFieldName => $this->owner->ID,
				]);

				$this->owner->$relationshipName()->add($actionRecord);
			} else {
				// TODO handle bad types better
				//                throw new SS_HTTPResponse_Exception("Invalid type for {$this->owner->class}: $typeID", 400);
			}
		}
		return $this;

	}

	/**
	 * @param $typeID
	 * @return $this
	 */
	protected function removeAction($typeID) {
		$actionClassName = self::$action_class;
		$actionFieldName = $this->owner->class . 'ID';

		if ($actionRecord = $actionClassName::get()
			->filter([
				static::$other_field => $typeID,
				$actionFieldName => $this->owner->ID,
			])) {

			$actionRecord->delete();
		}
		return $this;

	}

	/**
	 * @return $this
	 */
	protected function clearActions() {
		foreach ($this->actions() as $action) {
			$action->delete();
		}
		return $this;

	}

	/**
	 * @param array $titles
	 * @param bool  $idsNotTitles
	 * @return $this
	 */
	protected function setActions(array $titles, $idsNotTitles = false) {
		$this->clearActions();
		if ($titles) {
			if ($idsNotTitles) {
				$ids = DataObject::get(static::$other_class)->filter('ID', $titles)->column('ID');
			} else {
				$ids = DataObject::get(static::$other_class)->filter('Title', $titles)->column('ID');
			}
			foreach ($ids as $id) {
				$this->addAction($id);
			}
		}
		return $this;

	}

	/**
	 * Return related types allowed for this owner's class e.g. InterestTypes allowed from a Member.
	 *
	 * @return DataList
	 */
	protected function getAllowedActionTypes() {
		$filters = [
			ActionType::FromModelFieldName => $this->owner->class,
		];
		$query = DataObject::get(static::$other_class)->filter($filters);

		return $query;
	}

	/**
	 * Return action objects (not Related objects) filtered to those allowed from the owner class.
	 * @return mixed
	 */
	protected function actions() {
		$relationshipName = static::$relationship_name;

		$allowedIDs = $this->getAllowedActionTypes()->column('ID');

		$actions = $this->owner->$relationshipName();

		return $actions->filter([
			static::$other_field => $allowedIDs,
		]);
	}

	/**
	 * Returns array of
	 * -    config.field_name
	 * -    _t.HasInterests.field_title
	 * -    config.value_seperator
	 *
	 * @return array
	 */
	protected static function get_field_config() {
		$className = get_called_class();
		return [
			Config::inst()->get($className, 'field_name') ?: static::$field_name,
			_t("$className.Label", static::$field_label),
			Config::inst()->get($className, 'value_seperator') ?: static::$value_seperator,
		];
	}
	public function beforeModelWrite(SS_HTTPRequest $request, DataObject $model, $mode, &$fieldsHandled = []) {
		// stub for interface, override in concreate implementations
	}

	/**
	 * Handle decoding of fields back into actions on the model.
	 *
	 * @param SS_HTTPRequest $request
	 * @param DataObject $model
	 * @param $mode
	 */
	public function afterModelWrite(SS_HTTPRequest $request, DataObject $model, $mode) {
		list($fieldName, , $seperator) = self::get_field_config();

		$postVars = $request->postVars();

		if (array_key_exists($fieldName, $postVars)) {
			if ($interests = $postVars[$fieldName]) {
				$this->setActions(explode($seperator, $interests));
			} else {
				$this->clearActions();
			}
		}
	}
}