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

namespace Mondido\Mondido\Model\Api;

use Magento\Framework\UrlInterface;
use Mondido\Mondido\Helper\Iso;
use Magento\Framework\HTTP\Adapter\Curl;
use Mondido\Mondido\Model\Config;
use Magento\Store\Model\StoreManagerInterface;
use Mondido\Mondido\Helper\Data;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ShippingMethodManagement;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;

/**
 * Mondido transaction API model
 *
 * @category Mondido
 * @package  Mondido_Mondido
 * @author   Andreas Karlsson <andreas@kodbruket.se>
 * @license  MIT License https://opensource.org/licenses/MIT
 * @link     https://www.mondido.com
 */
class Transaction extends Mondido
{
    public $resource = 'transactions';
    protected $_config = 'config';
    protected $_storeManager;
    protected $urlBuilder;
    protected $helper;
    protected $quoteRepository;

    /**
     * @var \Mondido\Mondido\Helper\Iso
     */
    protected $isoHelper;

    /**
     * @var State
     */
    protected $state;

    /**
     * Transaction constructor.
     *
     * @param Curl                     $adapter
     * @param Config                   $config
     * @param StoreManagerInterface    $storeManager
     * @param UrlInterface             $urlBuilder
     * @param Data                     $helper
     * @param CartRepositoryInterface  $quoteRepository
     * @param Iso                      $isoHelper
     * @param ShippingMethodManagement $shippingMethodManagement
     * @param State                    $state
     */
    public function __construct(
        Curl $adapter,
        Config $config,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        Data $helper,
        CartRepositoryInterface $quoteRepository,
        Iso $isoHelper,
        ShippingMethodManagement $shippingMethodManagement,
        State $state
    ) {
        $this->_adapter = $adapter;
        $this->_config = $config;
        $this->_storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->helper = $helper;
        $this->quoteRepository = $quoteRepository;
        $this->isoHelper = $isoHelper;
        $this->shippingMethodManagement = $shippingMethodManagement;
        $this->state = $state;
    }

    /**
     * Create hash
     *
     * @param Magento\Quote\Model\Quote $quote Quote object
     *
     * @return string
     */
    protected function _createHash(\Magento\Quote\Model\Quote $quote)
    {
        $hashRecipe = [
            'merchant_id'  => $this->_config->getMerchantId(),
            'payment_ref'  => $quote->getId(),
            'customer_ref' => $quote->getCustomerId() ? $quote->getCustomerId() : '',
            'amount'       => $this->helper->formatNumber($quote->getBaseGrandTotal()),
            'currency'     => strtolower($quote->getBaseCurrencyCode()),
            'test'         => $this->_config->isTest() ? 'test' : '',
            'secret'       => $this->_config->getSecret()
        ];

        $hash = md5(implode($hashRecipe));

        return $hash;
    }

    /**
     * Create transaction
     *
     * @param int|Magento\Quote\Model\Quote $quote A quote object or ID
     *
     * @return string
     */
    public function create($quote)
    {
        if (!is_object($quote)) {
            $quote = $this->quoteRepository->get($quote);
        }

        $method = 'POST';

        $webhook = [
            'url'         => $this->_storeManager->getStore()->getUrl('mondido/payment'),
            'trigger'     => 'payment',
            'http_method' => 'post',
            'data_format' => 'form_data'
        ];

        $metaData = $this->getMetaData($quote);
        $transactionItems = $this->getItems($quote);
        $shippingAddress = $quote->getShippingAddress('shipping');

        // Generate URLs depending on area
        if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
            $successUrl = $this->_storeManager->getStore()->getUrl(
                'mondido/paymentlink/redirect',
                ['_nosid' => true]
            );
            $errorUrl = $this->_storeManager->getStore()->getUrl(
                'mondido/paymentlink/error',
                ['_nosid' => true]
            );
        } else {
            $successUrl = $this->urlBuilder->getUrl('mondido/checkout/redirect');
            $errorUrl = $this->urlBuilder->getUrl('mondido/checkout/error');
        }

