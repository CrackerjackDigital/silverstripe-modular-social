<?php
namespace Modular\Edges;

/**
 * SocialRelationship between a SocialOrganisation and SocialContactInfo
 */
class OrganisationContactInfo extends SocialRelationship  {
	const FromModelClass = 'Modular\Models\Social\Organisation';
	const ToModelClass = 'Modular\Models\Social\ContactInfo';
	// const FromFieldName = 'FromOrganisation';
	// const ToFieldName = 'ToContactInfo';
}