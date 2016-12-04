<?php
namespace Modular\Extensions\Model;

use ArrayData;
use Member;
use Modular\Actions\Createable;
use Modular\Actions\Registerable;
use Modular\Interfaces\SocialModel;

/**
 * Adds fields, relationships and functionality to the SilverStripe framework Member object.
 */
class SocialModelMember extends SocialModel implements SocialModel {
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
		'MembershipType' => 'MembershipType',
	];
	private static $belongs_many_many = [
		'NotifyRelationshipTypes' => 'ActionType',
	];
	private static $has_many = [
		'RelatedMembers'       => 'MemberMemberRelationship.FromMember',
		'RelatedOrganisations' => 'MemberOrganisationRelationship.FromMember',
		'RelatedForums'        => 'MemberForumRelationship.FromMember',
		'RelatedForumTopics'   => 'MemberForumTopicRelationship.FromMember',
		'RelatedPosts'         => 'MemberPostRelationship.FromMember',
		'RelatedRssFeeds'      => "MemberRssFeedRelationship.FromMember",
	];
	// fields to show by action.
	private static $fields_for_mode = [
		Registerable::Action => [
			'FirstName'        => true,
			'Surname'          => true,
			'Email'            => ['EmailField', true],
			'MembershipTypeID' => ['Select2Field', true],
			// Organisation chooser gets added by updateFieldsForMode
		],
		Createable::Action   => [
			'FirstName'        => true,
			'Surname'          => true,
			'Email'            => ['EmailField', true],
			'Phone'            => true,
			'MembershipTypeID' => ['Select2Field', true],
			// Organisation chooser gets added by updateFieldsForMode
		],
		Editable::Action     => [
			'FirstName' => true,
			'Surname'   => true,
			'Email'     => ['EmailField', true],
			'Phone'     => true,
			'Bio'       => ['TextareaField', true],
		],
		Viewable::Action     => [
			'FirstName'        => false,
			'Surname'          => false,
			'Email'            => false,
			'Phone'            => false,
			'Bio'              => false,
			'Interests'        => false,
			'MembershipTypeID' => false,
		],
		Listable::Action     => [
			'FirstName'    => false,
			'Surname'      => false,
			'Email'        => ['EmailField', false],
			'Phone'        => false,
			'Bio'          => 'TextareaField',
			'ProfileImage' => 'ImageField',
			'Interests'    => false,
		],
		Searchable::Action   => [
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
	 * @return OrganisationChooserField
	 */
	protected function OrganisationChooser() {
		return (new OrganisationChooserField(
			$this()->getOrganisationID()
		));
	}

	protected function OrganisationTypesChooser() {
		$chooser = new OrganisationTypesChooser();

		return $chooser;
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
				Registerable::Action,
				Createable::Action,
				Editable::Action,
			]
		)) {

			if (in_array($mode, [
				Createable::Action,
				Registerable::Action,
			])
			) {
				if ($this()->hasExtension('ApproveableExtension')) {
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
					Createable::Action,
					Registerable::Action,
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
			->filter(['ActionType.Code' => ['MJO', 'MRO', 'MEM', 'MCO']])
			->first();

		if ($org) {
			return ArrayData::create(["OrgModel" => $org->ToOrganisation()]);
		}

		return false;
	}

	/**
	 *
	 * Get member's created Organisation
	 *
	 **/
	public function MemberCreatedOrganisation() {
		$org = $this->owner->RelatedOrganisations()
			->filter(['ActionType.Code' => ['MCO', 'MRO']])
			->first();
		if ($org) {
			return $org->ToOrganisation();
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
	 * @return DataList
	 */
	public static function getAdmins() {
		$clientsGroup = DataObject::get_one('Group', "Title = 'Administrators'");
		return $clientsGroup->Members();

	}

}