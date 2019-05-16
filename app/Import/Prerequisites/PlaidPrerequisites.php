<?php
/**
 * SpectrePrerequisites.php
 * Copyright (c) 2017 thegrumpydictator@gmail.com
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

namespace FireflyIII\Import\Prerequisites;

use FireflyIII\Models\Preference;
use FireflyIII\User;
use Illuminate\Support\MessageBag;
use Log;

/**
 * This class contains all the routines necessary to connect to Spectre.
 */
class PlaidPrerequisites implements PrerequisitesInterface
{
    /** @var User The current user */
    private $user;

    /**
     * Returns view name that allows user to fill in prerequisites.
     *
     * @return string
     */
    public function getView(): string
    {
        return 'import.plaid.prerequisites';
    }

    /**
     * Returns any values required for the prerequisites-view.
     *
     * @return array
     */
    public function getViewParameters(): array
    {
        /** @var Preference $appIdPreference */
        $appIdPreference = app('preferences')->getForUser($this->user, 'plaid_app_id', null);
        $appId           = null === $appIdPreference ? '' : $appIdPreference->data;
        /** @var Preference $secretPreference */
        $secretPreference = app('preferences')->getForUser($this->user, 'plaid_secret', null);
        $secret           = null === $secretPreference ? '' : $secretPreference->data;
	    /** @var Preference $secretPreference */
	    $publicKeyPreference = app( 'preferences' )->getForUser( $this->user, 'plaid_public_key', null );
	    $publicKey           = null === $publicKeyPreference ? '' : $publicKeyPreference->data;

        return [
            'app_id'     => $appId,
            'secret'     => $secret,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Indicate if all prerequisites have been met.
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->hasAppId() && $this->hasSecret() && $this->hasPublicKey();
    }

    /**
     * Set the user for this Prerequisites-routine. Class is expected to implement and save this.
     *
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * This method responds to the user's submission of an API key. Should do nothing but store the value.
     *
     * Errors must be returned in the message bag under the field name they are requested by.
     *
     * @param array $data
     *
     * @return MessageBag
     */
    public function storePrerequisites(array $data): MessageBag
    {
        Log::debug('Storing Plaid API keys..');
        app('preferences')->setForUser($this->user, 'plaid_app_id', $data['app_id'] ?? null);
        app('preferences')->setForUser($this->user, 'plaid_secret', $data['secret'] ?? null);
        app('preferences')->setForUser($this->user, 'plaid_public_key', $data['public_key'] ?? null);
        Log::debug('Done!');

        return new MessageBag;
    }

    /**
     * Check if we have the App ID.
     *
     * @return bool
     */
    private function hasAppId(): bool
    {
        $appId = app('preferences')->getForUser($this->user, 'plaid_app_id', null);
        if (null === $appId) {
            return false;
        }
        if ('' === (string)$appId->data) {
            return false;
        }

        return true;
    }

    /**
     * Check if we have the secret.
     *
     * @return bool
     */
    private function hasSecret(): bool
    {
        $secret = app('preferences')->getForUser($this->user, 'plaid_secret', null);
        if (null === $secret) {
            return false;
        }
        if ('' === (string)$secret->data) {
            return false;
        }

        return true;
    }

	/**
	 * Check if we have the public key.
	 *
	 * @return bool
	 */
	private function hasPublicKey(): bool {
		$publicKey = app( 'preferences' )->getForUser( $this->user, 'plaid_public_key', null );
		if ( null === $publicKey ) {
			return false;
		}
		if ( '' === (string) $publicKey->data ) {
			return false;
		}

		return true;
	}
}
