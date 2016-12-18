<?php
namespace Modular\Types\Social;
use Modular\Types\SocialType;

/**
 * User: wakes
 * Date: 15/12/14
 * Time: 10:32 AM
 */
class InterestType extends SocialType {
	private static $db = [
		\Modular\Types\Graph\DirectedEdgeType::NodeAFieldName => 'Varchar(32)',
	];
	private static $has_one = [

	];
	private static $has_many = [
		'Organisations' => 'OrganisationInterestAction.ToInterestType',
		'Members'       => 'MemberInterestAction.ToInterestType',
	];
	private static $many_many_extraFields = [
	];
	private static $default_sort = 'Sort,Title';

	private static $summary_fields = [
		'Title',
		\Modular\Types\Graph\DirectedEdgeType::NodeAFieldName,
		'Approved',
	];
	private static $singular_name = 'Interest';

	private static $plural_name = 'Interests';

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		return $fields;
	}
}