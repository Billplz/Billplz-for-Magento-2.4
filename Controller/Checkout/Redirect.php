<?php

namespace Billplz\BillplzPaymentGateway\Controller\Checkout;

use Billplz\BillplzPaymentGateway\Model\BillplzConnect;
use Magento\Sales\Model\Order;

/**
 * @package Billplz\BillplzPaymentGateway\Controller\Checkout
 */
class Redirect extends AbstractAction
{
    public function execute()
    {
        try {
            $params = BillplzConnect::getXSignature($this->getGatewayConfig()->getXSignature());
            $this->getLogger()->debug('X Signature validation passed.');
        } catch (\Exception $e) {
            $this->getLogger()->debug('Failed X Signature Validation. Possibly due to invalid X Signature Key');
            exit('Failed X Signature Validation');
        }

        $order = $this->getOrderByBillplzBillId('billplz_bill_id', $params['id']);

        if (!$order) {
            $this->getLogger()->debug("Billplz Bill id could not be retrieved: {$params['id']}");
            $this->_redirect('checkout/onepage/error', array('_secure' => false));
            return;
        }

        if ($params['paid']) {
            if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
                $this->_createInvoice($order, $params['id']);
            }
            $this->getMessageManager()->addSuccessMessage(__("Your payment with Billplz is complete"));

            $this->_redirect('checkout/onepage/success', array('_secure' => false));
            return;
        } else {
            $this->getCheckoutHelper()->cancelCurrentOrder("Order #" . ($order->getId()) . " was rejected by Billplz. Bill {$params['id']}.");
            $this->getCheckoutHelper()->restoreQuote(); //restore cart
            $this->getMessageManager()->addErrorMessage(__("There was an error in the Billplz payment"));
            $this->_redirect('checkout/onepage/failure');
        }

    }

    private function _createInvoice(Order $order, $bill_id)
    {
        if (!$order->canInvoice()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Cannot create an invoice.')
            );
        }

        $invoice = $this->getObjectManager()
            ->create('Magento\Sales\Model\Service\InvoiceService')
            ->prepareInvoice($order);

        if (!$invoice->getTotalQty()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t create an invoice without products.')
            );
        }

        /*
         * Look Magento/Sales/Model/Order/Invoice.register() for CAPTURE_OFFLINE explanation.
         * Basically, if !config/can_capture and config/is_gateway and CAPTURE_OFFLINE and
         * Payment.IsTransactionPending => pay (Invoice.STATE = STATE_PAID...)
         */
        $invoice->setTransactionId($bill_id);
        $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();

        $transaction = $this->getObjectManager()->create('Magento\Framework\DB\Transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transaction->save();

        $order->setState(Order::STATE_PROCESSING);
        $order->addStatusToHistory($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING), "Billplz bill payment success. Bill $bill_id", true);
        $order->setIsNotified(true);
        $order->save();
    }
}
