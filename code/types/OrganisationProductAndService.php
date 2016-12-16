<?php
namespace Modular\Types\Social;

use Modular\Types\SocialType;

class OrganisationProductAndServiceType extends SocialType {
	private static $db = [
		\Modular\Types\SocialEdgeType::FromModelFieldName => 'Varchar(32)',
	];

	private static $has_many = [
		'Organisations' => 'OrganisationProductAndServiceAction.ToProductAndServiceType',
	];

	private static $singular_name = 'Product & Service Type';
	private static $plural_name = 'Product & Service Types';

	private static $default_sort = 'Sort,Title';

	private static $summary_fields = [
		'Title',
		\Modular\Types\SocialEdgeType::FromModelFieldName,
		'Approved'
	];
}