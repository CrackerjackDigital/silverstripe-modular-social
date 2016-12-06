<?php
namespace Modular\Edges;

class MemberForumTopic extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass   = 'Modular\Models\SocialForumTopic';

}