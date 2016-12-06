<?php
namespace Modular\Edges;

/**
 * ActionType between a Member and an SocialOrganisationProductAndServiceType.
 */
class OrganisationProductAndService extends SocialRelationship  {
	const FromModelClass = 'Modular\Models\SocialOrganisation';
	const ToModelClass   = 'Modular\Types\SocialOrganisationProductAndServiceType';
	const FromFieldName = 'FromOrganisation';
	const ToFieldName = 'ToProductAndService';

}