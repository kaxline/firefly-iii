<?php
/**
 * ChooseAccount.php
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

namespace FireflyIII\Support\Import\JobConfiguration\Plaid;


use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Models\Account as AccountModel;
use FireflyIII\Models\AccountType;
use FireflyIII\Models\ImportJob;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Repositories\ImportJob\ImportJobRepositoryInterface;
use FireflyIII\Services\Plaid\Object\Account as PlaidAccount;
use FireflyIII\Services\Plaid\Object\Login;
use Illuminate\Support\MessageBag;
use Log;

/**
 * Class ChooseAccountsHandler
 *
 */
class ChooseAccountsHandler implements PlaidJobConfigurationInterface
{

    /** @var AccountRepositoryInterface */
    private $accountRepository;
    /** @var CurrencyRepositoryInterface */
    private $currencyRepository;
    /** @var ImportJob */
    private $importJob;
    /** @var ImportJobRepositoryInterface */
    private $repository;

    /**
     * Return true when this stage is complete.
     *
     * @return bool
     */
    public function configurationComplete(): bool
    {
        Log::debug('Now in ChooseAccountsHandler::configurationComplete()');
        $config         = $this->importJob->configuration;
        $importAccounts = $config['account_mapping'] ?? [];
        $complete       = \count($importAccounts) > 0 && $importAccounts !== [0 => 0];
        if ($complete) {
            Log::debug('Looks like user has mapped import accounts to Firefly III accounts', $importAccounts);
            $this->repository->setStage($this->importJob, 'go-for-import');
        }

        return $complete;
    }

    /**
     * Store the job configuration.
     *
     * @param array $data
     *
     * @return MessageBag
     */
    public function configureJob(array $data): MessageBag
    {
        Log::debug('Now in ChooseAccountsHandler::configureJob()', $data);
        $config     = $this->importJob->configuration;
        $mapping    = $data['account_mapping'] ?? [];
        $final      = [];
        $applyRules = 1 === (int)($data['apply_rules'] ?? 0);
        foreach ($mapping as $plaidId => $localId) {
            $accountId         = $this->validLocalAccount((int)$localId);
            $final[$plaidId] = $accountId;

        }
        $messages                  = new MessageBag;
        $config['account_mapping'] = $final;
        $config['apply-rules']     = $applyRules;
        $this->repository->setConfiguration($this->importJob, $config);
        if ($final === [0 => 0] || 0 === \count($final)) {
            $messages->add('count', (string)trans('import.plaid_no_mapping'));
        }

        return $messages;
    }

    /**
     * Get data for config view.
     *
     * @return array
     * @throws FireflyException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getNextData(): array
    {
        Log::debug('Now in ChooseAccountsHandler::getnextData()');

	    // list the users accounts:
	    $accounts = $this->accountRepository->getAccountsByType( [
		    AccountType::ASSET,
		    AccountType::DEBT,
		    AccountType::LOAN,
		    AccountType::MORTGAGE
	    ] );

	    $array = [];
	    /** @var AccountModel $account */
	    foreach ( $accounts as $account ) {
		    $accountId           = $account->id;
		    $currencyId          = (int) $this->accountRepository->getMetaValue( $account, 'currency_id' );
		    $currency            = $this->getCurrency( $currencyId );
		    $array[ $accountId ] = [
			    'name' => $account->name,
			    'iban' => $account->iban,
			    'code' => $currency->code,
		    ];
	    }

	    Log::debug( '=================================' );
	    Log::debug( 'Returning accounts' );
//	    Log::debug( sprintf('array length: %d', print_r($accounts)));
	    Log::debug( '=================================' );

	    return [
		    'ff_accounts' => $array,
	    ];
    }

    /**
     * @codeCoverageIgnore
     * Get the view for this stage.
     *
     * @return string
     */
    public function getNextView(): string
    {
        return 'import.plaid.accounts';
    }

    /**
     * @codeCoverageIgnore
     * Set the import job.
     *
     * @param ImportJob $importJob
     */
    public function setImportJob(ImportJob $importJob): void
    {
        $this->importJob          = $importJob;
        $this->repository         = app(ImportJobRepositoryInterface::class);
        $this->accountRepository  = app(AccountRepositoryInterface::class);
        $this->currencyRepository = app(CurrencyRepositoryInterface::class);
        $this->repository->setUser($importJob->user);
        $this->currencyRepository->setUser($importJob->user);
        $this->accountRepository->setUser($importJob->user);
    }

    /**
     * @param int $currencyId
     *
     * @return TransactionCurrency
     */
    private function getCurrency(int $currencyId): TransactionCurrency
    {
        $currency = $this->currencyRepository->findNull($currencyId);
        if (null === $currency) {
            return app('amount')->getDefaultCurrencyByUser($this->importJob->user);
        }

        return $currency;

    }

    /**
     * @param int $accountId
     *
     * @return int
     */
    private function validLocalAccount(int $accountId): int
    {
        $account = $this->accountRepository->findNull($accountId);
        if (null === $account) {
            return 0;
        }

        return $accountId;
    }

    /**
     * @param int $accountId
     *
     * @return int
     */
    private function validPlaidAccount(string $accountId): string
    {
        $config   = $this->importJob->configuration;
        $accounts = $config['accounts'] ?? [];
	    Log::debug( '=================================' );
//	    Log::debug( 'accountId: %s', $accountId);
//	    Log::debug( sprintf('array length: %d', print_r($config)));
	    Log::debug( '=================================' );
        foreach ($accounts as $account) {
            if ((string)$account['id'] === $accountId) {
                return $accountId;
            }
        }

        return '';
    }
}
