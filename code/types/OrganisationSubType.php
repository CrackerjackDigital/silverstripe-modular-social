<?php
namespace Modular\Types\Social;
use Modular\Types\SocialType;

/**
 * SocialOrganisationSubType
 * @method OrganisationType SocialOrganisationType
 * @method \SS_List Organisations
 * @method OrganisationType OrganisationType
 */
class OrganisationSubType extends SocialType {
	private static $has_one = [
		'OrganisationType' => 'Modular\Types\SocialOrganisationType',
	];
	private static $belongs_many_many = [
		'Organisations' => 'Modular\Models\SocialOrganisation',
	];
	private static $singular_name = 'SocialOrganisation Sub-type';

	private static $plural_name = 'SocialOrganisation Sub-types';

	public function OrganisationTypeTitle() {
		return $this->OrganisationType() ? $this->OrganisationType()->Title : '~';
	}

}