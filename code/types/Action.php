<?php
namespace Modular\Types\Social;

use ArrayList;
use DataList;
use DataObject;
use Modular\config;
use Modular\Edges\SocialRelationship;
use Modular\Extensions\Model\SocialMember;
use Modular\Fields\Code;
use Modular\Fields\SystemData;
use Modular\Interfaces\Graph\EdgeType;
use Modular\reflection;
use Modular\Types\SocialType;
use Permission;
use TreeDropdownField;

/**
 * SocialActions are the core rules for the SocialModel system which describe what relationships are allowed between
 * what models, what actions can be performed to create/delete the actual relationship records
 * and who gets notified when one is made or broken.
 *
 * @property string ActionName
 * @property string ReverseActionName
 * @property string ActionType
 * @property string AllowedFrom
 * @property string AllowedTo
 * @property string Code
 * @property string ParentCode
 * @method \SS_List NotifyMembers()
 * @method \SS_List NotifyGroups()
 * @method \SS_List ImpliedActions()
 * @method ActionType|null RequirePrevious()
 *
 */
class ActionType extends SocialType implements EdgeType {
	use reflection;
	use config;

	const ActionCode                  = '';
	const RelationshipClassNamePrefix = '';
	const RelationshipClassNameSuffix = '';
	const RelationshipNamePrefix      = '';
	const RelationshipNameSuffix      = '';

	const CodeFieldName       = \Modular\Fields\Code::SingleFieldName;
	const FromModelFieldName  = 'FromModel';
	const ToModelFieldName    = 'ToModel';
	const ParentCodeFieldName = self::CodeFieldName;

	private static $admin_groups = [
		'administrators' => true,
		'social-admin'   => true,
	];

	private static $indexes = [
		'AllowedClassNames' => 'AllowedFrom,AllowedTo',
		'ParentCode'        => true,
	];

