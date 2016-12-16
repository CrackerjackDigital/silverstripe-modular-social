<?php
namespace Modular\Extensions\Model;

use ArrayData;
use ConfirmedPasswordField;
use DataList;
use DataObject;
use FieldList;
use Member;
use Modular\Actions\Approveable;
use Modular\Actions\Createable;
use Modular\Actions\Editable;
use Modular\Actions\Registerable;
use Modular\UI\Components\Social\OrganisationChooser;
use Modular\UI\Components\Social\OrganisationSubTypeChooser;
use ValidationResult;

/**
 * Adds fields, relationships and functionality to the SilverStripe framework Member object.
 */
class SocialMember extends SocialModel {
	const GuestMemberField = 'GuestMemberFlag';
	const GuestMemberYes   = 1;

	private static $db = [
		'Phone'                => 'Varchar',
		'Bio'                  => 'Text',
		'Interests'            => 'Text',
		'isEmailPrivate'       => 'Boolean',
		'isPhoneNumberPrivate' => 'Boolean',
		self::GuestMemberField => 'Boolean',
	];
	private static $has_one = [
		'ProfileImage'   => 'Image',
		'MembershipType' => 'SocialMembershipType',
	];
	private static $belongs_many_many = [
		'NotifyRelationshipTypes' => '\Modular\Types\Social\Action',
	];
	private static $has_many = [
		'RelatedMembers'       => 'MemberMemberRelationship.FromModel',
		'RelatedOrganisations' => 'MemberOrganisationRelationship.FromModel',
		'RelatedForums'        => 'MemberForumRelationship.FromModel',
		'RelatedForumTopics'   => 'MemberForumTopicRelationship.FromModel',
		'RelatedPosts'         => 'MemberPostRelationship.FromModel',
		'RelatedRssFeeds'      => "MemberRssFeedRelationship.FromModel",
	];
	// fields to show by action.
	private static $fields_for_mode = [
		\Modular\Actions\Registerable::ActionName => [
			'FirstName'        => true,
			'Surname'          => true,
			'Email'            => ['EmailField', true],
			'MembershipTypeID' => ['Select2Field', true],
			// SocialOrganisation chooser gets added by updateFieldsForMode
		],
		\Modular\Actions\Createable::ActionName   => [
			'FirstName'        => true,
			'Surname'          => true,
			'Email'            => ['EmailField', true],
			'Phone'            => true,
			'MembershipTypeID' => ['Select2Field', true],
			// SocialOrganisation chooser gets added by updateFieldsForMode
		],
		\Modular\Actions\Editable::ActionName     => [
			'FirstName' => true,
			'Surname'   => true,
			'Email'     => ['EmailField', true],
			'Phone'     => true,
			'Bio'       => ['TextareaField', true],
		],
		\Modular\Actions\Viewable::ActionName     => [
			'FirstName'        => false,
			'Surname'          => false,
			'Email'            => false,
			'Phone'            => false,
			'Bio'              => false,
			'Interests'        => false,
			'MembershipTypeID' => false,
		],
		\Modular\Actions\Listable::ActionName     => [
			'FirstName'    => false,
			'Surname'      => false,
			'Email'        => ['EmailField', false],
			'Phone'        => false,
			'Bio'          => 'TextareaField',
			'ProfileImage' => 'ImageField',
			'Interests'    => false,
		],
		\Modular\Actions\Searchable::ActionName   => [
			'FirstName'        => false,
			'Surname'          => false,
			'MembershipTypeID' => 'Select2Field',
		],
	];
	private static $default_sort = 'Sort,Title';

	private static $summary_fields = [
		'FirstName',
		'Surname',
		'Email',
		'Title',
	];
	private static $singular_name = 'Member';

	private static $route_part = 'member';

	/**
	 * Return form component used to modify this relationship.
	 *
	 * @return OrganisationChooser
	 */
	protected function OrganisationChooser() {
		return (new OrganisationChooser(
			$this()->getOrganisationID()
		));
	}

	protected function OrganisationTypesChooser() {
		return new OrganisationSubTypeChooser();
	}

	public function Title() {
		return $this->owner->FirstName . " " . $this->owner->Surname;
	}

	public function TitleWithOrganisationName() {
		if ($this->MemberOrganisation()) {
			$organisationTitle = " (" . $this->MemberOrganisation()->OrgModel->Title . ")";
		} else {
			$organisationTitle = "";
		}

		return $this()->FirstName . " " . $this()->Surname . $organisationTitle;
	}

