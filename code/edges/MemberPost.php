<?php
namespace Modular\Edges;

class MemberPost extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass = 'Modular\Models\SocialPost';
	const FromFieldName = 'FromMember';
	const ToFieldName = 'ToPost';
}