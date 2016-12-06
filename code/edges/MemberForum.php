<?php
namespace Modular\Edges;

class MemberForum extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass = 'Modular\Models\SocialForum';
	const FromFieldName = 'FromMember';
	const ToFieldName = 'ToForum';

}