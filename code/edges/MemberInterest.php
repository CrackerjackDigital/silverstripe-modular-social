<?php
namespace Modular\Edges;

/**
 * SocialRelationship between a Member and an SocialInterestType.
 */
class MemberInterest extends SocialRelationship {
	const FromModelClass = 'Member';
	const ToModelClass   = 'Modular\Types\SocialInterestType';

}