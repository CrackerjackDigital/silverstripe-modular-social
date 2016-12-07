<?php
namespace Modular\Edges;

/**
 * ActionType between a Member and a PostReply.
 */
class MemberPostReply extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass = 'Modular\Models\SocialPostReply';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToModelReply';
}