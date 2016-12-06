<?php
namespace Modular\Types;

use ArrayList;
use DataList;
use DataObject;
use Modular\config;
use Modular\Edges\SocialRelationship;
use Modular\Extensions\Model\SocialMember;
use Modular\Extensions\Model\SocialModel as SocialModelExtension;
use Modular\Fields\Code;
use Modular\Fields\SystemData;
use Modular\Interfaces\GraphEdgeType;
use Permission;
use TreeDropdownField;

/**
 * SocialActions are the core rules for the SocialModel system which describe what relationships are allowed between
 * what models, what actions can be performed to create/delete the actual relationship records
 * and who gets notified when one is made or broken.
 *
 * @property string ActionType
 * @property string AllowedFrom
 * @property string AllowedTo
 * @property string Code
 * @property string ParentCode
 * @method \SS_List NotifyMembers()
 * @method \SS_List NotifyGroups()
 * @method \SS_List ImpliedActions()
 * @method SocialAction|null RequirePrevious()
 *
 */
class SocialAction extends SocialType implements GraphEdgeType {
	use config;

	const ActionCode                  = '';
	const ActionName                  = '';
	const RelationshipClassNamePrefix = '';
	const RelationshipClassNameSuffix = '';
	const RelationshipNamePrefix      = '';
	const RelationshipNameSuffix      = '';

	private static $admin_groups = [
		'administrators' => true,
		'social-admin'   => true,
	];

	private static $indexes = [
		'AllowedClassNames' => 'AllowedFrom,AllowedTo',
		'ParentCode'        => true,
	];

	private static $db = [
		// Title and Code from Modular\Type
		'ActionType'          => 'Varchar(12)',              // e.g. 'Follow'
		'ReverseAction'       => 'Varchar(12)',       // e.g. 'Unfollow'
		'ReverseTitle'        => 'Varchar(64)',        // e.g for Title of 'Follows' would be 'Followed by'
		'AllowedFrom'         => 'Varchar(64)',         // e.g. 'Member'
		'AllowedTo'           => 'Varchar(64)',           // e.g. 'SocialOrganisation'
		'LastBuildResult'     => 'Varchar(32)',     // if this record was created, changed or unchanged by last build
		'ShowInActionLinks'   => 'Int',           // show this action in action-link menus if not 0
		'ShowInActionButtons' => 'Int',         // show this action in action-button menus if not 0
		'ParentCode'          => 'Varchar(3)',           // Code of Parent (e.g. 'LIK' for 'MLM'), for simplicity, not in record
		'PermissionPrefix'    => 'Varchar(32)',                // e.g. 'CAN_APPROVE_' for approval relationships, not in record,
		'ActionLinkType'      => "enum('nav,modal,inplace')"     // when clicked what to do?
	];
	private static $has_one = [
		'Parent'          => 'Modular\Types\SocialAction',         // typical parent relationship
		'Permission'      => 'Permission',           // what permission is required to make/break a relationship
		'NotifyFrom'      => 'Member',                // who emails are sent from when one is made/broken
		'RequirePrevious' => 'Modular\Types\SocialAction'   // e.g. for 'EDT' then a 'CRT' MemberPost relationship must exist
	];
	private static $has_many = [
		'Relationships' => 'Modular\Edges\SocialRelationship',
	];
	private static $many_many = [
		'ImpliedActions' => 'Modular\Types\SocialAction',  // when this relationship is created also create these between member and model
		'NotifyMembers'  => 'Member',            // who (Members) get notified when made/broken
		'NotifyGroups'   => 'Group',               // who (Security Groups) get notified
	];
	private static $belongs_many_many = [
		'TriggerAction' => 'Modular\Types\SocialAction'  // back relationship to 'ImpliedActions'
	];
	private static $summary_fields = [
		'Title',
		'ReverseTitle',
		'Code',
		'AllowedFrom',
		'AllowedTo',
		'Action',
		'ActionLinkType',
	];
	private static $singular_name = 'Action';

