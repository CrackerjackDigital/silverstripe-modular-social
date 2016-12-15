<?php
namespace Modular\Types\Social;

use Modular\Types\SocialType;

class MembershipType extends SocialType {
	private static $has_many = [
		'Members' => 'Member',
	];
    private static $approveable_mode = \Modular\Actions\Approveable::ApprovalAutomatic;

}