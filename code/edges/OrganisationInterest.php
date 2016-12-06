<?php
namespace Modular\Edges;

/**
 * ActionType between a Member and an SocialInterestType.
 */
class OrganisationInterest extends SocialRelationship {
	const FromModelClass = 'Modular\Models\SocialOrganisation';
	const ToModelClass = 'Modular\Models\SocialInterestType';
	const FromFieldName = 'FromOrganisation';
	const ToFieldName = 'ToInterest';

}