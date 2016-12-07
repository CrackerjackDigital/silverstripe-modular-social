<?php
namespace Modular\Edges;

/**
 * ActionType record between an organisation and an organisation.
 */
class OrganisationOrganisation extends SocialRelationship {
	const FromModelClass = 'Modular\Models\SocialOrganisation';
	const ToModelClass = 'Modular\Models\SocialOrganisation';
	// const FromFieldName = 'FromModel';
	// const ToFieldName = 'FromModel';

}