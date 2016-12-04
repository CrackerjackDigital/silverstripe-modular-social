<?php
use Modular\Actions\Createable;
use Modular\Actions\Editable;
use Modular\Actions\Listable;
use Modular\Actions\Registerable;
use Modular\Actions\Viewable;
use Modular\Models\SocialModel as SocialModel;

/**
 * An Organisation public model.
 */
class Organisation extends SocialModel {
	private static $db = [
		'Street'                 => 'Varchar(255)',
		'Suburb'                 => 'Varchar(255)',
		'City'                   => 'Varchar(255)',
		'Country'                => 'Varchar(255)',
		'PhoneNumber'            => 'Varchar(100)',
		'MobilePhoneNumber'      => 'Varchar',
		'Email'                  => 'Varchar(255)',
		'Website'                => 'Varchar(255)',
		'MbieRegistrationNumber' => 'Varchar',
	];
	private static $has_one = [
		"FeaturedImage"       => "Image",
		"OrganisationSubType" => "OrganisationSubType",
	];
	private static $has_many = [
		'RelatedMembers'             => 'MemberOrganisationAction.ToOrganisation',
		'RelatedInterests'           => 'OrganisationInterestAction.FromOrganisation',
		"RelatedProductsAndServices" => 'OrganisationProductAndServiceAction.FromOrganisation',
		'RelatedPosts'               => 'OrganisationPostAction.FromOrganisation',
		'RelatedContactInfo'         => 'OrganisationContactInfoAction.FromOrganisation',
	];

	private static $many_many = [
		"OrganisationSubTypes" => "OrganisationSubType",
	];
	// private static $default_sort = 'Sort,Title';

	private static $singular_name = 'Organisation';

	private static $route_part = 'organisation';

	// fields to show by mode.
	private static $fields_for_mode = [
		Registerable::Action => [
			'MbieRegistrationNumber' => false,
		],
		Createable::Action   => [
			'Title'       => true,
			'Logo'        => 'FileAttachmentField',
			'Website'     => false,
			'Description' => 'TextAreaField',
		],
		Editable::Action     => [
			'Title'               => true,
			'PhoneNumber'         => true,
			'MobilePhoneNumber'   => true,
			'Website'             => false,
			'Email'               => true,
			'Description'         => 'TextAreaField',
			'ProductsAndServices' => false,
		],
		Viewable::Action     => [
			'Title'                 => true,
			'Logo'                  => 'ImageField',
			'Website'               => false,
			'Description'           => true,
			'OrganisationSubTypeID' => true,
		],
		Listable::Action     => [
			'Images'      => 'HasImagesField',
			'Logo'        => 'ImageField',
			'Title'       => true,
			'Description' => false,
		],
		Searchable::Action   => [
			'Title'                 => ['TextField', false, 'Organisation Title'],
			'OrganisationSubTypeID' => true,
		],
		Joinable::Action     => [ // fields for join modal
		                                   'Body' => ['TextAreaField', true, 'Reason for joining'],
		],
	];

	public function Link() {
		return "organisation/" . $this->ID;
	}

	/**
	 *
	 * Organisation Type
	 *
	 */
	public function OrganisationTypes() {
		$subTypes = $this->OrganisationSubTypes();

		if ($subTypes->count() == 0) {
			if ($this->OrganisationSubTypeID != 0) {
				$subTypes->add($this->OrganisationSubTypeID);
				$subTypes = $this->OrganisationSubTypes();
			}
		}

		$typeIDs = [];
		foreach ($subTypes as $subType) {
			$typeIDs[] = $subType->OrganisationTypeID;
		}
		if (count($typeIDs) != 0) {
			return OrganisationType::get()->filter(["ID" => $typeIDs]);
		} else {
			return false;
		}
	}

	public function GetGroupedTypes() {
		$subTypes = $this->OrganisationSubTypes();

		if ($subTypes->count() == 0) {
			if ($this->OrganisationSubTypeID != 0) {
				$subTypes->add($this->OrganisationSubTypeID);
				$subTypes = $this->OrganisationSubTypes();
			}
		}
		return GroupedList::create($subTypes)->Groupedby('OrganisationTypeTitle');
	}

	public function getOrganisationSubTypes() {
		return $this->getManyManyComponents('OrganisationSubTypes');
	}

