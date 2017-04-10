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

namespace Mondido\Mondido\Controller\Paymentlink;

/**
 * Redirect action
 *
 * @category Mondido
 * @package  Mondido_Mondido
 * @author   Robert Lord <robert@codepeak.se>
 * @license  MIT License https://opensource.org/licenses/MIT
 * @link     https://www.mondido.com
 */
class Redirect extends \Magento\Checkout\Controller\Onepage
{
    /**
     * Execute
     *
     * @return void
     */
    public function execute()
    {
        die('redirect');
        // Get session
        $session = $this->getOnepage()->getCheckout();

        // Get quote
        $quote = $this->getOnepage()->getQuote();
        $quote->reserveOrderId()->setIsActive(0)->save();

        $reservedOrderId = $quote->getReservedOrderId();
        $quoteId = $quote->getId();

        $session->setLastQuoteId($quoteId)
            ->setLastSuccessQuoteId($quoteId)
            ->clearHelperData();

        $session->setLastRealOrderId($reservedOrderId);

        $resultPage = $this->resultPageFactory->create();

        return $resultPage;
    }
}
