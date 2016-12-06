<?php
namespace Modular\Edges;

class MemberRssFeed extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass = 'Modular\Models\SocialRssFeed';
	const FromFieldName = 'FromMember';
	const ToFieldName = 'ToRss';
}