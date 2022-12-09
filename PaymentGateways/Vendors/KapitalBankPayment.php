<?php

namespace App\PaymentGateways\Vendors;

use App\Enums\PaymentStatus;
use App\Models\Transaction;
use App\PaymentGateways\PaymentGatewayInterface;
use Exception;
use SimpleXMLElement;

class KapitalBankPayment implements PaymentGatewayInterface {

    /**
     *  Payment for Kapital bank
     */
    const MERCHANT  = 'E2080011';

    const SSL_CERT  = 'prod.crt';

    const SSL_KEY   = 'prod.key';

    /**
     * @var int
     */
    public $price = 0;

    /**
     * @var int
     */
    public $currency = 944;

    /**
     * @var string
     */
    public $language = 'AZ';

    /**
     * @var string
     */
    public $description = 'This is sample payment description';


    public $monthCount = 0;

    /**
     * @var
     */
    private $orderID;

    /**
     * @var
     */
    private $sessionID;

    /**
     * @var
     */
    private $orderURL;

    /**
     * @var
     */
    private $status;

    /**
     * @var array
     */
    protected $currencies = [
        'AZN'   =>  944
    ];

    public $xmlResponse;

    public $response;

    /**
     * @var array
     */
    protected $backUrls;


    public int $transactionId;

    /**
     * @var array
     */
    protected $errorMessages = [
        "30"    =>  "message invalid format (no mandatory fields etc.)",
        "10"    =>  "the Internet shop has no access to the 'Create Order' operation (or the Internet shop is not registered)",
        "54"    =>  "invalid operation",
        "96"    =>  "system error"
    ];

    /**
     * @var string
     */
    private $processUrl = 'https://3dsrv.kapitalbank.az:5443/Exec';

    public function __construct($xmlResponse = null)
    {
        $this->xmlResponse = $xmlResponse;

        $this->response = json_decode(
            json_encode(
                simplexml_load_string($xmlResponse)
            )
            , true);

        $userEmail = md5(sha1(auth()?->user()?->email));

        $this->backUrls = [
            'ApproveURL'    =>  route('payment.status', [PaymentStatus::PAID, $userEmail]),
            'CancelURL'     =>  route('payment.status', [PaymentStatus::CANCELLED, $userEmail]),
            'DeclineURL'    =>  route('payment.status', [PaymentStatus::DECLINED, $userEmail]),
        ];
    }

    /**
     * @param int $price
     * @return self
     */
    public function setPrice(float $price)
    {
        $this->price = $price * 100;

        return $this;
    }

    /**
     * @return int
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param string $language
     * @return self
     */
    public function setLanguage(string $language)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @param array $urls
     * @return self
     */
    public function setBackUrls(array $urls)
    {
        foreach ($urls as $reason => $url) {
            $this->backUrls[$reason] = $url;
        }

        return $this;
    }

