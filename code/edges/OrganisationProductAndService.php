<?php
namespace Modular\Edges;

/**
 * ActionType between a Member and an SocialOrganisationProductAndServiceType.
 */
class OrganisationProductAndService extends SocialRelationship  {
	const FromModelClass = 'Modular\Models\Social\Organisation';
	const ToModelClass   = 'Modular\Types\Social\OrganisationProductAndServiceType';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToProductAndService';

}