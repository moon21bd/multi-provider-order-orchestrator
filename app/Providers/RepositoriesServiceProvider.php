<?php

namespace App\Providers;

use App\Models\AdminConfig;
use App\Models\BalanceInfoModel;
use App\Models\CountryModel;
use App\Models\DatapackDetailsModel;
use App\Models\ProviderModel;
use App\Models\ProviderUtilityProviderMapModel;
use App\Models\MobileLocationModel;
use App\Models\Operator;
use App\Models\OrderRequestModel;
use App\Models\SimBoxLogModel;
use App\Models\SmsReceiveModel;
use App\Models\TopupProductDetails;
use App\Models\TopupRequestModel;
use App\Models\TransactionModel;
use App\Models\User;
use App\Models\UtilityPackageModel;
use App\Models\UtilityPaymentTransactionModel;
use App\Models\UtilityProviderModel;
use App\Models\WorkerAppStatusModel;
use App\Repositories\AdminConfigRepositoryImpl;
use App\Repositories\BalanceInfoRepositoryImpl;
use App\Repositories\Contracts\AdminConfigRepository;
use App\Repositories\Contracts\BalanceInfoRepository;
use App\Repositories\Contracts\CountryRepository;
use App\Repositories\Contracts\DatapackDetailsRepository;
use App\Repositories\Contracts\ProviderRepository;
use App\Repositories\Contracts\ProviderUtilityProviderMapRepository;
use App\Repositories\Contracts\MobileLocationRepository;
use App\Repositories\Contracts\OperatorRepository;
use App\Repositories\Contracts\OrderRequestRepository;
use App\Repositories\Contracts\SimBoxLogRepository;
use App\Repositories\Contracts\SmsReceiveRepository;
use App\Repositories\Contracts\TopupProductDetailsRepository;
use App\Repositories\Contracts\TopupRequestRepository;
use App\Repositories\Contracts\TransactionRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Contracts\UtilityPackageRepository;
use App\Repositories\Contracts\UtilityPaymentTransactionRepository;
use App\Repositories\Contracts\UtilityProviderRepository;
use App\Repositories\Contracts\WorkerAppStatusRepository;
use App\Repositories\CountryRepositoryImpl;
use App\Repositories\DatapackDetailsRepositoryImpl;
use App\Repositories\EloquentUserRepository;
use App\Repositories\ProviderRepositoryImpl;
use App\Repositories\ProviderUtilityProviderMapRepositoryImpl;
use App\Repositories\MobileLocationRepositoryImpl;
use App\Repositories\OperatorRepositoryImpl;
use App\Repositories\OrderRequestRepositoryImpl;
use App\Repositories\SimBoxLogRepositoryImpl;
use App\Repositories\SmsReceiveRepositoryImpl;
use App\Repositories\TopupProductDetailsRepositoryImpl;
use App\Repositories\TopupRequestRepositoryImpl;
use App\Repositories\TransactionRepositoryImpl;
use App\Repositories\UtilityPackageRepositoryImpl;
use App\Repositories\UtilityPaymentTransactionRepositoryImpl;
use App\Repositories\UtilityProviderRepositoryImpl;
use App\Repositories\WorkerAppStatusRepositoryImpl;
use Illuminate\Support\ServiceProvider;

class RepositoriesServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(UserRepository::class, function () {
            return new EloquentUserRepository(new User());
        });
        $this->app->bind(OrderRequestRepository::class, function () {
            return new OrderRequestRepositoryImpl(new OrderRequestModel());
        });

        $this->app->bind(ProviderRepository::class, function () {
            return new ProviderRepositoryImpl(new ProviderModel());
        });
        $this->app->bind(OperatorRepository::class, function () {
            return new OperatorRepositoryImpl(new Operator());
        });
        $this->app->bind(TransactionRepository::class, function () {
            return new TransactionRepositoryImpl(new TransactionModel());
        });

        $this->app->bind(CountryRepository::class, function () {
            return new CountryRepositoryImpl(new CountryModel());
        });

        $this->app->bind(TopupProductDetailsRepository::class, function () {
            return new TopupProductDetailsRepositoryImpl(new TopupProductDetails());
        });

        $this->app->bind(TopupRequestRepository::class, function () {
            return new TopupRequestRepositoryImpl(new TopupRequestModel());
        });

        $this->app->bind(DatapackDetailsRepository::class, function () {
            return new DatapackDetailsRepositoryImpl(new DatapackDetailsModel());
        });

        $this->app->bind(SmsReceiveRepository::class, function () {
            return new SmsReceiveRepositoryImpl(new SmsReceiveModel());
        });
        $this->app->bind(BalanceInfoRepository::class, function () {
            return new BalanceInfoRepositoryImpl(new BalanceInfoModel());
        });
        $this->app->bind(SimBoxLogRepository::class, function () {
            return new SimBoxLogRepositoryImpl(new SimBoxLogModel());
        });

        $this->app->bind(AdminConfigRepository::class, function () {
            return new AdminConfigRepositoryImpl(new AdminConfig());
        });

        $this->app->bind(UtilityProviderRepository::class, function () {
            return new UtilityProviderRepositoryImpl(new UtilityProviderModel());
        });

        $this->app->bind(UtilityPaymentTransactionRepository::class, function () {
            return new UtilityPaymentTransactionRepositoryImpl(new UtilityPaymentTransactionModel());
        });

        $this->app->bind(ProviderUtilityProviderMapRepository::class, function () {
            return new ProviderUtilityProviderMapRepositoryImpl(new ProviderUtilityProviderMapModel());
        });

        $this->app->bind(UtilityPackageRepository::class, function () {
            return new UtilityPackageRepositoryImpl(new UtilityPackageModel());
        });
        $this->app->bind(WorkerAppStatusRepository::class, function () {
            return new WorkerAppStatusRepositoryImpl(new WorkerAppStatusModel());
        });
        $this->app->bind(MobileLocationRepository::class, function () {
            return new MobileLocationRepositoryImpl(new MobileLocationModel());
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            UserRepository::class,
            OrderRequestRepository::class,
            ProviderRepository::class,
            OperatorRepository::class,
            TransactionRepository::class,
            CountryRepository::class,
            TopupProductDetailsRepository::class,
            TopupRequestRepository::class,
            UtilityProviderRepositoryImpl::class,
            AdminConfigRepositoryImpl::class,
            SimBoxLogRepositoryImpl::class,
            BalanceInfoRepositoryImpl::class,
            SmsReceiveRepositoryImpl::class,
            DatapackDetailsRepositoryImpl::class,
            UtilityPaymentTransactionRepository::class,
            ProviderUtilityProviderMapRepository::class,
            UtilityPackageRepository::class,
            WorkerAppStatusRepository::class,
            MobileLocationRepository::class,
        ];
    }
}