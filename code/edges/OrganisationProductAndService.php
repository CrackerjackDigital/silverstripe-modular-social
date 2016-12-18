<?php
namespace Modular\Edges;

/**
 * SocialEdgeType between a Member and an SocialOrganisationProductAndServiceType.
 */
class OrganisationProductAndService extends SocialRelationship  {
	const NodeAClassName = 'Modular\Models\Social\Organisation';
	const NodeBClassName   = 'Modular\Types\Social\OrganisationProductAndServiceType';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToProductAndService';

}