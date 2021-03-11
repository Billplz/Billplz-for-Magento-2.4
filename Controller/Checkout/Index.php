<?php

namespace Billplz\BillplzPaymentGateway\Controller\Checkout;

use Billplz\BillplzPaymentGateway\Model\BillplzAPI;
use Billplz\BillplzPaymentGateway\Model\BillplzConnect;
use Magento\Sales\Model\Order;

/**
 * @package Billplz\BillplzPaymentGateway\Controller\Checkout
 */
class Index extends AbstractAction
{

    private function createBill($order)
    {
        if ($order == null) {
            $this->getLogger()->debug('Unable to get order from last lodged order id. Possibly related to a failed database call');
            $this->_redirect('checkout/onepage/error', array('_secure' => false));
        }

        $orderId = $order->getRealOrderId();
        $gatewayConf = $this->getGatewayConfig();
        $billingAddress = $order->getBillingAddress();

        $parameter = array(
            'collection_id' => trim($gatewayConf->getCollectionId()),
            'email' => $order->getData('customer_email'),
            'mobile' => $billingAddress->getData('telephone'),
            'name' => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
            'amount' => $order->getTotalDue() * 100,
            'callback_url' => $this->getUrlHelper()->getCallbackUrl(),
            'description' => "Order $orderId",
        );
        $optional = array(
            'redirect_url' => $this->getUrlHelper()->getRedirectUrl(),
        );
        
        if (empty($parameter['mobile']) && empty($parameter['email'])) {
            $parameter['email'] = 'noreply@billplz.com';
        }

        if (empty($parameter['name'])) {
            $parameter['name'] =  'Payer Name Unavailable';
        }

        $connect = new BillplzConnect(trim($gatewayConf->getApiKey()));
        $connect->detectMode();

        $billplz = new BillplzAPI($connect);
        $payload = $billplz->toArray($billplz->createBill($parameter, $optional));

        $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $order->addCommentToStatusHistory("Collection ID: {$parameter['collection_id']}; Bill: {$payload[1]['id']}; Status: Pending Payment; Bill URL: {$payload[1]['url']}", true, true);
        $order->setData('billplz_bill_id', $payload[1]['id']);
        $order->save();

        return $payload;

        // $order->getOrderCurrencyCode() // String: MYR ??
    }

    private function redirectToBill($shouldRedirect, $bill)
    {
        if ($shouldRedirect) {
            $this->renderRedirect($bill['url']);
        } else {
            $this->getLogger()->debug('Bill creation failed: ' . print_r($payload, true));
            $this->_redirect('checkout/cart');
        }
    }

    private function renderRedirect($bill_url)
    {
        echo
            "<html>
            <body>
            <a href=\"$bill_url\">Redirecting to Bill</a>
            </body>
            <script>
                window.location.replace(\"$bill_url\");
            </script>
            </html>";
    }

    /**
     *
     *
     * @return void
     */
    public function execute()
    {
        try {
            $order = $this->getOrder();
            if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
                list($rheader, $bill) = $this->createBill($order);
                $this->redirectToBill($rheader === 200, $bill);
            } else if ($order->getState() === Order::STATE_CANCELED) {
                $errorMessage = $this->getCheckoutSession()->getBillplzErrorMessage(); //set in InitializationRequest
                if ($errorMessage) {
                    $this->getMessageManager()->addWarningMessage($errorMessage);
                    $errorMessage = $this->getCheckoutSession()->unsBillplzErrorMessage();
                }
                $this->getCheckoutHelper()->restoreQuote(); //restore cart
                $this->_redirect('checkout/cart');
            } else {
                $this->getLogger()->debug('Order in unrecognized state: ' . $order->getState());
                $this->_redirect('checkout/cart');
            }
        } catch (Exception $ex) {
            $this->getLogger()->debug('An exception was encountered in billplz/checkout/index: ' . $ex->getMessage());
            $this->getLogger()->debug($ex->getTraceAsString());
            $this->getMessageManager()->addErrorMessage(__('Unable to start Billplz Checkout.'));
        }
    }

}