	private static $plural_name = 'Actions';

	public function __invoke() {
		return $this;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab(
			'Root.Main',
			new TreeDropdownField(
				'RequirePreviousID',
				'Require previous relationship',
				'SocialAction'
			)
		);

		return $fields;
	}

	/**
	 * Returns a deduped list of members explicitly related or via member groups.
	 *
	 * @return \SS_List
	 */
	public function NotificationRecipients() {
		$members = new ArrayList();
		$members->merge($this->NotifyMembers());
		/** @var \Group $group */
		foreach ($this->NotifyGroups() as $group) {
			$members->merge($group->Members());
		}
		$members->removeDuplicates();
		return $members;
	}

	/**
	 * Returns an array of information used to build records around this type of relationship:
	 * -    FromName                e.g. 'Member'
	 * -    ToName                  e.g. 'SocialOrganisation' (not SocialOrganisation)
	 * -    FromFieldName           e.g. 'FromMemberID'
	 * -    ToFieldName             e.g. 'ToOrganisationModelID'
	 * -    RelationshipClassName   e.g. 'MemberOrganisationRelationship'
	 * -    RelationshipName        e.g. 'RelatedMembers'
	 *
	 * NB you can use list(,,$useThisOne,,,$andThisOne) to ignore ones you're not using.
	 *
	 * @return array
	 */
	public function getLinkInfo() {
		return [
			$this->getFromName(),
			$this->getToName(),
			$this->getFromFieldName(),
			$this->getToFieldName(),
			$this->getRelationshipClassName(),
			$this->getRelationshipName(),
		];
	}

	/**
	 * Check that rules are met for this relationship to be allowed, e.g. previous relationship exists.
	 *
	 * @param DataObject $fromModel
	 * @param DataObject $toModel
	 * @param array      $requirementsTally
	 * @return bool
	 */
	public function checkRules(DataObject $fromModel, DataObject $toModel, array &$requirementsTally = []) {
		return self::check_rules($fromModel, $toModel, $this->Code, $requirementsTally);
	}

	/**
	 * Check that provided model or member or current member or guest can do the relationship to the $toModel.
	 *
	 * @param DataObject $toModel
	 * @param DataObject $fromMemberOrModel
	 * @return bool|int
	 */
	public function checkPermission(DataObject $toModel, DataObject $fromMemberOrModel = null) {
		$fromMemberOrModel = $fromMemberOrModel ?: SocialMember::current_or_guest();
		return self::check_permission($this->Code, $toModel, $fromMemberOrModel, false);
	}

	/**
	 * Check permissions and rules for a relationship.
	 *
	 * @param DataObject $toModel
	 * @param DataObject $fromMemberOrModel
	 * @return bool
	 */
	public function checkAllowed(DataObject $toModel, DataObject $fromMemberOrModel = null) {
		return $this->checkPermission($toModel, $fromMemberOrModel)
		&& $this->checkRules($fromMemberOrModel, $toModel);
	}

	/**
	 * Check a relationship of this type exists between two objects. Looks for relationships
	 * and returns the last action of the type performed (in order of ID descending).
	 *
	 * @param int   $nodeAID
	 * @param int   $nodeBID
	 * @param array $archetype
	 * @return SocialRelationship|null
	 */
	public function checkRelationshipExists($nodeAID, $nodeBID, &$archetype = []) {
		return $this->buildGraphEdgeTypeInstanceQuery(
			$nodeAID,
			$nodeBID,
			$archetype
		)->sort('ID', 'desc')->first();
	}

