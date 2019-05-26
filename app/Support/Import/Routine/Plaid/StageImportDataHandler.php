<?php
/**
 * StageImportDataHandler.phpr.php
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

namespace FireflyIII\Support\Import\Routine\Plaid;


use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Models\Account as LocalAccount;
use FireflyIII\Models\AccountType;
use FireflyIII\Models\ImportJob;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\ImportJob\ImportJobRepositoryInterface;
use FireflyIII\Services\Plaid\Object\Account as PlaidAccount;
use FireflyIII\Services\Plaid\Object\Transaction as PlaidTransaction;
use FireflyIII\Services\Plaid\Object\Transaction;
use FireflyIII\Services\Plaid\Request\ListTransactionsRequest;
use FireflyIII\Support\Import\Routine\File\OpposingAccountMapper;
use FireflyIII\Import\JobConfiguration\PlaidJobConfiguration;
use Log;
use Carbon\Carbon;

/**
 * Class StageImportDataHandler
 *
 */
class StageImportDataHandler
{
    /** @var AccountRepositoryInterface */
    private $accountRepository;
    /** @var ImportJob */
    private $importJob;
    /** @var OpposingAccountMapper */
    private $mapper;
    /** @var ImportJobRepositoryInterface */
    private $repository;

    /**
     * @throws FireflyException
     */
    public function run(): void
    {
        Log::debug('Now in StageImportDataHandler::run()');
        $config   = $this->importJob->configuration;
        $accounts = $config['accounts'] ?? [];
        Log::debug(sprintf('Count of accounts in array is %d', \count($accounts)));
        if (0 === \count($accounts)) {
            throw new FireflyException('There are no accounts in this import job. Cannot continue.'); // @codeCoverageIgnore
        }
        $toImport = $config['account_mapping'] ?? [];
	    Log::debug( '$toImport: ' );
	    Log::debug( $toImport );
	    Log::debug( '' );
        $totalSet = [[]];
        foreach ($toImport as $plaidId => $localId) {
            if ((int)$localId > 0) {
                Log::debug(sprintf('Will get transactions from Plaid account #%s and save them in Firefly III account #%d', $plaidId, $localId));
                $plaidAccount = $this->getPlaidAccount((string)$plaidId);
                $localAccount   = $this->getLocalAccount((int)$localId);
                $merge          = $this->getTransactions($plaidAccount, $localAccount);
                $totalSet[]     = $merge;
                Log::debug(
                    sprintf('Found %d transactions in account "%s" (%s)', \count($merge), $plaidAccount->getName(), $plaidAccount->getCurrencyCode())
                );
                continue;
            }
            Log::debug(sprintf('Local account is = zero, will not import from Plaid account with ID #%d', $plaidId));
        }
        $totalSet = array_merge(...$totalSet);
        Log::debug(sprintf('Found %d transactions in total.', \count($totalSet)));
        Log::debug($totalSet);
        $this->repository->setTransactions($this->importJob, $totalSet);
        // Save last time synced so we don't have to import every transaction each time
	    $user = auth()->user();
	    foreach ( $toImport as $plaidId => $localId ) {
	        app( 'preferences' )->setForUser( $user, 'plaid_last_date_synced_' . $localId, Carbon::now()->toIso8601String() );
	    }
    }


