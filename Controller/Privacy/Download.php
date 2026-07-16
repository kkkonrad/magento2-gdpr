<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Privacy;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class Download implements HttpGetActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly RequestInterface $request,
        private readonly ResourceConnection $resourceConnection,
        private readonly DirectoryList $directoryList,
        private readonly FileFactory $fileFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly HttpResponse $response
    ) {
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        $exportId = (int)$this->request->getParam('id');
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $exportTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_export');
        $connection = $this->resourceConnection->getConnection();
        $export = $connection->fetchRow(
            $connection->select()
                ->from(['export' => $exportTable])
                ->joinInner(['request' => $requestTable], 'request.request_id = export.request_id', [])
                ->where('export.export_id = ?', $exportId)
                ->where('request.customer_id = ?', (int)$this->customerSession->getCustomerId())
                ->where('export.expires_at > UTC_TIMESTAMP()')
        );
        if ($export === false) {
            throw NoSuchEntityException::singleField('export_id', $exportId);
        }
        $relativePath = ltrim((string)$export['storage_path'], '/');
        $varPath = realpath($this->directoryList->getPath(DirectoryList::VAR_DIR));
        $filePath = realpath($this->directoryList->getPath(DirectoryList::VAR_DIR) . '/' . $relativePath);
        if ($varPath === false || $filePath === false || !str_starts_with($filePath, $varPath . DIRECTORY_SEPARATOR)) {
            throw NoSuchEntityException::singleField('export_id', $exportId);
        }
        $connection->update($exportTable, ['downloaded_at' => gmdate('Y-m-d H:i:s')], ['export_id = ?' => $exportId]);
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $this->response->setHeader('Pragma', 'no-cache', true);
        $this->response->setHeader('X-Content-Type-Options', 'nosniff', true);

        return $this->fileFactory->create(
            'personal-data-export.zip',
            ['type' => 'filename', 'value' => $relativePath, 'rm' => false],
            DirectoryList::VAR_DIR,
            'application/zip'
        );
    }
}