	/**
	 * Find the implied actions for a given action and create those records in the database between the supplied models.
	 *
	 * @param \DataObject $fromModel
	 * @param \DataObject $toModel
	 */
	public function createImpliedActions(DataObject $fromModel, DataObject $toModel) {
		// add additional relationships between models as listed in SocialAction.ImpliedActions

		foreach ($this->ImpliedActions() as $impliedAction) {
			// we might have a parent code so look up the suitable 'real' code.
			/** @var SocialAction $implied */
			$implied = SocialAction::get_heirarchy(
				$fromModel,
				$toModel,
				$impliedAction->Code
			)->first();

			$impliedClassName = $implied->getRelationshipClassName();

			// now make the implied relationship
			$impliedClassName::make(
				$fromModel,
				$toModel,
				$implied->Code
			);
		}
	}

	/**
	 * Return a class name like 'MemberOrganisationRelationship'
	 *
	 * @return string
	 */
	public function getRelationshipClassName() {
		return self::RelationshipClassNamePrefix . $this->getFromName() . $this->getToName() . self::RelationshipClassNameSuffix;
	}

	/**
	 * Return the has_many name of a relationship to the AllowedTo class like 'RelatedOrganisations'
	 *
	 * @return string
	 */
	public function getRelationshipName() {
		return self::RelationshipNamePrefix . $this->getToName() . self::RelationshipNameSuffix;
	}

	/**
	 * Look back in time for a relationship between the two models which matches the provided Code.
	 *
	 * @param DataObject $fromModel
	 * @param DataObject $toModel
	 * @param            $actionCode
	 * @return SocialRelationship
	 */
	public static function check_relationship_exists(DataObject $fromModel, DataObject $toModel, $actionCode) {
		/** @var SocialAction $action */
		$action = SocialAction::get_by_edge_type_code(
			$fromModel,
			$toModel,
			$actionCode
		)->first();

		return $action->checkRelationshipExists($fromModel->ID, $toModel->ID);
	}

	/**
	 * Return a mangled class name like 'SocialOrganisation' from AllowedFrom 'SocialOrganisation'
	 *
	 * @return string
	 */
	private function getFromName() {
		return SocialModelExtension::name_from_class_name($this->AllowedFrom);
	}

	/**
	 * Return a field name like 'FromOrganisationID' using AllowedFrom
	 *
	 * @return string
	 */
	public function getFromFieldName() {
		return 'From' . $this->getFromName() . 'ID';
	}

	/**
	 * Return a mangled class name like 'SocialOrganisation' from AllowedTo 'SocialOrganisation'
	 *
	 * @return string
	 */
	private function getToName() {
		return SocialModelExtension::name_from_class_name($this->AllowedTo);
	}

	/**
	 * Return a field name like 'ToOrganisationID' using AllowedTo
	 *
	 * @return string
	 */
	public function getToFieldName() {
		return 'To' . $this->getToName() . 'ID';
	}

	/**
	 * Social Action uses the 'Code' field to store type
	 *
	 * @return string
	 */
	public static function edge_type_field_name() {
		return Code::single_field_name();
	}

	/**
	 * Return a query which uses the ArchType
	 */
	public function buildGraphEdgeTypeQuery() {
		$archetype = $this->buildGraphEdgeTypeArchetype();
		return SocialAction::get()->filter($archetype);
	}

	/**
	 * Returns a filter array used to locate Edge types (e.g. 'SocialAction' models)
	 *
	 * @return array
	 */
	public function buildGraphEdgeTypeArchetype() {
		$typeFieldName = static::edge_type_field_name();
		return [
			'AllowedFrom'  => $this->AllowedFrom,
			'AllowedTo'    => $this->AllowedTo,
			$typeFieldName => $this->{$typeFieldName},
		];
	}

