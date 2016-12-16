<?php
namespace Modular\Edges;

/**
 * SocialEdgeType between a Member and a PostReply.
 */
class MemberPostReply extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass = 'Modular\Models\Social\PostReply';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToModelReply';
}