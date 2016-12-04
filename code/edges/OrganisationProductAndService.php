<?php
namespace Modular\Edges;

/**
 * ActionType between a Member and an OrganisationProductAndServiceType.
 */
class OrganisationProductAndService extends SocialEdge  {
	const FromModelClass = 'Organisation';
	const ToModelClass   = 'OrganisationProductAndServiceType';

}