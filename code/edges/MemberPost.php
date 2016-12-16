<?php
namespace Modular\Edges;

class MemberPost extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass = 'Modular\Models\Social\Post';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToPost';
}