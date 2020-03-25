<?php

namespace Arpo\Report\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Arpo\Report\Model\Export\DataForSave;
use Magento\Framework\App\Response\Http\FileFactory;


class CustomExport extends Action
{

    protected $converter;

    protected $fileFactory;

    public function __construct(
        Context $context,
        DataForSave $converter,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->converter = $converter;
        $this->fileFactory = $fileFactory;
    }

    public function execute()
    {
        return $this->fileFactory->create('Orders_by_CouponCode.csv', $this->converter->getCsvFile(), 'var');
    }
}