<?php
namespace Modular\Edges;

/**
 * ActionType between a Member and an SocialInterestType.
 */
class OrganisationInterest extends SocialRelationship {
	const FromModelClass = 'Modular\Models\Social\Organisation';
	const ToModelClass = 'Modular\Models\Social\InterestType';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'ToInterest';

}