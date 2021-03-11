<?php

namespace Billplz\BillplzPaymentGateway\Controller\Checkout;

use Billplz\BillplzPaymentGateway\Gateway\Config\Config;
use Billplz\BillplzPaymentGateway\Helper\Checkout;
use Billplz\BillplzPaymentGateway\Helper\UrlCallbackRedirect;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

/**
 * @package Billplz\BillplzPaymentGateway\Controller\Checkout
 */
abstract class AbstractAction extends Action
{

    const LOG_FILE = 'billplz.log';

    private $_context;

    private $_checkoutSession;

    private $_orderFactory;

    private $_checkoutHelper;

    private $_gatewayConfig;

    private $_messageManager;

    private $_logger;

    public function __construct(
        Config $gatewayConfig,
        Session $checkoutSession,
        Context $context,
        OrderFactory $orderFactory,
        UrlCallbackRedirect $urlHelper,
        Checkout $checkoutHelper,
        LoggerInterface $logger) {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_checkoutHelper = $checkoutHelper;
        $this->_gatewayConfig = $gatewayConfig;
        $this->_messageManager = $context->getMessageManager();
        $this->_logger = $logger;
        $this->_urlHelper = $urlHelper;
    }

    protected function getContext()
    {
        return $this->_context;
    }

    protected function getUrlHelper()
    {
        return $this->_urlHelper;
    }

    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    protected function getOrderFactory()
    {
        return $this->_orderFactory;
    }

    protected function getCheckoutHelper()
    {
        return $this->_checkoutHelper;
    }

    protected function getGatewayConfig()
    {
        return $this->_gatewayConfig;
    }

    protected function getMessageManager()
    {
        return $this->_messageManager;
    }

    protected function getLogger()
    {
        return $this->_logger;
    }

    protected function getOrder()
    {
        $orderId = $this->_checkoutSession->getLastRealOrderId();

        if (!isset($orderId)) {
            return null;
        }

        return $this->getOrderById($orderId);
    }

    protected function getOrderById($orderId)
    {
        $order = $this->_orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            return null;
        }

        return $order;
    }

    protected function getOrderByBillplzBillId($attribute, $value)
    {
        $order = $this->_orderFactory->create()->loadByAttribute($attribute, $value);

        if (!$order->getId()) {
            return null;
        }

        return $order;
    }

    protected function getObjectManager()
    {
        return \Magento\Framework\App\ObjectManager::getInstance();
    }

}
