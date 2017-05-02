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

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Mondido\Mondido\Helper\Iso;
use Mondido\Mondido\Model\Api\Transaction;
use Mondido\Mondido\Helper\Data;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Newsletter\Model\Subscriber;

/**
 * Payment action
 *
 * @category Mondido
 * @package  Mondido_Mondido
 * @author   Andreas Karlsson <andreas@kodbruket.se>
 * @license  MIT License https://opensource.org/licenses/MIT
 * @link     https://www.mondido.com
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $quoteManagement;

    /**
     * @var \Mondido\Mondido\Helper\Iso
     */
    protected $isoHelper;

    /**
     * @var \Mondido\Mondido\Model\Api\Transaction
     */
    protected $transaction;

    /**
     * @var \Mondido\Mondido\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Newsletter\Model\Subscriber
     */
    protected $subscriber;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context               $context           Context object
     * @param \Magento\Framework\Controller\Result\JsonFactory    $resultJsonFactory Result factory
     * @param \Psr\Log\LoggerInterface                            $logger            Logger interface
     * @param \Magento\Quote\Api\CartRepositoryInterface          $quoteRepository   Cart repository interface
     * @param \Magento\Quote\Api\CartManagementInterface          $quoteManagement   Cart management interface
     * @param \Mondido\Mondido\Helper\Iso                         $isoHelper         ISO helper
     * @param \Mondido\Mondido\Api\Transaction                    $transaction       Transaction API model
     * @param \Mondido\Mondido\Helper\Data                        $helper            Data helper
     * @param \Magento\Sales\Model\Order                          $order             Order model
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender       Order seder
     * @param \Magento\Newsletter\Model\Subscriber                $subscriber        Subscriber model
     *
     * @return void
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        CartRepositoryInterface $quoteRepository,
        CartManagementInterface $quoteManagement,
        Iso $isoHelper,
        Transaction $transaction,
        Data $helper,
        Order $order,
        OrderSender $orderSender,
        Subscriber $subscriber
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->isoHelper = $isoHelper;
        $this->transaction = $transaction;
        $this->helper = $helper;
        $this->order = $order;
        $this->orderSender = $orderSender;
        $this->subscriber = $subscriber;
    }

    /**
     * Execute
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $this->logger->debug(var_export($data, true));

        $result = [];
        $resultJson = $this->resultJsonFactory->create();

        if (array_key_exists('status', $data) && in_array($data['status'], ['authorized'])) {
            $quoteId = $data['payment_ref'];
            $orderObject = $this->order->loadByAttribute('quote_id', $quoteId);
            $incrementId = $orderObject->getIncrementId();

            if ($incrementId) {
                try {
                    $transactionJson = $this->transaction->show($data['id']);
                    $transaction = json_decode($transactionJson);

                    if (is_object($transaction) && $transaction->status == 'authorized') {
                        $orderObject
                            ->setStatus('processing')
                            ->setState('processing')
                            ->save();

                        $result['order_ref'] = $incrementId;
                    } else {
                        $result['error'] = 'Order not in authorized state';
                    }

                } catch (\Exception $e) {
                    $this->logger->debug($e->getMessage());
                    $result['error'] = $e->getMessage();
                }
            }
        }

        $response = json_encode($result);
        $resultJson->setData($response);

        return $resultJson;
    }
}
