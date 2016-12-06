<?php
namespace Modular\Edges;

class MemberMember extends SocialRelationship   {
	const FromModelClass = 'Member';
	const ToModelClass = 'Member';
	const FromFieldName = 'FromMember';
	const ToFieldName = 'ToMember';
}