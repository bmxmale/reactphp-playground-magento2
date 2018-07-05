<?php
declare(strict_types=1);

/**
 * File: StartWebapiReportingService.php
 *
 * @author      Maciej Sławik <maciekslawik@gmail.com>
 * Github:      https://github.com/maciejslawik
 */

namespace MSlwk\ReactPhpPlayground\Console\Command;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use MSlwk\ReactPhpPlayground\Api\CustomerIdsProviderInterface;
use MSlwk\ReactPhpPlayground\Api\TimerInterface;
use MSlwk\ReactPhpPlayground\Model\Adapter\ReactPHP\ClientFactory;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use React\HttpClient\Response;

/**
 * Class StartWebapiReportingService
 * @package MSlwk\ReactPhpPlayground\Console\Command
 */
class StartWebapiReportingService extends Command
{
    const COMMAND_NAME = 'mslwk:webapi-reporting-start';
    const ARGUMENT_NUMBER_OF_THREADS = 'threads';

    const API_ENDPOINT_PATH = 'rest/V1/mslwk/customer-report/generate';

    /**
     * @var TimerInterface
     */
    private $timer;

    /**
     * @var CustomerIdsProviderInterface
     */
    private $customerIdsProvider;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Json
     */
    private $jsonHandler;

    /**
     * @var null
     */
    private $name;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * StartWebapiReportingService constructor.
     * @param TimerInterface $timer
     * @param CustomerIdsProviderInterface $customerIdsProvider
     * @param Factory $loopFactory
     * @param ClientFactory $clientFactory
     * @param Json $jsonHandler
     * @param StoreManagerInterface $storeManager
     * @param null $name
     */
    public function __construct(
        TimerInterface $timer,
        CustomerIdsProviderInterface $customerIdsProvider,
        Factory $loopFactory,
        ClientFactory $clientFactory,
        Json $jsonHandler,
        StoreManagerInterface $storeManager,
        $name = null
    ) {
        parent::__construct($name);
        $this->timer = $timer;
        $this->customerIdsProvider = $customerIdsProvider;
        $this->loop = $loopFactory::create();
        $this->clientFactory = $clientFactory;
        $this->jsonHandler = $jsonHandler;
        $this->name = $name;
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Start asynchronous WebAPI reporting service')
            ->addArgument(
                self::ARGUMENT_NUMBER_OF_THREADS,
                InputArgument::REQUIRED,
                'Number of threads for running the export process'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $customerIds = $this->customerIdsProvider->getCustomerIds();
        $numberOfThreads = (int)$input->getArgument(self::ARGUMENT_NUMBER_OF_THREADS);

        $this->timer->startTimer();

        $this->startProcesses($customerIds, $numberOfThreads);

        $this->timer->stopTimer();

        $output->writeln("Process finished after {$this->timer->getExecutionTimeInSeconds()} seconds");
    }

    /**
     * @param array $customerIds
     * @param int $numberOfThreads
     */
    protected function startProcesses(array $customerIds, int $numberOfThreads): void
    {
        $numberOfChunks = $this->calculateNumberOfChunksForThreads($customerIds, $numberOfThreads);
        $threadedCustomerIds = array_chunk($customerIds, $numberOfChunks);
        foreach ($threadedCustomerIds as $customerIdsForSingleThread) {
            $this->createRequestDefinition($customerIdsForSingleThread);
        }
        $this->loop->run();
    }

    /**
     * @param int[] $customerIds
     */
    protected function createRequestDefinition(array $customerIds): void
    {
        $client = $this->clientFactory->create($this->loop);
        $data = $this->jsonHandler->serialize(['customerIds' => $customerIds]);
        $request = $client->request(
            'POST',
            $this->getRequestUrl(),
            [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($data)
            ]
        );
        $request->write($data);
        $request->on('response', function (Response $response) use (&$htmlObjectArray) {
            $data = '';
            $response->on(
                'data',
                function ($chunk) use (&$data) {
                    $data .= $chunk;
                }
            )->on(
                'end',
                function () use (&$data) {
                    foreach ($this->jsonHandler->unserialize($data) as $message) {
                        echo $message;
                    }
                }
            );
        });
        $request->end();
    }

    /**
     * @return string
     */
    protected function getRequestUrl(): string
    {
        return $this->storeManager->getStore()->getBaseUrl() . self::API_ENDPOINT_PATH;
    }

    /**
     * @param array $customerIds
     * @param int $numberOfThreads
     * @return int
     */
    protected function calculateNumberOfChunksForThreads(array $customerIds, int $numberOfThreads): int
    {
        $numberOfChunks = (int)(count($customerIds) / $numberOfThreads);
        return $numberOfChunks > 0 ? $numberOfChunks : 1;
    }
}
