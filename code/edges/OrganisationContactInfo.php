<?php
namespace Modular\Edges;

/**
 * ActionType between a SocialOrganisation and Contact Info
 */
class OrganisationContactInfo extends SocialRelationship  {
	const FromModelClass = 'Modular\Models\SocialOrganisation';
	const ToModelClass = 'Modular\Models\SocialContactInfo';

}