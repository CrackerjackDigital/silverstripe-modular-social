<?php
/**
 * OrganisationType
 * @method DataList OrganisationSubTypes()
 */
class OrganisationType extends SocialType {
	private static $db = [
		'Fred' => 'Boolean'
	];

	private static $has_many = [
		'OrganisationSubTypes' => 'OrganisationSubType',
	];
	private static $singular_name = 'Organisation Type';
	private static $plural_name = 'Organisation Types';

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
		foreach ($subTypes as $subType) {
			foreach ($subType->Organisations() as $org) {
				$orgs[] = $org->ID;
			}
		}
		return Organisation::get()->filter(["ID" => $orgs]);
	}

	public function OrganisationTypeTitle() {
		return $this->Title;
	}

}