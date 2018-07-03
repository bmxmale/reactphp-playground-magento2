<?php
declare(strict_types=1);

/**
 * File: ReportSender.php
 *
 * @author      Maciej Sławik <maciekslawik@gmail.com>
 * Github:      https://github.com/maciejslawik
 */

namespace MSlwk\ReactPhpPlayground\Model\Report;

use MSlwk\ReactPhpPlayground\Api\ReportSenderInterface;

/**
 * Class ReportSender
 * @package MSlwk\ReactPhpPlayground\Model\Report
 */
class ReportSender implements ReportSenderInterface
{
    /**
     * @param string $report
     * @return void
     */
    public function sendReport(string $report): void
    {
        /**
         * Report is being sent
         */
        sleep(1);
    }
}
