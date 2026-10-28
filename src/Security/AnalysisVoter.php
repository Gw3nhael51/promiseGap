<?php

namespace App\Security;

use App\Entity\Analysis;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Decides who can view or delete an analysis workspace
 *
 * @extends Voter<string, Analysis>
 */
final class AnalysisVoter extends Voter
{
    public const string VIEW = 'ANALYSIS_VIEW';
    public const string DELETE = 'ANALYSIS_DELETE';

    /**
     * Checks that the attribute and subject are handled by this voter
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::DELETE], true) && $subject instanceof Analysis;
    }

    /**
     * Grants access to the owner, to administrators and to anyone for shared demo analyses (view only)
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Analysis $subject */
        $user = $token->getUser();

        if ($user !== null && in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        if ($subject->isDemo()) {
            return $attribute === self::VIEW;
        }

        return $user !== null && $user->getUserIdentifier() === $subject->getOwnerIdentifier();
    }
}
