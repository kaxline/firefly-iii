<?php
/**
 * PlaidJobConfiguration.php
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

namespace FireflyIII\Import\JobConfiguration;

use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Models\ImportJob;
use FireflyIII\Repositories\ImportJob\ImportJobRepositoryInterface;
use FireflyIII\Support\Import\JobConfiguration\Plaid\AuthenticatedHandler;
use FireflyIII\Support\Import\JobConfiguration\Plaid\ChooseAccountsHandler;
use FireflyIII\Support\Import\JobConfiguration\Plaid\ChooseLoginHandler;
use FireflyIII\Support\Import\JobConfiguration\Plaid\DoAuthenticateHandler;
use FireflyIII\Support\Import\JobConfiguration\Plaid\NewPlaidJobHandler;
use FireflyIII\Support\Import\JobConfiguration\Plaid\PlaidJobConfigurationInterface;
use Illuminate\Support\MessageBag;
use Plaid\Client;
use Log;

/**
 * Class PlaidJobConfiguration
 */
class PlaidJobConfiguration implements JobConfigurationInterface
{
    /** @var PlaidJobConfigurationInterface The job handler. */
    private $handler;
    /** @var ImportJob The import job */
    private $importJob;
    /** @var ImportJobRepositoryInterface Import job repository */
    private $repository;

    /**
     * Returns true when the initial configuration for this job is complete.
     *
     * @return bool
     */
    public function configurationComplete(): bool
    {
        return $this->handler->configurationComplete();
    }

    /**
     * Store any data from the $data array into the job. Anything in the message bag will be flashed
     * as an error to the user, regardless of its content.
     *
     * @param array $data
     *
     * @return MessageBag
     */
    public function configureJob(array $data): MessageBag
    {
        return $this->handler->configureJob($data);
    }

    /**
     * Return the data required for the next step in the job configuration.
     *
     * @return array
     */
    public function getNextData(): array
    {
        return $this->handler->getNextData();
    }

    /**
     * Returns the view of the next step in the job configuration.
     *
     * @return string
     */
    public function getNextView(): string
    {
        return $this->handler->getNextView();
    }

    /**
     * Set the import job.
     *
     * @param ImportJob $importJob
     *
     * @throws FireflyException
     */
    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob  = $importJob;
        $this->repository = app(ImportJobRepositoryInterface::class);
        $this->repository->setUser($importJob->user);
        $this->handler = $this->getHandler();
    }

    /**
     * Get correct handler.
     *
     * @return PlaidJobConfigurationInterface
     * @throws FireflyException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function getHandler(): PlaidJobConfigurationInterface
    {
        Log::debug(sprintf('Now in PlaidJobConfiguration::getHandler() with stage "%s"', $this->importJob->stage));
        $handler = null;
        switch ($this->importJob->stage) {
            case 'new':
                /** @var NewPlaidJobHandler $handler */
                $handler = app(NewPlaidJobHandler::class);
                $handler->setImportJob($this->importJob);
                break;
            case 'do-authenticate':
                /** @var DoAuthenticateHandler $handler */
                $handler = app(DoAuthenticateHandler::class);
                $handler->setImportJob($this->importJob);
                break;
            case 'choose-login':
                /** @var ChooseLoginHandler $handler */
                $handler = app(ChooseLoginHandler::class);
                $handler->setImportJob($this->importJob);
                break;
            case 'authenticated':
                /** @var AuthenticatedHandler $handler */
                $handler = app(AuthenticatedHandler::class);
                $handler->setImportJob($this->importJob);
                break;
            case 'choose-accounts':
                /** @var ChooseAccountsHandler $handler */
                $handler = app(ChooseAccountsHandler::class);
                $handler->setImportJob($this->importJob);
                break;
            default:
                // @codeCoverageIgnoreStart
                throw new FireflyException(sprintf('Firefly III cannot create a configuration handler for stage "%s"', $this->importJob->stage));
            // @codeCoverageIgnoreEnd
        }

        return $handler;
    }

	/**
	 * Check preferences in DB for existing Plaid access_tokens and retrieve them
	 *
	 * @param User $user
	 *
	 * @return array
	 */
	static function getSavedPlaidAccessTokens( $user ) {
		$count        = 0;
		$accessTokens = array();
		$tokenExists  = false;

		// Count up through integers to find all the existing access tokens
		do {
			$preferenceKey   = 'plaid_access_token_' . $count;
			$tokenPreference = app( 'preferences' )->getForUser( $user, $preferenceKey, null );
			$token           = null === $tokenPreference ? null : $tokenPreference->data;
			$tokenExists     = ! is_null( $token );
			if ( $tokenExists ) {
				$accessTokens[] = $token;
			}
			$count ++;
		} while ( $tokenExists );

		return $accessTokens;
	}

	/**
	 * Check preferences in DB for existing Plaid access_tokens and retrieve them
	 *
	 * @param User $user
	 * @param string $key
	 *
	 * @return array
	 */
	static function getPlaidAccessTokenByIndex( $user, $key ) {
		$tokenPreference = app( 'preferences' )->getForUser( $user, $key, null );

		$token       = null === $tokenPreference ? null : $tokenPreference->data;
		$tokenExists = ! is_null( $token );
		if ( $tokenExists ) {
			return $token;
		}

		return null;
	}

	/**
	 * Check preferences in DB for existing Plaid access_tokens and retrieve them
	 *
	 * @param User $user
	 *
	 * @return Client
	 */
	static function getPlaidClient( $user ) {
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
}