	/**
	 * Returns a query which uses this SocialAction to find records in a relationship
	 * table which match the passed in object IDs. e.g. MemberOrganisationRelationship with
	 * Member.ID = $formObjectID and OrganisationModelID = $nodeBID
	 *
	 * NB we take ints not models here as the model class etc comes from instance of SocialAction
	 *
	 * @param int        $nodeAID
	 * @param int        $nodeBID
	 * @param array|null $archetype will be filled with an 'archtype' which can be used to build queries on the relationship
	 * @return \SS_List
	 */
	public function buildGraphEdgeTypeInstanceQuery($nodeAID, $nodeBID, &$archetype = []) {
		if (!(is_numeric($nodeAID) && is_numeric($nodeBID))) {
			user_error(__METHOD__ . " expects IDs only, something else passed", E_USER_ERROR);
			return null;
		}
		$archetype = $this->buildGraphEdgeTypeArchtype($nodeAID, $nodeBID);
		$relationshipClassName = $this->getRelationshipClassName();
		return $relationshipClassName::get()->filter($archetype);
	}

	/**
	 * Build a filter and data array used in checking an instance of a relationship exists between two
	 * objects of the provided type and for initialising a new relationship object.
	 *
	 * for use against e.g. this being a 'FOL' between Member 1 and SocialOrganisation 10
	 *
	 * @param $nodeAID
	 * @param $nodeBID
	 * @return array
	 */
	public function buildGraphEdgeTypeArchtype($nodeAID, $nodeBID) {
		return [
			$this->getFromFieldName() => $nodeAID,
			$this->getToFieldName()   => $nodeBID,
			'ActionID'                => $this->ID,
		];

	}

	/**
	 * Return the possible actions between two objects, optionally restricted by SocialAction.ActionType.
	 *
	 * @param                   $fromModel
	 * @param                   $toModel
	 * @param null|string|array $restrictTo
	 * @return DataList
	 */

	public static function get_possible_actions(DataObject $fromModel, DataObject $toModel, $restrictTo = null) {
		$restrictTo = $restrictTo
			? is_array($restrictTo) ? $restrictTo : explode(',', $restrictTo)
			: null;

		$query = SocialAction::get()->filter([
			'AllowedFrom' => $fromModel->class,
			'AllowedTo'   => $toModel->class,
		]);
		return $restrictTo ? $query->filter('SocialAction', $restrictTo) : $query;

	}

	/**
	 * Convenience fetch helper.
	 *
	 * @param string|array $actionCodes
	 * @return SocialAction
	 */
	public static function get_by_code($actionCodes) {
		return self::get()->filter('Code', $actionCodes)->first();
	}

	/**
	 * Given two lists of codes either as a single code, CSV or array merge together and return a set of codes. Can
	 * also be used without second parameter to turn $codes into an array.
	 *
	 * @param string|array      $codes e.g. 'MFR', 'MFR,MLO' or ['MFR', 'MLO']
	 * @param null|string|array $merge e.g. 'MFR', 'MFR,MLO' or ['MFR', 'MLO']
	 * @return array numerically indexed array of unique codes
	 */
	public static function merge_code_lists($codes, $merge = []) {
		if (!is_array($codes)) {
			$codes = explode(',', $codes);
		}
		if (!is_array($merge)) {
			$merge = explode(',', $merge);
		}
		return array_unique(array_merge($codes, $merge));
	}

	/**
	 * Return all SocialAction records which have the particular code(s) passed as their parent(s).
	 * e.g. passing 'LIK' will return 'MLO', 'MLG' etc which are children of the 'LIK' record. Does not return the
	 * 'LIK' record.
	 *
	 * @param string|array $parentActionCodes
	 * @return SS_List
	 */
	public static function get_by_parent($parentActionCodes) {
		return SocialAction::get()->filter('ParentCode', $parentActionCodes);
	}

