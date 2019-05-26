<?php
/**
 * CallbackController.php
 * Copyright (c) 2018 thegrumpydictator@gmail.com
 *
 * This file is part of Firefly III.
 *
 * Firefly III is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Firefly III is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Firefly III. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FireflyIII\Http\Controllers\Import;


use FireflyIII\Http\Controllers\Controller;
use FireflyIII\Repositories\ImportJob\ImportJobRepositoryInterface;
use FireflyIII\Services\Plaid\Object\Account;
use Illuminate\Http\Request;
use Plaid\Client;
use Log;

/**
 * Class CallbackController
 */
class CallbackController extends Controller
{

    /**
     * Callback specifically for YNAB logins.
     *
     * @param Request                      $request
     *
     * @param ImportJobRepositoryInterface $repository
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function ynab(Request $request, ImportJobRepositoryInterface $repository)
    {
        $code   = (string)$request->get('code');
        $jobKey = (string)$request->get('state');

        if ('' === $code) {
            return view('error')->with('message', 'You Need A Budget did not reply with a valid authorization code. Firefly III cannot continue.');
        }

        $importJob = $repository->findByKey($jobKey);

        if ('' === $jobKey || null === $importJob) {
            return view('error')->with('message', 'You Need A Budget did not reply with the correct state identifier. Firefly III cannot continue.');
        }
        Log::debug(sprintf('Got a code from YNAB: %s', $code));

        // we have a code. Make the job ready for the next step, and then redirect the user.
        $configuration              = $repository->getConfiguration($importJob);
        $configuration['auth_code'] = $code;
        $repository->setConfiguration($importJob, $configuration);

        // set stage to make the import routine take the correct action:
        $repository->setStatus($importJob, 'ready_to_run');
        $repository->setStage($importJob, 'get_access_token');

        return redirect(route('import.job.status.index', [$importJob->key]));
    }

	/**
	 * Check preferences in DB for existing Plaid access_tokens and retrieve them
	 *
	 * @param User $user
	 *
	 * @return array
	 */
    private function getSavedPlaidAccessTokens($user) {
	    $count = 0;
	    $accessTokens = array();
	    $tokenExists = false;

	    // Count up through integers to find all the existing access tokens
	    do {
		    $preferenceKey   = 'plaid_access_token_' . $count;
		    $tokenPreference = app( 'preferences' )->getForUser( $user, $preferenceKey, null );
		    $token = null === $tokenPreference ? null : $tokenPreference->data;
		    $tokenExists   = ! is_null( $token );
		    if ( $tokenExists ) {
			    $accessTokens[$preferenceKey] = $token;
		    }
		    $count++;
	    } while ( $tokenExists );

	    return $accessTokens;
    }

	/**
	 * Check preferences in DB for existing Plaid access_tokens and retrieve them
	 *
	 * @param User $user
	 *
	 * @return Client
	 */
	private function getPlaidClient($user) {
		/** @var Preference $appIdPreference */
		$appIdPreference = app( 'preferences' )->getForUser( $user, 'plaid_app_id', null );
		$appId           = null === $appIdPreference ? '' : $appIdPreference->data;
		/** @var Preference $secretPreference */
		$secretPreference = app( 'preferences' )->getForUser( $user, 'plaid_secret', null );
		$secret           = null === $secretPreference ? '' : $secretPreference->data;
		/** @var Preference $secretPreference */
		$publicKeyPreference = app( 'preferences' )->getForUser( $user, 'plaid_public_key', null );
		$publicKey           = null === $publicKeyPreference ? '' : $publicKeyPreference->data;

		return new Client( $appId, $secret, $publicKey, 'development' );
	}

	/**
	 * Get the public_token from the request, turn it into an access_token, and save it.
	 *
	 * @param Request $request
	 *
	 * @param ImportJobRepositoryInterface $repository
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
	 */
	public function createPlaidAccessToken( Request $request, ImportJobRepositoryInterface $repository ) {
		if ( !auth()->check() ) {
		  return response('Not authorized', 403);
		}

		$publicToken = (string) $request->get( 'public_token' );
		$user = auth()->user();
		$accessTokens = $this->getSavedPlaidAccessTokens($user);

		$plaidClient = $this->getPlaidClient($user);

		$response = $plaidClient->item()->publicToken()->exchange($publicToken);
		$accessToken = $response['access_token'];

		$preferenceKey = 'plaid_access_token_' . count($accessTokens);

		app('preferences')->setForUser($user, $preferenceKey, $accessToken);

		return response()->json( [
			'success' => true,
		] );
	}

	/**
	 * Get all accounts available to user through saved access tokens.
	 *
	 * @param Request $request
	 *
	 * @param ImportJobRepositoryInterface $repository
	 *
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
	 */
	public function getPlaidAccounts( Request $request, ImportJobRepositoryInterface $repository ) {
		$user         = auth()->user();
		$accessTokens = $this->getSavedPlaidAccessTokens( $user );
		$plaidClient = $this->getPlaidClient($user);

		$allInstitutions = array();

		$importJobKey = $request->query('importJob');

		$importJob = $repository->findByKey($importJobKey);

		Log::debug(sprintf( 'importJob: %s', $importJob));

		$config = array(
			'accounts' => array()
		);

		foreach ($accessTokens as $key=>$token) {
			Log::debug( 'Access token key: ' . $key );

			$response = $plaidClient->accounts()->get($token);

			$institution = $plaidClient->institutions()->getById($response['item']['institution_id'])['institution'];

			$institution['accounts'] = $response['accounts'];

			$allInstitutions[$key] = $institution;
		}

		foreach ($allInstitutions as $key=>$institution) {
			foreach ($institution['accounts'] as $account) {
				$plaidAccount = [
					'account_id'       => $account['account_id'],
					'name'             => $account['name'],
					'balance'          => $account['balances']['current'],
					'mask'             => $account['mask'],
					'subtype'          => $account['subtype'],
					'type'             => $account['type'],
					'currency_code'    => $account['balances']['unofficial_currency_code'],
					'access_token_key' => $key,
				];

				$config['accounts'][] = $plaidAccount;
			}
		}

		$repository->setConfiguration($importJob, $config);

		return response()->json( $allInstitutions );
	}

}