	/**
	 * If a new Member and email address doesn't exist already with approval checking disabled.
	 *
	 * @param \ValidationResult $result
	 * @throws \ValidationException
	 */
	public function validate(ValidationResult $result) {
		if (!$this()->isInDB()) {
			$email = $this()->Email;
			$enable = Approveable::disable();
			$exists = Member::get()->filter('Email', $email)->count();
			Approveable::enable($enable);

			if ($email && $exists) {
				$result->error("A member already exists with email address '$email'");
			}
		}
	}

	/**
	 * Add a OrganisationChooser to fields, hide the CreateNew field on the chooser depending on mode.
	 *
	 * @param DataObject $model
	 * @param FieldList  $fields
	 * @param            $mode
	 * @param array      $requiredFields
	 */
	public function updateFieldsForMode(DataObject $model, FieldList $fields, $mode, array &$requiredFields = []) {

		if (in_array(
			$mode,
			[
				Registerable::ActionName,
				Createable::ActionName,
				Editable::ActionName,
			]
		)) {

			if (in_array($mode, [
				Createable::ActionName,
				Registerable::ActionName,
			])
			) {
				if ($this()->hasExtension('\Modular\Actions\Approveable')) {
					$fields->push(
						$field = new ConfirmedPasswordField('Password', 'Password')
					);
					$field->setValue($model->Password);
				}

				$fields->push(
					$this->OrganisationTypesChooser()
				);

				$fields->push(
					$chooser = $this->OrganisationChooser()
				);

				// only show create new if we are registering or creating a new organisation
				if (!in_array($mode, [
					Createable::ActionName,
					Registerable::ActionName,
				])
				) {

					$chooser->showCreateNew(false);
				}
			}

		}
	}

	/**
	 * Return the first Member found with guest flag on.
	 *
	 * @return Member|null
	 */
	public static function guest_member() {
		return Member::get()->filter(self::GuestMemberField, self::GuestMemberYes)->first();
	}

	/**
	 * Return the current member or if not logged in the guest member
	 *
	 * @return Member
	 */
	public static function current_or_guest() {
		return Member::currentUser() ?: self::guest_member();
	}

	public function InterestsView() {
		$ex = explode(", ", $this->owner->Interests);
		if (count($ex) > 0) {
			return implode(", ", $ex);
		} else {
			return false;
		}

	}

	/**
	 *
	 * Get Current member's organisation
	 *
	 **/
	public function MemberOrganisation() {
		$org = $this()->RelatedOrganisations()
			->filter(['Type.Code' => ['MJO', 'MRO', 'MEM', 'MCO']])
			->first();

		if ($org) {
			return ArrayData::create(["OrgModel" => $org->ToModel()]);
		}

		return false;
	}

	/**
	 *
	 * Get member's created SocialOrganisation
	 *
	 **/
	public function MemberCreatedOrganisation() {
		$org = $this->owner->RelatedOrganisations()
			->filter(['Type.Code' => ['MCO', 'MRO']])
			->first();
		if ($org) {
			return $org->ToModel();
		}

		return false;
	}

	/**
	 *
	 * Email and Phone number privacy check
	 *
	 **/
	public function PrivacyCheck($target) {
		if ($target == 'email') {
			return $this()->isEmailPrivate;
		} else if ($target == 'phone') {
			return $this()->isPhoneNumberPrivate;
		} else {
			return true;
		}

	}

	/**
	 *
	 * Returns first character of the organisation name
	 *
	 **/
	public function FirstCharacter() {
		$ch = substr($this()->FirstName, 0, 1);
		if (is_numeric($ch)) {
			return '#';
		} else {
			return strtolower($ch);
		}

	}

	public function getFieldsForMode($mode) {
		return \Modular\Module::get_config_setting('fields_for_mode', $mode, __CLASS__);
	}

	public function endpoint() {
		return $this()->config()->get('route_part');
	}

	/**
	 *
	 * Check user profile completion status
	 *
	 * @return Boolean
	 *
	 */
	public function isProfileCompleted() {
		if (empty($this()->FirstName) ||
			empty($this()->Surname) ||
			empty($this()->Phone) ||
			empty($this()->Bio)
		) {

			return false;
		} else {
			return true;
		}
	}

	/**
	 *
	 * Check if member has at least one interest added
	 *
	 */
	public function hasAddedInterests() {
		if (count($this()->Interests) != 0) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 *
	 * Used for back button during editing
	 *
	 **/
	public function EditSourceLink() {
		return "member / " . $this()->ID . " / view";
	}

	/**
	 * Return a list of people in the 'Administrator' group
	 * @return DataList
	 */
	public static function getAdmins() {
		/** @var \Group $clientsGroup */
		$clientsGroup = DataObject::get_one('Group', "Code = 'admin'");
		return $clientsGroup->Members();

	}

}