<?php
namespace Modular\Types;

use ArrayList;
use DataObject;
use Modular\Collections\Graph\EdgeTypeList;
use Modular\config;
use Modular\Edges\SocialRelationship;
use Modular\Types\Graph\DirectedEdgeType;
use Modular\reflection;
use Modular\Traits\custom_create;
use Modular\Traits\custom_get;
use TreeDropdownField;

/**
 * SocialActions are the core rules for the SocialModel system which describe what relationships are allowed between
 * what models, what actions can be performed to create/delete the actual relationship records
 * and who gets notified when one is made or broken.
 *
 * @property string Title
 * @property string ModelTag
 * @property string ActionName
 * @property string ReverseActionName
 * @property string ReverseTitle
 * @property string SocialEdgeType
 * @property string FromModel
 * @property string ToModel
 * @property string Code
 * @property string ParentCode
 * @property string ShowInActionLinks
 * @property string ShowInActionButtons
 * @property string PermissionPrefix
 * @property string ActionLinkType
 *
 * @method \SS_List NotifyMembers()
 * @method \SS_List NotifyGroups()
 * @method \SS_List ImpliedActions()
 * @method SocialEdgeType|null RequirePrevious()
 *
 */
class SocialEdgeType extends DirectedEdgeType {
	const ActionCode                  = '';
	const RelationshipClassNamePrefix = '';
	const RelationshipClassNameSuffix = '';
	const RelationshipNamePrefix      = '';
	const RelationshipNameSuffix      = '';

	const CodeFieldName   = \Modular\Fields\Code::SingleFieldName;
	const CodeFieldSchema = \Modular\Fields\Code::SingleFieldSchema;

	const ParentCodeFieldName = self::CodeFieldName;

	private static $code_field_name = self::CodeFieldName;

	private static $custom_class_name = 'Modular\Types\SocialEdgeType';
	private static $custom_list_class_name = 'Modular\Collections\Graph\DirectedEdgeTypeList';

	private static $admin_groups = [
		'administrators' => true,
		'social-admin'   => true,
	];

	private static $indexes = [
		self::CodeFieldName       => true,
		self::ParentCodeFieldName => true,
	];

	private static $db = [
		// 'Title'            from Modular\Fields\Title
		// 'Code'             from Modular\Fields\Code
		'ActionName'              => 'Varchar(12)',                             // e.g. 'Follow'
		'ReverseActionName'       => 'Varchar(12)',                             // e.g. 'Unfollow'
		'ReverseTitle'            => 'Varchar(64)',                             // e.g for Title of 'Follows' would be 'Followed by'
		self::ParentCodeFieldName => self::CodeFieldSchema,                     // Code of Parent (e.g. 'LIK' for 'MLM'), for simplicity, not in record
		'ShowInActionLinks'       => 'Int',                                     // show this action in action-link menus if not 0
		'ShowInActionButtons'     => 'Int',                                     // show this action in action-button menus if not 0
		'PermissionPrefix'        => 'Varchar(32)',                             // e.g. 'CAN_APPROVE_' for approval relationships, not in record,
		'ActionLinkType'          => "enum('nav,modal,inplace')"                // when clicked what to do?
	];
	private static $has_one = [
		'Parent'          => 'Modular\Types\SocialEdgeType',                        // typical parent relationship
		'Permission'      => 'Permission',                                      // what permission is required to make/break a relationship
		'NotifyFrom'      => 'Member',                                          // who emails are sent from when one is made/broken
		'RequirePrevious' => 'Modular\Types\SocialEdgeType'                         // e.g. for 'EDT' then a 'CRT' MemberPost relationship must exist
	];
	private static $has_many = [
		'Relationships' => 'Modular\Edges\SocialRelationship',
	];
	private static $many_many = [
		'ImpliedActions' => 'Modular\Types\SocialEdgeType',
		// when this relationship is created also create these between member and model
		'NotifyMembers'  => 'Member',
		// who (Members) get notified when made/broken
		'NotifyGroups'   => 'Group',
		// who (Security Groups) get notified
	];
	private static $belongs_many_many = [
		'TriggerAction' => 'Modular\Types\SocialEdgeType'                           // back relationship to 'ImpliedActions'
	];
	private static $summary_fields = [
		'Title',
		'ReverseTitle',
		self::CodeFieldName,
		\Modular\Types\Graph\DirectedEdgeType::NodeAFieldName,
		\Modular\Types\Graph\DirectedEdgeType::NodeBFieldName,
		'ActionName',
		'ReverseActionName',
		'ActionLinkType',
	];
	private static $singular_name = 'Action';

	private static $plural_name = 'Actions';

	public function __invoke() {
		return $this;
	}

	/**
	 * Return the name of the field used as the unique identity for this edge type, in this case 'Code'.
	 *
	 * @return string
	 */
	public static function code_field_name($suffix = '') {
		return static::config()->get('code_field_name') . $suffix;
	}

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->addFieldToTab(
			'Root.Main',
			new TreeDropdownField(
				'RequirePreviousID',
				'Require previous relationship',
				'SocialEdgeType'
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
	 * -    FromFieldName           e.g. 'FromModel'
	 * -    ToFieldName             e.g. 'ToModel'
	 * -    FromModel               e.g. 'Member'
	 * -    ToModel                 e.g. 'Modular\Modela\Social\Organisation'
	 * -    RelationshipClassName   e.g. 'MemberOrganisation'
	 * -    RelationshipName        e.g. 'RelatedMembers'
	 *
	 * NB you can use list(,,$useThisOne,,,$andThisOne) to ignore ones you're not using.
	 *
	 * @param string $fieldNameSuffix to append to field names returned
	 * @return array
	 */
	public function getEdgeInfo($fieldNameSuffix = 'ID') {
		return [
			static::from_field_name(),
			static::to_field_name(),
			$this->{static::from_field_name()},
			$this->{static::to_field_name()},
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
		return self::check_permission($fromModel, $toModel, $this->Code, false);
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
		// add additional relationships between models as listed in SocialEdgeType.ImpliedActions

		$created = new \ArrayList();

		foreach ($this->ImpliedActions() as $impliedAction) {
			// we might have a parent code so look up the suitable 'real' code.
			/** @var SocialEdgeType $implied */
			$implieds = SocialEdgeType::get_heirarchy(
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
	 * Returns admin groups (keys) from config.admin_groups which have a truthish value.
	 *
	 * @return array
	 */
	public static function admin_groups() {
		return array_keys(array_filter(static::config()->get('admin_groups')));
	}

}