    /**
     * @param ImportJob $importJob
     *
     * @return void
     */
    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob         = $importJob;
        $this->repository        = app(ImportJobRepositoryInterface::class);
        $this->accountRepository = app(AccountRepositoryInterface::class);
        $this->mapper            = app(OpposingAccountMapper::class);
        $this->accountRepository->setUser($importJob->user);
        $this->repository->setUser($importJob->user);
        $this->mapper->setUser($importJob->user);
    }

    /**
     * @param array          $transactions
     * @param PlaidAccount   $plaidAccount
     * @param LocalAccount   $originalSource
     *
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function convertToArray(array $transactions, PlaidAccount $plaidAccount, LocalAccount $originalSource): array
    {
        $array = [];
        $total = \count($transactions);
        Log::debug(sprintf('Now in StageImportDataHandler::convertToArray() with count %d', \count($transactions)));
        /** @var PlaidTransaction $transaction */
        foreach ($transactions as $index => $transaction) {
            Log::debug(sprintf('Now creating array for transaction %d of %d', $index + 1, $total));
	        Log::debug( 'Transaction: ');
	        Log::debug(serialize($transaction));
            $extra = [];
            $destinationData     = $transaction->getOpposingAccountData();
            $amount              = $transaction->getAmount();
            $source              = $originalSource;
            $destination         = $this->mapper->map(null, $amount, $destinationData);
            $notes               = trans('import.imported_from_account', ['account' => $plaidAccount->getName()]) . '  ' . "\n";
            $foreignAmount       = null;
            $foreignCurrencyCode = null;

            $currencyCode = $transaction->getCurrencyCode();
            $type         = 'withdrawal';
            // switch source and destination if amount is greater than zero.
            if (1 === bccomp( '0', $amount )) {
                [$source, $destination] = [$destination, $source];
                $type = 'deposit';
            }

            Log::debug(sprintf('Mapped destination to #%d ("%s")', $destination->id, $destination->name));
            Log::debug(sprintf('Set source to #%d ("%s")', $source->id, $source->name));

            // put some data in tags:
            $tags   = $transaction->getCategories();


            $entry   = [
                'type'            => $type,
                'date'            => $transaction->getDate()->format('Y-m-d'),
                'tags'            => $tags,
                'user'            => $this->importJob->user_id,
                'notes'           => $notes,

                // all custom fields:
                'external_id'     => (string)$transaction->getTransactionId(),

                // journal data:
                'description'     => $transaction->getName(),
                'piggy_bank_id'   => null,
                'piggy_bank_name' => null,
                'bill_id'         => null,
                'bill_name'       => null,
                'original-source' => sprintf('plaid-v%s', config('firefly.version')),

                // transaction data:
                'transactions'    => [
                    [
                        'currency_id'           => null,
                        'currency_code'         => $currencyCode,
                        'description'           => null,
                        'amount'                => $amount,
                        'budget_id'             => null,
                        'budget_name'           => null,
                        'category_id'           => null,
                        'source_id'             => $source->id,
                        'source_name'           => null,
                        'destination_id'        => $destination->id,
                        'destination_name'      => null,
                        'foreign_currency_id'   => null,
                        'foreign_currency_code' => $foreignCurrencyCode,
                        'foreign_amount'        => $foreignAmount,
                        'reconciled'            => false,
                        'identifier'            => 0,
                    ],
                ],
            ];
            $array[] = $entry;
        }
        Log::debug(sprintf('Return %d entries', \count($array)));

        return $array;
    }

    /**
     * @param int $accountId
     *
     * @return LocalAccount
     * @throws FireflyException
     */
    private function getLocalAccount(int $accountId): LocalAccount
    {
        $account = $this->accountRepository->findNull($accountId);
        if (null === $account) {
            throw new FireflyException(sprintf('Cannot find Firefly III asset account with ID #%d. Job must stop now.', $accountId)); // @codeCoverageIgnore
        }
        if (!\in_array($account->accountType->type, [AccountType::ASSET, AccountType::LOAN, AccountType::MORTGAGE, AccountType::DEBT], true)) {
            throw new FireflyException(
                sprintf('Account with ID #%d is not an asset/loan/mortgage/debt account. Job must stop now.', $accountId)
            ); // @codeCoverageIgnore
        }

        return $account;
    }

    /**
     * @param int $accountId
     *
     * @return PlaidAccount
     * @throws FireflyException
     */
    private function getPlaidAccount(string $accountId): PlaidAccount
    {
        $config   = $this->importJob->configuration;
        $accounts = $config['accounts'] ?? [];
        foreach ($accounts as $account) {
            $plaidId = (string)($account['account_id'] ?? '');
            if ($plaidId === $accountId) {
	            Log::debug( 'Creating new Plaid account with this data' );
	            Log::debug( $account );
                return new PlaidAccount($account);
            }
        }
        throw new FireflyException(sprintf('Cannot find Plaid account with ID %s in configuration. Job will exit.', $accountId)); // @codeCoverageIgnore
    }

    /**
     * @param PlaidAccount $plaidAccount
     * @param LocalAccount   $localAccount
     *
     * @return array
     * @throws FireflyException
     */
    private function getTransactions(PlaidAccount $plaidAccount, LocalAccount $localAccount): array
    {
        // grab all transactions
        /** @var ListTransactionsRequest $request */
        Log::debug('In getTransactions()');
	    Log::debug( '$plaidAccount:' . serialize($plaidAccount) );

	    $user = auth()->user();
	    $accessToken = PlaidJobConfiguration::getPlaidAccessTokenByIndex( $user, $plaidAccount->getAccessTokenKey() );
	    $client = PlaidJobConfiguration::getPlaidClient( $user );

	    // Check last synced date
	    // If no last synced date, start from two years ago
	    // Go up until now in pages of 250

	    $lastSyncedDatePreference = app( 'preferences' )->getForUser( $user, 'plaid_last_date_synced_' . $localAccount->id, '2017-01-01' );

	    Log::debug('Last synced date string: ' . $lastSyncedDatePreference->data);

	    $lastSyncedDate = new Carbon($lastSyncedDatePreference->data);

	    $now = Carbon::now();

	    $count = 250;

	    $offset = 0;

	    $finalTransactions = [];

	    do {
	        $response = $client->transactions()->get($accessToken, $lastSyncedDate->format('Y-m-d'), $now->format('Y-m-d'), [], [], $count, $offset);
	        Log::debug('Count of transactions: ' . count( $response['transactions'] ));
	        $finalTransactions = array_merge($finalTransactions, $response['transactions']);
	        $offset += $count;
	    } while ( count( $response['transactions'] ) == $count );

	    $transactions = [];

	    foreach ( $finalTransactions as $transactionData) {
	    	$transactionData['local_account_name'] = $localAccount->name;
	    	$transaction = new Transaction($transactionData);
		    $transactions[] = $transaction;
	    }

	    Log::debug('Final response:');
	    Log::debug(serialize($finalTransactions));

	    Log::debug('Transaction count: ' . count($finalTransactions));

//	    return [];

        return $this->convertToArray($transactions, $plaidAccount, $localAccount);
    }


}
