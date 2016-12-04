<?php
/**
 * User: wakes
 * Date: 18/12/14
 * Time: 2:18 PM
 */

/**
 * OrganisationSubType
 * @method OrganisationType OrganisationType
 * @method SS_List Organisations
 */
class OrganisationSubType extends SocialType {
	private static $has_one = [
		'OrganisationType' => 'OrganisationType',
	];
	private static $belongs_many_many = [
		'Organisations' => 'Organisation',
	];
	private static $singular_name = 'Organisation Sub-type';

	private static $plural_name = 'Organisation Sub-types';

	public function OrganisationTypeTitle() {
		return $this->OrganisationType() ? $this->OrganisationType()->Title : '~';
	}

}