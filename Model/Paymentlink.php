<?php

namespace Mondido\Mondido\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\MethodInterface;

class Paymentlink extends AbstractMethod implements MethodInterface
{
    /**
     * @var string
     */
    protected $_code = "mondido_paymentlink";

    /**
     * @var string
     */
    protected $_infoBlockType = \Mondido\Mondido\Block\Info\Paymentlink::class;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isOffline = false;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canOrder = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_isInitializeNeeded = false;

    /**
     * Authorize
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment Payment
     * @param float                                       $amount  Amount
     *
     * @return $this
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // Fetch object manager
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        // Fetch instance of Mondido Transaction
        $mondidoTransaction = $objectManager->get('Mondido\Mondido\Model\Api\Transaction');

        // Get the order
        $order = $payment->getOrder();

        // Build the result
        $result = $mondidoTransaction->create($payment->getOrder()->getQuoteId());
        $result = json_decode($result);

        if (property_exists($result, 'code') && $result->code != 200) {
            $message = sprintf(
                __("Mondido returned error code %d: %s (%s)"),
                $result->code,
                $result->description,
                $result->name
            );
            throw new \Magento\Framework\Exception\LocalizedException(__($message));
        }

        $payment->setTransactionId($result->id)->setIsTransactionClosed(false);
        $payment->setAdditionalInformation('id', $result->id);
        $payment->setAdditionalInformation('href', $result->href);
        $payment->setAdditionalInformation('status', $result->status);

        return $this;
    }

    /**
     * Order payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment Payment
     * @param float                                       $amount  Amount
     *
     * @return $this
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * Capture payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment Payment
     * @param float                                       $amount  Amount
     *
     * @return $this
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        die('xxx capture');
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return true;

        return parent::isAvailable($quote);
    }
}