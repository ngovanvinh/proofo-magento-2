<?php
/**
 * Avada
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the avada.io license that is
 * available through the world-wide-web at this URL:
 * https://www.avada.io/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Avada
 * @package     Avada_Proofo
 * @copyright   Copyright (c) Avada (https://www.avada.io/)
 * @license     https://www.avada.io/LICENSE.txt
 */

namespace Avada\Proofo\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Avada\Proofo\Helper\Data as Helper;
use \Magento\Directory\Model\CountryFactory;
use \Avada\Proofo\Helper\WebHookSync;
use Avada\Proofo\Model\Config\Webhooks;

class SyncOrder implements ObserverInterface
{
    /**
     * @var Helper
     */
    protected $_helperData;

    /**
     * @var CountryFactory
     */
    protected $_countryFactory;

    /**
     * @var WebHookSync
     */
    protected $_webHookSync;

    /**
     * SyncOrder constructor.
     *
     * @param Helper $helper
     * @param CountryFactory $countryFactory
     * @param WebHookSync $webHookSync
     */
    public function __construct(
        Helper $helper,
        CountryFactory $countryFactory,
        WebHookSync $webHookSync
    )
    {
        $this->_helperData = $helper;
        $this->_countryFactory = $countryFactory;
        $this->_webHookSync = $webHookSync;
    }

    /**
     * @param Observer $observer
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        try {
            if (!$this->_helperData->isEnabled()) {
                return $this;
            }

            $enabledWebHooks = $this->_helperData->getEnabledWebHooks();
            if (!in_array(Webhooks::ORDER_HOOK, $enabledWebHooks)) {
                return $this;
            }
            /**
             * @var $order  \Magento\Sales\Model\Order
             */
            $order = $observer->getEvent()->getOrder();

            /**
             * @var $billingAddress \Magento\Sales\Api\Data\OrderAddressInterface
             */
            $billingAddress = $order->getBillingAddress();
            $country = $this->_countryFactory->create()->load($billingAddress->getCountryId());
            $orderItems = $order->getAllVisibleItems();

            $lineItems = [];
            /**
             * @var $item \Magento\Sales\Model\Order\Item
             */
            foreach ($orderItems as $item) {
                if (
                    $item->getHasChildren() &&
                    $item->isChildrenCalculated() &&
                    $this->_helperData->getBundleAsMultipleItems()
                ) {
                    /** @var \Magento\Sales\Model\Order\Item $childItem */
                    foreach ($item->getChildrenItems() as $childItem) {
                        /** @var \Magento\Catalog\Model\Product $childProduct */
                        $childProduct = $childItem->getProduct();
                        $lineItems[] = [
                            "title" => $childProduct->getName(),
                            "quantity" => $item->getQtyOrdered(),
                            "price" => $childProduct->getPrice(),
                            "product_link" => $childProduct->getProductUrl(),
                            "product_image" => $this->_helperData->getProductImage($childProduct),
                            "product_id" => $childProduct->getId()
                        ];
                    }
                } else {
                    $product = $item->getProduct();
                    $lineItems[] = [
                        "title" => $item->getName(),
                        "quantity" => $item->getQtyOrdered(),
                        "price" => $item->getPrice(),
                        "product_link" => $product->getProductUrl(),
                        "product_image" => $this->_helperData->getProductImage($product),
                        "product_id" => $product->getId()
                    ];
                }
            }

            $createdAt = $order->getUpdatedAt() === null
                ? date("c")
                : date("c", strtotime($order->getUpdatedAt()));
            $hookData = [
                "billing_address" => [
                    "city" => $billingAddress->getCity(),
                    "country" => $country->getName(),
                    "first_name" => $billingAddress->getFirstname(),
                    "last_name" => $billingAddress->getLastname(),
                ],
                "created_at" => $createdAt,
                "line_items" => $lineItems
            ];
            $this->_webHookSync->syncToWebHook($hookData, WebHookSync::ORDER_WEBHOOK, WebHookSync::ORDER_CREATE_TOPIC);
        } catch (\Exception $e) {
            $this->_helperData->criticalLog($e->getMessage());
        }
    }
}
