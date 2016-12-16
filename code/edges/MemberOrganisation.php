<?php
namespace Modular\Edges;

/**
 * ActionType record between a member and an organisation.
 */
class MemberOrganisation extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass   = 'Modular\Models\Social\Organisation';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToOrganisation';

	private static $db = [
		"Body" => "Text",
	];

}