# payment-integration implementation

Kapital bank payment-gateway implementation with Laravel.


In the controller, you can call and send your request in this way

      $payment = new KapitalBankPayment(request()->post('xmlmsg'));
      
      
