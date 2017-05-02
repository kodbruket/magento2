<?php
/**
 * Mondido
 *
 * PHP version 5.6
 *
 * @category Mondido
 * @package  Mondido_Mondido
 * @author   Andreas Karlsson <andreas@kodbruket.se>
 * @license  MIT License https://opensource.org/licenses/MIT
 * @link     https://www.mondido.com
 */

namespace Mondido\Mondido\Observer;

use Magento\Framework\Event\ObserverInterface;

class SalesOrderPaymentPlaceEnd implements ObserverInterface
{
    /**
     * Execute
     *
     * @param \Magento\Framework\Event\Observer $observer Observer object
     *
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $payment = $observer->getPayment();
        $paymentMethod = $payment->getMethodInstance()->getCode();

        // Update order state and status
        if ($paymentMethod == 'mondido_paymentlink') {
            $payment->getOrder()
                ->setStatus('pending')
                ->setState(\Magento\Sales\Model\Order::STATE_NEW)
                ->save();
        }
    }
}
