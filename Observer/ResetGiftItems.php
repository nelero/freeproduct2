<?php

namespace C4B\FreeProduct\Observer;

use C4B\FreeProduct\SalesRule\Action\GiftAction;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;

/**
 * Observer for resetting gift cart items
 *
 * Note: Using quote->getAllItems() instead of the API getItems because they do not return the same items and sometimes
 * other functions will get old/cached items.
 *
 * @category   C4B
 * @package    C4B_FreeProduct
 * @author     Dominik Meglič <meglic@code4business.de>
 * @copyright  code4business Software GmbH
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class ResetGiftItems implements ObserverInterface
{
    /**
     * Delete all gift items. They will be re-added by SalesRule (If possible).
     *
     * @event sales_quote_address_collect_totals_before
     * @param Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        /** @var ShippingAssignmentInterface $shippingAssignment */
        $shippingAssignment = $observer->getEvent()->getData('shipping_assignment');
        /** @var Quote\Address $address */
        $address = $shippingAssignment->getShipping()->getAddress();

        if ($quote->getAllItems() == null || $address->getAddressType() != Quote\Address::TYPE_SHIPPING)
        {
            return;
        }

        $newShippingAssignment = [];

        /** @var Quote\Item $quoteItem */
        foreach ($quote->getAllItems() as $quoteItem)
        {
            if ($quoteItem->getOptionByCode(GiftAction::ITEM_OPTION_UNIQUE_ID) instanceof Quote\Item\Option)
            {
                $quoteItem->isDeleted(true);

                /**
                 * In some cases when the quoteItem is being deleted its option will be saved. It will fail because item_id
                 * is null.
                 */
                foreach ($quoteItem->getOptions() as $option)
                {
                    $option->isDeleted(true);
                }
            }
            else
            {
                /**
                 * Reset shipping assignment to prevent others from working on old items
                 * @see \Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector::processAppliedTaxes
                 * @see \Magento\Tax\Model\Plugin\OrderSave::saveOrderTax
                 */
                $newShippingAssignment[] = $quoteItem;
            }
        }

        $shippingAssignment->setItems($newShippingAssignment);
        $address->unsetData(GiftAction::APPLIED_FREEPRODUCT_RULE_IDS);
    }
}