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
namespace Avada\Proofo\Controller\Adminhtml\Webhook;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use \Magento\Framework\Controller\Result\JsonFactory;
use \Magento\Framework\Json\Helper\Data as JsonHelper;
use \Avada\Proofo\Helper\WebHookSync;

/**
 * Class TestConnection
 * @package Avada\Proofo\Controller\Adminhtml\Webhook
 */
class TestConnection extends Action
{
    /**
     * @var JsonHelper
     */
    protected $jsonHelper;

    /**
     * @var WebHookSync
     */
    protected $_webHookSync;

    /**
     * TestConnection constructor.
     * @param Context $context
     * @param JsonHelper $jsonHelper
     * @param WebHookSync $webHookSync
     */
    public function __construct(
        Context $context,
        JsonHelper $jsonHelper,
        WebHookSync $webHookSync
    )
    {
        $this->jsonHelper = $jsonHelper;
        $this->_webHookSync = $webHookSync;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $this->_webHookSync->syncToWebHook([], WebHookSync::ORDER_WEBHOOK, WebHookSync::ORDER_CREATE_TOPIC, true);
            $this->_webHookSync->syncToWebHook([], WebHookSync::CUSTOMER_WEBHOOK, WebHookSync::CUSTOMER_CREATE_TOPIC, true);
            $this->_webHookSync->syncToWebHook([], WebHookSync::CART_WEBHOOK, WebHookSync::CART_UPDATE_TOPIC, true);

            $result = $this->jsonHelper->jsonEncode([
                'status'  => true,
                "content" => "Webhook connection is working properly"
            ]);

            return $this->getResponse()->representJson($result);
        } catch (\Exception $e) {
            $result = $this->jsonHelper->jsonEncode([
                'status'  => false,
                "content" => $e->getMessage()
            ]);

            return $this->getResponse()->representJson($result);
        }
    }
}
