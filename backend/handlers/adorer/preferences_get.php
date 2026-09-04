<?php
/**
 * GET /api/adorer/preferences
 *
 * Current notification toggles for the signed-in adorer.
 */

return function (): void {
    $user = Auth::require(Token::ROLE_ADORER);

    Response::success([
        'preferences' => Preferences::forUser((int) $user['id']),
    ]);
};
