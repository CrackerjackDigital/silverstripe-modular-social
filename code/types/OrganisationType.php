<?php
namespace Modular\Types;
use Modular\Models\SocialOrganisation;

/**
 * SocialOrganisationType
 * @method \DataList OrganisationSubTypes()
 */
class SocialOrganisationType extends SocialType {
	private static $has_many = [
		'OrganisationSubTypes' => 'Modular\Types\SocialOrganisationSubType',
	];
	private static $singular_name = 'SocialOrganisation Type';
	private static $plural_name = 'SocialOrganisation Types';

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

	public function Organisations() {
		$subTypes = $this->OrganisationSubTypes();
		$orgs = [];
		/** @var SocialOrganisationSubType $subType */
		foreach ($subTypes as $subType) {
			foreach ($subType->Organisations() as $org) {
				$orgs[] = $org->ID;
			}
		}
		return SocialOrganisation::get()->filter(["ID" => $orgs]);
	}

	public function OrganisationTypeTitle() {
		return $this->Title;
	}

}