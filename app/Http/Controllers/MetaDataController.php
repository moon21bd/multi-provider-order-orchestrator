<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 12/27/2017
 * Time: 4:57 PM
 */

namespace App\Http\Controllers;


use App\Repositories\Contracts\AdminConfigRepository;
use App\Repositories\Contracts\BalanceInfoRepository;
use App\Repositories\Contracts\CountryRepository;
use App\Repositories\Contracts\DatapackDetailsRepository;
use App\Repositories\Contracts\OperatorRepository;
use App\Repositories\Contracts\SimBoxLogRepository;
use App\Repositories\Contracts\SmsReceiveRepository;
use App\Repositories\Contracts\TransactionRepository;
use App\Repositories\Contracts\UserRepository;
use App\Repositories\Contracts\WorkerAppStatusRepository;
use App\Transformers\CountryTransformer;
use App\Transformers\OperatorTransformer;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Pheanstalk\Pheanstalk;
use SoapClient;

class MetaDataController extends Controller
{
    /**
     * Instance of UserRepository
     *
     * @var UserRepository
     */
    private $userRepository;

    private $operatorRepository;

    private $operatorTransformer;
    private $countryRepository;
    private $countryTransformer;
    private $datapackDetailsRepository;
    private $smsReceiveRepository;
    private $transactionRepository;
    private $balanceInfoRepository;
    private $simBoxLogRepository;
    private $adminConfigRepository;
    private $workerAppStatusRepository;

    /**
     * Constructor
     *
     * @param UserRepository $userRepository
     * @param OperatorRepository $operatorRepository
     * @param OperatorTransformer $operatorTransformer
     * @param CountryRepository $countryRepository
     * @param CountryTransformer $countryTransformer
     * @param DatapackDetailsRepository $datapackDetailsRepository
     * @param SmsReceiveRepository $smsReceiveRepository
     * @param TransactionRepository $transactionRepository
     * @param BalanceInfoRepository $balanceInfoRepository
     * @param SimBoxLogRepository $simBoxLogRepository
     * @param AdminConfigRepository $adminConfigRepository
     * @param WorkerAppStatusRepository $workerAppStatusRepository
     */
    public function __construct(UserRepository $userRepository, OperatorRepository $operatorRepository, OperatorTransformer $operatorTransformer, CountryRepository $countryRepository, CountryTransformer $countryTransformer, DatapackDetailsRepository $datapackDetailsRepository, SmsReceiveRepository $smsReceiveRepository, TransactionRepository $transactionRepository, BalanceInfoRepository $balanceInfoRepository, SimBoxLogRepository $simBoxLogRepository, AdminConfigRepository $adminConfigRepository,
                                WorkerAppStatusRepository $workerAppStatusRepository)
    {
        $this->userRepository = $userRepository;
        $this->operatorRepository = $operatorRepository;
        $this->countryRepository = $countryRepository;
        $this->countryTransformer = $countryTransformer;
        $this->operatorTransformer = $operatorTransformer;
        $this->datapackDetailsRepository = $datapackDetailsRepository;
        $this->smsReceiveRepository = $smsReceiveRepository;
        $this->transactionRepository = $transactionRepository;
        $this->balanceInfoRepository = $balanceInfoRepository;
        $this->simBoxLogRepository = $simBoxLogRepository;
        $this->adminConfigRepository = $adminConfigRepository;
        $this->workerAppStatusRepository = $workerAppStatusRepository;

        parent::__construct();
    }

    public function getOperators($country)
    {
        $data = $this->operatorRepository->getAllOperatorByCountry($country);
        return $this->format(Response::HTTP_OK, [], $data);
    }

    public function getAllCountry()
    {
        $data = $this->countryRepository->getAllCountry();

        return $this->format(Response::HTTP_OK, "", $data);
    }

    public function getAllDataPackInfo()
    {
        $items = $this->datapackDetailsRepository->getAllDatapackInfo();
        $data = [];
        foreach ($items as $item) {
            $dataItem['name'] = $item->name;
            $dataItem['connection_type'] = $item->connection_type > 0 ? "postpaid" : "prepaid";
            $dataItem['volume'] = $item->volume . " " . $item->volume_unit;
            $dataItem['validity'] = $item->validity . " " . $item->validity_unit;
            $dataItem['code'] = $item->code;
            $dataItem['price'] = $item->price;
            array_push($data, $dataItem);
        }
        return $this->formatRes(Response::HTTP_OK, "", $data);
    }

    public function getDataPackInfosByOperatorCode($operatorCode)
    {
        $operator = $this->operatorRepository->findOneBy(["code" => $operatorCode]);
        $items = $this->datapackDetailsRepository->getAllDatapackInfoByOperatorId($operator->id);
        $data = array();
        foreach ($items as $item) {
            $dataItem['name'] = $item->name;
            $dataItem['connection_type'] = $item->connection_type > 0 ? "postpaid" : "prepaid";
            $dataItem['volume'] = $item->volume . " " . $item->volume_unit;
            $dataItem['validity'] = $item->validity . " " . $item->validity_unit;
            $dataItem['code'] = $item->code;
            $dataItem['price'] = $item->price;
            $dataItem['operator_code'] = $operatorCode;
            array_push($data, $dataItem);
        }


        return $this->formatRes(Response::HTTP_OK, "", $data);
    }


