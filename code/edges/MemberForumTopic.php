<?php
namespace Modular\Edges;

class MemberForumTopic extends SocialRelationship {
	const FromModelClass = 'Member';
	// const FromFieldName = 'FromMember';

	const ToModelClass   = 'Modular\Models\SocialForumTopic';
	// const ToFieldName = 'ToModel';
}