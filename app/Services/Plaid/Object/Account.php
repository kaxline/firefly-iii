<?php
/**
 * Account.php
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

namespace FireflyIII\Services\Plaid\Object;

use Carbon\Carbon;

/**
 * Class Account
 *
 * @codeCoverageIgnore
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class Account extends PlaidObject
{
    /** @var float */
    private $balance;
    /** @var string */
    private $currencyCode;
    /** @var string */
    private $accountId;
    /** @var string */
    private $name;
    /** @var string */
    private $mask;
	/** @var string */
	private $type;
	/** @var string */
	private $subtype;
	/** @var string */
	private $accessTokenKey;

    /**
     * Account constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->accountId        = $data['account_id'];
        $this->currencyCode     = $data['currency_code'];
        $this->balance          = $data['balance'];
        $this->name             = $data['name'];
        $this->mask             = $data['mask'];
        $this->type             = $data['type'];
        $this->subtype          = $data['subtype'];
        $this->accessTokenKey   = $data['access_token_key'];
    }

    /**
     * @return float
     */
    public function getBalance(): float
    {
        return $this->balance;
    }

	/**
	 * @return string
	 */
	public function getAccountId(): string {
		return $this->accountId;
	}

    /**
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode ?? '';
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

	/**
	 * @return string
	 */
	public function getSubtype(): string {
		return $this->subtype;
	}

	/**
	 * @return string
	 */
	public function getMask(): string {
		return $this->mask;
	}

	/**
	 * @return string
	 */
	public function getAccessTokenKey(): string {
		return $this->accessTokenKey;
	}

    /**
     * @return array
     */
    public function toArray(): array
    {
        $array = [
            'account_id'        => $this->accountId,
            'balance'           => $this->balance,
            'currency_code'     => $this->currencyCode,
            'name'              => $this->name,
            'mask'              => $this->mask,
            'type'              => $this->type,
            'subtype'           => $this->subtype,
            'accessTokenKey'    => $this->accessTokenKey,
        ];

        return $array;
    }

}
