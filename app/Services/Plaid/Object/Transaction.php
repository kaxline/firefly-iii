<?php
/**
 * Transaction.php
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
 * Class Transaction
 *
 * @codeCoverageIgnore
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class Transaction extends PlaidObject
{
    /** @var string */
    private $accountId;
	/** @var string */
	private $transactionId;
	/** @var string */
	private $transactionType;
	/** @var string */
	private $categoryId;
    /** @var double */
    private $amount;
    /** @var array */
    private $categories;
    /** @var Carbon */
    private $createdAt;
    /** @var string */
    private $currencyCode;
    /** @var string */
    private $name;
	/** @var string */
	private $localAccountName;
	/** @var bool */
	private $pending;
    /** @var int */
    private $id;
    /** @var Carbon */
    private $date;
    /** @var Carbon */
    private $updatedAt;

    /**
     * Transaction constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->transactionId    = $data['transaction_id'];
        $this->transactionType  = $data['transaction_type'];
        $this->date             = new Carbon($data['date']);
        $this->amount           = $data['amount'];
        $this->currencyCode     = $data['iso_currency_code'];
        $this->name             = $data['name'];
        $this->localAccountName = $data['local_account_name'];
        $this->categories       = $data['category'];
        $this->pending          = $data['pending'];
        $this->accountId        = $data['account_id'];
        $this->createdAt        = new Carbon();
        $this->updatedAt        = new Carbon();
    }

    /**
     * @return string
     */
    public function getAmount(): string
    {
        return (string)$this->amount;
    }

    /**
     * @return array
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
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
	public function getLocalAccountName(): string {
		return $this->localAccountName;
	}

    /**
     * @return string
     */
    public function getHash(): string
    {
        $array = [
            'transaction_id' => $this->transactionId,
            'name'           => $this->name,
            'category_id'    => $this->categoryId,
            'date'           => $this->date->toIso8601String(),
            'amount'         => $this->amount,
            'currency_code'  => $this->currencyCode,
            'category'       => $this->categories,
            'pending'        => $this->pending,
            'account_id'     => $this->accountId,
            'created_at'     => $this->createdAt->toIso8601String(),
            'updated_at'     => $this->updatedAt->toIso8601String(),
        ];

        return hash('sha256', json_encode($array));
    }

    /**
     * @return string
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        return $this->date;
    }

    /**
     * Get opposing account data.
     *
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getOpposingAccountData(): array
    {
        $data  = [
            'name'   => $this->localAccountName,
            'iban'   => null,
            'number' => null,
            'bic'    => null,
        ];

        return $data;
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->pending;
    }


}
