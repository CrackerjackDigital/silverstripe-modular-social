<?php
namespace Modular\Types;

class SocialMembershipType extends SocialType {
	private static $has_many = [
		'Members' => 'Member',
	];
    private static $approveable_mode = \Modular\Actions\Approveable::ApprovalAutomatic;

}