    public function saveSmsFromClientApp(Request $request)
    {
        $item['text'] = $request->input('sms');
        $item['sender'] = $request->input('sender');
        $item['receive_time'] = $request->input('time');
        $item['operator'] = $request->input('operator');
        $item['sim_no'] = $request->input('sim') ?? '';
        $item['status'] = 0;
        $data = $this->smsReceiveRepository->save($item);

        $transactions['type'] = 5;
        $transactions['ref_id'] = 5;
        $transactions['status_route'] = 5;
        $transactions['request_payload'] = 5;
        $transactions['route'] = config('settings.host_url') . "process/sms/" . $data->id;
        try {
            $pheanstalk = new Pheanstalk(config('settings.beanstalkd_host'), config('settings.beanstalkd_port'));
            $pheanstalk
                ->useTube('request')
                ->put(json_encode($transactions), 0, Pheanstalk::DEFAULT_DELAY, 600);
        } catch (\Exception $ex) {
            return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, 'CODE 1002: Unable to process request. Please try again later' . config('settings.beanstalkd_host'));
        }
        return $this->format(Response::HTTP_OK, 'SMS saved successfully');
    }


    public function updateBalanceInfo()
    {
        $item = $this->transactionRepository->getSuccessfulTransactionByOperatorProvider('GP', 'ALPHA');
        $gpB = json_decode($item->response_data)->msg;
        $pieces = explode("Your account balance is TK ", $gpB);
        $gp = $this->balanceInfoRepository->findOneBy(['code' => 'GP@ALPHA']);
        $this->balanceInfoRepository->update($gp, ["balance" => rtrim($pieces[1], ".")]);

        $item = $this->transactionRepository->getSuccessfulTransactionByOperatorProvider('OPERATOR_C', 'ALPHA');
        $balanceMsg = json_decode($item->response_data)->msg;
        $pieces = explode("Your new balance is ", $balanceMsg);
        $pieces1 = explode(" ", $pieces[1]);
        $operatorC = $this->balanceInfoRepository->findOneBy(['code' => 'OPERATOR_C@ALPHA']);
        $this->balanceInfoRepository->update($operatorC, ["balance" => $pieces1[0]]);


        $this->getCharlieBalance();
        $this->getSierraBalance();
        $mins = intval(date("i"));
        if ($mins > 4 && $mins < 7) {
            $this->getNovemberBalance();
        }

        /*$transactions['type'] = 4;
        $transactions['route'] = config('settings.host_url')."update/balance/info";
        try {
            $pheanstalk = new Pheanstalk(config('settings.beanstalkd_host'),config('settings.beanstalkd_port'));
            $pheanstalk
                ->useTube('request')
                ->put(json_encode($transactions), Pheanstalk::DEFAULT_PRIORITY, 120);
        } catch (\Exception $ex) {
            return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, 'CODE 1002: Unable to process request. Please try again later'.config('settings.beanstalkd_host'));
        }*/

        return $this->format(Response::HTTP_OK, 'Operation  successfully');
    }

    private function getCharlieBalance()
    {
        $wsdl = 'http://kadriah.ddns.net/thirdparty/connect.asmx?WSDL';
        $soap = new SoapClient($wsdl);
        $response = $soap->CheckBalance([
            'sClientUserName' => '<MSISDN_B>',
            'sClientPassword' => '90472364',
            'dBalance' => '0'
        ]);
        $responseId = intval($response->sResponseID);
        $data = null;
        if ($responseId > 0) {
            $item = $this->balanceInfoRepository->findOneBy(['code' => 'CHARLIE']);
            $this->balanceInfoRepository->update($item, ["balance" => $response->dBalance]);
        }
    }

    private function getSierraBalance()
    {
        $api_password = "replace-me";
        $payload = array("command" => 1,
            "account_id" => <MSISDN_B>,
            "api_password" => $api_password);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.sierra.my/user/api/v3/request",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 3600,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/json"
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {

        } else {
            $responseData = json_decode($response)->Response[0];
            $data = null;
            if ($responseData->responce_status == 0) {
                $item = $this->balanceInfoRepository->findOneBy(['code' => 'SIERRA']);
                $this->balanceInfoRepository->update($item, ["balance" => $responseData->balance]);
            }
        }
    }

    private function getNovemberBalance()
    {
        $t2Login = "platform";
        $t2Token = "replace-me";
        $t = microtime(true);
        $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
        $d = new DateTime(date('Y-m-d H:i:s.' . $micro, $t));
        $key = $d->format("YmdHisu");
        $time = $d->format("i");

        $md5 = md5($t2Login . $t2Token . $key);
        $postData = '<xml><login>' . $t2Login . '</login><key>' . $key . '</key><md5>' . $md5 . '</md5><action>check_wallet</action></xml>';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.november.provider.example/topup",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 3600,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: text/xml"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($err) {

        } else {
            if ($http_code == 200) {
                $res = simplexml_load_string($response);
                if ((string)$res->error_code == "0") {
                    $item = $this->balanceInfoRepository->findOneBy(['code' => 'NOVEMBER']);
                    $this->balanceInfoRepository->update($item, ["balance" => $res->wallet]);
                }
            }
        }
    }

    public function saveSimBoxLogClientApp(Request $request)
    {
        $item['mobile_no'] = $request->input('mobile_no');
        $item['ref_id'] = $request->input('ref_id');
        $item['amount'] = $request->input('amount');
        $item['message'] = $request->input('message');
        $item['operator'] = $request->input('operator');
        $item['sim_no'] = $request->input('sim') ?? '';
        $item['status'] = 0;
        $data = $this->simBoxLogRepository->save($item);

        $transactions['type'] = 5;
        $transactions['ref_id'] = 5;
        $transactions['status_route'] = 5;
        $transactions['request_payload'] = 5;
        $transactions['route'] = config('settings.host_url') . "process/log/" . $data->id;
        $isProcessLogTaskValue = $this->adminConfigRepository->findOneBy(["name" => "PROCESS_LOG_VIA_API"]);

        if (!empty((array)$isProcessLogTaskValue) && (int)$isProcessLogTaskValue->value > 0) {
            try {
                $pheanstalk = new Pheanstalk(config('settings.beanstalkd_host'), config('settings.beanstalkd_port'));
                $pheanstalk
                    ->useTube('request')
                    ->put(json_encode($transactions), 0, Pheanstalk::DEFAULT_DELAY, 600);
            } catch (\Exception $ex) {
                return $this->formatError(Response::HTTP_INTERNAL_SERVER_ERROR, 'CODE 1002: Unable to process request. Please try again later' . config('settings.beanstalkd_host'));
            }
        }
        return $this->format(Response::HTTP_OK, 'Log saved successfully');
    }

    public function processingTxnRequery(Request $request)
    {
        $startId = trim($request['start_id']);
        $endId = trim($request['end_id']);
        $res = array();

        for ($i = $startId; $i <= $endId; $i++) {
            $transaction = $this->transactionRepository->findOneBy(["id" => $i]);
            if (is_null($transaction)) {
                continue;
            } else if ($transaction['status'] < 2) {
                $url = $transaction['status_route'];
                $params['transaction_id'] = $transaction['transaction_id'];
                $params['ref_id'] = $transaction['ref_id'];
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 3600,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($params),
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json"
                    ),
                    CURLOPT_SSL_VERIFYPEER => 0
                ));

                $response = curl_exec($curl);
                $err = curl_error($curl);

                curl_close($curl);
                Log::debug("requery response -" . $response);
                $transaction = $this->transactionRepository->findOneBy(["id" => $i]);
                if ($transaction['status'] > 1) {
                    if ($transaction['trans_type'] == 1) {
                        $payload['transaction_id'] = $transaction['ref_id'];
                        $payload['mobile_no'] = $transaction['mobile_no'];
                        $payload['operator_code'] = $transaction['operator_code'];
                        $payload['status'] = $transaction['status'];
                    } else {
                        $payload['transaction_id'] = $transaction['ref_id'];
                        $payload['mobile_no'] = $transaction['mobile_no'];
                        $payload['operator_code'] = $transaction['operator_code'];
                        $payload['amount'] = $transaction['amount'];
                        $payload['connection_type'] = $transaction['connection_type'];
                        $payload['status'] = $transaction['status'];
                    }

                    $data = json_encode($payload);
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $transaction['client_callback_url'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 3600,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_POSTFIELDS => $data,
                        CURLOPT_HTTPHEADER => array(
                            "Content-Type: application/json"
                        ),
                        CURLOPT_SSL_VERIFYPEER => 0
                    ));

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);
                    $item["ref_id"] = $transaction['ref_id'];
                    $item["mobile_no"] = $transaction['mobile_no'];
                    $item["amount"] = $transaction['amount'];
                    $item['operator_code'] = $transaction['operator_code'];
                    $item["refund_status"] = "Refund Successful";
                    if ($err) {
                        //return $this->formatError(Response::HTTP_NOT_FOUND, "Error");
                        Log::error("requery callback err -" . $err);
                        $item["refund_status"] = "Refund Failed";
                    } else {
                        Log::debug("requery callback response -" . $err);
                        $this->transactionRepository->update($transaction, ["client_callback_code" => "200"]);
                    }

                    array_push($res, $item);
                }
            }
        }

        return $this->format(Response::HTTP_OK, "", $res);
    }

    public function saveWorkerStatus($id)
    {
        $item['worker_id'] = $id;
        $item['last_check_in'] = date('Y-m-d H:i:s');


        $transaction = $this->workerAppStatusRepository->findOneBy(["worker_id" => $id]);
        if (is_null($transaction)) {
            $data = $this->workerAppStatusRepository->save($item);
        } else {
            $transaction->last_check_in = $item['last_check_in'];
            $transaction->save();
        }
        return $this->format(Response::HTTP_OK, 'status saved successfully');
    }


}