	private static $db = [
		// 'Title'            from Modular\Fields\Title
		// 'Code'             from Modular\Fields\Code
		'ActionName'              => 'Varchar(12)',                             // e.g. 'Follow'
		'ReverseActionName'       => 'Varchar(12)',                             // e.g. 'Unfollow'
		'ReverseTitle'            => 'Varchar(64)',                             // e.g for Title of 'Follows' would be 'Followed by'
		self::FromModelFieldName  => 'Varchar(64)',                             // e.g. 'Member'
		self::ToModelFieldName    => 'Varchar(64)',                             // e.g. 'SocialOrganisation'
		'LastBuildResult'         => 'Varchar(32)',                             // if this record was created, changed or unchanged by last build
		'ShowInActionLinks'       => 'Int',                                     // show this action in action-link menus if not 0
		'ShowInActionButtons'     => 'Int',                                     // show this action in action-button menus if not 0
		self::ParentCodeFieldName => \Modular\Fields\Code::SingleFieldSchema,   // Code of Parent (e.g. 'LIK' for 'MLM'), for simplicity, not in record
		'PermissionPrefix'        => 'Varchar(32)',                             // e.g. 'CAN_APPROVE_' for approval relationships, not in record,
		'ActionLinkType'          => "enum('nav,modal,inplace')"                // when clicked what to do?
	];
	private static $has_one = [
		'Parent'          => 'Modular\Types\ActionType',                        // typical parent relationship
		'Permission'      => 'Permission',                                      // what permission is required to make/break a relationship
		'NotifyFrom'      => 'Member',                                          // who emails are sent from when one is made/broken
		'RequirePrevious' => 'Modular\Types\ActionType'                         // e.g. for 'EDT' then a 'CRT' MemberPost relationship must exist
	];
	private static $has_many = [
		'Relationships' => 'Modular\Edges\SocialRelationship',
	];
	private static $many_many = [
		'ImpliedActions' => 'Modular\Types\ActionType',                         // when this relationship is created also create these between member and model
		'NotifyMembers'  => 'Member',                                           // who (Members) get notified when made/broken
		'NotifyGroups'   => 'Group',                                            // who (Security Groups) get notified
	];
	private static $belongs_many_many = [
		'TriggerAction' => 'Modular\Types\ActionType'                           // back relationship to 'ImpliedActions'
	];
	private static $summary_fields = [
		'Title',
		'ReverseTitle',
		'Code',
		'AllowedFrom',
		'AllowedTo',
		'ActionName',
		'ReverseActionName',
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
				'ActionType'
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
	 * -    FromModel                e.g. 'Member'
	 * -    ToModel               e.g. 'Modular\' (not SocialOrganisation)
	 * -    FromFieldName           e.g. 'FromModelID'
	 * -    ToFieldName             e.g. 'ToModelID'
	 * -    RelationshipClassName   e.g. 'MemberOrganisationRelationship'
	 * -    RelationshipName        e.g. 'RelatedMembers'
	 *
	 * NB you can use list(,,$useThisOne,,,$andThisOne) to ignore ones you're not using.
	 *
	 * @param string $fieldNameSuffix to append to field names returned
	 * @return array
	 */
	public function getEdgeInfo($fieldNameSuffix = 'ID') {
		return [
			$this->getFromName(),
			$this->getToName(),
			static::from_field_name($fieldNameSuffix),
			static::to_field_name($fieldNameSuffix),
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
	 * @param DataObject $fromModel
	 * @param DataObject $toModel
	 * @return bool|int
	 */
	public function checkPermission(DataObject $fromModel, DataObject $toModel) {
		return self::check_permission($this->Code, $fromModel, $toModel, false);
	}

	/**
	 * Check permissions and rules for a relationship.
	 *
	 * @param DataObject $toModel
	 * @param DataObject $fromMemberOrModel
	 * @return bool
	 */
	public function checkAllowed(DataObject $toModel, DataObject $fromMemberOrModel = null) {
		return $this->checkPermission($fromMemberOrModel, $toModel)
		&& $this->checkRules($fromMemberOrModel, $toModel);
	}

	/**
	 * Find the implied actions for a given action and create those records in the database between the supplied models.
	 *
	 * @param \DataObject  $fromModel
	 * @param \DataObject  $toModel
	 * @param array|string $variantData will be set on the created GraphEdges, using the name of the Edge class as a key into the values to set.
	 * @return \ArrayList
	 */
	public function createImpliedRelationships(DataObject $fromModel, DataObject $toModel, $variantData = []) {
		// add additional relationships between models as listed in ActionType.ImpliedActions

		$created = new \ArrayList();

		foreach ($this->ImpliedActions() as $impliedAction) {
			// we might have a parent code so look up the suitable 'real' code.
			/** @var ActionType $implied */
			$implieds = ActionType::get_heirarchy(
				$fromModel,
				$toModel,
				$impliedAction->Code
			);
			foreach ($implieds as $implied) {
				/** @var SocialRelationship $impliedRelationshipClass */
				$impliedRelationshipClass = $implied->getRelationshipClassName();

				// now make the implied relationship
				$created->merge(
					$impliedRelationshipClass::make(
						$fromModel,
						$toModel,
						$implied->Code,
						isset($variantData[ $impliedRelationshipClass ]) ? $variantData[ $impliedRelationshipClass ] : []
					)
				);
			}

		}
		return $created;
	}

	/**
	 * Return a class name like 'MemberOrganisation' manufactured from the From and To class names with prefix and suffix.
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
	 * Return the name of the field used as the unique identity for this edge type, in this case 'Code'.
	 *
	 * @return string
	 */
	public static function code_field_name($suffix = '') {
		return Code::single_field_name($suffix);
	}

	/**
	 * Returns a list of ActionType records from the database which apply to actions between two models provided (by their class names).
	 *
	 * e.g.     given 'Member', 'Organisation' ( or an instance of each/either) then would return all ActionType records that
	 *          implement a Edge between 'Member' and 'Organisation' by filtering by ActionType 'AllowedFrom' and 'AllowedTo' fields.
	 *
	 *          given 'Member', null returns all ActionType records can be performed going from a Member to any model
	 *
	 *          given null, 'Organisation' returns all ActionType records which can be performed on an Organisation
	 *
	 * @param  DataObject|string|array|null $fromModelClass an instance, class name, array of class names or null to not include in filter
	 * @param  DataObject|string|array|null $toModelClass
	 * @return \DataList
	 */
	public static function get_for_models($fromModelClass, $toModelClass, $typeCodes = []) {
		// always turn into an array of class names
		$fromModelClasses = static::derive_class_name($fromModelClass);
		$toModelClasses = static::derive_class_name($toModelClass);

		$filter = EdgeType::archtype($fromModelClasses, $toModelClasses, $typeCodes);
		return static::get()->filter($filter);
	}

	public static function from_field_name($suffix = '') {
		return static::FromModelFieldName . $suffix;
	}

	public static function to_field_name($suffix = '') {
		return static::ToModelFieldName . $suffix;
	}

	/**
	 * Return a filter which can be used to select a Action based on passed parameters (of which some may be empty).
	 *
	 * @param \DataObject|string $nodeAClass
	 * @param \DataObject|string $nodeBClass
	 * @param array              $typeCodes
	 * @return array e.g. [ 'FromModel' => 'Member', 'ToModel' => 'SocialOrganisation', 'Code' => ['CRT', 'REG'] ]
	 */
	public static function archtype($nodeAClass, $nodeBClass, $typeCodes = []) {
		$fromFieldName = static::from_field_name();
		$toFieldName = static::to_field_name();
		$identifyFieldName = self::code_field_name();

		$archtype = [];

		if ($nodeAClass = static::derive_class_name($nodeAClass)) {
			$archtype[ $fromFieldName ] = $nodeAClass;
		}
		if ($nodeBClass = static::derive_class_name($nodeBClass)) {
			$archtype[ $toFieldName ] = $nodeBClass;
		}
		if ($typeCodes) {
			$archtype[ $identifyFieldName ] = $typeCodes;
		}
		return $archtype;
	}

	/**
	 * Return the possible actions between two objects, optionally restricted by ActionType.ActionType.
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

		$filter = ActionType::archtype(
			$fromModel,
			$toModel,
			$restrictTo
		);
		return ActionType::get()->filter($filter);

	}

	/**
	 * Convenience fetch helper.
	 *
	 * @param string|array $actionCodes
	 * @return ActionType
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
	 * Return all ActionType records which have the particular code(s) passed as their parent(s).
	 * e.g. passing 'LIK' will return 'MLO', 'MLG' etc which are children of the 'LIK' record. Does not return the
	 * 'LIK' record.
	 *
	 * @param string|array $parentActionCodes
	 * @return SS_List
	 */
	public static function get_by_parent($parentActionCodes) {
		return ActionType::get()->filter('ParentCode', $parentActionCodes);
	}

	/**
	 * Returns a list of ActionType models which have the provided code or have the code as a Parent.
	 *
	 * @param string|DataObject $fromModelClass
	 * @param string|DataObject $toModelClass
	 * @param string|array      $typeCodes
	 * @return DataList
	 */
	public static function get_heirarchy($fromModelClass, $toModelClass, $typeCodes) {
		$old = SystemData::disable();

		$typeCodes = static::parse_type_codes($typeCodes);
		$fromModelClass = static::derive_class_name($fromModelClass);
		$toModelClass = static::derive_class_name($toModelClass);

		// get relationship types for the code and the parent matching that code.
		$heirarchy = ActionType::get()->filter([
			'AllowedFrom' => $fromModelClass,
			'AllowedTo'   => $toModelClass,
		]);
		if ($typeCodes) {
			$heirarchy = $heirarchy->filterAny([
				'Code'       => $typeCodes,
				'ParentCode' => $typeCodes,
			]);
		}
		SystemData::enable($old);

		return $heirarchy;
	}

	/**
	 * Returns all defined Actions from one model to another,
	 * optionally filtered by passed ActionType.Codes
	 *
	 * @param string|DataObject $fromModelClass
	 * @param string|DataObject $toModelClass
	 * @param array             $actionCodes
	 * @return DataList
	 */
	public static function get_by_archtype($fromModelClass, $toModelClass, $actionCodes = null) {
		if (is_object($fromModelClass)) {
			$fromModelClass = get_class($fromModelClass);
		}
		if (is_object($toModelClass)) {
			$toModelClass = get_class($toModelClass);
		}
		return ActionType::get_heirarchy($fromModelClass, $toModelClass, $actionCodes);
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
	 * @param DataObject|string $fromModel                 - either class name or an instance of it
	 * @param bool              $checkObjectInstances      - if we have instances of the from and to models then check
	 *                                                     rules are met
	 * @return bool|int
	 */
	public static function check_permission(
		$actionCodes,
		$fromModel,
		$toModel,
		$checkObjectInstances = true
	) {
		// generally we're check the current site viewer though may wany to check e.g. if an SocialOrganisation can do something
		$fromModel = $fromModel ?: SocialMember::current_or_guest();

		// sometimes we only have the model class name to go on, get a singleton to make things easier
		$toModel = ($toModel instanceof DataObject) ? $toModel : singleton($toModel);

		// check if owner is a member of ADMIN, social-admin or can administer the type in general.
		if (self::check_admin_permissions($fromModel, $toModel)) {
			return true;
		}
		$permissionOK = false;

		$actions = ActionType::get_heirarchy($fromModel, $toModel, $actionCodes);

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
					$fromModel
				);

				// now we get more specific; if we were handed a model object it should have an ID so also check that
				// instance rules are met, such as a previous relationship existing (if just a class was passed to function
				// then we have a singleton and we can't check these requirements).
				// This check uses the ActionType.RequirePrevious relationship on the current ActionType

				if ($permissionOK && $toModel->ID && $checkObjectInstances) {

					$actionCodes = $actions->column('Code');

					$permissionOK = self::check_rules(
						$fromModel,
						$toModel,
						$actionCodes
					);

					if (!$permissionOK) {
						$permissionOK = self::check_implied_rules(
							$fromModel,
							$toModel,
							$actionCodes
						);
					}
				}

				if ($permissionOK) {
					// now we ask the models to check themselves, e.g. if they require a field to be set outside of the permissions
					// ActionType model, such as a Member requiring to be Confirmed then the Confirmable extension will
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
		$actions = ActionType::get_for_models(
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
	 * ActionType.RequirePrevious relationship.
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
		$actions = ActionType::get_heirarchy(
			$fromModel,
			$toModel,
			$actionCodes
		);

		$old = SystemData::disable();
		// check each relationships 'RequirePrevious' exists in the corresponding relationship table for the model
		// instances
		/** @var ActionType $action */
		foreach ($actions as $action) {
			// NB: only handle has_ones at the moment, need to refactor if we move to multiple previous requirements
			if ($action->RequirePreviousID) {

				/** @var ActionType $requiredAction */
				$requiredAction = ActionType::get()->byID($action->RequirePreviousID);

				// now we have a required ActionType which may be a parent or child
				// if a parent we can't check the relationship exists directly, as there
				// are no Allowed... constraints on a parent, so we need to get the child
				// action which matches the parent code. e.g. for a CRT we need to
				// get the MemberOrganisationRelationship record with 'MCO'

				if (!$requiredAction->ParentID) {
					$requiredAction = ActionType::get()->filter([
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

		$actions = ActionType::get_heirarchy($fromModel, $toModel, $actionCodes);

		/** @var ActionType $action */
		foreach ($actions as $action) {

			// if the relationship type requires a previous to have been made/action performed
			if ($action->RequirePreviousID) {
				// get the required relationship
				/** @var ActionType $requiredAction */
				if ($requiredAction = ActionType::get()->byID($action->RequirePreviousID)) {

					// get the relationship class name for this particular ActionType
					/** @var SocialRelationship|string $relationshipClassName */
					$relationshipClassName = $action->getRelationshipClassName();

					// find all the
					$previous = $relationshipClassName::graph(
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