	/**
	 * Returns a list of SocialAction models which have the provided code or have the code as a Parent.
	 *
	 * @param string|DataObject $fromModelOrClassName
	 * @param string|DataObject $toModelOrClassName
	 * @param string|array      $actionCodes
	 * @return DataList
	 */
	public static function get_heirarchy($fromModelOrClassName, $toModelOrClassName, $actionCodes) {
		$old = SystemData::disable();

		$actionCodes = array_filter(
			is_array($actionCodes)
				? $actionCodes
				: explode(',', $actionCodes)
		);

		$fromModelClass = ($fromModelOrClassName instanceof DataObject) ? $fromModelOrClassName->class
			: $fromModelOrClassName;
		$toModelClass = ($toModelOrClassName instanceof DataObject) ? $toModelOrClassName->class : $toModelOrClassName;

		// get relationship types for the code and the parent matching that code.
		$heirarchy = SocialAction::get()->filter([
			'AllowedFrom' => $fromModelClass,
			'AllowedTo'   => $toModelClass,
		]);
		if ($actionCodes) {
			$heirarchy = $heirarchy->filterAny([
				'Code'       => $actionCodes,
				'ParentCode' => $actionCodes,
			]);
		}
		SystemData::enable($old);

		return $heirarchy;
	}

	/**
	 * Returns all defined Actions from one model to another,
	 * optionally filtered by passed SocialAction.Codes
	 *
	 * @param string|DataObject $fromModelClass
	 * @param string|DataObject $toModelClass
	 * @param array             $actionCodes
	 * @return DataList
	 */
	public static function get_by_edge_type_code($fromModelClass, $toModelClass, $actionCodes = null) {
		if (is_object($fromModelClass)) {
			$fromModelClass = get_class($fromModelClass);
		}
		if (is_object($toModelClass)) {
			$toModelClass = get_class($toModelClass);
		}
		return SocialAction::get_heirarchy($fromModelClass, $toModelClass, $actionCodes);
	}

	/**
	 * Check to see if valid permissions to perform an actione exist between two objects.
	 *
	 * The 'from' object is generally (and by default) the logged in member, the 'to' object is e.g. an SocialOrganisation
	 * and the permission code is the three-letter code such as 'MAO' for 'Member Administer SocialOrganisation'.
	 *
	 * If a direct relationship is not found then the parent relationship is also tried, e.g. passing in 'ADM' will
	 * check for all Administer actions.
	 *
	 * If the from object is not supplied then the current member is tried, if not logged in then the Guest Member is
	 * used.
	 *
	 * @param string|array      $actionCodes
	 * @param DataObject|string $toModel                   - either class name or an instance of it
	 * @param DataObject|string $fromModelOrLoggedInMember - either class name or an instance of it
	 * @param bool              $checkObjectInstances      - if we have instances of the from and to models then check
	 *                                                     rules are met
	 * @return bool|int
	 */
	public static function check_permission(
		$actionCodes,
		$toModel,
		$fromModelOrLoggedInMember = null,
		$checkObjectInstances = true
	) {
		// generally we're check the current site viewer though may wany to check e.g. if an SocialOrganisation can do something
		$fromModelOrLoggedInMember = $fromModelOrLoggedInMember ?: SocialMember::current_or_guest();

		// sometimes we only have the model class name to go on, get a singleton to make things easier
		$toModel = ($toModel instanceof DataObject) ? $toModel : singleton($toModel);

		// check if owner is a member of ADMIN, social-admin or can administer the type in general.
		if (self::check_admin_permissions($fromModelOrLoggedInMember, $toModel)) {
			return true;
		}
		$permissionOK = false;

		$actions = SocialAction::get_heirarchy($fromModelOrLoggedInMember, $toModel, $actionCodes);

		// get the ids of permissions for the allowed relationships (and Codes to help debugging)
		if ($permissionIDs = $actions->map('PermissionID', 'Code')->toArray()) {

			// get the codes for those permissions using keys from map
			if ($permissions = Permission::get()->filter('ID', array_keys($permissionIDs))) {
				$permissionCodes = $permissions->column('Code');

				// check the codes against the member/other object (which may be guest member)
				// this is a 'general' permission such as 'CAN_Edit_Member' or 'CAN_Like_Post'
				$permissionOK = Permission::check(
					$permissionCodes,
					"any",
					$fromModelOrLoggedInMember
				);

				// now we get more specific; if we were handed a model object it should have an ID so also check that
				// instance rules are met, such as a previous relationship existing (if just a class was passed to function
				// then we have a singleton and we can't check these requirements).
				// This check uses the SocialAction.RequirePrevious relationship on the current SocialAction

				if ($permissionOK && $toModel->ID && $checkObjectInstances) {

					$actionCodes = $actions->column('Code');

					$permissionOK = self::check_rules(
						$fromModelOrLoggedInMember,
						$toModel,
						$actionCodes
					);

					if (!$permissionOK) {
						$permissionOK = self::check_implied_rules(
							$fromModelOrLoggedInMember,
							$toModel,
							$actionCodes
						);
					}
				}

				if ($permissionOK) {
					// now we ask the models to check themselves, e.g. if they require a field to be set outside of the permissions
					// SocialAction model, such as a Member requiring to be Confirmed then the Confirmable extension will
					// intercept this and check the 'RegistrationConfirmed' field
					if ($modelCheck = $toModel->extend('checkPermissions', $fromModel, $toModel, $actionCodes)) {
						$permissionOK = count(array_filter($modelCheck)) != 0;
					}
				}
			}
		}
		return $permissionOK;
	}

