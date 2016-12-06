<?php
namespace Modular\Types;

class SocialMembershipType extends SocialType {
	private static $db = [
		// db fields are from Common Fields, Approveable, SystemData etc extensions
	];
	private static $has_many = [
		'Members' => 'Member',
	];
    private static $approveable_mode = \Modular\Actions\Approveable::ApprovalAutomatic;

}