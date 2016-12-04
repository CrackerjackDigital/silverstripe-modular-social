<?php

/**
 * User: wakes
 * Date: 15/12/14
 * Time: 10:32 AM
 */
class InterestType extends SocialType {
	private static $db = [
		'AllowedFrom' => 'Varchar(32)',
	];
	private static $has_one = [
	];
	private static $has_many = [
		'Organisations' => 'OrganisationInterestAction.ToInterestType',
		'Members'       => 'MemberInterestAction.ToInterestType',
	];
	private static $many_many = [

	];
	private static $many_many_extraFields = [
	];
	private static $default_sort = 'Sort,Title';

	private static $summary_fields = [
		'Title',
		'AllowedFrom',
		'Approved',
	];
	private static $singular_name = 'Interest';

	private static $plural_name = 'Interests';

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		return $fields;
	}
}