<?php
namespace Modular\Edges;

/**
 * SocialEdgeType record between a member and an organisation.
 */
class MemberOrganisation extends SocialRelationship {
	const NodeAClassName = 'Member';
	const NodeBClassName   = 'Modular\Models\Social\Organisation';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToOrganisation';

	private static $db = [
		"Body" => "Text",
	];

}