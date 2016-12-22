<?php
namespace Modular\Edges;

class MemberForumTopic extends SocialRelationship {
	const NodeAClassName = 'Member';
	const NodeBClassName   = 'Modular\Models\Social\ForumTopic';
	// const FromFieldName = 'FromMember';
	// const ToFieldName = 'ToModel';
}