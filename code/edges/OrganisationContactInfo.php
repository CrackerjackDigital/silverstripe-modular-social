<?php
namespace Modular\Edges;

/**
 * SocialRelationship between a SocialOrganisation and SocialContactInfo
 */
class OrganisationContactInfo extends SocialRelationship  {
	const NodeAClassName = 'Modular\Models\Social\Organisation';
	const NodeBClassName = 'Modular\Models\Social\ContactInfo';
	// const FromFieldName = 'FromOrganisation';
	// const ToFieldName = 'ToContactInfo';
}