<?php

namespace App\Policies\Marketplace;

use App\Models\Marketplace\ServiceEnquiry;
use App\Models\User;

/**
 * Two sides: the wedding (anyone with access to it, couples included) and the
 * provider (members with the right permission). Nobody else sees an enquiry.
 */
class ServiceEnquiryPolicy
{
    public function viewAsWedding(User $user, ServiceEnquiry $enquiry): bool
    {
        return $user->canAccessWedding($enquiry->wedding);
    }

    public function actAsWedding(User $user, ServiceEnquiry $enquiry): bool
    {
        return $user->canAccessWedding($enquiry->wedding);
    }

    public function viewAsProvider(User $user, ServiceEnquiry $enquiry): bool
    {
        return $enquiry->provider->allows($user, 'enquiries');
    }

    public function respondAsProvider(User $user, ServiceEnquiry $enquiry): bool
    {
        return ! $enquiry->provider->isBanned() && $enquiry->provider->allows($user, 'respond');
    }
}