    /**
     * @param string $description
     * @return self
     */
    public function setDescription(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param string $currencyCode
     * @return self
     */
    public function setCurrency(string $currencyCode)
    {
        $this->currency = $this->currencies[$currencyCode];

        return $this;
    }

    private function setOrderId(int $orderId)
    {
        $this->orderID = $orderId;
    }

    private function setSessionID($sessionID)
    {
        $this->sessionID = $sessionID;
    }

    public function setMonth(int $monthCount)
    {
        $this->monthCount = $monthCount;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrderID()
    {
        return $this->orderID;
    }

    /**
     * @return mixed
     */
    public function getMonth()
    {
        return $this->monthCount;
    }
    /**
     * @return mixed
     */
    public function getSessionID()
    {
        return $this->sessionID;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * @param float $amount
     * @param string $description
     * @return mixed
     */
    private function createOrderXML(): string
    {
        $xml = new \SimpleXMLElement('<TKKPG/>');

//        $xml->addAttribute('version', "1.0");
//        $xml->addAttribute('encoding', "UTF-8");

        $request = $xml->addChild('Request');
        $request->addChild('Operation', 'CreateOrder');
        $request->addChild('Language', $this->language);

        $order = $request->addChild('Order');
        $order->addChild('OrderType', 'Purchase');
        $order->addChild('Merchant', self::MERCHANT);
        $order->addChild('Amount', $this->price);
        $order->addChild('Currency', $this->currency);

        $order->addChild('Description',
            $this->getMonth() ? "TAKSIT=" . $this->getMonth() : $this->description
        );

        foreach ($this->backUrls as $reason => $url) {
            $order->addChild($reason, $this->backUrls[$reason]);
        }

        return $xml->saveXML();
    }

    /**
     * @return bool|string
     */
    private function responseXmlFromBank($xml = null)
    {
        $curl = curl_init($this->processUrl);

        $header = array("Content-Type: text/html; charset=utf-8");

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_TIMEOUT         =>  10,
            CURLOPT_POST            =>  true,
            CURLOPT_POSTFIELDS      =>  $xml ?: self::createOrderXML(),
            CURLOPT_SSL_VERIFYHOST  =>  false,
            CURLOPT_SSL_VERIFYPEER  =>  false,
            CURLOPT_HTTPHEADER      =>  $header,
            CURLOPT_SSLCERT         =>  storage_path('payment/' . self::SSL_CERT),
            CURLOPT_SSLKEY          =>  storage_path('payment/' . self::SSL_KEY)
        ]);

        return curl_exec($curl);
    }

    /**
     * @throws Exception
     */
    public function execute()
    {
        $xml = simplexml_load_string($this->responseXmlFromBank());

        if(isset($xml->Response)) {

            $response           = $xml->Response;

            $this->status       = (string) $response->Status;

            $this->orderID      = (string) $response->Order->OrderID;

            $this->sessionID    = (string) $response->Order->SessionID;

            $this->orderURL     = (string) $response->Order->URL;

        }
        else {

            throw new  Exception(
                'Wrong response from bank'
            );
        }

        return $this;
    }


    /**
     * @throws Exception
     */
    public function getRedirectUrlToPaymentPage(): string
    {
        if($this->getStatus() === "00") {

            return $this->orderURL . '?' . http_build_query([
                    'OrderID'   =>  (string) $this->getOrderID(),
                    'SessionID' =>  (string) $this->getSessionID()
                ]);
        }
        elseif(isset($this->errorMessages[$this->getStatus()])) {
            throw new Exception($this->errorMessages[$this->getStatus()]);
        }
        else {
            throw new Exception("First run self::execute method");
        }
    }

    public function isSuccess()
    {
        if(
            ! empty($this->response) &&
            count($this->response) &&
            isset($this->response['Message']['OrderID'])
        ) {
            
            $this->setOrderId($this->response['Message']['OrderID']);
            $this->setSessionID($this->response['Message']['SessionID']);

            $orderStatusXml = $this->getOrderStatus();

            $orderStatus = json_decode(
                json_encode(
                    simplexml_load_string($orderStatusXml)
                )
                , true);

            return count($orderStatus) &&
                isset($orderStatus['Response']['Order']) &&
                strtolower($orderStatus['Response']['Order']['OrderStatus']) === "approved";
        }

        return false;
    }

    public function getOrderStatus()
    {
        $xml = new SimpleXMLElement('<TKKPG/>');

        $xml->addAttribute('version', "1.0");
        $xml->addAttribute('encoding', "UTF-8");

        $request = $xml->addChild('Request');
        $request->addChild('Operation', 'GetOrderStatus');
        $request->addChild('Language', $this->language);

        $order = $request->addChild('Order');
        $order->addChild('Merchant', self::MERCHANT);
        $order->addChild('OrderID', $this->getOrderID());

        $request->addChild('SessionID', $this->getSessionID());

        return $this->responseXmlFromBank(
            $xml->saveXML()
        );
    }

    public function getPaymentDetails()
    {
        return $this->response;
    }

    public function isRefundable(): bool
    {
        return true;
    }

    public function setTransactionId(int $transactionId)
    {
        $this->transactionId = $transactionId;
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function refundTransaction(int $transactionId)
    {
        if($transaction = Transaction::find($transactionId)) {

            $xml = new SimpleXMLElement('<TKKPG/>');

            $xml->addAttribute('version', "1.0");
            $xml->addAttribute('encoding', "UTF-8");

                $request = $xml->addChild('Request');
                $request->addChild('Operation', 'Refund');
                $request->addChild('Language', $this->language);

                    $order = $request->addChild('Order');
                        $order->addChild('Merchant', self::MERCHANT);
                        $order->addChild('OrderID', $transaction->data['Message']['OrderID']);

                    $positions  = $order->addChild('Positions');
                        $position   = $positions->addChild('Position');
                            $position->addChild('PaymentSubjectType', 1);
                            $position->addChild('Quantity', 1);
                            $position->addChild('Price', 1);
                            $position->addChild('Tax', 1);
                            $position->addChild('Text', 'name position');
                            $position->addChild('Text', 'name position');
                            $position->addChild('PaymentType', 2);
                            $position->addChild('PaymentMethodType', 1);

                $request->addChild('Description', 'refund test');
                $request->addChild('SessionID', $transaction->data['Message']['SessionID']);

                $refund = $request->addChild('Refund');
                    $refund->addChild('Amount', $transaction->amount*100);
                    $refund->addChild('Currency', $this->currency);
                    $refund->addChild('WithFee', 'false');

                $request->addChild('Source', 1);

            $refundResponse = $this->responseXmlFromBank(
                $xml->saveXML()
            );

            $refundResponse = json_decode(
                json_encode(
                    simplexml_load_string($refundResponse)
                ));
            return $refundResponse?->Response?->Status === "00";
        }

        return false;
    }



}
























