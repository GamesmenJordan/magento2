<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Sales\Test\Fixture;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\RefundOrderInterface;
use Magento\TestFramework\Fixture\Api\ServiceFactory;
use Magento\TestFramework\Fixture\RevertibleDataFixtureInterface;

class Creditmemo implements RevertibleDataFixtureInterface
{
    private const DEFAULT_DATA = [
        'order_id' => null,
        'items' => [],
        'notify' => false,
        'append_comment' => false,
        'comment' => null,
        'arguments' => null,
    ];

    /**
     * @var ServiceFactory
     */
    private $serviceFactory;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $creditmemoRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param ServiceFactory $serviceFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        ServiceFactory $serviceFactory,
        CreditmemoRepositoryInterface $creditmemoRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->serviceFactory = $serviceFactory;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * {@inheritdoc}
     * @param array $data Parameters. Same format as Creditmemo::DEFAULT_DATA.
     * Fields structure fields:
     * - $data['items']: can be supplied in following formats:
     *      - array of arrays [["sku":"$product1.sku$","qty":1], ["sku":"$product2.sku$","qty":1]]
     *      - array of arrays [["order_item_id":"$oItem1.sku$","qty":1], ["order_item_id":"$oItem2.sku$","qty":1]]
     *      - array of arrays [["product_id":"$product1.id$","qty":1], ["product_id":"$product2.id$","qty":1]]
     *      - array of arrays [["quote_item_id":"$qItem1.id$","qty":1], ["quote_item_id":"$qItem2.id$","qty":1]]
     *      - array of SKUs ["$product1.sku$", "$product2.sku$"]
     *      - array of order items IDs ["$oItem1.id$", "$oItem2.id$"]
     *      - array of product instances ["$product1$", "$product2$"]
     */
    public function apply(array $data = []): ?DataObject
    {
        $service = $this->serviceFactory->create(RefundOrderInterface::class, 'execute');

        $invoiceId = $service->execute($this->prepareData($data));

        return $this->creditmemoRepository->get($invoiceId);
    }

    /**
     * @inheritdoc
     */
    public function revert(DataObject $data): void
    {
        $invoice = $this->creditmemoRepository->get($data->getId());
        $this->creditmemoRepository->delete($invoice);
    }

    /**
     * Prepare creditmemo data
     *
     * @param array $data
     * @return array
     */
    private function prepareData(array $data): array
    {
        $data = array_merge(self::DEFAULT_DATA, $data);
        $data['items'] = $this->prepareCreditmemoItems($data);

        return $data;
    }

    /**
     * Prepare creditmemo item
     *
     * @param array $data
     * @return array
     */
    private function prepareCreditmemoItems(array $data): array
    {
        $creditmemoItems = [];
        $order = $this->orderRepository->get($data['order_id']);
        $orderItemIdsBySku = [];
        $orderItemIdsByProductIds = [];
        $orderItemIdsByQuoteItemIds = [];
        foreach ($order->getItems() as $item) {
            $orderItemIdsBySku[$item->getSku()] = $item->getItemId();
            $orderItemIdsByQuoteItemIds[$item->getQuoteItemId()] = $item->getItemId();
            $orderItemIdsByProductIds[$item->getProductId()] = $item->getItemId();
        }

        foreach ($data['items'] as $itemToRefund) {
            $creditmemoItem = ['order_item_id' => null, 'qty' => 1];
            if (is_numeric($itemToRefund)) {
                $creditmemoItem['order_item_id'] = $itemToRefund;
            } elseif (is_string($itemToRefund)) {
                $creditmemoItem['order_item_id'] = $orderItemIdsBySku[$itemToRefund];
            } elseif ($itemToRefund instanceof ProductInterface) {
                $creditmemoItem['order_item_id'] = $orderItemIdsBySku[$itemToRefund->getSku()];
            } else {
                $creditmemoItem = array_intersect($itemToRefund, $creditmemoItem) + $creditmemoItem;
                if (isset($itemToRefund['sku'])) {
                    $creditmemoItem['order_item_id'] = $orderItemIdsBySku[$itemToRefund['sku']];
                } elseif (isset($itemToRefund['product_id'])) {
                    $creditmemoItem['order_item_id'] = $orderItemIdsByProductIds[$itemToRefund['product_id']];
                } elseif (isset($itemToRefund['quote_item_id'])) {
                    $creditmemoItem['order_item_id'] = $orderItemIdsByQuoteItemIds[$itemToRefund['quote_item_id']];
                }
            }
            $creditmemoItems[] = $creditmemoItem;
        }

        return $creditmemoItems;
    }
}
