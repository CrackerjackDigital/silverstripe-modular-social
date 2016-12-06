<?php
namespace Modular\Edges;

/**
 * SocialRelationship between a SocialOrganisation and SocialContactInfo
 */
class OrganisationContactInfo extends SocialRelationship  {
	const FromModelClass = 'Modular\Models\SocialOrganisation';
	const ToModelClass = 'Modular\Models\SocialContactInfo';
	const FromFieldName = 'FromOrganisation';
	const ToFieldName = 'ToContactInfo';
}