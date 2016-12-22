<?php
namespace Modular\Models\Social;

use ArrayData;
use CompaniesEntityDetails;
use Member;
use Modular\Models\SocialModel;
use Modular\Types\Social\OrganisationSubType;
use Modular\Types\Social\OrganisationType;
use Permission;
use ValidationException;
use ValidationResult;

/**
 * An SocialOrganisation public model.
 * @method OrganisationSubType OrganisationSubType()
 * @method \SS_List OrganisationSubTypes()
 * @method \DataList RelatedMembers()
 * @method \DataList RelatedInterests()
 * @method \DataList RelatedProductsAndServices()
 * @method \DataList RelatedPosts()
 * @method \DataList RelatedContactInfos()
 * @method \Image FeaturedImage()
 * @method \Image Logo()
 *
 * @property string   Title
 * @property string   Street
 * @property string   Suburb
 * @property string   City
 * @property string   Country
 * @property string   PhoneNumber
 * @property string   MobilePhoneNumber
 * @property string   Email
 * @property string   Website
 * @property string   MbieRegistrationNumber
 * @property int|null LogoID
 * @property int      FeaturedImageID
 * @property int      OrganisationSubTypeID
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
		"OrganisationSubType" => 'Modular\Models\Social\OrganisationSubType',
	];
	private static $has_many = [
		'RelatedMembers'             => 'MemberOrganisation.ToModel',
		'RelatedInterests'           => 'OrganisationInterest.FromModel',
		"RelatedProductsAndServices" => 'OrganisationProductAndService.FromModel',
		'RelatedPosts'               => 'OrganisationPost.FromModel',
		'RelatedContactInfos'        => 'OrganisationContactInfo.FromModel',
	];

	private static $many_many = [
		"OrganisationSubTypes" => 'Modular\Models\Social\OrganisationSubType',
	];
	// private static $default_sort = 'Sort,Title';

	private static $singular_name = 'Organisation';

	private static $route_part = 'organisation';

	// fields to show by mode.
	private static $fields_for_mode = [
		\Modular\Actions\Registerable::ActionName => [
			'MbieRegistrationNumber' => false,
		],
		\Modular\Actions\Createable::ActionName   => [
			'Title'       => true,
			'Logo'        => 'FileAttachmentField',
			'Website'     => false,
			'Description' => 'TextAreaField',
		],
		\Modular\Actions\Editable::ActionName     => [
			'Title'               => true,
			'PhoneNumber'         => true,
			'MobilePhoneNumber'   => true,
			'Website'             => false,
			'Email'               => true,
			'Description'         => 'TextAreaField',
			'ProductsAndServices' => false,
		],
		\Modular\Actions\Viewable::ActionName     => [
			'Title'                 => true,
			'Logo'                  => 'ImageField',
			'Website'               => false,
			'Description'           => true,
			'OrganisationSubTypeID' => true,
		],
		\Modular\Actions\Listable::ActionName     => [
			'Images'      => 'HasImagesField',
			'Logo'        => 'ImageField',
			'Title'       => true,
			'Description' => false,
		],
		\Modular\Actions\Searchable::ActionName   => [
			'Title'                 => ['TextField', false, 'Organisation Title'],
			'OrganisationSubTypeID' => true,
		],
		\Modular\Actions\Joinable::ActionName     => [
			// fields for join modal
			'Body' => ['TextAreaField', true, 'Reason for joining'],
		],
	];

	public function Link() {
		return "organisation/" . $this->ID;
	}

	/**
	 *
	 * SocialOrganisation Type
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
		return \GroupedList::create($subTypes)->groupBy('OrganisationTypeTitle');
	}

	public function getOrganisationSubTypes() {
		return $this->getManyManyComponents('OrganisationSubTypes');
	}

	/**
	 * Checks:
	 * - SocialOrganisation with provided Title not already in Database
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
	 * Unique SocialOrganisation cities array
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
			->filter(['Type.Code' => ['MJO', 'MRO', 'MEM', 'MCO']])
			->column('FromModelID');

		$uniquemembers = Member::get()->filter(['ID' => $memberIDs])->distinct(true)->setQueriedColumns(array('ID'));

		return $uniquemembers;
	}

	public function OrganisationFollowers() {
		$members = $this->RelatedMembers()
			->filter(['Type.Code' => ['FOL']]);

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

		// $checkAction_Edit = SocialEdgeType::check_permission("EDT", $this);

		$relatedMembers = $this->RelatedMembers();
		if ($relatedMembers->count() > 0) {
			$members = $relatedMembers->filter(["FromModelID" => $member->ID, "ToModelID" => $this->ID]);
			$members = $members->filter('Type.Code', ["MCO", "MAO", "MRO"]);
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
	 * SocialOrganisation's location info
	 *
	 * @return \DataList
	 */
	public function officeLocations() {
		$locationList = $this->RelatedContactInfos();
		$locationId = [];
		foreach ($locationList as $loc) {
			$locationId[] = $loc->ToContactInfo()->ID;
		}
		return ContactInfo::get()->filter(['ID' => $locationId]);
	}
}