<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\Transaction;
use App\PaymentGateways\PaymentGatewayInterface;
use App\PaymentGateways\Vendors\KapitalBankPayment;
use Illuminate\Support\Facades\Mail;
use Exception;

class PaymentController extends Controller
{
    public function status(string $status, string $token)
    {
        $payment = new KapitalBankPayment(request()->post('xmlmsg'));

        $transaction = $this->transaction($payment);


        if(md5(sha1($transaction?->order?->user?->email)) === $token) {
            auth()->login($transaction->order->user);
        }

        switch ($status) {

            case PaymentStatus::PAID:

                $transaction?->setPaymentStatus($status);

                return $this->paid($payment, $transaction);

            case PaymentStatus::CANCELLED:

                $transaction?->setPaymentStatus($status);

                return $this->cancelled();


            case PaymentStatus::DECLINED:

                $transaction?->setPaymentStatus($status);

                return $this->declined();

            default:

                return $this->declined();
        }

    }
    /**
     * Execute transaction
     * @param PaymentGatewayInterface $payment
     * @return null|Transaction
     */
    public function transaction(PaymentGatewayInterface $payment)
    {
        $paymentData = $payment->getPaymentDetails();

        if(isset($paymentData['Message']['OrderID'])) {

            $transaction = Transaction::where('transaction_id', $paymentData['Message']['OrderID'])->first();

            $transaction->update([
                'card'  =>  empty($paymentData['Message']['PAN']) ? null : $paymentData['Message']['PAN'],
                'data'  =>  $paymentData
            ]);

            return $transaction;
        }

        return null;
    }


    protected function paid(PaymentGatewayInterface $payment, ?Transaction $transaction)
    {
        if($payment->isSuccess()) {
            if(! $transaction->order->is_paid) {

                $transaction->order()->update([
                    'is_paid'   =>  true,
                    'status'    =>  OrderStatus::ORDER_PLACED
                ]);

                foreach ($transaction->order->products as $product) {
                    $product->decrement('count', $product->pivot->cart_count);
                }
                
                
                


                try {
                                    Mail::send(new OrderPlacedMail($transaction->order));

                } catch (Exception $e) {
                
                }








                // Mail::send(new OrderPlacedMail($transaction->order));

            }


            return view('pages.payment.success', [
                'order' =>  $transaction->order,
            ]);
        }

        return view('pages.payment.success');
    }

    protected function cancelled()
    {
        return view('pages.payment.error');
    }

    protected function declined()
    {
        return view('pages.payment.error');
    }

    protected function error()
    {
        return view('pages.payment.error');
    }

    public function cash()
    {
        return view('pages.payment.cash');
    }
}