	/**
	 * Returns admin groups (keys) from config.admin_groups which have a truthish value.
	 *
	 * @return array
	 */
	public static function admin_groups() {
		return array_keys(array_filter(static::config()->get('admin_groups')));
	}

	/**
	 * Checks if the logged in member is an admin (is a member of the groups defined in config.admin_groups), or if the
	 * from object has CAN_ADMIN_ on the to object type.
	 *
	 * @param DataObject $fromModel
	 * @param DataObject $toModel
	 * @return boolean
	 */
	public static function check_admin_permissions($fromModel, $toModel) {
		$member = SocialMember::current_or_guest();

		// check if current or guest member is in admin groups first (guest should never be though!)
		if ($member->inGroups(static::admin_groups())) {
			return true;
		}

		$fromModel = $fromModel ?: $member;

		// get all the ADM type relationships for the models
		$actions = SocialAction::get_by_edge_type_code(
			$fromModel,
			$toModel
		)->filter('ParentCode', 'ADM');

		// get the permission IDs for the admin actions for the models and check the member has them
		if ($permissionIDs = $actions->map('PermissionID', 'Code')->toArray()) {
			if ($permissionCodes = Permission::get()->filter('ID', $permissionIDs)->column('Code')) {
				return Permission::checkMember($member, $permissionCodes, "any");
			}
		}
		return false;
	}

	/**
	 * Checks 'default' rules such as if passed a Member and an SocialOrganisation then the member can only EDT
	 * if a MemberOrganisationRelationship of type 'CRT' exists. These are set up by the
	 * SocialAction.RequirePrevious relationship.
	 *
	 * @param string|array $actionCodes           - three letter code e.g. 'MEO' for Member edit Organistion
	 * @param DataObject   $fromModel
	 * @param DataObject   $toModel
	 * @param array        $requirementTally      - list of relationship Types checked and the result of permission
	 *                                            check
	 * @return boolean
	 */
	public static function check_rules(DataObject $fromModel, DataObject $toModel, $actionCodes, array &$requirementTally = []) {
		// e.g. get all 'EDT' Actions from e.g. Model to SocialOrganisation
		$actions = SocialAction::get_heirarchy(
			$fromModel,
			$toModel,
			$actionCodes
		);

		$old = SystemData::disable();
		// check each relationships 'RequirePrevious' exists in the corresponding relationship table for the model
		// instances
		/** @var SocialAction $action */
		foreach ($actions as $action) {
			// NB: only handle has_ones at the moment, need to refactor if we move to multiple previous requirements
			if ($action->RequirePreviousID) {

				/** @var SocialAction $requiredAction */
				$requiredAction = SocialAction::get()->byID($action->RequirePreviousID);

				// now we have a required SocialAction which may be a parent or child
				// if a parent we can't check the relationship exists directly, as there
				// are no Allowed... constraints on a parent, so we need to get the child
				// action which matches the parent code. e.g. for a CRT we need to
				// get the MemberOrganisationRelationship record with 'MCO'

				if (!$requiredAction->ParentID) {
					$requiredAction = SocialAction::get()->filter([
						'AllowedFrom' => $fromModel->class,
						'AllowedTo'   => $toModel->class,
						'ParentCode'  => $requiredAction->Code,
					])->first();
				}
				// get the instance of the required relationship if it exists
				$requiredRelationship = $requiredAction->checkRelationshipExists(
					$fromModel->ID,
					$toModel->ID
				);
				$recordExists = (bool) $requiredRelationship;

				$requirementTally[ $requiredAction->Code ] = $recordExists;
			}
		}
		SystemData::enable($old);

		// if no tally then we didn't hit any requirement to check so OK.
		if ($requirementTally) {
			foreach ($requirementTally as $exists) {
				if (!$exists) {
					// fail a requirement
					return false;
				}
			}
			// all requirements met
			return true;
		}
		// no requirements found so OK
		return true;
	}

