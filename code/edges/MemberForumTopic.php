<?php
namespace Modular\Edges;

class MemberForumTopic extends SocialRelationship {
	const NodeAClassName = 'Member';
	// const FromFieldName = 'FromMember';

	const NodeBClassName   = 'Modular\Models\Social\ForumTopic';
	// const ToFieldName = 'ToModel';
}