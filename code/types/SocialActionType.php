<?php
namespace Modular\Types;

use DataList;
use DataObject;
use Group;
use Member;
use Modular\Collections\Graph\DirectedEdgeTypeList;
use Modular\Edges\SocialRelationship;
use Modular\Extensions\Model\SocialMember;
use Modular\Fields\Code;
use Modular\Fields\SystemData;
use Modular\Fields\Title;
use Modular\Interfaces\Graph\EdgeType as EdgeTypeInterface;
use Modular\Traits\config;
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
 * @property string Action
 * @property string ReverseAction
 * @method \SS_List NotifyMembers()
 * @method \SS_List NotifyGroups()
 * @method \SS_List ImpliedActions()
 * @method SocialActionType|null RequirePrevious()
 *
 */
class SocialActionType extends SocialType implements EdgeTypeInterface {
	use config;
	
	// name of the field used to store the short action code, e.g. 'Code' (which stores a value 'LIK')
	const ActionCodeFieldName = Code::SingleFieldName;
	
	const EdgeTypeClassName = SocialRelationship::class;
	
	// name of the field used to store the Action, e.g. 'Action' (which stores a value 'like')
	const ActionFieldName = 'Action';
	// name of the field used to store the Action, e.g. 'Action' (which stores a value 'unlike')
	const ReverseActionFieldName = 'ReverseAction';
	
	// name of the field used to store the 'Title' of the action, e.g. 'Like'
	const TitleFieldName = Title::SingleFieldName;
	// name of the field used to store the displayable 'Title' of the reverse action, e.g. 'Unlike'
	const ReverseTitleFieldName = 'ReverseTitle';
	
	// if there is a constant prefix on relationship names, e.g. 'Related' for 'RelatedMembers'
	const RelationshipNamePrefix = '';
	
	// if there is a constant suffix on relationship names, e.g. 'Models' for 'MemberModels'
	const RelationshipNameSuffix = '';
	
	// name of the field which holds the model class name this edge can connect 'from'
	const FromFieldName = 'FromModel';
	
	// name of the field which holds the model class name this edge can connect 'to'
	const ToFieldName = 'ToModel';
	
	private static $admin_groups = [
		'administrators' => true,
		'social-admin'   => true,
	];
	
	private static $indexes = [
		'AllowedClassNames' => 'FromModel,ToModel',
		'ParentCode'        => true,
	];
	
	private static $db                = [
		// e.g. value of 'Follow'
		self::ActionFieldName        => 'Varchar(12)',
		// e.g. value of 'Unfollow'
		self::ReverseActionFieldName => 'Varchar(12)',
		// e.g for Title of 'Follows' would be 'Followed by'
		self::ReverseTitleFieldName  => 'Varchar(64)',
		// e.g. value of 'Member'
		self::FromFieldName          => 'Varchar(255)',
		// e.g. value of 'Modular\Models\Social\Organisation'
		self::ToFieldName            => 'Varchar(255)',
		// show this action in action-link menus if not 0
		'ShowInActionLinks'          => 'Int',
		// show this action in action-button menus if not 0
		'ShowInActionButtons'        => 'Int',
		// Code of Parent (e.g. 'LIK' for 'MLM'), for simplicity finding
		'ParentCode'                 => Code::SingleFieldSchema,
		// e.g. 'CAN_APPROVE_' for approval relationships
		'PermissionPrefix'           => 'Varchar(32)',
		// when clicked what to do?
		'ActionLinkType'             => "enum('nav,modal,inplace')",
	];
	private static $has_one           = [
		'Parent'          => self::class,
		// typical parent relationship
		'Permission'      => Permission::class,
		// what permission is required to make/break a relationship
		'NotifyFrom'      => Member::class,
		// who emails are sent from when one is made/broken
		'RequirePrevious' => self::class,
		// e.g. for 'EDT' then a 'CRT' MemberPost relationship must exist
	];
	private static $has_many          = [
		'Relationships' => SocialRelationship::class,
	];
	private static $many_many         = [
		// when this relationship is created also create these between member and model
		'ImpliedActions' => self::class,
		// who (Members) get notified when made/broken
		'NotifyMembers'  => Member::class,
		// who (Security Groups) get notified
		'NotifyGroups'   => Group::class,
	];
	private static $belongs_many_many = [
		'TriggerAction' => self::class,
		// back relationship to 'ImpliedActions'
	];
	private static $summary_fields    = [
		self::ActionCodeFieldName,
		self::ActionFieldName,
		self::TitleFieldName,
		self::ReverseTitleFieldName,
		self::FromFieldName,
		self::ToFieldName,
		'ActionLinkType',
	];
	