	/**
	 * Checks:
	 * - Organisation with provided Title not already in Database
	 *
	 * @return ValidationResult|void
	 * @throws ValidationException
	 */
	public function validate() {
		if (!$this->isInDB()) {
			if (Organisation::get()->filterAny(['Title' => $this->Title, 'MbieRegistrationNumber' => $this->MbieRegistrationNumber])->count()) {
				throw new ValidationException("Sorry, an organisation with that name already exists", 400);
			}
		}
		return parent::validate();
	}

	public function RelatedItems() {
		return new ArrayData([
			'Title' => 'Members',
		]);
	}

	/**
	 *
	 * Returns first character of the organisation name
	 *
	 **/
	public function FirstCharacter() {
		$ch = substr($this->Title, 0, 1);
		if (is_numeric($ch)) {
			return '#';
		} else {
			return strtolower($ch);
		}

	}

	/**
	 *
	 * Unique Organisation cities array
	 *
	 * @return array
	 *
	 **/
	public static function CitiesArray() {
		$res = self::get()->column('City');
		sort($res);
		$res = array_unique($res);
		$out = [];
		foreach ($res as $key => $value) {
			$out[ $value ] = $value;
		}
		return $out ?: [];
	}

	/**
	 * If Title is empty and we have an MbieRegistrationNumber then fill title
	 * field from MBIE details api lookup via CompaniesEntityDetails lookup.
	 */
	public function onBeforeWrite() {
		if (empty($this->Title) && !empty($this->MbieRegistrationNumber)) {
			//MBIE Get full company details
			$CompanyDetails = CompaniesEntityDetails::create()->search($this->MbieRegistrationNumber);
			if ($CompanyDetails) {
				$this->Title = $CompanyDetails->OrganisationName;
			}
		}

		parent::onBeforeWrite();
	}

	/**
	 * Return Members for this organisation with membership action codes
	 *
	 * @param string|array $actionCodes
	 * @return \DataList of Members
	 */
	public function OrganisationMembers() {
		$memberIDs = $this->RelatedMembers()
			->filter(['ActionType.Code' => ['MJO', 'MRO', 'MEM', 'MCO']])
			->column('FromMemberID');

		$uniquemembers = Member::get()->filter(['ID' => $memberIDs])->distinct(true)->setQueriedColumns(array('ID'));

		return $uniquemembers;
	}

	public function OrganisationFollowers() {
		$members = $this->RelatedMembers()
			->filter(['ActionType.Code' => ['FOL']]);

		return $members ? $members : false;
	}

	public function canEdit($member = null) {
		if (Permission::check("CMS_ACCESS_CMSMain")) {
			return true;
		}

		$member = Member::currentUser();
		if (!$member) {
			return false;
		}

		// $checkAction_Edit = ActionType::check_permission("EDT", $this);

		$relatedMembers = $this->relatedMembers();
		if ($relatedMembers->count() > 0) {
			$members = $relatedMembers->filter(["FromMemberID" => $member->ID, "ToOrganisationID" => $this->ID]);
			$members = $members->filter('ActionType.Code', ["MCO", "MAO", "MRO"]);
			if ($members->count() > 0) {
				return true;
			} else {
				return false;
			}
		}

		return false;
	}

	/**
	 *
	 * Used for back button during editing
	 *
	 **/
	public function EditSourceLink() {
		return "organisation/" . $this->ID . "/view";
	}

	public function getModelId() {
		return $this->ID;
	}

	public function endpoint() {
		return $this->config()->get('route_part');
	}

	/**
	 *
	 * Check organisation profile completition status
	 *
	 * @return Boolean
	 *
	 */

	public function isProfileCompleted() {
		if ($this->RelatedProductsAndServices()->count() == 0) {
			return false;
		}

		if (empty($this->Title) ||
			empty($this->Description) ||
			empty($this->PhoneNumber)
		) {

			return false;
		} else {
			return true;
		}
	}

	/**
	 *
	 * Organisation's location info
	 *
	 * @return ArrayData
	 */
	public function officeLocations() {
		$locationList = $this->RelatedContactInfo();
		$locationId = [];
		foreach ($locationList as $loc) {
			$locationId[] = $loc->ToContactInfo()->ID;
		}
		return ContactInfo::get()->filter(['ID' => $locationId]);
	}
}