<?php
namespace Modular\Edges;

/**
 * SocialEdgeType between a Member and an SocialInterestType.
 */
class OrganisationInterest extends SocialRelationship {
	const NodeAClassName = 'Modular\Models\Social\Organisation';
	const NodeBClassName = 'Modular\Models\Social\InterestType';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToInterest';

}