	private static $singular_name = 'Action';
	private static $plural_name   = 'Actions';
	
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
				'SocialActionType'
			)
		);
		
		return $fields;
	}

	/**
	 * Check a relationship of this SocialActionType exists between two objects. Looks for relationships
	 * and returns the first action of the type performed (in order provided).
	 *
	 * @param int    $nodeAID
	 * @param int    $nodeBID
	 * @param string $order
	 * @return \DataObject|\Modular\Edges\SocialRelationship
	 */
	public function findRelationship($nodeAID, $nodeBID, $order = 'ID desc') {
		return static::get()->filter(
			static::archetype(
				$nodeAID,
				$nodeBID,
				$this->{static::edge_type_filter_field_name()}
			)
		)->sort($order)->first();
	}
	
	/**
	 * Check that rules are met for this SocialActionType instance to be allowed,
	 * e.g. a previous relationship exists.
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
	 * Check that permissions are met for this SocialActionType instance to be allowed
	 *
	 * @param DataObject $fromModel
	 * @param DataObject $toModel
	 * @return bool|int
	 */
	public function checkPermission(DataObject $fromModel, DataObject $toModel) {
		return self::check_permission($fromModel, $toModel, $this->Code);
	}
	
	/**
	 * Find the implied actions for a given action and create those records in the database between the supplied models.
	 *
	 * @param \DataObject $fromModel
	 * @param \DataObject $toModel
	 */
	public function createImpliedActions(DataObject $fromModel, DataObject $toModel) {
		// add additional relationships between models as listed in SocialActionType.ImpliedActions
		
		foreach ($this->ImpliedActions() as $impliedAction) {
			// we might have a parent code so look up the suitable 'real' code.
			/** @var SocialActionType $implied */
			$implied = static::get_heirarchy(
				$fromModel,
				$toModel,
				$impliedAction->Code
			)->first();
			
			// now make the implied relationship
			SocialRelationship::ma(
				$fromModel,
				$toModel,
				$implied->Code
			);
		}
	}
	
	/**
	 * Return a filter which can be used to select EdgeTypes based on From and To models passed as instances or
	 * class names.
	 *
	 * @param DataObject|string $fromModel       a class name or model instance to get class name from
	 * @param DataObject|string $toModel         a class name or model instance to get class name from
	 * @param array|string      $actionTypeCodes single or array of codes
	 * @return array e.g. ['FromModel' => 'Member', 'ToModel' => 'Modular\Models\Social\Organisation', 'Code' => 'CRT' ]
	 */
	public static function archetype($fromModel = null, $toModel = null, $actionTypeCodes = []) {
		$filter = [];
		if ($fromModel) {
			$filter[ static::node_a_field_name() ] = self::derive_class_name($fromModel);
		}
		if ($toModel) {
			// we are looking for model classes
			$filter[ static::node_b_field_name() ] = self::derive_class_name($toModel);
		}
		if ($actionTypeCodes) {
			$filter[ static::edge_type_filter_field_name() ] = $actionTypeCodes;
		}
		
		return $filter;
	}
	
	/**
	 * Check that a SocialRelationship can exist (be created or is still valid) between two models.
	 *
	 * @param DataObject                               $fromModel
	 * @param DataObject                               $toModel
	 * @param mixed|\Modular\Interfaces\Graph\EdgeType $edgeType
	 * @return bool
	 */
	public static function valid(DataObject $fromModel, DataObject $toModel, $edgeType) {
		return static::check_permission($fromModel, $toModel)
		       && static::check_rules($fromModel, $toModel, $edgeType);
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
		/** @var SocialActionType $action */
		$action = static::get_by_edge_type_code(
			$fromModel,
			$toModel,
			$actionCode
		)->first();
		
		return $action->findRelationship($fromModel->ID, $toModel->ID);
	}
	
	/**
	 * Return the name of the DirectedEdgeType class for this Edge.
	 *
	 * @param string $fieldName optionally appended with a '.' e.g. for use when making a relationship join
	 * @return string
	 */
	public static function edge_type_class_name($fieldName = '') {
		return static::EdgeTypeClassName ? (static::EdgeTypeClassName . ($fieldName ? ".$fieldName" : '')) : '';
	}
	
	/**
	 * Social Action uses the 'Code' field to store type
	 *
	 * @return string
	 */
	public static function edge_type_filter_field_name() {
		return Code::single_field_name();
	}
	
	/**
	 * Convenience fetch helper.
	 *
	 * @param string|array $actionCodes
	 * @return SocialActionType|\DataObject
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
	 * Return all SocialActionType records which have the particular code(s) passed as their parent(s).
	 * e.g. passing 'LIK' will return 'MLO', 'MLG' etc which are children of the 'LIK' record. Does not return the
	 * 'LIK' record.
	 *
	 * @param string|array $parentActionCodes
	 * @return \SS_List|DirectedEdgeTypeList
	 */
	public static function get_by_parent($parentActionCodes) {
		return static::get()->filter('ParentCode', $parentActionCodes);
	}
	
	/**
	 * Returns a list of SocialActionType models which have the provided code or have the code as a Parent.
	 *
	 * @param string|DataObject $fromModelOrClassName
	 * @param string|DataObject $toModelOrClassName
	 * @param string|array      $actionCodes
	 * @return DataList|DirectedEdgeTypeList
	 */
	public static function get_heirarchy($fromModelOrClassName, $toModelOrClassName, $actionCodes) {
		$old = SystemData::disable();
		
		$actionCodes = array_filter(
			is_array($actionCodes)
				? $actionCodes
				: explode(',', $actionCodes)
		);
		
		$fromModelClass = ( $fromModelOrClassName instanceof DataObject ) ? $fromModelOrClassName->class
			: $fromModelOrClassName;
		$toModelClass   =
			( $toModelOrClassName instanceof DataObject ) ? $toModelOrClassName->class : $toModelOrClassName;
		
		// get relationship types for the code and the parent matching that code.
		$heirarchy = static::get()->filter(
			[
				'AllowedFrom' => $fromModelClass,
				'AllowedTo'   => $toModelClass,
			]
		);
		if ($actionCodes) {
			$heirarchy = $heirarchy->filterAny(
				[
					'Code'       => $actionCodes,
					'ParentCode' => $actionCodes,
				]
			);
		}
		SystemData::enable($old);
		
		return $heirarchy;
	}
	
	/**
	 * Returns all defined Actions from one model to another,
	 * optionally filtered by passed SocialActionType.Codes
	 *
	 * @param string|DataObject $fromModelClass
	 * @param string|DataObject $toModelClass
	 * @param array             $actionCodes
	 * @return DataList|DirectedEdgeTypeList
	 */
	public static function get_by_edge_type_code($fromModelClass, $toModelClass, $actionCodes = null) {
		if (is_object($fromModelClass)) {
			$fromModelClass = get_class($fromModelClass);
		}
		if (is_object($toModelClass)) {
			$toModelClass = get_class($toModelClass);
		}
		
		return static::get_heirarchy($fromModelClass, $toModelClass, $actionCodes);
	}
	
	/**
	 * Check to see if valid permissions to perform an actione exist between two objects.
	 *
	 * The 'from' object is generally (and by default) the logged in member, the 'to' object is e.g. an
	 * SocialOrganisation and the permission code is the three-letter code such as 'MAO' for 'Member Administer
	 * SocialOrganisation'.
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
		$toModel = ( $toModel instanceof DataObject ) ? $toModel : singleton($toModel);
		
		// check if owner is a member of ADMIN, social-admin or can administer the type in general.
		if (self::check_admin_permissions($fromModelOrLoggedInMember, $toModel)) {
			return true;
		}
		$permissionOK = false;
		
		$actions = static::get_heirarchy($fromModelOrLoggedInMember, $toModel, $actionCodes);
		
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
				// This check uses the SocialActionType.RequirePrevious relationship on the current SocialActionType
				
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
					// SocialActionType model, such as a Member requiring to be Confirmed then the Confirmable extension will
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
		$actions = static::get_by_edge_type_code(
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
	 * SocialActionType.RequirePrevious relationship.
	 *
	 * @param DataObject   $fromModel
	 * @param DataObject   $toModel
	 * @param string|array $actionCodes           - three letter code e.g. 'MEO' for Member edit Organisation
	 * @param array        $requirementTally      - list of relationship Types checked and the result of permission
	 *                                            check
	 * @return boolean
	 */
	public static function check_rules(DataObject $fromModel, DataObject $toModel, $actionCodes, array &$requirementTally = []) {
		// e.g. get all 'EDT' Actions from e.g. Model to SocialOrganisation
		$actions = static::get_heirarchy(
			$fromModel,
			$toModel,
			$actionCodes
		);
		
		$old = SystemData::disable();
		// check each relationships 'RequirePrevious' exists in the corresponding relationship table for the model
		// instances
		/** @var SocialActionType $action */
		foreach ($actions as $action) {
			// NB: only handle has_ones at the moment, need to refactor if we move to multiple previous requirements
			if ($action->RequirePreviousID) {
				
				/** @var SocialActionType $requiredAction */
				$requiredAction = static::get()->byID($action->RequirePreviousID);
				
				// now we have a required SocialActionType which may be a parent or child
				// if a parent we can't check the relationship exists directly, as there
				// are no Allowed... constraints on a parent, so we need to get the child
				// action which matches the parent code. e.g. for a CRT we need to
				// get the MemberOrganisationRelationship record with 'MCO'
				
				if (!$requiredAction->ParentID) {
					$requiredAction = static::get()->filter(
						[
							'AllowedFrom' => $fromModel->class,
							'AllowedTo'   => $toModel->class,
							'ParentCode'  => $requiredAction->Code,
						]
					)->first();
				}
				// get the instance of the required relationship if it exists
				$requiredRelationship = $requiredAction->findRelationship(
					$fromModel->ID,
					$toModel->ID
				);
				$recordExists         = (bool)$requiredRelationship;
				
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
	 * Given a relationship type code checks to see if that the check will pass 'as if' an action was previously
	 * created according to 'implied rules'.
	 *
	 * So we need to go back through all previous relationships between two models and see if any of them have a
	 * implied relationship which satisfies the required relationships being checked.
	 *
	 * For example given a relationship of type 'EDT' then that would be satisified by the immediate Require Previous
	 * rule of 'CRT' however it can also be satisfied by the relationship 'REG' from the 'implied relationship' of
	 * 'REG' to 'CRT' as if a 'CRT' record had been created in the past along with the 'REG' record which WAS created.
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
		
		$actions = static::get_heirarchy($fromModel, $toModel, $actionCodes);
		
		/** @var SocialActionType $action */
		foreach ($actions as $action) {
			
			// if the relationship type requires a previous to have been made/action performed
			if ($action->RequirePreviousID) {
				// get the required relationship
				/** @var SocialActionType $requiredAction */
				if ($requiredAction = static::get()->byID($action->RequirePreviousID)) {
					
					// get the SocialModel class name for this particular SocialActionType
					$relationshipClassName = $action->getRelationshipClassName();
					$previous              = $relationshipClassName::history(
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
	 * Return the name of the field which would be used in a query on this action to find an action From a model class.
	 * e.g. 'From'
	 *
	 * @param string $suffix this is a class name not a relationship name by default, however for a has_one
	 *                       relationship call this method with 'ID' to get the name of the relationship
	 *                       on the Edge for the 'From' model
	 * @return string e.g. 'From'
	 */
	public static function node_a_field_name($suffix = '') {
		return self::FromFieldName . $suffix;
	}
	
	/**
	 * Return the name of the field which would be used in a query on this action to find an action To a model class.
	 *
	 * @param string $suffix this is a class name not a relationship name by default, however for a has_one
	 *                       relationship call this method with 'ID' to get the name of the relationship
	 *                       on the Edge for the 'To' model
	 * @return string e.g. 'To'
	 */
	public static function node_b_field_name($suffix = '') {
		return self::ToFieldName . $suffix;
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
			[ '__' ],
			[ '_' ],
			$code . '_' . preg_replace('/[^A-Za-z_]/', '_', $title)
		);
	}
	
}
