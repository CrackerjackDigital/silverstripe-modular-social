<?php
namespace Modular\Edges;

class OrganisationPost extends SocialRelationship {
	const FromModelClass = 'Modular\Models\SocialOrganisation';
	const ToModelClass = 'Modular\Models\SocialPost';
	const FromFieldName = 'FromOrganisation';
	const ToFieldName = 'ToPost';
}