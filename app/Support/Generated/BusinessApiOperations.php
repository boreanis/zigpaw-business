<?php

declare(strict_types=1);

namespace App\Support\Generated;

/**
 * GENERATED from contracts/business-v1.json and contracts/clinical-v1.json.
 * Do not edit manually; run `php artisan business:sync-contracts` instead.
 */
final class BusinessApiOperations
{
    /** @var array<string, string> */
    public const CONTRACT_SHA256 = [
        'business-v1' => '3005512e235530c4c1db641ed5ab925660ebf006e5cf9d77ee5752bd09d711d6',
        'clinical-v1' => '2cc197bdf15d26b24cde83d8985979adf0819058bc20f3fe00a23047ba29257f',
    ];

    /** @var list<array{contract: string, operation_id: string, method: string, path: string}> */
    private const OPERATIONS = [
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.booking-profiles.index',
            'method' => 'GET',
            'path' => '/v1/business/booking-profiles',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.booking-profiles.update',
            'method' => 'PUT',
            'path' => '/v1/business/booking-profiles',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.bookings.index',
            'method' => 'GET',
            'path' => '/v1/business/bookings',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.bookings.show',
            'method' => 'GET',
            'path' => '/v1/business/bookings/{booking}',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.bookings.messages.index',
            'method' => 'GET',
            'path' => '/v1/business/bookings/{booking}/messages',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.bookings.pet-context.show',
            'method' => 'GET',
            'path' => '/v1/business/bookings/{booking}/pet-context',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.bookings.response.store',
            'method' => 'POST',
            'path' => '/v1/business/bookings/{booking}/response',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.dashboard',
            'method' => 'GET',
            'path' => '/v1/business/clinical/dashboard',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.me',
            'method' => 'GET',
            'path' => '/v1/business/clinical/me',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.organizations.index',
            'method' => 'GET',
            'path' => '/v1/business/clinical/organizations',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.provider-grants.index',
            'method' => 'GET',
            'path' => '/v1/business/clinical/provider-grants',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.provider-grants.show',
            'method' => 'GET',
            'path' => '/v1/business/clinical/provider-grants/{grant}',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.provider-grants.care-context.show',
            'method' => 'GET',
            'path' => '/v1/business/clinical/provider-grants/{grant}/care-context',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.provider-grants.care-submissions.store',
            'method' => 'POST',
            'path' => '/v1/business/clinical/provider-grants/{grant}/care-submissions',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.provider-grants.media.index',
            'method' => 'GET',
            'path' => '/v1/business/clinical/provider-grants/{grant}/media',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.provider-grants.media.show',
            'method' => 'GET',
            'path' => '/v1/business/clinical/provider-grants/{grant}/media/{media}',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.session.destroy',
            'method' => 'DELETE',
            'path' => '/v1/business/clinical/session',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.submissions.index',
            'method' => 'GET',
            'path' => '/v1/business/clinical/submissions',
        ],
        [
            'contract' => 'clinical-v1',
            'operation_id' => 'v1.business.clinical.submissions.show',
            'method' => 'GET',
            'path' => '/v1/business/clinical/submissions/{submission}',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.financials.show',
            'method' => 'GET',
            'path' => '/v1/business/financials',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.financials.agreements.index',
            'method' => 'GET',
            'path' => '/v1/business/financials/agreements',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.financials.commissions.index',
            'method' => 'GET',
            'path' => '/v1/business/financials/commissions',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.me.show',
            'method' => 'GET',
            'path' => '/v1/business/me',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.me.update',
            'method' => 'PATCH',
            'path' => '/v1/business/me',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.offerings.index',
            'method' => 'GET',
            'path' => '/v1/business/offerings',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.offerings.store',
            'method' => 'POST',
            'path' => '/v1/business/offerings',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.offerings.destroy',
            'method' => 'DELETE',
            'path' => '/v1/business/offerings/{offering}',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.offerings.update',
            'method' => 'PATCH',
            'path' => '/v1/business/offerings/{offering}',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.organizations.index',
            'method' => 'GET',
            'path' => '/v1/business/organizations',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.programs.index',
            'method' => 'GET',
            'path' => '/v1/business/programs',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.programs.store',
            'method' => 'POST',
            'path' => '/v1/business/programs',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.provider-claims.index',
            'method' => 'GET',
            'path' => '/v1/business/provider-claims',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.provider-claims.store',
            'method' => 'POST',
            'path' => '/v1/business/provider-claims',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.provider-claims.discovery.index',
            'method' => 'GET',
            'path' => '/v1/business/provider-claims/discovery',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.providers.index',
            'method' => 'GET',
            'path' => '/v1/business/providers',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.providers.update',
            'method' => 'PATCH',
            'path' => '/v1/business/providers/{providerLink}',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.session.destroy',
            'method' => 'DELETE',
            'path' => '/v1/business/session',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.team.index',
            'method' => 'GET',
            'path' => '/v1/business/team',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.team-invitations.accept',
            'method' => 'POST',
            'path' => '/v1/business/team-invitations/{membership}/accept',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.team.invitations.store',
            'method' => 'POST',
            'path' => '/v1/business/team/invitations',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.team.invitations.resend',
            'method' => 'POST',
            'path' => '/v1/business/team/invitations/{membership}/resend',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.team.memberships.destroy',
            'method' => 'DELETE',
            'path' => '/v1/business/team/memberships/{membership}',
        ],
        [
            'contract' => 'business-v1',
            'operation_id' => 'v1.business.team.memberships.update',
            'method' => 'PATCH',
            'path' => '/v1/business/team/memberships/{membership}',
        ],
    ];

    public static function allows(string $method, string $path): bool
    {
        $method = strtoupper($method);
        $path = '/'.ltrim((string) str($path)->before('?'), '/');

        foreach (self::OPERATIONS as $operation) {
            if ($operation['method'] !== $method) {
                continue;
            }

            $pattern = preg_quote($operation['path'], '#');
            $pattern = preg_replace('/\\\{[^}]+\\\}/', '[^/]+', $pattern);

            if (is_string($pattern) && preg_match('#^'.$pattern.'$#D', $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
