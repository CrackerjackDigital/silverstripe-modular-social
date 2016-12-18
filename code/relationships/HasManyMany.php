<?php
namespace Modular\Relationships\Social;

use ArrayList;
use ClassInfo;
use DataList;
use DataObject;
use FieldList;
use FormField;
use Modular\Edges\SocialRelationship;
use Modular\Exceptions\Social as Exception;
use Modular\Fields\HasManyManyGridField;
use Modular\Object;
use Modular\Types\SocialEdgeType as SocialEdgeType;
use Modular\UI\Component;
use SS_List;

/**
 * Base class for action classes which consist of one object with an intermediate action record with a
 * SocialEdgeType and ID for another object.
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
class HasManyMany extends HasManyManyGridField {
	const RelatedClassName    = '';             # e.g. 'Modular\Models\Social\Organisation
	const ChooserClassName    = '';             # e.g. 'Modular\UI\Components\OrganisationChooser'
	const GridFieldConfigName = 'Modular\GridField\Configs\SocialModelGridFieldConfig';
	const RelationshipPrefix  = 'Related';      // will try and build one from this and the sanitised class name being related to

	private static $url_handlers = [
		'$ID/related/$RelationshipName!' => 'related',
	];
	private static $allowed_actions = [
		'related' => '->canShowRelated',
	];

	/**
	 * Return form component used to modify this action. If no static::ChooserFieldName set then return null.
	 *
	 * @return Component
	 */
	protected function Chooser() {
		if ($className = static::chooser_class_name()) {
			// create chooser field and set options to map of other class ID => Title
			$field = ($className::create()
				->setOptions(
					DataObject::get(static::related_class_name())->map()->toArray()
				));

			// TODO: why?
			if ($instance = $this->related('ADM')->first()) {
				$field->setValue($instance->ID);
			}

			return $field;
		}
	}

	public static function chooser_class_name() {
		return static::ChooserClassName;
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
				$this->relationships($code)
			);
		}
		return $listItems;
	}

	/**
	 * Update action fields by adding a chooser field which is found by:
	 * - existence of class static::ChooserClassName
	 * - existence of class $this->to_class_name() . 'ChooserField' e.g. PostChooserField
	 * - owner having method $this->to_class_name() . 'Chooser' e.g. PostChooser()
	 * - just use the parent GridField as per Modular\HasManyManyGridField
	 *
	 * @param FieldList $fields
	 * @param           $mode
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, &$requiredFields = []) {
		$chooser = null;

		$relatedClassName = static::related_class_name();

		if ($chooserField = static::chooser_class_name()) {

			if (\ClassInfo::exists($chooserField)) {

				$chooser = Object::create_from_string($chooserField);

			} else {

				if (ClassInfo::exists($relatedClassName . 'ChooserField')) {

					$chooser = Object::create_from_string($relatedClassName . 'ChooserField');

				} else if ($this()->hasMethod($relatedClassName . 'Chooser')) {

					$chooserMethod = $relatedClassName . 'Chooser';
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
	 * Add search fields for Organisations if SocialOrganisation is in the csvWhat array/string. This is called as
	 * an extend e.g. by SearchableExtension.
	 *
	 * @param FieldList    $fields
	 * @param string|array $csvWhat
	 */
	public function updateSearchFields(FieldList &$fields, &$csvWhat) {
		$csvWhat = is_array($csvWhat) ? $csvWhat : explode(',', $csvWhat);

		if (in_array(static::related_class_name(), $csvWhat)) {
			list($modelFields,) = singleton(static::related_class_name())->getFieldsForMode('search');
			$fields->merge(
				$modelFields
			);
		}
	}

	/**
	 * Return the name of the relationship class from the extended model to the 'other' model. e.g. 'Modular\Edges\MemberMember'
	 *
	 * @param string|DataObject $fromModelClass
	 * @return string
	 */
	public static function relationship_class_name($fromModelClass) {
		$fromModelClass = is_object($fromModelClass) ? get_class($fromModelClass) : $fromModelClass;
		// there should be only one in the list returned as both from and to are specified
		return current(SocialRelationship::implementors(
			$fromModelClass,
			static::related_class_name()
		));
	}

	/**
	 * Return models related to the extended model by the given codes.
	 *
	 * @param null $actionCodes
	 * @return DataList|ArrayList
	 */
	public function related($actionCodes = null) {
		if ($this()->isInDB()) {
			/** @var \SS_List $relationships e.g. MemberOrganisations */
			$relationships = static::relationships($actionCodes);

			/** @var string|SocialRelationship $relationshipClassName e.g. 'MemberOrganisation' */
			$relationshipClassName = static::relationship_class_name($this());

			/** @var string $toFieldName e.g. 'ToModelID' */
			$toFieldName = $relationshipClassName::to_field_name();

			// return MemberOrganisation records which have an ID in the list of MemberOrganisation.ToModelID
			return DataObject::get()->filter([
				'ID' => $relationships->column($toFieldName),
			]);

		}
		return new ArrayList();
	}

	/**
	 * Return owner's action records (not the Related foreign objects) optionally filtered by type. If the owner
	 * isn't in the database then returns an empty ArrayList instead.
	 *
	 * @param null|string|array $actionCodes
	 * @return SS_List|null
	 */
	public function relationships($actionCodes = null) {
		if ($this()->isInDB()) {
			/** @var SocialRelationship $relationshipClassName */
			$relationshipClassName = static::relationship_class_name($this());
			return $relationshipClassName::nodeAForAction($this(), $actionCodes);
		}
		return new ArrayList();
	}

	/**
	 * Return an initialised SocialRelationship object suitable from the extended model to the RelatedClassName.
	 * Does not write it (and so no relationship is really created yet).
	 *
	 * @param string|DataObject|int $action
	 * @return SocialRelationship
	 */
	public function createRelationshipModel($toModelOrID, $action, $data = []) {
		$toModelID = is_object($toModelOrID)
			? $toModelOrID->ID
			: $toModelOrID;

		$actionID = is_object($action)
			? $action->ID
			: (is_numeric($action)
				? $action
				: SocialEdgeType::get_by_code($action));

		/** @var string|SocialRelationship $relationshipClassName */
		$relationshipClassName = static::relationship_class_name($this());

		if ($toModelID && $actionID) {
			return new $relationshipClassName(array_merge(
				$data,
				[
					$relationshipClassName::from_field_name('ID')      => $this()->ID,
					$relationshipClassName::to_field_name('ID')        => $toModelID,
					$relationshipClassName::edge_type_field_name('ID') => $actionID,
				]
			));
		} else {
			$fromModelClass = get_class($this());
			$toModelClass = static::relationship_class_name($this());
			$this->debug_fail(new Exception("Failed to create relationship '$relationshipClassName' model from '$fromModelClass' to '$toModelClass' type ID '$actionID'"));
		}
	}

	/**
	 * Returns an array of the items considered to be favourites of the extended class. These are for multiple
	 * action codes and action names.
	 *
	 * @param string|array $parentActionCodes e.g. 'LIK,FOL'
	 * @param array        $relationshipNames e.g. 'Organisations'
	 * @return ArrayList
	 */
	protected function relatedByParent($parentActionCodes, $relationshipNames = []) {
		$related = new ArrayList();

		if ($this()->isInDB()) {
			$relationshipNames = $relationshipNames
				? (is_array($relationshipNames)
					? $relationshipNames
					: array_filter(explode(',', $relationshipNames)))
				: [$this->relationship_name()];

			// get action type records which are children of the passed in code.
			$actionTypeIDs = SocialEdgeType::get_by_parent($parentActionCodes)->column('ID');

			// for each of the action names append records which match the current action type
			foreach ($relationshipNames as $relationshipName) {

				// check we can call the relationshipName as a method
				// TODO hasAction doesn't work, I don't think hasMethod checks actions.
				if (true || $this()->hasAction($relationshipName)) {
					$actions = $this()
						->$relationshipName()
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
	 * Return relationship types which can be created from this model to any other model
	 *
	 * @param string $actionCode e.g. 'CRT', 'REG'
	 * @return Edg“eType|SocialEdgeType|DataObject
	 */
	protected function action_for_code($actionCode) {
		return SocialEdgeType::get_heirarchy($this(), static::related_class_name(), $actionCode)->first();
	}

	/**
	 * Get first related instances's ID with provided action or return null if none such.
	 *
	 * @param $actionCode
	 * @return int|null
	 */
	protected function firstRelatedID($actionCode) {
		if ($model = $this->firstRelated($actionCode)) {
			return $model->ID;
		}
	}

	protected function firstRelated($actionCode) {
		return $this->related($actionCode)->first();
	}

	/**
	 * Return first related instance found with ID and optionally actionCode.
	 *
	 * @param int  $modelID
	 * @param null $actionCode
	 * @return \DataObject
	 */
	protected function hasRelated($modelOrID, $actionCode = null) {
		$model = is_object($modelOrID)
			? $modelOrID->ID
			: $modelOrID;

		$related = static::related($actionCode);
		return $related->filter('ID', $model->ID)->count();
	}

	/**
	 * Relate a model to the extended model by supplied action.
	 *
	 * Creates a action class object if Model and SocialEdgeType records
	 * exist for supplied parameters and adds it to the action collection.
	 *
	 * @param int    $modelOrID
	 * @param string $actionCode
	 * @param array  $extraData to add to the relationship model when it is created, won't update existing though
	 * @return SocialRelationship|null
	 */
	protected function addRelated($modelOrID, $actionCode, $extraData = []) {
		$relationship = null;

		if (!$this->hasRelated($modelOrID, $actionCode)) {
			$relatedClassName = static::related_class_name();
			$model = is_object($modelOrID)
				? $modelOrID
				: DataObject::get_by_id($relatedClassName, $modelOrID);

			if ($model) {
				// get the first action (e.g. SocialEdgeType) allowed between the extended model
				// and the related model with the supplied action code
				$action = static::action_for_code($actionCode);

				if ($action) {
					/** @var SocialRelationship $relationshipClassName */
					$relationshipClassName = static::relationship_class_name($this());

					/** @var SocialRelationship $relationship */
					$relationship = static::relationship(
						array_merge(
							$extraData,
							[
								$relationshipClassName::edge_type_field_name('ID') => $action->ID,
								$relationshipClassName::from_field_name('ID')      => $this()->ID,
								$relationshipClassName::to_field_name('ID')        => $model->ID,
							]
						)
					);
					if ($relationship->write()) {
						$relationshipName = static::relationship_name();

						$this()->$relationshipName()->add($relationship);
					}

				}
			}
		}
		return $relationship;
	}

	/**
	 * Remove relationship from this object to an instance, optionally by a supplied type.
	 *
	 * @param int|DataObject    $modelOrID if null then all related by actionCodes will be removed
	 * @param string|array|null $actionCodes
	 * @return int count of actions deleted
	 */
	protected function removeRelated($modelOrID = null, $actionCodes = []) {

		$relationships = static::relationships($actionCodes);

		if ($modelOrID) {
			$modelID = is_object($modelOrID)
				? $modelOrID->ID
				: $modelOrID;

			$relationships = $relationships->filter([
				'ID' => $modelID,
			]);
		}

		$count = $relationships->count();

		foreach ($relationships as $relationship) {
			$relationship->delete();
		}
		return $count;
	}

	/**
	 * Clear out all related models of a provided type and add new one.
	 *
	 * @param int $modelID
	 * @param     $actionCode
	 */
	protected function setRelated($modelID, $actionCode) {
		if ($existing = $this->relationships($actionCode)) {
			foreach ($existing as $action) {
				$action->delete();
			}
		}
		$this->addRelated($modelID, $actionCode);
	}

}