	/**
	 * Given a relationship type code checks to see if that the check will pass 'as if' an action was previously created according to 'implied rules'.
	 *
	 * So we need to go back through all previous relationships between two models and see if any of them have a implied relationship which satisfies the
	 * required relationships being checked.
	 *
	 * For example given a relationship of type 'EDT' then that would be satisified by the immediate Require Previous rule of 'CRT' however it can also
	 * be satisfied by the relationship 'REG' from the 'implied relationship' of 'REG' to 'CRT' as if a 'CRT' record had been created in the past along
	 * with the 'REG' record which WAS created.
	 *
	 * @param \DataObject $fromModel
	 * @param \DataObject $toModel
	 * @param array       $actionCodes expected to be already a heirarchy
	 * @return bool true if an implied rule satisfying existing rules was found
	 */
	protected static function check_implied_rules(DataObject $fromModel, DataObject $toModel, $actionCodes) {
		// we start with fail as we are relying on an implied rule to make permissions OK
		$permissionOK = false;

		$old = SystemData::disable();

		$actions = SocialAction::get_heirarchy($fromModel, $toModel, $actionCodes);

		/** @var SocialAction $action */
		foreach ($actions as $action) {

			// if the relationship type requires a previous to have been made/action performed
			if ($action->RequirePreviousID) {
				// get the required relationship
				/** @var SocialAction $requiredAction */
				if ($requiredAction = SocialAction::get()->byID($action->RequirePreviousID)) {

					// get the SocialModel class name for this particular SocialAction
					$relationshipClassName = $action->getRelationshipClassName();
					$previous = $relationshipClassName::history(
						$fromModel,
						$toModel
					);

					/** @var SocialRelationship $prev */
					foreach ($previous as $prev) {
						// search previous relationships for an implied relationship matching the expected one
						if ($found = $prev->Action()->ImpliedActions()->find('ID', $requiredAction->ID)) {
							$permissionOK = true;
							// break out of both foreach loops so we can continue to enable SystemData again so can't early return.
							break 2;
						}
					}

				}
			}
		}
		SystemData::enable($old);
		// will only be true if an implied rule was found
		return $permissionOK;
	}

	/**
	 * Build permission code from class name and prefix e.g. Member and CAN_FOLLOW_
	 *
	 * Pads $code right to one '_' if not already there.
	 * Replaces non-alpha in title with '_'.
	 *
	 * @param string $code  e.g. 'CAN_APPROVE' or 'SYS'
	 * @param string $title e.g. 'Members' or 'Placeholder'
	 * @return string
	 */
	public static function make_permission_code($code, $title) {
		return str_replace(
			['__'],
			['_'],
			$code . '_' . preg_replace('/[^A-Za-z_]/', '_', $title));
	}

}
