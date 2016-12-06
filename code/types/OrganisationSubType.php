<?php
namespace Modular\Types;

/**
 * SocialOrganisationSubType
 * @method SocialOrganisationType SocialOrganisationType
 * @method \SS_List Organisations
 * @method SocialOrganisationType OrganisationType
 */
class SocialOrganisationSubType extends SocialType {
	private static $has_one = [
		'OrganisationType' => 'SocialOrganisationType',
	];
	private static $belongs_many_many = [
		'Organisations' => 'SocialOrganisation',
	];
	private static $singular_name = 'SocialOrganisation Sub-type';

	private static $plural_name = 'SocialOrganisation Sub-types';

	public function OrganisationTypeTitle() {
		return $this->OrganisationType() ? $this->OrganisationType()->Title : '~';
	}

}