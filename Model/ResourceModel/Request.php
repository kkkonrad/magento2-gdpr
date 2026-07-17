<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Request extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('kkkonrad_gdpr_request', 'request_id');
    }
}
