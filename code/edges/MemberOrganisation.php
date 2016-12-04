<?php
namespace Modular\Edges;

/**
 * ActionType record between a member and an organisation.
 */
class MemberOrganisation extends SocialEdge {
	const FromModelClass = 'Member';
	const ToModelClass   = 'Organisation';

	private static $db = [
		"Body" => "Text",
	];

}