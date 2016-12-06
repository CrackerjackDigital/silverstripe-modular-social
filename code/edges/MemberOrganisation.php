<?php
namespace Modular\Edges;

/**
 * ActionType record between a member and an organisation.
 */
class MemberOrganisation extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass   = 'Modular\Models\SocialOrganisation';

	private static $db = [
		"Body" => "Text",
	];

}