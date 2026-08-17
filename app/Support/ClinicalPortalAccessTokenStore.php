<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

/**
 * Clinical credentials share the Business deployment but never share its
 * browser handle, cache entry, client secret, or OAuth scope set.
 */
class ClinicalPortalAccessTokenStore extends PortalAccessTokenStore
{
    public function __construct(Session $session)
    {
        parent::__construct($session, 'clinical');
    }
}