        $data = [
            'merchant_id'     => $this->_config->getMerchantId(),
            'amount'          => $this->helper->formatNumber($quote->getBaseGrandTotal()),
            'vat_amount'      => $this->helper->formatNumber($shippingAddress->getBaseTaxAmount()),
            'payment_ref'     => $quote->getId(),
            'test'            => $this->_config->isTest() ? 'true' : 'false',
            'metadata'        => $metaData,
            'currency'        => strtolower($quote->getBaseCurrencyCode()),
            'hash'            => $this->_createHash($quote),
            'process'         => 'false',
            'success_url'     => $successUrl,
            'error_url'       => $errorUrl,
            'authorize'       => 'true',
            'items'           => json_encode($transactionItems),
            'webhook'         => json_encode($webhook),
            'payment_details' => $metaData['user']
        ];

        if ($quote->getCustomerId()) {
            $data['customer_ref'] = $quote->getCustomerId();
        }

        return $this->call($method, $this->resource, null, $data);
    }

    /**
     * Update transaction
     *
     * @param int|Magento\Quote\Model\Quote $quote A quote object or ID
     *
     * @return string|boolean
     */
    public function update($quote)
    {
        if (!is_object($quote)) {
            $quote = $this->quoteRepository->getActive($quote);
        }

        $transaction = json_decode($quote->getMondidoTransaction());

        if (property_exists($transaction, 'id')) {
            $id = $transaction->id;
        } else {
            return false;
        }

        $method = 'PUT';

        $metaData = $this->getMetaData($quote);
        $transactionItems = $this->getItems($quote);
        $shippingAddress = $quote->getShippingAddress('shipping');

        $data = [
            'amount'     => $this->helper->formatNumber($quote->getBaseGrandTotal()),
            'vat_amount' => $this->helper->formatNumber($shippingAddress->getBaseTaxAmount()),
            'metadata'   => $metaData,
            'currency'   => strtolower($quote->getBaseCurrencyCode()),
            'hash'       => $this->_createHash($quote),
            'items'      => json_encode($transactionItems),
            'process'    => 'false'
        ];

        if ($quote->getCustomerId()) {
            $data['customer_ref'] = $quote->getCustomerId();
        }

        return $this->call($method, $this->resource, (string) $id, $data);
    }

    /**
     * Capture transaction
     *
     * @param \Magento\Sales\Model\Order $order  Order
     * @param float                      $amount Amount to capture
     *
     * @return string
     */
    public function capture(\Magento\Sales\Model\Order $order, $amount)
    {
        $method = 'PUT';

        $transaction = json_decode($order->getMondidoTransaction());

        if (property_exists($transaction, 'id')) {
            $id = $transaction->id;
        } else {
            return false;
        }

        // Assure remote transaction only is reserved
        $currentTransactionJson = $this->show($id);
        $currentTransaction = json_decode($currentTransactionJson);

        if (is_object($currentTransaction) && property_exists($currentTransaction, 'status')) {
            if ($currentTransaction->status == 'authorized') {
                $data = ['amount' => $this->helper->formatNumber($amount)];

                return $this->call($method, $this->resource, [$id, 'capture'], $data);
            } else {
                return $currentTransactionJson;
            }
        }

        return true;
    }

    /**
     * Capture transaction
     *
     * @param \Magento\Sales\Model\Order $order  Order
     * @param float                      $amount Amount to capture
     *
     * @return string
     */
    public function refund(\Magento\Sales\Model\Order $order, $amount)
    {
        $method = 'POST';

        $transaction = json_decode($order->getMondidoTransaction());

        if (property_exists($transaction, 'id')) {
            $id = $transaction->id;
        } else {
            return false;
        }

        $data = [
            'amount'         => $this->helper->formatNumber($amount),
            'reason'         => 'Refund from Magento',
            'transaction_id' => $id
        ];

        return $this->call($method, 'refunds', null, $data);
    }

    /**
     * Show transaction
     *
     * @param int $id Transaction ID
     *
     * @return string
     */
    public function show($id)
    {
        $method = 'GET';

        return $this->call($method, $this->resource, (string) $id);
    }

    /**
     * Get items
     *
     * @param Magento\Quote\Model\Quote $quote A quote object
     *
     * @return array
     */
    protected function getItems($quote)
    {
        $quoteItems = $quote->getAllVisibleItems();

        $transactionItems = [];

        foreach ($quoteItems as $item) {
            $transactionItems[] = [
                'artno'       => $item->getSku(),
                'description' => $item->getName(),
                'qty'         => $item->getQty(),
                'amount'      => $this->helper->formatNumber(
                    $item->getBaseRowTotalInclTax() - $item->getBaseDiscountAmount()
                ),
                'vat'         => $this->helper->formatNumber($item->getTaxPercent()),
                'discount'    => $this->helper->formatNumber($item->getBaseDiscountAmount())
            ];
        }

        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress('shipping');
            $baseShippingAmount = $shippingAddress->getBaseShippingAmount();

            if ($baseShippingAmount > 0) {
                $shippingVat = $baseShippingAmount / ($baseShippingAmount - $shippingAddress->getBaseShippingTaxAmount(
                        ));
            } else {
                $shippingVat = 0;
            }

            $transactionItems[] = [
                'artno'       => $shippingAddress->getShippingMethod(),
                'description' => $shippingAddress->getShippingDescription(),
                'qty'         => 1,
                'amount'      => $this->helper->formatNumber($baseShippingAmount),
                'vat'         => $this->helper->formatNumber($shippingVat),
                'discount'    => $this->helper->formatNumber($shippingAddress->getBaseShippingDiscountAmount())
            ];
        }

        return $transactionItems;
    }

    /**
     * Get meta data
     *
     * @param Magento\Quote\Model\Quote $quote A quote object
     *
     * @return array
     */
    protected function getMetaData($quote)
    {
        $shippingAddress = $quote->getShippingAddress('shipping');

        $shippingMethods = $this->shippingMethodManagement->getList($quote->getId());

        $shippingData = [];

        foreach ($shippingMethods as $shippingMethod) {
            $shippingData[] = [
                'carrier_code'    => $shippingMethod->getCarrierCode(),
                'method_code'     => $shippingMethod->getMethodCode(),
                'carrier_title'   => $shippingMethod->getCarrierTitle(),
                'method_title'    => $shippingMethod->getMethodTitle(),
                'amount'          => $shippingMethod->getAmount(),
                'base_amount'     => $shippingMethod->getBaseAmount(),
                'available'       => $shippingMethod->getAvailable(),
                'error_message'   => $shippingMethod->getErrorMessage(),
                'price_excl_tax'  => $shippingMethod->getPriceExclTax(),
                'getPriceInclTax' => $shippingMethod->getPriceInclTax()
            ];
        }

        $paymentDetails = [
            'email'        => $shippingAddress->getEmail(),
            'phone'        => $shippingAddress->getTelephone(),
            'first_name'   => $shippingAddress->getFirstname(),
            'last_name'    => $shippingAddress->getLastname(),
            'zip'          => $shippingAddress->getPostcode(),
            'address_1'    => $shippingAddress->getStreetLine(1),
            'address_2'    => $shippingAddress->getStreetLine(2),
            'city'         => $shippingAddress->getCity(),
            'country_code' => $this->isoHelper->transform($shippingAddress->getCountryId())
        ];

        $allowedCountries = $this->_config->getAllowedCountries();
        $defaultCountry = $this->_config->getDefaultCountry();

        $data = [
            'user'     => $paymentDetails,
            'products' => $this->getItems($quote),
            'magento'  => [
                'edition'          => $this->_config->getMagentoEdition(),
                'version'          => $this->_config->getMagentoVersion(),
                'php'              => phpversion(),
                'module'           => $this->_config->getModuleInformation(),
                'configuration'    => [
                    'general' => [
                        'country' => [
                            'allow'   => $allowedCountries,
                            'default' => $defaultCountry
                        ]
                    ]
                ],
                'shipping_methods' => $shippingData
            ]
        ];

        return $data;
    }
}
