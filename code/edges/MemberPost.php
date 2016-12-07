<?php
namespace Modular\Edges;

class MemberPost extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass = 'Modular\Models\SocialPost';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToPost';
}