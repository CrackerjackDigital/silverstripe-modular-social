<?php
namespace Modular\Edges;

class MemberRssFeed extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass = 'Modular\Models\Social\RssFeed';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToRss';
}