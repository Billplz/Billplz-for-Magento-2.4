<?php

namespace Billplz\BillplzPaymentGateway\Controller\Checkout;

use Billplz\BillplzPaymentGateway\Model\BillplzConnect;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\Order;

/**
 * @package Billplz\BillplzPaymentGateway\Controller\Checkout
 */
class Callback extends AbstractAction implements CsrfAwareActionInterface
{
    public function execute()
    {
        try {
            $params = BillplzConnect::getXSignature($this->getGatewayConfig()->getXSignature());
            $this->getLogger()->debug('X Signature validation passed.');
        } catch (\Exception $e) {
            $this->getLogger()->debug('Failed X Signature Validation. Possibly due to invalid X Signature Key');
            exit;
        }

        $order = $this->getOrderByBillplzBillId('billplz_bill_id', $params['id']);

        if (!$order) {
            $this->getLogger()->debug("Billplz Bill id could not be retrieved: {$params['id']}");
        }

        if ($params['paid']) {
            if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
                $this->_createInvoice($order, $params['id']);
            }

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

        $order->save();
    }

    public function createCsrfValidationException(RequestInterface $request):  ? InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request) :  ? bool
    {
        return true;
    }
}
