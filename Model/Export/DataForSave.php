<?php

namespace Arpo\Report\Model\Export;

use Magento\Framework\Filesystem;
use Magento\Sales\Model\OrderFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\Export\ConvertToCsv;
use Magento\Ui\Model\Export\MetadataProvider;

class DataForSave extends ConvertToCsv
{
    /** @var \Magento\Sales\Model\OrderFactory $orderFactory */
    protected $orderFactory;

    public function __construct(
        Filesystem $filesystem,
        Filter $filter,
        MetadataProvider $metadataProvider,
        OrderFactory $orderFactory,
        $pageSize = 200
    )
    {
        parent::__construct($filesystem, $filter, $metadataProvider, $pageSize);
        $this->orderFactory = $orderFactory;
    }

    public function getCsvFile()
    {
        $component = $this->filter->getComponent();
        $name = md5(microtime());
        $file = 'export/' . $component->getName() . $name . '.csv';

        $this->filter->prepareComponent($component);
        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();

        $fields = [];
        $fieldsName = $this->metadataProvider->getFields($component);
        foreach ($fieldsName as $fieldskey => $fieldsitem) {
            if ($fieldskey == 5) {
                $fields[] = 'name';
                $fields[] = 'base_subtotal_incl_tax';
                $fields[] = 'coupon_code';
                $fields[] = 'tax_percent';
                $fields[] = $fieldsitem;
            } else {
                $fields[] = $fieldsitem;
            }
        }

        $options = $this->metadataProvider->getOptions();
        $this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
        $stream->lock();

        $headers = [];
        $headersName = $this->metadataProvider->getHeaders($component);
        foreach ($headersName as $headerskey => $headersitem) {
            if ($headerskey == 5) {
                $headers[] = 'Product ';
                $headers[] = 'Price before coupon';
                $headers[] = 'Coupon ';
                $headers[] = 'Fee ';
                $headers[] = $headersitem;
            } else {
                $headers[] = $headersitem;
            }
        }

        $stream->writeCsv($headers);
        $i = 1;
        $searchCriteria = $dataProvider->getSearchCriteria()
            ->setCurrentPage($i)
            ->setPageSize($this->pageSize);
        $totalCount = (int)$dataProvider->getSearchResult()->getTotalCount();
        while ($totalCount > 0) {
            $items = $dataProvider->getSearchResult()->getItems();
            foreach ($items as $item) {
                if ($component->getName() == 'sales_order_grid') {
                    /** @var \Magento\Sales\Model\Order $order */
                    $order = $this->orderFactory->create()->loadByIncrementId($item->getIncrementId());
                    $allItems = $order->getAllItems();
                    $taxPercentArray = [];
                    $nameSkuArray = [];

                    foreach ($allItems as $key => $oneItem) {
                        $nameSkuArray[] = $oneItem->getName();
                        $nameSkuArray[] = ' (';
                        $nameSkuArray[] = $oneItem->getSku();
                        $nameSkuArray[] = '); ';
                        implode($nameSkuArray);
                        $taxPercentArray[] = round($oneItem->getTaxPercent());
                    }

                    $taxBeforeDiscountStatus = $order->getBaseSubtotalInclTax();
                    $coupon = $order->getCouponCode();
                    $nameSkuArray = implode($nameSkuArray);
                    $taxPercentStatus = implode('; ', $taxPercentArray);

                    $item->setName($nameSkuArray);
                    $item->setTaxPercent($taxPercentStatus);
                    $item->setBaseSubtotalInclTax(round($taxBeforeDiscountStatus, 2));
                    $item->setCoupon_code($coupon);
                }
                $this->metadataProvider->convertDate($item, $component->getName());
                $dataArray = $this->metadataProvider->getRowData($item, $fields, $options);
                $roundArray = [];
                foreach ($dataArray as $editItemKey => $editItemValue) {

                    if ($editItemKey == 9 || $editItemKey == 10 || $editItemKey == 17 || $editItemKey == 18) {
                        $roundArray[$editItemKey] = str_replace(array("\r\n", "\r", "\n"), ' ', round($editItemValue, 2));
                    } else {
                        $roundArray[$editItemKey] = str_replace(array("\r\n", "\r", "\n"), ' ', $editItemValue);
                    }
                }
                $niceArray = [];
                foreach ($roundArray as $niceItemKey => $niceItemValue) {

                    if ($niceItemKey == 6 || $niceItemKey == 9 || $niceItemKey == 10 || $niceItemKey == 17 || $niceItemKey == 18) {
                        $niceArray[$niceItemKey] = number_format($niceItemValue, 2, ',', '');
                    } else {
                        $niceArray[$niceItemKey] = $niceItemValue;
                    }
                }
                $stream->writeCsv($niceArray);
            }
            $searchCriteria->setCurrentPage(++$i);
            $totalCount = $totalCount - $this->pageSize;
        }
        $stream->unlock();
        $stream->close();

        return [
            'type' => 'filename',
            'value' => $file,
            'rm' => true  // can delete file after use
        ];
    }
}